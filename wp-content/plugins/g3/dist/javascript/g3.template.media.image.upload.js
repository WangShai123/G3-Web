jQuery(document).ready(($) => {
  /**
   * 后台设置页面中，基于wp.media进行选项图片上传和预览
   * @since 1.0.0
   * @author Wang Shai
   */
  function create_preview_wrap(size = 120, url = '') {
    const preview_wrap = $(
      "<p class='description preview-wrap' style='position:relative;width:auto;height:" +
        size +
        "px;overflow:hidden;'></p>",
    )
    const img = create_preview_image(size, url)
    const clear = create_clear_image()
    preview_wrap.append(img)
    preview_wrap.append(clear)
    return preview_wrap
  }
  function create_preview_image(size = 120, url = '') {
    const img = $(
      "<img class='preview-image' src='" +
        url +
        "' alt='preview image' style='width:auto;height:" +
        size +
        "px;object-fit:cover;' />",
    )
    return img
  }
  function create_clear_image() {
    const clear = $(
      "<span class='clear-image' style='position:absolute;top:0;left:0;width:16px;height:16px;line-height:16px;text-align:center;background-color:#f00;color:#fff;cursor:pointer;'>×</span>",
    )
    return clear
  }
  function clear_preview() {
    const clear = $('.clear-image')
    clear.each(function () {
      $(this).on('click', function () {
        const input = $(this).parent().siblings('input.field-upload-url')
        const preview = $(this).parent()
        window.confirm('确认删除图片吗？') ? (input.val(''), preview.remove()) : ''
      })
    })
  }

  const button = $('.field-upload-image-button')
  let mediaUploader = null

  button.each(function () {
    $(this).on('click', function (e) {
      const input = $(this).siblings('input.field-upload-url')
      let preview = $(this).siblings('.preview-wrap')
      const that = $(this)
      e.preventDefault()

      if (mediaUploader) {
        mediaUploader.open()
        return
      }

      mediaUploader = wp.media.frames.file_frame = wp.media({
        title: '添加图片',
        button: {
          text: '插入图片',
        },
        multiple: false,
      })

      mediaUploader.on('select', () => {
        const attachment = mediaUploader.state().get('selection').first().toJSON()

        input.val(attachment.url)
        preview.remove()
        preview = create_preview_wrap(120, attachment.url)
        that.after(preview)

        clear_preview()
        mediaUploader = null
      })
      mediaUploader.open()
    })
  })

  const upload_url = $('input.field-upload-url')
  upload_url.each(function () {
    const url = $(this).val()
    $(this).on('input', function () {
      if ($(this).val() == '') {
        $(this).siblings('.preview-wrap').remove()
      }
      if (is_resource($(this).val())) {
        $(this).siblings('.preview-wrap').remove()
        $(this)
          .parent()
          .append(create_preview_wrap(120, $(this).val()))
      } else {
        $(this).siblings('.preview-wrap').remove()
      }
      clear_preview()
    })
  })
})

// 资源合法性检测
function is_resource(url) {
  let result
  if (url == '' || url == null || url == undefined) {
    return false
  }
  if (
    typeof url == 'string' &&
    (url.indexOf('http://') == 0 || url.indexOf('https://') == 0) &&
    url.indexOf(' ') == -1 &&
    url.match(/\.(.*)$/i) != null
  ) {
    const xhr = new XMLHttpRequest()
    xhr.open('GET', url, false)
    xhr.send()
    if (xhr.status == 200) {
      result = true
    } else {
      result = false
    }
    return result
  }
}
