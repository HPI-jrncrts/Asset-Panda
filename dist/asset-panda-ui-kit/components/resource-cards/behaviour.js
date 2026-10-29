/* resource-cards - category tabs and page numbers change the results in place.
 *
 * The listing pages (Resource Center, Blog, Case Studies, Success Stories,
 * White Papers, Webinars, Press Room) are server-rendered: every category tab in
 * resource-hero's rail, every page number and every empty-state link is a plain
 * link to another server-rendered listing, and that stays true without this
 * script. With it, a click on one of those links fetches that page in the
 * background and swaps the results in place instead of reloading:
 *
 *   1. the clicked tab takes its active look at once (its own CSS transition);
 *   2. the count line, the cards and the pager fade and lift together on one
 *      fast beat (--ap-dur-fast);
 *   3. if the page has not arrived by then, a skeleton grid of the same size
 *      (the <template> render.php prints) fades in and holds the space; when
 *      the page arrives the skeleton leaves on the same fast beat, from
 *      wherever its own fade-in got to, so it never flashes;
 *   4. the new count, cards and pager fade and rise in, each card one
 *      --ap-stagger-step after the last (the second row on wide screens comes
 *      in together); the first row's photos are fetched as soon as the page
 *      arrives and waited for at most --ap-dur-fast, so the cards rarely arrive
 *      with empty frames; the new
 *      count ("Showing 1-3 of 3 in Construction") is announced through the
 *      listing's status line;
 *   5. the address and title follow (history.pushState); Back and Forward
 *      replay the same swap (popstate).
 *
 * The rail itself is never re-rendered, so it cannot jump: only is-active and
 * aria-current move between its tabs. The listing keeps its height while it
 * changes, and gives back only as much as the page can lose without ending
 * above the bottom of the window (otherwise the browser would pull the scroll
 * position, and the rail with it, upwards).
 *
 * No markup is built here: the parts swapped in are the kit's own output on the
 * fetched page, so render.php stays the only source of markup. A page that turns
 * out to be another listing (its hero's H1 or tab addresses differ), a failed
 * fetch, a response without a listing, a click with a modifier key or the middle
 * button, a link to another origin: all fall through to normal navigation. An
 * empty-state link is only taken over when it leads back into this listing.
 *
 * resource-search offers this listing's own results pages (Enter, "View all")
 * through a cancelable 'ap:listing-navigate' event; they arrive the same way.
 *
 * Delegated and idempotent: one click listener on the document, armed only on
 * pages with a [data-ap-resource-cards] listing; geometry is read once before a
 * swap and once after it, never in a scroll handler. */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;
	var html = document.documentElement;
	if (html.classList.contains('ap-resource-cards-js')) return;
	html.classList.add('ap-resource-cards-js');
	if (typeof window.fetch !== 'function' || typeof window.DOMParser !== 'function' || typeof window.Promise !== 'function' ||
		typeof window.URL !== 'function' || !Element.prototype.closest || !Element.prototype.matches ||
		!window.history || typeof window.history.pushState !== 'function') return;

	var ROOT = '[data-ap-resource-cards]';
	var INNER = '.ap-resource-cards__inner';
	var RAIL = '[data-ap-rh-tabs]'; // resource-hero's category rail: its tabs are the filters
	var TAB = '.ap-resource-hero__tab';
	var HERO_TITLE = '.ap-resource-hero__title';
	var LINKS = RAIL + ' a, .ap-resource-cards__pages a, .ap-resource-cards__empty-links a';
	var PARTS = '.ap-resource-cards__count, .ap-resource-cards__grid, .ap-resource-cards__empty, .ap-resource-cards__pages';
	var NAV_EVENT = 'ap:listing-navigate'; // resource-search's results pages (see the foot of this file)
	var STAGGER_CAP = 3; // cards past the fourth arrive with the fourth: a short cascade, the second row as one
	var WARM = 3; // photos decoded before the cards arrive: the first row
	var SKELETON_CAP = 12;
	var STATE = 'apResourceCards';
	var slice = Array.prototype.slice;
	var timing = null;
	var current = null; // the load in flight
	var placed = 0; // bumps on every swap so a stale settle timer does nothing
	var held = null; // the listing's inner while it keeps a min-height from the last change
	var lastUrl = clean(window.location.href);

	function clean(url) { return String(url).split('#')[0]; }
	function reduced() { return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches); }
	function toMs(value, fallback) {
		var n = parseFloat(value);
		if (!isFinite(n)) return fallback;
		return /ms\s*$/.test(value) ? n : n * 1000;
	}
	// Durations come from the tokens, read once, so the script and the stylesheet agree.
	function getTiming() {
		if (!timing) {
			var cs = getComputedStyle(html);
			timing = {
				fast: toMs(cs.getPropertyValue('--ap-dur-fast'), 150),
				base: toMs(cs.getPropertyValue('--ap-dur-base'), 300),
				slow: toMs(cs.getPropertyValue('--ap-dur-slow'), 600),
				step: toMs(cs.getPropertyValue('--ap-stagger-step'), 80)
			};
		}
		return timing;
	}
	function wait(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }
	function remove(el) { if (el && el.parentNode) el.parentNode.removeChild(el); }
	function listing() { return document.querySelector(ROOT); }
	// The parts of a listing that change between pages: count line, grid or empty box, pager.
	function parts(inner) { return slice.call(inner.children).filter(function (el) { return el.matches(PARTS); }); }
	function tabs(doc) { return slice.call(doc.querySelectorAll(RAIL + ' ' + TAB)); }

	// Two pages are the same listing when their heroes carry the same H1 and the same tab
	// addresses (a filter, a search or another page number of it). Anything else (the whole
	// Resource Center from a Blog empty state) is another page and loads normally.
	function hubKey(doc) {
		var h1 = doc.querySelector(HERO_TITLE);
		return (h1 ? h1.textContent.trim() : '') + '\n' + tabs(doc).map(function (a) { return a.getAttribute('href') || ''; }).join('\n');
	}

	// Rail tabs and page numbers always change this listing; an empty-state link only when it
	// leads back into it (one of the rail's tabs, or the same address with another query).
	function ours(link, url) {
		if (!link.closest('.ap-resource-cards__empty-links')) return true;
		if (url.pathname === window.location.pathname) return true;
		var href = clean(url.href);
		return tabs(document).some(function (tab) { return clean(tab.href) === href; });
	}

	// The first row's photos start loading the moment the page arrives (not when the cards are
	// inserted), usually while the old cards are still leaving, so the new cards arrive with their
	// pictures instead of an empty frame. Never adds more than --ap-dur-fast to the wait.
	function warm(inner, base) {
		var imgs = slice.call(inner.querySelectorAll('.ap-resource-cards__grid img')).slice(0, WARM);
		if (!imgs.length || typeof window.Image !== 'function') return Promise.resolve();
		var loads = imgs.map(function (img) {
			return new Promise(function (resolve) {
				var pre = new window.Image();
				pre.onload = pre.onerror = function () { resolve(); };
				try {
					if (img.getAttribute('srcset')) {
						if (img.getAttribute('sizes')) pre.sizes = img.getAttribute('sizes');
						pre.srcset = img.getAttribute('srcset');
					}
					pre.src = new URL(img.getAttribute('src') || '', base).href;
					if (pre.decode) pre.decode().then(resolve, resolve);
				} catch (e) { resolve(); }
			});
		});
		return Promise.race([Promise.all(loads), wait(getTiming().fast)]);
	}

	// The listing holds its height while it changes (see start), then gives back only as much
	// as the page can lose without ending above the bottom of the window: past that the browser
	// pulls the scroll position up and the tab rail jumps with it. What is kept stays as space
	// under the results until the next change or a resize.
	function unlock(inner) {
		inner.style.removeProperty('min-height');
		if (!inner.getAttribute('style')) inner.removeAttribute('style');
		if (held === inner) held = null;
	}
	function relax(inner, lock) {
		var se = document.scrollingElement || html;
		var slack = se.scrollHeight - ((window.pageYOffset || se.scrollTop) + window.innerHeight);
		var keep = Math.floor(lock - Math.max(0, slack));
		if (keep > 0) {
			inner.style.minHeight = keep + 'px';
			held = inner;
		} else {
			unlock(inner);
		}
	}

	function setActive(tab, on) {
		tab.classList.toggle('is-active', on);
		if (on) tab.setAttribute('aria-current', 'page');
		else tab.removeAttribute('aria-current');
	}
	// The clicked tab lights up at once (resource-hero's own transition); the rest of its rail goes quiet.
	function pressTab(link) {
		var rail = link.closest(RAIL);
		if (!rail || !link.matches(TAB)) return;
		slice.call(rail.querySelectorAll(TAB)).forEach(function (tab) { setActive(tab, tab === link); });
	}
	// Once a page has arrived (or on Back/Forward) the rail mirrors the active tab of that page, matched by address.
	function syncTabs(doc) {
		var fresh = doc.querySelector(RAIL);
		if (!fresh) return;
		var active = {};
		slice.call(fresh.querySelectorAll(TAB + '.is-active')).forEach(function (tab) { active[tab.getAttribute('href')] = true; });
		slice.call(document.querySelectorAll(RAIL + ' ' + TAB)).forEach(function (tab) { setActive(tab, !!active[tab.getAttribute('href')]); });
	}
	// The search field shows the query of the page on show (a topic tab clears a search, as a reload would).
	function syncSearch(doc) {
		var live = document.querySelectorAll('[data-ap-resource-search] .ap-resource-search__input');
		var next = doc.querySelectorAll('[data-ap-resource-search] .ap-resource-search__input');
		for (var i = 0; i < live.length && i < next.length; i++) {
			if (document.activeElement === live[i]) continue;
			live[i].value = next[i].value;
			var clear = live[i].parentNode ? live[i].parentNode.querySelector('.ap-resource-search__clear') : null;
			if (clear) clear.hidden = !live[i].value;
		}
	}

	function announce(section, text) {
		var status = section.querySelector('.ap-resource-cards__status');
		if (!status) return;
		status.textContent = ''; // cleared first, so the same sentence twice in a row is still read out
		setTimeout(function () { status.textContent = text; }, 60);
	}

	// Fade out and lift whatever the listing shows now; resolves when the beat is over.
	// is-arriving is kept on purpose: a click mid-entrance retargets the running
	// transition from wherever it is instead of snapping (the leaving rules win in the cascade).
	function leave(section) {
		slice.call(section.querySelectorAll('.is-entering')).forEach(function (el) { el.classList.remove('is-entering'); });
		section.classList.add('is-leaving');
		return wait(reduced() ? 0 : getTiming().fast + 40);
	}

	// Put new parts where the old ones were and let them rise in, one stagger step per card (up to `cap` steps).
	function place(section, inner, fresh, cap) {
		var old = parts(inner);
		var at = old.length ? old[0] : section.querySelector('template[data-ap-rc-skeleton]');
		var n = 0;
		fresh.forEach(function (el) {
			slice.call(el.querySelectorAll('script')).forEach(remove); // markup only, never code, from a fetched page
			if (el.classList.contains('ap-resource-cards__grid')) {
				slice.call(el.children).forEach(function (card, i) {
					n = Math.min(i, cap);
					card.classList.add('is-entering');
					card.style.setProperty('--ap-rc-i', String(n));
				});
			} else {
				el.classList.add('is-entering');
				el.style.setProperty('--ap-rc-i', el.classList.contains('ap-resource-cards__pages') ? String(n) : '0');
			}
			inner.insertBefore(el, at);
		});
		old.forEach(remove);
		section.classList.remove('is-leaving');
		section.classList.add('is-arriving');
		void section.offsetWidth; // commit the hidden pre-state, so releasing it transitions
		slice.call(section.querySelectorAll('.is-entering')).forEach(function (el) { el.classList.remove('is-entering'); });
		var id = ++placed;
		var t = getTiming();
		setTimeout(function () {
			if (id !== placed) return;
			// Hand the cards back to their own hover transitions.
			section.classList.remove('is-arriving');
			slice.call(inner.querySelectorAll('[style]')).forEach(function (el) {
				el.style.removeProperty('--ap-rc-i');
				if (!el.getAttribute('style')) el.removeAttribute('style');
			});
		}, reduced() ? 0 : t.base + t.step * n + 60);
	}

	// A skeleton the size of the grid that just left, so nothing below the listing moves while the page loads.
	function skeleton(job) {
		var section = job.section;
		var tpl = section.querySelector('template[data-ap-rc-skeleton]');
		if (!tpl || !('content' in tpl)) return;
		var frag = document.importNode(tpl.content, true);
		var grid = frag.querySelector('.ap-resource-cards__grid');
		var bone = grid ? grid.firstElementChild : null;
		if (!bone) return;
		var n = Math.max(1, Math.min(job.cards || 3, SKELETON_CAP));
		for (var i = 1; i < n; i++) grid.appendChild(bone.cloneNode(true));
		if (!job.hadCount) remove(frag.querySelector('.ap-resource-cards__count'));
		if (!job.hadPager) remove(frag.querySelector('.ap-resource-cards__pages'));
		// The placeholders come in almost together (two short steps), not one row at a time like real cards.
		place(section, job.inner, slice.call(frag.children), 2);
	}

	function swap(job, res) {
		var section = job.section, inner = job.inner;
		place(section, inner, parts(res.inner).map(function (el) { return document.importNode(el, true); }), STAGGER_CAP);
		syncTabs(res.doc);
		syncSearch(res.doc);
		if (res.doc.title) document.title = res.doc.title;
		var count = inner.querySelector('.ap-resource-cards__count');
		var empty = inner.querySelector('.ap-resource-cards__empty-title');
		announce(section, count ? count.textContent.trim() : (empty ? empty.textContent.trim() : ''));
		// One geometry read, after the write: a listing whose top has scrolled away (a page number
		// pressed at the bottom) comes back into view, and its height is let go once it is there;
		// otherwise the height is let go now, as far as the window allows (relax).
		if (section.getBoundingClientRect().top < 0) {
			var id = placed, once = false;
			var settle = function () {
				if (once) return;
				once = true;
				window.removeEventListener('scrollend', settle);
				if (id === placed && (!current || current === job)) relax(inner, job.lock); // not once a newer change holds the listing
			};
			if (reduced()) {
				section.scrollIntoView({ behavior: 'auto', block: 'start' });
				settle();
			} else {
				window.addEventListener('scrollend', settle);
				setTimeout(settle, getTiming().slow * 2); // browsers without scrollend
				section.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		} else {
			relax(inner, job.lock);
		}
		// The pressed page number was rebuilt: keep the keyboard on the pager (or the listing).
		if (job.fromPager) {
			var next = inner.querySelector('.ap-resource-cards__num.is-current') || section;
			next.setAttribute('tabindex', '-1');
			try { next.focus({ preventScroll: true }); } catch (e) { next.focus(); }
		}
	}

	function done(job) {
		job.section.removeAttribute('aria-busy');
		if (current === job) current = null;
	}

	function run(job) {
		var section = job.section, result = null, error = null, skeletonAt = 0, settled = false;
		function finish(fn) {
			if (settled || job !== current) return;
			settled = true;
			fn();
		}
		function arrive() {
			finish(function () {
				try {
					swap(job, result);
				} catch (e) {
					// Never leave a half-swapped listing behind: load the page the old way.
					done(job);
					window.location.href = job.url;
					return;
				}
				if (job.push) {
					try { window.history.pushState(state(), '', result.url); } catch (e) { /* another origin: the content still changed */ }
				}
				lastUrl = clean(window.location.href);
				done(job);
			});
		}
		// Whatever went wrong, the visitor still gets the page: the browser loads it the old way.
		function abandon() {
			finish(function () {
				done(job);
				window.location.href = job.url;
			});
		}
		function exited() {
			if (settled || job !== current) return;
			if (result) return arrive();
			if (error) return abandon();
			try {
				skeleton(job);
			} catch (e) { /* no skeleton: the faded listing holds the space instead */ }
			skeletonAt = Date.now();
		}
		// The page arrived while the skeleton shows: it leaves at once, from wherever its own
		// fade-in got to (the leaving rules retarget the running transition), so a response that
		// only just missed the exit costs one fast beat, not a fixed hold, and never flashes.
		function responded() {
			if (settled || job !== current || !skeletonAt) return; // still leaving: exited() picks it up
			leave(section).then(function () { if (result) arrive(); else abandon(); });
		}
		leave(section).then(exited);
		job.request.then(
			function (res) { result = res; responded(); },
			function (err) {
				if (err && err.name === 'AbortError') return; // a newer click took over
				error = err || new Error('failed');
				responded();
			}
		);
	}

	function state() {
		var s = window.history.state, out = {};
		if (s && typeof s === 'object') for (var k in s) if (Object.prototype.hasOwnProperty.call(s, k)) out[k] = s[k];
		out[STATE] = true;
		return out;
	}

	function start(url, opts) {
		var section = listing();
		var inner = section ? section.querySelector(INNER) : null;
		if (!inner) return false;
		if (current && current.controller) current.controller.abort();
		var controller = typeof window.AbortController === 'function' ? new window.AbortController() : null;
		var job = {
			url: url,
			section: section,
			inner: inner,
			controller: controller,
			push: !!opts.push,
			fromPager: !!opts.fromPager,
			lock: inner.offsetHeight, // read before this change writes anything
			cards: inner.querySelectorAll('.ap-resource-cards__grid > .ap-resource-cards__card').length,
			hadCount: !!inner.querySelector('.ap-resource-cards__count'),
			hadPager: !!inner.querySelector('.ap-resource-cards__pages')
		};
		current = job;
		if (opts.tab) pressTab(opts.tab);
		// Hold the height: the leaving cards, the skeleton and the arriving cards all fit in it,
		// so nothing below the listing (or the scroll position) moves while it changes.
		inner.style.minHeight = job.lock + 'px';
		held = inner;
		section.setAttribute('aria-busy', 'true');
		job.request = fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' }, signal: controller ? controller.signal : undefined })
			.then(function (r) {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.text().then(function (text) { return { url: r.url || url, text: text }; });
			})
			.then(function (got) {
				var doc = new DOMParser().parseFromString(got.text, 'text/html');
				var next = doc.querySelector(ROOT + ' ' + INNER);
				if (!next) throw new Error('no listing');
				if (hubKey(doc) !== hubKey(document)) throw new Error('another listing');
				return warm(next, got.url).then(function () { return { doc: doc, inner: next, url: got.url }; });
			});
		run(job);
		return true;
	}

	document.addEventListener('click', function (e) {
		if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
		var link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
		if (!link || !link.matches(LINKS) || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;
		if (!listing()) return;
		var url;
		try { url = new URL(link.href, window.location.href); } catch (err) { return; }
		if (url.origin !== window.location.origin || !ours(link, url)) return;
		e.preventDefault();
		if (clean(url.href) === clean(window.location.href)) return; // the active tab, or the page already shown
		start(url.href, { push: true, fromPager: !!link.closest('.ap-resource-cards__pages'), tab: link });
	});

	// resource-search asks before it loads a results page of this listing's own scope (Enter,
	// "View all N results"): the results then arrive here, with the same animation as a filter.
	document.addEventListener(NAV_EVENT, function (e) {
		var url = e.detail && e.detail.url;
		if (!url || !listing()) return;
		try { url = new URL(url, window.location.href); } catch (err) { return; }
		if (url.origin !== window.location.origin) return;
		e.preventDefault();
		if (clean(url.href) === clean(window.location.href)) return; // that search is already shown
		start(url.href, { push: true, fromPager: false });
	});

	window.addEventListener('popstate', function (e) {
		if (!e.state || !e.state[STATE] || !listing()) return;
		if (clean(window.location.href) === lastUrl) return; // a move within the page (#hash)
		start(window.location.href, { push: false, fromPager: false });
	});

	// Space kept under the results (relax) was sized for this window; a new width lets it go.
	// (Height alone changes whenever a phone's address bar slides away: that keeps it.)
	var lastWidth = window.innerWidth;
	window.addEventListener('resize', function () {
		if (window.innerWidth === lastWidth) return;
		lastWidth = window.innerWidth;
		if (held && !current) unlock(held);
	});

	// The entry the visitor is on becomes one of ours, so Back to it replays the swap too.
	if (listing()) {
		try { window.history.replaceState(state(), '', window.location.href); } catch (e) { /* locked-down frame: Back reloads instead */ }
	}
})();
