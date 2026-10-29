/**
 * plans-compare — the phone plan switch, and following plans-picker.
 *
 * Delegated and idempotent. boot() marks each [data-ap-plans-compare] section
 * `is-switchable` once; below 768px the stylesheet then shows the switch and
 * hides every value column but the chosen one (`is-off`). Above that width the
 * classes have no effect; the only viewport read is a matchMedia listener that
 * narrows the section bands' colspan on phones.
 *
 * An `ap:plan-pick` event (sent by plans-picker) tints the picked plan's
 * column (`is-focus`) and moves the phone switch to it; an empty pick clears
 * the tint. Without this script the table simply scrolls sideways.
 */
(function () {
	'use strict';

	if (window.apPlansCompareReady) {
		return;
	}
	window.apPlansCompareReady = true;

	function list(root, selector) {
		return Array.prototype.slice.call(root.querySelectorAll(selector));
	}

	function valueCells(section) {
		return list(section, '.ap-plans-compare__table [data-col]');
	}

	// Phone view: one plan column.
	function show(section, col) {
		col = String(col);
		list(section, '.ap-plans-compare__switch-btn').forEach(function (btn) {
			btn.setAttribute('aria-pressed', btn.getAttribute('data-col') === col ? 'true' : 'false');
		});
		valueCells(section).forEach(function (cell) {
			cell.classList.toggle('is-off', cell.getAttribute('data-col') !== col);
		});
	}

	/*
	 * A section band spans every column. With one plan column showing, the
	 * hidden columns would keep a share of the table width, so on phones the
	 * band spans just the two that are visible. The original span is kept in
	 * data-ap-span.
	 */
	var phone = window.matchMedia ? window.matchMedia('(max-width: 767px)') : null;

	function fitBands(section) {
		list(section, '.ap-plans-compare__table tr.is-group th[colspan]').forEach(function (th) {
			if (!th.hasAttribute('data-ap-span')) {
				th.setAttribute('data-ap-span', th.getAttribute('colspan'));
			}
			th.setAttribute('colspan', phone && phone.matches ? '2' : th.getAttribute('data-ap-span'));
		});
	}

	// Wide view: tint the picked plan's column; '' clears it.
	function focus(section, col) {
		col = String(col);
		valueCells(section).forEach(function (cell) {
			cell.classList.toggle('is-focus', col !== '' && cell.getAttribute('data-col') === col);
		});
	}

	function boot() {
		list(document, '[data-ap-plans-compare]').forEach(function (section) {
			if (section.hasAttribute('data-ap-compare-ready')) {
				return;
			}
			section.setAttribute('data-ap-compare-ready', '');
			section.classList.add('is-switchable');
			show(section, section.getAttribute('data-ap-focus') || '1');
			fitBands(section);
		});
	}

	if (phone) {
		var refit = function () {
			list(document, '[data-ap-plans-compare][data-ap-compare-ready]').forEach(fitBands);
		};
		if (phone.addEventListener) {
			phone.addEventListener('change', refit);
		} else if (phone.addListener) {
			phone.addListener(refit);
		}
	}

	document.addEventListener('click', function (e) {
		var btn = e.target && e.target.closest ? e.target.closest('.ap-plans-compare__switch-btn') : null;
		if (!btn) {
			return;
		}
		var section = btn.closest('[data-ap-plans-compare]');
		if (section) {
			show(section, btn.getAttribute('data-col'));
		}
	});

	document.addEventListener('ap:plan-pick', function (e) {
		var key = e.detail && e.detail.plan ? String(e.detail.plan) : '';
		list(document, '[data-ap-plans-compare]').forEach(function (section) {
			var col = '';
			list(section, '.ap-plans-compare__table thead th[data-col]').forEach(function (th) {
				if (key !== '' && th.getAttribute('data-ap-plan') === key) {
					col = th.getAttribute('data-col');
				}
			});
			focus(section, col);
			if (col !== '') {
				show(section, col);
			}
		});
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
