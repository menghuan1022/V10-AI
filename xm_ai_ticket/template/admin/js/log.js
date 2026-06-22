/**
 * AI回复日志页面逻辑
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
          params: {
            page: 1,
            limit: 20,
          },
          columns: [
            { colKey: 'id', title: this.lang.log_id, width: 60 },
            { colKey: 'ticket_id', title: this.lang.ticket_id, width: 80 },
            { colKey: 'ticket_title', title: this.lang.ticket_title, ellipsis: true },
            { colKey: 'model_name', title: this.lang.model_name_col, ellipsis: true },
            { colKey: 'reply_content', title: this.lang.reply_content, cell: 'reply_content', ellipsis: true },
            { colKey: 'tokens_used', title: this.lang.tokens_used, width: 90 },
            { colKey: 'create_time', title: this.lang.reply_time, cell: 'create_time', width: 150 },
            { colKey: 'op', title: this.lang.edit, cell: 'op', width: 120 },
          ],
          detailVisible: false,
          currentRow: null,
        };
      },
      created: function () {
        this.getList();
      },
      methods: {
        getList: function () {
          var _this = this;
          _this.loading = true;
          ai_ticket_api.getLogList(_this.params).then(function (res) {
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
        formatTime: function (timestamp) {
          if (!timestamp) return '-';
          var d = new Date(timestamp * 1000);
          var Y = d.getFullYear();
          var m = (d.getMonth() + 1 < 10 ? '0' + (d.getMonth() + 1) : d.getMonth() + 1);
          var dd = (d.getDate() < 10 ? '0' + d.getDate() : d.getDate());
          var H = (d.getHours() < 10 ? '0' + d.getHours() : d.getHours());
          var i = (d.getMinutes() < 10 ? '0' + d.getMinutes() : d.getMinutes());
          var s = (d.getSeconds() < 10 ? '0' + d.getSeconds() : d.getSeconds());
          return Y + '-' + m + '-' + dd + ' ' + H + ':' + i + ':' + s;
        },
        viewDetail: function (row) {
          this.currentRow = row;
          this.detailVisible = true;
        },
        deleteRow: function (row) {
          var _this = this;
          _this.$confirm(_this.lang.delete + '?', function () {
            ai_ticket_api.deleteLog(row.id).then(function (res) {
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