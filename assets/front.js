(function () {
	'use strict';

	function init(root) {
		if (root.getAttribute('data-scb-ready')) {
			return;
		}
		root.setAttribute('data-scb-ready', '1');

		var cfg = JSON.parse(root.getAttribute('data-scb'));
		var blocked = root.classList.contains('is-blocked');
		var rows = Array.prototype.slice.call(root.querySelectorAll('.scb-row'));
		var form = root.closest('form');
		var btn = form ? form.querySelector('.single_add_to_cart_button') : null;
		var msg = root.querySelector('.scb-msg');
		var totalEl = root.querySelector('.scb-total');
		var counterEl = root.querySelector('.scb-counter');
		var barEl = root.querySelector('.scb-progress-bar');
		var recap = root.querySelector('.scb-recap');
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

		function openRow(i, focus) {
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
			// La riga di prima si chiude: chi usa la tastiera non deve perdere il punto.
			if (focus) {
				rows[i].querySelector('.scb-row-head').focus({ preventScroll: true });
			}
		}

		function setNote(row, text) {
			var el = row.querySelector('.scb-row-note');
			if (!el && text) {
				el = document.createElement('p');
				el.className = 'scb-row-note';
				var body = row.querySelector('.scb-row-body');
				body.insertBefore(el, body.firstChild);
			}
			if (el) {
				el.textContent = text;
				el.hidden = !text;
			}
		}

		function nameOf(item) {
			var n = item.querySelector('.scb-name');
			var text = n ? n.textContent : '';
			var sel = item.querySelector('.scb-var');
			if (sel && sel.options.length) {
				text += ' – ' + sel.options[sel.selectedIndex].text.split(' – ')[0];
			}
			return text;
		}

		function update() {
			var total = 0;
			var sum = cfg.boxCost;
			var picks = [];
			var done = 0;
			firstBad = null;

			rows.forEach(function (row) {
				var checked = row.querySelectorAll('.scb-check:checked');
				row._count = checked.length;
				total += checked.length;
				row._names = [];
				Array.prototype.forEach.call(checked, function (chk) {
					var item = chk.closest('.scb-item');
					var p = priceOf(item);
					sum += p;
					row._names.push(nameOf(item));
					picks.push([nameOf(item), p]);
				});
			});

			var capTotal = cfg.totalMax && total >= cfg.totalMax;
			var valid = true;

			rows.forEach(function (row) {
				var min = +row.getAttribute('data-min');
				var max = +row.getAttribute('data-max');
				var c = row._count;
				// Righe "scegli 1": un clic su un altro prodotto sostituisce la scelta.
				var swap = max === 1 && c === 1;
				var capped = !swap && (c >= max || capTotal);

				Array.prototype.forEach.call(row.querySelectorAll('.scb-check'), function (chk) {
					var item = chk.closest('.scb-item');
					if (!chk.checked) {
						chk.disabled = capped;
					}
					item.classList.toggle('is-checked', chk.checked);
					item.classList.toggle('is-capped', !chk.checked && capped);
				});

				var note = '';
				if (!swap && max > 1 && c >= max) {
					note = 'Hai scelto il massimo per questa sezione: togli un prodotto per sceglierne un altro.';
				} else if (!swap && capTotal && c < max) {
					note = 'Hai raggiunto il massimo di ' + cfg.totalMax + ' prodotti per la box: togline uno per sceglierne un altro.';
				}
				setNote(row, note);

				row.querySelector('.scb-row-status').textContent = c + ' / ' + max;
				var pick = row.querySelector('.scb-row-pick');
				if (pick) {
					pick.textContent = row._names.join(', ');
				}
				var ok = c >= min;
				row.classList.toggle('is-done', c > 0 && ok);
				if (c > 0 && ok) {
					done++;
				}
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
			if (total < need || blocked) {
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
			if (barEl) {
				barEl.style.width = (rows.length ? Math.round(done / rows.length * 100) : 0) + '%';
			}
			if (recap) {
				recap.hidden = !picks.length;
				var list = recap.querySelector('.scb-recap-list');
				list.textContent = '';
				var line = function (label, price, cls) {
					var li = document.createElement('li');
					if (cls) {
						li.className = cls;
					}
					var a = document.createElement('span');
					a.textContent = label;
					var b = document.createElement('span');
					b.textContent = fmt(price);
					li.appendChild(a);
					li.appendChild(b);
					list.appendChild(li);
				};
				if (cfg.boxCost > 0) {
					line('Scatola', cfg.boxCost, 'is-box');
				}
				picks.forEach(function (p) {
					line(p[0], p[1]);
				});
				recap.querySelector('.scb-recap-total strong').textContent = fmt(sum);
			}

			var hint = '';
			if (blocked) {
				hint = '';
			} else if (!valid) {
				hint = firstBad ? 'Completa la sezione «' + firstBad.getAttribute('data-title') + '».' : 'Scegli almeno ' + need + (need === 1 ? ' prodotto.' : ' prodotti.');
			}
			if (msg) {
				msg.textContent = hint;
				if (valid) {
					msg.classList.remove('is-error');
				}
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
			var row = item.closest('.scb-row');
			var chk = item.querySelector('.scb-check');
			var before = row._count;
			var max = +row.getAttribute('data-max');

			if (t.classList.contains('scb-var')) {
				syncItem(item);
				// Scegliere la variante seleziona anche il prodotto.
				if (!chk.checked && !chk.disabled) {
					chk.checked = true;
				}
			}

			if (chk.checked && max === 1) {
				Array.prototype.forEach.call(row.querySelectorAll('.scb-check:checked'), function (other) {
					if (other !== chk) {
						other.checked = false;
					}
				});
			}
			update();

			// Riga appena completata (non un cambio di scelta): passa in automatico alla successiva.
			var idx = rows.indexOf(row);
			if (chk.checked && before < max && row._count >= max && idx < rows.length - 1) {
				window.setTimeout(function () {
					openRow(idx + 1, true);
				}, 250);
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
				openRow(rows.indexOf(next.closest('.scb-row')) + 1, true);
			}
		});

		function showProblem() {
			if (msg) {
				msg.classList.add('is-error');
			}
			if (firstBad) {
				openRow(rows.indexOf(firstBad), true);
			}
		}

		// Richiesta dallo sticky add to cart: aggiunge se la box è completa, altrimenti mostra cosa manca.
		root.addEventListener('scb:submit', function () {
			if (update() && btn) {
				btn.click();
			} else {
				showProblem();
			}
		});

		if (form) {
			form.addEventListener('submit', function (e) {
				if (!update()) {
					e.preventDefault();
					e.stopImmediatePropagation();
					showProblem();
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

	// Quick view e contenuti caricati via ajax: inizializza i blocchi appena compaiono.
	function watch() {
		if (!window.MutationObserver || !document.body) {
			return;
		}
		var queued = false;
		new MutationObserver(function () {
			if (queued) {
				return;
			}
			queued = true;
			window.requestAnimationFrame(function () {
				queued = false;
				boot();
			});
		}).observe(document.body, { childList: true, subtree: true });
	}

	// Sticky add to cart (Woodmart): il suo form non contiene la box, quindi porta al blocco.
	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.single_add_to_cart_button') : null;
		if (!btn) {
			return;
		}
		var form = btn.closest('form');
		if (!form || form.querySelector('.scb')) {
			return;
		}
		var box = document.getElementById('scb-' + btn.value);
		if (!box) {
			return;
		}
		e.preventDefault();
		e.stopImmediatePropagation();
		box.scrollIntoView({ behavior: 'smooth', block: 'start' });
		box.dispatchEvent(new CustomEvent('scb:submit'));
	}, true);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			boot();
			watch();
		});
	} else {
		boot();
		watch();
	}
})();
