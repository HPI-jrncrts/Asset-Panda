/**
 * form-hero — draws the HubSpot form into each [data-ap-form-hero] mount.
 *
 * v2 mounts: HubSpot's classic embed script (region host) and
 * hbspt.forms.create({ css: "" }), so the form is drawn unstyled into the page
 * and style.css styles it, as live does. developer mounts: the server already
 * rendered HubSpot's <div class="hs-form-html">; loading the developer embed
 * script for the portal draws the form into it.
 *
 * A mount loads its script when it nears the viewport (the heroes are already
 * in view, so they load at once). If no form has appeared 6 seconds after the
 * script was requested, the "Form not loading?" box is shown, as on live.
 * Nothing here submits anything or changes where the form goes.
 */
(function () {
	'use strict';

	var loading = {};

	function load(src, ready) {
		if (ready()) {
			return Promise.resolve();
		}
		if (loading[src]) {
			return loading[src];
		}
		loading[src] = new Promise(function (resolve, reject) {
			var tag = document.createElement('script');
			tag.src = src;
			tag.async = true;
			tag.charset = 'utf-8';
			tag.onload = function () {
				var tries = 0;
				(function wait() {
					if (ready()) {
						resolve();
					} else if (tries++ < 100) {
						window.setTimeout(wait, 100);
					} else {
						reject(new Error('hubspot'));
					}
				})();
			};
			tag.onerror = reject;
			document.head.appendChild(tag);
		});
		loading[src].catch(function () {
			loading[src] = null;
		});
		return loading[src];
	}

	function v2Script(region) {
		return region && region !== 'na1'
			? 'https://js-' + region + '.hsforms.net/forms/embed/v2.js'
			: 'https://js.hsforms.net/forms/embed/v2.js';
	}

	function developerScript(region, portal) {
		return 'https://js' + (region && region !== 'na1' ? '-' + region : '') + '.hsforms.net/forms/embed/developer/' + portal + '.js';
	}

	function drawn(el) {
		return !!el.querySelector('form, iframe, .hsfc-Form, .hsfc-Step');
	}

	function watchFallback(el) {
		var box = el.parentNode ? el.parentNode.querySelector('[data-ap-form-fallback]') : null;
		if (!box) {
			return;
		}
		window.setTimeout(function () {
			if (!drawn(el)) {
				box.hidden = false;
			}
		}, 6000);
	}

	function relabel(el) {
		var label = el.getAttribute('data-ap-hs-submit');
		if (!label) {
			return;
		}
		var input = el.querySelector("input[type='submit']");
		var button = el.querySelector("button[type='submit']");
		if (input) {
			input.value = label;
		}
		if (button) {
			button.textContent = label;
		}
	}

	function mount(el) {
		if (el.getAttribute('data-ap-hs-mounted')) {
			return;
		}
		el.setAttribute('data-ap-hs-mounted', '1');
		var region = el.getAttribute('data-ap-hs-region') || 'na1';
		var portal = el.getAttribute('data-ap-hs-portal');
		var form = el.getAttribute('data-ap-hs-form');
		watchFallback(el);

		if (el.getAttribute('data-ap-hs-embed') === 'developer') {
			// The developer script draws every .hs-form-html on the page when it
			// runs; one copy per portal is enough.
			var src = developerScript(region, portal);
			if (!loading[src]) {
				loading[src] = true;
				var tag = document.createElement('script');
				tag.src = src;
				tag.defer = true;
				document.head.appendChild(tag);
			}
			return;
		}

		load(v2Script(region), function () {
			return !!(window.hbspt && window.hbspt.forms && window.hbspt.forms.create);
		}).then(function () {
			window.hbspt.forms.create({
				region: region,
				portalId: portal,
				formId: form,
				target: '#' + el.id,
				css: '',
				onFormReady: function () {
					try {
						relabel(el);
					} catch (e) {}
				}
			});
		}).catch(function () {
			el.removeAttribute('data-ap-hs-mounted');
		});
	}

	function boot() {
		document.addEventListener('click', function (event) {
			var link = event.target && event.target.closest ? event.target.closest('[data-ap-form-reload]') : null;
			if (link) {
				event.preventDefault();
				window.location.reload();
			}
		});
		var mounts = document.querySelectorAll('[data-ap-form-hero][data-ap-hs-form]');
		if (!mounts.length) {
			return;
		}
		if (!('IntersectionObserver' in window)) {
			Array.prototype.forEach.call(mounts, mount);
			return;
		}
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					io.unobserve(entry.target);
					mount(entry.target);
				}
			});
		}, { rootMargin: '600px 0px' });
		Array.prototype.forEach.call(mounts, function (el) {
			io.observe(el);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
