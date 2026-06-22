# V10-AI: IDC Smart V10 AI 工单自动回复插件

一个基于 IDC Smart V10 框架的 AI 驱动工单自动回复系统，能够智能处理客户工单，提供 24/7 自动客服支持。

## 🚀 功能特性

### 1. 多 AI 供应商支持
- **OpenAI** (GPT-4, GPT-3.5)
- **Anthropic** (Claude)
- **百度** (文心一言)
- **智谱** (ChatGLM)
- **月之暗面** (Kimi)
- **DeepSeek**
- **自定义** OpenAI 兼容 API

### 2. 智能工具调用 (Tool Calling)
- **SQL 工具**: 安全查询数据库（仅 SELECT，自动 LIMIT 20）
- **API 工具**: 调用外部 HTTP 接口
- **服务器监控工具**: 自动检测服务器开通状态，支持自动重试
- 最多支持 3 轮工具调用对话

### 3. 自动工单处理
- 定时扫描待处理工单
- 原子锁定机制防止重复处理
- 智能判断客户消息是否需要回复
- 可配置回复间隔时间

### 4. 客户上下文注入
- 自动获取客户产品信息
- 查询最近订单记录
- 避免询问客户已知信息

### 5. 智能转人工系统
- **AI 主动转人工**: AI 判断需要时自动标记 `[TRANSFER]`
- **关键词触发**: 支持自定义转人工关键词
- **多种通知方式**:
  - 仅关闭 AI
  - 飞书 Webhook 通知
  - 自动回复客户
  - 飞书 + 自动回复
- **转人工后监控**: 客户继续发消息时自动安抚

### 6. 服务器开通监控
- 自动检测服务器开通失败
- 供应商余额不足自动重试（最多 15 次）
- 自动向上游供应商提交工单
- 飞书实时通知
- 开通成功自动回复客户

### 7. 完整的管理后台
- **模型管理**: 配置多个 AI 模型
- **部门配置**: 每个部门独立配置
- **产品绑定**: 关联产品与部门
- **工具管理**: 创建和测试 AI 工具
- **回复日志**: 查看所有 AI 回复记录
- **系统设置**: 全局配置选项

## 📁 项目结构

```
xm_ai_ticket/
├── XmAiTicket.php                    # 插件入口文件
├── config.php                        # 配置文件
├── auth.php                          # 权限定义
├── route.php                         # 路由定义
├── sidebar.php                       # 后台菜单
├── lang/
│   └── zh-cn.php                     # 中文语言包
├── logic/
│   └── AiReplyLogic.php              # 核心 AI 回复逻辑 (~1466行)
├── controller/
│   ├── AdminModelController.php      # 模型管理
│   ├── AdminDepartmentController.php # 部门配置
│   ├── AdminProductController.php    # 产品绑定
│   ├── AdminSettingController.php    # 系统设置
│   ├── AdminTicketController.php     # 工单管理
│   ├── AdminLogController.php        # 日志管理
│   ├── AdminToolController.php       # 工具管理
│   └── CronController.php            # 定时任务
├── model/
│   ├── AiTicketModelModel.php        # AI 模型
│   ├── AiTicketDepartmentModel.php   # 部门配置
│   ├── AiTicketProductModel.php      # 产品绑定
│   ├── AiTicketReplyModel.php        # 回复日志
│   ├── AiTicketStatusModel.php       # 工单状态
│   ├── AiTicketToolModel.php         # AI 工具
│   └── AiTicketHostCreateMonitorModel.php # 服务器监控
└── template/admin/
    ├── *.html                        # 管理页面
    ├── api/ai_ticket.js              # API 接口
    ├── js/*.js                       # 页面逻辑
    ├── lang/zh-cn.js                 # 前端语言包
    └── css/ai_ticket.css             # 样式文件
```

## 🗄️ 数据库表

| 表名 | 说明 |
|------|------|
| `idcsmart_addon_xm_ai_ticket_model` | AI 模型配置 |
| `idcsmart_addon_xm_ai_ticket_department` | 部门 AI 配置 |
| `idcsmart_addon_xm_ai_ticket_product` | 部门产品关联 |
| `idcsmart_addon_xm_ai_ticket_reply` | AI 回复日志 |
| `idcsmart_addon_xm_ai_ticket_status` | 工单 AI 状态 |
| `idcsmart_addon_xm_ai_ticket_tool` | AI 工具定义 |
| `idcsmart_addon_xm_ai_ticket_host_create_monitor` | 服务器开通监控 |

## ⚙️ 配置选项

| 配置项 | 默认值 | 说明 |
|--------|--------|------|
| `enable` | 1 | 全局开关 |
| `default_reply_interval` | 15 | 回复间隔（秒） |
| `default_max_chars` | 255 | 最大回复字符数 |
| `transfer_keywords` | 转人工,人工客服,... | 转人工关键词 |
| `transfer_method` | 1 | 转人工通知方式 |
| `feishu_webhook` | - | 飞书 Webhook URL |
| `context_max_chars` | 5120 | 上下文最大字符数 |
| `host_create_max_retry` | 15 | 服务器开通最大重试次数 |

## 🔧 安装使用

1. 将插件上传到 `public/plugins/addon/` 目录
2. 在 IDC Smart V10 后台启用插件
3. 配置 AI 模型（API Key、模型名称等）
4. 为各部门配置 AI 参数
5. 设置定时任务（每分钟访问 `/ai_ticket_cron`）

## 🔐 安全特性

- SQL 工具仅允许 SELECT 查询
- 自动过滤危险 SQL 关键字
- API 工具支持模板变量替换
- 工具调用结果自动截断（2000字符）
- 原子操作防止并发问题

## 📊 监控与日志

- 完整的 AI 回复审计日志
- Token 使用量统计
- 服务器开通状态监控
- 飞书实时告警通知

## 🛠️ 技术栈

- **后端**: PHP 7.4+, ThinkPHP 6.x
- **前端**: Vue.js 2, TDesign, Axios
- **数据库**: MySQL 5.7+
- **AI**: OpenAI API 兼容接口

## 📝 更新日志

### v1.0.0 (2026-06-03)
- 初始版本发布
- 支持 6+ AI 供应商
- 实现 Tool Calling 功能
- 完整的管理后台
- 飞书通知集成
- 服务器开通监控

## 👨‍💻 作者

**小梦** - [GitHub](https://github.com/menghuan1022)

## 📄 许可证

MIT License

---

> 💡 **提示**: 本插件需要 IDC Smart V10 框架支持，请确保您的系统版本兼容。
