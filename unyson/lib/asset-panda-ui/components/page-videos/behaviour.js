/**
 * page-videos — a large player, a deck of video cards, autoplay and loop.
 *
 * One state object per [data-ap-pv-gallery] (built on load, or on first use).
 *
 * Deck. Each card's place is its `--slot` (0 = up next; the playing video's
 * card is visually hidden, with aria-current). A swap — a card clicked, or the
 * playing video ending — re-numbers the slots and animates the change with
 * the Web Animations API on --ap-dur-slow / --ap-ease-standard, after the
 * MDJAmin pen the client sent (the next card grows into the stage, the old
 * one goes to the back of the line):
 *   - the chosen card's poster flies from its card into the player;
 *   - the finished video's poster shrinks and tilts back towards the deck and
 *     fades, while its card fades up into the back of the deck;
 *   - every other card slides to its new place (FLIP).
 * prefers-reduced-motion, a hidden tab, or a gallery off screen = instant.
 *
 * Player. The YouTube IFrame Player API (www.youtube.com/iframe_api, requested
 * once, when a gallery comes within 600px of the viewport) drives one player
 * per gallery on youtube-nocookie.com. When the player is half in view the
 * first video starts MUTED (browsers refuse sound without a click; an
 * "Unmute" pill turns it on); each ENDED advances the deck and plays the
 * next, and the last loops back to the first. A muted video pauses while the
 * player is off screen and resumes when it is back. A click on a card or the
 * poster wins: that video plays with sound (where the browser allows) and the
 * cycle carries on from it when it ends; a visitor's pause holds everything
 * (nothing advances until a video ends). No autoplay for prefers-reduced-
 * motion or Save-Data visitors: for them nothing loads from YouTube until they
 * click, as before.
 *
 * If the API can't load (blocked, offline) there is no autoplay and no
 * auto-advance: a click plays that video in a plain youtube-nocookie iframe,
 * as the earlier gallery did. A timed advance would cut tutorials off
 * mid-sentence, so it is deliberately not used.
 *
 * The dataLayer `ap_video_play` push fires for a visitor's own play (a card
 * or the poster) only — never for autoplay or auto-advance — so the event
 * still means "a visitor chose a video". Geometry is read once per swap, never
 * in a scroll handler. The attributes are distinct from media-band's
 * [data-ap-video] so the two never both handle a click.
 */
