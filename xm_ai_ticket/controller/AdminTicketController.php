<?php
namespace addon\xm_ai_ticket\controller;

use addon\xm_ai_ticket\model\AiTicketStatusModel;
use addon\xm_ai_ticket\model\AiTicketReplyModel;
use app\event\controller\PluginAdminBaseController;
use think\facade\Db;

/**
 * 工单AI状态管理控制器
 */
class AdminTicketController extends PluginAdminBaseController
{
    /**
     * 获取工单AI状态
     */
    public function ticketAiStatus()
    {
        $param = $this->request->param();
        $ticketId = intval($param['id']);

        $model = new AiTicketStatusModel();
        $status = $model->getStatusByTicketId($ticketId);

        $data = null;
        if (!empty($status)) {
            $data = $status->toArray();
            // 获取转接管理员名称
            if ($data['transfer_admin_id'] > 0) {
                $admin = Db::name('admin')->where('id', $data['transfer_admin_id'])->value('name');
                $data['transfer_admin_name'] = $admin ?: '';
            }
        }

        return json(['status' => 200, 'msg' => lang_plugins('success_message'), 'data' => $data]);
    }

    /**
     * 工单转接人工
     */
    public function ticketTransfer()
    {
        $param = $this->request->param();
        $ticketId = intval($param['id']);
        $adminId = intval($param['admin_id'] ?? 0);
        $reason = $param['reason'] ?? '';

        $model = new AiTicketStatusModel();
        $result = $model->transferToHuman($ticketId, $adminId, $reason);

        // 如果指定了管理员，调用工单转交
        if ($adminId > 0) {
            $ticket = Db::name('addon_idcsmart_ticket')->where('id', $ticketId)->find();
            if (!empty($ticket)) {
                $ticketTypeId = intval($param['ticket_type_id'] ?? $ticket['ticket_type_id']);

                // 验证管理员是否属于目标部门
                $link = Db::name('addon_idcsmart_ticket_type_admin_link')
                    ->where('ticket_type_id', $ticketTypeId)
                    ->where('admin_id', $adminId)
                    ->find();

                if (!empty($link)) {
                    // 更新工单
                    Db::name('addon_idcsmart_ticket')->where('id', $ticketId)->update([
                        'admin_id'      => $adminId,
                        'ticket_type_id' => $ticketTypeId,
                        'update_time'   => time(),
                    ]);

                    // 记录转交
                    Db::name('addon_idcsmart_ticket_forward')->insert([
                        'ticket_id'        => $ticketId,
                        'admin_id'         => get_admin_id(),
                        'forward_admin_id' => $adminId,
                        'ticket_type_id'   => $ticketTypeId,
                        'notes'            => 'AI客服转接：' . $reason,
                        'create_time'      => time(),
                        'update_time'      => time(),
                    ]);
                }
            }
        }

        return json(['status' => 200, 'msg' => lang_plugins('success_message')]);
    }

    /**
     * 重新激活AI接管
     */
    public function ticketAiReactivate()
    {
        $param = $this->request->param();
        $ticketId = intval($param['id']);

        $model = new AiTicketStatusModel();
        return json($model->reactivateAi($ticketId));
    }

    /**
     * 获取工单AI回复记录
     */
    public function ticketAiReplyLog()
    {
        $param = array_merge($this->request->param(), [
            'page'  => $this->request->page,
            'limit' => $this->request->limit,
        ]);
        $ticketId = intval($param['id']);

        $model = new AiTicketReplyModel();
        return json($model->replyLogByTicketId($ticketId, $param));
    }
}