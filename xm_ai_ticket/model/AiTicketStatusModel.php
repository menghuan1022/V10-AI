<?php
namespace addon\xm_ai_ticket\model;

use think\Model;
use think\facade\Db;
use addon\xm_ai_ticket\XmAiTicket;

/**
 * 工单AI状态模型
 */
class AiTicketStatusModel extends Model
{
    protected $name = 'addon_xm_ai_ticket_status';

    /**
     * 获取工单AI状态
     */
    public function getStatusByTicketId($ticketId)
    {
        return $this->where('ticket_id', $ticketId)->find();
    }

    /**
     * 初始化工单AI状态(工单创建时调用)
     */
    public function initStatus($ticketId, $ticketTypeId)
    {
        $existing = $this->where('ticket_id', $ticketId)->find();
        if (!empty($existing)) {
            return $existing;
        }

        // 判断该部门是否配置了AI
        $deptConfig = AiTicketDepartmentModel::where('ticket_type_id', $ticketTypeId)->where('auto_reply', 1)->find();

        $data = [
            'ticket_id'          => $ticketId,
            'ai_active'          => !empty($deptConfig) ? 1 : 0,
            'last_ai_reply_time' => 0,
            'transfer_admin_id'  => 0,
            'transfer_time'      => 0,
            'transfer_reason'    => '',
        ];

        return $this->create($data);
    }

    /**
     * 转接为人工回复
     */
    public function transferToHuman($ticketId, $adminId = 0, $reason = '')
    {
        $status = $this->where('ticket_id', $ticketId)->find();
        if (empty($status)) {
            $status = $this->create([
                'ticket_id'          => $ticketId,
                'ai_active'          => 0,
                'last_ai_reply_time' => 0,
                'transfer_admin_id'  => $adminId,
                'transfer_time'      => time(),
                'transfer_reason'    => $reason,
            ]);
        } else {
            $status->save([
                'ai_active'         => 0,
                'transfer_admin_id' => $adminId,
                'transfer_time'     => time(),
                'transfer_reason'   => $reason,
            ]);
        }

        return $status;
    }

    /**
     * 重新激活AI接管
     */
    public function reactivateAi($ticketId)
    {
        $status = $this->where('ticket_id', $ticketId)->find();
        if (empty($status)) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_status_not_exist')];
        }

        $status->save([
            'ai_active'         => 1,
            'transfer_admin_id' => 0,
            'transfer_time'     => 0,
            'transfer_reason'   => '',
        ]);

