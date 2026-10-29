/**
 * roi-calculator — recomputes the savings card as the fields change.
 *
 * Delegated and idempotent: one set of listeners on the document serves every
 * [data-ap-roicalc] card on the page, and boot() can run any number of times.
 * The script holds no figures of its own: the field values come from the
 * card's inputs ([data-ap-roicalc-field]; a field the editor left out is a
 * hidden input carrying live's starting value), the hours/days constants from
 * the card's data attributes, and every caption from the markup.
 *
 * The maths is live's (e25-base-theme bundle.js, `.bs-section--roi-calculator`
 * module), operation for operation, on the same raw field strings, so the
 * doubles — and therefore the rounding — agree exactly:
 *   loss        = int(assets) x float(value) x (float(lost%)/100) x (int(tracked%)/100)
 *   ghost value = int(assets) x float(value) x (int(tracked%)/100) x (int(ghost%)/100) x (int(remaining%)/100)
 *   ghost cost  = ghost value x ((federal + state + property)/100) + .01 x ghost value x insurance
 *   search      = (tracked%/100) x int(days) x hours/day x (float(salary)/hours/year)
 *   total       = loss + ghost cost + search;   daily = total / days a year
 * and every figure is shown as live's U(x.toFixed(2)): en-US grouping, the
 * cents dropped when they are .00, a trailing zero kept (.50).
 *
 * The keystroke filter is live's inputFilter too: on every input / key / mouse
 * / select / drop event a value that fails the field's pattern is put back to
 * the last value that passed (with its selection), or emptied if none has.
 *
 * One deliberate difference: live redraws the whole card only from its desktop
 * slider; its phone stepper (below 768px) leaves "Audit Search Saving" stale
 * when % tracked changes. Here every change redraws every figure.
 */
