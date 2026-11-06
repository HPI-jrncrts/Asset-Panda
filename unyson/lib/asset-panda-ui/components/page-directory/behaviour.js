/**
 * page-directory — category chips and instant search over the directory cards.
 *
 * Delegated and idempotent: every [data-ap-directory] section is wired once
 * (data-ap-ready guards a second run). A chip sets the active tag (aria-pressed
 * moves with it); the search box filters by name, tags and description as the
 * visitor types. Cards are only shown or hidden — no layout is read — and a
 * card that comes back plays the short .is-in entrance (CSS, reduced-motion
 * aware). The 'coming soon' tile shows only while nothing narrows the grid.
 */
(function () {
	'use strict';

	function init(root) {
		if (root.hasAttribute('data-ap-ready')) {
			return;
		}
		root.setAttribute('data-ap-ready', '');

		var chips = Array.prototype.slice.call(root.querySelectorAll('[data-ap-filter]'));
		var cards = Array.prototype.slice.call(root.querySelectorAll('[data-ap-card]'));
		var input = root.querySelector('[data-ap-search]');
		var count = root.querySelector('[data-ap-count]');
		var empty = root.querySelector('[data-ap-empty]');
		var soon = root.querySelector('[data-ap-soon]');
		var tag = '*';
		var query = '';

		function apply() {
			var shown = 0;
			var back = [];
			cards.forEach(function (card) {
				var tags = (card.getAttribute('data-tags') || '').split('|');
				var text = card.getAttribute('data-text') || '';
				var ok = (tag === '*' || tags.indexOf(tag) !== -1) && (query === '' || text.indexOf(query) !== -1);
				if (ok) {
					shown++;
					if (card.hidden) {
						card.hidden = false;
						card.classList.remove('is-in');
						back.push(card);
					}
				} else {
					card.hidden = true;
				}
			});
			// cards that come back play the short entrance (next frame, so the class change restarts it)
			if (back.length && window.requestAnimationFrame) {
				window.requestAnimationFrame(function () {
					back.forEach(function (card) {
						card.classList.add('is-in');
					});
				});
			}
			if (soon) {
				soon.hidden = !(tag === '*' && query === '');
			}
			if (empty) {
				empty.hidden = shown > 0;
			}
			if (count) {
				count.textContent = (count.getAttribute('data-ap-count') || '{n}').replace('{n}', String(shown));
			}
		}

		chips.forEach(function (chip) {
			chip.addEventListener('click', function () {
				tag = chip.getAttribute('data-ap-filter') || '*';
				chips.forEach(function (c) {
					c.setAttribute('aria-pressed', c === chip ? 'true' : 'false');
				});
				apply();
			});
		});

		// Phones fold long descriptions to three lines (CSS); a tap on a card without a link unfolds it.
		cards.forEach(function (card) {
			if (card.classList.contains('ap-page-directory__card--link')) {
				return;
			}
			card.addEventListener('click', function (event) {
				if (event.target && event.target.closest && event.target.closest('a')) {
					return;
				}
				card.classList.toggle('is-open');
			});
		});

		if (input) {
			input.addEventListener('input', function () {
				query = input.value.trim().toLowerCase();
				apply();
			});
		}
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ap-directory]'), init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
