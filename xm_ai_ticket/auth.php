<?php

return [
    [
        'title' => 'auth_user_xm_ai_ticket',
        'url'   => '',
        'auth_rule' => [],
        'child' => [
            [
                'title' => 'auth_user_xm_ai_ticket_model',
                'url'   => 'ai_ticket_model',
                'auth_rule' => [
                    'addon\\xm_ai_ticket\\controller\\AdminModelController::modelList',
                    'addon\\xm_ai_ticket\\controller\\AdminModelController::modelDetail',
                    'addon\\xm_ai_ticket\\controller\\AdminModelController::modelCreate',
                    'addon\\xm_ai_ticket\\controller\\AdminModelController::modelUpdate',
                    'addon\\xm_ai_ticket\\controller\\AdminModelController::modelDelete',
                    'addon\\xm_ai_ticket\\controller\\AdminModelController::modelTest',
                ],
                'child' => [],
            ],
            [
                'title' => 'auth_user_xm_ai_ticket_department',
                'url'   => 'ai_ticket_department',
                'auth_rule' => [
                    'addon\\xm_ai_ticket\\controller\\AdminDepartmentController::departmentList',
                    'addon\\xm_ai_ticket\\controller\\AdminDepartmentController::departmentSave',
                    'addon\\xm_ai_ticket\\controller\\AdminDepartmentController::departmentDelete',
                ],
                'child' => [],
            ],
            [
                'title' => 'auth_user_xm_ai_ticket_product',
                'url'   => 'ai_ticket_product',
                'auth_rule' => [
                    'addon\\xm_ai_ticket\\controller\\AdminProductController::productList',
                    'addon\\xm_ai_ticket\\controller\\AdminProductController::productSave',
                    'addon\\xm_ai_ticket\\controller\\AdminProductController::productDelete',
                ],
                'child' => [],
            ],
            [
                'title' => 'auth_user_xm_ai_ticket_status',
                'url'   => 'ai_ticket_status',
                'auth_rule' => [
                    'addon\\xm_ai_ticket\\controller\\AdminTicketController::ticketAiStatus',
                    'addon\\xm_ai_ticket\\controller\\AdminTicketController::ticketTransfer',
                    'addon\\xm_ai_ticket\\controller\\AdminTicketController::ticketAiReactivate',
                    'addon\\xm_ai_ticket\\controller\\AdminTicketController::ticketAiReplyLog',
                ],
                'child' => [],
            ],
            [
                'title' => 'auth_user_xm_ai_ticket_log',
                'url'   => 'ai_ticket_log',
                'auth_rule' => [
                    'addon\\xm_ai_ticket\\controller\\AdminLogController::logList',
                    'addon\\xm_ai_ticket\\controller\\AdminLogController::logDelete',
                ],
                'child' => [],
            ],
            [
                'title' => 'auth_user_xm_ai_ticket_setting',
                'url'   => 'ai_ticket_setting',
                'auth_rule' => [
                    'addon\\xm_ai_ticket\\controller\\AdminSettingController::getConfig',
                    'addon\\xm_ai_ticket\\controller\\AdminSettingController::saveConfig',
                    'addon\\xm_ai_ticket\\controller\\AdminSettingController::testFeishu',
                ],
                'child' => [],
            ],
            [
                'title' => 'auth_user_xm_ai_ticket_tool',
                'url'   => 'ai_ticket_tool',
                'auth_rule' => [
                    'addon\\xm_ai_ticket\\controller\\AdminToolController::toolList',
                    'addon\\xm_ai_ticket\\controller\\AdminToolController::toolCreate',
                    'addon\\xm_ai_ticket\\controller\\AdminToolController::toolUpdate',
                    'addon\\xm_ai_ticket\\controller\\AdminToolController::toolDelete',
                    'addon\\xm_ai_ticket\\controller\\AdminToolController::toolTest',
                ],
                'child' => [],
            ],
        ],
    ],
];
