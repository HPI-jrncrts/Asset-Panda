/**
 * page-faq-hub — instant search and the current-topic highlight.
 *
 * Delegated and idempotent ([data-ap-faqhub], guarded by data-ap-ready).
 * Search: as the visitor types, questions whose question or answer text does
 * not contain the query are hidden, empty topics hide, the topic counts show
 * the matches and an empty state appears when nothing is left. Topic
 * highlight: one IntersectionObserver per FAQ marks the topic in view in the
 * topic list (aria-current). No layout is read in a scroll handler.
 */
(function () {
	'use strict';

	function init(root) {
		if (root.hasAttribute('data-ap-ready')) {
			return;
		}
		root.setAttribute('data-ap-ready', '');

		var input = root.querySelector('[data-ap-faq-search]');
		var groups = Array.prototype.slice.call(root.querySelectorAll('[data-ap-faq-group]'));
		var links = Array.prototype.slice.call(root.querySelectorAll('[data-ap-faq-link]'));
		var empty = root.querySelector('[data-ap-faq-empty]');

		function setCurrent(id) {
			links.forEach(function (a) {
				if (a.getAttribute('data-ap-faq-link') === id) {
					a.setAttribute('aria-current', 'true');
				} else {
					a.removeAttribute('aria-current');
				}
			});
		}

		if (input) {
			input.addEventListener('input', function () {
				var q = input.value.trim().toLowerCase();
				var total = 0;
				groups.forEach(function (g) {
					var n = 0;
					Array.prototype.forEach.call(g.querySelectorAll('[data-ap-faq-item]'), function (item) {
						var ok = q === '' || (item.getAttribute('data-text') || '').indexOf(q) !== -1;
						item.hidden = !ok;
						if (ok) {
							n++;
						}
					});
					g.hidden = n === 0;
					total += n;
					links.forEach(function (a) {
						if (a.getAttribute('data-ap-faq-link') === g.id) {
							var badge = a.querySelector('[data-ap-faq-n]');
							if (badge) {
								badge.textContent = String(n);
							}
							a.parentNode.hidden = n === 0;
						}
					});
				});
				if (empty) {
					empty.hidden = total > 0;
				}
			});
		}

		links.forEach(function (a) {
			a.addEventListener('click', function () {
				setCurrent(a.getAttribute('data-ap-faq-link'));
			});
		});

		if ('IntersectionObserver' in window && groups.length) {
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (e) {
					if (e.isIntersecting) {
						setCurrent(e.target.id);
					}
				});
			}, { rootMargin: '-120px 0px -60% 0px', threshold: 0 });
			groups.forEach(function (g) {
				io.observe(g);
			});
		}
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ap-faqhub]'), init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
