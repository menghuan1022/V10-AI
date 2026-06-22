/**
 * 部门商品关联页面逻辑
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
          filterTypeId: '',
          params: {
            page: 1,
            limit: 20,
          },
          columns: [
            { colKey: 'id', title: 'ID', width: 60 },
            { colKey: 'type_name', title: this.lang.ticket_type, ellipsis: true },
            { colKey: 'product_name', title: this.lang.product, ellipsis: true },
            { colKey: 'op', title: this.lang.delete, cell: 'op', width: 100 },
          ],
          addVisible: false,
          formData: {
            ticket_type_id: '',
            product_ids: [],
          },
          formRules: {
            ticket_type_id: [{ required: true, message: this.lang.ticket_type }],
            product_ids: [{ required: true, message: this.lang.select_product }],
          },
          typeOptions: [],
          productOptions: [],
        };
      },
      created: function () {
        this.getList();
        this.loadTypeOptions();
        this.loadProductOptions();
      },
      methods: {
        getList: function () {
          var _this = this;
          _this.loading = true;
          var params = Object.assign({}, _this.params);
          if (_this.filterTypeId) params.ticket_type_id = _this.filterTypeId;
          ai_ticket_api.getProductBindList(params).then(function (res) {
            _this.list = res.data.data.list || [];
            _this.count = res.data.data.count || 0;
            _this.loading = false;
          }).catch(function () {
            _this.loading = false;
          });
        },
        doSearch: function () {
          this.params.page = 1;
          this.getList();
        },
        changePage: function (params) {
          this.params.page = params.page;
          this.params.limit = params.limit;
          this.getList();
        },
        loadTypeOptions: function () {
          var _this = this;
          ai_ticket_api.getTicketTypeList().then(function (res) {
            _this.typeOptions = res.data.data.list || [];
          });
        },
        loadProductOptions: function () {
          var _this = this;
          ai_ticket_api.getAllProductList().then(function (res) {
            _this.productOptions = res.data.data.list || [];
          });
        },
        showAddDialog: function () {
          this.formData = {
            ticket_type_id: '',
            product_ids: [],
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
          ai_ticket_api.saveProductBind(data).then(function (res) {
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
            ai_ticket_api.deleteProductBind(row.id).then(function (res) {
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