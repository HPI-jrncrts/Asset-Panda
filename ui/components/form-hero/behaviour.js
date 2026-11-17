/**
 * form-hero — draws the HubSpot form into each [data-ap-form-hero] mount and
 * keeps it in the kit's form skin.
 *
 * v2 mounts: HubSpot's classic embed script (region host) and
 * hbspt.forms.create({ css: "", cssRequired: "" }), so HubSpot attaches no
 * stylesheet at all and style.css draws the form. developer mounts: the server
 * already rendered HubSpot's <div class="hs-form-html">; loading the developer
 * embed script for the portal draws the form into it.
 *
 * The skin shows each field's label as its placeholder: once a form is drawn
 * (and again whenever HubSpot re-renders it, e.g. after a validation error)
 * every text-like field that has no placeholder of its own gets its label
 * text, and the submit button takes the component's label. Should HubSpot
 * draw a form inside an iframe (a form not set as raw HTML), the same skin is
 * written into that iframe, as the demo card does.
 *
 * A mount loads its script when it nears the viewport (the heroes are already
 * in view, so they load at once). If no form has appeared 6 seconds after the
 * script was requested, the "Form not loading?" box is shown, as on live.
 * Nothing here submits anything. Where the form goes is HubSpot's setting,
 * unless the mount names an after_submit_url (see goAfter below).
 */
