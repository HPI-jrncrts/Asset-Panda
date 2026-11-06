/**
 * integration-directory — category filter, live search and pagination.
 *
 * Delegated and idempotent: every [data-ap-intdir] section is wired once
 * (data-ap-intdir-ready guards a second run) and instances never share state.
 * render.php prints every card visible and the pager hidden, so without this
 * script the whole directory simply shows. With it:
 *
 *   - the Categories radios (native radio group: arrow keys move and select)
 *     filter by each card's data-group; 'all' shows everything;
 *   - the search field matches name, tag and description (case- and
 *     accent-insensitive, every word must match), debounced as you type;
 *   - matches are paged per_page at a time; Previous / numbered pages (first
 *     three, last three and the current page's neighbours, the rest folded
 *     into an ellipsis) / Next, aria-current on the current page;
 *   - a filter or search change goes back to page 1; a page change scrolls the
 *     list's top into view (smoothly unless reduced motion is asked for) and
 *     moves focus to the list;
 *   - a visually hidden live region announces "Showing 1–12 of 40
 *     integrations" (or the empty message) after every change, never on load;
 *   - the first instance with url sync mirrors ?category= and ?q= with
 *     history.replaceState, and restores them on load.
 *
 * Card shuffle (client QA 2026-10-29: "make it look like it's a card
 * shuffling"). A filter, search, reset or page change no longer swaps the grid
 * in one frame; it plays a FLIP shuffle with the Web Animations API, on
 * transform and opacity only (hover keeps `translate`, so nothing fights):
 *
 *   - cards that stay glide from where they were to where they now sit,
 *     lifting, tilting and shrinking a touch half way, in reading order;
 *   - cards that go drop out where they were (fade, scale down, sink, tilt),
 *     pulled out of the flow (.is-leaving, inert, aria-hidden) so the new
 *     layout forms under them;
 *   - cards that arrive are dealt in from a deck above the middle of the grid:
 *     a fanned-out tilt, a short slide and a fade, slightly staggered;
 *   - the grid keeps its old height (and the column its height, should the
 *     pager go) until the motion ends, then lets go, so nothing under it jumps
 *     mid-shuffle; when nothing matches, the cards drop out first and the
 *     empty state fades in after them.
 *
 * Durations, curves, stagger and distances come from the motion tokens; the
 * whole shuffle ends inside ~500 ms. A change mid-shuffle captures every card
 * where it is on screen, cancels the running animations and shuffles on from
 * there, so rapid clicks or typing never leave a card stuck. Reduced motion
 * (or no element.animate) keeps the instant swap. The status text and empty
 * state change with the data, as before.
 *
 * Geometry is read once per change (before and after the swap) and once on
 * load to bring a restored chip into view, never on scroll.
 */