(function () {
	'use strict';

	if (window.apPageVideosReady) {
		return;
	}
	window.apPageVideosReady = true;

	var ID_RE = /^[A-Za-z0-9_-]{11}$/;
	var LIST_RE = /^[A-Za-z0-9_-]{10,64}$/;
	var API_URL = 'https://www.youtube.com/iframe_api';
	var HOST = 'https://www.youtube-nocookie.com';
	var COVER_WAIT = 4000; // a poster gives way to the player's own UI if a picked video hasn't started by then
	var motionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
	var canAnimate = typeof Element !== 'undefined' && typeof Element.prototype.animate === 'function';
	var hasIO = typeof window.IntersectionObserver === 'function';

	function calm() {
		return !!(motionQuery && motionQuery.matches);
	}

	function saveData() {
		var c = navigator.connection;
		return !!(c && c.saveData);
	}

	/* ---- Tokens ---- */
	function token(el, name, fallback) {
		var v = el && window.getComputedStyle ? window.getComputedStyle(el).getPropertyValue(name).trim() : '';
		return v || fallback;
	}

	function toMs(v, fallback) {
		var m = /^(-?[\d.]+)(ms|s)$/.exec(v);
		return m ? parseFloat(m[1]) * (m[2] === 's' ? 1000 : 1) : fallback;
	}

	/* ---- YouTube IFrame API: requested once, on demand ---- */
	var api = { state: 'idle', waiters: [], timer: 0 }; // idle | loading | ready | failed

	function apiReady() {
		return !!(window.YT && typeof window.YT.Player === 'function');
	}

	function apiSettle(ok) {
		if (api.state === 'ready' || api.state === 'failed') {
			return;
		}
		api.state = ok ? 'ready' : 'failed';
		clearTimeout(api.timer);
		var waiting = api.waiters;
		api.waiters = [];
		for (var i = 0; i < waiting.length; i++) {
			waiting[i](ok);
		}
	}

	function loadApi() {
		if (api.state !== 'idle') {
			return;
		}
		if (apiReady()) {
			api.state = 'ready';
			return;
		}
		api.state = 'loading';
		var previous = window.onYouTubeIframeAPIReady;
		window.onYouTubeIframeAPIReady = function () {
			if (typeof previous === 'function') {
				try {
					previous();
				} catch (e) {
					// another embed's callback failing must not stop ours
				}
			}
			apiSettle(true);
		};
		if (!document.querySelector('script[src*="youtube.com/iframe_api"]')) {
			var script = document.createElement('script');
			script.src = API_URL;
			script.async = true;
			script.onerror = function () {
				apiSettle(false);
			};
			(document.head || document.body).appendChild(script);
		}
		api.timer = setTimeout(function () {
			apiSettle(apiReady());
		}, 10000);
	}

	/* cb(true) once YT.Player exists; cb(false) if it never will (or not within `wait` ms). */
	function whenApi(cb, wait) {
		if (apiReady()) {
			api.state = 'ready';
			cb(true);
			return;
		}
		if (api.state === 'failed') {
			cb(false);
			return;
		}
		var done = false;
		var once = function (ok) {
			if (!done) {
				done = true;
				cb(ok);
			}
		};
		api.waiters.push(once);
		if (wait) {
			setTimeout(function () {
				once(apiReady());
			}, wait);
		}
		loadApi();
	}

	/* ---- Small helpers ---- */
	function track(id, title) {
		if (window.dataLayer && window.dataLayer.push) {
			window.dataLayer.push({ event: 'ap_video_play', video_id: id, video_title: title || '', page_path: location.pathname });
		}
	}

	function announce(g, text) {
		if (g.status) {
			g.status.textContent = text;
		}
	}

	/* Call a player method if it exists yet (the API adds them on ready). */
	function call(player, method, a, b) {
		try {
			if (player && typeof player[method] === 'function') {
				return player[method](a, b);
			}
		} catch (e) {
			// a player torn down mid-call
		}
		return undefined;
	}

	function rectOf(el) {
		return el ? el.getBoundingClientRect() : null;
	}

	function removeNode(el) {
		if (el && el.parentNode) {
			el.parentNode.removeChild(el);
		}
	}

	function makeFrame(it) {
		var frame = document.createElement('iframe');
		frame.className = 'ap-page-videos__frame';
		frame.src = HOST + '/embed/' + it.id + '?autoplay=1&rel=0&modestbranding=1&playsinline=1' + (LIST_RE.test(it.list) ? '&list=' + it.list : '');
		frame.title = it.title || 'Video';
		frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
		frame.setAttribute('allowfullscreen', '');
		frame.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
		return frame;
	}

	/* ---- Gallery state ---- */
	function setup(root) {
		if (root.apPv) {
			return root.apPv;
		}
		var g = {
			root: root,
			screen: root.querySelector('[data-ap-pv-screen]'),
			status: root.querySelector('[data-ap-pv-status]'),
			sound: root.querySelector('[data-ap-pv-sound]'),
			list: root.querySelector('[data-ap-pv-cards]'),
			captions: root.querySelectorAll('[data-ap-pv-caption]'),
			cards: [],
			slots: [],
			active: 0,
			player: null, // YT.Player
			ready: false, // the player has fired onReady
			pending: '', // a video asked for before onReady
			frame: null, // plain iframe (API unavailable)
			playing: false,
			muted: false,
			chosen: false, // the current video was a visitor's pick
			touched: false, // a visitor has picked or played something
			started: false, // autoplay has been tried
			autoPaused: false,
			inView: true,
			errors: 0,
			swapping: null,
			coverTimer: 0,
			soundTimer: 0
		};
		var buttons = root.querySelectorAll('[data-ap-pv-card]');
		for (var i = 0; i < buttons.length; i++) {
			g.cards.push(buttons[i]);
			g.slots.push(buttons[i].closest('[data-ap-pv-slot]') || buttons[i].parentNode);
			if (buttons[i].getAttribute('aria-current') === 'true') {
				g.active = i;
			}
		}
		root.apPv = g;
		if (!g.cards.length) {
			g.solo = soloItem(g); // one video, no deck: read it now — its poster link goes once it plays
		}
		// The server-rendered poster is the first video's cover.
		var initial = covers(g);
		for (var c = 0; c < initial.length; c++) {
			initial[c].setAttribute('data-ap-pv-cover-of', String(g.active));
		}
		return g;
	}

	/* Video i: from its card, or (a one-video gallery) as read from the poster link at setup. */
	function item(g, i) {
		var c = g.cards[i];
		if (c) {
			var img = c.querySelector('img');
			return {
				id: c.getAttribute('data-ap-pv-id') || '',
				list: c.getAttribute('data-ap-pv-list') || '',
				title: c.getAttribute('data-ap-pv-title') || '',
				poster: img ? img.currentSrc || img.src : ''
			};
		}
		return g.solo || soloItem(g);
	}

	function soloItem(g) {
		var link = g.screen ? g.screen.querySelector('[data-ap-pv]') : null;
		var img = g.screen ? g.screen.querySelector('.ap-page-videos__poster') : null;
		return {
			id: link ? link.getAttribute('data-ap-pv') || '' : '',
			list: link ? link.getAttribute('data-ap-pv-list') || '' : '',
			title: link ? link.getAttribute('data-ap-pv-title') || '' : '',
			poster: img ? img.currentSrc || img.src : ''
		};
	}

	function count(g) {
		return Math.max(1, g.cards.length);
	}

	function slotOf(g, i) {
		var n = count(g);
		return i === g.active ? n - 1 : (i - g.active - 1 + n) % n;
	}

	function cardAtSlot(g, slot) {
		for (var i = 0; i < g.cards.length; i++) {
			if (i !== g.active && slotOf(g, i) === slot) {
				return g.cards[i];
			}
		}
		return null;
	}

	/* Re-number the deck around g.active; show its caption. */
	function applySlots(g) {
		for (var i = 0; i < g.cards.length; i++) {
			var on = i === g.active;
			var slot = slotOf(g, i);
			g.slots[i].style.setProperty('--slot', String(slot));
			g.slots[i].classList.toggle('is-active', on);
			g.slots[i].classList.toggle('is-next', !on && slot === 0);
			if (on) {
				g.cards[i].setAttribute('aria-current', 'true');
				g.cards[i].setAttribute('tabindex', '-1');
			} else {
				g.cards[i].removeAttribute('aria-current');
				g.cards[i].removeAttribute('tabindex');
			}
		}
		for (var c = 0; c < g.captions.length; c++) {
			g.captions[c].hidden = g.captions[c].getAttribute('data-ap-pv-caption') !== String(g.active);
		}
	}

	/* ---- The screen: player iframe, a poster cover over it, the Unmute pill ---- */
	function covers(g) {
		return g.screen ? g.screen.querySelectorAll('.ap-page-videos__play, [data-ap-pv-cover]') : [];
	}

	function frameEl(g) {
		return g.player ? call(g.player, 'getIframe') : g.frame;
	}

	/* Take the poster away so the player shows; keyboard focus moves into the player. */
	function dropCover(g) {
		clearTimeout(g.coverTimer);
		var list = covers(g);
		var wait = calm() ? 0 : toMs(token(g.root, '--ap-dur-base', '300ms'), 300);
		for (var i = 0; i < list.length; i++) {
			var cover = list[i];
			if (cover.classList.contains('is-gone')) {
				continue;
			}
			if (cover.contains(document.activeElement)) {
				var f = frameEl(g);
				if (f && f.focus) {
					f.focus();
				}
			}
			cover.classList.add('is-gone');
			setTimeout(removeNode.bind(null, cover), wait);
		}
	}

	/* Show video i's poster over the screen (kept if it already shows i). */
	function setCover(g, i) {
		if (!g.screen) {
			return;
		}
		var list = covers(g);
		var k;
		for (k = 0; k < list.length; k++) {
			if (!list[k].classList.contains('is-gone') && list[k].getAttribute('data-ap-pv-cover-of') === String(i)) {
				return;
			}
		}
		for (k = 0; k < list.length; k++) {
			removeNode(list[k]);
		}
		var it = item(g, i);
		var cover = document.createElement('div');
		cover.className = 'ap-page-videos__cover';
		cover.setAttribute('data-ap-pv-cover', '');
		cover.setAttribute('data-ap-pv-cover-of', String(i));
		if (it.poster) {
			var img = document.createElement('img');
			img.className = 'ap-page-videos__poster';
			img.src = it.poster;
			img.alt = '';
			cover.appendChild(img);
		}
		g.screen.insertBefore(cover, g.sound || null);
	}

	/* The cover stays until the video plays — or, for a picked video, until
	 * COVER_WAIT, so a blocked autoplay shows the player's own play button
	 * rather than a dead picture. The first poster waiting on muted autoplay is
	 * kept: it is itself the play button. */
	function armCover(g, keep) {
		clearTimeout(g.coverTimer);
		if (!keep) {
			g.coverTimer = setTimeout(function () {
				dropCover(g);
			}, COVER_WAIT);
		}
	}

	function isMuted(g) {
		return !!(g.player && g.ready && call(g.player, 'isMuted'));
	}

	function syncSound(g) {
		if (!g.sound) {
			return;
		}
		var show = !!(g.player && g.playing && isMuted(g));
		g.sound.hidden = !show;
		if (show && !g.soundTimer) {
			// The player's own volume control fires no event: check now and then while the pill shows.
			g.soundTimer = setInterval(function () {
				syncSound(g);
			}, 1500);
		} else if (!show && g.soundTimer) {
			clearInterval(g.soundTimer);
			g.soundTimer = 0;
		}
	}

	function unmute(g) {
		g.muted = false;
		if (g.player && g.ready) {
			call(g.player, 'unMute');
		}
		syncSound(g);
	}

	function createPlayer(g, i, muted) {
		var it = item(g, i);
		var holder = document.createElement('div');
		holder.className = 'ap-page-videos__frame';
		g.screen.insertBefore(holder, g.screen.firstChild);
		g.muted = muted;
		g.ready = false;
		g.pending = '';
		var vars = { autoplay: 1, mute: muted ? 1 : 0, playsinline: 1, rel: 0, modestbranding: 1 };
		if (/^https?:$/.test(location.protocol)) {
			vars.origin = location.origin;
		}
		try {
			g.player = new window.YT.Player(holder, {
				host: HOST,
				videoId: it.id,
				width: '100%',
				height: '100%',
				playerVars: vars,
				events: {
					onReady: function (e) {
						g.ready = true;
						var f = call(e.target, 'getIframe');
						if (f) {
							f.className = 'ap-page-videos__frame';
							f.title = item(g, g.active).title || 'Video';
						}
						call(e.target, g.muted ? 'mute' : 'unMute');
						if (g.pending) {
							call(e.target, 'loadVideoById', g.pending);
							g.pending = '';
						} else {
							call(e.target, 'playVideo');
						}
					},
					onStateChange: function (e) {
						onState(g, e.data);
					},
					onError: function () {
						onError(g);
					}
				}
			});
		} catch (err) {
			g.player = null;
			removeNode(holder);
			return false;
		}
		return true;
	}

	/* The API is unavailable: a plain player for a visitor's click (no auto-advance). */
	function plainFrame(g, i, focus) {
		removeNode(g.frame);
		g.frame = makeFrame(item(g, i));
		g.screen.insertBefore(g.frame, g.screen.firstChild);
		dropCover(g);
		if (focus) {
			g.frame.focus();
		}
	}

	/* Play video i in the screen. `user` = a visitor asked for it; `quiet` = no announcement. */
	function play(g, i, user, focus, quiet) {
		var it = item(g, i);
		setCover(g, i);
		g.chosen = !!user;
		if (user) {
			g.touched = true;
		}
		if (!quiet) {
			announce(g, (ID_RE.test(it.id) ? 'Now playing: ' : 'Selected: ') + (it.title || 'video'));
		}
		if (!ID_RE.test(it.id)) {
			return; // nothing to play: the poster stays, and the cycle stops here
		}
		if (user) {
			track(it.id, it.title);
		}
		if (g.player) {
			if (user) {
				unmute(g);
			}
			var f = frameEl(g);
			if (f) {
				f.title = it.title || 'Video';
			}
			if (g.ready) {
				call(g.player, 'loadVideoById', it.id);
			} else {
				g.pending = it.id;
			}
			armCover(g, false);
			if (focus && f && f.focus) {
				f.focus();
			}
			return;
		}
		if (g.frame && !apiReady()) {
			plainFrame(g, i, focus); // the API failed earlier: carry on as the plain gallery
			return;
		}
		removeNode(g.frame); // the API arrived after a plain fallback: upgrade
		g.frame = null;
		armCover(g, !user);
		whenApi(function (ok) {
			if (g.active !== i || g.player || g.frame) {
				return; // moved on while the API loaded
			}
			if (ok && createPlayer(g, i, !user)) {
				if (user) {
					dropCover(g);
					var fr = frameEl(g);
					if (focus && fr && fr.focus) {
						fr.focus();
					}
				}
				return;
			}
			if (user) {
				plainFrame(g, i, focus);
			}
			// autoplay without the API: the poster waits for a click
		}, user ? 2500 : 0);
	}

	function onState(g, s) {
		if (s === 1 && g.swapping) {
			// the previous video got going mid-swap: hold it; the swap loads the right one when it lands
			call(g.player, 'pauseVideo');
			return;
		}
		if (s === 1) {
			// playing
			g.playing = true;
			g.errors = 0;
			dropCover(g);
		} else if (s === 2) {
			// paused (by the visitor, or by us off screen / for a swap)
			g.playing = false;
		} else if (s === 0) {
			// ended: on to the next card
			g.playing = false;
			if (!g.swapping) {
				advance(g);
			}
		}
		syncSound(g);
	}

	/* A video that can't play (removed, embedding off): an auto-advanced one is
	 * skipped after a moment; a visitor's pick stays on YouTube's message. */
	function onError(g) {
		g.playing = false;
		syncSound(g);
		if (g.chosen || ++g.errors >= count(g)) {
			return;
		}
		var at = g.active;
		setTimeout(function () {
			if (g.active === at && !g.playing) {
				advance(g);
			}
		}, 1500);
	}

	/* The next playable video after the active one, looping; replay if it's the only one. */
	function advance(g) {
		var n = count(g);
		for (var k = 1; k <= n; k++) {
			var j = (g.active + k) % n;
			if (ID_RE.test(item(g, j).id)) {
				if (j === g.active) {
					call(g.player, 'seekTo', 0, true);
					call(g.player, 'playVideo');
				} else {
					swap(g, j, false, false);
				}
				return;
			}
		}
	}

	/* ---- The swap ---- */
	function ghost(g, src, at, base) {
		var el = document.createElement('div');
		el.className = 'ap-page-videos__ghost';
		el.setAttribute('aria-hidden', 'true');
		el.style.left = at.left - base.left + 'px';
		el.style.top = at.top - base.top + 'px';
		el.style.width = at.width + 'px';
		el.style.height = at.height + 'px';
		if (src) {
			var img = document.createElement('img');
			img.src = src;
			img.alt = '';
			el.appendChild(img);
		}
		g.root.appendChild(el);
		return el;
	}

	/* The transform that lays a box drawn at `from` (origin top-left) over `to`. */
	function mapTo(to, from) {
		return 'translate(' + (to.left - from.left) + 'px,' + (to.top - from.top) + 'px) scale(' + to.width / from.width + ')';
	}

	function swap(g, to, user, focus) {
		if (g.swapping) {
			g.swapping.finish(false); // a second pick mid-swap: settle the first at once
		}
		var from = g.active;
		if (to === from || !g.cards[to]) {
			return;
		}
		var animate = canAnimate && !calm() && g.inView && !document.hidden && !!g.screen;
		var base, screen, inThumb;
		var first = [];
		if (animate) {
			base = rectOf(g.root);
			screen = rectOf(g.screen);
			for (var i = 0; i < g.slots.length; i++) {
				first.push(rectOf(g.slots[i]));
			}
			inThumb = rectOf(g.cards[to].querySelector('[data-ap-pv-thumb]'));
		}

		if (g.player && g.playing) {
			call(g.player, 'pauseVideo'); // silence the old video for the move
		}
		g.active = to;
		applySlots(g);
		if (g.list && g.list.scrollWidth > g.list.clientWidth + 1) {
			g.list.scrollLeft = 0; // the swipe row: back to "up next"
		}

		var anims = [];
		var ghosts = [];
		var D = 600;
		if (animate && screen && screen.width) {
			D = toMs(token(g.root, '--ap-dur-slow', '600ms'), 600);
			var EASE = token(g.root, '--ap-ease-standard', 'cubic-bezier(0.25, 0.45, 0.45, 0.95)');
			var EASE_OUT = token(g.root, '--ap-ease-out', 'cubic-bezier(0.16, 1, 0.3, 1)');
			var outThumb = rectOf(g.cards[from] ? g.cards[from].querySelector('[data-ap-pv-thumb]') : null);
			if (outThumb && outThumb.width && outThumb.left > base.right - outThumb.width * 0.6) {
				// The swipe row's back is off to the right: aim at the visible end of the row instead.
				outThumb = { left: base.right - outThumb.width * 0.6, top: outThumb.top, width: outThumb.width, height: outThumb.height };
			}

			// The rest of the deck slides to its new places.
			for (var s = 0; s < g.slots.length; s++) {
				if (s === to || s === from || !first[s] || !first[s].width) {
					continue;
				}
				var last = rectOf(g.slots[s]);
				if (!last.width || !last.height) {
					continue;
				}
				var dx = first[s].left - last.left;
				var dy = first[s].top - last.top;
				var sx = first[s].width / last.width;
				var sy = first[s].height / last.height;
				if (Math.abs(dx) < 0.5 && Math.abs(dy) < 0.5 && Math.abs(sx - 1) < 0.002 && Math.abs(sy - 1) < 0.002) {
					continue;
				}
				anims.push(g.slots[s].animate(
					[{ transform: 'translate(' + dx + 'px,' + dy + 'px) scale(' + sx + ',' + sy + ')' }, { transform: 'none' }],
					{ duration: D, easing: EASE }
				));
			}

			// The finished video's card fades up into the back of the deck.
			if (g.slots[from]) {
				anims.push(g.slots[from].animate(
					[{ opacity: 0, transform: 'translateY(24px)' }, { opacity: 1, transform: 'none' }],
					{ duration: D * 0.55, delay: D * 0.45, easing: EASE_OUT, fill: 'backwards' }
				));
			}

			// Its poster leaves the player, tilting back towards the deck…
			var gOut = ghost(g, item(g, from).poster, screen, base);
			ghosts.push(gOut);
			anims.push(gOut.animate(
				outThumb && outThumb.width ?
					[
						{ transform: 'none', opacity: 1 },
						{ opacity: 1, offset: 0.4 },
						{ transform: mapTo(outThumb, screen) + ' rotate(-6deg)', opacity: 0 }
					] :
					[{ transform: 'none', opacity: 1 }, { transform: 'scale(0.92)', opacity: 0 }],
				{ duration: D, easing: EASE, fill: 'forwards' }
			));

			// …while the chosen card's poster grows from its card into the player.
			var gIn = ghost(g, item(g, to).poster, screen, base);
			ghosts.push(gIn);
			var fromCard = inThumb && inThumb.width;
			anims.push(gIn.animate(
				[
					{ transform: fromCard ? mapTo(inThumb, screen) : 'scale(0.9)', opacity: fromCard ? 1 : 0 },
					{ transform: 'none', opacity: 1 }
				],
				{ duration: D, easing: EASE, fill: 'forwards' }
			));
			g.screen.classList.add('is-swapping');
		}

		var landed = false;
		function land(playIt) {
			if (landed) {
				return;
			}
			landed = true;
			g.swapping = null;
			if (g.screen) {
				g.screen.classList.remove('is-swapping');
			}
			if (playIt) {
				play(g, to, user, focus, false); // the new poster goes under the ghost first…
			} else {
				setCover(g, to);
			}
			for (var a = 0; a < anims.length; a++) {
				try {
					anims[a].cancel(); // …then the ghosts go (the slid cards are already at rest)
				} catch (e) {
					// already gone
				}
			}
			for (var k = 0; k < ghosts.length; k++) {
				removeNode(ghosts[k]);
			}
		}

		if (!anims.length) {
			land(true);
			return;
		}
		g.swapping = {
			finish: function (playIt) {
				land(playIt);
			}
		};
		var pending = [];
		for (var p = 0; p < anims.length; p++) {
			pending.push(anims[p].finished);
		}
		var settle = function () {
			land(true);
		};
		Promise.all(pending).then(settle, settle);
		setTimeout(settle, D * 2 + 600); // a throttled tab never strands the swap
	}

	/* ---- Visitor actions ---- */
	function choose(g, i) {
		if (i < 0) {
			return;
		}
		g.touched = true;
		if (i === g.active) {
			userPlay(g, false); // the hidden current card (screen readers)
			return;
		}
		var hadFocus = document.activeElement === g.cards[i];
		var wasSlot = slotOf(g, i);
		swap(g, i, true, false);
		if (hadFocus) {
			// The chosen card leaves the deck: keep focus on the card now in its place.
			var next = cardAtSlot(g, Math.min(wasSlot, count(g) - 2));
			if (next) {
				next.focus({ preventScroll: true });
			}
		}
		reveal(g);
	}

	/* The poster's play button: play the active video, with sound. */
	function userPlay(g, focus) {
		var it = item(g, g.active);
		if (!ID_RE.test(it.id)) {
			return;
		}
		g.touched = true;
		if (g.player) {
			g.chosen = true;
			unmute(g);
			dropCover(g);
			if (g.ready) {
				call(g.player, 'playVideo');
			}
			track(it.id, it.title);
			announce(g, 'Now playing: ' + (it.title || 'video'));
			var f = frameEl(g);
			if (focus && f && f.focus) {
				f.focus();
			}
			return;
		}
		play(g, g.active, true, focus, false);
	}

	/* Stacked layout: bring the player back on screen after a pick (one read per click). */
	function reveal(g) {
		if (!g.screen) {
			return;
		}
		var box = g.screen.getBoundingClientRect();
		if (box.top < 0 || box.top > window.innerHeight * 0.6) {
			g.screen.scrollIntoView({ behavior: calm() ? 'auto' : 'smooth', block: 'start' });
		}
	}

	/* ---- Autoplay on view ---- */
	function startAuto(g) {
		if (g.started) {
			return;
		}
		g.started = true;
		if (g.touched || g.player || g.frame || calm() || saveData() || !ID_RE.test(item(g, g.active).id)) {
			return;
		}
		play(g, g.active, false, false, true);
	}

	function onView(g, entry) {
		g.inView = entry.isIntersecting;
		if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
			if (!g.started) {
				startAuto(g);
			} else if (g.autoPaused && g.player) {
				g.autoPaused = false;
				call(g.player, 'playVideo');
			}
		} else if (!entry.isIntersecting && g.player && g.playing && isMuted(g)) {
			// Muted background play stops while nobody can see it.
			g.autoPaused = true;
			call(g.player, 'pauseVideo');
		}
	}

	function init() {
		var roots = document.querySelectorAll('[data-ap-pv-gallery]');
		if (!roots.length) {
			return;
		}
		var near = hasIO && !calm() && !saveData() ? new IntersectionObserver(function (entries) {
			for (var i = 0; i < entries.length; i++) {
				if (entries[i].isIntersecting) {
					loadApi();
					near.disconnect();
					return;
				}
			}
		}, { rootMargin: '600px 0px' }) : null;
		var view = hasIO ? new IntersectionObserver(function (entries) {
			for (var i = 0; i < entries.length; i++) {
				var root = entries[i].target.closest('[data-ap-pv-gallery]');
				if (root && root.apPv) {
					onView(root.apPv, entries[i]);
				}
			}
		}, { threshold: [0, 0.5] }) : null;
		for (var r = 0; r < roots.length; r++) {
			var g = setup(roots[r]);
			if (!g.screen) {
				continue;
			}
			if (near) {
				near.observe(roots[r]);
			}
			if (view) {
				view.observe(g.screen);
			}
		}
	}

	/* ---- Delegated input ---- */
	document.addEventListener('click', function (event) {
		var target = event.target;
		if (!target || !target.closest) {
			return;
		}
		var root;
		var card = target.closest('[data-ap-pv-card]');
		if (card) {
			root = card.closest('[data-ap-pv-gallery]');
			if (root) {
				var g = setup(root);
				choose(g, g.cards.indexOf(card));
			}
			return;
		}
		var sound = target.closest('[data-ap-pv-sound]');
		if (sound) {
			root = sound.closest('[data-ap-pv-gallery]');
			if (root) {
				var gs = setup(root);
				gs.touched = true;
				unmute(gs);
			}
			return;
		}
		var link = target.closest('a[data-ap-pv]');
		if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button > 0) {
			return;
		}
		root = link.closest('[data-ap-pv-gallery]');
		if (!root || !ID_RE.test(link.getAttribute('data-ap-pv') || '')) {
			return;
		}
		event.preventDefault();
		userPlay(setup(root), true);
	});

	/* Arrow keys walk the deck in its visible order; Enter/Space are the button's own. */
	document.addEventListener('keydown', function (event) {
		var card = event.target && event.target.closest ? event.target.closest('[data-ap-pv-card]') : null;
		if (!card || event.altKey || event.ctrlKey || event.metaKey) {
			return;
		}
		var key = event.key;
		var forward = key === 'ArrowDown' || key === 'ArrowRight';
		var back = key === 'ArrowUp' || key === 'ArrowLeft';
		if (!forward && !back && key !== 'Home' && key !== 'End') {
			return;
		}
		var root = card.closest('[data-ap-pv-gallery]');
		if (!root) {
			return;
		}
		var g = setup(root);
		var lastSlot = count(g) - 2;
		var at = slotOf(g, g.cards.indexOf(card));
		var to = key === 'Home' ? 0 : key === 'End' ? lastSlot : forward ? Math.min(lastSlot, at + 1) : Math.max(0, at - 1);
		var next = cardAtSlot(g, to);
		event.preventDefault();
		if (next) {
			next.focus();
		}
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
