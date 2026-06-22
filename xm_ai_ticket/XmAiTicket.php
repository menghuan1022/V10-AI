<?php
namespace addon\xm_ai_ticket;

use addon\xm_ai_ticket\model\AiTicketStatusModel;
use addon\xm_ai_ticket\logic\AiReplyLogic;
use app\common\lib\Plugin;
use think\facade\Db;

/**
 * AI工单客服插件
 * @author 小梦
 * @time 2026-06-03
 */
class XmAiTicket extends Plugin
{
    public $info = array(
        'name'        => 'XmAiTicket',
        'title'       => 'AI工单客服',
        'description' => 'AI自动回复工单，支持多AI大模型、人设设定、自动回复间隔、工单转接人工等功能',
        'author'      => '小梦',
        'version'     => '1.0.0',
    );

    public $noNav = 0;

    public function install()
    {
        $sql = [
            "CREATE TABLE IF NOT EXISTS `idcsmart_addon_xm_ai_ticket_model` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(100) NOT NULL DEFAULT '' COMMENT '模型名称',
              `provider` varchar(50) NOT NULL DEFAULT 'openai' COMMENT '提供商:openai/anthropic/baidu/zhipu/moonshot/deepseek/custom',
              `api_url` varchar(255) NOT NULL DEFAULT '' COMMENT 'API地址',
              `api_key` varchar(255) NOT NULL DEFAULT '' COMMENT 'API密钥',
              `model` varchar(100) NOT NULL DEFAULT '' COMMENT '模型标识',
              `max_tokens` int(11) NOT NULL DEFAULT 256 COMMENT '最大输出token',
              `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认模型',
              `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:1启用 0禁用',
              `create_time` int(11) NOT NULL DEFAULT 0,
              `update_time` int(11) NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI模型配置表'",

            "CREATE TABLE IF NOT EXISTS `idcsmart_addon_xm_ai_ticket_department` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `ticket_type_id` int(11) NOT NULL DEFAULT 0 COMMENT '工单部门ID',
              `ai_model_id` int(11) NOT NULL DEFAULT 0 COMMENT 'AI模型ID',
              `auto_reply` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用自动回复',
              `reply_interval` int(11) NOT NULL DEFAULT 60 COMMENT '自动回复间隔(秒)',
              `persona` text COMMENT '模型人设',
              `max_reply_chars` int(11) NOT NULL DEFAULT 255 COMMENT '最大回复字符数',
              `closing_remark` varchar(500) NOT NULL DEFAULT '' COMMENT '结束语',
              `create_time` int(11) NOT NULL DEFAULT 0,
              `update_time` int(11) NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              UNIQUE KEY `ticket_type_id` (`ticket_type_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单部门AI配置'",

            "CREATE TABLE IF NOT EXISTS `idcsmart_addon_xm_ai_ticket_product` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `ticket_type_id` int(11) NOT NULL DEFAULT 0 COMMENT '工单部门ID',
              `product_id` int(11) NOT NULL DEFAULT 0 COMMENT '商品ID',
              `create_time` int(11) NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              UNIQUE KEY `type_product` (`ticket_type_id`, `product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='部门商品关联表'",

            "CREATE TABLE IF NOT EXISTS `idcsmart_addon_xm_ai_ticket_reply` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `ticket_id` int(11) NOT NULL DEFAULT 0 COMMENT '工单ID',
              `ai_model_id` int(11) NOT NULL DEFAULT 0 COMMENT '使用的AI模型ID',
              `context` text COMMENT '发送给AI的上下文',
              `response` text COMMENT 'AI原始响应',
              `reply_content` text COMMENT '实际回复内容',
              `tokens_used` int(11) NOT NULL DEFAULT 0 COMMENT '消耗token数',
              `create_time` int(11) NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              KEY `ticket_id` (`ticket_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI回复记录'",

            "CREATE TABLE IF NOT EXISTS `idcsmart_addon_xm_ai_ticket_status` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `ticket_id` int(11) NOT NULL DEFAULT 0 COMMENT '工单ID',
              `ai_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'AI是否接管:1是 0否(已转人工)',
              `last_ai_reply_time` int(11) NOT NULL DEFAULT 0 COMMENT '上次AI回复时间',
              `transfer_admin_id` int(11) NOT NULL DEFAULT 0 COMMENT '转接的管理员ID',
              `transfer_time` int(11) NOT NULL DEFAULT 0 COMMENT '转接时间',
              `transfer_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '转接原因',
              PRIMARY KEY (`id`),
              UNIQUE KEY `ticket_id` (`ticket_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单AI状态'",

            "CREATE TABLE IF NOT EXISTS `idcsmart_addon_xm_ai_ticket_tool` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(100) NOT NULL DEFAULT '' COMMENT '工具名称(英文)',
              `description` varchar(500) NOT NULL DEFAULT '' COMMENT '工具描述(AI根据此描述决定是否调用)',
              `type` varchar(50) NOT NULL DEFAULT 'sql' COMMENT '类型:sql=数据库查询,api=HTTP接口',
              `config` text COMMENT '工具配置(JSON)',
              `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
              `create_time` int(11) NOT NULL DEFAULT 0,
              `update_time` int(11) NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI工具配置表'",

            "INSERT INTO `idcsmart_addon_xm_ai_ticket_tool` (`name`, `description`, `type`, `config`, `status`, `create_time`) VALUES
            ('get_client_products', '查询客户名下的所有产品/服务信息，包括状态、IP地址、到期时间、续费金额等', 'sql', '{\"query\":\"SELECT h.id,h.name,h.status,hi.dedicate_ip,hi.assign_ip,p.name as product_name,h.billing_cycle_name,h.renew_amount,FROM_UNIXTIME(h.due_time,\\\\\"%Y-%m-%d\\\\\") as due_date FROM idcsmart_host h LEFT JOIN idcsmart_product p ON p.id=h.product_id LEFT JOIN idcsmart_host_ip hi ON hi.host_id=h.id WHERE h.client_id={client_id} AND h.is_delete=0 AND h.is_sub=0 ORDER BY h.id DESC LIMIT 20\",\"params\":[\"client_id\"]}', 1, 0),
            ('get_client_orders', '查询客户近期的订单记录，包括订单类型、金额、状态等', 'sql', '{\"query\":\"SELECT o.id,o.type,o.amount,o.status,FROM_UNIXTIME(o.create_time,\\\\\"%Y-%m-%d %H:%i\\\\\") as create_date FROM idcsmart_order o WHERE o.client_id={client_id} ORDER BY o.create_time DESC LIMIT 10\",\"params\":[\"client_id\"]}', 1, 0),
            ('get_ticket_products', '查询当前工单关联的产品信息', 'sql', '{\"query\":\"SELECT h.id,h.name,h.status,hi.dedicate_ip,hi.assign_ip,p.name as product_name,h.billing_cycle_name,h.renew_amount,FROM_UNIXTIME(h.due_time,\\\\\"%Y-%m-%d\\\\\") as due_date FROM idcsmart_host h LEFT JOIN idcsmart_product p ON p.id=h.product_id LEFT JOIN idcsmart_host_ip hi ON hi.host_id=h.id LEFT JOIN idcsmart_addon_idcsmart_ticket_host_link thl ON thl.host_id=h.id WHERE thl.ticket_id={ticket_id}\",\"params\":[\"ticket_id\"]}', 1, 0)",

            "ALTER TABLE `idcsmart_addon_xm_ai_ticket_model` ADD COLUMN `supports_tool_call` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否支持tool_calling:0否/未知 1是' AFTER `status`",

            "CREATE TABLE IF NOT EXISTS `idcsmart_addon_xm_ai_ticket_host_create_monitor` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `ticket_id` int(11) NOT NULL DEFAULT 0 COMMENT '工单ID',
              `host_id` int(11) NOT NULL DEFAULT 0 COMMENT '本地产品ID',
              `client_id` int(11) NOT NULL DEFAULT 0 COMMENT '客户ID',
              `supplier_id` int(11) NOT NULL DEFAULT 0 COMMENT '供应商ID',
              `upstream_host_id` int(11) NOT NULL DEFAULT 0 COMMENT '上游产品ID',
              `type` varchar(30) NOT NULL DEFAULT '' COMMENT '类型:balance_insufficient/submit_ticket',
              `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态:pending/success/error/ticket_submitted',
              `task_id` int(11) NOT NULL DEFAULT 0 COMMENT '关联的失败任务ID',
              `fail_reason` varchar(500) NOT NULL DEFAULT '' COMMENT '失败原因',
              `retry_count` int(11) NOT NULL DEFAULT 0 COMMENT '已重试次数',
              `last_retry_time` int(11) NOT NULL DEFAULT 0 COMMENT '上次重试时间',
              `feishu_sent` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已发飞书通知',
              `result` text COMMENT '处理结果',
              `create_time` int(11) NOT NULL DEFAULT 0,
              `update_time` int(11) NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`),
              KEY `ticket_id` (`ticket_id`),
              KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='服务器开通监控表'",

            "INSERT INTO `idcsmart_addon_xm_ai_ticket_tool` (`name`, `description`, `type`, `config`, `status`, `create_time`) VALUES
            ('check_host_create_status', '检测服务器开通状态，判断产品是否为上游供应商产品，查询开通日志和失败任务，适用于客户要求开通/重新开通服务器的场景。当客户提到\"开通服务器\"、\"重新开通\"、\"开通产品\"等相关请求时使用此工具', 'api', '{\"params\":[\"ticket_id\",\"host_id\"]}', 1, 0)",
        ];

        foreach ($sql as $v) {
            Db::execute($v);
        }

        return true;
    }

    public function uninstall()
    {
        $sql = [
            "DROP TABLE IF EXISTS `idcsmart_addon_xm_ai_ticket_model`",
            "DROP TABLE IF EXISTS `idcsmart_addon_xm_ai_ticket_department`",
            "DROP TABLE IF EXISTS `idcsmart_addon_xm_ai_ticket_product`",
            "DROP TABLE IF EXISTS `idcsmart_addon_xm_ai_ticket_reply`",
            "DROP TABLE IF EXISTS `idcsmart_addon_xm_ai_ticket_status`",
            "DROP TABLE IF EXISTS `idcsmart_addon_xm_ai_ticket_tool`",
            "DROP TABLE IF EXISTS `idcsmart_addon_xm_ai_ticket_host_create_monitor`",
        ];
        foreach ($sql as $v) {
            Db::execute($v);
        }

        return true;
    }

    /**
     * 每分钟定时任务 - 检查并执行AI自动回复
     */
    public function minuteCron($param)
    {
        $config = $this->getConfig();
        if (empty($config['enable'])) {
            return true;
        }

        $logic = new AiReplyLogic();
        $logic->processAutoReply();
        $logic->processHostCreateMonitor();

        return true;
    }

    /**
     * 保存插件配置到数据库
     */
    public function setConfig($config)
    {
        $name = $this->getName();
        $PluginModel = new \app\admin\model\PluginModel();
        $plugin = $PluginModel->where('name', $name)->find();
        if (empty($plugin)) {
            return false;
        }
        $result = $PluginModel->where('name', $name)->update([
            'config' => json_encode($config),
            'update_time' => time()
        ]);
        return $result !== false;
    }
}
