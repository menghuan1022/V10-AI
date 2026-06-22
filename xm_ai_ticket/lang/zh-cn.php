<?php

return [
    'success_message' => '操作成功',
    'fail_message' => '操作失败',

    // AI模型管理
    'xm_ai_ticket_model_not_exist' => 'AI模型不存在',
    'xm_ai_ticket_model_used_by_department' => '该模型正在被部门使用，无法删除',
    'xm_ai_ticket_param_required' => '必填参数不能为空',

    // 部门配置
    'xm_ai_ticket_department_not_exist' => '部门AI配置不存在',

    // 商品关联
    'xm_ai_ticket_product_not_exist' => '商品关联不存在',

    // 工单状态
    'xm_ai_ticket_status_not_exist' => '工单AI状态不存在',

    // 测试
    'xm_ai_ticket_test_success' => '连接测试成功',
    'xm_ai_ticket_test_fail' => '连接测试失败',
    'xm_ai_ticket_feishu_test_success' => '飞书通知发送成功',
    'xm_ai_ticket_feishu_test_fail' => '飞书通知发送失败',
    'xm_ai_ticket_feishu_webhook_required' => '请先填写飞书Webhook地址',

    // 导航
    'nav_plugin_addon_xm_ai_ticket' => 'AI工单客服',
    'nav_plugin_addon_xm_ai_ticket_model' => 'AI模型管理',
    'nav_plugin_addon_xm_ai_ticket_department' => '部门AI配置',
    'nav_plugin_addon_xm_ai_ticket_product' => '部门商品关联',
    'nav_plugin_addon_xm_ai_ticket_log' => 'AI回复日志',
    'nav_plugin_addon_xm_ai_ticket_tool' => 'AI工具管理',
    'nav_plugin_addon_xm_ai_ticket_setting' => '插件设置',

    // 权限
    'auth_user_xm_ai_ticket' => 'AI工单客服',
    'auth_user_xm_ai_ticket_model' => 'AI模型管理',
    'auth_user_xm_ai_ticket_department' => '部门AI配置',
    'auth_user_xm_ai_ticket_product' => '部门商品关联',
    'auth_user_xm_ai_ticket_status' => '工单AI状态',
    'auth_user_xm_ai_ticket_log' => 'AI回复日志',
    'auth_user_xm_ai_ticket_tool' => 'AI工具管理',
    'auth_user_xm_ai_ticket_setting' => '插件设置',

    // 提供商
    'xm_ai_ticket_provider_openai' => 'OpenAI',
    'xm_ai_ticket_provider_anthropic' => 'Anthropic(Claude)',
    'xm_ai_ticket_provider_baidu' => '百度(文心)',
    'xm_ai_ticket_provider_zhipu' => '智谱(ChatGLM)',
    'xm_ai_ticket_provider_moonshot' => 'Moonshot(Kimi)',
    'xm_ai_ticket_provider_deepseek' => 'DeepSeek',
    'xm_ai_ticket_provider_custom' => '自定义(兼容OpenAI协议)',

    // AI工具
    'xm_ai_ticket_tool_not_exist' => '工具不存在',
    'xm_ai_ticket_tool_name_exists' => '工具名称已存在',
    'xm_ai_ticket_tool_name_format' => '工具名称只能包含英文字母、数字和下划线，且必须以字母开头',
    'xm_ai_ticket_tool_config_invalid' => '工具配置JSON格式无效',
    'xm_ai_ticket_tool_sql_required' => 'SQL工具必须提供query查询语句',
    'xm_ai_ticket_tool_sql_select_only' => 'SQL工具只允许SELECT查询，禁止INSERT/UPDATE/DELETE等操作',
];