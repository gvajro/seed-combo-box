(function () {
	'use strict';

	function init(root) {
		var cfg = JSON.parse(root.getAttribute('data-scb'));
		var rows = Array.prototype.slice.call(root.querySelectorAll('.scb-row'));
		var form = root.closest('form');
		var btn = form ? form.querySelector('.single_add_to_cart_button') : null;
		var msg = root.querySelector('.scb-msg');
		var totalEl = root.querySelector('.scb-total');
		var counterEl = root.querySelector('.scb-counter');
		var firstBad = null;

		function fmt(n) {
			var c = cfg.currency;
			var parts = Number(n).toFixed(c.decimals).split('.');
			parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thouSep);
			return c.format.replace('%1$s', c.symbol).replace('%2$s', parts.join(c.decSep));
		}

		function priceOf(item) {
			var sel = item.querySelector('.scb-var');
			if (sel && sel.options.length) {
				return parseFloat(sel.options[sel.selectedIndex].getAttribute('data-price')) || 0;
			}
			return parseFloat(item.getAttribute('data-price')) || 0;
		}

		function syncItem(item) {
			var chk = item.querySelector('.scb-check');
			var sel = item.querySelector('.scb-var');
			if (sel) {
				chk.value = item.getAttribute('data-pid') + ':' + sel.value;
			}
			var pe = item.querySelector('.scb-price');
			if (pe) {
				pe.textContent = fmt(priceOf(item));
			}
		}

		function openRow(i) {
			if (!rows[i]) {
				return;
			}
			rows.forEach(function (r, k) {
				r.classList.toggle('is-open', k === i);
				r.querySelector('.scb-row-head').setAttribute('aria-expanded', k === i ? 'true' : 'false');
			});
			var top = rows[i].getBoundingClientRect().top;
			if (top < 0 || top > window.innerHeight * 0.5) {
				rows[i].scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		}

		function update() {
			var total = 0;
			var sum = cfg.boxCost;
			firstBad = null;

			rows.forEach(function (row) {
				var checked = row.querySelectorAll('.scb-check:checked');
				row._count = checked.length;
				total += checked.length;
				Array.prototype.forEach.call(checked, function (chk) {
					sum += priceOf(chk.closest('.scb-item'));
				});
			});

			var capTotal = cfg.totalMax && total >= cfg.totalMax;
			var valid = true;

			rows.forEach(function (row) {
				var min = +row.getAttribute('data-min');
				var max = +row.getAttribute('data-max');
				var c = row._count;
				var capped = c >= max || capTotal;

				Array.prototype.forEach.call(row.querySelectorAll('.scb-check'), function (chk) {
					var item = chk.closest('.scb-item');
					if (!chk.checked) {
						chk.disabled = capped;
					}
					item.classList.toggle('is-checked', chk.checked);
					item.classList.toggle('is-capped', !chk.checked && capped);
				});

				row.querySelector('.scb-row-status').textContent = c + ' / ' + max;
				var ok = c >= min;
				row.classList.toggle('is-done', c > 0 && ok);
				var next = row.querySelector('.scb-next');
				if (next) {
					next.disabled = !ok;
				}
				if (!ok) {
					valid = false;
					if (!firstBad) {
						firstBad = row;
					}
				}
			});

			var need = cfg.totalMin || 1;
			if (total < need) {
				valid = false;
			}

			var rule = '';
			if (cfg.totalMin && cfg.totalMax) {
				rule = ' (da ' + cfg.totalMin + ' a ' + cfg.totalMax + ')';
			} else if (cfg.totalMin) {
				rule = ' (minimo ' + cfg.totalMin + ')';
			} else if (cfg.totalMax) {
				rule = ' (massimo ' + cfg.totalMax + ')';
			}
			if (counterEl) {
				counterEl.textContent = 'Prodotti scelti: ' + total + rule;
			}
			if (totalEl) {
				totalEl.textContent = 'Totale box: ' + fmt(sum);
			}

			var hint = '';
			if (!valid) {
				hint = firstBad ? 'Completa la sezione «' + firstBad.getAttribute('data-title') + '».' : 'Scegli almeno ' + need + (need === 1 ? ' prodotto.' : ' prodotti.');
			}
			if (msg) {
				msg.textContent = hint;
			}
			if (btn) {
				btn.disabled = !valid;
				btn.classList.toggle('scb-disabled', !valid);
			}
			return valid;
		}

		root.addEventListener('change', function (e) {
			var t = e.target;
			var item = t.closest ? t.closest('.scb-item') : null;
			if (!item) {
				return;
			}
			if (t.classList.contains('scb-var')) {
				syncItem(item);
			}
			update();

			// Raggiunto il massimo della riga: passa in automatico alla successiva.
			if (t.classList.contains('scb-check') && t.checked) {
				var row = t.closest('.scb-row');
				var idx = rows.indexOf(row);
				if (row._count >= +row.getAttribute('data-max') && idx < rows.length - 1) {
					window.setTimeout(function () {
						openRow(idx + 1);
					}, 250);
				}
			}
		});

		root.addEventListener('click', function (e) {
			var head = e.target.closest('.scb-row-head');
			if (head) {
				openRow(rows.indexOf(head.closest('.scb-row')));
				return;
			}
			var next = e.target.closest('.scb-next');
			if (next) {
				openRow(rows.indexOf(next.closest('.scb-row')) + 1);
			}
		});

		if (form) {
			form.addEventListener('submit', function (e) {
				if (!update()) {
					e.preventDefault();
					e.stopImmediatePropagation();
					if (firstBad) {
						openRow(rows.indexOf(firstBad));
					}
				}
			}, true);
		}

		Array.prototype.forEach.call(root.querySelectorAll('.scb-item'), syncItem);
		openRow(0);
		update();
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('.scb'), init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
