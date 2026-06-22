/**
 * 插件设置页面逻辑
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
          formData: {
            enable: 0,
            default_reply_interval: 15,
            default_max_chars: 255,
            default_persona: '',
            transfer_keywords: '转人工,人工客服,人工服务,转接人工',
            transfer_method: 1,
            feishu_webhook: '',
            transfer_auto_reply: '您的这个问题我这边权限不够处理不了，已经帮您升级工单转交给高级技术人员了，请稍等片刻他们会尽快为您处理。',
            context_max_chars: 5120,
            enable_client_context: 1,
            client_context_hosts_limit: 10,
            client_context_orders_limit: 5,
          },
          formRules: {},
          enableOptions: [
            { label: this.lang.enable_on, value: 1 },
            { label: this.lang.enable_off, value: 0 },
          ],
          transferMethodOptions: [
            { label: this.lang.transfer_method_none, value: 1 },
            { label: this.lang.transfer_method_feishu, value: 2 },
            { label: this.lang.transfer_method_auto_reply, value: 3 },
            { label: this.lang.transfer_method_all, value: 4 },
          ],
          cronUrl: location.origin + '/ai_ticket_cron',
          feishuTesting: false,
        };
      },
      computed: {
        showFeishuConfig: function () {
          return this.formData.transfer_method === 2 || this.formData.transfer_method === 4;
        },
        showAutoReplyConfig: function () {
          return this.formData.transfer_method === 3 || this.formData.transfer_method === 4;
        },
      },
      created: function () {
        this.getConfig();
      },
      methods: {
        getConfig: function () {
          var _this = this;
          ai_ticket_api.getPluginConfig().then(function (res) {
            if (res.data.status === 200 && res.data.data) {
              var config = res.data.data;
              _this.formData.enable = parseInt(config.enable) || 0;
              _this.formData.default_reply_interval = parseInt(config.default_reply_interval) || 15;
              _this.formData.default_max_chars = parseInt(config.default_max_chars) || 255;
              _this.formData.default_persona = config.default_persona || '';
              _this.formData.transfer_keywords = config.transfer_keywords || '';
              _this.formData.transfer_method = parseInt(config.transfer_method) || 1;
              _this.formData.feishu_webhook = config.feishu_webhook || '';
              _this.formData.transfer_auto_reply = config.transfer_auto_reply || '正在为您转接人工客服，请稍候...';
              _this.formData.context_max_chars = parseInt(config.context_max_chars) || 5120;
              _this.formData.enable_client_context = parseInt(config.enable_client_context) || 0;
              _this.formData.client_context_hosts_limit = parseInt(config.client_context_hosts_limit) || 10;
              _this.formData.client_context_orders_limit = parseInt(config.client_context_orders_limit) || 5;
            }
          });
        },
        submitConfig: function (validateResult) {
          if (validateResult.validateResult !== true) return;
          var _this = this;
          ai_ticket_api.savePluginConfig(JSON.parse(JSON.stringify(_this.formData))).then(function (res) {
            if (res.data.status === 200) {
              _this.$message.success(_this.lang.submit_success);
            } else {
              _this.$message.error(res.data.msg || _this.lang.submit_fail);
            }
          }).catch(function () {
            _this.$message.error(_this.lang.submit_fail);
          });
        },
        testFeishu: function () {
          var _this = this;
          if (!_this.formData.feishu_webhook) {
            _this.$message.warning(_this.lang.feishu_webhook_placeholder);
            return;
          }
          _this.feishuTesting = true;
          ai_ticket_api.testFeishuWebhook({ feishu_webhook: _this.formData.feishu_webhook }).then(function (res) {
            if (res.data.status === 200) {
              _this.$message.success(_this.lang.feishu_test_success);
            } else {
              _this.$message.error(res.data.msg || _this.lang.feishu_test_fail);
            }
          }).catch(function () {
            _this.$message.error(_this.lang.feishu_test_fail);
          }).finally(function () {
            _this.feishuTesting = false;
          });
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
      },
    }).$mount(template);

    typeof old_onload == "function" && old_onload;
  };
})(window);