(function () {
	'use strict';

	var loading = {};

	/*
	 * The skin for a form HubSpot draws inside its own iframe, where page CSS
	 * cannot reach: the same rules as style.css, flattened (the iframe has no
	 * tokens.css).
	 */
	var FRAME_CSS = 'html,body{margin:0 !important;padding:0 !important;background:transparent !important}'
		+ 'form.hs-form{display:flex !important;flex-direction:column !important;gap:12px !important;width:100% !important;max-width:100% !important;margin:0 !important;padding:0 !important;font-family:Manrope,system-ui,sans-serif !important}'
		+ 'form.hs-form fieldset{display:flex !important;gap:12px !important;width:100% !important;max-width:100% !important;margin:0 !important;padding:0 !important;border:0 !important}'
		+ 'form.hs-form fieldset:not(:has(.hs-input:not([type="hidden"]),.hs-richtext,.legal-consent-container)){display:none !important}'
		+ 'form.hs-form .hs-form-field,form.hs-form .hs_submit,form.hs-form .actions{position:relative;flex:1 1 0 !important;width:auto !important;min-width:0 !important;max-width:none !important;float:none !important;margin:0 !important;padding:0 !important}'
		+ 'form.hs-form .input{margin:0 !important}'
		+ 'form.hs-form .hs-form-field>label:not(.hs-error-msg){position:absolute !important;width:1px !important;height:1px !important;margin:-1px !important;padding:0 !important;overflow:hidden !important;clip:rect(0,0,0,0) !important;white-space:nowrap !important;border:0 !important}'
		+ 'form.hs-form .hs-fieldtype-select>label,form.hs-form .hs-fieldtype-checkbox>label,form.hs-form .hs-fieldtype-booleancheckbox>label,form.hs-form .hs-fieldtype-radio>label,form.hs-form .hs-fieldtype-date>label,form.hs-form .hs-fieldtype-file>label{position:static !important;display:block !important;width:auto !important;height:auto !important;margin:0 0 6px !important;overflow:visible !important;clip:auto !important;white-space:normal !important;color:#25272c !important;font-size:14px !important;font-weight:600 !important;line-height:1.3 !important}'
		+ 'form.hs-form .hs-form-required{margin-left:2px;color:#e61c55}'
		+ 'form.hs-form input.hs-input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="file"]),form.hs-form select.hs-input,form.hs-form textarea.hs-input{display:block !important;width:100% !important;max-width:100% !important;box-sizing:border-box !important;height:46px !important;min-height:46px !important;margin:0 !important;padding:0 14px !important;border:1px solid #e5edf3 !important;border-radius:6px !important;background:#f5f8fa !important;color:#25272c !important;font-family:inherit !important;font-size:15px !important;font-weight:400 !important;line-height:1.4 !important;box-shadow:none !important;outline:none !important}'
		+ 'form.hs-form textarea.hs-input{height:120px !important;min-height:120px !important;padding:12px 14px !important;resize:vertical !important}'
		+ 'form.hs-form .hs-input::placeholder{color:#5b616e !important;opacity:1 !important}'
		+ 'form.hs-form .hs-input:focus{border-color:#5044e1 !important;box-shadow:0 0 0 3px rgba(80,68,225,.14) !important}'
		+ 'form.hs-form .hs-input.error,form.hs-form .hs-input.invalid{border-color:#d93025 !important}'
		+ 'form.hs-form ul.inputs-list{display:grid !important;gap:8px !important;margin:0 !important;padding:0 !important;list-style:none !important}'
		+ 'form.hs-form ul.inputs-list label{display:flex !important;gap:10px !important;align-items:flex-start !important;margin:0 !important;color:#4b5563 !important;font-size:14px !important;font-weight:400 !important;line-height:1.5 !important}'
		+ 'form.hs-form .hs-error-msgs{margin:6px 0 0 !important;padding:0 !important;list-style:none !important}'
		+ 'form.hs-form .hs-error-msg,form.hs-form .hs-error-msgs label,form.hs-form .hs_error_rollup label{position:static !important;display:block !important;width:auto !important;height:auto !important;margin:0 !important;overflow:visible !important;clip:auto !important;white-space:normal !important;color:#d93025 !important;font-size:12px !important;font-weight:400 !important;line-height:1.4 !important}'
		+ 'form.hs-form .hs-richtext,form.hs-form .legal-consent-container,form.hs-form .legal-consent-container p{margin:0 !important;color:#5b616e !important;font-size:13px !important;line-height:1.5 !important}'
		+ 'form.hs-form input[type="submit"],form.hs-form .hs-button{display:block !important;width:100% !important;max-width:100% !important;min-height:52px !important;height:auto !important;margin:4px 0 0 !important;padding:14px 20px !important;border:0 !important;border-radius:6px !important;background:linear-gradient(35deg,#5044e1 0%,#00a991 70%) !important;color:#fff !important;font-family:inherit !important;font-size:16px !important;font-weight:700 !important;line-height:1.2 !important;text-align:center !important;text-shadow:none !important;box-shadow:none !important;cursor:pointer !important;-webkit-appearance:none !important;appearance:none !important}'
		+ 'form.hs-form input[type="submit"]:hover,form.hs-form .hs-button:hover{box-shadow:0 8px 20px rgba(80,68,225,.25) !important;filter:brightness(1.06)}'
		+ 'form.hs-form .submitted-message{color:#25272c !important;font-size:16px !important;font-weight:600 !important;line-height:1.5 !important}'
		+ '@media (max-width:767px){form.hs-form fieldset{flex-direction:column !important}}';

	var PLACEHOLDER_FIELDS = 'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="submit"]):not([type="button"]), textarea';
	var AUTOCOMPLETE = {
		email: 'email',
		phone: 'tel',
		mobilephone: 'tel',
		firstname: 'given-name',
		lastname: 'family-name',
		company: 'organization',
		jobtitle: 'organization-title'
	};

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
		return !!el.querySelector('form, iframe.hs-form-iframe, .hsfc-Form, .hsfc-Step');
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

	/*
	 * The visible name of a field: HubSpot's label (text plus its required
	 * mark) for v2 forms, .hsfc-FieldLabel for the developer embed.
	 */
	function labelText(input) {
		var field = input.closest('.hs-form-field') || input.closest('[class*="hsfc-"][class$="Field"]') || input.parentElement;
		var label = null;
		if (field) {
			label = field.querySelector('label:not(.hs-error-msg), .hsfc-FieldLabel');
		}
		if (!label && input.id && input.ownerDocument) {
			label = input.ownerDocument.querySelector('label[for="' + input.id.replace(/"/g, '') + '"]');
		}
		return label ? label.textContent.replace(/\s+/g, ' ').trim() : '';
	}

	/*
	 * One pass over a drawn form: every text-like field without a placeholder
	 * shows its label there, common fields get autocomplete hints, the submit
	 * button takes the component's label. Writes only when something differs,
	 * so re-running after each HubSpot re-render settles at once.
	 */
	function skin(el, root) {
		Array.prototype.forEach.call(root.querySelectorAll(PLACEHOLDER_FIELDS), function (input) {
			if (!input.getAttribute('placeholder')) {
				var text = labelText(input);
				if (text) {
					input.setAttribute('placeholder', text);
				}
			}
			var name = (input.getAttribute('name') || '').toLowerCase().replace(/^.*\//, '');
			var auto = AUTOCOMPLETE[name] || (input.type === 'email' ? 'email' : input.type === 'tel' ? 'tel' : '');
			if (auto && !input.getAttribute('autocomplete')) {
				input.setAttribute('autocomplete', auto);
			}
		});
		var label = el.getAttribute('data-ap-hs-submit');
		if (label) {
			Array.prototype.forEach.call(root.querySelectorAll("input[type='submit'], button[type='submit']"), function (button) {
				if (button.tagName === 'INPUT') {
					if (button.value !== label) {
						button.value = label;
					}
				} else if (button.textContent !== label) {
					button.textContent = label;
				}
			});
		}
	}

	/* A form HubSpot drew in its own iframe gets the skin written into it. */
	function skinFrame(doc) {
		if (!doc || doc.getElementById('ap-form-hero-skin')) {
			return;
		}
		var style = doc.createElement('style');
		style.id = 'ap-form-hero-skin';
		style.appendChild(doc.createTextNode(FRAME_CSS));
		(doc.head || doc.documentElement).appendChild(style);
	}

	function frameDocument(el) {
		var frame = el.querySelector('iframe.hs-form-iframe, iframe[id^="hs-form-iframe"]');
		if (!frame) {
			return null;
		}
		try {
			return frame.contentDocument || null;
		} catch (e) {
			return null;
		}
	}

	function settle(el) {
		var doc = frameDocument(el);
		if (doc) {
			skinFrame(doc);
			if (doc.body) {
				skin(el, doc.body);
			}
		}
		skin(el, el);
	}

	/*
	 * HubSpot re-renders its fields (validation, enrichment); re-apply the
	 * skin after each burst, once per frame. The observer also catches the
	 * developer embed, which draws without a callback.
	 */
	function observe(el) {
		if (!('MutationObserver' in window) || el.getAttribute('data-ap-hs-observed')) {
			return;
		}
		el.setAttribute('data-ap-hs-observed', '1');
		var pending = false;
		new MutationObserver(function () {
			if (pending) {
				return;
			}
			pending = true;
			window.requestAnimationFrame(function () {
				pending = false;
				settle(el);
			});
		}).observe(el, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'placeholder', 'src'] });
	}

	/*
	 * after_submit_url (data-ap-hs-after): once HubSpot reports the form sent, the
	 * visitor goes there. {trial_token} in it becomes a new random token that is
	 * first written into the form's trial_token field, so the app can find this
	 * submission: the trial sign-up mechanism (the GTM tag on
	 * /start-a-free-trial-email-only/), built in so it also works where GTM does
	 * not run (staging, signed-in staff, blocked tag managers). Hidden fields
	 * named like a query parameter of the page (aws_marketplace_ref) are filled
	 * by HubSpot itself. A "Not redirected?" link is left in case the browser
	 * holds the page.
	 */
	var TOKEN_FIELD = 'trial_token';
	var AFTER_OK = /^(https?:\/\/[^\s\/]+|\/(?![\/\\]))/i;

	function newToken() {
		var c = window.crypto;
		if (c && typeof c.randomUUID === 'function') {
			return c.randomUUID();
		}
		var b = new Uint8Array(16);
		if (c && c.getRandomValues) {
			c.getRandomValues(b);
		} else {
			for (var i = 0; i < 16; i++) {
				b[i] = Math.floor(Math.random() * 256);
			}
		}
		b[6] = (b[6] & 15) | 64;
		b[8] = (b[8] & 63) | 128;
		var h = Array.prototype.map.call(b, function (x) {
			return (x + 256).toString(16).slice(1);
		}).join('');
		return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
	}

	function wantsToken(el) {
		return (el.getAttribute('data-ap-hs-after') || '').indexOf('{trial_token}') !== -1;
	}

	function tokenOf(el) {
		if (!el.apTrialToken) {
			el.apTrialToken = newToken();
		}
		return el.apTrialToken;
	}

	function afterUrl(el) {
		var url = el.getAttribute('data-ap-hs-after') || '';
		if (!AFTER_OK.test(url)) {
			return '';
		}
		return wantsToken(el) ? url.split('{trial_token}').join(encodeURIComponent(tokenOf(el))) : url;
	}

	function goAfter(el) {
		var url = afterUrl(el);
		if (!url || el.getAttribute('data-ap-hs-sent')) {
			return;
		}
		el.setAttribute('data-ap-hs-sent', '1');
		var note = document.createElement('p');
		note.className = 'ap-form-hero__after';
		var link = document.createElement('a');
		link.href = url;
		link.textContent = 'Continue';
		note.appendChild(document.createTextNode('Not redirected? '));
		note.appendChild(link);
		el.appendChild(note);
		window.location.assign(url);
	}

	/* The kit mount a developer-embed form belongs to: its instance's root, else the form ID. */
	function mountOfForm(form) {
		var id = '';
		var formId = '';
		try {
			id = String(form.getInstanceId() || '');
		} catch (e) {}
		try {
			formId = String(form.getFormId() || '').toLowerCase();
		} catch (e) {}
		var node = id ? (document.getElementById(id + '-r') || document.getElementById(id)) : null;
		var hit = node && node.closest ? node.closest('[data-ap-form-hero]') : null;
		if (hit) {
			return hit;
		}
		var all = document.querySelectorAll('[data-ap-form-hero][data-ap-hs-after]');
		for (var i = 0; i < all.length; i++) {
			if (formId && all[i].getAttribute('data-ap-hs-form') === formId) {
				return all[i];
			}
		}
		return null;
	}

	function formFromEvent(event) {
		try {
			return window.HubSpotFormsV4 ? window.HubSpotFormsV4.getFormFromEvent(event) : null;
		} catch (e) {
			return null;
		}
	}

	function setDeveloperToken(form, el) {
		try {
			form.setFieldValue('0-1/' + TOKEN_FIELD, tokenOf(el));
			el.setAttribute('data-ap-hs-token', '1');
			return true;
		} catch (e) {
			return false;
		}
	}

	var afterHooked = false;

	function hookDeveloperAfter() {
		if (afterHooked) {
			return;
		}
		afterHooked = true;
		window.addEventListener('hs-form-event:on-ready', function (event) {
			var form = formFromEvent(event);
			var el = form ? mountOfForm(form) : null;
			if (el && el.getAttribute('data-ap-hs-after') && wantsToken(el)) {
				setDeveloperToken(form, el);
			}
		});
		window.addEventListener('hs-form-event:on-submission:success', function (event) {
			var form = formFromEvent(event);
			var el = form ? mountOfForm(form) : null;
			if (el && el.getAttribute('data-ap-hs-after')) {
				goAfter(el);
			}
		});
	}

	/* The form may have become ready before the listener: set the token from the live forms too (as the GTM tag does). */
	function pollDeveloperToken(el) {
		if (!wantsToken(el)) {
			return;
		}
		var tries = 0;
		(function attempt() {
			if (el.getAttribute('data-ap-hs-token')) {
				return;
			}
			var forms = [];
			try {
				forms = window.HubSpotFormsV4 ? window.HubSpotFormsV4.getForms() : [];
			} catch (e) {}
			for (var i = 0; i < forms.length; i++) {
				if (mountOfForm(forms[i]) === el && setDeveloperToken(forms[i], el)) {
					return;
				}
			}
			if (tries++ < 60) {
				window.setTimeout(attempt, 250);
			}
		})();
	}

	/* v2 (classic) forms: the token goes into the hidden input itself. */
	function setV2Token(el) {
		var doc = frameDocument(el);
		var roots = doc ? [doc, el] : [el];
		roots.forEach(function (root) {
			Array.prototype.forEach.call(root.querySelectorAll('input[name="' + TOKEN_FIELD + '"]'), function (input) {
				if (input.value !== tokenOf(el)) {
					input.value = tokenOf(el);
					input.dispatchEvent(new Event('input', { bubbles: true }));
					input.dispatchEvent(new Event('change', { bubbles: true }));
				}
			});
		});
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
		observe(el);

		var after = AFTER_OK.test(el.getAttribute('data-ap-hs-after') || '');

		if (el.getAttribute('data-ap-hs-embed') === 'developer') {
			if (after) {
				hookDeveloperAfter();
				pollDeveloperToken(el);
			}
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
				cssRequired: '',
				onFormReady: function () {
					[0, 250, 900].forEach(function (delay) {
						window.setTimeout(function () {
							try {
								settle(el);
								if (after && wantsToken(el)) {
									setV2Token(el);
								}
							} catch (e) {}
						}, delay);
					});
				},
				onBeforeFormSubmit: function () {
					if (after && wantsToken(el)) {
						try {
							setV2Token(el);
						} catch (e) {}
					}
				},
				onFormSubmitted: function () {
					if (after) {
						goAfter(el);
					}
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