(function () {
	'use strict';

	var DEBOUNCE = 180;
	var urlClaimed = false;

	function reducedMotion() {
		return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	}

	function fill(template, values) {
		return String(template || '').replace(/\{(\w+)\}/g, function (match, key) {
			return Object.prototype.hasOwnProperty.call(values, key) ? String(values[key]) : match;
		});
	}

	// Lower case, accents folded (é -> e), whitespace collapsed.
	function norm(text) {
		var s = String(text || '').toLowerCase();
		if (s.normalize) {
			s = s.normalize('NFD').replace(/[̀-ͯ]/g, '');
		}
		return s.replace(/\s+/g, ' ').trim();
	}

	// Page numbers to show: 1-3, the last three and the current page's neighbours; gaps become null (ellipsis).
	function pageList(page, pages) {
		var want = {};
		var add = function (n) {
			if (n >= 1 && n <= pages) {
				want[n] = true;
			}
		};
		[1, 2, 3, pages - 2, pages - 1, pages, page - 1, page, page + 1].forEach(add);
		var nums = Object.keys(want).map(Number).sort(function (a, b) {
			return a - b;
		});
		var out = [];
		for (var i = 0; i < nums.length; i++) {
			if (i > 0) {
				var gap = nums[i] - nums[i - 1];
				if (gap === 2) {
					out.push(nums[i] - 1); // a single hidden page is cheaper shown than folded
				} else if (gap > 2) {
					out.push(null);
				}
			}
			out.push(nums[i]);
		}
		return out;
	}

	// ---- shuffle helpers ----

	var CAN_ANIMATE = typeof Element !== 'undefined' && typeof Element.prototype.animate === 'function';
	var timing = null;

	// "150ms" / "0.3s" -> milliseconds.
	function toMs(value, fallback) {
		var n = parseFloat(value);
		if (!isFinite(n)) {
			return fallback;
		}
		return /ms\s*$/.test(value) ? n : n * 1000;
	}

	// The shuffle's clock, curves and distances: the motion tokens (tokens.css), read once.
	function getTiming(el) {
		if (!timing) {
			var cs = window.getComputedStyle(el);
			var prop = function (name) {
				return (cs.getPropertyValue(name) || '').trim();
			};
			timing = {
				fast: toMs(prop('--ap-dur-fast'), 150),
				base: toMs(prop('--ap-dur-base'), 300),
				step: toMs(prop('--ap-stagger-step'), 80),
				dist: parseFloat(prop('--ap-reveal-distance')) || 16,
				standard: prop('--ap-ease-standard') || 'ease',
				out: prop('--ap-ease-out') || 'ease-out',
			};
		}
		return timing;
	}

	// A computed transform as translate / rotate / uniform scale, the only moves this script makes. 'none' = rest.
	function readTransform(value) {
		var m = /^matrix\(([^)]+)\)$/.exec(String(value || '').trim());
		if (!m) {
			return { x: 0, y: 0, r: 0, s: 1 };
		}
		var v = m[1].split(',').map(parseFloat);
		return {
			x: v[4] || 0,
			y: v[5] || 0,
			r: (Math.atan2(v[1], v[0]) * 180) / Math.PI || 0,
			s: Math.sqrt(v[0] * v[0] + v[1] * v[1]) || 1,
		};
	}

	// Every keyframe uses the same function list, so the browser interpolates each part on its own.
	function tf(x, y, r, s) {
		return 'translate(' + x.toFixed(2) + 'px, ' + y.toFixed(2) + 'px) rotate(' + r.toFixed(2) + 'deg) scale(' + s.toFixed(4) + ')';
	}

	function init(root) {
		if (root.hasAttribute('data-ap-intdir-ready')) {
			return;
		}
		root.setAttribute('data-ap-intdir-ready', '');

		var slice = Array.prototype.slice;
		var cards = slice.call(root.querySelectorAll('[data-ap-intdir-card]')).map(function (el) {
			var text = slice.call(el.querySelectorAll('[data-ap-intdir-text]')).map(function (t) {
				return t.textContent;
			});
			return {
				el: el,
				groups: (el.getAttribute('data-group') || '').split(/\s+/).filter(Boolean),
				text: norm(text.join(' ')),
				anims: null, // this card's running shuffle animations
				leaving: false, // dropping out: shown, out of the flow, logically hidden
				was: null, // where it was on screen when the change came
			};
		});
		if (!cards.length) {
			return;
		}

		var radios = slice.call(root.querySelectorAll('[data-ap-intdir-cat]'));
		var input = root.querySelector('[data-ap-intdir-search]');
		var grid = root.querySelector('[data-ap-intdir-grid]');
		var main = root.querySelector('[data-ap-intdir-main]') || grid;
		var status = root.querySelector('[data-ap-intdir-status]');
		var empty = root.querySelector('[data-ap-intdir-empty]');
		var emptyText = empty ? empty.querySelector('.ap-integration-directory__empty-text') : null;
		var reset = root.querySelector('[data-ap-intdir-reset]');
		var pager = root.querySelector('[data-ap-intdir-pager]');
		var pagesList = root.querySelector('[data-ap-intdir-pages]');
		var pageOf = root.querySelector('[data-ap-intdir-page-of]');
		var prev = root.querySelector('[data-ap-intdir-step="-1"]');
		var next = root.querySelector('[data-ap-intdir-step="1"]');
		var perPage = Math.max(0, parseInt(root.getAttribute('data-ap-intdir-per-page'), 10) || 0);
		var statusText = root.getAttribute('data-ap-intdir-status-text') || '{from}–{to} of {total}';
		var pageText = root.getAttribute('data-ap-intdir-page-text') || 'Page {page} of {pages}';
		var sync = false;
		if (root.getAttribute('data-ap-intdir-url') === '1' && !urlClaimed && window.history && window.history.replaceState && window.URL) {
			sync = true;
			urlClaimed = true;
		}

		var state = { cat: 'all', query: '', page: 1, pages: 1 };
		var announceTimer = 0;
		var searchTimer = 0;
		var run = 0; // shuffle generation: a run only cleans up after itself if no change replaced it
		var emptyAnim = null;

		function radioFor(value) {
			for (var i = 0; i < radios.length; i++) {
				if (radios[i].value === value) {
					return radios[i];
				}
			}
			return null;
		}

		function announce(message) {
			if (!status) {
				return;
			}
			// Clear first, so the same sentence twice in a row is still read out.
			status.textContent = '';
			window.clearTimeout(announceTimer);
			announceTimer = window.setTimeout(function () {
				status.textContent = message;
			}, 60);
		}

		function setDisabled(button, off) {
			if (button) {
				button.setAttribute('aria-disabled', off ? 'true' : 'false');
			}
		}

		function buildPager() {
			if (!pager) {
				return;
			}
			var show = perPage > 0 && state.pages > 1;
			pager.hidden = !show;
			if (!show) {
				return;
			}
			setDisabled(prev, state.page <= 1);
			setDisabled(next, state.page >= state.pages);
			if (pageOf) {
				pageOf.textContent = fill(pageText, { page: state.page, pages: state.pages });
			}
			if (!pagesList) {
				return;
			}
			var frag = document.createDocumentFragment();
			pageList(state.page, state.pages).forEach(function (n) {
				var li = document.createElement('li');
				if (n === null) {
					li.className = 'ap-integration-directory__gap';
					li.setAttribute('aria-hidden', 'true');
					li.textContent = '…';
				} else {
					var b = document.createElement('button');
					b.type = 'button';
					b.className = 'ap-integration-directory__page';
					b.setAttribute('data-ap-intdir-page', String(n));
					b.setAttribute('aria-label', fill(pageText, { page: n, pages: state.pages }));
					b.textContent = String(n);
					if (n === state.page) {
						b.setAttribute('aria-current', 'page');
					}
					li.appendChild(b);
				}
				frag.appendChild(li);
			});
			pagesList.textContent = '';
			pagesList.appendChild(frag);
		}

		// ---- shuffle ----

		// Reads only: every card on screen now (a leaving one included), as its layout box in the grid plus
		// whatever a running shuffle is doing to it, so the next shuffle starts exactly there.
		// Returns the heights on screen of the grid (-1 while it is hidden) and of the column holding it.
		function capture() {
			var shown = !grid.hidden;
			cards.forEach(function (card) {
				var el = card.el;
				card.was = null;
				if (!shown || el.hidden) {
					return;
				}
				var cs = card.anims && card.anims.length ? window.getComputedStyle(el) : null;
				var o = cs ? parseFloat(cs.opacity) : 1;
				card.was = {
					x: el.offsetLeft,
					y: el.offsetTop,
					w: el.offsetWidth,
					h: el.offsetHeight,
					t: readTransform(cs ? cs.transform : 'none'),
					o: isFinite(o) ? o : 1,
				};
			});
			return { grid: shown ? grid.offsetHeight : -1, main: main.offsetHeight };
		}

		function unleave(card) {
			var el = card.el;
			card.leaving = false;
			el.hidden = true;
			el.classList.remove('is-leaving');
			el.removeAttribute('aria-hidden');
			el.removeAttribute('inert');
			el.style.removeProperty('left');
			el.style.removeProperty('top');
			el.style.removeProperty('width');
			el.style.removeProperty('height');
		}

		// Stop a shuffle where it is: its animations cancelled, leaving cards hidden for good, the grid's height released.
		function settle() {
			run++;
			cards.forEach(function (card) {
				if (card.anims) {
					card.anims.forEach(function (a) {
						a.cancel();
					});
					card.anims = null;
				}
				if (card.leaving) {
					unleave(card);
				}
			});
			if (grid) {
				grid.style.removeProperty('min-height');
				grid.classList.remove('is-shuffling');
			}
		}

		// FLIP: the cards already sit in their new places (and hidden ones are gone); animate each from where it was.
		function shuffle(held, deferEmpty) {
			var t = getTiming(root);
			var moving = [];
			var leaves = [];
			var gw = grid.clientWidth;
			// Reads: the new layout, in one pass (cards that go are still display:none here).
			cards.forEach(function (card) {
				var el = card.el;
				if (!el.hidden) {
					moving.push({ card: card, at: { x: el.offsetLeft, y: el.offsetTop, w: el.offsetWidth, h: el.offsetHeight } });
				} else if (card.was) {
					leaves.push({ card: card, at: card.was });
				}
			});
			var newH = grid.offsetHeight;
			// Hold the grid at least as tall as it was (leaving cards sit in that space), and tall enough that the
			// column keeps its height when the pager goes too, so nothing under the directory moves mid-shuffle.
			var hold = Math.max(held.grid, newH + held.main - main.offsetHeight);

			var byPlace = function (a, b) {
				return a.at.y - b.at.y || a.at.x - b.at.x;
			};
			moving.sort(byPlace);
			leaves.sort(byPlace);
			// A short stagger in reading order, squeezed so a full page still ends inside ~500 ms.
			var spread = function (n, span) {
				return n > 1 ? Math.min(t.step / 4, span / (n - 1)) : 0;
			};
			var leaveStep = spread(leaves.length, t.fast * 0.4);
			var moveStep = spread(moving.length, t.fast * 0.8);
			var dealAt = leaves.length ? t.fast / 2 : 0; // arrivals start once the leavers are half gone
			var deck = t.dist * 3; // how far a dealt card travels; the deck sits that far above the grid's middle
			var token;
			var all = [];
			var play = function (card, frames, options) {
				options.fill = 'both';
				var a = card.el.animate(frames, options);
				(card.anims || (card.anims = [])).push(a);
				all.push(a);
			};

			// Writes.
			grid.classList.add('is-shuffling');
			if (hold > newH + 0.5) {
				grid.style.minHeight = hold + 'px';
			}

			leaves.forEach(function (item, i) {
				var card = item.card;
				var el = card.el;
				var w = card.was;
				var s = w.t;
				card.leaving = true;
				el.hidden = false;
				el.classList.add('is-leaving');
				el.setAttribute('aria-hidden', 'true');
				el.setAttribute('inert', '');
				el.style.left = w.x + 'px';
				el.style.top = w.y + 'px';
				el.style.width = w.w + 'px';
				el.style.height = w.h + 'px';
				var side = w.x + w.w / 2 < gw / 2 ? -1 : 1;
				play(
					card,
					[
						{ opacity: w.o, transform: tf(s.x, s.y, s.r, s.s) },
						{ opacity: 0, transform: tf(s.x, s.y + t.dist, s.r + side * 6, s.s * 0.85) },
					],
					// a card already half gone finishes sooner
					{ duration: t.fast * Math.max(0.35, Math.min(1, w.o)), delay: i * leaveStep, easing: t.standard }
				);
			});

			moving.forEach(function (item, i) {
				var card = item.card;
				var at = item.at;
				var w = card.was;
				var side;
				if (!w) {
					// Dealt from the deck: start towards it (fanned out by side), small and clear, then land.
					var cx = at.x + at.w / 2;
					var vx = gw / 2 - cx;
					var vy = -deck - (at.y + at.h / 2);
					var len = Math.sqrt(vx * vx + vy * vy) || 1;
					var reach = Math.min(len, deck) / len;
					var nx = gw ? Math.max(-1, Math.min(1, (cx - gw / 2) / (gw / 2))) : 0;
					side = Math.abs(nx) > 0.1 ? (nx > 0 ? 1 : -1) : i % 2 ? 1 : -1;
					play(
						card,
						[
							{ opacity: 0, transform: tf(vx * reach, vy * reach, side * (4 + 4 * Math.abs(nx)), 0.9) },
							{ opacity: 1, transform: tf(0, 0, 0, 1) },
						],
						{ duration: t.base, delay: dealAt + i * moveStep, easing: t.out }
					);
					return;
				}
				var s = w.t;
				var x = w.x - at.x + s.x;
				var y = w.y - at.y + s.y;
				if (Math.abs(x) < 0.5 && Math.abs(y) < 0.5 && Math.abs(s.r) < 0.1 && Math.abs(s.s - 1) < 0.001 && w.o > 0.99) {
					return; // stayed put
				}
				// Lifted, tilted and shrunk a touch half way; a short hop does less of it.
				var k = Math.min(1, Math.sqrt(x * x + y * y) / (t.dist * 6));
				side = x > 0.5 ? -1 : x < -0.5 ? 1 : i % 2 ? 1 : -1;
				play(
					card,
					[
						{ opacity: w.o, transform: tf(x, y, s.r, s.s) },
						{ offset: 0.5, opacity: (w.o + 1) / 2, transform: tf(x / 2, y / 2 - (t.dist / 2) * k, s.r / 2 + side * 3 * k, (s.s + 1) / 2 - 0.04 * k) },
						{ opacity: 1, transform: tf(0, 0, 0, 1) },
					],
					{ duration: t.base, delay: i * moveStep, easing: t.standard }
				);
			});

			token = run;
			var finish = function () {
				if (token !== run) {
					return; // a later change took over
				}
				settle();
				if (deferEmpty && empty) {
					grid.hidden = true;
					empty.hidden = false;
					emptyAnim = empty.animate(
						[
							{ opacity: 0, transform: 'translateY(' + t.dist / 2 + 'px)' },
							{ opacity: 1, transform: 'none' },
						],
						{ duration: t.base, easing: t.out }
					);
				}
			};
			var left = all.length;
			if (!left) {
				finish();
				return;
			}
			all.forEach(function (a) {
				a.onfinish = function () {
					left--;
					if (!left) {
						finish();
					}
				};
			});
		}

		function apply(opts) {
			opts = opts || {};
			var motion = !!opts.animate && CAN_ANIMATE && !!grid && !reducedMotion();
			var held = motion ? capture() : null;
			settle();
			var terms = state.query ? state.query.split(' ') : [];
			var matches = cards.filter(function (card) {
				if (state.cat !== 'all' && card.groups.indexOf(state.cat) === -1) {
					return false;
				}
				for (var t = 0; t < terms.length; t++) {
					if (card.text.indexOf(terms[t]) === -1) {
						return false;
					}
				}
				return true;
			});
			var total = matches.length;
			state.pages = perPage > 0 ? Math.max(1, Math.ceil(total / perPage)) : 1;
			state.page = Math.min(Math.max(1, state.page), state.pages);
			var from = perPage > 0 ? (state.page - 1) * perPage : 0;
			var to = perPage > 0 ? Math.min(total, from + perPage) : total;
			var shown = {};
			for (var m = from; m < to; m++) {
				shown[cards.indexOf(matches[m])] = true;
			}

			var leaving = false;
			cards.forEach(function (card, i) {
				var on = !!shown[i];
				if (on && card.el.hidden) {
					card.el.hidden = false;
				} else if (!on && !card.el.hidden) {
					card.el.hidden = true;
				}
				if (motion && !on && card.was) {
					leaving = true;
				}
			});
			// Nothing matches: the cards on screen drop out first, then the empty state fades in (shuffle's finish).
			var deferEmpty = total === 0 && leaving;

			if (empty) {
				empty.hidden = total > 0 || deferEmpty;
				if (emptyAnim && empty.hidden) {
					emptyAnim.cancel();
					emptyAnim = null;
				}
			}
			if (grid) {
				grid.hidden = total === 0 && !deferEmpty; // an empty list would still take a gap above the message
			}
			buildPager();
			if (motion) {
				shuffle(held, deferEmpty);
			}

			if (opts.announce) {
				announce(
					total
						? fill(statusText, { from: from + 1, to: to, total: total, page: state.page, pages: state.pages })
						: emptyText
							? emptyText.textContent
							: ''
				);
			}
		}

		function writeUrl() {
			if (!sync) {
				return;
			}
			try {
				var url = new URL(window.location.href);
				if (state.cat && state.cat !== 'all') {
					url.searchParams.set('category', state.cat);
				} else {
					url.searchParams.delete('category');
				}
				var raw = input ? input.value.trim() : '';
				if (raw) {
					url.searchParams.set('q', raw);
				} else {
					url.searchParams.delete('q');
				}
				if (url.href !== window.location.href) {
					window.history.replaceState(window.history.state, '', url.href);
				}
			} catch (e) {
				// file:// previews or locked-down frames: the filter still works.
			}
		}

		function goTo(page) {
			if (page < 1 || page > state.pages || page === state.page) {
				return;
			}
			var hadPagerFocus = pager && pager.contains(document.activeElement);
			state.page = page;
			apply({ announce: true, animate: true });

			// Bring the top of the list back into view when it has scrolled away (one read, on click).
			var top = main.getBoundingClientRect().top;
			if (top < 96) {
				main.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'start' });
				if (grid && grid.focus) {
					grid.focus({ preventScroll: true });
				}
			} else if (hadPagerFocus && pagesList && !pager.contains(document.activeElement)) {
				// The pressed number was rebuilt: keep the keyboard on the pager.
				var current = pagesList.querySelector('[aria-current="page"]');
				if (current) {
					current.focus();
				}
			}
		}

		// ---- wiring ----

		radios.forEach(function (radio) {
			radio.addEventListener('change', function () {
				if (!radio.checked) {
					return;
				}
				state.cat = radio.value || 'all';
				state.page = 1;
				apply({ announce: true, animate: true });
				writeUrl();
			});
		});

		if (input) {
			input.addEventListener('input', function () {
				window.clearTimeout(searchTimer);
				searchTimer = window.setTimeout(function () {
					var q = norm(input.value);
					if (q === state.query) {
						writeUrl();
						return;
					}
					state.query = q;
					state.page = 1;
					apply({ announce: true, animate: true });
					writeUrl();
				}, DEBOUNCE);
			});
			// Touch keyboards: Enter / Search closes the keyboard so the results show.
			input.addEventListener('keydown', function (event) {
				if (event.key === 'Enter' && window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
					input.blur();
				}
			});
		}

		if (reset) {
			reset.addEventListener('click', function () {
				var all = radioFor('all');
				if (all) {
					all.checked = true;
				}
				if (input) {
					input.value = '';
				}
				window.clearTimeout(searchTimer);
				state.cat = 'all';
				state.query = '';
				state.page = 1;
				apply({ announce: true, animate: true });
				writeUrl();
				if (input) {
					input.focus();
				} else if (all) {
					all.focus();
				}
			});
		}

		if (pager) {
			pager.addEventListener('click', function (event) {
				var target = event.target && event.target.closest ? event.target.closest('button') : null;
				if (!target || !pager.contains(target)) {
					return;
				}
				if (target.getAttribute('aria-disabled') === 'true') {
					return;
				}
				var step = parseInt(target.getAttribute('data-ap-intdir-step'), 10);
				if (step) {
					goTo(state.page + step);
					return;
				}
				var n = parseInt(target.getAttribute('data-ap-intdir-page'), 10);
				if (n) {
					goTo(n);
				}
			});
		}

		// ---- restore ?category= / ?q= ----
		if (sync) {
			try {
				var params = new URL(window.location.href).searchParams;
				var wantCat = params.get('category');
				var r = wantCat ? radioFor(wantCat.toLowerCase()) : null;
				if (r) {
					r.checked = true;
					state.cat = r.value;
				}
				var wantQ = params.get('q');
				if (wantQ && input) {
					input.value = wantQ;
					state.query = norm(wantQ);
				}
			} catch (e) {
				// ignore a malformed URL
			}
		}
		// A checked radio the browser restored on Back keeps its place too.
		radios.forEach(function (radio) {
			if (radio.checked) {
				state.cat = radio.value || 'all';
			}
		});
		if (input && input.value && !state.query) {
			state.query = norm(input.value);
		}

		apply();

		// On the chip row, bring a restored (non-first) category into view. One layout read, on load.
		var checked = radioFor(state.cat);
		var row = checked && checked.closest ? checked.closest('[data-ap-intdir-cats]') : null;
		if (row && state.cat !== 'all' && row.scrollWidth > row.clientWidth + 1) {
			var label = checked.closest('label');
			if (label) {
				row.scrollLeft = Math.max(0, label.offsetLeft - 24);
			}
		}
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ap-intdir]'), init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
