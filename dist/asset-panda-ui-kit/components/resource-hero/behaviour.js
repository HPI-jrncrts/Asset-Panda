/* resource-hero - the ... button opens and closes the rest of the category rail.
 * Delegated and idempotent: one listener for every [data-ap-rh-tabs]. */
(function () {
	'use strict';
	if (typeof document === 'undefined') return;
	var html = document.documentElement;
	if (html.classList.contains('ap-resource-hero-js')) return;
	html.classList.add('ap-resource-hero-js');

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.ap-resource-hero__more') : null;
		if (!btn) return;
		var nav = btn.closest('[data-ap-rh-tabs]');
		if (!nav) return;
		var open = !nav.classList.contains('is-open');
		nav.classList.toggle('is-open', open);
		btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		btn.setAttribute('aria-label', btn.getAttribute(open ? 'data-less' : 'data-more') || '');
		if (open) {
			var first = nav.querySelector('.ap-resource-hero__item.is-extra a');
			if (first && e.detail === 0) first.focus();
		}
	});
})();
