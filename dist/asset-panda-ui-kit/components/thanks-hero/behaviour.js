/**
 * thanks-hero — mounts the inline Cal.com booking calendar.
 *
 * Every mount point carries its booking link and embed namespace as data
 * attributes (validated server-side). Cal.com's loader stub is installed once
 * per page and queues calls until app.cal.com/embed/embed.js arrives — the
 * same snippet and options the live thank-you pages use (month view, light
 * theme in the site palette, query parameters forwarded to the booking).
 * Idempotent: a mount point is claimed once, however often this runs.
 */
(function () {
	'use strict';

	var EMBED = 'https://app.cal.com/embed/embed.js';
	var ORIGIN = 'https://app.cal.com';

	var CONFIG = { layout: 'month_view', useSlotsViewOnSmallScreen: 'true', theme: 'light' };

	/* Live's palette for both of Cal's themes, so a dark-mode visitor still gets the light calendar. */
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

	var UI = {
		theme: 'light',
		hideEventTypeDetails: false,
		layout: 'month_view',
		cssVarsPerTheme: { light: PALETTE, dark: PALETTE }
	};

	/* Cal.com's published loader stub, unminified. */
	function installStub() {
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
				document.head.appendChild(document.createElement('script')).src = EMBED;
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

	function mount(el) {
		if (el.getAttribute('data-ap-cal-ready')) {
			return;
		}
		var link = el.getAttribute('data-ap-cal');
		var ns = el.getAttribute('data-ap-cal-ns') || 'asset-panda-demo';
		if (!link) {
			return;
		}
		el.setAttribute('data-ap-cal-ready', '1');

		installStub();
		var Cal = window.Cal;
		if (!Cal.ns || !Cal.ns[ns]) {
			Cal('init', ns, { origin: ORIGIN });
		}
		Cal.config = Cal.config || {};
		Cal.config.forwardQueryParams = true;
		Cal.ns[ns]('inline', { elementOrSelector: el, config: CONFIG, calLink: link });
		Cal.ns[ns]('ui', UI);
	}

	function init() {
		var nodes = document.querySelectorAll('[data-ap-cal]');
		for (var i = 0; i < nodes.length; i++) {
			mount(nodes[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
