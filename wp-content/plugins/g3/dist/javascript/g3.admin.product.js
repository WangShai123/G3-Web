jQuery(document).ready(($) => {
  if (!productData) return
  const { toast, tooltip } = jui
  const { randomId, t, escapeHtml, q } = jui.u
  const { data, prop, gallery } = productData.data
  const skuApp = $('#sku-app')
  const galleryApp = $('#gallery-app')
  const propApp = $('#prop-app')

  const _testData = {
    key: 'g3_product_type',
    type: 'product',
    specs: ['颜色', '尺寸'],
    sku: [
      {
        specs: ['红色', 'M'],
        originalPrice: '100.00',
        salePrice: '80.00',
        weight: '1.00',
        size: '2',
        stock: '1000',
        sold: '20',
      },
      {
        specs: ['蓝色', 'L'],
        originalPrice: '110.00',
        salePrice: '90.00',
        weight: '2.00',
        size: '',
        stock: '100',
        sold: '2',
      },
    ],
  }

  const texts = {
    en: {
      default: 'Default',
      confirmToDelete: 'Are you sure to delete?',
      property: 'Property',
      name: 'Name',
      value: 'Value',
      enterPropertyName: 'Enter property name',
      enterPropertyValue: 'Enter property value',
      remove: 'Remove',
      addProperty: 'Add Property',
      general: 'General',
      download: 'Download',
      membership: 'Membership',
      originalPrice: 'Original Price',
      salePrice: 'Sale price',
      weight: 'Weight',
      size: 'Size',
      stock: 'Stock',
      quantitySold: 'Quantity Sold',
      setGallery: 'Set Gallery',
      addSpecItem: 'Add Spec Item',
      atLeastOneRowRequired: 'At least one row is required.',
      spec: 'Spec',
      specValue: 'Spec Value',
      maximumTwoSpecsAllowed: 'Maximum two specifications are allowed.',
    },
    zh: {
      default: '默认',
      confirmToDelete: '确定要刪除吗？',
      property: '属性',
      name: '名称',
      value: '值',
      enterPropertyName: '请输入属性名称',
      enterPropertyValue: '请输入属性值',
      remove: '移除',
      addProperty: '添加属性',
      general: '常规',
      download: '下载',
      membership: '会员',
      originalPrice: '原价',
      salePrice: '售价',
      weight: '重量',
      size: '大小',
      kg: '千克',
      stock: '库存',
      quantitySold: '销量',
      add: '添加',
      addSpec: '添加规格',
      addSpecValue: '添加规格值',
      addSpecValueTip: '请输入规格值',
      addSpecTip: '请输入规格名称',
      setGallery: '设置图册',
      addSpecItem: '添加规格项',
      atLeastOneRowRequired: '至少需要添加一行数据',
      spec: '规格',
      specValue: '规格值',
      maximumTwoSpecsAllowed: '最多允许添加两个规格',
    },
  }
  const ts = (key) => {
    return t(key, texts)
  }

  // render gallery app
  const slider = (url) => {
    return `
			<div class="swiper-slide">
				<div class="gallery-item" data-url="${url}">
					${
            url.match(/\.(mp4|webm|ogg)$/i)
              ? `<video src="${url}" controls></video>`
              : `<img src="${url}">`
          }
					<div class="action-removeGalleryItem"><span class="icon-close"></span></div>
					<input type="hidden" name="${gallery.key}[]" value="${url}">
				</div>
			</div>`
  }
  const renderGallery = (gallery) => {
    let slidesHtml = ''
    if (gallery.items && gallery.items.length > 0) {
      for (const item of gallery.items) {
        slidesHtml += slider(item)
      }
    }
    return `
			<div style="overflow: hidden;position:relative" class="hide-if-no-js">
				<div class="swiper-container" id="g3GallerySwiper">
					<div class="swiper-wrapper">${slidesHtml}</div>
					<div class="swiper-pagination"></div>
				</div>
			</div>
			<p class="hide-if-no-js">
				<a href="javascript:void(0)" id="action-addToGallery" style="font-weight: 600">+ ${ts('setGallery')}</a>
			</p>`
  }
  if (galleryApp) galleryApp.html(renderGallery(gallery))

  // register product gallery swiper
  let gallerySwiper
  if ($('#g3-metabox-gallery').length > 0) {
    gallerySwiper = new Swiper('#g3GallerySwiper', {
      autoplay: true,
      effect: 'fade',
      slidesPerView: 1,
      spaceBetween: 8,
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
    })
  }
  if ($('#action-addToGallery').length > 0) {
    // add to product gallery
    $('#action-addToGallery').on('click', () => {
      const frame = wp.media({
        multiple: true,
        library: { type: ['image', 'video'] },
      })
      frame.on('select', () => {
        const selection = frame.state().get('selection')
        selection.each((attachment) => {
          const url = attachment.attributes.url
          const slide = slider(url)
          gallerySwiper.appendSlide(slide)
          gallerySwiper.update()
        })
      })
      frame.open()
    })
    // remove from product gallery
    $(document).on('click', '.action-removeGalleryItem', function () {
      if (confirm(ts('confirmToDelete'))) {
        $(this)
          .closest('.swiper-slide')
          .fadeOut(300, function () {
            $(this).remove()
            gallerySwiper.update()
          })
      }
    })
  }

  if ($('#specification-app').length > 0) {
    /**
     * product specification
     */
    const content = `
      <div class="specification-container">
        <div class="j-radio is-group">
          <label class="radio-label">
            <input type="radio" class="j-input" name="g3-specification" value="product">
            <span class="radio-text">${ts('general')}</span>
          </label>
          <label class="radio-label">
            <input type="radio" class="j-input" name="g3-specification" value="download">
            <span class="radio-text">${ts('download')}</span>
          </label>
          <label class="radio-label">
            <input type="radio" class="j-input" name="g3-specification" value="membership">
            <span class="radio-text">${ts('membership')}</span>
          </label>
        </div>
        <div id="specification-content"></div>
      </div>
      `
    $('#specification-app').html(content)
    const renderContent = (type) => {
      switch (type) {
        case 'download':
          renderDownload()
          break
        case 'product':
          renderGeneral()
          break
        default:
          break
      }
    }

    const renderDownload = () => {
      skuApp.empty()
      const download = data.sku[0]
      const inputs = [
        {
          label: ts('originalPrice'),
          name: 'originalPrice',
          type: 'number',
          value: download.originalPrice ? download.originalPrice : 0,
        },
        {
          label: ts('salePrice'),
          name: 'salePrice',
          type: 'number',
          value: download.salePrice ? download.salePrice : 0,
        },
        {
          label: ts('size'),
          name: 'size',
          type: 'number',
          value: download.size ? download.size : 0,
          unit: 'MB',
        },
        {
          label: ts('stock'),
          name: 'stock',
          type: 'number',
          value: download.stock ? download.stock : 0,
        },
        {
          label: ts('quantitySold'),
          name: 'sold',
          type: 'number',
          value: download.sold ? download.sold : 0,
        },
      ]
      let html = '<div class="specification-inputs">'
      for (const input of inputs) {
        html += `
			<div class="grid-container grid-col-1 grid-col-sm-1 grid-col-md-2 grid-col-lg-2 grid-col-xl-4">
			<div class="input-group">
				<span class="el-addon">
					<span class="is-text">${input.label}</span>
				</span>
				<input 
					class="j-input"
					type="${input.type || 'text'}" 
					name="${input.name}" 
					value="${input.value || ''}"
					${input.disabled ? 'disabled' : ''}
					${input.readonly ? 'readonly' : ''}
					placeholder="${input.placeholder || ''}"
				>
				${input.unit ? `<div class="el-addon"><div class="is-text">${input.unit}</div></div>` : ''}
			</div></div>
			`
      }

      html += '</div>'
      skuApp.html(html)
    }

    const renderGeneral = () => {
      skuApp.empty()

      // === 1. 数据准备 ===
      let specs = data.specs || [{ name: '' }]
      let sku = data.sku || []

      // 限制最多2列
      if (specs.length > 2) {
        specs = specs.slice(0, 2)
      }

      const colCount = specs.length
      if (colCount === 0) specs = [{ name: '' }]

      // 确保至少一行
      if (sku.length === 0) {
        sku = [
          {
            specValues: Array(colCount).fill(''),
            originalPrice: '',
            salePrice: '',
            weight: '',
            stock: '',
            sold: '',
          },
        ]
      }

      // === 2. 创建容器 ===
      const $wrapper = $(
        '<div class="table-wrapper" style="overflow-x: auto; margin-top: 16px;"></div>',
      )
      const $table = $('<table class="j-table sku-table"></table>')
      const $thead = $('<thead></thead>')
      const $tbody = $('<tbody></tbody>')

      const fixedHeaders = [
        ts('originalPrice'),
        ts('salePrice'),
        `${ts('weight')} (${ts('kg')})`,
        ts('stock'),
        ts('quantitySold'),
      ]

      // === 3. 渲染表头 ===
      const $tr = $('<tr></tr>')

      for (let idx = 0; idx < specs.length; idx++) {
        const col = specs[idx]
        const $th = $('<th></th>')

        if (idx === 0) {
          const $input = $(`
        <input type="text" class="j-input spec-name-input"
          name="g3_product_specs[${idx}]"
          placeholder="${ts('spec')} ${idx + 1}"
          data-col-index="${idx}"
          value="${escapeHtml(col)}">
      `)
          $th.append($input)
        } else {
          const _value = typeof col === 'object' ? `${ts('spec')} ${idx + 1}` : col
          const $group = $(`<div class="input-inner-group" data-index="${idx}"></div>`)
          const $input = $(`
        <input type="text" class="j-input spec-name-input"
          name="g3_product_specs[${idx}]"
          placeholder="${ts('spec')} ${idx + 1}"
          data-col-index="${idx}"
          value="${_value}">
      `)
          const $del = $(
            `<span class="el-addon remove-spec-col"><span class="icon-close"></span></span>`,
          )
          $group.append($input).append($del)
          $th.append($group)
        }
        $tr.append($th)
      }

      for (const h of fixedHeaders) {
        $tr.append(`<th>${escapeHtml(h)}</th>`)
      }

      $thead.append($tr)
      $table.append($thead)

      // === 4. 渲染表体 ===
      for (let rowIndex = 0; rowIndex < sku.length; rowIndex++) {
        const row = sku[rowIndex]
        const $row = $('<tr></tr>')
        const specValues = row.specs || []

        // 渲染规格值列
        for (let colIndex = 0; colIndex < colCount; colIndex++) {
          const val = colIndex < specValues.length ? specValues[colIndex] : ''
          let inputHtml = `
        <input type="text" class="j-input spec-value-input"
          name="g3_product_sku[${rowIndex}][specValue][${colIndex}]"
          placeholder="${ts('specValue')}"
          data-row-index="${rowIndex}"
          data-col-index="${colIndex}"
          value="${escapeHtml(val)}">`
          if (colIndex === 0) {
            inputHtml += '<span class="el-addon remove-row"><span class="icon-close"></span></span>'
          }

          $row.append(`<td><div class="input-inner-group">${inputHtml}</div></td>`)
        }

        // 渲染固定字段列
        const fields = ['originalPrice', 'salePrice', 'weight', 'stock', 'sold']
        for (let i = 0; i < fields.length; i++) {
          const field = fields[i]
          const header = fixedHeaders[i]
          const value = row[field] != null ? row[field] : ''
          $row.append(`
        <td>
          <div class="input-inner-group">
            <input type="number" class="j-input"
              name="g3_product_sku[${rowIndex}][${field}]"
              value="${value}" min="0"
              placeholder="${header}">
          </div>
        </td>
      `)
        }

        $tbody.append($row)
      }

      $table.append($tbody)
      $wrapper.append($table)
      skuApp.append($wrapper)

      // === 5. 操作栏 ===
      const $actionBar = $(`
    <div style="margin-top: 12px;">
      <button type="button" class="j-button is-outline is-sm add-spec-item">+ ${ts('addSpecItem')}</button>
      <button type="button" class="j-button is-secondary is-sm add-spec-col" ${specs.length >= 2 ? 'disabled' : ''}>+ ${ts('addSpec')}</button>
    </div>
  `)
      skuApp.append($actionBar)

      // === 6. 事件绑定（委托）===

      // 添加规格列
      skuApp.off('click', '.add-spec-col').on('click', '.add-spec-col', () => {
        if (data.specs.length < 2) {
          data.specs.push({ name: '' })
          for (const sku of data.sku) {
            if (!sku.specValues) sku.specValues = []
            sku.specValues.push('')
          }
          renderGeneral()
        } else {
          toast.error(ts('maximumTwoSpecsAllowed'))
        }
      })

      // 删除规格列
      skuApp.off('click', '.remove-spec-col').on('click', '.remove-spec-col', function () {
        const index = parseInt($(this).closest('.input-inner-group').data('index'), 10)
        if (confirm(ts('confirmToDelete'))) {
          data.specs.splice(index, 1)
          for (const sku of data.sku) {
            if (sku.specValues && sku.specValues.length > index) {
              sku.specValues.splice(index, 1)
            }
          }
          renderGeneral()
        }
      })

      // 添加 SKU 行
      skuApp.off('click', '.add-spec-item').on('click', '.add-spec-item', () => {
        const newSpecValues = Array(data.specs.length).fill('')
        data.sku.push({
          specValues: newSpecValues,
          originalPrice: '',
          salePrice: '',
          weight: '',
          stock: '',
          sold: '',
        })
        renderGeneral()
      })

      // 删除行
      skuApp.off('click', '.remove-row').on('click', '.remove-row', function () {
        if (data.sku.length <= 1) {
          toast.info(ts('atLeastOneRowRequired'))
          return
        }
        const rowIndex = $(this).closest('tr').index()
        if (confirm(ts('confirmToDelete'))) {
          data.sku.splice(rowIndex, 1)
          renderGeneral()
        }
      })

      // 规格名输入同步（可选，因有 name，通常提交时整体读取即可）
      // 如果需要实时响应，可监听，但非必需
    }

    const radio = $('.j-input[name="g3-specification"]')
    if (radio.length > 0) {
      radio.filter(`[value="${data.type}"]`).prop('checked', true)
      renderContent(data.type)
      radio.on('change', function () {
        renderContent(this.value)
      })
    }
    // 确保表格在手机设备上可以左右滑动
    $('.sku-table-container').css('overflow-x', 'auto')

    new tooltip(q('.add-spec-col'), {
      message: `${ts('maximumTwoSpecsAllowed')}`,
      delay: 300,
    })
  }

  /**
   * Property
   */
  if (propApp.length > 0) {
    const renderPropItem = (item, key, indexOrHash, labelIndex) => {
      return `
      <div class="prop-item">
        <div>${ts('property')} ${labelIndex}</div>
        <div class="grid-container grid-col-xl-4 grid-col-lg-3 grid-col-md-2 grid-col-sm-2 grid-col-1">
          <div class="input-group">
            <span class="el-addon"><span class="is-text">${ts('name')}</span></span>
            <input class="j-input" type="text"
              name="${key}[${indexOrHash}][name]"
              id="${key}[${indexOrHash}][name]"
              value="${item.name ? escapeHtml(item.name) : ''}"
              placeholder="${ts('enterPropertyName')}">
          </div>
          <div class="input-group">
            <span class="el-addon"><span class="is-text">${ts('value')}</span></span>
            <input class="j-input" type="text"
              name="${key}[${indexOrHash}][value]"
              id="${key}[${indexOrHash}][value]"
              value="${item.value ? escapeHtml(item.value) : ''}"
              placeholder="${ts('enterPropertyValue')}">
            <span class="el-addon">
              <button class="j-button is-danger remove-property">${ts('remove')}</button>
            </span>
          </div>
        </div>
      </div>
    `
    }

    // 初始化容器和计数
    const container = $('<div class="properties-container"></div>')
    let count = 0

    // 渲染初始项（使用数字索引）
    if (prop && Array.isArray(prop.items)) {
      prop.items.forEach((item, index) => {
        container.append(renderPropItem(item, prop.key, index, index + 1))
        count++
      })
    }

    propApp.empty().append(container)

    // 添加“+”按钮
    const addButton = $(
      `<div style="margin-top:8px">
      <button type="button" class="j-button is-outline is-sm add-property">+ ${ts('addProperty')}</button>
    </div>`,
    )
    propApp.append(addButton)

    // 事件处理
    $(document).on('click', '.add-property', () => {
      const hash = randomId()
      const html = renderPropItem({ name: '', value: '' }, prop.key, hash, count + 1)
      $(html).hide().appendTo(container).slideDown(300)
      count++
    })

    $(document).on('click', '.remove-property', function (e) {
      e.preventDefault()
      if (confirm(ts('confirmToDelete'))) {
        $(this)
          .closest('.prop-item')
          .fadeOut(300, function () {
            $(this).remove()
            count--
          })
      }
    })
  }
})
