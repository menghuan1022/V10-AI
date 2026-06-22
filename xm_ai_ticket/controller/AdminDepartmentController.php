<?php
namespace addon\xm_ai_ticket\controller;

use addon\xm_ai_ticket\model\AiTicketDepartmentModel;
use app\event\controller\PluginAdminBaseController;
use think\facade\Db;

/**
 * 部门AI配置管理控制器
 */
class AdminDepartmentController extends PluginAdminBaseController
{
    /**
     * 部门AI配置列表
     */
    public function departmentList()
    {
        $param = $this->request->param();
        $model = new AiTicketDepartmentModel();
        return json($model->departmentList($param));
    }

    /**
     * 保存部门AI配置
     */
    public function departmentSave()
    {
        $param = $this->request->param();
        $model = new AiTicketDepartmentModel();
        return json($model->departmentSave($param));
    }

    /**
     * 删除部门AI配置
     */
    public function departmentDelete()
    {
        $param = $this->request->param();
        $model = new AiTicketDepartmentModel();
        return json($model->departmentDelete(intval($param['id'])));
    }

    /**
     * 获取工单部门列表(供下拉选择用)
     */
    public function ticketTypeList()
    {
        $list = Db::name('addon_idcsmart_ticket_type')
            ->field('id, name')
            ->order('id asc')
            ->select()
            ->toArray();

        return json(['status' => 200, 'msg' => lang_plugins('success_message'), 'data' => ['list' => $list]]);
    }
}