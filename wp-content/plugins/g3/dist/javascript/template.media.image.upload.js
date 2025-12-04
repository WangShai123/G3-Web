var $ = jQuery.noConflict();

jQuery(document).ready(function ($) {

	/**
	 * 后台设置页面中，基于wp.media进行选项图片上传和预览
	 * @since 1.0.0
	 * @author Wang Shai
	 */
	function create_preview_wrap(size = 120, url = "") {
		let preview_wrap = $("<p class='description preview-wrap' style='position:relative;width:auto;height:" + size + "px;overflow:hidden;'></p>");
		let img = create_preview_image(size, url);
		let clear = create_clear_image();
		preview_wrap.append(img);
		preview_wrap.append(clear);
		return preview_wrap;
	}
	function create_preview_image(size = 120, url = "") {
		let img = $("<img class='preview-image' src='" + url + "' alt='preview image' style='width:auto;height:" + size + "px;object-fit:cover;' />");
		return img;
	}
	function create_clear_image() {
		let clear = $("<span class='clear-image' style='position:absolute;top:0;left:0;width:16px;height:16px;line-height:16px;text-align:center;background-color:#f00;color:#fff;cursor:pointer;'>×</span>");
		return clear;
	}
	function clear_preview() {
		let clear = $(".clear-image");
		clear.each(function () {
			$(this).on("click", function () {
				let input = $(this).parent().siblings("input.field-upload-url");
				let preview = $(this).parent();
				window.confirm("确认删除图片吗？") ? (input.val(""), preview.remove()) : "";
			});
		});
	}

	let button = $(".field-upload-image-button");
	let mediaUploader = null;

	button.each(function () {
		$(this).on("click", function (e) {
			let input = $(this).siblings("input.field-upload-url");
			let preview = $(this).siblings(".preview-wrap");
			let that = $(this);
			e.preventDefault();

			if (mediaUploader) {
				mediaUploader.open();
				return;
			}

			mediaUploader = wp.media.frames.file_frame = wp.media({
				title: "添加图片",
				button: {
					text: "插入图片",
				},
				multiple: false,
			});

			mediaUploader.on("select", function () {
				let attachment = mediaUploader.state().get("selection").first().toJSON();

				input.val(attachment.url);
				preview.remove();
				preview = create_preview_wrap(120, attachment.url);
				that.after(preview);

				clear_preview();
				mediaUploader = null;
			});
			mediaUploader.open();
		});
	});

	let upload_url = $("input.field-upload-url");
	upload_url.each(function () {
		let url = $(this).val();
		$(this).on("input", function () {
			if ($(this).val() == "") {
				$(this).siblings(".preview-wrap").remove();
			}
			if (is_resource($(this).val())) {
				$(this).siblings(".preview-wrap").remove();
				$(this).parent().append(create_preview_wrap(120, $(this).val()));
			} else {
				$(this).siblings(".preview-wrap").remove();
			}
			clear_preview();
		});
	});
});

// 资源合法性检测
function is_resource(url) {
	let result;
	if (url == "" || url == null || url == undefined) {
		return false;
	}
	if (typeof (url) == 'string' && (url.indexOf("http://") == 0 || url.indexOf("https://") == 0) && url.indexOf(" ") == -1 && url.match(/\.(.*)$/i) != null) {
		let xhr = new XMLHttpRequest();
		xhr.open("GET", url, false);
		xhr.send();
		if (xhr.status == 200) {
			result = true;
		} else {
			result = false;
		}
		return result;
	}
}