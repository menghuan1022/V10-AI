<?php
namespace addon\xm_ai_ticket\controller;

use addon\xm_ai_ticket\model\AiTicketReplyModel;
use app\event\controller\PluginAdminBaseController;

/**
 * AI回复日志控制器
 */
class AdminLogController extends PluginAdminBaseController
{
    /**
     * 日志列表
     */
    public function logList()
    {
        $param = $this->request->param();
        $param['page'] = $this->request->page;
        $param['limit'] = $this->request->limit;
        $model = new AiTicketReplyModel();
        return json($model->logList($param));
    }

    /**
     * 删除日志
     */
    public function logDelete()
    {
        $param = $this->request->param();
        $id = intval($param['id'] ?? 0);
        if ($id <= 0) {
            return json(['status' => 400, 'msg' => lang_plugins('param_error')]);
        }
        $model = new AiTicketReplyModel();
        return json($model->logDelete($id));
    }
}
