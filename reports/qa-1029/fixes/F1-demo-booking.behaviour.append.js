(() => {
  /* QA 2026-10-29: the one-screen hero squeezes the testimonial card on laptop screens. Each quote is
     clamped to the lines that fit its slide, so it ends on "…" and the author row always shows; a
     slide with no room for a single line shows its title and author only. Runs on load, on resize, when the
     fonts arrive and when the hero column changes size; one read pass, then one write pass. */
  function fitQuotes() {
    const sets = document.querySelectorAll('.ap-demo-booking .ap-d26-v1-side .ap-d26-qslides');
    if (!sets.length) return;
    const wide = window.matchMedia('(min-width:1024px)').matches;
    sets.forEach((set) => {
      const slides = [...set.querySelectorAll('.ap-d26-qslide')];
      slides.forEach((s) => { const t = s.querySelector('.ap-d26-qtext'); if (t) { t.style.removeProperty('-webkit-line-clamp'); t.style.removeProperty('display'); } });
      if (!wide) return;
      const plan = slides.map((s) => {
        const t = s.querySelector('.ap-d26-qtext');
        if (!t) return null;
        const gap = parseFloat(getComputedStyle(s).rowGap) || 0;
        let fixed = 0;
        for (const k of s.children) if (k !== t) fixed += k.getBoundingClientRect().height;
        fixed += gap * (s.children.length - 1);
        const cs = getComputedStyle(t), lh = parseFloat(cs.lineHeight) || 24, max = parseInt(cs.webkitLineClamp, 10) || 99;
        return [t, Math.min(max, Math.floor((s.clientHeight - fixed - 2) / lh))];
      });
      const apply = (t, n) => { if (n < 1) t.style.display = 'none'; else t.style.setProperty('-webkit-line-clamp', String(n)); };
      plan.forEach((x) => { if (x) apply(x[0], x[1]); });
      /* Check pass: a quote the card still squeezes below its clamped height loses a line. */
      plan.forEach((x) => {
        if (!x) return;
        const lh = parseFloat(getComputedStyle(x[0]).lineHeight) || 24;
        for (let n = x[1]; n > 0 && x[0].clientHeight + 1 < n * lh; ) apply(x[0], --n);
      });
    });
  }
  let queued = false;
  const later = () => { if (queued) return; queued = true; requestAnimationFrame(() => { queued = false; fitQuotes(); }); };
  if (!document.querySelector('.ap-demo-booking .ap-d26-qslides') || document.documentElement.hasAttribute('data-ap-qfit')) return;
  document.documentElement.setAttribute('data-ap-qfit', '1');
  later();
  window.addEventListener('resize', later);
  window.addEventListener('load', later);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(later);
  /* The hero's height is set by the fold measure (after load, on resize); the side column's size
     follows the hero, never the card's content, so watching it cannot loop. */
  if ('ResizeObserver' in window) {
    const ro = new ResizeObserver(later);
    document.querySelectorAll('.ap-demo-booking .ap-d26-v1-side').forEach((el) => ro.observe(el));
  }
})();
