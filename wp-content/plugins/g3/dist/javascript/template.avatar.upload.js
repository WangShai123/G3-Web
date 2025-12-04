jQuery(document).ready(function ($) {

    const label = $('#j-user-avatar-label');
    const input = $('#j-user-avatar-input');

    input.on('change', handleUpload);

    function handleUpload(event) {
        var file = event.target.files[0];
        if (!file) return;

        var formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'jl_ajax_upload_user_avatar');
        formData.append('filesize', file.size);
        formData.append('type', file.type);

        $.ajax({
            url: "/wp-admin/admin-ajax.php",
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function () {
                label.innerHTML = '<span class="el-prefix"><i class="ri-loader-line animate-spin"></i></span><span>上传中</span>';
            },
            success: function (res) {
                if (res.code === 200) {
                    var useravatar = $('#is-user-avatar-wrap');
                    useravatar.css('background-image', 'url(' + res.data.url + ')');
                    var success = new JToast({
                        content: res.msg,
                        theme: 'success',
                        icon: 'checkbox-circle-line',
                        delay: 1500,
                    });
                } else {
                    var error = new JToast({
                        content: res.msg,
                        theme: 'error',
                        icon: 'error-warning-line',
                        delay: 1500,
                    });
                }
            },
            error: function (xhr, status, res) {
                var error = new JToast({
                    content: res.msg,
                    theme: 'error',
                    icon: 'error-warning-line',
                    delay: 1500,
                });
            },
            complete: function () {
                label.innerHTML = '<span class="el-prefix"><i class="ri-camera-line"></i></span><span>编辑头像</span>';
            }
        });
    }
});
