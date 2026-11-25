/**
 * SEO Core Best Admin Scripts
 */
jQuery(document).ready(function ($) {
	// WordPress Media Uploader for Social Image & Logo
	$(document).on('click', '.seo-media-upload-btn', function (e) {
		e.preventDefault();
		var button = $(this);
		var targetInput = $(button.data('target'));
		var previewContainer = $(button.data('preview'));

		var customUploader = wp.media({
			title: button.data('title') || 'Выберите изображение',
			button: { text: 'Выбрать изображение' },
			multiple: false
		}).on('select', function () {
			var attachment = customUploader.state().get('selection').first().toJSON();
			targetInput.val(attachment.url).trigger('change');
			if (previewContainer.length) {
				previewContainer.html('<img src="' + attachment.url + '" style="max-width:280px; max-height:160px; object-fit:cover; border-radius:6px; margin-top:10px; border:1px solid #cbd5e1; display:block;" />');
			}
		}).open();
	});

	// Clear Media Button
	$(document).on('click', '.seo-media-clear-btn', function (e) {
		e.preventDefault();
		var button = $(this);
		var targetInput = $(button.data('target'));
		var previewContainer = $(button.data('preview'));

		targetInput.val('').trigger('change');
		if (previewContainer.length) {
			previewContainer.html('');
		}
	});
});
