/**
 * thanks-calendly — picks the booking track and mounts its Calendly scheduler.
 *
 * Each section renders every track; the first track whose sessionStorage key
 * (data-when-key) holds one of its values (data-when-values) is shown,
 * otherwise the default track (live's page script did the same with jQuery).
 * The scheduler loads Calendly's widget script when the card nears the
 * viewport and draws the inline widget into the chosen track only, so a
 * visitor never loads two calendars. Calendly's own postMessage events reach
 * the page as usual (the site's GTM Calendly listener reads them).
 */
(function () {
	'use strict';

	var SCRIPT = 'https://assets.calendly.com/assets/external/widget.js';
	var loading = null;

	function loadCalendly() {
		if (window.Calendly && window.Calendly.initInlineWidget) {
			return Promise.resolve();
		}
		if (loading) {
			return loading;
		}
		loading = new Promise(function (resolve, reject) {
			var tag = document.createElement('script');
			tag.src = SCRIPT;
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
		loading.catch(function () {
			loading = null;
		});
		return loading;
	}

	function stored(key) {
		try {
			return window.sessionStorage ? window.sessionStorage.getItem(key) : null;
		} catch (e) {
			return null;
		}
	}

	function pick(section) {
		var tracks = section.querySelectorAll('[data-ap-calendly-track]');
		var chosen = null;
		Array.prototype.forEach.call(tracks, function (track) {
			if (chosen) {
				return;
			}
			var key = track.getAttribute('data-when-key');
			if (!key) {
				return;
			}
			var values = [];
			try {
				values = JSON.parse(track.getAttribute('data-when-values') || '[]') || [];
			} catch (e) {
				values = [];
			}
			var value = stored(key);
			if (value !== null && values.indexOf(value) !== -1) {
				chosen = track;
			}
		});
		chosen = chosen || section.querySelector('[data-ap-calendly-default]') || tracks[0];
		Array.prototype.forEach.call(tracks, function (track) {
			track.hidden = track !== chosen;
		});
		return chosen;
	}

	function mount(track) {
		var box = track && track.querySelector('[data-ap-calendly]');
		if (!box || box.getAttribute('data-ap-calendly-mounted')) {
			return;
		}
		box.setAttribute('data-ap-calendly-mounted', '1');
		loadCalendly().then(function () {
			box.innerHTML = '';
			window.Calendly.initInlineWidget({ url: box.getAttribute('data-ap-calendly'), parentElement: box });
		}).catch(function () {
			box.removeAttribute('data-ap-calendly-mounted');
		});
	}

	function boot() {
		var sections = document.querySelectorAll('.ap-thanks-calendly');
		Array.prototype.forEach.call(sections, function (section) {
			var track = pick(section);
			if (!track) {
				return;
			}
			if (!('IntersectionObserver' in window)) {
				mount(track);
				return;
			}
			var io = new IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						io.disconnect();
						mount(track);
					}
				});
			}, { rootMargin: '600px 0px' });
			io.observe(section);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
