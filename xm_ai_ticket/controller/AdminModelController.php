<?php
namespace addon\xm_ai_ticket\controller;

use addon\xm_ai_ticket\model\AiTicketModelModel;
use addon\xm_ai_ticket\logic\AiReplyLogic;
use app\event\controller\PluginAdminBaseController;

/**
 * AI模型管理控制器
 */
class AdminModelController extends PluginAdminBaseController
{
    /**
     * AI模型列表
     */
    public function modelList()
    {
        $param = array_merge($this->request->param(), [
            'page'  => $this->request->page,
            'limit' => $this->request->limit,
        ]);
        $model = new AiTicketModelModel();
        return json($model->modelList($param));
    }

    /**
     * AI模型详情
     */
    public function modelDetail()
    {
        $param = $this->request->param();
        $model = new AiTicketModelModel();
        return json($model->modelDetail(intval($param['id'])));
    }

    /**
     * 创建AI模型
     */
    public function modelCreate()
    {
        $param = $this->request->param();
        $model = new AiTicketModelModel();
        return json($model->modelCreate($param));
    }

    /**
     * 更新AI模型
     */
    public function modelUpdate()
    {
        $param = $this->request->param();
        $model = new AiTicketModelModel();
        return json($model->modelUpdate($param));
    }

    /**
     * 删除AI模型
     */
    public function modelDelete()
    {
        $param = $this->request->param();
        $model = new AiTicketModelModel();
        return json($model->modelDelete(intval($param['id'])));
    }

    /**
     * 测试AI模型连接
     */
    public function modelTest()
    {
        $param = $this->request->param();
        $logic = new AiReplyLogic();

        $modelConfig = [
            'provider'   => $param['provider'] ?? 'openai',
            'api_url'    => $param['api_url'] ?? '',
            'api_key'    => $param['api_key'] ?? '',
            'model'      => $param['model'] ?? '',
            'max_tokens' => intval($param['max_tokens'] ?? 256),
        ];

        // 验证必填参数
        if (empty($modelConfig['api_url'])) {
            return json(['status' => 400, 'msg' => 'API URL 不能为空']);
        }
        if (empty($modelConfig['api_key'])) {
            return json(['status' => 400, 'msg' => 'API Key 不能为空']);
        }
        if (empty($modelConfig['model'])) {
            return json(['status' => 400, 'msg' => '模型标识不能为空']);
        }

        $result = $logic->callAi($modelConfig, [
            ['role' => 'system', 'content' => '你是一个测试助手。'],
            ['role' => 'user', 'content' => '你好，请回复"连接测试成功"'],
        ]);

        if ($result['success']) {
            return json(['status' => 200, 'msg' => lang_plugins('xm_ai_ticket_test_success'), 'data' => ['response' => $result['content']]]);
        } else {
            return json(['status' => 400, 'msg' => $result['error'] ?: lang_plugins('xm_ai_ticket_test_fail'), 'debug' => $result]);
        }
    }
}