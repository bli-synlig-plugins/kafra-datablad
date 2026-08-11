(function ($) {
	'use strict';

	if (!window.kdbAdmin) return;

	var cfg = window.kdbAdmin;
	var searchTimer = null;

	function initPdf() {
		var root = document.querySelector('[data-kdb-pdf]');
		if (!root) return;

		var input = root.querySelector('[data-kdb-pdf-id]');
		var preview = root.querySelector('[data-kdb-pdf-preview]');
		var nameEl = root.querySelector('[data-kdb-pdf-name]');
		var linkEl = root.querySelector('[data-kdb-pdf-link]');
		var selectBtn = root.querySelector('[data-kdb-select-pdf]');
		var removeBtn = root.querySelector('[data-kdb-remove-pdf]');
		var frame;

		selectBtn.addEventListener('click', function (e) {
			e.preventDefault();
			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: cfg.i18n.selectPdf,
				button: { text: cfg.i18n.selectPdf },
				library: { type: 'application/pdf' },
				multiple: false,
			});

			frame.on('select', function () {
				var file = frame.state().get('selection').first().toJSON();
				input.value = file.id;
				nameEl.textContent = file.filename || file.title || 'PDF';
				linkEl.href = file.url;
				linkEl.hidden = false;
				preview.hidden = false;
				removeBtn.hidden = false;
				selectBtn.textContent = cfg.i18n.changePdf;
			});

			frame.open();
		});

		removeBtn.addEventListener('click', function (e) {
			e.preventDefault();
			input.value = '';
			nameEl.textContent = '';
			linkEl.removeAttribute('href');
			linkEl.hidden = true;
			preview.hidden = true;
			removeBtn.hidden = true;
			selectBtn.textContent = cfg.i18n.selectPdf;
		});
	}

	function initProducts() {
		var root = document.querySelector('[data-kdb-products]');
		if (!root) return;

		var search = root.querySelector('[data-kdb-product-search]');
		var results = root.querySelector('[data-kdb-product-results]');
		var selected = root.querySelector('[data-kdb-product-selected]');

		function selectedIds() {
			return Array.prototype.map.call(
				selected.querySelectorAll('[data-product-id]'),
				function (el) {
					return String(el.getAttribute('data-product-id'));
				}
			);
		}

		function addProduct(item) {
			if (selectedIds().indexOf(String(item.id)) !== -1) {
				return;
			}

			var li = document.createElement('li');
			li.setAttribute('data-product-id', String(item.id));
			li.innerHTML =
				'<input type="hidden" name="kdb_product_ids[]" value="' + String(item.id) + '">' +
				'<span></span>' +
				'<button type="button" class="button-link-delete" data-kdb-remove-product>' +
				cfg.i18n.remove +
				'</button>';
			li.querySelector('span').textContent = item.name;
			selected.appendChild(li);
		}

		search.addEventListener('input', function () {
			clearTimeout(searchTimer);
			var q = search.value.trim();
			if (q.length < 2) {
				results.hidden = true;
				results.innerHTML = '';
				return;
			}

			searchTimer = setTimeout(function () {
				$.getJSON(cfg.ajaxUrl, {
					action: 'kdb_search_products',
					nonce: cfg.nonce,
					q: q,
				}).done(function (json) {
					var items = (json && json.success && json.data && json.data.items) || [];
					results.innerHTML = '';
					if (!items.length) {
						results.innerHTML = '<div class="kdb-products__empty">' + cfg.i18n.noProducts + '</div>';
						results.hidden = false;
						return;
					}

					items.forEach(function (item) {
						if (selectedIds().indexOf(String(item.id)) !== -1) return;
						var btn = document.createElement('button');
						btn.type = 'button';
						btn.className = 'kdb-products__result';
						btn.textContent = item.sku ? item.name + ' (' + item.sku + ')' : item.name;
						btn.addEventListener('click', function () {
							addProduct(item);
							results.hidden = true;
							results.innerHTML = '';
							search.value = '';
						});
						results.appendChild(btn);
					});
					results.hidden = false;
				});
			}, 250);
		});

		selected.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-kdb-remove-product]');
			if (!btn) return;
			var li = btn.closest('[data-product-id]');
			if (li) li.remove();
		});
	}

	$(function () {
		initPdf();
		initProducts();
	});
})(jQuery);
