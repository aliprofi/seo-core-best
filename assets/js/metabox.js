/**
 * SEO Core Best Metabox Live Preview & Counters
 */
jQuery(document).ready(function ($) {
	var $titleInput = $('#seo_core_title');
	var $descInput = $('#seo_core_description');
	var $previewTitle = $('#seo-preview-title');
	var $previewDesc = $('#seo-preview-desc');
	var $titleCount = $('#seo-title-count');
	var $descCount = $('#seo-desc-count');

	function updateTitle() {
		var val = $titleInput.val().trim();
		$titleCount.text(val.length);

		if (val.length > 60) {
			$titleCount.parent().addClass('counter-warning');
		} else {
			$titleCount.parent().removeClass('counter-warning');
		}

		if (val) {
			$previewTitle.text(val);
		}
	}

	function updateDesc() {
		var val = $descInput.val().trim();
		$descCount.text(val.length);

		if (val.length > 160) {
			$descCount.parent().addClass('counter-warning');
		} else {
			$descCount.parent().removeClass('counter-warning');
		}

		if (val) {
			$previewDesc.text(val);
		}
	}

	$titleInput.on('input change', updateTitle);
	$descInput.on('input change', updateDesc);

	// Quick tag insertion buttons
	$('.seo-tag-insert').on('click', function (e) {
		e.preventDefault();
		var tag = $(this).data('tag');
		var current = $titleInput.val();
		$titleInput.val(current ? current + ' ' + tag : tag).trigger('input');
		$titleInput.focus();
	});

	// AI Generation for Title & Description
	$('.seo-ai-gen-meta').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var postId = $btn.data('post-id');
		var origText = $btn.text();

		if (typeof seoCoreAI === 'undefined' || !seoCoreAI.ajaxurl) {
			alert('AI модуль не инициализирован. Проверьте настройки API.');
			return;
		}

		$btn.prop('disabled', true).text('⏳ AI думает...');

		$.post(seoCoreAI.ajaxurl, {
			action: 'seo_core_ai_generate_meta',
			nonce: seoCoreAI.nonce,
			post_id: postId
		}, function(res) {
			$btn.prop('disabled', false).text(origText);
			if (res.success && res.data) {
				if (res.data.title) {
					$titleInput.val(res.data.title).trigger('input');
				}
				if (res.data.description) {
					$descInput.val(res.data.description).trigger('input');
				}
			} else {
				alert(res.data && res.data.message ? res.data.message : 'Ошибка генерации');
			}
		}).fail(function() {
			$btn.prop('disabled', false).text(origText);
			alert('Ошибка связи с сервером при обращении к AI');
		});
	});

	// AI Generation for Image Alts in post
	$('#seo-btn-gen-alts').on('click', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var postId = $btn.data('post-id');
		var $status = $('#seo-ai-alts-status');
		var origText = $btn.text();

		if (typeof seoCoreAI === 'undefined' || !seoCoreAI.ajaxurl) {
			alert('AI модуль не инициализирован. Проверьте настройки API.');
			return;
		}

		$btn.prop('disabled', true).text('⏳ AI анализирует картинки...');
		$status.html('<span style="color:#64748b;">Нейросеть генерирует описания...</span>');

		$.post(seoCoreAI.ajaxurl, {
			action: 'seo_core_ai_generate_alts',
			nonce: seoCoreAI.nonce,
			post_id: postId
		}, function(res) {
			$btn.prop('disabled', false).text(origText);
			if (res.success && res.data) {
				$status.html('<span style="color:#166534; font-weight:600;">✔ ' + res.data.message + '</span>');
			} else {
				$status.html('<span style="color:#b91c1c;">✕ ' + (res.data && res.data.message ? res.data.message : 'Ошибка генерации') + '</span>');
			}
		}).fail(function() {
			$btn.prop('disabled', false).text(origText);
			$status.html('<span style="color:#b91c1c;">✕ Ошибка запроса к серверу</span>');
		});
	});
});
