/**
 * 部门AI配置页面逻辑
 */
(function (window, undefined) {
  var old_onload = window.onload;
  window.onload = function () {
    var template = document.getElementsByClassName("template")[0];
    Vue.prototype.lang = Object.assign(window.lang, window.plugin_lang);

    new Vue({
      components: { comConfig },
      data: function () {
        return {
          list: [],
          loading: false,
          columns: [
            { colKey: 'id', title: 'ID', width: 60 },
            { colKey: 'type_name', title: this.lang.ticket_type, ellipsis: true },
            { colKey: 'model_name', title: this.lang.ai_model, ellipsis: true },
            { colKey: 'auto_reply', title: this.lang.auto_reply, cell: 'auto_reply', width: 100 },
            { colKey: 'reply_interval', title: this.lang.reply_interval, cell: 'reply_interval', width: 100 },
            { colKey: 'max_reply_chars', title: this.lang.max_reply_chars, cell: 'max_reply_chars', width: 110 },
            { colKey: 'persona', title: this.lang.persona, cell: 'persona', ellipsis: true },
            { colKey: 'op', title: this.lang.edit, cell: 'op', width: 140 },
          ],
          addVisible: false,
          isEdit: false,
          dialogTitle: this.lang.add_department_config,
          formData: {
            ticket_type_id: '',
            ai_model_id: '',
            auto_reply: 1,
            reply_interval: 60,
            max_reply_chars: 255,
            persona: '',
            closing_remark: '',
          },
          formRules: {
            ticket_type_id: [{ required: true, message: this.lang.ticket_type }],
            ai_model_id: [{ required: true, message: this.lang.ai_model }],
          },
          typeOptions: [],
          modelOptions: [],
          autoReplyOptions: [
            { label: this.lang.enabled, value: 1 },
            { label: this.lang.disabled, value: 0 },
          ],
          editId: 0,
        };
      },
      created: function () {
        this.getList();
        this.loadTypeOptions();
        this.loadModelOptions();
      },
      methods: {
        getList: function () {
          var _this = this;
          _this.loading = true;
          ai_ticket_api.getDepartmentList().then(function (res) {
            _this.list = res.data.data.list || [];
            _this.loading = false;
          }).catch(function () {
            _this.loading = false;
          });
        },
        loadTypeOptions: function () {
          var _this = this;
          ai_ticket_api.getTicketTypeList().then(function (res) {
            _this.typeOptions = res.data.data.list || [];
          });
        },
        loadModelOptions: function () {
          var _this = this;
          ai_ticket_api.getModelList({ status: 1, limit: 100 }).then(function (res) {
            _this.modelOptions = res.data.data.list || [];
          });
        },
        showAddDialog: function () {
          this.isEdit = false;
          this.dialogTitle = this.lang.add_department_config;
          this.formData = {
            ticket_type_id: '',
            ai_model_id: '',
            auto_reply: 1,
            reply_interval: 60,
            max_reply_chars: 255,
            persona: '',
            closing_remark: '',
          };
          this.editId = 0;
          this.addVisible = true;
        },
        editRow: function (row) {
          this.isEdit = true;
          this.dialogTitle = this.lang.edit_department;
          this.editId = row.id;
          this.formData = {
            ticket_type_id: row.ticket_type_id,
            ai_model_id: row.ai_model_id,
            auto_reply: row.auto_reply,
            reply_interval: row.reply_interval,
            max_reply_chars: row.max_reply_chars,
            persona: row.persona || '',
            closing_remark: row.closing_remark || '',
          };
          this.addVisible = true;
        },
        closeAddDialog: function () {
          this.addVisible = false;
        },
        submitRow: function (validateResult) {
          var _this = this;
          if (validateResult.validateResult !== true) return;
          var data = Object.assign({}, _this.formData);
          ai_ticket_api.saveDepartment(data).then(function (res) {
            if (res.data.status === 200) {
              _this.$message.success(_this.lang.success);
              _this.addVisible = false;
              _this.getList();
            } else {
              _this.$message.error(res.data.msg || _this.lang.fail);
            }
          });
        },
        deleteRow: function (row) {
          var _this = this;
          _this.$confirm(_this.lang.delete + '?', function () {
            ai_ticket_api.deleteDepartment(row.id).then(function (res) {
              if (res.data.status === 200) {
                _this.$message.success(_this.lang.success);
                _this.getList();
              } else {
                _this.$message.error(res.data.msg || _this.lang.fail);
              }
            });
          });
        },
      },
    }).$mount(template);

    typeof old_onload == "function" && old_onload;
  };
})(window);