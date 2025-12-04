var $ = jQuery.noConflict();
jQuery(document).ready(function ($) {

    /**
     * 后台设置页面中，基于wp.media进行资源上传
     * @since 1.0.0
     * @author Wang Shai
     */

    let button = $(".field-upload-button");
    let mediaUploader = null;

    button.each(function () {
        $(this).on("click", function (e) {
            let input = $(this).siblings("input.field-upload-url");
            let that = $(this);
            e.preventDefault();

            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: "添加资源",
                button: {
                    text: "插入资源",
                },
                multiple: false,
            });

            mediaUploader.on("select", function () {
                let attachment = mediaUploader.state().get("selection").first().toJSON();

                input.val(attachment.url);

                mediaUploader = null;
            });
            mediaUploader.open();
        });
    });
});