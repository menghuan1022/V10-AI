<?php
namespace addon\xm_ai_ticket\controller;

use addon\xm_ai_ticket\model\AiTicketProductModel;
use app\event\controller\PluginAdminBaseController;
use think\facade\Db;

/**
 * 部门商品关联管理控制器
 */
class AdminProductController extends PluginAdminBaseController
{
    /**
     * 部门商品关联列表
     */
    public function productList()
    {
        $param = $this->request->param();
        $param['page'] = $this->request->page;
        $param['limit'] = $this->request->limit;
        $model = new AiTicketProductModel();
        return json($model->productList($param));
    }

    /**
     * 保存部门商品关联
     */
    public function productSave()
    {
        $param = $this->request->param();
        $model = new AiTicketProductModel();
        return json($model->productSave($param));
    }

    /**
     * 删除部门商品关联
     */
    public function productDelete()
    {
        $param = $this->request->param();
        $model = new AiTicketProductModel();
        return json($model->productDelete(intval($param['id'])));
    }

    /**
     * 获取所有商品列表(供选择用)
     */
    public function allProductList()
    {
        try {
            $list = Db::name('product')
                ->field('id, name')
                ->order('id asc')
                ->select()
                ->toArray();
        } catch (\Exception $e) {
            return json(['status' => 400, 'msg' => $e->getMessage()]);
        }

        return json(['status' => 200, 'msg' => lang_plugins('success_message'), 'data' => ['list' => $list]]);
    }
}