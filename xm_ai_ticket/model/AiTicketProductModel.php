<?php
namespace addon\xm_ai_ticket\model;

use think\Model;

/**
 * 部门商品关联模型
 */
class AiTicketProductModel extends Model
{
    protected $name = 'addon_xm_ai_ticket_product';

    /**
     * 部门商品关联列表
     */
    public function productList($param)
    {
        $where = [];
        if (isset($param['ticket_type_id']) && $param['ticket_type_id'] > 0) {
            $where[] = ['p.ticket_type_id', '=', intval($param['ticket_type_id'])];
        }

        $count = $this->alias('p')
            ->leftJoin('addon_idcsmart_ticket_type t', 'p.ticket_type_id = t.id')
            ->leftJoin('product pro', 'p.product_id = pro.id')
            ->where($where)
            ->count();

        $list = $this->alias('p')
            ->leftJoin('addon_idcsmart_ticket_type t', 'p.ticket_type_id = t.id')
            ->leftJoin('product pro', 'p.product_id = pro.id')
            ->where($where)
            ->field('p.*, t.name as type_name, pro.name as product_name')
            ->order('p.id asc')
            ->page($param['page'] ?? 1, $param['limit'] ?? 20)
            ->select()
            ->toArray();

        return ['status' => 200, 'msg' => lang_plugins('success_message'), 'data' => ['list' => $list, 'count' => $count]];
    }

    /**
     * 保存部门商品关联
     * 支持批量：ticket_type_id + product_ids 数组
     */
    public function productSave($param)
    {
        $ticketTypeId = intval($param['ticket_type_id']);
        if ($ticketTypeId <= 0) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_param_required')];
        }

        $productIds = $param['product_ids'] ?? [];
        if (empty($productIds)) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_param_required')];
        }

        $time = time();
        $insertData = [];
        foreach ($productIds as $pid) {
            $pid = intval($pid);
            if ($pid <= 0) continue;

            // 检查是否已存在
            $exists = $this->where('ticket_type_id', $ticketTypeId)->where('product_id', $pid)->find();
            if (!empty($exists)) continue;

            $insertData[] = [
                'ticket_type_id' => $ticketTypeId,
                'product_id'     => $pid,
                'create_time'    => $time,
            ];
        }

        if (!empty($insertData)) {
            $this->insertAll($insertData);
        }

        return ['status' => 200, 'msg' => lang_plugins('success_message')];
    }

    /**
     * 删除部门商品关联
     */
    public function productDelete($id)
    {
        $item = $this->find($id);
        if (empty($item)) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_product_not_exist')];
        }

        $item->delete();
        return ['status' => 200, 'msg' => lang_plugins('success_message')];
    }

    /**
     * 根据产品ID获取可提交的工单部门ID列表
     */
    public function getTypeIdsByProductId($productId)
    {
        return $this->where('product_id', $productId)->column('ticket_type_id');
    }
}