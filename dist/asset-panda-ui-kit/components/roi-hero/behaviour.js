/**
 * roi-hero — draws the HubSpot quote form and keeps it short.
 *
 * The enrichment rules are pricing-hero's (the live A/B-tested /new-pricing/
 * form) — keep the two in step. Each mount ([data-ap-roi-form]) carries its
 * portal, form and region as data attributes (validated server-side), plus
 * optional placeholder text, a submit label, a redirect and the list of
 * "enrich" fields. HubSpot's embed script loads once, only when a page has a
 * mount.
 *
 * Enrich fields stay hidden while the visitor types an email: ZoomInfo
 * FormComplete marks each field data-zi-field-enriched / data-zi-input-enriched,
 * and a field is shown only when enrichment reports it could NOT fill it. On
 * submit, every enrich field enrichment did not fill is revealed so HubSpot's
 * validation can ask for it. Rows holding only hidden tracking inputs collapse.
 *
 * The field text (form_placeholders) goes into HubSpot's own <label for>, empty
 * on this form, which style.css draws as the floating label of the Get a Demo
 * booking form (.ap-dw-label); the shell carries .has-value while the field
 * holds text, so the label stays up. Fields still without a label get the text
 * (minus its asterisk) as their accessible name, and the form gets the mount's
 * label, so it is a named form landmark. If HubSpot cannot load (blocked,
 * offline) or never draws the form, the card shows its contact line instead of
 * an empty box.
 *
 * Every state write is guarded (no change, no write), so the MutationObserver
 * that re-applies the rules after HubSpot or ZoomInfo touch the form settles
 * after one pass.
 */
