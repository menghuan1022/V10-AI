<?php
namespace addon\xm_ai_ticket\model;

use think\Model;
use think\facade\Db;

/**
 * 服务器开通监控模型
 */
class AiTicketHostCreateMonitorModel extends Model
{
    protected $name = 'addon_xm_ai_ticket_host_create_monitor';

    /**
     * 添加监控记录
     */
    public function addMonitor($data)
    {
        // 检查是否已存在相同工单+产品的pending记录
        $exists = $this->where('ticket_id', $data['ticket_id'])
            ->where('host_id', $data['host_id'])
            ->where('status', 'pending')
            ->find();
        if (!empty($exists)) {
            return $exists;
        }

        $time = time();
        $data['create_time'] = $time;
        $data['update_time'] = $time;
        $data['retry_count'] = 0;
        $data['last_retry_time'] = 0;
        $data['feishu_sent'] = 0;
        $data['status'] = 'pending';

        return $this->create($data);
    }

    /**
     * 获取待处理的监控记录
     */
    public function getPendingMonitors()
    {
        return $this->where('status', 'pending')->select()->toArray();
    }

    /**
     * 更新监控记录
     */
    public function updateMonitor($id, $data)
    {
        $monitor = $this->find($id);
        if (empty($monitor)) {
            return false;
        }
        $data['update_time'] = time();
        $monitor->save($data);
        return true;
    }
}
