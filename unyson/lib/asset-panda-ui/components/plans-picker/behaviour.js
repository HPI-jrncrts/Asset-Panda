/**
 * plans-picker — the "how many records?" chips.
 *
 * Delegated and idempotent: one click listener on the document serves every
 * [data-ap-plans-picker] section on the page. Pressing a chip flags the card it
 * points at ("Fits you"), writes the answer line, and announces the pick with
 * an `ap:plan-pick` event on the document so the compare table can follow.
 * Pressing the active chip again clears the pick.
 *
 * When the cards stack (phones, small tablets) the flagged card can be far
 * below the chips, so it is brought into view; on a desktop row it already is,
 * and the page stays put.
 */
(function () {
	'use strict';

	if (window.apPlansPickerReady) {
		return;
	}
	window.apPlansPickerReady = true;

	// Room for the sticky site header (and the admin bar when signed in).
	var HEADER_ROOM = 96;
	// How much of the card's top (name, price, tag) must be on screen.
	var TOP_SLICE = 140;

	var reduceMotion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;

	function list(root, selector) {
		return Array.prototype.slice.call(root.querySelectorAll(selector));
	}

	// Answer line: "<b>Plan</b> note", built as nodes so nothing is parsed as HTML.
	function writeAnswer(out, chip) {
		if (!out) {
			return;
		}
		while (out.firstChild) {
			out.removeChild(out.firstChild);
		}
		if (!chip) {
			out.textContent = out.getAttribute('data-ap-hint') || '';
			return;
		}
		var name = document.createElement('b');
		name.textContent = chip.getAttribute('data-ap-plan-name') || '';
		out.appendChild(name);
		var note = chip.getAttribute('data-ap-note') || '';
		if (note) {
			out.appendChild(document.createTextNode(' ' + note));
		}
	}

	function bringIntoView(card) {
		var box = card.getBoundingClientRect();
		var viewport = window.innerHeight || document.documentElement.clientHeight;
		if (box.top >= HEADER_ROOM && box.top + TOP_SLICE <= viewport) {
			return;
		}
		card.scrollIntoView({
			behavior: reduceMotion && reduceMotion.matches ? 'auto' : 'smooth',
			block: 'start',
		});
	}

	function pick(section, chip) {
		var key = chip ? chip.getAttribute('data-ap-plan') : '';
		var target = null;

		list(section, '.ap-plans-picker__chip').forEach(function (c) {
			c.setAttribute('aria-pressed', c === chip ? 'true' : 'false');
		});

		list(section, '.ap-plans-picker__card').forEach(function (card) {
			var hit = key !== '' && card.getAttribute('data-ap-plan') === key;
			card.classList.toggle('is-picked', hit);
			if (hit) {
				target = card;
			}
		});

		writeAnswer(section.querySelector('[data-ap-plans-out]'), chip);

		var detail = { plan: key, name: chip ? chip.getAttribute('data-ap-plan-name') || '' : '' };
		var event;
		if (typeof window.CustomEvent === 'function') {
			event = new CustomEvent('ap:plan-pick', { detail: detail });
		} else {
			event = document.createEvent('CustomEvent');
			event.initCustomEvent('ap:plan-pick', false, false, detail);
		}
		document.dispatchEvent(event);

		if (target) {
			bringIntoView(target);
		}
	}

	document.addEventListener('click', function (e) {
		var chip = e.target && e.target.closest ? e.target.closest('.ap-plans-picker__chip') : null;
		if (!chip) {
			return;
		}
		var section = chip.closest('[data-ap-plans-picker]');
		if (!section) {
			return;
		}
		pick(section, chip.getAttribute('aria-pressed') === 'true' ? null : chip);
	});
})();
