<?php
namespace addon\xm_ai_ticket\model;

use think\Model;
use think\facade\Db;

/**
 * AI工具配置模型
 */
class AiTicketToolModel extends Model
{
    protected $name = 'addon_xm_ai_ticket_tool';

    // 允许的模板变量白名单
    private $allowedParams = ['client_id', 'ticket_id', 'host_id', 'product_id'];

    /**
     * 工具列表
     */
    public function toolList($param)
    {
        $where = [];
        if (isset($param['status']) && $param['status'] !== '') {
            $where[] = ['status', '=', intval($param['status'])];
        }
        if (isset($param['type']) && $param['type'] !== '') {
            $where[] = ['type', '=', $param['type']];
        }

        $count = $this->where($where)->count();

        $list = $this->where($where)
            ->order('id asc')
            ->page($param['page'] ?? 1, $param['limit'] ?? 20)
            ->select()
            ->toArray();

        return ['status' => 200, 'msg' => lang_plugins('success_message'), 'data' => ['list' => $list, 'count' => $count]];
    }

    /**
     * 创建工具
     */
    public function toolCreate($param)
    {
        $time = time();
        $name = $param['name'] ?? '';
        $description = $param['description'] ?? '';
        $type = $param['type'] ?? 'sql';
        $config = $param['config'] ?? '';
        $status = intval($param['status'] ?? 1);

        if (empty($name) || empty($description)) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_param_required')];
        }