        return ['status' => 200, 'msg' => lang_plugins('success_message')];
    }

    /**
     * 更新AI回复时间（如果记录不存在则创建）
     */
    public function updateAiReplyTime($ticketId)
    {
        $status = $this->where('ticket_id', $ticketId)->find();
        if (!empty($status)) {
            $status->save(['last_ai_reply_time' => time()]);
        } else {
            // 没有记录则创建，AI已激活
            $this->create([
                'ticket_id'          => $ticketId,
                'ai_active'          => 1,
                'last_ai_reply_time' => time(),
                'transfer_admin_id'  => 0,
                'transfer_time'      => 0,
                'transfer_reason'    => '',
            ]);
        }
    }

    /**
     * 查找需要AI自动回复的工单
     * 核心逻辑：从工单表直接查找未关闭+有用户回复的工单，匹配部门AI配置
     */
    public function findTicketsNeedAiReply()
    {
        // 获取所有启用了AI的部门
        $deptConfigs = AiTicketDepartmentModel::where('auto_reply', 1)->select()->toArray();
        if (empty($deptConfigs)) {
            return [];
        }

        $deptConfigMap = [];
        $ticketTypeIds = [];
        foreach ($deptConfigs as $dc) {
            $deptConfigMap[$dc['ticket_type_id']] = $dc;
            $ticketTypeIds[] = $dc['ticket_type_id'];
        }

        // 查找未关闭的工单(状态1待接单,2用户已回复)，且属于AI启用的部门
        // 注意：状态5(处理中)表示正在被AI处理，不查询
        $tickets = Db::name('addon_idcsmart_ticket')
            ->whereIn('ticket_type_id', $ticketTypeIds)
            ->whereIn('status', [1, 2])
            ->select()
            ->toArray();

        if (empty($tickets)) {
            return [];
        }

        // 获取已有AI状态记录的工单ID
        $ticketIds = array_column($tickets, 'id');
        $statusRecords = $this->whereIn('ticket_id', $ticketIds)->select()->toArray();
        $statusMap = [];
        foreach ($statusRecords as $sr) {
            $statusMap[$sr['ticket_id']] = $sr;
        }

        $now = time();
        $needReply = [];
        foreach ($tickets as $ticket) {
            $ticketId = $ticket['id'];
            $ticketTypeId = $ticket['ticket_type_id'];
            $deptConfig = $deptConfigMap[$ticketTypeId] ?? null;
            if (empty($deptConfig)) {
                continue;
            }

            // 检查AI状态：如果已转人工则跳过
            $status = $statusMap[$ticketId] ?? null;
            if (!empty($status) && $status['ai_active'] == 0) {
                continue;
            }

            // 获取所有回复，按时间倒序
            $replies = Db::name('addon_idcsmart_ticket_reply')
                ->where('ticket_id', $ticketId)
                ->order('create_time', 'desc')
                ->select()
                ->toArray();

            // 没有任何回复 → 工单刚创建，需要AI回复
            if (empty($replies)) {
                $replyInterval = max(intval($deptConfig['reply_interval']), 1);
                if ($now - $ticket['create_time'] < $replyInterval) {
                    continue;
                }
                $needReply[] = [
                    'ticket'      => $ticket,
                    'dept_config' => $deptConfig,
                ];
                continue;
            }

            // 找到最后一条客户消息的时间和最后一条AI/客服回复的时间
            $lastClientTime = 0;
            $lastAdminTime = 0;
            $lastClientContent = '';
            foreach ($replies as $reply) {
                if ($reply['type'] === 'Client' && $lastClientTime === 0) {
                    $lastClientTime = $reply['create_time'];
                    $lastClientContent = $reply['content'];
                }
                if ($reply['type'] !== 'Client' && $lastAdminTime === 0) {
                    $lastAdminTime = $reply['create_time'];
                }
                if ($lastClientTime > 0 && $lastAdminTime > 0) {
                    break;
                }
            }

            // 没有客户消息 → 不需要AI回复
            if ($lastClientTime === 0) {
                continue;
            }

            // 关键判断：最后一条客户消息必须在最后一条客服/AI回复之后
            // 即：客户发了消息，且还没有人回复过这条消息
            if ($lastAdminTime > 0 && $lastAdminTime > $lastClientTime) {
                // 客服/AI已经回复过了（在客户最后一条消息之后），跳过
                continue;
            }

            // 客户有未回复的消息，检查间隔
            $replyInterval = max(intval($deptConfig['reply_interval']), 1);

            // 以客户最后一条消息的时间为基准等待间隔
            // 这样客户连续发多条消息时，等最后一条发完后才开始计时
            if ($now - $lastClientTime < $replyInterval) {
                continue;
            }

            $needReply[] = [
                'ticket'      => $ticket,
                'dept_config' => $deptConfig,
            ];
        }

        return $needReply;
    }

    /**
     * 查找已转人工但客户还在发消息的工单
     * 如果客户在转人工后继续发消息且没有人工回复，需要AI自动回复一条等待提示
     * @return array [['ticket' => ..., 'dept_config' => ..., 'transfer_reply' => true], ...]
     */
    public function findTransferWaitReply()
    {
        // 查找已转人工(ai_active=0)的工单
        $transferRecords = $this->where('ai_active', 0)->select()->toArray();
        if (empty($transferRecords)) {
            return [];
        }

        $ticketIds = array_column($transferRecords, 'ticket_id');
        $transferMap = [];
        foreach ($transferRecords as $tr) {
            $transferMap[$tr['ticket_id']] = $tr;
        }

        // 查找这些工单中状态为2(用户已回复)的
        $tickets = Db::name('addon_idcsmart_ticket')
            ->whereIn('id', $ticketIds)
            ->where('status', 2)
            ->select()
            ->toArray();

        if (empty($tickets)) {
            return [];
        }

        // 获取部门配置
        $deptConfigs = AiTicketDepartmentModel::where('auto_reply', 1)->select()->toArray();
        $deptConfigMap = [];
        foreach ($deptConfigs as $dc) {
            $deptConfigMap[$dc['ticket_type_id']] = $dc;
        }

        // 批量获取这些工单的AI回复时间（用于区分AI回复和人工客服回复）
        $activeTicketIds = array_column($tickets, 'id');
        $aiReplyRecords = Db::name('addon_xm_ai_ticket_reply')
            ->whereIn('ticket_id', $activeTicketIds)
            ->field('ticket_id, create_time')
            ->select()
            ->toArray();
        $aiReplyTimeMap = []; // [ticket_id => [time1, time2, ...]]
        foreach ($aiReplyRecords as $ar) {
            $aiReplyTimeMap[$ar['ticket_id']][$ar['create_time']] = true;
        }

        $now = time();
        $needReply = [];

        foreach ($tickets as $ticket) {
            $ticketId = $ticket['id'];
            $deptConfig = $deptConfigMap[$ticket['ticket_type_id']] ?? null;
            if (empty($deptConfig)) {
                continue;
            }

            // 检查客户是否有未回复的消息
            $replies = Db::name('addon_idcsmart_ticket_reply')
                ->where('ticket_id', $ticketId)
                ->order('create_time', 'desc')
                ->select()
                ->toArray();

            // 该工单的AI回复时间集合
            $aiTimes = $aiReplyTimeMap[$ticketId] ?? [];

            $lastClientTime = 0;
            $lastHumanTime = 0;  // 人工客服回复时间（排除AI）
            foreach ($replies as $reply) {
                if ($reply['type'] === 'Client' && $lastClientTime === 0) {
                    $lastClientTime = $reply['create_time'];
                }
                // 只统计人工客服回复（type=Admin但不是AI回复的时间）
                if ($reply['type'] !== 'Client' && $lastHumanTime === 0) {
                    $isAiReply = isset($aiTimes[$reply['create_time']]);
                    if (!$isAiReply) {
                        $lastHumanTime = $reply['create_time'];
                    }
                }
                if ($lastClientTime > 0 && $lastHumanTime > 0) {
                    break;
                }
            }

            // 没有客户消息或已有人工客服回复，跳过
            if ($lastClientTime === 0 || ($lastHumanTime > 0 && $lastHumanTime > $lastClientTime)) {
                continue;
            }

            // 等待间隔
            $replyInterval = max(intval($deptConfig['reply_interval']), 1);
            if ($now - $lastClientTime < $replyInterval) {
                continue;
            }

            $needReply[] = [
                'ticket'          => $ticket,
                'dept_config'     => $deptConfig,
                'transfer_reply'  => true,
            ];
        }

        return $needReply;
    }
}