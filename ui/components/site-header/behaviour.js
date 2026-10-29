/**
 * site-header — behaviour for the global header.
 *
 * - Deferred art (data-ap-sh-src: post images, case-card photos) loads per pane: the
 *   first pointer or focus on the bar warms every menu's shown pane, and showing a
 *   tab loads its pane. Loaded images get .is-loaded (the posts fade in on it).
 * - Tab rails: hovering, focusing or clicking a tab shows its panel.
 * - Desktop menus open on hover and focus (CSS). A mouse leaving a menu, or
 *   Escape, drops focus so a clicked menu never stays pinned open.
 * - Login dropdown, the phone drawer (one section open at a time) and the app
 *   banner (Google Play link on Android, dismiss).
 * - Geo: html.ap-intl for visitors outside US/CA/GB/NZ/AU, as on live.
 *
 * Bound once per [data-ap-site-header] root; safe with several on a page.
 */
(function () {
	'use strict';

	var DESKTOP = window.matchMedia('(min-width: 1101px)');

	function init(root) {
		if (root.getAttribute('data-ap-sh-ready')) {
			return;
		}
		root.setAttribute('data-ap-sh-ready', '1');

		/*
		 * Deferred art. Post images and the case cards' photo and logo ship as data-ap-sh-src
		 * (render.php): their panes are laid out while hidden, so loading="lazy" would still fetch
		 * them on every page. A pane's images load when the bar is first pointed at or focused (the
		 * pane each menu shows first) and when its tab is shown, so a visitor who never opens a menu
		 * downloads none of them, and one who does sees the shown pane's images arrive with the panel
		 * rather than every pane's at once.
		 */
		function hydrate(scope) {
			scope.querySelectorAll('img[data-ap-sh-src]').forEach(function (img) {
				img.addEventListener('load', function () { img.classList.add('is-loaded'); }, { once: true });
				img.setAttribute('src', img.getAttribute('data-ap-sh-src'));
				img.removeAttribute('data-ap-sh-src');
			});
		}

		// What a menu shows right now: its active tab panes, or the whole panel when it has none.
		function hydrateShown(panel) {
			var panes = panel.querySelectorAll('[data-ap-help-panel].is-active');
			if (panes.length) {
				panes.forEach(hydrate);
			} else {
				hydrate(panel);
			}
		}

		var bar = root.querySelector('.ap-site-header__desk');
		if (bar && root.querySelector('img[data-ap-sh-src]')) {
			var warm = function () {
				bar.removeEventListener('pointerover', warm);
				bar.removeEventListener('focusin', warm);
				bar.querySelectorAll('.ap-mega-panel').forEach(hydrateShown);
			};
			bar.addEventListener('pointerover', warm);
			bar.addEventListener('focusin', warm);
		}

		/* Tab rails. */
		root.querySelectorAll('.ap-mega-panel').forEach(function (panel) {
			var tabs = panel.querySelectorAll('[data-ap-help-tab]');
			var panes = panel.querySelectorAll('[data-ap-help-panel]');

			function activate(key) {
				tabs.forEach(function (tab) {
					tab.classList.toggle('is-active', tab.getAttribute('data-ap-help-tab') === key);
				});
				panes.forEach(function (pane) {
					var shown = pane.getAttribute('data-ap-help-panel') === key;
					pane.classList.toggle('is-active', shown);
					if (shown) {
						hydrate(pane);
					}
				});
			}

			tabs.forEach(function (tab) {
				var key = tab.getAttribute('data-ap-help-tab');
				tab.addEventListener('mouseenter', function () { activate(key); });
				tab.addEventListener('focus', function () { activate(key); });
				tab.addEventListener('click', function () { activate(key); });
			});

			if (panel.classList.contains('ap-mega-panel--figma-2026')) {
				railKeys(panel, tabs, activate);
			}
		});

		/*
		 * Keyboard order for the Figma rail (Who We Help). Every category sits
		 * above every link in the DOM and only the active category's links are
		 * displayed, so plain Tab walked the rail and reached only the last
		 * category's links. Here Tab runs as if each category's links followed
		 * it: category, its links, next category ... and Shift+Tab mirrors that.
		 */
		function railKeys(panel, tabs, activate) {
			var list = Array.prototype.slice.call(tabs);

			function items(index) {
				var key = list[index].getAttribute('data-ap-help-tab');
				var pane = panel.querySelector('[data-ap-help-panel="' + key + '"]');
				return pane ? Array.prototype.slice.call(pane.querySelectorAll('a[href], button:not([disabled])')) : [];
			}

			function plainTab(event) {
				return event.key === 'Tab' && !event.altKey && !event.ctrlKey && !event.metaKey;
			}

			list.forEach(function (tab, index) {
				tab.addEventListener('keydown', function (event) {
					if (!plainTab(event)) {
						return;
					}
					if (!event.shiftKey) {
						var own = items(index);
						if (own.length) {
							event.preventDefault();
							activate(tab.getAttribute('data-ap-help-tab'));
							own[0].focus();
						}
					} else if (index > 0) {
						var before = items(index - 1);
						event.preventDefault();
						activate(list[index - 1].getAttribute('data-ap-help-tab'));
						(before.length ? before[before.length - 1] : list[index - 1]).focus();
					}
				});

				var key = tab.getAttribute('data-ap-help-tab');
				var pane = panel.querySelector('[data-ap-help-panel="' + key + '"]');
				if (!pane) {
					return;
				}
				pane.addEventListener('keydown', function (event) {
					if (!plainTab(event)) {
						return;
					}
					var own = items(index);
					if (!event.shiftKey && own.length && document.activeElement === own[own.length - 1] && list[index + 1]) {
						event.preventDefault();
						list[index + 1].focus();
					} else if (event.shiftKey && own.length && document.activeElement === own[0]) {
						event.preventDefault();
						tab.focus();
					}
				});
			});
		}

		/* Desktop menus: report state, and never leave one pinned open. */
		root.querySelectorAll('.ap-nav-item.ap-has-mega').forEach(function (item) {
			var trigger = item.querySelector('.ap-nav-top');

			function sync() {
				if (trigger) {
					trigger.setAttribute('aria-expanded', item.matches(':hover, :focus-within') ? 'true' : 'false');
				}
			}

			item.addEventListener('mouseenter', sync);
			item.addEventListener('focusin', sync);
			item.addEventListener('focusout', function () { window.setTimeout(sync, 0); });
			item.addEventListener('mouseleave', function () {
				if (item.contains(document.activeElement)) {
					document.activeElement.blur();
				}
				sync();
			});
		});

		/* Login dropdown. */
		var login = root.querySelector('[data-ap-sh-login]');
		var dropdown = root.querySelector('.ap-login-dd');

		function setLogin(open) {
			if (!login || !dropdown) {
				return;
			}
			dropdown.classList.toggle('is-open', open);
			login.classList.toggle('is-open', open);
			login.setAttribute('aria-expanded', open ? 'true' : 'false');
		}

		if (login && dropdown) {
			login.addEventListener('click', function (event) {
				event.preventDefault();
				setLogin(!dropdown.classList.contains('is-open'));
			});
			document.addEventListener('click', function (event) {
				if (!login.contains(event.target) && !dropdown.contains(event.target)) {
					setLogin(false);
				}
			});
		}

		/* Phone drawer. */
		var mob = root.querySelector('.ap-site-header__mob');
		var burger = mob ? mob.querySelector('.ap-mobile-burger') : null;
		var menu = mob ? mob.querySelector('.ap-mobile-menu') : null;
		var head = mob ? mob.querySelector('.ap-mobile-head-inner') : null;
		var sections = mob ? mob.querySelectorAll('.ap-mobile-menu details') : [];

		// Pin the drawer under the header wherever the header sits right now
		// (below the app banner, under the admin bar, or at the top).
		function place() {
			if (!head || !menu) {
				return;
			}
			var bottom = Math.max(0, head.getBoundingClientRect().bottom);
			menu.style.top = bottom + 'px';
			menu.style.height = (window.innerHeight - bottom) + 'px';
		}

		function setDrawer(open) {
			if (!mob || !burger) {
				return;
			}
			if (open) {
				place();
			}
			mob.classList.toggle('is-open', open);
			burger.setAttribute('aria-expanded', open ? 'true' : 'false');
			burger.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
			if (!open) {
				sections.forEach(function (section) { section.open = false; });
			}
		}

		if (burger) {
			burger.addEventListener('click', function () {
				setDrawer(!mob.classList.contains('is-open'));
			});
		}

		sections.forEach(function (current) {
			current.addEventListener('toggle', function () {
				if (!current.open) {
					return;
				}
				sections.forEach(function (other) {
					if (other !== current) {
						other.open = false;
					}
				});
			});
		});

		window.addEventListener('resize', function () {
			if (mob && mob.classList.contains('is-open')) {
				place();
			}
		});

		var onBreakpoint = function (event) {
			if (event.matches) {
				setDrawer(false);
			}
		};
		if (DESKTOP.addEventListener) {
			DESKTOP.addEventListener('change', onBreakpoint);
		} else if (DESKTOP.addListener) {
			DESKTOP.addListener(onBreakpoint);
		}

		/* App banner. */
		var banner = root.querySelector('.ap-site-header__banner');
		if (banner) {
			var store = banner.querySelector('[data-ap-sh-android]');
			if (store && /android/i.test(navigator.userAgent || '')) {
				store.setAttribute('href', store.getAttribute('data-ap-sh-android'));
			}
			var close = banner.querySelector('.ap-app-close');
			if (close) {
				close.addEventListener('click', function () {
					banner.classList.add('is-hidden');
					if (mob && mob.classList.contains('is-open')) {
						place();
					}
				});
			}
		}

		/* Escape closes whatever is open. */
		document.addEventListener('keydown', function (event) {
			if (event.key !== 'Escape') {
				return;
			}
			setLogin(false);
			setDrawer(false);
			var desk = root.querySelector('.ap-site-header__desk');
			if (desk && desk.contains(document.activeElement)) {
				document.activeElement.blur();
			}
		});
	}

	/*
	 * International visitors. As on live, html.ap-intl is added when the CDN
	 * trace places the visitor outside these countries; style.css then makes
	 * Try for Free the primary action and hides the US sales number. Runs once
	 * per page; any failure leaves the US layout in place.
	 */
	var HOME_COUNTRIES = ['US', 'CA', 'GB', 'NZ', 'AU'];

	function geo() {
		var html = document.documentElement;
		if (html.getAttribute('data-ap-sh-geo') || html.classList.contains('ap-intl') || !window.fetch) {
			return;
		}
		html.setAttribute('data-ap-sh-geo', '1');
		try {
			window.fetch('/cdn-cgi/trace', { cache: 'no-store' })
				.then(function (response) { return response.ok ? response.text() : ''; })
				.then(function (text) {
					var match = /loc=([A-Z]{2})/.exec(text || '');
					if (match && HOME_COUNTRIES.indexOf(match[1]) === -1) {
						html.classList.add('ap-intl');
					}
				})
				.catch(function () {});
		} catch (e) {}
	}

	function boot() {
		var roots = document.querySelectorAll('[data-ap-site-header]');
		roots.forEach(init);
		if (roots.length) {
			geo();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
