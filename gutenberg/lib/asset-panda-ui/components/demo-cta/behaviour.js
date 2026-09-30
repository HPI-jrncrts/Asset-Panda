/**
 * demo-cta — mounts the HubSpot demo form.
 *
 * Each mount point carries its portal, form and region as data attributes
 * (validated server-side). HubSpot's embed script loads once, only when a page
 * actually has a form, and only when the card nears the viewport.
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
				}
			});
		}).catch(function () {
			el.removeAttribute('data-ap-hs-mounted');
		});
	}

	function boot() {
		var mounts = document.querySelectorAll('.ap-demo-cta__form[data-ap-hs-form]');
		if (!mounts.length) {
			return;
		}
		if (!('IntersectionObserver' in window)) {
			mounts.forEach(mount);
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
