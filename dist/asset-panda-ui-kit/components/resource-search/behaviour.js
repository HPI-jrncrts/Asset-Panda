/* resource-search - instant results under the field while the visitor types.
 *
 * The index (one compact JSON file) is fetched the first time any search field
 * on the page is focused, hovered or touched, so the page itself loads nothing
 * extra. Matching runs in memory on every keystroke (a few hundred items take
 * well under a millisecond): every query word must match a word of the title,
 * the categories/type or the excerpt, as a whole word, a prefix (the word being
 * typed), or a near miss (one typo from 4 letters, two from 8). Stop words are
 * optional. Matches are highlighted; Up/Down/Enter/Escape drive the list.
 *
 * Enter (with no row chosen) and "View all N results" open the full results
 * page. When that page belongs to the listing the visitor is on, it is first
 * offered to resource-cards ('ap:listing-navigate', cancelable), which swaps
 * the results in place with the filter animation; otherwise it loads.
 *
 * Delegated and idempotent: one init for every [data-ap-resource-search]. */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;
	var html = document.documentElement;
	if (html.classList.contains('ap-resource-search-js')) return;
	html.classList.add('ap-resource-search-js');

	var STOP = { a: 1, an: 1, and: 1, the: 1, of: 1, to: 1, for: 1, in: 1, on: 1, with: 1, by: 1, is: 1, are: 1, how: 1, what: 1, your: 1, you: 1, or: 1, at: 1, from: 1, vs: 1, do: 1, does: 1, can: 1 };
	var cache = {};

	function norm(s) {
		s = String(s || '').toLowerCase();
		if (s.normalize) s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
		return s.replace(/[\u2018\u2019'`]/g, '').replace(/[^a-z0-9]+/g, ' ').trim();
	}
	function words(s) { s = norm(s); return s ? s.split(' ') : []; }
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	// Optimal string alignment distance, abandoned as soon as it exceeds k.
	function dist(a, b, k) {
		var la = a.length, lb = b.length, i, j;
		if (Math.abs(la - lb) > k) return k + 1;
		var prev2 = null, prev = [], cur;
		for (j = 0; j <= lb; j++) prev[j] = j;
		for (i = 1; i <= la; i++) {
			cur = [i];
			var rowMin = i;
			for (j = 1; j <= lb; j++) {
				var cost = a.charCodeAt(i - 1) === b.charCodeAt(j - 1) ? 0 : 1;
				var v = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + cost);
				if (prev2 && i > 1 && j > 1 && a.charCodeAt(i - 1) === b.charCodeAt(j - 2) && a.charCodeAt(i - 2) === b.charCodeAt(j - 1)) {
					v = Math.min(v, prev2[j - 2] + 1);
				}
				cur[j] = v;
				if (v < rowMin) rowMin = v;
			}
			if (rowMin > k) return k + 1;
			prev2 = prev;
			prev = cur;
		}
		return prev[lb];
	}

	// How well one query word q matches a list of words: 3 exact, 2 prefix, 1 near miss, 0 none.
	function wordMatch(q, list) {
		var best = 0, L = q.length, k = L >= 8 ? 2 : (L >= 4 ? 1 : 0);
		for (var i = 0; i < list.length; i++) {
			var w = list[i];
			if (w === q) return 3;
			if (best < 2 && w.length > L && w.lastIndexOf(q, 0) === 0) { best = 2; continue; }
			if (best < 1 && k && w.length >= L - k) {
				if (dist(q, w, k) <= k || (w.length > L && dist(q, w.slice(0, L), k) <= k)) best = 1;
			}
		}
		return best;
	}

	function prepare(data) {
		var types = data.types || {}, cats = data.cats || {};
		var items = (data.items || []).map(function (it, n) {
			var catNames = (it.c || []).map(function (s) { return cats[s] || ''; }).filter(Boolean);
			var typeLabel = types[it.y] || '';
			return {
				n: n,
				title: it.t || '',
				url: it.u || '#',
				type: it.y || '',
				typeLabel: typeLabel,
				cats: catNames,
				excerpt: it.x || '',
				img: it.i || '',
				date: it.d || '',
				tw: words(it.t),
				mw: words(catNames.join(' ') + ' ' + typeLabel),
				xw: words(it.x),
				tn: ' ' + norm(it.t) + ' '
			};
		});
		return { items: items, types: types };
	}

	function load(url) {
		if (!cache[url]) {
			cache[url] = fetch(url, { credentials: 'omit' }).then(function (r) {
				if (!r.ok) throw new Error('HTTP ' + r.status);
				return r.json();
			}).then(prepare);
			cache[url].catch(function () { delete cache[url]; });
		}
		return cache[url];
	}

	function search(idx, query, scope) {
		var qw = words(query);
		if (!qw.length) return { hits: [], outside: 0, terms: [] };
		var required = qw.filter(function (w) { return !STOP[w]; });
		if (!required.length) required = qw;
		var phrase = ' ' + qw.join(' ');
		var hits = [], outside = 0;
		for (var i = 0; i < idx.items.length; i++) {
			var it = idx.items[i], score = 0, ok = true;
			for (var j = 0; j < qw.length; j++) {
				var q = qw[j];
				var t = wordMatch(q, it.tw), m = t === 3 ? 0 : wordMatch(q, it.mw), x = (t || m) ? 0 : wordMatch(q, it.xw);
				var s = Math.max(t * 4, m * 2.4, x * 1.2);
				if (!s && required.indexOf(q) !== -1) { ok = false; break; }
				score += s;
			}
			if (!ok) continue;
			if (it.tn.indexOf(phrase) !== -1) score += 10;
			if (it.tn.indexOf(' ' + qw.join(' ')) === 0) score += 4;
			if (scope && it.type !== scope) { outside++; continue; }
			hits.push({ it: it, s: score });
		}
		hits.sort(function (a, b) { return b.s - a.s || (a.it.date < b.it.date ? 1 : a.it.date > b.it.date ? -1 : 0); });
		return { hits: hits, outside: outside, terms: qw };
	}

	// Wrap the parts of `text` that match the query words in <mark>.
	function mark(text, terms) {
		var out = '', last = 0, re = /[A-Za-z0-9\u00C0-\u024F]+/g, m;
		while ((m = re.exec(text))) {
			var w = norm(m[0]), hit = 0;
			for (var i = 0; i < terms.length; i++) {
				var q = terms[i];
				if (STOP[q] && terms.length > 1) continue;
				if (w === q) { hit = m[0].length; break; }
				if (w.lastIndexOf(q, 0) === 0) { hit = Math.max(hit, Math.min(m[0].length, q.length)); continue; }
				if (q.length >= 4 && wordMatch(q, [w])) hit = Math.max(hit, m[0].length);
			}
			if (hit) {
				out += esc(text.slice(last, m.index)) + '<mark>' + esc(m[0].slice(0, hit)) + '</mark>' + esc(m[0].slice(hit));
				last = m.index + m[0].length;
			}
		}
		return out + esc(text.slice(last));
	}

	// The query becomes one path segment of live's filter address (/resource-center/filter/<q>/...):
	// a slash would split it, so it travels as a space (%20, as live encodes it).
	function fill(tpl, q) { return tpl ? tpl.split('{q}').join(encodeURIComponent(q.replace(/[\/\\]+/g, ' ').replace(/\s+/g, ' ').trim())) : ''; }

	function init(root) {
		if (root.getAttribute('data-ap-rs-ready')) return;
		root.setAttribute('data-ap-rs-ready', '1');
		var form = root.querySelector('.ap-resource-search__form');
		var input = root.querySelector('.ap-resource-search__input');
		var clear = root.querySelector('.ap-resource-search__clear');
		var panel = root.querySelector('.ap-resource-search__panel');
		var list = root.querySelector('.ap-resource-search__list');
		var status = root.querySelector('.ap-resource-search__status');
		var foot = root.querySelector('.ap-resource-search__foot');
		if (!input || !panel || !list) return;
		var url = root.getAttribute('data-index');
		var max = parseInt(root.getAttribute('data-max'), 10) || 8;
		var action = root.getAttribute('data-action') || '';
		var actionAll = root.getAttribute('data-action-all') || '';
		var homeScope = root.getAttribute('data-scope') || '';
		var scopeLabel = root.getAttribute('data-scope-label') || '';
		var scope = homeScope;
		var idx = null, failed = false, failedAt = 0, loading = false, active = -1, rows = [], lastQ = null;
		var listId = list.id;

		// Fetch the index once. A failed fetch (offline, a 429 from the host) is retried on the next
		// interaction or keystroke, at most every 3 s; meanwhile Enter still opens the full results page.
		function want() {
			if (idx || loading || (failed && Date.now() - failedAt < 3000)) return;
			loading = true;
			load(url).then(function (d) { loading = false; failed = false; idx = d; if (document.activeElement === input && input.value.trim()) run(true); })
				.catch(function () { loading = false; failed = true; failedAt = Date.now(); if (document.activeElement === input && input.value.trim()) run(true); });
		}
		root.addEventListener('pointerenter', want);
		root.addEventListener('focusin', want);
		root.addEventListener('touchstart', want, { passive: true });

		function open(on) {
			panel.hidden = !on;
			root.classList.toggle('is-open', on);
			input.setAttribute('aria-expanded', on ? 'true' : 'false');
			if (!on) setActive(-1);
		}
		function setActive(i) {
			if (active > -1 && rows[active]) rows[active].classList.remove('is-active'), rows[active].setAttribute('aria-selected', 'false');
			active = i;
			if (active > -1 && rows[active]) {
				rows[active].classList.add('is-active');
				rows[active].setAttribute('aria-selected', 'true');
				input.setAttribute('aria-activedescendant', rows[active].id);
				var r = rows[active], top = r.offsetTop, bottom = top + r.offsetHeight;
				if (top < panel.scrollTop) panel.scrollTop = top - 8;
				else if (bottom > panel.scrollTop + panel.clientHeight) panel.scrollTop = bottom - panel.clientHeight + 8;
			} else {
				input.removeAttribute('aria-activedescendant');
			}
		}
		function allUrl(q) { return fill(scope ? action : (actionAll || action), q); }

		// Opens a full results page. One of this listing's own (the scope the page was built
		// with) is first offered to resource-cards as a cancelable 'ap:listing-navigate' event:
		// on a listing it changes the results in place, animated like a filter tab. Without a
		// taker (no listing on the page, no script) the browser loads the page as before.
		function go(href) {
			if (scope === homeScope) {
				var ev = null;
				try { ev = new window.CustomEvent('ap:listing-navigate', { bubbles: true, cancelable: true, detail: { url: href } }); } catch (e) { ev = null; }
				if (ev && !root.dispatchEvent(ev)) {
					open(false);
					// On a touch screen, put the keyboard away so the results can be seen.
					if (window.matchMedia && window.matchMedia('(hover: none)').matches) input.blur();
					return;
				}
			}
			window.location.href = href;
		}

		function run(force) {
			var q = input.value;
			if (clear) clear.hidden = !q;
			if (!q.trim()) { lastQ = ''; list.innerHTML = ''; status.textContent = ''; foot.innerHTML = ''; open(false); return; }
			if (!force && q === lastQ) return;
			lastQ = q;
			if (!idx) {
				list.innerHTML = '';
				foot.innerHTML = '';
				status.textContent = failed ? 'Press Enter to see every result.' : 'Loading\u2026';
				open(true);
				return;
			}
			var res = search(idx, q, scope);
			var shown = res.hits.slice(0, max);
			rows = [];
			active = -1;
			input.removeAttribute('aria-activedescendant');
			var h = '';
			for (var i = 0; i < shown.length; i++) {
				var it = shown[i].it;
				var meta = it.typeLabel ? '<span class="ap-resource-search__type">' + esc(it.typeLabel) + '</span>' : '';
				if (it.cats.length) meta += '<span class="ap-resource-search__cat">' + mark(it.cats.slice(0, 2).join(', '), res.terms) + '</span>';
				h += '<li class="ap-resource-search__item" role="option" aria-selected="false" id="' + listId + '-' + i + '">'
					+ '<a class="ap-resource-search__link" href="' + esc(it.url) + '" tabindex="-1">'
					+ (it.img ? '<img class="ap-resource-search__thumb" src="' + esc(it.img) + '" alt="" width="72" height="48"' + (i > 3 ? ' loading="lazy"' : '') + ' decoding="async">' : '<span class="ap-resource-search__thumb ap-resource-search__thumb--none" aria-hidden="true"></span>')
					+ '<span class="ap-resource-search__text">'
					+ (meta ? '<span class="ap-resource-search__meta">' + meta + '</span>' : '')
					+ '<span class="ap-resource-search__title">' + mark(it.title, res.terms) + '</span>'
					+ (it.excerpt ? '<span class="ap-resource-search__excerpt">' + mark(it.excerpt, res.terms) + '</span>' : '')
					+ '</span></a></li>';
			}
			list.innerHTML = h;
			rows = Array.prototype.slice.call(list.children);
			var total = res.hits.length;
			var where = scope && scopeLabel ? ' in ' + scopeLabel : '';
			if (!total) {
				status.innerHTML = '<span class="ap-resource-search__none">No results' + esc(where) + ' for \u201c' + esc(q.trim()) + '\u201d.</span> Check the spelling or try a broader word.';
			} else {
				status.textContent = total + (total === 1 ? ' result' : ' results') + where;
			}
			var f = '';
			if (total > shown.length && allUrl(q)) {
				f += '<a class="ap-resource-search__all" href="' + esc(allUrl(q)) + '">View all ' + total + ' results' + esc(where) + '</a>';
			}
			if (scope && res.outside) {
				f += '<button type="button" class="ap-resource-search__widen">Search all resources (' + res.outside + ' more)</button>';
			} else if (!scope && homeScope) {
				f += '<button type="button" class="ap-resource-search__narrow">Only ' + esc(scopeLabel || 'this section') + '</button>';
			}
			foot.innerHTML = f;
			foot.hidden = !f;
			open(true);
		}

		input.addEventListener('input', function () { if (!idx) want(); run(false); });
		input.addEventListener('focus', function () { if (input.value.trim() && idx) run(true); });
		input.addEventListener('keydown', function (e) {
			var k = e.key;
			if (k === 'ArrowDown' || k === 'Down') {
				e.preventDefault();
				if (panel.hidden && input.value.trim()) run(true);
				if (rows.length) setActive(active < rows.length - 1 ? active + 1 : 0);
			} else if (k === 'ArrowUp' || k === 'Up') {
				e.preventDefault();
				if (rows.length) setActive(active > 0 ? active - 1 : rows.length - 1);
			} else if (k === 'Enter') {
				var q = input.value.trim();
				if (active > -1 && rows[active]) {
					e.preventDefault();
					window.location.href = rows[active].querySelector('a').href;
				} else if (q && allUrl(q)) {
					e.preventDefault();
					go(allUrl(q));
				}
			} else if (k === 'Escape' || k === 'Esc') {
				if (!panel.hidden) { e.preventDefault(); open(false); }
				else if (input.value) { e.preventDefault(); input.value = ''; run(true); }
			}
		});
		if (form) {
			form.addEventListener('submit', function (e) {
				var q = input.value.trim();
				if (q && allUrl(q)) { e.preventDefault(); go(allUrl(q)); }
				else if (!q) e.preventDefault();
			});
		}
		if (clear) {
			clear.addEventListener('click', function () { input.value = ''; run(true); input.focus(); });
		}
		list.addEventListener('mousemove', function (e) {
			var li = e.target.closest ? e.target.closest('.ap-resource-search__item') : null;
			if (li) { var i = rows.indexOf(li); if (i !== active) setActive(i); }
		});
		foot.addEventListener('click', function (e) {
			var all = e.target.closest ? e.target.closest('a.ap-resource-search__all') : null;
			if (all) {
				// A plain click goes the same way as Enter; a modified one opens the link as usual.
				if (e.button === 0 && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey) {
					e.preventDefault();
					go(all.href);
				}
				return;
			}
			var b = e.target.closest ? e.target.closest('button') : null;
			if (!b) return;
			scope = b.classList.contains('ap-resource-search__widen') ? '' : homeScope;
			run(true);
			input.focus();
		});
		document.addEventListener('pointerdown', function (e) {
			if (!panel.hidden && !root.contains(e.target)) open(false);
		});
		root.addEventListener('focusout', function (e) {
			if (e.relatedTarget && !root.contains(e.relatedTarget)) open(false);
		});
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-ap-resource-search]'), init);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
