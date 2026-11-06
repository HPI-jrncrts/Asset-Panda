/*
 * demo-stories — the customer stories slider (live page/cases.js): one slide at a time, dots, arrows, progress
 * line, auto-advance every data-ap-autoplay ms (3 s default) with a directional swipe; pauses on mouse hover,
 * keyboard focus, a finger on it (resuming after), off screen and in a hidden tab. Keys off [data-ap-cases];
 * idempotent.
 */
(function () {
  'use strict';

  /* ---------- Testimonial sliders (live page/cases.js) ----------
     One slide shows at a time; dots, arrows and the progress line pick it. Images of slides not
     yet shown wait in data-src and load when their slide or a neighbour comes up. Sliders advance
     on their own every data-ap-autoplay ms (CASES_AUTO_MS when absent, 0 = off); a mouse over a
     slider, keyboard focus in it or a finger on it pauses it (the count restarts when the finger
     lifts), it waits while off screen or in a hidden tab, a dot or arrow restarts the count, a
     horizontal finger swipe moves one slide, and visitors who ask for reduced motion get no
     auto-advance. Keyed off data-ap-cases; data-ready makes a second run (or a second component's
     copy of this code) a no-op. */
  const CASES_AUTO_MS = 3000;
  let casesWatcher = null;

  function casesLoadImages(slide) {
    if (!slide) return;
    slide.querySelectorAll('img[data-src]').forEach((img) => {
      img.setAttribute('src', img.getAttribute('data-src'));
      img.removeAttribute('data-src');
    });
  }

  function casesReducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function casesKeyboardFocus(el) {
    try { return el.matches(':focus-visible'); } catch (e) { return false; }
  }

  function initCasesSlider(root) {
    if (root.getAttribute('data-ready') === '1') return;
    root.setAttribute('data-ready', '1');
    const slides = root.querySelectorAll('[data-case]');
    const dots = root.querySelectorAll('[data-case-dot]');
    const bar = root.querySelector('.ap-d26-case-bar');
    const count = slides.length;
    if (!count) return;
    const live = slides[0].parentNode;
    let current = 0;
    let timer = 0;
    let hovered = false;
    let focused = false;
    let touching = false;
    let visible = !casesWatcher;
    const attr = root.getAttribute('data-ap-autoplay');
    let ms = attr === null || attr === '' ? CASES_AUTO_MS : parseInt(attr, 10);
    if (!(ms >= 0)) ms = CASES_AUTO_MS;
    if (ms > 0 && ms < 1500) ms = 1500;
    const calm = casesReducedMotion();
    const autoplay = count > 1 && ms > 0 && !calm;

    function schedule() {
      clearTimeout(timer);
      const running = autoplay && visible && !hovered && !focused && !touching && !document.hidden;
      /* Screen readers announce a slide change only when a person made it. */
      live.setAttribute('aria-live', running ? 'off' : 'polite');
      if (running) timer = setTimeout(() => go(current + 1, 1), ms);
    }

    /* Directional swipe: the incoming slide starts a few px off on the side it comes from and the
       outgoing one leaves to the other side; the slides' own translate transitions do the motion. */
    function swipe(from, to, dir) {
      if (calm || !dir || from === to) return;
      let shift = parseFloat(getComputedStyle(root).getPropertyValue('--d26-swipe')) || 40;
      /* Never travel further than the gutter beside the slides, so a moving slide cannot widen the page. */
      const gutter = to.parentNode.getBoundingClientRect().left;
      if (gutter >= 0) shift = Math.min(shift, Math.max(8, gutter - 4));
      to.style.transition = 'none';
      to.style.translate = (dir * shift) + 'px 0';
      getComputedStyle(to).translate; /* commit the start position (style only, no layout) */
      to.style.transition = '';
      from.style.translate = (-dir * shift) + 'px 0';
      clearTimeout(from.apLeave);
      from.apLeave = setTimeout(() => {
        if (from.classList.contains('is-active')) return;
        from.style.transition = 'none';
        from.style.translate = '';
        getComputedStyle(from).translate;
        from.style.transition = '';
      }, 700);
    }

    function go(index, dir) {
      const target = (index + count) % count;
      const from = slides[current];
      const to = slides[target];
      if (to !== from) swipe(from, to, dir);
      current = target;
      slides.forEach((slide, i) => slide.classList.toggle('is-active', i === current));
      to.style.translate = '';
      clearTimeout(to.apLeave);
      dots.forEach((dot, i) => {
        dot.classList.toggle('is-active', i === current);
        if (i === current) dot.setAttribute('aria-current', 'true');
        else dot.removeAttribute('aria-current');
      });
      if (bar) bar.style.transform = 'translateX(' + current * 100 + '%)';
      casesLoadImages(slides[current]);
      casesLoadImages(slides[(current + 1) % count]);
      casesLoadImages(slides[(current - 1 + count) % count]);
      schedule();
    }

    dots.forEach((dot, i) => dot.addEventListener('click', () => { if (i !== current) go(i, i > current ? 1 : -1); }));
    const prev = root.querySelector('[data-case-prev]');
    const next = root.querySelector('[data-case-next]');
    if (prev) prev.addEventListener('click', () => go(current - 1, -1));
    if (next) next.addEventListener('click', () => go(current + 1, 1));
    let sx = 0;
    let sy = 0;
    root.addEventListener('touchstart', (event) => {
      sx = event.touches[0].clientX;
      sy = event.touches[0].clientY;
      touching = true;
      schedule();
    }, { passive: true });
    const release = (event) => {
      touching = false;
      const t = event.changedTouches && event.changedTouches[0];
      if (t && count > 1 && !event.target.closest('[data-case-dot], [data-case-prev], [data-case-next]')) {
        const dx = t.clientX - sx;
        const dy = t.clientY - sy;
        if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy) * 1.5) { go(current + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1); return; }
      }
      schedule();
    };
    root.addEventListener('touchend', release, { passive: true });
    root.addEventListener('touchcancel', release, { passive: true });
    if (casesWatcher && autoplay) {
      root.apCasesVisible = (v) => { visible = v; schedule(); };
      casesWatcher.observe(root);
    }
    root.addEventListener('pointerenter', (event) => { hovered = event.pointerType === 'mouse'; schedule(); });
    root.addEventListener('pointerleave', () => { hovered = false; schedule(); });
    root.addEventListener('focusin', (event) => { focused = casesKeyboardFocus(event.target); schedule(); });
    root.addEventListener('focusout', (event) => {
      if (event.relatedTarget && root.contains(event.relatedTarget)) return;
      focused = false;
      schedule();
    });
    document.addEventListener('visibilitychange', schedule);
    go(0);
  }

  function initCasesIn(scope) {
    document.querySelectorAll(scope + ' [data-ap-cases]').forEach(initCasesSlider);
  }

  function boot() {
    if (!casesReducedMotion() && typeof IntersectionObserver === 'function') {
      casesWatcher = new IntersectionObserver((entries) => {
        entries.forEach((e) => { if (e.target.apCasesVisible) e.target.apCasesVisible(e.isIntersecting); });
      }, { threshold: 0.25 });
    }
    initCasesIn('.ap-demo-stories');
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
