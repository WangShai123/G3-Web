"use strict";

jQuery(document).ready(function ($) {
    if (!productData) return

    const { spec, prop, gallery } = productData.data
	const {randomId, t, escapeHtml} = jui.u
	const skuApp = $('#sku-app')
	const galleryApp = $('#gallery-app')
	const propApp = $('#prop-app')

	const texts = {
		en: {
			default: "Default",
			confirmToDelete: "Are you sure to delete?",
			property: "Property",
			name: "Name",
			value: "Value",
			enterPropertyName: "Enter property name",
			enterPropertyValue: "Enter property value",
			remove: "Remove",
			addProperty: "Add Property",
            singleSpec: "Single Specification",
            multipleSpec: "Multiple Specification",
            originalPrice: 'Original Price',
            salePrice: 'Sale price',
            weight: 'Weight',
            stock: 'Stock',
            quantitySold: 'Quantity Sold',
			setGallery: "Set Gallery",
		},
		zh: {
			default: "默认",
			confirmToDelete: "确定要刪除吗？",
			property: "属性",
			name: "名称",
			value: "值",
			enterPropertyName: "请输入属性名称",
			enterPropertyValue: "请输入属性值",
			remove: "移除",
			addProperty: "添加属性",
            singleSpec: "单规格",
            multipleSpec: "多规格",
            originalPrice: '原价',
            salePrice: '售价',
            weight: '重量',
			kg: '千克',
            stock: '库存',
            quantitySold: '已售数量',
            add: '添加',
            addSpec: '添加规格',
            addSpecValue: '添加规格值',
            addSpecValueTip: '请输入规格值',
            addSpecTip: '请输入规格名称',
			setGallery: '设置图册',
		}
	};
	const ts = (key)=> {
		return t(key, texts);
	}

	/**
	 * check path
	 * @param {string} path
	 * @returns {boolean} true if path is matched
	 */
	const isPath = (path) => {
		return location.pathname === path;
	}

	// render gallery app
	const slider = (url) => {
		return `
			<div class="swiper-slide">
				<div class="gallery-item" data-url="${url}">
					${url.match(/\.(mp4|webm|ogg)$/i)
						? `<video src="${url}" controls></video>`
						: `<img src="${url}">`
					}
					<div class="action-removeGalleryItem"><span class="icon-close"></span></div>
					<input type="hidden" name="${gallery.key}[]" value="${url}">
				</div>
			</div>`
	}
	const renderGallery = (gallery) => {
		let slidesHtml = '';
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
			</p>`;
	}
	if(galleryApp) galleryApp.html(renderGallery(gallery));


	// register product gallery swiper
	let gallerySwiper;
	if ($("#g3-metabox-gallery").length > 0) {
		gallerySwiper = new Swiper("#g3GallerySwiper", {
			autoplay: true,
			slidesPerView: 1,
			spaceBetween: 8,
			pagination: {
				el: ".swiper-pagination",
				clickable: true
			}
		});
	}
	// add to product gallery
	if ($('#action-addToGallery').length > 0) { 
		$('#action-addToGallery').on('click', function () {
			const frame = wp.media({
				multiple: true,
				library: { type: ['image', 'video'] }
			});
			frame.on('select', function () {
				const selection = frame.state().get('selection');
				selection.each(function (attachment) {
					const url = attachment.attributes.url;
					const slide = slider(url)
					gallerySwiper.appendSlide(slide);
					gallerySwiper.update();
				});
			});
			frame.open();
		});
		// remove from product gallery
		$(document).on('click', '.action-removeGalleryItem', function () {
			if (confirm(ts('confirmToDelete'))) {
				$(this).closest('.swiper-slide').fadeOut(300, function () {
					$(this).remove();
					gallerySwiper.update();
				});
			}
		});
	}


	/**
	 * product specification
	 */
	if ($('#specification-app').length > 0) {
const content = `
<div class="specification-container">
    <div class="j-radio is-group">
        <label class="radio-label">
            <input type="radio" class="j-input" name="g3-specification" value="single">
            <span class="radio-text">${ts('singleSpec')}</span>
        </label>
        <label class="radio-label">
            <input type="radio" class="j-input" name="g3-specification" value="multiple">
            <span class="radio-text">${ts('multipleSpec')}</span>
        </label>
    </div>
    <div id="specification-content"></div>
</div>
`
		$('#specification-app').html(content);
	}

	const renderContent = (spec) => { 
		switch (spec) {
			case 'single':
				renderSingle();
				break;
			case 'multiple':
				renderMultiple();
				break;
		}
	}

	const renderSingle = () => {
		const inputs = [
			{
				label: ts('originalPrice'),
				name: 'originalPrice',
				type: 'number',
				value: spec.originalPrice ? spec.originalPrice : 0
			},
			{
				label: ts('salePrice'),
				name: 'salePrice',
				type: 'number',
				value: spec.salePrice ? spec.salePrice : 0
			},
			{
				label: ts('weight'),
				name: 'weight',
				type: 'number',
				value: spec.weight ? spec.weight : 0,
				unit: ts('kg')
			},
			{
				label: ts('stock'),
				name: 'stock',
				type: 'number',
				value: spec.stock ? spec.stock : 0
			},
			{
				label: ts('quantitySold'),
				name: 'sold',
				type: 'number',
				value: spec.sold ? spec.sold : 0
			},
		];
		let html = '<div class="specification-inputs">';
		inputs.forEach(input => {
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
				${input.unit ? '<div class="el-addon"><div class="is-text">' + input.unit + '</div></div>' : ''}
			</div></div>
			`;
		});
		html += '</div>';
		skuApp.html(html);
	};
	const renderMultiple = () => {
		skuApp.html('');
	}

	const radio = $('.j-input[name="g3-specification"]');
	if(radio.length > 0) {
		radio.filter(`[value="${spec.type}"]`).prop('checked', true);
		renderContent(spec.type);
		radio.on('change', function () {
			renderContent(this.value)
		});
	}

	/**
	 * Property
	 */
	const renderPropApp = (prop, container) => {
		
		if (prop && prop.items && Array.isArray(prop.items) && prop.items.length > 0) {
			for (const [index, item] of  Object.entries(prop.items)) {
				const html = `
					<div class="prop-item">
						<div>${ts('property')} ${+index +1}</div>
						<div class="grid-container grid-col-xl-4 grid-col-lg-3 grid-col-md-2 grid-col-sm-2 grid-col-1">
							<div class="input-group">
								<span class="el-addon"><span class="is-text">${ts('name')}</span></span>
								<input class="j-input" type="text" 
									name="${prop.key}[${index}][name]" 
									id="${prop.key}[${index}][name]" 
									value="${item.name ? escapeHtml(item.name) : ''}" 
									placeholder="${ts('enterPropertyName')}">
							</div>
							<div class="input-group">
								<span class="el-addon"><span class="is-text">${ts('value')}</span></span>
								<input type="text" class="j-input" 
									name="${prop.key}[${index}][value]"
									id="${prop.key}[${index}][value]"
									value="${item.value ? escapeHtml(item.value) : ''}" 
									placeholder="${ts('enterPropertyValue')}">
								<span class="el-addon"><button class="j-button is-danger" id="remove-property">${ts('remove')}</button></span>
							</div>
						</div>
					</div>
					`;
				container.append(html)
			}
		}
		const addElement = $('<div style="margin-top:8px"><button type="button" class="button" id="add-property">+ '+ts('addProperty')+'</button></div>')
		propApp.html(container)
		propApp.append(addElement)
	};
	if (propApp.length > 0) {
		const container = $('<div class="properties-container"></div>');
		renderPropApp(prop, container)
		let _hash = randomId();
		let count = container.find('.prop-item').length;
		$(document).on('click', '#add-property', function () {
			const template = `
				<div class="prop-item">
					<div>${ts('property')} ${count + 1}</div>
					<div class="grid-container grid-col-xl-4 grid-col-lg-3 grid-col-md-2 grid-col-sm-2 grid-col-1">
						<div class="input-group">
							<span class="el-addon"><span class="is-text">${ts('name')}</span></span>	
							<input class="j-input" type="text" name="${prop.key}[${_hash}][name]" id="${prop.key}[${_hash}][name]" value="" placeholder="${ts('enterPropertyName')}">
						</div>
						<div class="input-group">
							<span class="el-addon"><span class="is-text">${ts('value')}</span></span>
							<input class="j-input" type="text" name="${prop.key}[${_hash}][value]" id="${prop.key}[${_hash}][value]" value="" placeholder="${ts('enterPropertyValue')}">
							<span class="el-addon"><button class="j-button is-danger" id="remove-property">${ts('remove')}</button></span>
						</div>
					</div>
				</div>`;
			$(template).hide().appendTo(container).slideDown(300);
			count++;
			_hash = randomId();
		});
		$(document).on('click', '#remove-property', function (e) {
			e.preventDefault();
			if (confirm(ts('confirmToDelete'))) {
				$(this).closest('.prop-item').fadeOut(300, function () {
					$(this).remove();
				});
				// count--;
				count = container.find('.prop-item').length;
			}
		});
	}


});