/**
 * AI工具管理页面逻辑
 */
(function (window, undefined) {
  var old_onload = window.onload;
  window.onload = function () {
    var template = document.getElementsByClassName("template")[0];
    Vue.prototype.lang = Object.assign(window.lang, window.plugin_lang);

    new Vue({
      components: { comConfig, comPagination },
      data: function () {
        return {
          list: [],
          count: 0,
          loading: false,
          filterType: '',
          filterStatus: '',
          params: {
            page: 1,
            limit: 20,
          },
          columns: [
            { colKey: 'id', title: 'ID', width: 60 },
            { colKey: 'name', title: this.lang.tool_name, ellipsis: true },
            { colKey: 'description', title: this.lang.tool_description, ellipsis: true },
            { colKey: 'type', title: this.lang.tool_type, cell: 'type', width: 120 },
            { colKey: 'status', title: this.lang.tool_status, cell: 'status', width: 80 },
            { colKey: 'op', title: this.lang.edit, cell: 'op', width: 180 },
          ],
          typeOptions: [
            { label: this.lang.tool_type_sql, value: 'sql' },
            { label: this.lang.tool_type_api, value: 'api' },
          ],
          statusOptions: [
            { label: this.lang.enabled, value: 1 },
            { label: this.lang.disabled, value: 0 },
          ],
          methodOptions: [
            { label: 'GET', value: 'GET' },
            { label: 'POST', value: 'POST' },
          ],
          paramOptions: [
            { label: 'client_id', value: 'client_id' },
            { label: 'ticket_id', value: 'ticket_id' },
            { label: 'host_id', value: 'host_id' },
            { label: 'product_id', value: 'product_id' },
          ],
          addVisible: false,
          isEdit: false,
          dialogTitle: this.lang.add_tool,
          formData: {
            name: '',
            description: '',
            type: 'sql',
            sql_query: '',
            sql_params: [],
            api_url: '',
            api_method: 'GET',
            api_headers: '',
            api_body: '',
            api_params: [],
            status: 1,
          },
          formRules: {
            name: [{ required: true, message: this.lang.input_tool_name }],
            description: [{ required: true, message: this.lang.input_tool_description }],
          },
          editId: 0,
          testVisible: false,
          testLoading: false,
          testSuccess: false,
          testResult: null,
          testToolId: 0,
          testParams: {
            client_id: 1,
            ticket_id: 1,
          },
        };
      },
      created: function () {
        this.doSearch();
      },
      methods: {
        doSearch: function () {
          this.params.page = 1;
          this.getList();
        },
        getList: function () {
          var _this = this;
          _this.loading = true;
          var params = Object.assign({}, _this.params);
          if (_this.filterType !== '') params.type = _this.filterType;
          if (_this.filterStatus !== '') params.status = _this.filterStatus;
          ai_ticket_api.getToolList(params).then(function (res) {
            _this.list = res.data.data.list || [];
            _this.count = res.data.data.count || 0;
            _this.loading = false;
          }).catch(function () {
            _this.loading = false;
          });
        },
        changePage: function (params) {
          this.params.page = params.page;
          this.params.limit = params.limit;
          this.getList();
        },
        showAddDialog: function () {
          this.isEdit = false;
          this.dialogTitle = this.lang.add_tool;
          this.formData = {
            name: '',
            description: '',
            type: 'sql',
            sql_query: '',
            sql_params: [],
            api_url: '',
            api_method: 'GET',
            api_headers: '',
            api_body: '',
            api_params: [],
            status: 1,
          };
          this.editId = 0;
          this.addVisible = true;
        },
        editTool: function (row) {
          this.isEdit = true;
          this.dialogTitle = this.lang.edit;
          this.editId = row.id;

          // 解析config
          var config = {};
          try {
            config = JSON.parse(row.config || '{}');
          } catch (e) {
            config = {};
          }

          this.formData = {
            name: row.name,
            description: row.description,
            type: row.type,
            sql_query: config.query || '',
            sql_params: config.params || [],
            api_url: config.url || '',
            api_method: config.method || 'GET',
            api_headers: config.headers ? JSON.stringify(config.headers) : '',
            api_body: config.body || '',
            api_params: config.params || [],
            status: row.status,
          };
          this.addVisible = true;
        },
        closeAddDialog: function () {
          this.addVisible = false;
        },
        buildConfig: function () {
          var _this = this;
          if (_this.formData.type === 'sql') {
            return JSON.stringify({
              query: _this.formData.sql_query,
              params: _this.formData.sql_params,
            });
          } else {
            var headers = {};
            if (_this.formData.api_headers) {
              try {
                headers = JSON.parse(_this.formData.api_headers);
              } catch (e) {
                headers = {};
              }
            }
            return JSON.stringify({
              url: _this.formData.api_url,
              method: _this.formData.api_method,
              headers: headers,
              body: _this.formData.api_body,
              params: _this.formData.api_params,
            });
          }
        },
        submitTool: function (validateResult) {
          var _this = this;
          if (validateResult.validateResult !== true) return;
          var data = {
            name: _this.formData.name,
            description: _this.formData.description,
            type: _this.formData.type,
            config: _this.buildConfig(),
            status: _this.formData.status,
          };
          if (_this.isEdit) {
            data.id = _this.editId;
            ai_ticket_api.updateTool(_this.editId, data).then(function (res) {
              if (res.data.status === 200) {
                _this.$message.success(_this.lang.success);
                _this.addVisible = false;
                _this.getList();
              } else {
                _this.$message.error(res.data.msg || _this.lang.fail);
              }
            });
          } else {
            ai_ticket_api.createTool(data).then(function (res) {
              if (res.data.status === 200) {
                _this.$message.success(_this.lang.success);
                _this.addVisible = false;
                _this.getList();
              } else {
                _this.$message.error(res.data.msg || _this.lang.fail);
              }
            });
          }
        },
        deleteTool: function (row) {
          var _this = this;
          _this.$confirm(_this.lang.delete + '?', function () {
            ai_ticket_api.deleteTool(row.id).then(function (res) {
              if (res.data.status === 200) {
                _this.$message.success(_this.lang.success);
                _this.getList();
              } else {
                _this.$message.error(res.data.msg || _this.lang.fail);
              }
            });
          });
        },
        testTool: function (row) {
          this.testToolId = row.id;
          this.testResult = null;
          this.testSuccess = false;
          this.testVisible = true;
        },
        runTest: function () {
          var _this = this;
          _this.testLoading = true;
          _this.testResult = null;
          ai_ticket_api.testTool({
            id: _this.testToolId,
            test_params: _this.testParams,
          }).then(function (res) {
            _this.testLoading = false;
            if (res.data.status === 200) {
              var data = res.data.data || {};
              _this.testSuccess = data.success || false;
              _this.testResult = data.result || '';
            } else {
              _this.testSuccess = false;
              _this.testResult = res.data.msg || _this.lang.tool_test_fail;
            }
          }).catch(function (err) {
            _this.testLoading = false;
            _this.testSuccess = false;
            _this.testResult = (err && err.message) || _this.lang.tool_test_fail;
          });
        },
      },
    }).$mount(template);

    typeof old_onload == "function" && old_onload;
  };
})(window);
