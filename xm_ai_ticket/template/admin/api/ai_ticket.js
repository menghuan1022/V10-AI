/**
 * AI工单客服 - API封装
 */
var ai_ticket_api = {
  // AI模型管理
  getModelList: function (params) {
    return Axios.get('/ai_ticket_model', { params: params });
  },
  getModelDetail: function (id) {
    return Axios.get('/ai_ticket_model/' + id);
  },
  createModel: function (data) {
    return Axios.post('/ai_ticket_model', data);
  },
  updateModel: function (id, data) {
    return Axios.put('/ai_ticket_model/' + id, data);
  },
  deleteModel: function (id) {
    return Axios.delete('/ai_ticket_model/' + id);
  },
  testModel: function (data) {
    return Axios.post('/ai_ticket_model/test', data);
  },

  // 部门AI配置
  getDepartmentList: function (params) {
    return Axios.get('/ai_ticket_department', { params: params });
  },
  saveDepartment: function (data) {
    return Axios.post('/ai_ticket_department', data);
  },
  deleteDepartment: function (id) {
    return Axios.delete('/ai_ticket_department/' + id);
  },
  getTicketTypeList: function () {
    return Axios.get('/ai_ticket_ticket_type');
  },

  // 部门商品关联
  getProductBindList: function (params) {
    return Axios.get('/ai_ticket_product', { params: params });
  },
  saveProductBind: function (data) {
    return Axios.post('/ai_ticket_product', data);
  },
  deleteProductBind: function (id) {
    return Axios.delete('/ai_ticket_product/' + id);
  },
  getAllProductList: function () {
    return Axios.get('/ai_ticket_product_list');
  },

  // AI回复日志
  getLogList: function (params) {
    return Axios.get('/ai_ticket_log', { params: params });
  },
  deleteLog: function (id) {
    return Axios.delete('/ai_ticket_log/' + id);
  },

  // 插件配置
  getPluginConfig: function () {
    return Axios.get('/ai_ticket_setting');
  },
  savePluginConfig: function (data) {
    return Axios.put('/ai_ticket_setting', data);
  },
  testFeishuWebhook: function (data) {
    return Axios.post('/ai_ticket_setting/test_feishu', data);
  },

  // AI工具管理
  getToolList: function (params) {
    return Axios.get('/ai_ticket_tool', { params: params });
  },
  createTool: function (data) {
    return Axios.post('/ai_ticket_tool', data);
  },
  updateTool: function (id, data) {
    return Axios.put('/ai_ticket_tool/' + id, data);
  },
  deleteTool: function (id) {
    return Axios.delete('/ai_ticket_tool/' + id);
  },
  testTool: function (data) {
    return Axios.post('/ai_ticket_tool/test', data);
  },
};