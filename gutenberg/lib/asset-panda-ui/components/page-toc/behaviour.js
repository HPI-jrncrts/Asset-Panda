/* page-toc — pill/panel toggle, the section in view, reading progress.
 * Delegated and idempotent: one init for every [data-ap-page-toc] on the page.
 * Layout is read in a rAF-throttled scroll handler and only text/width/class
 * are written, never positions. */
(function () {
	'use strict';
	if (typeof window === 'undefined' || typeof document === 'undefined') return;
	var html = document.documentElement;
	if (html.classList.contains('ap-page-toc-js')) return;
	html.classList.add('ap-page-toc-js');

	var roots = Array.prototype.slice.call(document.querySelectorAll('[data-ap-page-toc]'));
	if (!roots.length) return;

	roots.forEach(function (root) {
		var chip = root.querySelector('[data-ap-toc-toggle]');
		var close = root.querySelector('[data-ap-toc-close]');
		var links = Array.prototype.slice.call(root.querySelectorAll('[data-ap-toc-link]'));
		var pct = root.querySelector('[data-ap-toc-pct]');
		var fill = root.querySelector('[data-ap-toc-fill]');
		var targets = links.map(function (a) {
			return { link: a, el: document.getElementById(a.getAttribute('data-ap-toc-link')) };
		}).filter(function (t) { return !!t.el; });

		function setOpen(open) {
			root.classList.toggle('is-open', open);
			if (chip) chip.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open && close) close.focus({ preventScroll: true });
			if (!open && chip) chip.focus({ preventScroll: true });
		}
		if (chip) chip.addEventListener('click', function () { setOpen(true); });
		if (close) close.addEventListener('click', function () { setOpen(false); });
		root.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && root.classList.contains('is-open')) setOpen(false);
		});

		var active = null;
		function setActive(t) {
			if (t === active) return;
			if (active) {
				active.link.parentNode.classList.remove('is-active');
				active.link.removeAttribute('aria-current');
			}
			active = t;
			if (active) {
				active.link.parentNode.classList.add('is-active');
				active.link.setAttribute('aria-current', 'true');
			}
		}

		var ticking = false;
		function update() {
			ticking = false;
			var line = window.innerHeight * 0.35;
			var current = targets.length ? targets[0] : null;
			for (var i = 0; i < targets.length; i++) {
				if (targets[i].el.getBoundingClientRect().top <= line) current = targets[i];
			}
			var doc = html.scrollHeight - window.innerHeight;
			var p = doc > 0 ? Math.min(100, Math.max(0, Math.round((window.scrollY || html.scrollTop) / doc * 100))) : 0;
			if (pct) pct.textContent = p + '%';
			if (fill) fill.style.width = p + '%';
			setActive(current);
		}
		function onScroll() {
			if (ticking) return;
			ticking = true;
			window.requestAnimationFrame(update);
		}
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll);
		update();
	});
})();
