/**
 * pricing-hero — draws the HubSpot quote form and keeps it short.
 *
 * Each mount point ([data-ap-pricing-form]) carries its portal, form and region
 * as data attributes (validated server-side), plus optional placeholder text, a
 * submit label, a redirect and the list of "enrich" fields. HubSpot's embed
 * script loads once, only when a page actually has a mount.
 *
 * Like the live pricing page, the enrich fields (phone, name, company) stay
 * hidden while the visitor types an email: ZoomInfo FormComplete marks each
 * field data-zi-field-enriched / data-zi-input-enriched, and a field is shown
 * only when enrichment reports it could NOT fill it. On submit, every enrich
 * field enrichment did not fill is revealed so HubSpot's validation can ask
 * for it. Rows holding only hidden tracking inputs are collapsed.
 *
 * Every state write is guarded (no change, no write), so the MutationObserver
 * that re-applies the rules after HubSpot or ZoomInfo touch the form settles
 * after one pass instead of re-firing every frame.
 */
(function () {
	'use strict';

	var HIDDEN = 'ap-pricing-hero__hidden';
	var FIELD_GAP = '16px';
	var CONTROLS = "input:not([type='hidden']), select, textarea";

	function scriptFor(region) {
		return region && region !== 'na1'
			? 'https://js-' + region + '.hsforms.net/forms/embed/v2.js'
			: 'https://js.hsforms.net/forms/embed/v2.js';
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

	/*
	 * A control with no field wrapper (the submit button) has its parent as both
	 * group and shell. Live styles it as a group and then as a shell on every
	 * pass, flipping display flex -> block; write that end state once instead.
	 */
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

	function relabel(mount, form) {
		var map = {};
		try {
			map = JSON.parse(mount.getAttribute('data-ap-hs-placeholders') || '{}') || {};
		} catch (e) {
			map = {};
		}
		Array.prototype.forEach.call(form.querySelectorAll('input[name], textarea[name]'), function (input) {
			var name = (input.getAttribute('name') || '').toLowerCase();
			var type = (input.getAttribute('type') || '').toLowerCase();
			if (!Object.prototype.hasOwnProperty.call(map, name) || type === 'hidden' || type === 'submit') {
				return;
			}
			if (input.getAttribute('placeholder') !== map[name]) {
				input.setAttribute('placeholder', map[name]);
			}
			var auto = name === 'email' || type === 'email' ? 'email' : type === 'tel' || /phone/.test(name) ? 'tel' : '';
			if (auto && input.getAttribute('autocomplete') !== auto) {
				input.setAttribute('autocomplete', auto);
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

	/*
	 * One pass over the form. `submitting` switches the enrich rule from
	 * "show only what enrichment could not fill" to "show everything it did
	 * not fill", as live does on submit.
	 */
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
		if (!form || form.getAttribute('data-ap-pricing-bound')) {
			return;
		}
		form.setAttribute('data-ap-pricing-bound', '1');
		var reveal = function () {
			apply(mount, true);
		};
		var email = form.querySelector("input[name='email']") || form.querySelector("input[type='email']");
		if (email) {
			email.addEventListener('input', function () {
				apply(mount, false);
			});
			email.addEventListener('blur', function () {
				apply(mount, false);
			});
		}
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

	function mount(el) {
		if (!el.id || el.getAttribute('data-ap-hs-mounted')) {
			return;
		}
		el.setAttribute('data-ap-hs-mounted', '1');
		var region = el.getAttribute('data-ap-hs-region') || 'na1';
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
			el.removeAttribute('data-ap-hs-mounted');
		});
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ap-pricing-form][data-ap-hs-form]'), mount);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
