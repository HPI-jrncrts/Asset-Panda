/**
 * pricing-calculator — recomputes the ROI card as the sliders move.
 *
 * Delegated and idempotent: one `input` listener on the document serves every
 * [data-ap-roi] card on the page, and boot() can run any number of times. The
 * script holds no figures of its own — the rates, weeks per year and people
 * baseline come from the card's data attributes, the scales from each range
 * input, and every text from data-ap-roi-template ({n} = the formatted value).
 *
 * The model is live's (/new-pricing/), operation for operation, so the
 * rounding agrees exactly:
 *   hours saved = round(hours/wk x weeks x time% x people / baseline)
 *   labour      = round(hours saved x hourly)
 *   loss        = round(asset value x loss%)
 *   total       = labour + loss
 */
(function () {
	'use strict';

	function number(value) {
		return Math.round(value).toLocaleString('en-US');
	}

	// Mirrors $format in render.php.
	function format(value, kind) {
		switch (kind) {
			case 'number':
				return number(value);
			case 'currency':
				return '$' + number(value);
			case 'money_short':
				if (value >= 1000000) {
					var millions = value / 1000000;
					return '$' + (millions % 1 === 0 ? millions.toFixed(0) : millions.toFixed(1)) + 'M';
				}
				return '$' + Math.round(value / 1000) + 'K';
			default:
				return String(value);
		}
	}

	function fill(template, text) {
		return (template || '{n}').split('{n}').join(text);
	}

	function attrNumber(el, name, fallback) {
		var value = parseFloat(el.getAttribute(name));
		return isFinite(value) ? value : fallback;
	}

	function paintFill(input) {
		var min = Number(input.min);
		var max = Number(input.max);
		var percent = ((Number(input.value) - min) / (max - min)) * 100;
		input.style.setProperty('--ap-fill', percent + '%');
	}

	function update(card) {
		var baseline = attrNumber(card, 'data-ap-roi-base', 10) || 10;
		var values = { people: baseline, hourly: 0, assets: 0, hours: 0 };

		Array.prototype.forEach.call(card.querySelectorAll('[data-ap-roi-input]'), function (input) {
			var key = input.getAttribute('data-ap-roi-input');
			var value = Number(input.value);
			values[key] = value;
			paintFill(input);

			var pill = card.querySelector('[data-ap-roi-pill="' + key + '"]');
			if (pill) {
				var one = pill.getAttribute('data-ap-roi-template-one');
				var text = fill(value === 1 && one ? one : pill.getAttribute('data-ap-roi-template'), format(value, pill.getAttribute('data-ap-roi-format')));
				pill.textContent = text;
				input.setAttribute('aria-valuetext', text);
			}
		});

		var timeRate = attrNumber(card, 'data-ap-roi-time-pct', 70) / 100;
		var lossRate = attrNumber(card, 'data-ap-roi-loss-pct', 2) / 100;
		var weeks = attrNumber(card, 'data-ap-roi-weeks', 52);

		var hours = Math.round(values.hours * weeks * timeRate * (values.people / baseline));
		var labor = Math.round(hours * values.hourly);
		var loss = Math.round(values.assets * lossRate);
		var shown = {
			hours: format(hours, 'number'),
			labor: format(labor, 'currency'),
			loss: format(loss, 'currency'),
			total: format(labor + loss, 'currency')
		};

		Array.prototype.forEach.call(card.querySelectorAll('[data-ap-roi-output]'), function (out) {
			var key = out.getAttribute('data-ap-roi-output');
			if (Object.prototype.hasOwnProperty.call(shown, key)) {
				out.textContent = fill(out.getAttribute('data-ap-roi-template'), shown[key]);
			}
		});
	}

	document.addEventListener('input', function (event) {
		var input = event.target;
		if (!input || !input.closest || !input.hasAttribute('data-ap-roi-input')) {
			return;
		}
		var card = input.closest('[data-ap-roi]');
		if (card) {
			update(card);
		}
	});

	/*
	 * Like live, every card starts from its authored values: a browser that
	 * restores form state (back/forward) would otherwise show stale positions.
	 */
	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ap-roi]'), function (card) {
			if (card.getAttribute('data-ap-roi-ready')) {
				return;
			}
			card.setAttribute('data-ap-roi-ready', '1');
			Array.prototype.forEach.call(card.querySelectorAll('[data-ap-roi-input]'), function (input) {
				input.value = input.defaultValue;
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
