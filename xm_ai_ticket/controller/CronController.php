<?php
namespace addon\xm_ai_ticket\controller;

use addon\xm_ai_ticket\logic\AiReplyLogic;
use addon\xm_ai_ticket\XmAiTicket;

/**
 * 定时任务控制器（无需鉴权）
 */
class CronController
{
    /**
     * AI工单自动回复定时任务
     * 通过访问 /ai_ticket_cron 触发
     */
    public function index()
    {
        // 检查全局开关
        try {
            $plugin = new XmAiTicket();
            $config = $plugin->getConfig();
            if (empty($config['enable'])) {
                return json(['status' => 200, 'msg' => 'AI not enabled']);
            }
        } catch (\Exception $e) {
            return json(['status' => 500, 'msg' => 'Config error: ' . $e->getMessage()]);
        }

        try {
            $logic = new AiReplyLogic();
            $logic->processAutoReply();
            return json(['status' => 200, 'msg' => 'ok']);
        } catch (\Exception $e) {
            return json(['status' => 500, 'msg' => $e->getMessage()]);
        }
    }
}
