<?php
namespace addon\xm_ai_ticket\model;

use think\Model;
use think\facade\Db;

/**
 * AI回复记录模型
 */
class AiTicketReplyModel extends Model
{
    protected $name = 'addon_xm_ai_ticket_reply';

    /**
     * AI回复日志列表(全局)
     */
    public function logList($param)
    {
        $where = [];
        if (!empty($param['ticket_id'])) {
            $where[] = ['r.ticket_id', '=', intval($param['ticket_id'])];
        }
        if (!empty($param['ai_model_id'])) {
            $where[] = ['r.ai_model_id', '=', intval($param['ai_model_id'])];
        }

        $count = $this->alias('r')
            ->leftJoin('addon_xm_ai_ticket_model m', 'r.ai_model_id = m.id')
            ->leftJoin('addon_idcsmart_ticket t', 'r.ticket_id = t.id')
            ->where($where)
            ->count();

        $list = $this->alias('r')
            ->leftJoin('addon_xm_ai_ticket_model m', 'r.ai_model_id = m.id')
            ->leftJoin('addon_idcsmart_ticket t', 'r.ticket_id = t.id')
            ->where($where)
            ->field('r.*, m.name as model_name, t.title as ticket_title')
            ->order('r.id desc')
            ->page($param['page'] ?? 1, $param['limit'] ?? 20)
            ->select()
            ->toArray();

        return ['status' => 200, 'msg' => lang_plugins('success_message'), 'data' => ['list' => $list, 'count' => $count]];
    }

    /**
     * AI回复记录列表(按工单ID)
     */
    public function replyLogByTicketId($ticketId, $param = [])
    {
        $count = $this->where('ticket_id', $ticketId)->count();

        $list = $this->alias('r')
            ->leftJoin('addon_xm_ai_ticket_model m', 'r.ai_model_id = m.id')
            ->where('r.ticket_id', $ticketId)
            ->field('r.*, m.name as model_name')
            ->order('r.id desc')
            ->page($param['page'] ?? 1, $param['limit'] ?? 20)
            ->select()
            ->toArray();

        return ['status' => 200, 'msg' => lang_plugins('success_message'), 'data' => ['list' => $list, 'count' => $count]];
    }

    /**
     * 创建AI回复记录
     */
    public function createReply($data)
    {
        return $this->create($data);
    }

    /**
     * 删除日志
     */
    public function logDelete($id)
    {
        $item = $this->find($id);
        if (empty($item)) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_log_not_exist')];
        }
        $item->delete();
        return ['status' => 200, 'msg' => lang_plugins('success_message')];
    }
}