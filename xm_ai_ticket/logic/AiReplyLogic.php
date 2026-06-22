<?php
namespace addon\xm_ai_ticket\logic;

use addon\xm_ai_ticket\model\AiTicketModelModel;
use addon\xm_ai_ticket\model\AiTicketDepartmentModel;
use addon\xm_ai_ticket\model\AiTicketReplyModel;
use addon\xm_ai_ticket\model\AiTicketStatusModel;
use addon\xm_ai_ticket\model\AiTicketHostCreateMonitorModel;
use addon\xm_ai_ticket\model\AiTicketToolModel;
use addon\xm_ai_ticket\XmAiTicket;
use think\facade\Db;

/**
 * AI回复核心逻辑
 */
class AiReplyLogic
{
    /**
     * 处理自动回复 - 由定时任务调用
     */
    public function processAutoReply()
    {
        $plugin = new XmAiTicket();
        $config = $plugin->getConfig();
        if (empty($config['enable'])) {
            return;
        }

        $statusModel = new AiTicketStatusModel();
        $tickets = $statusModel->findTicketsNeedAiReply();

        foreach ($tickets as $item) {
            $ticketId = $item['ticket']['id'];

            // 原子性抢占：先把工单状态改为5(处理中)，防止其他进程重复处理
            // 只有状态还是1或2时才更新（条件更新，保证原子性）
            $affected = Db::name('addon_idcsmart_ticket')
                ->where('id', $ticketId)
                ->whereIn('status', [1, 2])
                ->update(['status' => 5, 'update_time' => time()]);

            if ($affected === 0) {
                // 已被其他进程抢占，跳过
                continue;
            }

            // 标记AI状态
            $statusModel->updateAiReplyTime($ticketId);

            try {
                $this->generateAndReply($item['ticket'], $item['dept_config'], $config);
            } catch (\Exception $e) {
                $this->logError($ticketId, $e->getMessage());
                // 回复失败时恢复工单状态为2(用户已回复)，让下次cron可以重试
                Db::name('addon_idcsmart_ticket')->where('id', $ticketId)->update(['status' => 2, 'update_time' => time()]);
            }
        }

        // 处理已转人工但客户还在发消息的工单：AI自动回复等待提示
        $transferTickets = $statusModel->findTransferWaitReply();
        foreach ($transferTickets as $item) {
            $ticketId = $item['ticket']['id'];

            $affected = Db::name('addon_idcsmart_ticket')
                ->where('id', $ticketId)
                ->where('status', 2)
                ->update(['status' => 5, 'update_time' => time()]);

            if ($affected === 0) {
                continue;
            }

            try {
                $this->writeTransferWaitReply($ticketId, $config);
            } catch (\Exception $e) {
                $this->logError($ticketId, '转人工等待回复失败: ' . $e->getMessage());
                Db::name('addon_idcsmart_ticket')->where('id', $ticketId)->update(['status' => 2, 'update_time' => time()]);
            }
        }
    }

    /**
     * 生成AI回复并写入工单
     */
    public function generateAndReply($ticket, $deptConfig, $globalConfig = [])
    {
        if (empty($globalConfig)) {
            $plugin = new XmAiTicket();
            $globalConfig = $plugin->getConfig();
        }

        $ticketId = $ticket['id'];

        // 获取AI模型配置
        $aiModel = AiTicketModelModel::where('id', $deptConfig['ai_model_id'])->where('status', 1)->find();
        if (empty($aiModel)) {
            $aiModel = (new AiTicketModelModel())->getDefaultModel();
            if (empty($aiModel)) {
                $this->logError($ticketId, '无可用AI模型');
                return;
            }
        }

        $modelConfig = $aiModel->toArray();

        // 检查模型是否支持工具调用
        $supportsToolCall = !empty($aiModel['supports_tool_call']);
        $tools = [];
        $useToolCalling = false;

        if ($supportsToolCall) {
            // 加载启用的工具
            $toolModel = new AiTicketToolModel();
            $tools = $toolModel->getActiveTools();
            if (!empty($tools)) {
                $useToolCalling = true;
            }
        }

        // 构建上下文（工具调用模式不注入客户信息，由AI按需查询）
        $contextMaxChars = intval($globalConfig['context_max_chars'] ?? 5120);
        $messages = $this->buildMessages($ticketId, $ticket, $deptConfig, $contextMaxChars, $globalConfig, $useToolCalling);

        // 多轮工具调用（最多3轮）
        $maxToolRounds = 3;
        $toolRound = 0;
        $toolResults = [];
        $finalContent = '';
        $finalTokensUsed = 0;

        while ($toolRound < $maxToolRounds) {
            // 调用AI
            $result = $this->callAi($modelConfig, $messages, $useToolCalling ? $tools : []);

            if (!$result['success']) {
                // 如果工具调用失败，尝试回退到预注入模式
                if ($useToolCalling && $toolRound === 0) {
                    $this->logError($ticketId, '工具调用失败，回退到预注入模式: ' . $result['error']);
                    $useToolCalling = false;
                    $messages = $this->buildMessages($ticketId, $ticket, $deptConfig, $contextMaxChars, $globalConfig, false);
                    $result = $this->callAi($modelConfig, $messages, []);
                    if (!$result['success']) {
                        $this->logError($ticketId, $result['error']);
                        return;
                    }
                    $finalContent = $result['content'];
                    $finalTokensUsed = $result['tokens_used'] ?? 0;
                    break;
                }
                $this->logError($ticketId, $result['error']);
                return;
            }

            // 检查是否有工具调用
            if ($useToolCalling && !empty($result['tool_calls'])) {
                $toolRound++;

                // 执行工具调用
                $toolModel = new AiTicketToolModel();
                $toolCallResults = [];

                foreach ($result['tool_calls'] as $toolCall) {
                    $toolName = $toolCall['name'] ?? '';
                    $toolParams = $toolCall['arguments'] ?? [];

                    if (is_string($toolParams)) {
                        $toolParams = json_decode($toolParams, true) ?: [];
                    }

                    $execResult = $toolModel->executeTool($toolName, $toolParams, $ticket);
                    $toolCallResults[] = [
                        'name'   => $toolName,
                        'call_id' => $toolCall['id'] ?? '',
                        'result' => $execResult['result'] ?? '',
                        'success' => $execResult['success'] ?? false,
                    ];
                }

                // 将工具结果添加到消息中
                if ($modelConfig['provider'] === 'anthropic') {
                    // Anthropic格式：添加assistant消息和tool_result
                    $assistantContent = [];
                    foreach ($result['tool_calls'] as $tc) {
                        $assistantContent[] = [
                            'type' => 'tool_use',
                            'id' => $tc['id'] ?? '',
                            'name' => $tc['name'] ?? '',
                            'input' => is_string($tc['arguments']) ? json_decode($tc['arguments'], true) ?: [] : $tc['arguments'],
                        ];
                    }
                    $messages[] = ['role' => 'assistant', 'content' => $assistantContent];

                    // 添加tool_result
                    $toolResultContent = [];
                    foreach ($toolCallResults as $tcr) {
                        $toolResultContent[] = [
                            'type' => 'tool_result',
                            'tool_use_id' => $tcr['call_id'],
                            'content' => $tcr['result'],
                        ];
                    }
                    $messages[] = ['role' => 'user', 'content' => $toolResultContent];
                } else {
                    // OpenAI格式：添加assistant消息和tool消息
                    // 重建原生OpenAI tool_calls格式（含type和function包装）
                    $nativeToolCalls = [];
                    foreach ($result['tool_calls'] as $tc) {
                        $nativeToolCalls[] = [
                            'id' => $tc['id'] ?? '',
                            'type' => 'function',
                            'function' => [
                                'name' => $tc['name'] ?? '',
                                'arguments' => is_array($tc['arguments']) ? json_encode($tc['arguments']) : ($tc['arguments'] ?? '{}'),
                            ],
                        ];
                    }
                    $assistantMsg = [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => $nativeToolCalls,
                    ];
                    $messages[] = $assistantMsg;

                    foreach ($toolCallResults as $tcr) {
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $tcr['call_id'],
                            'name' => $tcr['name'],
                            'content' => $tcr['result'],
                        ];
                    }
                }

                // 继续下一轮调用
                continue;
            }

            // 没有工具调用，获取最终回复
            $finalContent = $result['content'];
            $finalTokensUsed = $result['tokens_used'] ?? 0;
            break;
        }

