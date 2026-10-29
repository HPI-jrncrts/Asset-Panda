/*
 * demo-stories — the customer stories slider (live page/cases.js): one slide at a time, dots, arrows, progress
 * line, auto-advance every 3 s with hover/keyboard pause. Keys off [data-ap-cases]; idempotent.
 */
(function () {
  'use strict';

  /* ---------- Testimonial sliders (live page/cases.js) ----------
     One slide shows at a time; dots, arrows and the progress line pick it. Images of slides not
     yet shown wait in data-src and load when their slide or a neighbour comes up. Sliders advance
     on their own every CASES_AUTO_MS; a mouse over a slider or keyboard focus in it pauses it (taps
     do not, so phones keep advancing), a dot or arrow restarts the count, and visitors who ask for
     reduced motion get no auto-advance. Keyed off data-ap-cases; data-ready makes a second run (or
     a second component's copy of this code) a no-op. */
  const CASES_AUTO_MS = 3000;

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
    const autoplay = count > 1 && !casesReducedMotion();

    function schedule() {
      clearTimeout(timer);
      const running = autoplay && !hovered && !focused && !document.hidden;
      /* Screen readers announce a slide change only when a person made it. */
      live.setAttribute('aria-live', running ? 'off' : 'polite');
      if (running) timer = setTimeout(() => go(current + 1), CASES_AUTO_MS);
    }

    function go(index) {
      current = (index + count) % count;
      slides.forEach((slide, i) => slide.classList.toggle('is-active', i === current));
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

    dots.forEach((dot, i) => dot.addEventListener('click', () => go(i)));
    const prev = root.querySelector('[data-case-prev]');
    const next = root.querySelector('[data-case-next]');
    if (prev) prev.addEventListener('click', () => go(current - 1));
    if (next) next.addEventListener('click', () => go(current + 1));
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

  function boot() { initCasesIn('.ap-demo-stories'); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
