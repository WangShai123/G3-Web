"use strict";
var $ = jQuery.noConflict();
jQuery(document).ready(function ($) {
	const lang = $('html').attr('lang').substring(0, 2);
	const texts = {
		en: {
			default: "Default",
			confirmToDelete: "Are you sure to delete?",
			property: "Property",
			enterPropertyName: "Enter property name",
			enterPropertyValue: "Enter property value",
			remove: "Remove",
		},
		zh: {
			default: "默认",
			confirmToDelete: "确定要刪除吗？",
			property: "属性",
			enterPropertyName: "请输入属性名称",
			enterPropertyValue: "请输入属性值",
			remove: "移除",
		}
	};

	/**
	 * check path
	 * @param {string} path
	 * @returns {boolean} true if path is matched
	 */
	const isPath = (path) => {
		return window.location.pathname === path;
	}

	const hash = () => {
		return Math.random().toString(16).slice(-6);
	}

	/**
	 * product edit page
	 */
	if (isPath("/wp-admin/post.php") || isPath("/wp-admin/post-new.php")) { 
		$('html').addClass('j-theme-indigo j-radius-sm');
		/**
		 * product gallery swiper
		 */
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
						const slide =
							`<div class="swiper-slide">
								<div class="gallery-item" data-url="${url}">
									${url.match(/\.(mp4|webm|ogg)$/i)
								? `<video src="${url}" controls></video>`
								: `<img src="${url}">`
							}
									<button class="button is-icon icon-error action-removeGalleryItem" type="button"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="none" d="M0 0h24v24H0z"></path><path d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4149L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z"></path></svg></button>
									<input type="hidden" name="g3_product_gallery[]" value="${url}">
								</div>
							</div>`;
						gallerySwiper.appendSlide(slide);
						gallerySwiper.update();
					});
				});
				frame.open();
			});
			// remove from product gallery
			$(document).on('click', '.action-removeGalleryItem', function () {
				if (confirm(texts[lang].confirmToDelete)) {
					$(this).closest('.swiper-slide').fadeOut(300, function () {
						$(this).remove();
						gallerySwiper.update();
					});
				}
			});
		}
		// add to product properties
		if ($('#g3-metabox-properties').length > 0) {
			const container = $('.properties-container');
			let count = 0;
			let _hash = hash();
			count = container.find('.property-item').length;
			$('#add-property').on('click', function () {
				const template =
					`<div class="property-item">
						<div class="property-label">${texts[lang].property} ${count + 1}</div>
						<div class="property-control">
							<input type="text" name="g3_product_properties[${_hash}][name]" id="g3_product_properties[${_hash}][name]" value="" placeholder="${texts[lang].enterPropertyName}">
							<input type="text" name="g3_product_properties[${_hash}][value]" id="g3_product_properties[${_hash}][value]" value="" placeholder="${texts[lang].enterPropertyValue}">
							<div class="property-actions">
								<button type="button" class="button button-error action-removeProperty">${texts[lang].remove}</button>
							</div>
						</div>
					</div>`;
				$(template).hide().appendTo(container).slideDown(300);
				count++;
				// reset _hash
				_hash = hash();
			});
			// delete from product properties
			$(document).on('click', '.action-removeProperty', function () {
				if (confirm(texts[lang].confirmToDelete)) {
					$(this).closest('.property-item').fadeOut(300, function () {
						$(this).remove();
					});
					count--;
				}
			});
		}
		// add to product sku
		if ($('#g3-metabox-sku').length > 0) {
			const addSku = $('#add-sku');
			let count = 0;
			addSku.on('click', function () {
				count = skuTabs.tabs.length;
				let newName = 'sku-' + (count + 1);
				skuTabs.addTab(
					{ title: newName, content: '这里是选项' + newName +'的内容', name: newName }
				);
				skuTabs.activate(count);
				count++;
			});
			$(document).on('click', '#delete-sku', function () {
				if (confirm(texts[lang].confirmToDelete)) {
					skuTabs.deleteTab(skuTabs.current);
					count--;
				}
			});
		}
	}
});