        // 如果达到最大轮次仍有工具调用，取最后一次的内容
        if (empty($finalContent) && !empty($result['content'])) {
            $finalContent = $result['content'];
            $finalTokensUsed = $result['tokens_used'] ?? 0;
        }

        if (empty($finalContent)) {
            $this->logError($ticketId, 'AI返回内容为空');
            return;
        }

        // 处理回复内容
        $replyContent = $finalContent;

        // 检查是否需要转交高级技术人员
        // 优先级1：AI主动判断需要转交（回复中包含 [TRANSFER] 标记）
        // 优先级2：用户消息中明确要求转人工（兜底）
        $needTransfer = false;

        if (mb_strpos($replyContent, '[TRANSFER]') !== false) {
            $needTransfer = true;
            // 从回复中移除标记，客户不可见
            $replyContent = str_replace('[TRANSFER]', '', $replyContent);
            $replyContent = trim($replyContent);
        }

        // 兜底：用户消息明确要求转人工
        if (!$needTransfer) {
            $transferKeywords = explode(',', $globalConfig['transfer_keywords'] ?? '转人工,人工客服,人工服务,转接人工');
            $lastClientReply = Db::name('addon_idcsmart_ticket_reply')
                ->where('ticket_id', $ticketId)
                ->where('type', 'Client')
                ->order('create_time', 'desc')
                ->find();
            if (!empty($lastClientReply)) {
                $clientMsg = htmlspecialchars_decode($lastClientReply['content']);
                foreach ($transferKeywords as $keyword) {
                    $keyword = trim($keyword);
                    if (!empty($keyword) && mb_strpos($clientMsg, $keyword) !== false) {
                        $needTransfer = true;
                        break;
                    }
                }
            }
        }

        // 截断回复内容（如果AI超长，优雅截断）
        $maxChars = intval($deptConfig['max_reply_chars'] ?? 512);
        if (mb_strlen($replyContent) > $maxChars) {
            $replyContent = mb_substr($replyContent, 0, $maxChars - 3) . '...';
        }

        // 追加结束语
        $closingRemark = $deptConfig['closing_remark'] ?? '';
        if (!empty($closingRemark)) {
            $replyContent .= "\n" . $closingRemark;
        }

        // 通过工单插件的replyTicket方法写入回复
        $writeResult = $this->writeTicketReply($ticketId, $replyContent, $aiModel->id, $messages, $finalContent, $finalTokensUsed);

