// AI工单客服 - 中文语言文件
(function() {
  const plugin_lang = {
    // 通用
    submit: '提交',
    edit: '编辑',
    delete: '删除',
    yes: '是',
    no: '否',
    enabled: '启用',
    disabled: '禁用',
    second: '秒',
    char: '字符',
    success: '操作成功',
    fail: '操作失败',
    cancel: '取消',

    // 导航
    ai_model: 'AI模型管理',
    department_config: '部门AI配置',
    product_bind: '部门商品关联',

    // AI模型
    add_model: '添加模型',
    model_name: '模型名称',
    provider: '提供商',
    api_url: 'API地址',
    api_key: 'API密钥',
    model_id: '模型标识',
    max_tokens: '最大输出Token',
    is_default: '是否默认',
    status: '状态',
    input_model_name: '请输入模型名称，如：GPT-4',
    input_api_url: '请输入API地址，如：https://api.openai.com/v1',
    input_api_key: '请输入API密钥',
    input_model_id: '请输入模型标识，如：gpt-4o-mini',
    filter_provider: '筛选提供商',
    filter_status: '筛选状态',
    test: '测试',
    test_result: '测试结果',
    testing: '正在测试连接，请稍候...',
    test_success: '连接成功',
    test_fail: '连接失败',

    // 提供商
    provider_openai: 'OpenAI',
    provider_anthropic: 'Anthropic(Claude)',
    provider_baidu: '百度(文心)',
    provider_zhipu: '智谱(ChatGLM)',
    provider_moonshot: 'Moonshot(Kimi)',
    provider_deepseek: 'DeepSeek',
    provider_custom: '自定义(兼容OpenAI协议)',

    // 部门配置
    add_department_config: '添加部门配置',
    ticket_type: '工单部门',
    auto_reply: '自动回复',
    reply_interval: '回复间隔',
    max_reply_chars: '最大回复字符数',
    persona: '人设设定',
    closing_remark: '结束语',
    persona_placeholder: '请输入人设设定，如：你是一个公司客服，尽量模仿人的语气，根据用户问题给出最合适的答案，回答字数不要超过150个字符。',
    closing_remark_placeholder: '请输入结束语，如：如果不能解决您的问题，您可以回复请求更高级的技术支持。',
    default_persona: '默认人设',
    edit_department: '编辑部门配置',

    // 商品关联
    add_product_bind: '添加商品关联',
    product: '商品',
    select_product: '请选择商品',
    filter_type: '筛选部门',

    // 首页
    please_config: '请点击上方标签页配置AI模型和部门',

    // 日志
    ai_log: 'AI回复日志',
    log_id: 'ID',
    ticket_id: '工单ID',
    ticket_title: '工单标题',
    model_name_col: 'AI模型',
    reply_content: '回复内容',
    tokens_used: '消耗Token',
    reply_time: '回复时间',
    context: '发送上下文',
    raw_response: 'AI原始响应',
    view_detail: '查看详情',
    clear_log: '清空日志',
    no_log: '暂无日志',

    // 定时任务
    cron_config: '定时任务配置',
    cron_url_tip: '请将以下URL添加到服务器定时任务中，每分钟GET请求一次：',
    cron_shell_tip: 'Crontab命令（可直接复制到服务器crontab）：',
    cron_help_1: '1. Linux服务器：执行 crontab -e，粘贴上方命令保存即可',
    cron_help_2: '2. Windows服务器：在任务计划程序中创建基本任务，每分钟触发，操作为启动程序 curl.exe 并加上上方URL',
    copy: '复制',
    copy_success: '已复制',

    // 插件设置
    plugin_setting: '插件设置',
    enable_ai: '启用AI客服',
    enable_on: '启用',
    enable_off: '关闭',
    submit_success: '保存成功',
    submit_fail: '保存失败',

    // 转人工通知
    transfer_method: '转交高级技术人员通知方式',
    transfer_method_none: '仅停用AI',
    transfer_method_feishu: '飞书通知',
    transfer_method_auto_reply: '自动回复',
    transfer_method_all: '飞书通知+自动回复',
    feishu_webhook: '飞书自定义机器人Webhook',
    feishu_webhook_placeholder: '请输入飞书自定义机器人Webhook地址',
    transfer_auto_reply: '转交高级技术人员自动回复内容',
    transfer_auto_reply_placeholder: '请输入转交高级技术人员时自动回复给客户的内容',
    test_feishu: '测试飞书',
    feishu_test_success: '飞书通知发送成功',
    feishu_test_fail: '飞书通知发送失败',
    default_reply_interval: '默认回复间隔(秒)',
    default_max_chars: '默认最大回复字符数',
    default_persona: '默认人设',
    persona_placeholder: '请输入人设设定，如：你是一个公司客服，尽量模仿人的语气，根据用户问题给出最合适的答案，回答字数不要超过150个字符。',
    transfer_keywords: '转人工关键词',
    context_max_chars: '上下文最大字符数',
    cron_config: '定时任务配置',

    // 客户信息注入
    client_context_title: '客户信息注入',
    enable_client_context: '启用客户信息注入',
    client_context_hosts_limit: '最大展示产品数',
    client_context_orders_limit: '最大展示订单数',

    // AI工具管理
    ai_tool: 'AI工具管理',
    add_tool: '添加工具',
    tool_name: '工具名称',
    tool_description: '工具描述',
    tool_type: '工具类型',
    tool_type_sql: '数据库查询',
    tool_type_api: 'HTTP接口',
    tool_config: '工具配置',
    tool_status: '状态',
    tool_test: '测试',
    tool_test_result: '测试结果',
    tool_test_params: '测试参数',
    tool_test_client_id: '客户ID',
    tool_test_ticket_id: '工单ID',
    input_tool_name: '请输入工具名称(英文，如get_client_products)',
    input_tool_description: '请输入工具描述(AI根据此描述决定是否调用)',
    tool_config_sql_query: 'SQL查询语句',
    tool_config_sql_params: '模板参数',
    tool_config_api_url: 'API地址',
    tool_config_api_method: '请求方法',
    tool_config_api_headers: '请求头(JSON)',
    tool_config_api_body: '请求体',
    tool_config_tip: 'SQL工具：使用{client_id}、{ticket_id}等作为模板变量，执行时自动替换',
    tool_config_api_tip: 'API工具：URL和Body中可使用{client_id}、{ticket_id}等模板变量',
    tool_preset: '预置工具',
    tool_preset_tip: '以下为系统预置工具，可修改配置但建议不要删除',
    tool_test_success: '工具测试成功',
    tool_test_fail: '工具测试失败',
    tool_test_running: '正在测试工具执行...',
    tool_name_exists: '工具名称已存在',
    tool_name_format: '工具名称只能包含英文字母、数字和下划线',
    tool_sql_select_only: '只允许SELECT查询',
    tool_config_invalid: '配置JSON格式无效',

    // 模型-工具调用
    supports_tool_call: '支持工具调用',
    supports_tool_call_tip: '开启后AI可按需调用工具查询客户信息（需模型支持Function Calling/Tool Use功能）',
    yes: '是',
    no: '否',
  };

  if (typeof window.lang !== 'undefined') {
    Object.assign(window.lang, plugin_lang);
  }
  window.plugin_lang = plugin_lang;
})();