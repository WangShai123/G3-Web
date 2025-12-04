'use strict';
jQuery(document).ready(function($) {
  const edit = $('[data-selector="profile-edit"]');

  edit.each(function() {
    $(this).click(function() {
      const _label = $(this).data('label');
      const _name = $(this).data('key');
      const _value = $(this).data('value');
      const _type = $(this).data('type');
      const _uid = typeof $(this).data('uid') === 'undefined' ? 0 : $(this).data('uid');
      if (_type == 'input') {
        let _input_type = $(this).data('inputType');
        _input_type = typeof _input_type === 'undefined' ? 'text' : _input_type;
        const modal = new JModal({
          name: _name,
          title: '编辑',
          form: true,
          formDisplay: 'item-inline',
          formItems: {
            [_label]: {
              type: 'input',
              input_type: _input_type,
              name: _name,
              id: _name,
              value: _value,
              size: 'm',
            },
            '': {
              type: 'action',
              size: 'm',
            },
          },
          action: '.action-submit',
          onAction: function() {
            const _val = $('#' + _name).val();
            $.ajax({
              url: '/wp-admin/admin-ajax.php',
              type: 'POST',
              dataType: 'json',
              data: {
                action: 'jl_ajax_update_user_' + _name,
                [_name]: _val,
                uid: _uid,
              },
              beforeSend: function() {
                _before_send();
              },
              success: function(res) {
                _success_res(res, modal);
              },
              error: function(res) {
                _error_res(res);
              },
            });
          },
        });
      } else if (_type == 'textarea') {
        const textarea_modal = new JModal({
          name: _name,
          title: '编辑',
          form: true,
          formDisplay: 'item-inline',
          formItems: {
            [_label]: {
              type: 'textarea',
              name: _name,
              id: _name,
              value: _value,
              rows: 4,
              cols: 30,
              size: 'm',
            },
            '': {
              type: 'action',
              size: 'm',
            },
          },
          action: '.action-submit',
          onAction: function() {
            const _val = $('#' + _name).val();
            $.ajax({
              url: '/wp-admin/admin-ajax.php',
              type: 'POST',
              dataType: 'json',
              data: {
                action: 'jl_ajax_update_user_' + _name,
                [_name]: _val,
                uid: _uid,
              },
              beforeSend: function() {
                _before_send();
              },
              success: function(res) {
                _success_res(res, textarea_modal);
              },
              error: function(res) {
                _error_res(res);
              },
            });
          },
        });
      } else if (_type == 'select') {
        const _options = $(this).data('options');
        const select_modal = new JModal({
          name: _name,
          title: '编辑',
          form: true,
          formDisplay: 'item-inline',
          formItems: {
            [_label]: {
              type: 'select',
              name: _name,
              id: _name,
              options: _options,
              size: 'm',
            },
            '': {
              type: 'action',
              size: 'm',
            },
          },
          action: '.action-submit',
          onAction: function() {
            const _val = $('#' + _name).find('option:selected').val();
            $.ajax({
              url: '/wp-admin/admin-ajax.php',
              type: 'POST',
              dataType: 'json',
              data: {
                action: 'jl_ajax_update_user_' + _name,
                [_name]: _val,
                uid: _uid,
              },
              beforeSend: function() {
                _before_send();
              },
              success: function(res) {
                _success_res(res, select_modal);
              },
              error: function(res) {
                _error_res(res);
              },
            });
          },
        });
      } else if (_type == 'file') {
        $(this).change(function(event) {
          const file = event.target.files[0];
          if (!file) return;
          const formData = new FormData();
          formData.append('file', file);
          formData.append('action', 'jl_ajax_update_user_' + _name);
          formData.append('filesize', file.size);
          formData.append('type', file.type);
          formData.append('uid', _uid);
          const _label = $(this).siblings('label');
          const _background = $(this).parent();
          $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
              _label.innerHTML = '<i class="ri-loader-line animate-spin"></i>';
            },
            success: function(data) {
              if (data.code === 200) {
                _background.css('background-image', 'url(' + data.data.url + ')');
                const success = new JToast({
                  content: data.msg,
                  theme: 'success',
                  icon: 'checkbox-circle-line',
                  delay: 1500,
                });
              } else {
                const error = new JToast({
                  content: data.msg,
                  theme: 'error',
                  icon: 'error-warning-line',
                  delay: 1500,
                });
              }
            },
            error: function(xhr, status, data) {
              console.error(data.code);
              const error = new JToast({
                content: data.msg,
                theme: 'error',
                icon: 'error-warning-line',
                delay: 1500,
              });
            },
            complete: function() {
              _label.innerHTML = '编辑';
            },
          });
        });
      } else if (_type == 'set-password') {
        const _old = $(this).data('label-old');
        const _confirm = $(this).data('label-confirm');
        const is_old = $(this).data('old') === true ? true : false;
        const old_dom = is_old ? `<div class="form-item">
                    <label class="form-item-title text-m" for="old_password">${_old}</label>
                    <div class="form-item-content">
                        <div class="j-input is-m w-full"><input type="text" id="old_password" name="old_password" value="" class="is-input w-full" tab-index="0"></div>
                    </div>
                </div>` : ``;
        const pwd_modal = new JModal({
          name: _name,
          title: '编辑',
          content: `
                    <form class="j-form">
                        ${old_dom}
                        <div class="form-item">
                            <label class="form-item-title text-m" for="${_name}">${_label}</label>
                            <div class="form-item-content">
                                <div class="j-input is-m w-full"><input type="text" id="${_name}" name="${_name}" value="" class="is-input w-full" tab-index="0"></div>
                            </div>
                        </div>
                        <div class="form-item">
                            <label class="form-item-title text-m" for="confirm_password">${_confirm}</label>
                            <div class="form-item-content">
                                <div class="j-input is-m w-full"><input type="text" id="confirm_password" name="confirm_password" value="" class="is-input w-full" tab-index="0"></div>
                            </div>
                        </div>
                        <div class="form-item">
                            <label class="form-item-title"></label>
                            <div class="form-item-content">
                                <button type="button" class="j-button is-m is-primary action-submit" tab-index="1">提交</button>
                                <button type="reset" class="j-button is-m" tab-index="2">重置</button>
                            </div>
                        </div>
                    </form>`,
          action: '.action-submit',
          onAction: function() {
            const _old_password = $('#old_password').val();
            const _password = $('#' + _name).val();
            const _confirm_password = $('#confirm_password').val();
            $.ajax({
              url: '/wp-admin/admin-ajax.php',
              type: 'POST',
              dataType: 'json',
              data: {
                action: 'jl_ajax_update_user_' + _name,
                old_password: _old_password,
                new_password: _password,
                confirm_password: _confirm_password,
                uid: _uid,
              },
              beforeSend: function() {
                _before_send();
              },
              success: function(res) {
                _success_res(res, pwd_modal);
              },
              error: function(res) {
                _error_res(res);
              },
            });
          },
        });
      } else if (_type === 'cascading') {
        const cascading_modal = new JModal({
          name: _name,
          title: '编辑',
          content: `
                    <form class="j-form">
                        <div class="form-item">
                            <div class="form-item-title text-m">${_label}</div>
                            <div class="form-item-content is-grid is-3 gap-s" id="cascading">
                                <div class="j-select is-m">
                                    <select id="province" name="province" data-selector="province" class="is-select" tab-index="0">
                                        <option value="">省份</option>
                                    </select>
                                </div>
                                <div class="j-select is-m">
                                    <select id="city" name="city" data-selector="city" class="is-select" tab-index="0">
                                        <option value="">城市</option>
                                    </select>
                                </div>
                                <div class="j-select is-m">
                                    <select id="district" name="district" data-selector="district" class="is-select" tab-index="0">
                                        <option value="">区县</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-item">
                            <div class="form-item-title"></div>
                            <div class="form-item-content">
                                <button type="button" class="j-button is-m is-primary action-submit" tab-index="1">保存</button>
                                <button type="button" class="j-button is-m action-close" tab-index="2">取消</button>
                            </div>
                        </div>
                    </form>`,
          style: 'max-width:420px;',
          onShow: function() {
            const cascading = new JCascading('#cascading');
          },
          action: '.action-submit',
          onAction: function() {
            const _privince = $('#province').find('option:selected').val();
            const _city = $('#city').find('option:selected').val();
            const _district = $('#district').find('option:selected').val();
            if (_privince == '' || _city == '' || _district == '') {
              const error = new JToast({
                theme: 'error',
                content: '请输入完整的地域信息',
              });
              return;
            };
            $.ajax({
              url: '/wp-admin/admin-ajax.php',
              type: 'POST',
              dataType: 'json',
              data: {
                action: 'jl_ajax_update_user_' + _name,
                province: _privince,
                city: _city,
                district: _district,
                uid: _uid,
              },
              beforeSend: function() {
                _before_send();
              },
              success: function(res) {
                _success_res(res, cascading_modal);
              },
              error: function(res) {
                _error_res(res);
              },
            });
          },
        });
      } else {
        console.warn('未知类型');
      };
    });
  });

  function _before_send() {
    $('button.action-submit').prop('disabled', true).html('<i class="ri-loader-4-line animate-spin"></i>更新中...');
    $('button.action-submit').siblings('button.action-close').prop('disabled', true);
  };
  function _success_res(data, modal) {
    if (data.code == 200) {
      $('button.action-submit').siblings('button.action-close').remove();
      $('button.action-submit').prop('disabled', false).removeClass('is-primary action-submit').addClass('is-ghost-success').html('<i class="ri-checkbox-circle-line"></i>更新成功');
      setTimeout(function() {
        modal.destroy();
        window.location.reload();
      }, 800);
    } else {
      const error = new JToast({
        type: 'card',
        title: '非法请求',
        theme: 'error',
        content: data.msg,
        delay: 2000,
        position: 'center',
        icon: 'error-warning-line',
      });
      setTimeout(function() {
        $('button.action-submit').prop('disabled', false).html('保存');
        $('button.action-submit').siblings('button.action-close').prop('disabled', false);
      }, 2000);
    }
  };
  function _error_res(data) {
    console.error(data.code);
    const error = new JToast({
      type: 'card',
      title: '错误警告',
      theme: 'warning',
      content: data.msg,
      delay: 2000,
      position: 'center',
      icon: 'error-warning-line',
    });
    setTimeout(function() {
      $('button.action-submit').prop('disabled', false).html('保存');
      $('button.action-submit').siblings('button.action-close').prop('disabled', false);
    }, 2000);
  };
});