        if ($writeResult) {
            // 如果需要转人工
            if ($needTransfer) {
                $statusModel = new AiTicketStatusModel();
                $statusModel->transferToHuman($ticketId, 0, '客户需要高级技术人员处理');

                // 转人工通知处理
                $transferMethod = intval($globalConfig['transfer_method'] ?? 1);

                // 飞书通知（方式2或4）
                if ($transferMethod === 2 || $transferMethod === 4) {
                    $webhook = $globalConfig['feishu_webhook'] ?? '';
                    if (!empty($webhook)) {
                        // 获取工单部门名称
                        $ticketType = Db::name('addon_idcsmart_ticket_type')->where('id', $ticket['ticket_type_id'])->find();
                        $typeName = !empty($ticketType) ? $ticketType['name'] : '未知';

                        // 获取客户最后一条消息
                        $lastClientReply2 = Db::name('addon_idcsmart_ticket_reply')
                            ->where('ticket_id', $ticketId)
                            ->where('type', 'Client')
                            ->order('create_time', 'desc')
                            ->find();
                        $lastClientMsg = $lastClientReply2 ? mb_substr(htmlspecialchars_decode($lastClientReply2['content']), 0, 200) : '';

                        $msg = "【工单需要高级技术人员处理】\n"
                             . "工单ID：{$ticketId}\n"
                             . "工单标题：{$ticket['title']}\n"
                             . "工单部门：{$typeName}\n"
                             . "客户最后消息：{$lastClientMsg}\n"
                             . "转接原因：客户请求转交高级技术人员\n"
                             . "请尽快处理！";

                        $this->sendFeishuNotification($webhook, $msg);
                    }
                }

                // 转人工自动回复（方式3或4）
                if ($transferMethod === 3 || $transferMethod === 4) {
                    $autoReply = $globalConfig['transfer_auto_reply'] ?? '';
                    if (!empty($autoReply)) {
                        $this->writeTransferAutoReply($ticketId, $autoReply);
                    }
                }
            }
        }
    }

    /**
     * 构建客户信息上下文
     */
    private function buildClientContext($ticket, $globalConfig)
    {
        $clientId = $ticket['client_id'] ?? 0;
        if (empty($clientId)) {
            return '';
        }

        $hostsLimit = intval($globalConfig['client_context_hosts_limit'] ?? 10) ?: 10;

        $hostStatusMap = [
            'Pending'   => '待开通',
            'Active'    => '运行中',
            'Suspended' => '已暂停',
            'Grace'     => '宽限期',
            'Unpaid'    => '未付款',
            'Deleted'   => '已删除',
            'Failed'    => '开通失败',
            'Keep'      => '保留中',
            'Cancelled' => '已取消',
            'Expired'   => '已过期',
            'Offline'   => '已下线',
            'Upgrade'   => '升降级中',
        ];

        $contextParts = [];

        try {
            // 1. 工单关联产品
            $linkedHostIds = Db::name('addon_idcsmart_ticket_host_link')
                ->where('ticket_id', $ticket['id'])
                ->column('host_id');

            if (!empty($linkedHostIds)) {
                $linkedHosts = Db::name('host')->alias('h')
                    ->leftJoin('product p', 'p.id = h.product_id')
                    ->leftJoin('host_ip hi', 'hi.host_id = h.id')
                    ->whereIn('h.id', $linkedHostIds)
                    ->field('h.id, h.name, h.status, hi.dedicate_ip, hi.assign_ip, p.name as product_name, h.billing_cycle_name, h.renew_amount, h.due_time')
                    ->select()->toArray();

                if (!empty($linkedHosts)) {
                    $lines = ['【本工单关联的产品】'];
                    foreach ($linkedHosts as $h) {
                        $lines[] = $this->formatHostLine($h, $hostStatusMap, '- ');
                    }
                    $contextParts[] = implode("\n", $lines);
                } else {
                    $contextParts[] = "【本工单关联的产品】\n无关联产品";
                }
            }

            // 2. 客户名下所有产品/服务（不限状态，包括已过期/已删除，AI根据状态如实回答）
            // is_sub字段可能不存在，先检测
            $hasIsSub = true;
            try {
                Db::name('host')->where('id', 0)->field('is_sub')->find();
            } catch (\Exception $e) {
                $hasIsSub = false;
            }

            $hostQuery = Db::name('host')->alias('h')
                ->leftJoin('product p', 'p.id = h.product_id')
                ->leftJoin('host_ip hi', 'hi.host_id = h.id')
                ->where('h.client_id', $clientId)
                ->where('h.is_delete', 0);

            if ($hasIsSub) {
                $hostQuery = $hostQuery->where('h.is_sub', 0);
            }

            // 查所有状态的产品，不过滤，让AI如实告知客户
            $hosts = $hostQuery
                ->order('h.id', 'desc')
                ->limit($hostsLimit)
                ->field('h.id, h.name, h.status, hi.dedicate_ip, hi.assign_ip, p.name as product_name, h.billing_cycle_name, h.renew_amount, h.due_time')
                ->select()->toArray();

            if (!empty($hosts)) {
                $lines = ['【客户名下产品/服务】(共' . count($hosts) . '个)'];
                foreach ($hosts as $i => $h) {
                    $lines[] = $this->formatHostLine($h, $hostStatusMap, ($i + 1) . '. ');
                }
                $contextParts[] = implode("\n", $lines);
            } else {
                $contextParts[] = "【客户名下产品/服务】\n该客户名下暂无产品/服务";
            }

            // 3. 客户近期订单
            $orderTypeMap = [
                'new'        => '新购',
                'renew'      => '续费',
                'upgrade'    => '升降级',
                'artificial' => '人工',
                'zjmf'       => '转扣',
                'recharge'   => '充值',
                'combine'    => '合并',
            ];
            $orderStatusMap = [
                'Unpaid'      => '未支付',
                'Paid'        => '已支付',
                'Cancelled'   => '已取消',
                'Refunded'    => '已退款',
                'WaitUpload'  => '待上传凭证',
                'WaitReview'  => '待审核',
                'ReviewFail'  => '审核未通过',
            ];

            $orders = Db::name('order')
                ->where('client_id', $clientId)
                ->order('create_time', 'desc')
                ->limit(5)
                ->field('id, type, amount, status, create_time')
                ->select()->toArray();

            if (!empty($orders)) {
                $lines = ['【客户近期订单】'];
                foreach ($orders as $i => $o) {
                    $typeText = $orderTypeMap[$o['type']] ?? $o['type'];
                    $statusText = $orderStatusMap[$o['status']] ?? $o['status'];
                    $amount = number_format($o['amount'], 2);
                    $time = !empty($o['create_time']) ? date('Y-m-d', $o['create_time']) : '未知';
                    $lines[] = ($i + 1) . ". 订单#{$o['id']} | {$typeText} | ¥{$amount} | {$statusText} | {$time}";
                }
                $contextParts[] = implode("\n", $lines);
            } else {
                $contextParts[] = "【客户近期订单】\n该客户暂无订单记录";
            }
        } catch (\Exception $e) {
            $this->logError($ticket['id'] ?? 0, '获取客户信息失败: ' . $e->getMessage());
        }

        return empty($contextParts) ? '' : implode("\n\n", $contextParts);
    }

    /**
     * 格式化单个产品/服务的显示行
     */
    private function formatHostLine($h, $statusMap, $prefix = '')
    {
        $productName = $h['product_name'] ?: '未知产品';
        $hostName = $h['name'] ?: ('#'.$h['id']);
        $statusText = $statusMap[$h['status']] ?? $h['status'];

        $line = "{$prefix}{$productName} | 标识: {$hostName} | 状态: {$statusText}";

        // 显示IP信息（这是客户最常提到的信息，必须让AI能精确匹配）
        $ips = [];
        if (!empty($h['dedicate_ip'])) {
            $ips[] = $h['dedicate_ip'];
        }
        if (!empty($h['assign_ip'])) {
            $assigned = is_string($h['assign_ip']) ? $h['assign_ip'] : '';
            if (!empty($assigned)) {
                foreach (explode(',', $assigned) as $ip) {
                    $ip = trim($ip);
                    if (!empty($ip) && !in_array($ip, $ips)) {
                        $ips[] = $ip;
                    }
                }
            }
        }
        if (!empty($ips)) {
            $line .= " | IP: " . implode(', ', $ips);
        }

        if (!empty($h['billing_cycle_name'])) {
            $line .= " | 周期: {$h['billing_cycle_name']}";
        }
        if (!empty($h['renew_amount']) && $h['renew_amount'] > 0) {
            $line .= " | 续费: ¥" . number_format($h['renew_amount'], 2);
        }
        if (!empty($h['due_time']) && $h['due_time'] > 0) {
            $line .= " | 到期: " . date('Y-m-d', $h['due_time']);
        }
        return $line;
    }

    /**
     * 构建发送给AI的消息数组
     * @param bool $useToolCalling 是否使用工具调用模式（工具调用模式不注入客户信息，由AI按需查询）
     */
    private function buildMessages($ticketId, $ticket, $deptConfig, $contextMaxChars = 5120, $globalConfig = [], $useToolCalling = false)
    {
        // 系统消息(人设)
        $systemPrompt = $deptConfig['persona'] ?? '';
        if (empty($systemPrompt)) {
            $systemPrompt = $globalConfig['default_persona'] ?? '你是一个公司客服，尽量模仿人的语气，根据用户问题给出最合适的答案。';
        }

        // 注入工单基础上下文：告知AI当前工单和客户信息，禁止向客户索要ID
        $clientId = $ticket['client_id'] ?? 0;
        $maxReplyChars = intval($deptConfig['max_reply_chars'] ?? 512);
        $systemPrompt .= "\n\n【重要】这是客户主动提交的工单，你已掌握以下信息，禁止向客户索要：工单ID={$ticketId}，客户ID={$clientId}。你已能看到客户名下的产品/服务和订单信息，不要问客户要ID、账号等信息。你的回复必须在{$maxReplyChars}个字符以内，超过会被截断导致内容不完整，请精炼表达，控制篇幅。";

        // 注入客户信息上下文（仅非工具调用模式，工具调用模式由AI按需查询）
        if (!$useToolCalling && !empty($globalConfig['enable_client_context'])) {
            $clientContext = $this->buildClientContext($ticket, $globalConfig);
            if (!empty($clientContext)) {
                $systemPrompt .= "\n\n" . $clientContext;
            }
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // 构建上下文
        $context = "【工单标题】\n" . $ticket['title'] . "\n\n【问题描述】\n" . htmlspecialchars_decode($ticket['content']);

        // 获取所有回复
        $replies = Db::name('addon_idcsmart_ticket_reply')
            ->where('ticket_id', $ticketId)
            ->order('create_time', 'asc')
            ->select()
            ->toArray();

        $replyTexts = [];
        foreach ($replies as $reply) {
            $role = $reply['type'] === 'Client' ? '用户' : '客服';
            $content = htmlspecialchars_decode($reply['content']);
            $replyTexts[] = "【{$role}】\n" . $content;
        }

        // 从最早的回复开始，尽量填满上下文（优先保留最新回复）
        $totalChars = mb_strlen($context);
        $usedReplies = [];
        for ($i = count($replyTexts) - 1; $i >= 0; $i--) {
            $replyChars = mb_strlen($replyTexts[$i]);
            if ($totalChars + $replyChars <= $contextMaxChars) {
                array_unshift($usedReplies, $replyTexts[$i]);
                $totalChars += $replyChars;
            } else {
                break;
            }
        }

        $fullContext = $context;
        if (!empty($usedReplies)) {
            $fullContext .= "\n\n" . implode("\n\n", $usedReplies);
        }
        $fullContext .= "\n\n请根据以上对话内容，给出合适的回复。";

        $messages[] = ['role' => 'user', 'content' => $fullContext];

        return $messages;
    }

    /**
     * 调用AI API
     * @param array $modelConfig 模型配置
     * @param array $messages 消息数组
     * @param array $tools 工具定义（可选）
     * @return array
     */
    public function callAi($modelConfig, $messages, $tools = [])
    {
        $provider = $modelConfig['provider'] ?? 'openai';
        $apiUrl = rtrim($modelConfig['api_url'] ?? '', '/');
        $apiKey = $modelConfig['api_key'] ?? '';
        $model = $modelConfig['model'] ?? '';
        $maxTokens = intval($modelConfig['max_tokens'] ?? 256);

        if (empty($apiUrl) || empty($apiKey) || empty($model)) {
            return ['success' => false, 'error' => 'API配置不完整'];
        }

        switch ($provider) {
            case 'anthropic':
                return $this->callAnthropic($apiUrl, $apiKey, $model, $messages, $maxTokens, $tools);
            default:
                return $this->callOpenAiCompatible($apiUrl, $apiKey, $model, $messages, $maxTokens, $tools);
        }
    }

    /**
     * 调用OpenAI兼容协议API
     */
    private function callOpenAiCompatible($apiUrl, $apiKey, $model, $messages, $maxTokens, $tools = [])
    {
        $url = $apiUrl;
        if (substr($url, -strlen('/chat/completions')) !== '/chat/completions') {
            $url = rtrim($url, '/') . '/chat/completions';
        }

        $postData = [
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => 0.7,
        ];

        // 添加工具定义
        if (!empty($tools)) {
            $postData['tools'] = $tools;
            $postData['tool_choice'] = 'auto';
        }

        $result = $this->httpPost($url, json_encode($postData), [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);

        if (!$result['success']) {
            return $result;
        }

        $response = json_decode($result['content'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'API响应解析失败: ' . mb_substr($result['content'], 0, 200)];
        }

        if (isset($response['error'])) {
            $errorMsg = is_array($response['error']) ? ($response['error']['message'] ?? json_encode($response['error'])) : $response['error'];
            return ['success' => false, 'error' => 'API错误: ' . $errorMsg];
        }

        $content = '';
        $tokensUsed = 0;
        $toolCalls = [];

        $choice = $response['choices'][0] ?? [];

        // 检查是否有工具调用
        if (!empty($choice['message']['tool_calls'])) {
            foreach ($choice['message']['tool_calls'] as $tc) {
                $toolCalls[] = [
                    'id' => $tc['id'] ?? '',
                    'name' => $tc['function']['name'] ?? '',
                    'arguments' => $tc['function']['arguments'] ?? '{}',
                ];
            }
        }

        if (isset($choice['message']['content'])) {
            $content = $choice['message']['content'] ?? '';
        }

        if (isset($response['usage']['total_tokens'])) {
            $tokensUsed = intval($response['usage']['total_tokens']);
        }

        // 如果有工具调用，返回工具调用信息
        if (!empty($toolCalls)) {
            return ['success' => true, 'content' => $content, 'tokens_used' => $tokensUsed, 'tool_calls' => $toolCalls];
        }

        if (empty($content)) {
            return ['success' => false, 'error' => 'API返回内容为空'];
        }

        return ['success' => true, 'content' => $content, 'tokens_used' => $tokensUsed];
    }

    /**
     * 调用Anthropic API
     */
    private function callAnthropic($apiUrl, $apiKey, $model, $messages, $maxTokens, $tools = [])
    {
        $url = rtrim($apiUrl, '/');

        // 兼容各种中转服务的路径拼接
        if (substr($url, -strlen('/v1/messages')) !== '/v1/messages' && substr($url, -strlen('/messages')) !== '/messages') {
            if (substr($url, -strlen('/anthropic')) === '/anthropic') {
                $url .= '/v1/messages';
            } elseif (substr($url, -strlen('/v1')) === '/v1') {
                $url .= '/messages';
            } else {
                $url .= '/v1/messages';
            }
        }

        $systemContent = '';
        $chatMessages = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemContent = $msg['content'];
            } else {
                $chatMessages[] = $msg;
            }
        }

        $postData = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $systemContent,
            'messages'   => $chatMessages,
        ];

        // 添加工具定义（Anthropic格式）
        if (!empty($tools)) {
            $anthropicTools = [];
            foreach ($tools as $tool) {
                if (isset($tool['function'])) {
                    $anthropicTools[] = [
                        'name' => $tool['function']['name'],
                        'description' => $tool['function']['description'],
                        'input_schema' => $tool['function']['parameters'],
                    ];
                }
            }
            $postData['tools'] = $anthropicTools;
        }

        // 同时发送两种认证头，兼容官方API(x-api-key)和中转服务(Bearer)
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'Authorization: Bearer ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ];

        $result = $this->httpPost($url, json_encode($postData), $headers);

        if (!$result['success']) {
            return $result;
        }

        $response = json_decode($result['content'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'API响应解析失败: ' . mb_substr($result['content'], 0, 200)];
        }

        if (isset($response['error'])) {
            $errorMsg = is_array($response['error']) ? ($response['error']['message'] ?? json_encode($response['error'])) : $response['error'];
            return ['success' => false, 'error' => 'API错误: ' . $errorMsg];
        }

        $content = '';
        $tokensUsed = 0;
        $toolCalls = [];

        // 解析Anthropic响应内容
        if (isset($response['content']) && is_array($response['content'])) {
            foreach ($response['content'] as $block) {
                if (isset($block['type']) && $block['type'] === 'text') {
                    $content .= $block['text'];
                } elseif (isset($block['type']) && $block['type'] === 'tool_use') {
                    $toolCalls[] = [
                        'id' => $block['id'] ?? '',
                        'name' => $block['name'] ?? '',
                        'arguments' => $block['input'] ?? [],
                    ];
                }
            }
        }

        if (isset($response['usage']['input_tokens']) && isset($response['usage']['output_tokens'])) {
            $tokensUsed = intval($response['usage']['input_tokens']) + intval($response['usage']['output_tokens']);
        }

        // 如果有工具调用，返回工具调用信息
        if (!empty($toolCalls)) {
            return ['success' => true, 'content' => $content, 'tokens_used' => $tokensUsed, 'tool_calls' => $toolCalls];
        }

        if (empty($content)) {
            return ['success' => false, 'error' => 'API返回内容为空'];
        }

        return ['success' => true, 'content' => $content, 'tokens_used' => $tokensUsed];
    }

    /**
     * HTTP POST请求
     */
    private function httpPost($url, $postData, $headers = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);

        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        $content = curl_exec($curl);
        $error = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($error) {
            return ['success' => false, 'error' => '请求失败: ' . $error, 'url' => $url];
        }

        if ($httpCode >= 400) {
            return ['success' => false, 'error' => 'HTTP错误: ' . $httpCode . ' - ' . mb_substr($content, 0, 500), 'url' => $url, 'response' => mb_substr($content, 0, 500)];
        }

        return ['success' => true, 'content' => $content, 'http_code' => $httpCode, 'url' => $url];
    }

    /**
     * 写入工单回复 - 通过工单插件的replyTicket方法
     */
    private function writeTicketReply($ticketId, $content, $aiModelId, $context, $rawResponse, $tokensUsed)
    {
        $time = time();

        // 记录AI回复日志
        $replyModel = new AiTicketReplyModel();
        $replyModel->createReply([
            'ticket_id'     => $ticketId,
            'ai_model_id'   => $aiModelId,
            'context'       => is_array($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : $context,
            'response'      => $rawResponse,
            'reply_content' => $content,
            'tokens_used'   => $tokensUsed,
            'create_time'   => $time,
        ]);

        // 尝试通过工单插件的模型来回复（走标准流程，包含通知等）
        if (class_exists('\addon\idcsmart_ticket\model\IdcsmartTicketModel')) {
            try {
                $ticketModel = new \addon\idcsmart_ticket\model\IdcsmartTicketModel();
                $ticketModel->isAdmin = true;
                $result = $ticketModel->replyTicket([
                    'id'             => $ticketId,
                    'content'        => $content,
                    'attachment'     => [],
                    'quote_reply_id' => 0,
                ]);

                if (isset($result['status']) && $result['status'] == 200) {
                    return true;
                }

                // replyTicket失败时记录错误，降级到直接写入
                $this->logError($ticketId, 'replyTicket返回: ' . json_encode($result));
            } catch (\Exception $e) {
                $this->logError($ticketId, 'replyTicket异常: ' . $e->getMessage());
            }
        }

        // 降级方案：直接写入数据库
        $ticket = Db::name('addon_idcsmart_ticket')->where('id', $ticketId)->find();
        $adminId = !empty($ticket) ? intval($ticket['admin_id']) : 0;
        if ($adminId <= 0) {
            $adminId = 1;
        }

        Db::name('addon_idcsmart_ticket_reply')->insert([
            'ticket_id'      => $ticketId,
            'type'           => 'Admin',
            'rel_id'         => $adminId,
            'content'        => htmlspecialchars($content),
            'attachment'     => '',
            'create_time'    => $time,
            'update_time'    => $time,
            'quote_reply_id' => 0,
        ]);

        Db::name('addon_idcsmart_ticket')->where('id', $ticketId)->update([
            'status'              => 3,
            'last_reply_time'     => $time,
            'last_reply_admin_id' => $adminId,
            'update_time'         => $time,
        ]);

        return true;
    }

    /**
     * 发送飞书自定义机器人通知
     * @param string $webhook 飞书Webhook地址
     * @param string $message 消息内容
     * @return bool
     */
    public function sendFeishuNotification($webhook, $message)
    {
        try {
            $postData = json_encode([
                'msg_type' => 'text',
                'content'  => [
                    'text' => $message,
                ],
            ]);

            $result = $this->httpPost($webhook, $postData, [
                'Content-Type: application/json',
            ]);

            if (!$result['success']) {
                $this->logError(0, '飞书通知发送失败: ' . ($result['error'] ?? 'unknown'));
                return false;
            }

            $response = json_decode($result['content'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logError(0, '飞书通知响应解析失败');
                return false;
            }

            // 飞书返回 code=0 表示成功
            if (isset($response['code']) && $response['code'] !== 0) {
                $this->logError(0, '飞书通知失败: ' . ($response['msg'] ?? json_encode($response)));
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->logError(0, '飞书通知异常: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 写入转人工自动回复
     */
    private function writeTransferAutoReply($ticketId, $content)
    {
        $time = time();

        // 获取工单信息
        $ticket = Db::name('addon_idcsmart_ticket')->where('id', $ticketId)->find();
        $adminId = !empty($ticket) ? intval($ticket['admin_id']) : 0;
        if ($adminId <= 0) {
            $adminId = 1;
        }

        Db::name('addon_idcsmart_ticket_reply')->insert([
            'ticket_id'      => $ticketId,
            'type'           => 'Admin',
            'rel_id'         => $adminId,
            'content'        => htmlspecialchars($content),
            'attachment'     => '',
            'create_time'    => $time,
            'update_time'    => $time,
            'quote_reply_id' => 0,
        ]);

        // 记录到AI回复表，用于区分AI回复和人工客服回复
        $replyModel = new AiTicketReplyModel();
        $replyModel->createReply([
            'ticket_id'     => $ticketId,
            'ai_model_id'   => 0,
            'context'       => '',
            'response'      => '',
            'reply_content' => $content,
            'tokens_used'   => 0,
            'create_time'   => $time,
        ]);

        Db::name('addon_idcsmart_ticket')->where('id', $ticketId)->update([
            'status'              => 3,
            'last_reply_time'     => $time,
            'last_reply_admin_id' => $adminId,
            'update_time'         => $time,
        ]);
    }

    /**
     * 记录错误日志
     */
    private function logError($ticketId, $error)
    {
        $logFile = runtime_path() . 'log' . DIRECTORY_SEPARATOR . 'xm_ai_ticket_error.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logLine = date('Y-m-d H:i:s') . " | TicketID: {$ticketId} | Error: {$error}\n";
        file_put_contents($logFile, $logLine, FILE_APPEND);
    }

    /**
     * 转人工后客户继续发消息的自动回复
     * 告知客户已通知技术员，正在等待处理
     * 每个客户消息只回复一次，避免重复
     */
    private function writeTransferWaitReply($ticketId, $globalConfig)
    {
        $lastClientReply = Db::name('addon_idcsmart_ticket_reply')
            ->where('ticket_id', $ticketId)
            ->where('type', 'Client')
            ->order('create_time', 'desc')
            ->find();

        if (empty($lastClientReply)) {
            return;
        }

        // 检查是否已有人工客服回复（排除AI自动回复）
        // 获取该工单所有AI回复的时间点
        $aiReplyTimes = Db::name('addon_xm_ai_ticket_reply')
            ->where('ticket_id', $ticketId)
            ->column('create_time');
        $aiTimeMap = [];
        foreach ($aiReplyTimes as $t) {
            $aiTimeMap[$t] = true;
        }

        // 查找客户最后一条消息之后的人工客服回复（排除AI回复）
        $adminReplies = Db::name('addon_idcsmart_ticket_reply')
            ->where('ticket_id', $ticketId)
            ->where('type', 'Admin')
            ->where('create_time', '>', $lastClientReply['create_time'])
            ->order('create_time', 'desc')
            ->select()
            ->toArray();

        $hasHumanReply = false;
        foreach ($adminReplies as $ar) {
            // 这条回复不是AI发的，说明人工客服已回复
            if (!isset($aiTimeMap[$ar['create_time']])) {
                $hasHumanReply = true;
                break;
            }
        }

        // 已有人工客服回复，不需要AI再发等待提示
        if ($hasHumanReply) {
            return;
        }

        $waitMsg = '您好，您的问题已经转交给我们的技术专员处理了，请耐心等待一下，他们会尽快为您回复的哈～';

        $this->writeTransferAutoReply($ticketId, $waitMsg);
    }

    /**
     * 处理服务器开通监控 - 由定时任务每分钟调用
     * 处理pending状态的监控记录：重试、飞书通知、提交工单、通知客户
     */
    public function processHostCreateMonitor()
    {
        $plugin = new XmAiTicket();
        $config = $plugin->getConfig();
        $maxRetry = intval($config['host_create_max_retry'] ?? 15);

        $monitorModel = new AiTicketHostCreateMonitorModel();
        $monitors = $monitorModel->getPendingMonitors();
        if (empty($monitors)) {
            return;
        }

        foreach ($monitors as $monitor) {
            try {
                if ($monitor['type'] === 'balance_insufficient') {
                    $this->processBalanceInsufficient($monitor, $config, $maxRetry, $monitorModel);
                } elseif ($monitor['type'] === 'submit_ticket') {
                    $this->processSubmitTicket($monitor, $config, $monitorModel);
                }
            } catch (\Exception $e) {
                $this->logError($monitor['ticket_id'], '开通监控处理失败: ' . $e->getMessage());
            }
        }
    }

    /**
     * 处理余额不足类型的监控
     */
    private function processBalanceInsufficient($monitor, $config, $maxRetry, $monitorModel)
    {
        $now = time();
        $hostId = intval($monitor['host_id']);
        $ticketId = intval($monitor['ticket_id']);

        // 首次发飞书通知
        if (intval($monitor['feishu_sent']) === 0) {
            $supplier = Db::name('supplier')->where('id', $monitor['supplier_id'])->find();
            $supplierName = !empty($supplier) ? $supplier['name'] : '未知供应商';
            $client = Db::name('client')->where('id', $monitor['client_id'])->find();
            $clientName = !empty($client) ? $client['username'] : '未知客户';
            $host = Db::name('host')->where('id', $hostId)->find();
            $hostName = !empty($host) ? $host['name'] : '未知产品';

            $msg = "【服务器开通失败 - 供应商余额不足】\n"
                 . "供应商：{$supplierName}\n"
                 . "客户：{$clientName}（ID:{$monitor['client_id']}）\n"
                 . "产品：{$hostName}（ID:{$hostId}）\n"
                 . "失败原因：{$monitor['fail_reason']}\n"
                 . "请尽快为供应商充值余额，系统将自动重试开通！";

            $webhook = $config['feishu_webhook'] ?? '';
            if (!empty($webhook)) {
                $this->sendFeishuNotification($webhook, $msg);
            }

            $monitorModel->updateMonitor($monitor['id'], ['feishu_sent' => 1]);
        }

        // 检查host是否已开通成功
        $host = Db::name('host')->where('id', $hostId)->find();
        if (!empty($host) && $host['status'] === 'Active') {
            $serverInfo = $this->getHostServerInfo($hostId);
            $replyContent = "好消息！您的服务器已经开通成功了！以下是服务器信息：\n{$serverInfo}";
            $this->writeTransferAutoReply($ticketId, $replyContent);
            $monitorModel->updateMonitor($monitor['id'], [
                'status' => 'success',
                'result' => '开通成功: ' . $serverInfo,
            ]);
            return;
        }

        // 检查重试次数
        if (intval($monitor['retry_count']) >= $maxRetry) {
            $msg = "【服务器开通重试超限】\n"
                 . "产品ID:{$hostId} 重试{$maxRetry}次仍未开通成功\n"
                 . "请人工介入处理！";
            $webhook = $config['feishu_webhook'] ?? '';
            if (!empty($webhook)) {
                $this->sendFeishuNotification($webhook, $msg);
            }
            $this->writeTransferAutoReply($ticketId, '抱歉，经过多次尝试仍无法完成开通，已转交技术人员为您处理，请稍候。');
            $monitorModel->updateMonitor($monitor['id'], [
                'status' => 'error',
                'result' => '重试超限',
            ]);
            return;
        }

        // 检查距上次重试是否>=2分钟(120秒)
        $lastRetryTime = intval($monitor['last_retry_time']);
        if ($now - $lastRetryTime < 120) {
            return; // 还不到2分钟，跳过
        }

        // 查找该host最新的Failed host_create任务
        $failedTask = Db::name('task')
            ->where('status', 'Failed')
            ->where('type', 'host_create')
            ->where('rel_id', $hostId)
            ->where('retry', 0)
            ->order('id', 'desc')
            ->find();

        if (!empty($failedTask)) {
            $taskModel = new \app\common\model\TaskModel();
            $retryResult = $taskModel->retryTask($failedTask['id']);

            if (isset($retryResult['status']) && $retryResult['status'] === 200) {
                $monitorModel->updateMonitor($monitor['id'], [
                    'retry_count' => intval($monitor['retry_count']) + 1,
                    'last_retry_time' => $now,
                ]);
                $this->logError($ticketId, '开通任务重试第' . (intval($monitor['retry_count']) + 1) . '次，任务ID:' . $failedTask['id']);
            } else {
                $this->logError($ticketId, '开通任务重试失败: ' . ($retryResult['msg'] ?? 'unknown'));
            }
        } else {
            // 没找到可重试的Failed任务(retry=0)，可能所有任务都已重试过了
            // 查找是否有Wait或Exec状态的任务（正在处理中）
            $activeTask = Db::name('task')
                ->where('type', 'host_create')
                ->where('rel_id', $hostId)
                ->whereIn('status', ['Wait', 'Exec'])
                ->order('id', 'desc')
                ->find();

            if (!empty($activeTask)) {
                // 正在处理中，等待下次检查
                $this->logError($ticketId, '开通任务正在执行中，任务ID:' . $activeTask['id']);
            } else {
                // 没有可重试的任务也没有正在执行的任务
                $this->logError($ticketId, '没有找到可重试的开通任务，host_id:' . $hostId);
            }
        }
    }

    /**
     * 处理提交供应商工单类型的监控
     * 流程：提交工单 → 持续监测host状态 → 开通成功通知客户 / 超时通知管理员
     */
    private function processSubmitTicket($monitor, $config, $monitorModel)
    {
        $now = time();
        $hostId = intval($monitor['host_id']);
        $ticketId = intval($monitor['ticket_id']);

        // 1. 检查host是否已开通成功
        $host = Db::name('host')->where('id', $hostId)->find();
        if (!empty($host) && $host['status'] === 'Active') {
            $serverInfo = $this->getHostServerInfo($hostId);
            $replyContent = "好消息！您的服务器已经开通成功了！以下是服务器信息：\n{$serverInfo}";
            $this->writeTransferAutoReply($ticketId, $replyContent);
            $monitorModel->updateMonitor($monitor['id'], [
                'status' => 'success',
                'result' => '开通成功: ' . $serverInfo,
            ]);
            return;
        }

        // 2. 检查是否超时（超过max_retry分钟仍未开通）
        $maxRetry = intval($config['host_create_max_retry'] ?? 15);
        $elapsed = $now - intval($monitor['create_time']);
        if ($elapsed > $maxRetry * 120) {
            $msg = "【供应商工单处理超时】\n"
                 . "产品ID:{$hostId} 提交供应商工单后{$maxRetry}次检测周期仍未开通\n"
                 . "请人工介入处理！";
            $webhook = $config['feishu_webhook'] ?? '';
            if (!empty($webhook)) {
                $this->sendFeishuNotification($webhook, $msg);
            }
            $this->writeTransferAutoReply($ticketId, '抱歉，联系供应商后仍未完成开通，已转交技术人员为您处理，请稍候。');
            $monitorModel->updateMonitor($monitor['id'], [
                'status' => 'error',
                'result' => '工单处理超时',
            ]);
            return;
        }

        // 3. 首次提交供应商工单（feishu_sent复用为是否已提交工单的标记）
        if (intval($monitor['feishu_sent']) === 0) {
            $supplierId = intval($monitor['supplier_id']);
            $upstreamHostId = intval($monitor['upstream_host_id']);

            $supplier = Db::name('supplier')->where('id', $supplierId)->find();
            if (empty($supplier)) {
                $this->writeTransferAutoReply($ticketId, '无法联系供应商，已转交技术人员处理。');
                $monitorModel->updateMonitor($monitor['id'], ['status' => 'error', 'result' => '供应商不存在']);
                return;
            }

            $supplierType = $supplier['type'] ?? '';
            $hostName = !empty($host) ? $host['name'] : '未知';
            $failReason = $monitor['fail_reason'] ?? '未知原因';
            $title = "协助开通产品 #{$hostId} {$hostName}";
            $content = "请协助开通以下产品：\n本地产品ID: #{$hostId}\n上游产品ID: #{$upstreamHostId}\n产品标识: {$hostName}\n失败原因: {$failReason}\n请尽快处理。";

            $submitResult = $this->submitUpstreamTicket($supplierId, $supplierType, $upstreamHostId, $title, $content);

            if ($submitResult['success']) {
                $this->writeTransferAutoReply($ticketId, '已经联系供应商为您处理开通问题，请耐心等待，我们会持续关注进度。');
                // 标记工单已提交，但status仍为pending，继续监测
                $monitorModel->updateMonitor($monitor['id'], [
                    'feishu_sent' => 1,
                    'result' => '供应商工单已提交: ' . ($submitResult['msg'] ?? ''),
                ]);
            } else {
                // 工单提交失败，飞书通知
                $msg = "【供应商工单提交失败】\n"
                     . "供应商:{$supplier['name']}\n"
                     . "产品ID:{$hostId}\n"
                     . "失败原因:{$submitResult['msg']}\n"
                     . "请人工处理！";
                $webhook = $config['feishu_webhook'] ?? '';
                if (!empty($webhook)) {
                    $this->sendFeishuNotification($webhook, $msg);
                }
                $this->writeTransferAutoReply($ticketId, '联系供应商时遇到问题，已转交技术人员为您处理。');
                $monitorModel->updateMonitor($monitor['id'], [
                    'status' => 'error',
                    'result' => '工单提交失败: ' . ($submitResult['msg'] ?? ''),
                ]);
            }
        }
        // feishu_sent=1 表示工单已提交，继续等待下次定时检查host状态
    }

    /**
     * 提交供应商工单
     * @param int $supplierId 供应商ID
     * @param string $supplierType 供应商类型(finance/default)
     * @param int $upstreamHostId 上游产品ID
     * @param string $title 工单标题
     * @param string $content 工单内容
     * @return array ['success' => bool, 'msg' => string]
     */
    private function submitUpstreamTicket($supplierId, $supplierType, $upstreamHostId, $title, $content)
    {
        if ($supplierType === 'finance') {
            // 魔方财务: POST ticket/create
            // 需要工单部门ID，默认用1
            $postData = [
                'dptid'    => 1,
                'title'    => $title,
                'content'  => $content,
                'hostid'   => $upstreamHostId,
                'priority' => 'Medium',
            ];
            try {
                $res = \idcsmart_api_curl($supplierId, 'ticket/create', $postData, 30, 'POST');
                if (isset($res['status']) && $res['status'] == 200) {
                    return ['success' => true, 'msg' => '魔方财务工单已提交'];
                }
                return ['success' => false, 'msg' => $res['msg'] ?? '未知错误'];
            } catch (\Exception $e) {
                return ['success' => false, 'msg' => $e->getMessage()];
            }
        } elseif ($supplierType === 'default') {
            // IDC Smart V10: POST console/v1/ticket (需要JSON body)
            // idcsmart_api_curl 用 http_build_query 会破坏数组，需要手动curl
            try {
                $loginResult = \idcsmart_api_login($supplierId);
                if ($loginResult['status'] != 200) {
                    return ['success' => false, 'msg' => '供应商登录失败: ' . $loginResult['msg']];
                }
                $jwt = $loginResult['data']['jwt'];
                $url = $loginResult['data']['url'];
                $supplier = $loginResult['data']['supplier'];

                $postData = json_encode([
                    'title'          => $title,
                    'ticket_type_id' => 1,
                    'host_ids'       => [$upstreamHostId],
                    'content'        => $content,
                    'attachment'     => [],
                    'admin_role_id'  => '',
                ], JSON_UNESCAPED_UNICODE);

                $header = [
                    'Authorization: Bearer ' . $jwt,
                    'Content-Type: application/json',
                ];
                $apiUrl = $url . '/console/v1/ticket';

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $apiUrl);
                curl_setopt($curl, CURLOPT_TIMEOUT, 30);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);

                // 401/405时重新登录重试
                if ($httpCode == 401 || $httpCode == 405) {
                    $loginResult = \idcsmart_api_login($supplierId, true);
                    if ($loginResult['status'] == 200) {
                        $jwt = $loginResult['data']['jwt'];
                        $header[0] = 'Authorization: Bearer ' . $jwt;
                        $curl = curl_init();
                        curl_setopt($curl, CURLOPT_URL, $apiUrl);
                        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                        curl_setopt($curl, CURLOPT_POST, 1);
                        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
                        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
                        $response = curl_exec($curl);
                        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                        curl_close($curl);
                    }
                }

                if ($httpCode >= 400) {
                    return ['success' => false, 'msg' => 'V10工单提交失败HTTP: ' . $httpCode];
                }

                $res = json_decode($response, true);
                if (isset($res['status']) && $res['status'] == 200) {
                    return ['success' => true, 'msg' => 'V10工单已提交'];
                }
                return ['success' => false, 'msg' => $res['msg'] ?? 'V10返回错误'];
            } catch (\Exception $e) {
                return ['success' => false, 'msg' => $e->getMessage()];
            }
        }

        return ['success' => false, 'msg' => '不支持的供应商类型: ' . $supplierType];
    }

    /**
     * 获取服务器信息(IP、用户名、密码)
     */
    private function getHostServerInfo($hostId)
    {
        $info = [];

        // 获取IP
        $hostIp = Db::name('host_ip')->where('host_id', $hostId)->find();
        if (!empty($hostIp)) {
            if (!empty($hostIp['dedicate_ip'])) {
                $info[] = 'IP地址：' . $hostIp['dedicate_ip'];
            }
            if (!empty($hostIp['assign_ip'])) {
                $info[] = '附加IP：' . $hostIp['assign_ip'];
            }
        }

        // 获取用户名密码
        $hostAddition = Db::name('host_addition')->where('host_id', $hostId)->find();
        if (!empty($hostAddition)) {
            if (!empty($hostAddition['username'])) {
                $info[] = '用户名：' . $hostAddition['username'];
            }
            if (!empty($hostAddition['password'])) {
                $info[] = '密码：' . $hostAddition['password'];
            }
            if (!empty($hostAddition['port'])) {
                $info[] = '端口：' . $hostAddition['port'];
            }
            if (!empty($hostAddition['image_name'])) {
                $info[] = '操作系统：' . $hostAddition['image_name'];
            }
        }

        // 获取产品名
        $host = Db::name('host')->where('id', $hostId)->find();
        if (!empty($host)) {
            $product = Db::name('product')->where('id', $host['product_id'])->find();
            if (!empty($product)) {
                $info[] = '产品名称：' . $product['name'];
            }
        }

        return empty($info) ? '暂无服务器详情' : implode("\n", $info);
    }
}