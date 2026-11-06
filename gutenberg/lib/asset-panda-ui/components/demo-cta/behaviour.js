/**
 * demo-cta — mounts the HubSpot demo form.
 *
 * Each mount point carries its portal, form and region as data attributes
 * (validated server-side). HubSpot's embed script loads once, only when a page
 * actually has a form, and in idle time once the visitor scrolls (see boot).
 */
(function () {
	'use strict';

	var SCRIPT = 'https://js.hsforms.net/forms/embed/v2.js';

	/*
	 * HubSpot draws the form inside an iframe, so page CSS cannot reach it.
	 * This stylesheet is written into that iframe once the form is ready —
	 * the same styles the live vertical pages inject.
	 */
	var FORM_CSS = "html,body{background:transparent !important;margin:0 !important;padding:0 !important}.hs-form-private{font-family:Manrope,Arial,sans-serif !important}.hs-form-private fieldset{max-width:none !important;width:100% !important;display:flex !important;flex-direction:row !important;gap:16px !important;margin:0 !important}.hs-form-private fieldset.form-columns-1,.hs-form-private fieldset.form-columns-2{margin:0 0 16px 0 !important}.hs-form-private fieldset:not(:has(.hs-input:not([type=hidden]))){display:none !important}.hs-form-private .hs-form-field{flex:1 1 0 !important;width:auto !important;float:none !important;padding:0 !important;margin:0 !important}.hs-form-private .input{margin:0 !important}.hs-form-private .hs-form-field>label{position:absolute !important;width:1px !important;height:1px !important;padding:0 !important;margin:-1px !important;overflow:hidden !important;clip:rect(0 0 0 0) !important;white-space:nowrap !important;border:0 !important}.hs-form-private .hs-input:not([type=checkbox]):not([type=radio]){width:100% !important;box-sizing:border-box !important;background:#f9fafb !important;border:1px solid #edeef1 !important;border-radius:4px !important;padding:12px 12px 13px !important;font-family:inherit !important;font-size:14px !important;line-height:1.5 !important;color:rgba(0,0,0,.85) !important;box-shadow:none !important;height:auto !important}.hs-form-private .hs-input::placeholder{color:rgba(0,0,0,.6) !important;opacity:1 !important}.hs-form-private .hs-input:focus{outline:none !important;border-color:#5044e1 !important;box-shadow:0 0 0 3px rgba(80,68,225,.15) !important}.hs-form-private textarea.hs-input{height:120px !important;min-height:120px !important;padding:12px !important;resize:vertical !important}.hs-form-private .hs_submit,.hs-form-private .actions{padding:0 !important;margin:0 !important}.hs-form-private .hs-button,.hs-form-private input[type=submit]{display:inline-flex !important;align-items:center !important;justify-content:center !important;background:linear-gradient(60.14deg,#5044e1 0%,#00a991 70.44%) !important;border:1px solid #5044e1 !important;border-radius:4px !important;min-height:48px !important;padding:0 24px !important;font-family:inherit !important;font-weight:600 !important;font-size:16px !important;line-height:1.2 !important;color:#fff !important;width:auto !important;box-shadow:none !important;cursor:pointer !important}.hs-form-private .hs-button:hover{filter:brightness(1.08) !important}.hs-form-private .hs-error-msgs{list-style:none !important;padding:0 !important;margin:0 !important}.hs-form-private .hs-error-msg,.hs-form-private .hs-error-msgs label{color:#ffd9d9 !important;font-size:12px !important;margin-top:6px !important}.hs-form-private .hs-richtext,.hs-form-private .legal-consent-container{color:rgba(255,255,255,.7) !important;font-size:12px !important}@media (max-width:420px){.hs-form-private fieldset{flex-direction:column !important}}";

	function styleForm(form) {
		var node = form && form[0] ? form[0] : form;
		var doc = node && node.ownerDocument;
		if (!doc || doc.getElementById('ap-demo-cta-hs-css')) {
			return;
		}
		var style = doc.createElement('style');
		style.id = 'ap-demo-cta-hs-css';
		style.appendChild(doc.createTextNode(FORM_CSS));
		(doc.head || doc.documentElement).appendChild(style);
		styleButton(doc);
	}

	/*
	 * The sitewide CTA button scale (ui/src/tokens.css, QA 2026-10-29: every CTA button takes the site header's nav
	 * button metrics) for the submit of a form drawn inside HubSpot's iframe, which the page's stylesheets cannot
	 * reach. The iframe's own media queries see the iframe's width, not the page's, so the size is chosen from the
	 * page's width when the form is ready and again whenever the page crosses 1100px. A raw form drawn into the page
	 * itself is already sized by tokens.css.
	 */
	var BTN_WIDE = '.hs-form-private .hs-button,.hs-form-private input[type=submit]{min-height:42.8px !important;padding:12px 16px !important;border-radius:4px !important;font-size:14px !important;font-weight:800 !important;line-height:1.2 !important;letter-spacing:normal !important;text-transform:none !important}';
	var BTN_NARROW = '.hs-form-private .hs-button,.hs-form-private input[type=submit]{min-height:36px !important;padding:9px 12px !important;border-radius:4px !important;font-size:13px !important;font-weight:800 !important;line-height:1.2 !important;letter-spacing:normal !important;text-transform:none !important}';
	function styleButton(doc) {
		if (!doc || doc === document || doc.getElementById('ap-demo-cta-hs-btn')) {
			return;
		}
		var style = doc.createElement('style');
		style.id = 'ap-demo-cta-hs-btn';
		var mq = window.matchMedia ? window.matchMedia('(max-width: 1100px)') : null;
		var apply = function () {
			style.textContent = mq && mq.matches ? BTN_NARROW : BTN_WIDE;
		};
		apply();
		(doc.head || doc.documentElement).appendChild(style);
		if (mq && mq.addEventListener) {
			mq.addEventListener('change', apply);
		} else if (mq && mq.addListener) {
			mq.addListener(apply);
		}
	}
	/*
	 * The live vertical pages relabel HubSpot's fields once the form is drawn:
	 * placeholder text per field name (a message box without one takes the
	 * "textarea" entry) and their own submit label. Both arrive as data
	 * attributes; anything missing leaves HubSpot's own text alone.
	 * HubSpot hands the form over bare or in a jQuery wrapper, and only a
	 * wrapper is unwrapped: a bare <form>'s [0] is its first fieldset or field,
	 * not the form, which left every later field and the submit label as-is.
	 */
	function relabelForm(form, el) {
		var node = form && !form.nodeType && form[0] ? form[0] : form;
		if (!node || !node.querySelectorAll) {
			return;
		}
		var map = {};
		try {
			map = JSON.parse(el.getAttribute('data-ap-hs-placeholders') || '{}') || {};
		} catch (e) {
			map = {};
		}
		Array.prototype.forEach.call(node.querySelectorAll('input[name], select[name], textarea[name]'), function (input) {
			var name = (input.getAttribute('name') || '').toLowerCase();
			var type = (input.getAttribute('type') || '').toLowerCase();
			var tag = input.tagName.toLowerCase();
			if (type === 'hidden' || type === 'submit' || type === 'checkbox' || type === 'radio' || tag === 'select') {
				return;
			}
			if (tag === 'textarea' && !input.getAttribute('placeholder') && map.textarea) {
				input.setAttribute('placeholder', map.textarea);
			} else if (Object.prototype.hasOwnProperty.call(map, name)) {
				input.setAttribute('placeholder', map[name]);
			}
		});
		var label = el.getAttribute('data-ap-hs-submit');
		if (label) {
			var submitInput = node.querySelector('input[type="submit"]');
			var submitButton = node.querySelector('button[type="submit"]');
			if (submitInput) {
				submitInput.value = label;
			}
			if (submitButton) {
				submitButton.textContent = label;
			}
		}
	}

	/*
	 * White-card panels only (panel "card", the Partners page). A raw-HTML HubSpot form is drawn into the
	 * page, and HubSpot can swap in a fresh <form> after onFormReady, which brings its own placeholders and
	 * submit label back. Watch the mount and, whenever the current form has drifted, write ours again:
	 * placeholders by field name and the submit label only, never anything a visitor typed. Our own writes
	 * are dropped from the queue, so the observer cannot loop. Forms in other demo cards are left as before.
	 */
	function keepLabels(el) {
		if (!window.MutationObserver) {
			return;
		}
		var map = {};
		try {
			map = JSON.parse(el.getAttribute('data-ap-hs-placeholders') || '{}') || {};
		} catch (e) {
			map = {};
		}
		var label = el.getAttribute('data-ap-hs-submit') || '';
		var apply = function () {
			var form = el.querySelector('form');
			if (!form) {
				return;
			}
			Array.prototype.forEach.call(form.querySelectorAll('input[name], textarea[name]'), function (input) {
				var name = (input.getAttribute('name') || '').toLowerCase();
				var type = (input.getAttribute('type') || '').toLowerCase();
				if (type === 'hidden' || type === 'submit' || !Object.prototype.hasOwnProperty.call(map, name)) {
					return;
				}
				if (input.getAttribute('placeholder') !== map[name]) {
					input.setAttribute('placeholder', map[name]);
				}
				/*
				 * The Get a Demo field (QA 2026-10-29 reskin): its label sits inside the field and rises to the
				 * top edge on focus or once filled. HubSpot draws this form's labels empty, so an empty label
				 * takes the same words as the placeholder, and the field is marked data-ap-float for the
				 * stylesheet (style.css "Partners form"). Without this mark the placeholder stays visible.
				 */
				var field = input.closest ? input.closest('.hs-form-field') : null;
				var fieldLabel = field ? field.querySelector(':scope > label') : null;
				if (fieldLabel && fieldLabel.textContent.trim() === '') {
					(fieldLabel.querySelector('span') || fieldLabel).textContent = map[name];
				}
				if (fieldLabel && fieldLabel.textContent.trim() !== '' && !field.hasAttribute('data-ap-float')) {
					field.setAttribute('data-ap-float', '');
				}
			});
			var submit = form.querySelector('input[type="submit"]');
			if (label && submit && submit.value !== label) {
				submit.value = label;
			}
		};
		var observer = new MutationObserver(function () {
			apply();
			observer.takeRecords();
		});
		observer.observe(el, { subtree: true, childList: true, attributes: true, attributeFilter: ['placeholder', 'value'] });
		apply();
		observer.takeRecords();
	}

	var loading = null;

	function loadHubSpot() {
		if (window.hbspt && window.hbspt.forms) {
			return Promise.resolve();
		}
		if (loading) {
			return loading;
		}
		loading = new Promise(function (resolve, reject) {
			var tag = document.createElement('script');
			tag.src = SCRIPT;
			tag.async = true;
			tag.onload = resolve;
			tag.onerror = reject;
			document.head.appendChild(tag);
		});
		return loading;
	}

	function mount(el) {
		if (el.getAttribute('data-ap-hs-mounted')) {
			return;
		}
		el.setAttribute('data-ap-hs-mounted', '1');
		loadHubSpot().then(function () {
			window.hbspt.forms.create({
				region: el.getAttribute('data-ap-hs-region') || 'na1',
				portalId: el.getAttribute('data-ap-hs-portal'),
				formId: el.getAttribute('data-ap-hs-form'),
				target: '#' + el.id,
				onFormReady: function (form) {
					try {
						styleForm(form);
					} catch (e) {}
					try {
						relabelForm(form, el);
					} catch (e) {}
					try {
						if (el.closest && el.closest('.ap-demo-cta__panel--card')) {
							keepLabels(el);
						}
					} catch (e) {}
				}
			});
		}).catch(function () {
			el.removeAttribute('data-ap-hs-mounted');
			el.removeAttribute('data-ap-hs-queued');
		});
	}

	/*
	 * HubSpot's embed costs 0.5-1.5 s of long frames on a phone. Loading it as
	 * the card neared the viewport landed that work mid-scroll (QA 2026-10-28,
	 * motion agent's R5). It now starts in idle time, off the scroll path:
	 * on the visitor's first scroll (long before they reach the card), when the
	 * card is within ~2 screens, or at once on the first pointer / focus inside
	 * the card. A page load with no scroll (a PageSpeed run) never pays for it.
	 */
	function schedule(el) {
		if (el.getAttribute('data-ap-hs-mounted') || el.getAttribute('data-ap-hs-queued')) {
			return;
		}
		el.setAttribute('data-ap-hs-queued', '1');
		var run = function () {
			mount(el);
		};
		if ('requestIdleCallback' in window) {
			window.requestIdleCallback(run, { timeout: 3000 });
		} else {
			window.setTimeout(run, 300);
		}
	}

	function boot() {
		var mounts = Array.prototype.slice.call(document.querySelectorAll('.ap-demo-cta__form[data-ap-hs-form]'));
		if (!mounts.length) {
			return;
		}
		mounts.forEach(function (el) {
			var card = el.closest('.ap-demo-cta') || el;
			var now = function () {
				mount(el);
			};
			card.addEventListener('pointerenter', now, { once: true });
			card.addEventListener('focusin', now, { once: true });
		});
		var onScroll = function () {
			window.removeEventListener('scroll', onScroll);
			mounts.forEach(schedule);
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		if (!('IntersectionObserver' in window)) {
			mounts.forEach(schedule);
			return;
		}
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					io.unobserve(entry.target);
					schedule(entry.target);
				}
			});
		}, { rootMargin: '1500px 0px' });
		mounts.forEach(function (el) {
			io.observe(el);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