(function () {
	'use strict';

	var PATTERNS = {
		integer: /^\d*$/,
		decimal: /^(\d*(\.\d{0,2})?)$/,
		percent: /^(\d{0,2}(\.\d{0,2})?|100)$/
	};
	var FILTER_EVENTS = ['input', 'keydown', 'keyup', 'mousedown', 'mouseup', 'select', 'contextmenu', 'drop'];
	var last = typeof WeakMap === 'function' ? new WeakMap() : null;

	// Live's U(): the string from toFixed(2) -> en-US text.
	function shown(fixed) {
		return /^\d*\.(10|20|30|40|50|60|70|80|90)$/.test(fixed)
			? Number(fixed).toLocaleString('en-US').toString() + '0'
			: Number(fixed).toLocaleString('en-US').toString();
	}

	// Live tests `0 != field.val().length` before parsing.
	function has(value) {
		return value.length !== 0;
	}

	function attrNumber(el, name, fallback) {
		var value = parseFloat(el.getAttribute(name));
		return isFinite(value) ? value : fallback;
	}

	function read(card) {
		var v = {};
		Array.prototype.forEach.call(card.querySelectorAll('[data-ap-roicalc-field]'), function (input) {
			var key = input.getAttribute('data-ap-roicalc-field');
			if (!Object.prototype.hasOwnProperty.call(v, key)) {
				v[key] = String(input.value);
			}
		});
		['assets', 'asset_value', 'loss_pct', 'tracked_pct', 'ghost_pct', 'remaining_pct', 'federal_tax', 'state_tax', 'property_tax', 'insurance', 'search_days', 'salary'].forEach(function (key) {
			if (!Object.prototype.hasOwnProperty.call(v, key)) {
				v[key] = '';
			}
		});
		return v;
	}

	// z() in live.
	function loss(v) {
		return parseInt(has(v.assets) ? v.assets : 0) * parseFloat(has(v.asset_value) ? v.asset_value : 0) * (parseFloat(has(v.loss_pct) ? v.loss_pct : 0) / 100) * (parseInt(v.tracked_pct) / 100);
	}

	// N() in live.
	function ghostValue(v) {
		return parseInt(has(v.assets) ? v.assets : 0) * parseFloat(has(v.asset_value) ? v.asset_value : 0) * (parseInt(v.tracked_pct) / 100) * (parseInt(v.ghost_pct) / 100) * (parseInt(v.remaining_pct) / 100);
	}

	// j() in live.
	function ghostCost(v) {
		var t = ghostValue(v);
		return parseFloat(t) * parseFloat((parseFloat(has(v.federal_tax) ? v.federal_tax : 0) + parseFloat(has(v.state_tax) ? v.state_tax : 0) + parseFloat(has(v.property_tax) ? v.property_tax : 0)) / 100) + 0.01 * parseFloat(t) * parseFloat(has(v.insurance) ? v.insurance : 0);
	}

	// H() in live (8 and 2080 there; the same values arrive as attributes here).
	function search(v, hoursPerDay, hoursPerYear) {
		return parseFloat(has(v.tracked_pct) ? v.tracked_pct / 100 : 0) * parseInt(has(v.search_days) ? v.search_days : 0) * hoursPerDay * (parseFloat(has(v.salary) ? v.salary : 0) / hoursPerYear);
	}

	function paintSlider(card, input) {
		var min = Number(input.min);
		var max = Number(input.max);
		var value = Number(input.value);
		input.style.setProperty('--ap-fill', ((value - min) / (max - min)) * 100 + '%');
		var key = input.getAttribute('data-ap-roicalc-field');
		var pill = card.querySelector('[data-ap-roicalc-pill="' + key + '"]');
		if (pill) {
			var text = (pill.getAttribute('data-ap-roicalc-template') || '{n}').split('{n}').join(String(value));
			pill.textContent = text;
			input.setAttribute('aria-valuetext', text);
		}
	}

	function update(card) {
		var v = read(card);
		var hoursPerDay = attrNumber(card, 'data-ap-roicalc-hours-day', 8);
		var hoursPerYear = attrNumber(card, 'data-ap-roicalc-hours-year', 2080);
		var daysPerYear = attrNumber(card, 'data-ap-roicalc-days-year', 365);

		// F() in live.
		var n = parseFloat(loss(v)) + parseFloat(ghostCost(v)) + parseFloat(search(v, hoursPerDay, hoursPerYear));
		var out = {
			loss: shown(loss(v).toFixed(2)),
			ghost_value: shown(ghostValue(v).toFixed(2)),
			ghost_cost: shown(ghostCost(v).toFixed(2)),
			search: shown(search(v, hoursPerDay, hoursPerYear).toFixed(2)),
			total: shown(n.toFixed(2)),
			daily: shown((n / daysPerYear).toFixed(2))
		};
		var currency = card.getAttribute('data-ap-roicalc-currency') || '';

		Array.prototype.forEach.call(card.querySelectorAll('[data-ap-roicalc-output]'), function (el) {
			var key = el.getAttribute('data-ap-roicalc-output');
			if (Object.prototype.hasOwnProperty.call(out, key)) {
				el.textContent = currency + out[key];
			}
		});
		Array.prototype.forEach.call(card.querySelectorAll('input[type="range"][data-ap-roicalc-field]'), function (input) {
			paintSlider(card, input);
		});
	}

	// Live's $.fn.inputFilter, delegated.
	function filter(event) {
		var input = event.target;
		if (!input || !input.getAttribute || !last) {
			return;
		}
		var test = PATTERNS[input.getAttribute('data-ap-roicalc-filter')];
		if (!test) {
			return;
		}
		if (test.test(input.value)) {
			last.set(input, { value: input.value, start: input.selectionStart, end: input.selectionEnd });
		} else if (last.has(input)) {
			var prev = last.get(input);
			input.value = prev.value;
			try {
				input.setSelectionRange(prev.start, prev.end);
			} catch (e) {
				// Not focusable / not a text field: the value is what matters.
			}
		} else {
			input.value = '';
		}
	}

	FILTER_EVENTS.forEach(function (type) {
		document.addEventListener(type, filter, true);
	});

	document.addEventListener('input', function (event) {
		var input = event.target;
		if (!input || !input.closest || !input.hasAttribute('data-ap-roicalc-field')) {
			return;
		}
		var card = input.closest('[data-ap-roicalc]');
		if (card) {
			update(card);
		}
	});

	// The - / + buttons beside each slider: one step, then the same input path a drag takes.
	document.addEventListener('click', function (event) {
		var button = event.target && event.target.closest ? event.target.closest('[data-ap-roicalc-step]') : null;
		if (!button) {
			return;
		}
		var card = button.closest('[data-ap-roicalc]');
		var input = card ? card.querySelector('input[type="range"][data-ap-roicalc-field="' + button.getAttribute('data-ap-roicalc-for') + '"]') : null;
		if (!input) {
			return;
		}
		var step = Number(input.step) || 1;
		var next = Math.min(Number(input.max), Math.max(Number(input.min), Number(input.value) + step * Number(button.getAttribute('data-ap-roicalc-step'))));
		if (String(next) !== input.value) {
			input.value = String(next);
			input.dispatchEvent(new Event('input', { bubbles: true }));
		}
	});

	/*
	 * Recompute from whatever the fields hold — the authored values, or the ones
	 * a browser restored on back/forward (live reads restored text fields the
	 * same way). A restored value its filter would refuse goes back to the
	 * authored one.
	 */
	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ap-roicalc]'), function (card) {
			if (card.getAttribute('data-ap-roicalc-ready')) {
				return;
			}
			card.setAttribute('data-ap-roicalc-ready', '1');
			Array.prototype.forEach.call(card.querySelectorAll('[data-ap-roicalc-filter]'), function (input) {
				var test = PATTERNS[input.getAttribute('data-ap-roicalc-filter')];
				if (test && !test.test(input.value)) {
					input.value = input.defaultValue;
				}
			});
			update(card);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