(function () {
	'use strict';

	var HIDDEN = 'ap-roi-hero__hidden';
	var FIELD_GAP = '16px';
	var CONTROLS = "input:not([type='hidden']), select, textarea";
	var GIVE_UP_MS = 10000;

	function scriptFor(region) {
		return region && region !== 'na1'
			? 'https://js-' + region + '.hsforms.net/forms/embed/v2.js'
			: 'https://js.hsforms.net/forms/embed/v2.js';
	}

	/* The embed host is resolved and handshaken while the hero paints, so the deferred script lands fast. */
	function preconnect(region) {
		var href = scriptFor(region).replace(/\/forms\/embed\/v2\.js$/, '');
		if (document.querySelector('link[rel="preconnect"][href="' + href + '"]')) {
			return;
		}
		var link = document.createElement('link');
		link.rel = 'preconnect';
		link.href = href;
		link.crossOrigin = 'anonymous';
		document.head.appendChild(link);
	}

	var loading = null;

	function loadHubSpot(region) {
		if (window.hbspt && window.hbspt.forms) {
			return Promise.resolve();
		}
		if (loading) {
			return loading;
		}
		loading = new Promise(function (resolve, reject) {
			var tag = document.createElement('script');
			tag.src = scriptFor(region);
			tag.async = true;
			tag.charset = 'utf-8';
			tag.onload = function () {
				// Another component may have added its own copy first; wait for
				// whichever one defines hbspt.forms.
				var tries = 0;
				(function wait() {
					if (window.hbspt && window.hbspt.forms) {
						resolve();
					} else if (tries++ < 100) {
						window.setTimeout(wait, 100);
					} else {
						reject(new Error('hbspt'));
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

	function setImportant(el, property, value) {
		if (!el || !el.style) {
			return;
		}
		if (el.style.getPropertyValue(property) === value && el.style.getPropertyPriority(property) === 'important') {
			return;
		}
		el.style.setProperty(property, value, 'important');
	}

	// Values are written in the form the browser reads them back in (0px, not
	// 0), so the guard in setImportant() sees "no change" on the next pass.
	function setStyles(el, styles) {
		for (var i = 0; i < styles.length; i += 2) {
			setImportant(el, styles[i], styles[i + 1]);
		}
	}

	function setAttr(el, name, value) {
		if (el && el.getAttribute(name) !== value) {
			el.setAttribute(name, value);
		}
	}

	function addHidden(el) {
		if (el && el.classList && !el.classList.contains(HIDDEN)) {
			el.classList.add(HIDDEN);
		}
	}

	function removeHidden(el) {
		if (el && el.classList && el.classList.contains(HIDDEN)) {
			el.classList.remove(HIDDEN);
		}
	}

	function hide(el) {
		if (!el) {
			return;
		}
		addHidden(el);
		setStyles(el, ['display', 'none', 'height', '0px', 'min-height', '0px', 'max-height', '0px', 'margin', '0px', 'padding', '0px', 'overflow', 'hidden', 'visibility', 'hidden', 'opacity', '0']);
	}

	function showGroup(group) {
		if (!group) {
			return;
		}
		removeHidden(group);
		setStyles(group, ['display', 'flex', 'flex-direction', 'column', 'gap', FIELD_GAP, 'width', '100%', 'max-width', '100%', 'height', 'auto', 'max-height', 'none', 'margin', '0px', 'padding', '0px', 'visibility', 'visible', 'opacity', '1']);
	}

	function showShell(shell) {
		if (!shell) {
			return;
		}
		removeHidden(shell);
		setStyles(shell, ['display', 'block', 'width', '100%', 'max-width', '100%', 'flex', '1 1 100%', 'float', 'none', 'height', 'auto', 'max-height', 'none', 'margin', '0px', 'padding', '0px', 'visibility', 'visible', 'opacity', '1']);
	}

	// A control with no field wrapper (the submit button) has its parent as both
	// group and shell; write its end state once.
	function showField(group, shell) {
		if (group && group === shell) {
			removeHidden(shell);
			setStyles(shell, ['display', 'block', 'flex-direction', 'column', 'gap', FIELD_GAP, 'width', '100%', 'max-width', '100%', 'height', 'auto', 'max-height', 'none', 'margin', '0px', 'padding', '0px', 'visibility', 'visible', 'opacity', '1', 'flex', '1 1 100%', 'float', 'none']);
			return;
		}
		showGroup(group);
		showShell(shell);
	}

	function shellOf(control) {
		return control.closest('.hs-form__field-row__column') || control.closest('.hs-form__field') || control.closest('.hs-form-field') || control.parentElement;
	}

	function groupOf(control) {
		return control.closest('fieldset') || control.closest('.hs-form__field-row') || control.closest('.hs-form__row') || control.parentElement;
	}

	// ZoomInfo's verdict for a field: "true" (filled), "false" (could not), or null.
	function enrichState(control) {
		var carriers = [control, shellOf(control), groupOf(control)];
		for (var i = 0; i < carriers.length; i++) {
			var el = carriers[i];
			if (!el || !el.getAttribute) {
				continue;
			}
			var field = el.getAttribute('data-zi-field-enriched');
			var input = el.getAttribute('data-zi-input-enriched');
			if (field === 'true' || input === 'true') {
				return 'true';
			}
			if (field === 'false' || input === 'false') {
				return 'false';
			}
		}
		return null;
	}

	function enrichList(mount) {
		return (mount.getAttribute('data-ap-hs-enrich') || '').split(/\s+/).filter(Boolean);
	}

	function labelText(input) {
		var labels = input.labels ? Array.prototype.slice.call(input.labels) : [];
		return labels.map(function (label) {
			return String(label.textContent || '').trim();
		}).join('');
	}

	function labelOf(input) {
		if (input.labels && input.labels.length) {
			return input.labels[0];
		}
		var shell = shellOf(input);
		return shell && shell.querySelector ? shell.querySelector('label:not(.hs-error-msg)') : null;
	}

	// The label floats over a filled field (demo-booking's .has-value). The sheet
	// also tests :placeholder-shown, so a value set without an event still counts.
	function markValue(shell, control) {
		if (!shell || !shell.classList || control.type === 'submit') {
			return;
		}
		var filled = String(control.value || '').trim() !== '';
		if (filled !== shell.classList.contains('has-value')) {
			shell.classList.toggle('has-value', filled);
		}
	}

	function relabel(mount, form) {
		var map = {};
		try {
			map = JSON.parse(mount.getAttribute('data-ap-hs-placeholders') || '{}') || {};
		} catch (e) {
			map = {};
		}
		var name = mount.getAttribute('data-ap-hs-label');
		if (name) {
			setAttr(form, 'aria-label', name);
		}
		Array.prototype.forEach.call(form.querySelectorAll('input[name], textarea[name], select[name]'), function (input) {
			var field = (input.getAttribute('name') || '').toLowerCase();
			var type = (input.getAttribute('type') || '').toLowerCase();
			if (type === 'hidden' || type === 'submit') {
				return;
			}
			if (Object.prototype.hasOwnProperty.call(map, field)) {
				setAttr(input, 'placeholder', map[field]);
			}
			var auto = field === 'email' || type === 'email' ? 'email' : type === 'tel' || /phone/.test(field) ? 'tel' : '';
			if (auto) {
				setAttr(input, 'autocomplete', auto);
			}
			if (auto === 'tel' && type === 'text') {
				setAttr(input, 'inputmode', 'tel');
			}
			// The field text goes into HubSpot's own <label for> (empty on this form),
			// which the sheet floats over the field. The placeholder stays on the input
			// (drawn transparent), and every control carries one, so the sheet's
			// :placeholder-shown test can tell an empty field from a filled one.
			var text = String(input.getAttribute('placeholder') || '').trim();
			if (!text) {
				setAttr(input, 'placeholder', labelText(input) || ' ');
			}
			var label = labelOf(input);
			if (label && text && !labelText(input)) {
				var slot = label.querySelector('span') || label;
				if (slot.textContent !== text) {
					slot.textContent = text;
				}
			}
			// A field still without a label is named after its text.
			var name = text.replace(/\*\s*$/, '').trim();
			if (!labelText(input) && name) {
				setAttr(input, 'aria-label', name);
			}
		});
		var label = mount.getAttribute('data-ap-hs-submit');
		if (label) {
			Array.prototype.forEach.call(form.querySelectorAll("input[type='submit'], button[type='submit'], .hs-button, .hs-form__actions__submit"), function (button) {
				if (button.tagName.toLowerCase() === 'input') {
					if (button.value !== label) {
						button.value = label;
					}
				} else if (button.textContent !== label) {
					button.textContent = label;
				}
			});
		}
	}

	// One pass over the form. `submitting` switches the enrich rule from "show
	// only what enrichment could not fill" to "show everything it did not fill".
	function apply(mount, submitting) {
		var form = mount.querySelector('form');
		if (!form) {
			return;
		}
		relabel(mount, form);
		var enrich = enrichList(mount);

		Array.prototype.forEach.call(form.querySelectorAll('input, select, textarea'), function (control) {
			if (control.type === 'hidden') {
				return;
			}
			var group = groupOf(control);
			var shell = shellOf(control);
			markValue(shell, control);
			if (!control.name || enrich.indexOf(control.name) === -1) {
				showField(group, shell);
				return;
			}
			var state = enrichState(control);
			var filled = state === 'true' && String(control.value || '').trim() !== '';
			if (submitting ? !filled : state === 'false') {
				showField(group, shell);
			} else {
				hide(shell);
			}
		});

		// A row whose every field is held back collapses, so it leaves no gap.
		Array.prototype.forEach.call(form.querySelectorAll('fieldset, .hs-form__field-row'), function (group) {
			var controls = Array.prototype.slice.call(group.querySelectorAll(CONTROLS));
			if (!controls.length) {
				return;
			}
			var visible = controls.some(function (control) {
				var shell = shellOf(control);
				return shell && !shell.classList.contains(HIDDEN) && shell.style.display !== 'none';
			});
			if (visible) {
				showGroup(group);
			} else {
				hide(group);
			}
		});

		// Rows that only carry hidden tracking inputs.
		Array.prototype.forEach.call(mount.querySelectorAll('fieldset, .hs-form__row, .hs-form__field-row'), function (group) {
			if (!group.querySelectorAll(CONTROLS).length && group.querySelectorAll("input[type='hidden']").length) {
				hide(group);
			}
		});
	}

	function bind(mount) {
		var form = mount.querySelector('form');
		if (!form || form.getAttribute('data-ap-roi-bound')) {
			return;
		}
		form.setAttribute('data-ap-roi-bound', '1');
		var reveal = function () {
			apply(mount, true);
		};
		// Every field: the email drives the enrich rules, and each field's
		// .has-value keeps its label floated while it holds text.
		Array.prototype.forEach.call(form.querySelectorAll(CONTROLS), function (control) {
			['input', 'change', 'blur'].forEach(function (ev) {
				control.addEventListener(ev, function () {
					apply(mount, false);
				});
			});
		});
		form.addEventListener('submit', reveal, true);
		var submit = form.querySelector("input[type='submit'], button[type='submit'], .hs-button, .hs-form__actions__submit");
		if (submit) {
			submit.addEventListener('click', reveal, true);
		}
	}

	function settle(mount) {
		apply(mount, false);
		bind(mount);
	}

	// The card's contact line replaces the reserved (skeleton) space.
	function fail(el) {
		if (el.querySelector('form')) {
			return;
		}
		el.classList.add('is-failed');
		var card = el.parentElement;
		var line = card && card.querySelector('.ap-roi-hero__fallback[hidden]');
		if (line) {
			line.hidden = false;
		}
	}

	function mount(el) {
		if (!el.id || el.getAttribute('data-ap-hs-mounted')) {
			return;
		}
		el.setAttribute('data-ap-hs-mounted', '1');
		var region = el.getAttribute('data-ap-hs-region') || 'na1';
		// The 200 KB embed never competes with the first paint: it is fetched once the browser is idle
		// or the visitor moves, whichever comes first (and no later than 2.5 s).
		preconnect(region);
		var timer = null;
		var started = false;
		var start = function () {
		if (started) {
			return;
		}
		started = true;
		timer = window.setTimeout(function () {
			fail(el);
		}, GIVE_UP_MS);
		loadHubSpot(region).then(function () {
			el.innerHTML = '';
			var redirect = el.getAttribute('data-ap-hs-redirect');
			var options = {
				region: region,
				portalId: el.getAttribute('data-ap-hs-portal'),
				formId: el.getAttribute('data-ap-hs-form'),
				target: '#' + el.id,
				css: '',
				cssRequired: '',
				onFormReady: function () {
					window.clearTimeout(timer);
					// A slow load may have tripped the fallback first; the form wins.
					el.classList.remove('is-failed');
					el.classList.add('is-ready');
					var line = el.parentElement && el.parentElement.querySelector('.ap-roi-hero__fallback:not([hidden])');
					if (line && line.parentElement === el.parentElement) {
						line.hidden = true;
					}
					[25, 250, 900].forEach(function (delay) {
						window.setTimeout(function () {
							settle(el);
						}, delay);
					});
				}
			};
			if (redirect) {
				options.onFormSubmitted = function () {
					window.location.href = redirect;
				};
			}
			window.hbspt.forms.create(options);

			// HubSpot and ZoomInfo keep editing the form; re-apply after each burst.
			if ('MutationObserver' in window) {
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
				}).observe(el, {
					childList: true,
					subtree: true,
					attributes: true,
					attributeFilter: ['style', 'class', 'hidden', 'aria-hidden', 'data-zi-field-enriched', 'data-zi-input-enriched']
				});
			}
		}).catch(function () {
			window.clearTimeout(timer);
			el.removeAttribute('data-ap-hs-mounted');
			fail(el);
		});
		};
		['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach(function (ev) {
			window.addEventListener(ev, start, { once: true, passive: true });
		});
		if ('requestIdleCallback' in window) {
			window.requestIdleCallback(start, { timeout: 2500 });
		} else {
			window.setTimeout(start, 1500);
		}
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ap-roi-form][data-ap-hs-form]'), mount);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
