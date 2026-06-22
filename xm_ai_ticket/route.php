<?php

use think\facade\Route;

// 定时任务（无需鉴权，供外部GET请求触发）
Route::get('ai_ticket_cron', '\\addon\\xm_ai_ticket\\controller\\CronController@index')
    ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'cron', '_action' => 'index']);

// 后台路由
Route::group(DIR_ADMIN . '/v1', function () {

    // 插件配置
    Route::get('ai_ticket_setting', '\\addon\\xm_ai_ticket\\controller\\AdminSettingController@getConfig')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_setting', '_action' => 'get_config']);
    Route::put('ai_ticket_setting', '\\addon\\xm_ai_ticket\\controller\\AdminSettingController@saveConfig')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_setting', '_action' => 'save_config']);
    Route::post('ai_ticket_setting/test_feishu', '\\addon\\xm_ai_ticket\\controller\\AdminSettingController@testFeishu')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_setting', '_action' => 'test_feishu']);

    // AI模型管理
    Route::get('ai_ticket_model', '\\addon\\xm_ai_ticket\\controller\\AdminModelController@modelList')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_model', '_action' => 'model_list']);
    Route::get('ai_ticket_model/:id', '\\addon\\xm_ai_ticket\\controller\\AdminModelController@modelDetail')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_model', '_action' => 'model_detail']);
    Route::post('ai_ticket_model', '\\addon\\xm_ai_ticket\\controller\\AdminModelController@modelCreate')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_model', '_action' => 'model_create']);
    Route::put('ai_ticket_model/:id', '\\addon\\xm_ai_ticket\\controller\\AdminModelController@modelUpdate')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_model', '_action' => 'model_update']);
    Route::delete('ai_ticket_model/:id', '\\addon\\xm_ai_ticket\\controller\\AdminModelController@modelDelete')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_model', '_action' => 'model_delete']);
    Route::post('ai_ticket_model/test', '\\addon\\xm_ai_ticket\\controller\\AdminModelController@modelTest')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_model', '_action' => 'model_test']);

    // 部门AI配置
    Route::get('ai_ticket_department', '\\addon\\xm_ai_ticket\\controller\\AdminDepartmentController@departmentList')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_department', '_action' => 'department_list']);
    Route::post('ai_ticket_department', '\\addon\\xm_ai_ticket\\controller\\AdminDepartmentController@departmentSave')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_department', '_action' => 'department_save']);
    Route::delete('ai_ticket_department/:id', '\\addon\\xm_ai_ticket\\controller\\AdminDepartmentController@departmentDelete')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_department', '_action' => 'department_delete']);

    // 部门商品关联
    Route::get('ai_ticket_product', '\\addon\\xm_ai_ticket\\controller\\AdminProductController@productList')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_product', '_action' => 'product_list']);
    Route::post('ai_ticket_product', '\\addon\\xm_ai_ticket\\controller\\AdminProductController@productSave')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_product', '_action' => 'product_save']);
    Route::delete('ai_ticket_product/:id', '\\addon\\xm_ai_ticket\\controller\\AdminProductController@productDelete')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_product', '_action' => 'product_delete']);

    // AI回复日志
    Route::get('ai_ticket_log', '\\addon\\xm_ai_ticket\\controller\\AdminLogController@logList')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_log', '_action' => 'log_list']);
    Route::delete('ai_ticket_log/:id', '\\addon\\xm_ai_ticket\\controller\\AdminLogController@logDelete')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_log', '_action' => 'log_delete']);

    // 工单AI状态与转接
    Route::get('ai_ticket_status/:id', '\\addon\\xm_ai_ticket\\controller\\AdminTicketController@ticketAiStatus')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_ticket', '_action' => 'ticket_ai_status']);
    Route::post('ai_ticket_transfer/:id', '\\addon\\xm_ai_ticket\\controller\\AdminTicketController@ticketTransfer')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_ticket', '_action' => 'ticket_transfer']);
    Route::post('ai_ticket_reactivate/:id', '\\addon\\xm_ai_ticket\\controller\\AdminTicketController@ticketAiReactivate')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_ticket', '_action' => 'ticket_ai_reactivate']);
    Route::get('ai_ticket_reply_log/:id', '\\addon\\xm_ai_ticket\\controller\\AdminTicketController@ticketAiReplyLog')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_ticket', '_action' => 'ticket_ai_reply_log']);

    // 工单部门列表(供选择用)
    Route::get('ai_ticket_ticket_type', '\\addon\\xm_ai_ticket\\controller\\AdminDepartmentController@ticketTypeList')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_department', '_action' => 'ticket_type_list']);

    // 商品列表(供选择用)
    Route::get('ai_ticket_product_list', '\\addon\\xm_ai_ticket\\controller\\AdminProductController@allProductList')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_product', '_action' => 'all_product_list']);

    // AI工具管理
    Route::get('ai_ticket_tool', '\\addon\\xm_ai_ticket\\controller\\AdminToolController@toolList')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_tool', '_action' => 'tool_list']);
    Route::post('ai_ticket_tool', '\\addon\\xm_ai_ticket\\controller\\AdminToolController@toolCreate')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_tool', '_action' => 'tool_create']);
    Route::put('ai_ticket_tool/:id', '\\addon\\xm_ai_ticket\\controller\\AdminToolController@toolUpdate')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_tool', '_action' => 'tool_update']);
    Route::delete('ai_ticket_tool/:id', '\\addon\\xm_ai_ticket\\controller\\AdminToolController@toolDelete')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_tool', '_action' => 'tool_delete']);
    Route::post('ai_ticket_tool/test', '\\addon\\xm_ai_ticket\\controller\\AdminToolController@toolTest')
        ->append(['_plugin' => 'xm_ai_ticket', '_controller' => 'admin_tool', '_action' => 'tool_test']);

})
->middleware(\app\http\middleware\ParamFilter::class)
->middleware(\app\http\middleware\CheckAdmin::class);
