<?php
namespace addon\xm_ai_ticket\model;

use think\Model;
use addon\xm_ai_ticket\XmAiTicket;

/**
 * 工单部门AI配置模型
 */
class AiTicketDepartmentModel extends Model
{
    protected $name = 'addon_xm_ai_ticket_department';

    /**
     * 部门AI配置列表
     */
    public function departmentList($param)
    {
        $plugin = new XmAiTicket();
        $defaultConfig = $plugin->getConfig();

        $list = $this->alias('d')
            ->leftJoin('addon_idcsmart_ticket_type t', 'd.ticket_type_id = t.id')
            ->leftJoin('addon_xm_ai_ticket_model m', 'd.ai_model_id = m.id')
            ->field('d.*, t.name as type_name, m.name as model_name')
            ->order('d.id asc')
            ->select()
            ->toArray();

        // leftJoin查询返回的字段为字符串类型，需要转换整数字段以匹配前端组件
        foreach ($list as &$item) {
            $item['auto_reply'] = intval($item['auto_reply']);
            $item['ticket_type_id'] = intval($item['ticket_type_id']);
            $item['ai_model_id'] = intval($item['ai_model_id']);
            $item['reply_interval'] = intval($item['reply_interval']);
            $item['max_reply_chars'] = intval($item['max_reply_chars']);
        }
        unset($item);

        return ['status' => 200, 'msg' => lang_plugins('success_message'), 'data' => ['list' => $list, 'default_config' => $defaultConfig]];
    }

    /**
     * 保存部门AI配置
     * 支持批量保存，参数为数组
     */
    public function departmentSave($param)
    {
        $time = time();

        // 单个保存
        if (!isset($param[0])) {
            $param = [$param];
        }

        foreach ($param as $item) {
            $ticketTypeId = intval($item['ticket_type_id']);
            if ($ticketTypeId <= 0) {
                continue;
            }

            $existing = $this->where('ticket_type_id', $ticketTypeId)->find();

            $data = [
                'ticket_type_id' => $ticketTypeId,
                'ai_model_id'    => intval($item['ai_model_id'] ?? 0),
                'auto_reply'     => intval($item['auto_reply'] ?? 1),
                'reply_interval' => intval($item['reply_interval'] ?? 60),
                'persona'        => $item['persona'] ?? '',
                'max_reply_chars' => intval($item['max_reply_chars'] ?? 255),
                'closing_remark' => $item['closing_remark'] ?? '',
                'update_time'    => $time,
            ];

            if (empty($existing)) {
                $data['create_time'] = $time;
                $this->create($data);
            } else {
                $existing->save($data);
            }
        }

        return ['status' => 200, 'msg' => lang_plugins('success_message')];
    }

    /**
     * 删除部门AI配置
     */
    public function departmentDelete($id)
    {
        $dept = $this->find($id);
        if (empty($dept)) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_department_not_exist')];
        }

        $dept->delete();
        return ['status' => 200, 'msg' => lang_plugins('success_message')];
    }

    /**
     * 根据工单部门ID获取AI配置
     */
    public function getConfigByTypeId($ticketTypeId)
    {
        return $this->where('ticket_type_id', $ticketTypeId)->where('auto_reply', 1)->find();
    }
}