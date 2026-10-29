/**
 * page-reviews — switch industries in place.
 *
 * Delegated and idempotent ([data-ap-reviews], guarded by data-ap-ready). On
 * start the section gets .is-ready and the group named by data-current (or
 * every group for '*') is marked .is-current; the rest hide by CSS. A chip
 * click moves aria-pressed and .is-current and plays the short .is-in
 * entrance on the group(s) shown. No layout is read.
 */
(function () {
	'use strict';

	function init(root) {
		if (root.hasAttribute('data-ap-ready')) {
			return;
		}
		root.setAttribute('data-ap-ready', '');

		var chips = Array.prototype.slice.call(root.querySelectorAll('[data-ap-rv]'));
		var groups = Array.prototype.slice.call(root.querySelectorAll('[data-ap-rv-group]'));

		function show(key, animate) {
			groups.forEach(function (g) {
				var on = key === '*' || g.getAttribute('data-ap-rv-group') === key;
				g.classList.toggle('is-current', on);
				g.classList.remove('is-in');
				if (on && animate && window.requestAnimationFrame) {
					window.requestAnimationFrame(function () {
						g.classList.add('is-in');
					});
				}
			});
			chips.forEach(function (c) {
				c.setAttribute('aria-pressed', c.getAttribute('data-ap-rv') === key ? 'true' : 'false');
			});
			root.setAttribute('data-current', key);
		}

		show(root.getAttribute('data-current') || '*', false);
		root.classList.add('is-ready');

		chips.forEach(function (chip) {
			chip.addEventListener('click', function () {
				show(chip.getAttribute('data-ap-rv') || '*', true);
			});
		});
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ap-reviews]'), init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
