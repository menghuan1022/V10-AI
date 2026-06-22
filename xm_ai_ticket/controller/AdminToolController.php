<?php
namespace addon\xm_ai_ticket\controller;

use addon\xm_ai_ticket\model\AiTicketToolModel;
use app\event\controller\PluginAdminBaseController;

/**
 * AI工具管理控制器
 */
class AdminToolController extends PluginAdminBaseController
{
    /**
     * 工具列表
     */
    public function toolList()
    {
        $param = array_merge($this->request->param(), [
            'page'  => $this->request->page,
            'limit' => $this->request->limit,
        ]);
        $model = new AiTicketToolModel();
        return json($model->toolList($param));
    }

    /**
     * 创建工具
     */
    public function toolCreate()
    {
        $param = $this->request->param();
        $model = new AiTicketToolModel();
        return json($model->toolCreate($param));
    }

    /**
     * 更新工具
     */
    public function toolUpdate()
    {
        $param = $this->request->param();
        $model = new AiTicketToolModel();
        return json($model->toolUpdate($param));
    }

    /**
     * 删除工具
     */
    public function toolDelete()
    {
        $param = $this->request->param();
        $model = new AiTicketToolModel();
        return json($model->toolDelete(intval($param['id'])));
    }

    /**
     * 测试工具执行
     */
    public function toolTest()
    {
        $param = $this->request->param();
        $model = new AiTicketToolModel();
        return json($model->toolTest($param));
    }
}
