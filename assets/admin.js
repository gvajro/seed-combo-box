jQuery(function ($) {
	'use strict';

	var $rows = $('#scb-rows');
	var rowTpl = $('#scb-row-tpl').html();
	var customTpl = $('#scb-custom-tpl').html();
	var $modal = $('#scb-modal');
	var current = null; // { $row, $item }
	var frame = null;

	function toggle() {
		var on = $('#_scb_enabled').is(':checked');
		$('.scb-options').toggle(on);
		$('._scb_box_cost_field').toggle($('#_scb_mode').val() === 'sum');
	}
	$('#_scb_enabled, #_scb_mode').on('change', toggle);
	toggle();

	// Righe prodotto.
	$('#scb-add-row').on('click', function (e) {
		e.preventDefault();
		var idx = Date.now();
		$rows.append(rowTpl.replace(/__I__/g, idx));
		$(document.body).trigger('wc-enhanced-select-init');
	});

	$rows.on('click', '.scb-remove', function (e) {
		e.preventDefault();
		if (window.confirm('Rimuovere questa riga?')) {
			$(this).closest('.scb-row').remove();
		}
	});

	$rows.sortable({ items: '.scb-row', handle: '.scb-handle', axis: 'y', cursor: 'move' });

	// Prodotti non in elenco (modale).
	function setImage(id, url) {
		$('#scb-m-img').data('id', id || '').data('url', url || '').html(url ? '<img src="' + url + '" alt="" />' : '');
	}

	function openModal($row, $item) {
		current = { $row: $row, $item: $item };
		$('#scb-m-title').val($item ? $item.find('.f-title').val() : '');
		$('#scb-m-desc').val($item ? $item.find('.f-desc').val() : '');
		$('#scb-m-price').val($item ? $item.find('.f-price').val() : '');
		if ($item) {
			var $img = $item.find('.f-image');
			setImage($img.val(), $img.attr('data-thumb'));
		} else {
			setImage('', '');
		}
		$modal.removeAttr('hidden');
		$('#scb-m-title').trigger('focus');
	}

	function closeModal() {
		$modal.attr('hidden', 'hidden');
		current = null;
	}

	function saveModal() {
		var title = $.trim($('#scb-m-title').val());
		if (!title) {
			window.alert('Inserisci il titolo del prodotto.');
			$('#scb-m-title').trigger('focus');
			return;
		}
		var $item = current.$item;
		if (!$item) {
			var rowIdx = current.$row.attr('data-index');
			var key = Date.now() + '' + Math.floor(Math.random() * 1000);
			$item = $(customTpl.replace(/__I__/g, rowIdx).replace(/__K__/g, key));
			$item.find('.f-id').val('c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6));
			current.$row.find('.scb-custom-list').append($item);
		}
		var price = $.trim($('#scb-m-price').val());
		var imgId = $('#scb-m-img').data('id') || '';
		var imgUrl = $('#scb-m-img').data('url') || '';

		$item.find('.f-title').val(title);
		$item.find('.f-desc').val($.trim($('#scb-m-desc').val()));
		$item.find('.f-price').val(price);
		$item.find('.f-image').val(imgId).attr('data-thumb', imgUrl);

		$item.find('.scb-custom-name').text(title);
		$item.find('.scb-custom-price').text(price ? price : '');
		$item.find('.scb-custom-thumb').html(imgUrl ? '<img src="' + imgUrl + '" alt="" />' : '');
		closeModal();
	}

	$rows.on('click', '.scb-add-custom', function (e) {
		e.preventDefault();
		openModal($(this).closest('.scb-row'), null);
	});
	$rows.on('click', '.scb-custom-edit', function (e) {
		e.preventDefault();
		var $item = $(this).closest('.scb-custom-item');
		openModal($item.closest('.scb-row'), $item);
	});
	$rows.on('click', '.scb-custom-del', function (e) {
		e.preventDefault();
		if (window.confirm('Rimuovere questo prodotto?')) {
			$(this).closest('.scb-custom-item').remove();
		}
	});

	$('#scb-m-save').on('click', saveModal);
	$('#scb-m-cancel').on('click', closeModal);
	$modal.on('click', function (e) {
		if (e.target === this) {
			closeModal();
		}
	});
	$modal.on('keydown', function (e) {
		if (e.key === 'Escape') {
			closeModal();
		} else if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') {
			e.preventDefault(); // evita l'invio del form prodotto
			saveModal();
		}
	});

	$('#scb-m-pick').on('click', function (e) {
		e.preventDefault();
		if (!frame) {
			frame = wp.media({
				title: 'Scegli la foto del prodotto',
				button: { text: 'Usa questa foto' },
				library: { type: 'image' },
				multiple: false
			});
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				var url = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
				setImage(a.id, url);
			});
		}
		frame.open();
	});
	$('#scb-m-clear').on('click', function (e) {
		e.preventDefault();
		setImage('', '');
	});
});
