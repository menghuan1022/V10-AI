/**
 * AI模型管理页面逻辑
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
          filterProvider: '',
          filterStatus: '',
          params: {
            page: 1,
            limit: 20,
          },
          columns: [
            { colKey: 'id', title: 'ID', width: 60 },
            { colKey: 'name', title: this.lang.model_name, ellipsis: true },
            { colKey: 'provider', title: this.lang.provider, cell: 'provider', width: 150 },
            { colKey: 'model', title: this.lang.model_id, ellipsis: true },
            { colKey: 'max_tokens', title: this.lang.max_tokens, width: 100 },
            { colKey: 'supports_tool_call', title: this.lang.supports_tool_call, cell: 'supports_tool_call', width: 110 },
            { colKey: 'is_default', title: this.lang.is_default, cell: 'is_default', width: 80 },
            { colKey: 'status', title: this.lang.status, cell: 'status', width: 80 },
            { colKey: 'op', title: this.lang.edit, cell: 'op', width: 180 },
          ],
          providerOptions: [
            { label: this.lang.provider_openai, value: 'openai' },
            { label: this.lang.provider_anthropic, value: 'anthropic' },
            { label: this.lang.provider_baidu, value: 'baidu' },
            { label: this.lang.provider_zhipu, value: 'zhipu' },
            { label: this.lang.provider_moonshot, value: 'moonshot' },
            { label: this.lang.provider_deepseek, value: 'deepseek' },
            { label: this.lang.provider_custom, value: 'custom' },
          ],
          statusOptions: [
            { label: this.lang.enabled, value: 1 },
            { label: this.lang.disabled, value: 0 },
          ],
          addVisible: false,
          isEdit: false,
          dialogTitle: this.lang.add_model,
          formData: {
            name: '',
            provider: 'openai',
            api_url: '',
            api_key: '',
            model: '',
            max_tokens: 256,
            is_default: 0,
            status: 1,
            supports_tool_call: 0,
          },
          formRules: {
            name: [{ required: true, message: this.lang.input_model_name }],
            api_url: [{ required: true, message: this.lang.input_api_url }],
            api_key: [{ required: true, message: this.lang.input_api_key }],
            model: [{ required: true, message: this.lang.input_model_id }],
          },
          editId: 0,
          testVisible: false,
          testLoading: false,
          testSuccess: false,
          testResponse: '',
          cronVisible: false,
          cronUrl: location.origin + '/ai_ticket_cron',
          cronShell: '* * * * * curl -s ' + location.origin + '/ai_ticket_cron',
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
          if (_this.filterProvider !== '') params.provider = _this.filterProvider;
          if (_this.filterStatus !== '') params.status = _this.filterStatus;
          ai_ticket_api.getModelList(params).then(function (res) {
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
        getProviderLabel: function (provider) {
          var map = {};
          this.providerOptions.forEach(function (item) {
            map[item.value] = item.label;
          });
          return map[provider] || provider;
        },
        showAddDialog: function () {
          this.isEdit = false;
          this.dialogTitle = this.lang.add_model;
          this.formData = {
            name: '',
            provider: 'openai',
            api_url: '',
            api_key: '',
            model: '',
            max_tokens: 256,
            is_default: 0,
            status: 1,
            supports_tool_call: 0,
          };
          this.editId = 0;
          this.addVisible = true;
        },
        editModel: function (row) {
          this.isEdit = true;
          this.dialogTitle = this.lang.edit;
          this.editId = row.id;
          this.formData = {
            name: row.name,
            provider: row.provider,
            api_url: row.api_url,
            api_key: row.api_key,
            model: row.model,
            max_tokens: row.max_tokens,
            is_default: row.is_default,
            status: row.status,
            supports_tool_call: row.supports_tool_call || 0,
          };
          this.addVisible = true;
        },
        closeAddDialog: function () {
          this.addVisible = false;
        },
        submitModel: function (validateResult) {
          var _this = this;
          if (validateResult.validateResult !== true) return;
          var data = Object.assign({}, _this.formData);
          if (_this.isEdit) {
            data.id = _this.editId;
            ai_ticket_api.updateModel(_this.editId, data).then(function (res) {
              if (res.data.status === 200) {
                _this.$message.success(_this.lang.success);
                _this.addVisible = false;
                _this.getList();
              } else {
                _this.$message.error(res.data.msg || _this.lang.fail);
              }
            });
          } else {
            ai_ticket_api.createModel(data).then(function (res) {
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
        deleteModel: function (row) {
          var _this = this;
          _this.$confirm(_this.lang.delete + '?', function () {
            ai_ticket_api.deleteModel(row.id).then(function (res) {
              if (res.data.status === 200) {
                _this.$message.success(_this.lang.success);
                _this.getList();
              } else {
                _this.$message.error(res.data.msg || _this.lang.fail);
              }
            });
          });
        },
        testModel: function (row) {
          var _this = this;
          _this.testVisible = true;
          _this.testLoading = true;
          _this.testSuccess = false;
          _this.testResponse = '';
          ai_ticket_api.testModel({
            provider: row.provider,
            api_url: row.api_url,
            api_key: row.api_key,
            model: row.model,
            max_tokens: row.max_tokens,
          }).then(function (res) {
            _this.testLoading = false;
            if (res.data.status === 200) {
              _this.testSuccess = true;
              _this.testResponse = res.data.data.response || _this.lang.test_success;
            } else {
              _this.testSuccess = false;
              _this.testResponse = res.data.msg || _this.lang.test_fail;
            }
          }).catch(function (err) {
            _this.testLoading = false;
            _this.testSuccess = false;
            _this.testResponse = (err && err.message) || _this.lang.test_fail;
          });
        },
        closeTestDialog: function () {
          this.testVisible = false;
        },
        showCronDialog: function () {
          this.cronUrl = location.origin + '/ai_ticket_cron';
          this.cronShell = '* * * * * curl -s ' + location.origin + '/ai_ticket_cron';
          this.cronVisible = true;
        },
        copyCronUrl: function () {
          var _this = this;
          if (navigator.clipboard) {
            navigator.clipboard.writeText(_this.cronUrl).then(function () {
              _this.$message.success(_this.lang.copy_success || '已复制');
            });
          } else {
            var input = document.createElement('input');
            input.value = _this.cronUrl;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            _this.$message.success(_this.lang.copy_success || '已复制');
          }
        },
        copyCronShell: function () {
          var _this = this;
          var text = _this.cronShell;
          if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
              _this.$message.success(_this.lang.copy_success || '已复制');
            });
          } else {
            var input = document.createElement('input');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            _this.$message.success(_this.lang.copy_success || '已复制');
          }
        },
      },
    }).$mount(template);

    typeof old_onload == "function" && old_onload;
  };
})(window);