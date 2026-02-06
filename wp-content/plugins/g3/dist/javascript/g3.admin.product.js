/**
 * 注意：js规范：
 * - 不使用 forEach，使用 for of 或 for in
 * - 不使用可选链 ?.
 * - 不要创建不被使用的变量
 * - 优先使用 === 而不是 ==
 */

jQuery(document).ready(($) => {
  if (!productData) return
  const { toast, randomId, t, escapeHtml, reactive } = jui
  const skuApp = $('#sku-app')
  const galleryApp = $('#gallery-app')
  const propApp = $('#prop-app')
  const detailApp = $('#detail-app')
  const loadingBlock = `<div class="j-loading is-active"><span class="loading-spinner"></span></div>`
  const { support, data, property, gallery } = productData.data
  const isPath = (path) => {
    return window.location.pathname.indexOf(path) !== -1
  }
  let newPost = false
  if (isPath('post-new.php')) {
    newPost = true
  }

  const ts = (key) => {
    const texts = {
      en: {
        default: 'Default',
        toDelete: 'Are you sure to delete?',
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
        reset: 'Reset',
        originalPrice: 'Original Price',
        salePrice: 'Sale price',
        weight: 'Weight',
        unit: 'Unit',
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
        toDelete: '确定要刪除吗？',
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
        reset: '重置',
        originalPrice: '原价',
        salePrice: '售价',
        weight: '重量',
        unit: '单位',
        size: '大小',
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
    return t(key, texts)
  }

  // render gallery app
  const onGalleryApp = () => {
    if (galleryApp) {
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
        if (gallery.photos && gallery.photos.length > 0) {
          for (const item of gallery.photos) {
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
      galleryApp.html(renderGallery(gallery))
      galleryApp.prepend(loadingBlock)
      setTimeout(() => {
        galleryApp.find('.j-loading').remove()
      }, 1000)
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
          if (confirm(ts('toDelete'))) {
            $(this)
              .closest('.swiper-slide')
              .fadeOut(300, function () {
                $(this).remove()
                gallerySwiper.update()
              })
          }
        })
      }
    }
  }

  const onTypeApp = () => {
    if ($('#type-app').length > 0) {
      detailApp.prepend(loadingBlock)
      setTimeout(() => {
        detailApp.find('.j-loading').remove()
      }, 1000)
      /**
       * product specification
       */
      const content = `
      <div class="type-container">
        <div class="j-radio is-group">
          <label class="radio-label">
            <input type="radio" class="j-input" name="g3-specification" value="1">
            <span class="radio-text">${ts('general')}</span>
          </label>
          <!-- Download label -->
      ${
        support && support.download === '1'
          ? `
        <label class="radio-label">
          <input type="radio" class="j-input" name="g3-specification" value="4">
          <span class="radio-text">${ts('download')}</span>
        </label>
      `
          : ''
      }

      <!-- Membership label -->
      ${
        support && support.membership === '1'
          ? `
        <label class="radio-label">
          <input type="radio" class="j-input" name="g3-specification" value="3">
          <span class="radio-text">${ts('membership')}</span>
        </label>
      `
          : ''
      }
        </div>
      </div>
      `
      $('#type-app').html(content)

      // 根据规格ID获取规格选项（优先使用本地缓存）
      const getSpecOptionsBySpecId = (specId) => {
        if (data.spec_options && data.spec_options[specId]) {
          return data.spec_options[specId]
        }
        return []
      }
      const renderContent = (type) => {
        switch (type) {
          case '4':
          case 4:
            renderDownload()
            break
          case '1':
          case 1:
            renderGeneral()
            break
          default:
            renderGeneral()
            break
        }
      }

      const renderDownload = () => {
        skuApp.empty()
        const download = data.sku[0]
        const inputs = [
          {
            label: ts('originalPrice'),
            name: 'regular_price',
            type: 'number',
            value: download.regular_price ? download.regular_price : 0,
          },
          {
            label: ts('salePrice'),
            name: 'price',
            type: 'number',
            value: download.price ? download.price : 0,
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

        // 判断是否为新文章页面
        const isNewPost = newPost

        // 根据页面类型决定初始数据
        let initialSku = []
        let initialSpecCount = 1

        if (!isNewPost && data && data.skus && data.skus.length > 0) {
          // 编辑页面：使用已有数据
          initialSku = [...data.skus]

          // 根据第一行SKU确定规格数量
          if (initialSku[0] && initialSku[0].specs && Array.isArray(initialSku[0].specs)) {
            // 最多2列
            initialSpecCount = Math.min(initialSku[0].specs.length, 2)
          }
        } else {
          // 新建页面：初始化空数据
          initialSku = [
            {
              id: `new_${Date.now()}`,
              specs: Array(initialSpecCount)
                .fill(null)
                .map(() => ({
                  spec_id: '',
                  option_id: '',
                  spec_name: '',
                  option_name: '',
                })),
              regular_price: '',
              price: '',
              weight: '',
              unit: '',
              stock: '',
              sold: '',
              type: 1,
            },
          ]
        }

        // 使用响应式数据管理状态
        const state = reactive({
          sku: initialSku,
          specCount: initialSpecCount,
          specs: data.specs || [], // 总是使用data.specs，即使是新页面
        })

        // 获取已选择的规格ID列表
        const getSelectedSpecIds = () => {
          const selectedIds = []
          for (let i = 0; i < state.specCount; i++) {
            const $select = $(`.spec-name-select[data-col-index="${i}"]`)
            const specId = $select.val()
            if (specId) {
              selectedIds.push(specId)
            }
          }
          return selectedIds
        }

        // 更新规格名称选择器的可用选项
        const updateSpecNameSelectors = () => {
          const selectedSpecIds = getSelectedSpecIds()

          for (let colIndex = 0; colIndex < state.specCount; colIndex++) {
            const $select = $(`.spec-name-select[data-col-index="${colIndex}"]`)
            const currentValue = $select.val()

            // 重新填充选项
            $select.find('option:not(:first)').remove() // 保留第一个默认选项

            if (state.specs && Array.isArray(state.specs)) {
              for (const spec of state.specs) {
                // 检查是否应该禁用此选项
                const shouldDisable =
                  selectedSpecIds.includes(spec.id.toString()) &&
                  spec.id.toString() !== currentValue
                const selected = currentValue === spec.id.toString() ? 'selected' : ''
                const disabled = shouldDisable ? 'disabled' : ''

                $select.append(
                  `<option value="${spec.id}" ${selected} ${disabled}>${spec.name}</option>`,
                )
              }
            }
          }
        }

        // 更新规格选项的辅助函数
        const updateSpecOptions = (colIndex, selectedSpecId) => {
          // 更新状态中的规格ID
          for (let i = 0; i < state.sku.length; i++) {
            if (!Array.isArray(state.sku[i].specs)) {
              state.sku[i].specs = []
            }
            if (!state.sku[i].specs[colIndex]) {
              state.sku[i].specs[colIndex] = {
                spec_id: '',
                option_id: '',
                spec_name: '',
                option_name: '',
              }
            }
            state.sku[i].specs[colIndex].spec_id = selectedSpecId
            state.sku[i].specs[colIndex].option_id = '' // 重置选项ID
          }

          // 更新DOM中的规格选项
          $(`.spec-option-select[data-col-index="${colIndex}"]`).each(function () {
            const $select = $(this)
            const rowIndex = parseInt($select.data('row-index'), 10)
            const skuId =
              state.sku[rowIndex] && state.sku[rowIndex].id
                ? state.sku[rowIndex].id
                : `new_${rowIndex}`

            // 更新隐藏字段
            const $hiddenField = $select.siblings('input[type="hidden"]')
            if ($hiddenField.length) {
              $hiddenField
                .val(selectedSpecId)
                .attr('name', `g3_sku[${skuId}][specs][${selectedSpecId}][spec_id]`)
            }

            // 更新select的name属性
            $select.attr('name', `g3_sku[${skuId}][specs][${selectedSpecId}][option_id]`)

            // 清空并重新填充选项
            $select.empty().append(`<option value="">${ts('specValue')}</option>`)
            $select.prop('disabled', true)

            if (selectedSpecId) {
              // 使用缓存数据或异步加载
              const cachedOptions = getSpecOptionsBySpecId(parseInt(selectedSpecId, 10))
              if (cachedOptions.length > 0) {
                for (const option of cachedOptions) {
                  $select.append(`<option value="${option.id}">${escapeHtml(option.name)}</option>`)
                }
                $select.prop('disabled', false)
              } else {
                // 异步加载
                $.post(ajaxurl, {
                  action: 'g3_get_spec_options',
                  spec_id: selectedSpecId,
                  nonce: productData.nonce,
                })
                  .done((res) => {
                    if (res.success) {
                      const options = res.data.options
                      $select.empty().append(`<option value="">${ts('specValue')}</option>`)
                      for (const option of options) {
                        $select.append(
                          `<option value="${option.id}">${escapeHtml(option.name)}</option>`,
                        )
                      }
                    }
                    $select.prop('disabled', false)
                  })
                  .fail(() => {
                    $select.prop('disabled', false)
                  })
              }
            } else {
              $select.prop('disabled', false)
            }
          })

          // 更新规格名称选择器的可用选项
          updateSpecNameSelectors()
        }

        // 渲染单行SKU的函数
        const renderSkuRow = (rowIndex) => {
          const row = state.sku[rowIndex]
          const $row = $('<tr></tr>')

          // 渲染规格值列
          for (let colIndex = 0; colIndex < state.specCount; colIndex++) {
            let optionId = ''
            let specId = ''
            const skuId = row.id || `new_${rowIndex}`

            if (row.specs && Array.isArray(row.specs) && row.specs[colIndex]) {
              optionId = row.specs[colIndex].option_id || ''
              specId = row.specs[colIndex].spec_id || ''
            }

            // 创建规格选项下拉框
            let optionSelectHtml = `
            <input type="hidden" name="g3_sku[${skuId}][specs][${specId}][spec_id]" value="${specId}">
            <select class="j-select spec-option-select" 
              name="g3_sku[${skuId}][specs][${specId}][option_id]"
              data-row-index="${rowIndex}"
              data-col-index="${colIndex}">
              <option value="">${ts('specValue')}</option>
          `

            // 如果已知规格ID，加载该规格的选项
            if (specId) {
              const specOptions = getSpecOptionsBySpecId(parseInt(specId, 10))
              if (Array.isArray(specOptions) && specOptions.length > 0) {
                for (const option of specOptions) {
                  const selected = option.id === optionId ? 'selected' : ''
                  optionSelectHtml += `<option value="${option.id}" ${selected}>${escapeHtml(option.name)}</option>`
                }
              }
            }

            optionSelectHtml += '</select>'

            if (colIndex === 0) {
              optionSelectHtml +=
                '<span class="el-addon remove-row"><span class="is-text icon-close"></span></span>'
            }
            $row.append(`<td><div class="input-group">${optionSelectHtml}</div></td>`)
          }

          // 渲染固定字段列
          const fields = ['regular_price', 'price', 'weight', 'unit', 'stock', 'sold']
          const fixedHeaders = [
            ts('originalPrice'),
            ts('salePrice'),
            `${ts('weight')}`,
            ts('unit'),
            ts('stock'),
            ts('quantitySold'),
          ]

          for (let i = 0; i < fields.length; i++) {
            const field = fields[i]
            const header = fixedHeaders[i]
            const value = typeof row[field] !== 'undefined' && row[field] != null ? row[field] : ''
            const skuId = row.id || `new_${rowIndex}`
            // 根据字段动态设置 input 类型
            const inputType = field === 'unit' ? 'text' : 'number'

            $row.append(`
            <td>
              <div class="input-inner-group">
                <input class="j-input sku-field-input"
                  type="${inputType}"
                  name="g3_sku[${skuId}][${field}]"
                  value="${escapeHtml(value)}"
                  data-row-index="${rowIndex}"
                  data-field="${field}"
                  placeholder="${header}">
              </div>
            </td>
          `)
          }

          return $row
        }

        // 渲染表格头部
        const renderTableHeader = () => {
          const $tr = $('<tr></tr>')
          const fixedHeaders = [
            ts('originalPrice'),
            ts('salePrice'),
            `${ts('weight')}`,
            ts('unit'),
            ts('stock'),
            ts('quantitySold'),
          ]

          // 获取已选择的规格ID列表
          const selectedSpecIds = getSelectedSpecIds()

          // 根据specCount创建对应数量的规格列
          for (let idx = 0; idx < state.specCount; idx++) {
            const $th = $('<th></th>')

            if (idx === 0) {
              // 第一列使用下拉选择框
              const $group = $(`<div class="input-group" data-index="${idx}"></div>`)
              const $select = $(`
              <select class="j-select spec-name-select"
                name="g3_product_specs[${idx}]"
                data-col-index="${idx}">
                <option value="">${ts('spec')} ${idx + 1}</option>
              </select>
            `)

              // 添加规格选项
              if (state.specs && Array.isArray(state.specs)) {
                for (const spec of state.specs) {
                  // 查找当前已选的规格
                  const currentSpecId =
                    state.sku[0] && state.sku[0].specs && state.sku[0].specs[idx]
                      ? state.sku[0].specs[idx].spec_id
                      : undefined
                  const selected = currentSpecId === spec.id.toString() ? 'selected' : ''
                  // 第一列不需要禁用任何选项
                  $select.append(`<option value="${spec.id}" ${selected}>${spec.name}</option>`)
                }
              }
              $group.append($select)
              $th.append($group)
            } else {
              // 其他列也使用下拉选择框，并添加删除按钮
              const $group = $(`<div class="input-group" data-index="${idx}"></div>`)
              const $select = $(`
              <select class="j-select spec-name-select"
                name="g3_product_specs[${idx}]"
                data-col-index="${idx}">
                <option value="">${ts('spec')} ${idx + 1}</option>
              </select>
            `)

              // 添加规格选项
              if (state.specs && Array.isArray(state.specs)) {
                for (const spec of state.specs) {
                  // 查找当前已选的规格
                  const currentSpecId =
                    state.sku[0] && state.sku[0].specs && state.sku[0].specs[idx]
                      ? state.sku[0].specs[idx].spec_id
                      : undefined
                  const selected = currentSpecId === spec.id.toString() ? 'selected' : ''
                  // 检查是否应该禁用此选项（已被其他列选择）
                  const shouldDisable =
                    selectedSpecIds.includes(spec.id.toString()) &&
                    spec.id.toString() !== currentSpecId
                  const disabled = shouldDisable ? 'disabled' : ''
                  $select.append(
                    `<option value="${spec.id}" ${selected} ${disabled}>${spec.name}</option>`,
                  )
                }
              }

              const $del = $(
                `<span class="el-addon remove-spec-col"><span class="is-text icon-close"></span></span>`,
              )
              $group.append($select).append($del)
              $th.append($group)
            }
            $tr.append($th)
          }

          for (const h of fixedHeaders) {
            $tr.append(`<th>${escapeHtml(h)}</th>`)
          }

          return $tr
        }

        // 完整渲染表格
        const renderTable = () => {
          skuApp.empty()

          // === 1. 创建容器 ===
          const $wrapper = $(
            '<div class="table-wrapper" style="overflow-x: auto; margin-top: 16px;"></div>',
          )
          const $table = $('<table class="j-table sku-table"></table>')
          const $thead = $('<thead></thead>')
          const $tbody = $('<tbody></tbody>')

          // === 2. 渲染表头 ===
          $thead.append(renderTableHeader())
          $table.append($thead)

          // === 3. 渲染表体 ===
          for (let rowIndex = 0; rowIndex < state.sku.length; rowIndex++) {
            $tbody.append(renderSkuRow(rowIndex))
          }
          $table.append($tbody)
          $wrapper.append($table)
          skuApp.append($wrapper)

          // === 4. 操作栏 ===
          const $actionBar = $(`
          <div style="margin-top: 12px;">
            <button type="button" class="j-button is-outline is-sm add-spec-item">+ ${ts('addSpecItem')}</button>
            <button type="button" class="j-button is-secondary is-sm add-spec-col" ${state.specCount >= 2 ? 'disabled' : ''}>+ ${ts('addSpec')}</button>
          </div>
        `)
          skuApp.append($actionBar)
        }

        // 初始渲染
        renderTable()

        // === 5. 事件绑定 ===
        // 清除旧事件
        skuApp.off('change', '.spec-name-select')
        skuApp.off('change', '.spec-option-select')
        skuApp.off('input', '.sku-field-input')
        skuApp.off('click', '.add-spec-col')
        skuApp.off('click', '.remove-spec-col')
        skuApp.off('click', '.add-spec-item')
        skuApp.off('click', '.remove-row')

        // 监听规格名称变化
        skuApp.on('change', '.spec-name-select', function () {
          const colIndex = parseInt($(this).data('col-index'), 10)
          const selectedSpecId = $(this).val()
          updateSpecOptions(colIndex, selectedSpecId)
        })

        // 监听规格选项变化
        skuApp.on('change', '.spec-option-select', function () {
          const $select = $(this)
          const rowIndex = parseInt($select.data('row-index'), 10)
          const colIndex = parseInt($select.data('col-index'), 10)
          const selectedOptionId = $select.val()

          // 更新状态
          if (
            state.sku[rowIndex] &&
            state.sku[rowIndex].specs &&
            state.sku[rowIndex].specs[colIndex]
          ) {
            state.sku[rowIndex].specs[colIndex].option_id = selectedOptionId
          }
        })

        // 监听字段输入变化
        skuApp.on('input', '.sku-field-input', function () {
          const $input = $(this)
          const rowIndex = parseInt($input.data('row-index'), 10)
          const field = $input.data('field')
          const value = $input.val()

          // 更新状态
          if (state.sku[rowIndex]) {
            state.sku[rowIndex][field] = value
          }
        })

        // 添加规格列
        skuApp.on('click', '.add-spec-col', () => {
          if (state.specCount < 2) {
            // 为所有SKU行添加新的规格占位符
            for (let i = 0; i < state.sku.length; i++) {
              if (!Array.isArray(state.sku[i].specs)) {
                state.sku[i].specs = []
              }
              state.sku[i].specs.push({
                spec_id: '',
                option_id: '',
                spec_name: '',
                option_name: '',
              })
            }
            state.specCount++
            renderTable() // 重新渲染整个表格以反映新增的列
            // 渲染完成后更新规格名称选择器的禁用状态
            setTimeout(() => {
              updateSpecNameSelectors()
            }, 0)
          } else {
            toast.error(ts('maximumTwoSpecsAllowed'))
          }
        })

        // 删除规格列
        skuApp.on('click', '.remove-spec-col', function () {
          const index = parseInt($(this).closest('.input-group').data('index'), 10)
          if (confirm(ts('toDelete'))) {
            // 从所有SKU行中删除对应规格
            for (let i = 0; i < state.sku.length; i++) {
              if (Array.isArray(state.sku[i].specs) && state.sku[i].specs.length > index) {
                state.sku[i].specs.splice(index, 1)
              }
            }
            state.specCount--
            renderTable() // 重新渲染整个表格以反映删除的列
            // 渲染完成后更新规格名称选择器的禁用状态
            setTimeout(() => {
              updateSpecNameSelectors()
            }, 0)
          }
        })

        // 添加SKU行
        skuApp.on('click', '.add-spec-item', () => {
          const newSpecs = Array(state.specCount)
            .fill(null)
            .map((_, colIndex) => {
              // 获取当前列选中的规格ID
              const $specSelect = $(`.spec-name-select[data-col-index="${colIndex}"]`)
              const specId = $specSelect.val() || ''
              return {
                spec_id: specId,
                option_id: '',
                spec_name: '',
                option_name: '',
              }
            })

          const newSku = {
            id: `new_${Date.now()}`,
            specs: newSpecs,
            regular_price: '',
            price: '',
            weight: '',
            unit: '',
            stock: '',
            sold: '',
            type: 1,
          }

          state.sku.push(newSku)

          // 重新渲染整个表格以确保新行正确加载规格选项
          renderTable()
        })

        // 删除SKU行
        skuApp.on('click', '.remove-row', function () {
          if (state.sku.length <= 1) {
            toast.info(ts('atLeastOneRowRequired'))
            return
          }

          const $row = $(this).closest('tr')
          const rowIndex = $row.index()

          if (confirm(ts('toDelete'))) {
            state.sku.splice(rowIndex, 1)
            $row.fadeOut(300, function () {
              $(this).remove()
              // 更新剩余行的索引
              skuApp.find('tbody tr').each(function (index) {
                $(this).find('[data-row-index]').attr('data-row-index', index)
              })
            })
          }
        })
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
    }
  }

  /**
   * Property
   */
  const onPropApp = () => {
    if (propApp.length > 0) {
      const renderPropItem = (item, key, indexOrHash, labelIndex) => {
        return `
        <div class="prop-item">
          <div class="grid-container grid-col-xl-4 grid-col-lg-3 grid-col-md-2 grid-col-sm-2 grid-col-1">
            <div class="input-group">
              <span class="el-addon"><span class="is-text">${ts('property')} ${labelIndex}</span></span>
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
      if (property && Array.isArray(property.props)) {
        property.props.forEach((item, index) => {
          container.append(renderPropItem(item, property.key, index, index + 1))
          count++
        })
      }

      propApp.empty().append(container)
      if (container.children().length === 0) {
        propApp.css('height', '120px')
      }
      propApp.prepend(loadingBlock)
      setTimeout(() => {
        propApp.find('.j-loading').remove()
        propApp.css('height', 'auto')
      }, 1000)

      // 添加“+”按钮
      const addButton = $(
        `<div><button type="button" class="j-button is-outline is-sm add-property">+ ${ts('addProperty')}</button></div>`,
      )
      propApp.append(addButton)

      // 事件处理
      $(document).on('click', '.add-property', () => {
        const hash = randomId()
        const html = renderPropItem({ name: '', value: '' }, property.key, hash, count + 1)
        $(html).hide().appendTo(container).slideDown(300)
        count++
      })
      $(document).on('click', '.remove-property', function (e) {
        e.preventDefault()
        if (confirm(ts('toDelete'))) {
          $(this)
            .closest('.prop-item')
            .fadeOut(300, function () {
              $(this).remove()
              count--
            })
        }
      })
    }
  }

  // Run
  onTypeApp()
  onGalleryApp()
  onPropApp()
})
