<?php
namespace addon\xm_ai_ticket\controller;

use addon\xm_ai_ticket\XmAiTicket;
use addon\xm_ai_ticket\logic\AiReplyLogic;
use app\event\controller\PluginAdminBaseController;

/**
 * 插件设置控制器
 */
class AdminSettingController extends PluginAdminBaseController
{
    /**
     * 获取插件配置
     */
    public function getConfig()
    {
        $plugin = new XmAiTicket();
        $config = $plugin->getConfig();
        return json(['status' => 200, 'msg' => lang_plugins('success_message'), 'data' => $config]);
    }

    /**
     * 保存插件配置
     */
    public function saveConfig()
    {
        $param = $this->request->param();
        $plugin = new XmAiTicket();
        $result = $plugin->setConfig($param);
        if ($result !== false) {
            return json(['status' => 200, 'msg' => lang_plugins('success_message')]);
        }
        return json(['status' => 400, 'msg' => lang_plugins('fail_message')]);
    }

    /**
     * 测试飞书Webhook
     */
    public function testFeishu()
    {
        $param = $this->request->param();
        $webhook = $param['feishu_webhook'] ?? '';
        if (empty($webhook)) {
            return json(['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_feishu_webhook_required')]);
        }

        $logic = new AiReplyLogic();
        $result = $logic->sendFeishuNotification($webhook, '【测试消息】AI工单客服飞书通知测试成功！');

        if ($result) {
            return json(['status' => 200, 'msg' => lang_plugins('xm_ai_ticket_feishu_test_success')]);
        }
        return json(['status' => 400, 'msg' => lang_plugins('xm_ai_ticket_feishu_test_fail')]);
    }
}
