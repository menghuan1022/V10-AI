<?php
namespace addon\xm_ai_ticket\model;

use think\Model;

/**
 * AI模型配置模型
 */
class AiTicketModelModel extends Model
{
    protected $name = 'addon_xm_ai_ticket_model';

    /**
     * AI模型列表
     */
    public function modelList($param)
    {
        $where = [];
        if (isset($param['status']) && $param['status'] !== '') {
            $where[] = ['status', '=', intval($param['status'])];
        }
        if (isset($param['provider']) && $param['provider'] !== '') {
            $where[] = ['provider', '=', $param['provider']];
        }

        $count = $this->where($where)->count();

        $list = $this->where($where)
            ->order('is_default desc, id asc')
            ->page($param['page'] ?? 1, $param['limit'] ?? 20)
            ->select()
            ->toArray();

        return ['status' => 200, 'msg' => lang_plugins('success_message'), 'data' => ['list' => $list, 'count' => $count]];
    }

    /**
     * AI模型详情
     */
    public function modelDetail($id)
    {
        $model = $this->find($id);
        if (empty($model)) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_model_not_exist')];
        }
        return ['status' => 200, 'msg' => lang_plugins('success_message'), 'data' => $model->toArray()];
    }

    /**
     * 创建AI模型
     */
    public function modelCreate($param)
    {
        $time = time();
        $data = [
            'name'       => $param['name'] ?? '',
            'provider'   => $param['provider'] ?? 'openai',
            'api_url'    => $param['api_url'] ?? '',
            'api_key'    => $param['api_key'] ?? '',
            'model'      => $param['model'] ?? '',
            'max_tokens' => intval($param['max_tokens'] ?? 256),
            'is_default' => intval($param['is_default'] ?? 0),
            'status'     => intval($param['status'] ?? 1),
            'create_time' => $time,
            'update_time' => $time,
        ];

        if (empty($data['name']) || empty($data['api_url']) || empty($data['api_key']) || empty($data['model'])) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_param_required')];
        }

        // 如果设置为默认模型，取消其他默认
        if ($data['is_default'] == 1) {
            $this->where('is_default', 1)->update(['is_default' => 0, 'update_time' => $time]);
        }

        $result = $this->create($data);
        return ['status' => 200, 'msg' => lang_plugins('success_message'), 'data' => ['id' => $result->id]];
    }

    /**
     * 更新AI模型
     */
    public function modelUpdate($param)
    {
        $id = intval($param['id']);
        $model = $this->find($id);
        if (empty($model)) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_model_not_exist')];
        }

        $time = time();
        $data = [];
        $fields = ['name', 'provider', 'api_url', 'api_key', 'model', 'max_tokens', 'is_default', 'status', 'supports_tool_call'];
        foreach ($fields as $field) {
            if (isset($param[$field])) {
                if (in_array($field, ['max_tokens', 'is_default', 'status'])) {
                    $data[$field] = intval($param[$field]);
                } else {
                    $data[$field] = $param[$field];
                }
            }
        }
        $data['update_time'] = $time;

        // 如果设置为默认模型，取消其他默认
        if (isset($data['is_default']) && $data['is_default'] == 1) {
            $this->where('is_default', 1)->where('id', '<>', $id)->update(['is_default' => 0, 'update_time' => $time]);
        }

        $model->save($data);
        return ['status' => 200, 'msg' => lang_plugins('success_message')];
    }

    /**
     * 删除AI模型
     */
    public function modelDelete($id)
    {
        $model = $this->find($id);
        if (empty($model)) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_model_not_exist')];
        }

        // 检查是否有部门在使用此模型
        $deptCount = AiTicketDepartmentModel::where('ai_model_id', $id)->count();
        if ($deptCount > 0) {
            return ['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_model_used_by_department')];
        }

        $model->delete();
        return ['status' => 200, 'msg' => lang_plugins('success_message')];
    }

    /**
     * 获取默认模型
     */
    public function getDefaultModel()
    {
        return $this->where('is_default', 1)->where('status', 1)->find();
    }

    /**
     * 获取所有启用的模型
     */
    public function getActiveModels()
    {
        return $this->where('status', 1)->select()->toArray();
    }
}