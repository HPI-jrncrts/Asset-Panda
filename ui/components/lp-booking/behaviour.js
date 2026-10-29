/**
 * lp-booking — mounts the scheduler of a booking card when it nears the viewport.
 *
 * Calendly: loads Calendly's widget script once and draws the inline widget
 * (its postMessage events reach the page as usual, so the site's GTM
 * Calendly listener still counts bookings). Cal.com: installs Cal.com's
 * published loader stub once (or reuses one already on the page) and draws
 * the inline embed with the options live's booking pages use: month view,
 * light theme in the site palette, event details hidden (the card shows
 * them), query parameters forwarded to the booking. Idempotent: each mount
 * point is claimed once, however often this runs.
 */
(function () {
	'use strict';

	var CALENDLY = 'https://assets.calendly.com/assets/external/widget.js';
	var CAL_EMBED = 'https://app.cal.com/embed/embed.js';
	var CAL_ORIGIN = 'https://app.cal.com';
	var calendlyLoading = null;

	var PALETTE = {
		'cal-brand': '#5044e1',
		'cal-brand-emphasis': '#3e33c9',
		'cal-brand-text': '#ffffff',
		'cal-text': '#25272c',
		'cal-text-emphasis': '#25272c',
		'cal-text-subtle': '#5b616e',
		'cal-text-muted': '#8a8f99',
		'cal-bg': '#ffffff',
		'cal-bg-emphasis': '#eceef6',
		'cal-bg-subtle': '#f4f5fa',
		'cal-bg-muted': '#f7f8fb',
		'cal-border': '#e1e4ec',
		'cal-border-subtle': '#eceef3',
		'cal-border-booker': '#e1e4ec',
		'cal-border-booker-width': '1px'
	};

	function loadCalendly() {
		if (window.Calendly && window.Calendly.initInlineWidget) {
			return Promise.resolve();
		}
		if (calendlyLoading) {
			return calendlyLoading;
		}
		calendlyLoading = new Promise(function (resolve, reject) {
			var tag = document.createElement('script');
			tag.src = CALENDLY;
			tag.async = true;
			tag.onload = function () {
				var tries = 0;
				(function wait() {
					if (window.Calendly && window.Calendly.initInlineWidget) {
						resolve();
					} else if (tries++ < 100) {
						window.setTimeout(wait, 100);
					} else {
						reject(new Error('calendly'));
					}
				})();
			};
			tag.onerror = reject;
			document.head.appendChild(tag);
		});
		calendlyLoading.catch(function () {
			calendlyLoading = null;
		});
		return calendlyLoading;
	}

	/* Cal.com's published loader stub, unminified (skipped when the page already has one). */
	function installCalStub() {
		var w = window;
		if (w.Cal) {
			return;
		}
		var push = function (target, args) {
			target.q.push(args);
		};
		w.Cal = function () {
			var cal = w.Cal;
			var args = arguments;
			if (!cal.loaded) {
				cal.ns = {};
				cal.q = cal.q || [];
				document.head.appendChild(document.createElement('script')).src = CAL_EMBED;
				cal.loaded = true;
			}
			if (args[0] === 'init') {
				var api = function () {
					push(api, arguments);
				};
				var namespace = args[1];
				api.q = api.q || [];
				if (typeof namespace === 'string') {
					cal.ns[namespace] = cal.ns[namespace] || api;
					push(cal.ns[namespace], args);
					push(cal, ['initNamespace', namespace]);
				} else {
					push(cal, args);
				}
				return;
			}
			push(cal, args);
		};
	}

	function mountCalendly(box) {
		var url = box.getAttribute('data-ap-lp-booking-src');
		loadCalendly().then(function () {
			box.innerHTML = '';
			window.Calendly.initInlineWidget({ url: url, parentElement: box });
		}).catch(function () {
			box.removeAttribute('data-ap-lp-booking-ready');
		});
	}

	function mountCal(box) {
		var link = box.getAttribute('data-ap-lp-booking-src');
		var ns = box.getAttribute('data-ap-lp-booking-ns') || 'asset-panda-demo';
		installCalStub();
		var Cal = window.Cal;
		if (!Cal.ns || !Cal.ns[ns]) {
			Cal('init', ns, { origin: CAL_ORIGIN });
		}
		Cal.config = Cal.config || {};
		Cal.config.forwardQueryParams = true;
		var fallback = box.querySelector('.ap-lp-booking__fallback');
		if (fallback) {
			fallback.parentNode.removeChild(fallback);
		}
		Cal.ns[ns]('inline', {
			elementOrSelector: box,
			config: { layout: 'month_view', useSlotsViewOnSmallScreen: 'true', theme: 'light' },
			calLink: link
		});
		Cal.ns[ns]('ui', {
			theme: 'light',
			hideEventTypeDetails: true,
			layout: 'month_view',
			cssVarsPerTheme: { light: PALETTE, dark: PALETTE }
		});
	}

	function mount(box) {
		if (box.getAttribute('data-ap-lp-booking-ready')) {
			return;
		}
		box.setAttribute('data-ap-lp-booking-ready', '1');
		if (box.getAttribute('data-ap-lp-booking') === 'cal') {
			mountCal(box);
		} else {
			mountCalendly(box);
		}
	}

	function boot() {
		var boxes = document.querySelectorAll('[data-ap-lp-booking]');
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
			}, { rootMargin: '600px 0px' });
			io.observe(box);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