        // 验证工具名称唯一性
        $exists = $this->where('name', $name)->find();
        if (!empty($exists)) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_tool_name_exists')];
        }

        // 验证工具名称格式（只允许英文字母、数字、下划线）
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_tool_name_format')];
        }

        // 验证config是合法JSON
        if (!empty($config)) {
            $configData = is_string($config) ? json_decode($config, true) : $config;
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_tool_config_invalid')];
            }
            // 验证SQL工具安全性
            if ($type === 'sql') {
                $query = $configData['query'] ?? '';
                if (empty($query)) {
                    return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_tool_sql_required')];
                }
                // 只允许SELECT
                if (!preg_match('/^\s*SELECT\s/i', $query)) {
                    return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_tool_sql_select_only')];
                }
                // 禁止危险操作
                if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|REPLACE|CREATE)\b/i', $query)) {
                    return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_tool_sql_select_only')];
                }
            }
            $config = is_string($config) ? $config : json_encode($config, JSON_UNESCAPED_UNICODE);
        }

        $result = $this->create([
            'name'        => $name,
            'description' => $description,
            'type'        => $type,
            'config'      => $config,
            'status'      => $status,
            'create_time' => $time,
            'update_time' => $time,
        ]);

        return ['status' => 200, 'msg' => lang_plugins('success_message'), 'data' => ['id' => $result->id]];
    }

    /**
     * 更新工具
     */
    public function toolUpdate($param)
    {
        $id = intval($param['id']);
        $tool = $this->find($id);
        if (empty($tool)) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_tool_not_exist')];
        }

        $time = time();
        $data = [];

        if (isset($param['description'])) {
            $data['description'] = $param['description'];
        }
        if (isset($param['type'])) {
            $data['type'] = $param['type'];
        }
        if (isset($param['status'])) {
            $data['status'] = intval($param['status']);
        }
        if (isset($param['config'])) {
            $config = $param['config'];
            $type = $data['type'] ?? $tool['type'];
            $configData = is_string($config) ? json_decode($config, true) : $config;
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_tool_config_invalid')];
            }
            if ($type === 'sql') {
                $query = $configData['query'] ?? '';
                if (empty($query)) {
                    return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_tool_sql_required')];
                }
                if (!preg_match('/^\s*SELECT\s/i', $query)) {
                    return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_tool_sql_select_only')];
                }
                if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|REPLACE|CREATE)\b/i', $query)) {
                    return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_tool_sql_select_only')];
                }
            }
            $data['config'] = is_string($config) ? $config : json_encode($config, JSON_UNESCAPED_UNICODE);
        }

        $data['update_time'] = $time;
        $tool->save($data);

        return ['status' => 200, 'msg' => lang_plugins('success_message')];
    }

    /**
     * 删除工具
     */
    public function toolDelete($id)
    {
        $tool = $this->find($id);
        if (empty($tool)) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_tool_not_exist')];
        }

        $tool->delete();
        return ['status' => 200, 'msg' => lang_plugins('success_message')];
    }

    /**
     * 获取所有启用的工具，生成OpenAI tools定义
     */
    public function getActiveTools()
    {
        // 先同步预置工具SQL（确保已安装的预置工具SQL是最新的）
        $this->syncPresetTools();

        $tools = $this->where('status', 1)->select()->toArray();
        if (empty($tools)) {
            return [];
        }

        $toolsDef = [];
        foreach ($tools as $tool) {
            $config = json_decode($tool['config'], true) ?: [];
            $params = $config['params'] ?? [];

            $properties = [];
            $required = [];
            foreach ($params as $paramName) {
                if (in_array($paramName, $this->allowedParams)) {
                    $properties[$paramName] = [
                        'type'        => 'integer',
                        'description' => $paramName,
                    ];
                    $required[] = $paramName;
                }
            }

            $toolsDef[] = [
                'type' => 'function',
                'function' => [
                    'name'        => $tool['name'],
                    'description' => $tool['description'],
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => $properties,
                        'required'   => $required,
                    ],
                ],
            ];
        }

        return $toolsDef;
    }

    /**
     * 执行工具调用
     * @param string $toolName 工具名称
     * @param array $params 调用参数
     * @param array $ticket 工单信息（用于获取client_id等）
     * @return array ['success' => bool, 'result' => string]
     */
    public function executeTool($toolName, $params, $ticket)
    {
        $tool = $this->where('name', $toolName)->where('status', 1)->find();
        if (empty($tool)) {
            return ['success' => false, 'result' => '工具不存在或已禁用'];
        }

        $config = json_decode($tool['config'], true) ?: [];

        // 补充工单上下文参数
        $contextParams = $this->buildContextParams($ticket);
        $params = array_merge($contextParams, $params);

        if ($toolName === 'check_host_create_status') {
            return $this->executeCheckHostCreate($params, $ticket);
        } elseif ($tool['type'] === 'sql') {
            return $this->executeSqlTool($config, $params);
        } elseif ($tool['type'] === 'api') {
            return $this->executeApiTool($config, $params);
        }

        return ['success' => false, 'result' => '不支持的工具类型'];
    }

    /**
     * 构建工单上下文参数
     */
    private function buildContextParams($ticket)
    {
        $params = [];
        if (!empty($ticket['client_id'])) {
            $params['client_id'] = intval($ticket['client_id']);
        }
        if (!empty($ticket['id'])) {
            $params['ticket_id'] = intval($ticket['id']);
        }
        // 获取工单关联的第一个产品ID
        if (!empty($ticket['id'])) {
            $link = Db::name('addon_idcsmart_ticket_host_link')
                ->where('ticket_id', $ticket['id'])
                ->find();
            if (!empty($link)) {
                $params['host_id'] = intval($link['host_id']);
            }
        }
        return $params;
    }

    /**
     * 执行SQL工具
     */
    private function executeSqlTool($config, $params)
    {
        $query = $config['query'] ?? '';
        if (empty($query)) {
            return ['success' => false, 'result' => 'SQL查询模板为空'];
        }

        // 替换模板变量（只允许白名单变量）
        foreach ($this->allowedParams as $paramName) {
            $value = isset($params[$paramName]) ? intval($params[$paramName]) : 0;
            $query = str_replace('{' . $paramName . '}', $value, $query);
        }

        // 检查是否还有未替换的变量
        if (preg_match('/\{[a-zA-Z_]+\}/', $query)) {
            return ['success' => false, 'result' => '存在未识别的模板变量'];
        }

        // 自动添加LIMIT（如果没有）
        if (!preg_match('/\bLIMIT\s+\d+/i', $query)) {
            $query = rtrim($query, '; ') . ' LIMIT 20';
        }

        try {
            $results = Db::query($query);
            if (empty($results)) {
                return ['success' => true, 'result' => '查询结果为空，没有找到相关数据'];
            }

            $resultStr = json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            // 截断结果，防止过大
            if (mb_strlen($resultStr) > 2000) {
                $resultStr = mb_substr($resultStr, 0, 2000) . '...(结果已截断)';
            }

            return ['success' => true, 'result' => $resultStr];
        } catch (\Exception $e) {
            return ['success' => false, 'result' => 'SQL执行失败: ' . $e->getMessage()];
        }
    }

    /**
     * 执行API工具
     */
    private function executeApiTool($config, $params)
    {
        $url = $config['url'] ?? '';
        $method = strtoupper($config['method'] ?? 'GET');
        $headers = $config['headers'] ?? [];
        $body = $config['body'] ?? '';

        if (empty($url)) {
            return ['success' => false, 'result' => 'API URL为空'];
        }

        // 替换URL中的模板变量
        foreach ($this->allowedParams as $paramName) {
            $value = isset($params[$paramName]) ? intval($params[$paramName]) : 0;
            $url = str_replace('{' . $paramName . '}', $value, $url);
            if (is_array($body)) {
                $body = str_replace('{' . $paramName . '}', $value, $body);
            } else {
                $body = str_replace('{' . $paramName . '}', $value, $body);
            }
        }

        // 构建请求头
        $headerLines = ['Content-Type: application/json'];
        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                // 替换header中的模板变量
                foreach ($this->allowedParams as $paramName) {
                    $paramValue = isset($params[$paramName]) ? intval($params[$paramName]) : 0;
                    $value = str_replace('{' . $paramName . '}', $paramValue, $value);
                }
                $headerLines[] = $key . ': ' . $value;
            }
        }

        try {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headerLines);

            if ($method === 'POST' && !empty($body)) {
                curl_setopt($curl, CURLOPT_POST, 1);
                $postData = is_array($body) ? json_encode($body) : $body;
                curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
            }

            $content = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($error) {
                return ['success' => false, 'result' => 'API请求失败: ' . $error];
            }

            if ($httpCode >= 400) {
                return ['success' => false, 'result' => 'API返回HTTP错误: ' . $httpCode];
            }

            $resultStr = mb_substr($content, 0, 2000);
            return ['success' => true, 'result' => $resultStr];
        } catch (\Exception $e) {
            return ['success' => false, 'result' => 'API执行失败: ' . $e->getMessage()];
        }
    }

    /**
     * 测试工具执行
     */
    public function toolTest($param)
    {
        $id = intval($param['id'] ?? 0);
        $tool = $this->find($id);
        if (empty($tool)) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_tool_not_exist')];
        }

        // 使用测试参数执行
        $testParams = $param['test_params'] ?? [];
        $ticket = [
            'id'        => intval($testParams['ticket_id'] ?? 1),
            'client_id' => intval($testParams['client_id'] ?? 1),
        ];

        $result = $this->executeTool($tool['name'], $testParams, $ticket);

        return ['status' => 200, 'msg' => lang_plugins('success_message'), 'data' => $result];
    }

    /**
     * 同步预置工具的SQL配置（确保线上已安装的预置工具SQL是最新的）
     * 仅更新预置工具的config字段，不影响管理员自定义工具
     */
    private function syncPresetTools()
    {
        $presetTools = [
            'get_client_products' => json_encode([
                'query' => 'SELECT h.id,h.name,CASE h.status WHEN "Active" THEN "运行中" WHEN "Suspended" THEN "已暂停" WHEN "Deleted" THEN "已删除" WHEN "Pending" THEN "待开通" WHEN "Failed" THEN "开通失败" WHEN "Cancelled" THEN "已取消" WHEN "Expired" THEN "已过期" ELSE h.status END as status_text,hi.dedicate_ip,hi.assign_ip,p.name as product_name,h.billing_cycle_name,h.renew_amount,FROM_UNIXTIME(h.due_time,"%Y-%m-%d") as due_date FROM idcsmart_host h LEFT JOIN idcsmart_product p ON p.id=h.product_id LEFT JOIN idcsmart_host_ip hi ON hi.host_id=h.id WHERE h.client_id={client_id} AND h.is_delete=0 AND h.is_sub=0 ORDER BY h.id DESC LIMIT 20',
                'params' => ['client_id'],
            ]),
            'get_client_orders' => json_encode([
                'query' => 'SELECT o.id,o.type,o.amount,CASE o.status WHEN "Unpaid" THEN "未支付" WHEN "Paid" THEN "已支付" WHEN "Cancelled" THEN "已取消" WHEN "Refunded" THEN "已退款" WHEN "WaitUpload" THEN "待上传凭证" WHEN "WaitReview" THEN "待审核" WHEN "ReviewFail" THEN "审核未通过" ELSE o.status END as status_text,FROM_UNIXTIME(o.create_time,"%Y-%m-%d %H:%i") as create_date FROM idcsmart_order o WHERE o.client_id={client_id} ORDER BY o.create_time DESC LIMIT 10',
                'params' => ['client_id'],
            ]),
            'get_ticket_products' => json_encode([
                'query' => 'SELECT h.id,h.name,CASE h.status WHEN "Active" THEN "运行中" WHEN "Suspended" THEN "已暂停" WHEN "Deleted" THEN "已删除" WHEN "Pending" THEN "待开通" WHEN "Failed" THEN "开通失败" WHEN "Cancelled" THEN "已取消" WHEN "Expired" THEN "已过期" ELSE h.status END as status_text,hi.dedicate_ip,hi.assign_ip,p.name as product_name,h.billing_cycle_name,h.renew_amount,FROM_UNIXTIME(h.due_time,"%Y-%m-%d") as due_date FROM idcsmart_host h LEFT JOIN idcsmart_product p ON p.id=h.product_id LEFT JOIN idcsmart_host_ip hi ON hi.host_id=h.id LEFT JOIN idcsmart_addon_idcsmart_ticket_host_link thl ON thl.host_id=h.id WHERE thl.ticket_id={ticket_id}',
                'params' => ['ticket_id'],
            ]),
            'check_host_create_status' => json_encode([
                'params' => ['ticket_id', 'host_id'],
            ]),
        ];

        foreach ($presetTools as $name => $config) {
            $tool = $this->where('name', $name)->find();
            if (!empty($tool)) {
                $tool->save(['config' => $config, 'update_time' => time()]);
            }
        }
    }

    /**
     * 检测服务器开通状态
     * 核心逻辑：判断是否上游产品 → 查日志/任务 → 返回综合信息
     */
    private function executeCheckHostCreate($params, $ticket)
    {
        $ticketId = intval($params['ticket_id'] ?? ($ticket['id'] ?? 0));
        $hostId = intval($params['host_id'] ?? 0);
        $clientId = intval($ticket['client_id'] ?? 0);

        if ($hostId <= 0) {
            // 尝试从工单关联中获取host_id
            $link = Db::name('addon_idcsmart_ticket_host_link')
                ->where('ticket_id', $ticketId)
                ->find();
            if (!empty($link)) {
                $hostId = intval($link['host_id']);
            }
        }

        if ($hostId <= 0) {
            return ['success' => true, 'result' => json_encode([
                'is_upstream' => false,
                'message' => '无法确定要开通的产品，请告知客户具体的产品ID或产品名称',
            ], JSON_UNESCAPED_UNICODE)];
        }

        // 1. 查host信息
        $host = Db::name('host')->where('id', $hostId)->find();
        if (empty($host)) {
            return ['success' => true, 'result' => json_encode([
                'is_upstream' => false,
                'message' => '未找到该产品信息',
            ], JSON_UNESCAPED_UNICODE)];
        }

        // 2. 判断是否上游产品
        $upstreamProduct = Db::name('upstream_product')
            ->where('product_id', $host['product_id'])
            ->find();

        if (empty($upstreamProduct)) {
            return ['success' => true, 'result' => json_encode([
                'is_upstream' => false,
                'host_id' => $hostId,
                'host_name' => $host['name'] ?? '',
                'host_status' => $host['status'] ?? '',
                'message' => '该产品不是上游产品，需要转交高级技术人员处理',
            ], JSON_UNESCAPED_UNICODE)];
        }

        // 3. 获取供应商信息
        $supplierId = intval($upstreamProduct['supplier_id']);
        $supplier = Db::name('supplier')->where('id', $supplierId)->find();
        if (empty($supplier)) {
            return ['success' => true, 'result' => json_encode([
                'is_upstream' => false,
                'host_id' => $hostId,
                'message' => '未找到供应商信息，需要转交高级技术人员处理',
            ], JSON_UNESCAPED_UNICODE)];
        }

        $supplierType = $supplier['type'] ?? '';
        // 只处理魔方财务(finance)和业务V10系统(default)
        if (!in_array($supplierType, ['finance', 'default'])) {
            return ['success' => true, 'result' => json_encode([
                'is_upstream' => false,
                'host_id' => $hostId,
                'supplier_type' => $supplierType,
                'message' => '该供应商类型(' . $supplierType . ')不在此工具处理范围内，需要转交高级技术人员处理',
            ], JSON_UNESCAPED_UNICODE)];
        }

        // 4. 获取upstream_host_id
        $upstreamHost = Db::name('upstream_host')
            ->where('host_id', $hostId)
            ->find();
        $upstreamHostId = !empty($upstreamHost) ? intval($upstreamHost['upstream_host_id']) : 0;

        // 5. 检查host当前状态
        $hostStatus = $host['status'] ?? '';
        if ($hostStatus === 'Active') {
            // 已开通，返回服务器信息
            $serverInfo = $this->buildServerInfoString($hostId);
            return ['success' => true, 'result' => json_encode([
                'is_upstream' => true,
                'host_id' => $hostId,
                'host_status' => 'Active',
                'supplier_name' => $supplier['name'] ?? '',
                'supplier_type' => $supplierType,
                'upstream_host_id' => $upstreamHostId,
                'server_info' => $serverInfo,
                'action' => 'already_active',
                'message' => '产品已开通成功，服务器信息如下：' . $serverInfo,
            ], JSON_UNESCAPED_UNICODE)];
        }

        // 6. 查操作日志（开通相关 - 描述中包含hostId且包含开通/购买/余额关键词）
        $logs = Db::name('system_log')
            ->where('client_id', $clientId)
            ->where('description', 'like', '%' . $hostId . '%')
            ->where(function ($query) {
                $query->whereOr('description', 'like', '%开通%')
                    ->whereOr('description', 'like', '%购买%')
                    ->whereOr('description', 'like', '%余额%');
            })
            ->order('create_time', 'desc')
            ->limit(10)
            ->select()
            ->toArray();

        $logInfo = [];
        $hasBalanceInsufficient = false;
        foreach ($logs as $log) {
            // 去掉HTML标签
            $desc = strip_tags($log['description']);
            $logInfo[] = [
                'description' => $desc,
                'time' => date('Y-m-d H:i:s', $log['create_time']),
                'user_type' => $log['user_type'] ?? '',
            ];
            if (strpos($desc, '余额不足') !== false) {
                $hasBalanceInsufficient = true;
            }
        }

        // 7. 查失败任务
        $failedTasks = Db::name('task')
            ->where('status', 'Failed')
            ->where('type', 'host_create')
            ->where('rel_id', $hostId)
            ->order('id', 'desc')
            ->limit(5)
            ->select()
            ->toArray();

        $taskInfo = [];
        $latestFailReason = '';
        $latestTaskId = 0;
        foreach ($failedTasks as $task) {
            $taskInfo[] = [
                'id' => $task['id'],
                'description' => $task['description'] ?? '',
                'fail_reason' => $task['fail_reason'] ?? '',
                'start_time' => date('Y-m-d H:i:s', $task['start_time']),
                'retry' => $task['retry'] ?? 0,
            ];
            if (empty($latestFailReason)) {
                $latestFailReason = $task['fail_reason'] ?? '';
                $latestTaskId = intval($task['id']);
            }
        }

        // 8. 写入监控表并返回结果
        $monitorModel = new AiTicketHostCreateMonitorModel();
        $result = [
            'is_upstream' => true,
            'host_id' => $hostId,
            'host_status' => $hostStatus,
            'host_name' => $host['name'] ?? '',
            'supplier_name' => $supplier['name'] ?? '',
            'supplier_type' => $supplierType,
            'upstream_host_id' => $upstreamHostId,
            'logs' => $logInfo,
            'failed_tasks' => $taskInfo,
        ];

        if ($hasBalanceInsufficient) {
            // 余额不足
            $monitorModel->addMonitor([
                'ticket_id'        => $ticketId,
                'host_id'          => $hostId,
                'client_id'        => $clientId,
                'supplier_id'      => $supplierId,
                'upstream_host_id' => $upstreamHostId,
                'type'             => 'balance_insufficient',
                'task_id'          => $latestTaskId,
                'fail_reason'      => $latestFailReason,
            ]);
            $result['action'] = 'balance_insufficient';
            $result['message'] = '检测到供应商余额不足导致开通失败，已自动发起重试流程，正在等待供应商余额充值后重新开通。请回复客户：正在为您处理，请稍等。';
        } elseif (!empty($failedTasks)) {
            // 其他失败原因，需要提交供应商工单
            $monitorModel->addMonitor([
                'ticket_id'        => $ticketId,
                'host_id'          => $hostId,
                'client_id'        => $clientId,
                'supplier_id'      => $supplierId,
                'upstream_host_id' => $upstreamHostId,
                'type'             => 'submit_ticket',
                'task_id'          => $latestTaskId,
                'fail_reason'      => $latestFailReason,
            ]);
            $result['action'] = 'submit_ticket';
            $result['message'] = '检测到服务器开通失败（原因：' . $latestFailReason . '），正在联系供应商处理。请回复客户：正在为您处理，请稍等。';
        } else {
            // 没有失败记录，可能在处理中或无相关任务
            $result['action'] = 'no_failed_task';
            $result['message'] = '未检测到开通失败的任务，产品当前状态为' . $hostStatus . '，可能正在开通中。请回复客户：您的服务器正在开通中，请稍等。如长时间未开通，可转交高级技术人员处理。';
        }

        return ['success' => true, 'result' => json_encode($result, JSON_UNESCAPED_UNICODE)];
    }

    /**
     * 构建服务器信息字符串(IP、用户名、密码)
     */
    private function buildServerInfoString($hostId)
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
            if (!empty($hostAddition['os'])) {
                $info[] = '操作系统：' . $hostAddition['os'];
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

        return empty($info) ? '暂无服务器详情' : implode('，', $info);
    }
}
