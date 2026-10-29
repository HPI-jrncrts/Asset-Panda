/**
 * lp-cookie-declaration — adds Cookiebot's declaration script inside its mount point.
 *
 * Cookiebot's cd.js draws the cookie list where its <script id="CookieDeclaration">
 * sits, so the script is created inside the section's mount point (once per
 * page: Cookiebot looks the element up by that id). Loaded when the section
 * nears the viewport, since the list is at the end of a long page.
 */
(function () {
	'use strict';

	function mount(box) {
		if (box.getAttribute('data-ap-lp-cookies-ready') || document.getElementById('CookieDeclaration')) {
			return;
		}
		var cbid = box.getAttribute('data-ap-lp-cookies');
		if (!/^[0-9a-f-]{36}$/.test(cbid || '')) {
			return;
		}
		box.setAttribute('data-ap-lp-cookies-ready', '1');
		var tag = document.createElement('script');
		tag.id = 'CookieDeclaration';
		tag.type = 'text/javascript';
		tag.async = true;
		tag.src = 'https://consent.cookiebot.com/' + cbid + '/cd.js';
		var culture = box.getAttribute('data-ap-lp-cookies-culture');
		if (culture) {
			tag.setAttribute('data-culture', culture);
		}
		box.appendChild(tag);
	}

	function boot() {
		var boxes = document.querySelectorAll('[data-ap-lp-cookies]');
		Array.prototype.forEach.call(boxes, function (box) {
			if (!('IntersectionObserver' in window)) {
				mount(box);
				return;
			}
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						io.disconnect();
						mount(box);
					}
				});
			}, { rootMargin: '800px 0px' });
			io.observe(box);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
