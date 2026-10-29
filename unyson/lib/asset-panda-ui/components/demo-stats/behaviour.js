/*
 * demo-stats — the stats count up from 0 the first time the band scrolls into view (live page/stats.js).
 * Keys off [data-ap-stats] / [data-ap-count]; idempotent.
 */
(function () {
  'use strict';

  /* ---------- Stats count-up (live page/stats.js) ----------
     Each [data-ap-count] number in a [data-ap-stats] list counts up from 0 to the value in its
     markup the first time the list scrolls into view. The markup keeps the real value, so without
     JS, without IntersectionObserver or with reduced motion the final number simply shows; a hidden
     copy of the final value (.ap-d26-stat-ghost) holds the width while it counts. */
  const STATS_DURATION_MS = 1600;

  /* "10,000+" -> { prefix: '', value: 10000, suffix: '+', grouped: true } */
  function parseStat(text) {
    const match = /^(\D*)(\d[\d,]*)(\D*)$/.exec(text.trim());
    if (!match) return null;
    return { prefix: match[1], value: parseInt(match[2].replace(/,/g, ''), 10), suffix: match[3], grouped: match[2].includes(',') };
  }

  function formatStat(stat, n) {
    const digits = stat.grouped ? String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : String(n);
    return stat.prefix + digits + stat.suffix;
  }

  const statsEase = (t) => 1 - Math.pow(1 - t, 3);

  function statsCountUp(items) {
    const start = performance.now();
    function frame(now) {
      const t = Math.min(1, Math.max(0, (now - start) / STATS_DURATION_MS));
      items.forEach(({ el, stat }) => { el.textContent = formatStat(stat, Math.round(stat.value * statsEase(t))); });
      if (t < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  function initStatsList(list) {
    if (list.getAttribute('data-count-ready') === '1') return;
    list.setAttribute('data-count-ready', '1');
    const items = [];
    list.querySelectorAll('[data-ap-count]').forEach((el) => {
      const stat = parseStat(el.textContent);
      if (stat) items.push({ el, stat });
    });
    if (!items.length) return;
    items.forEach(({ el, stat }) => { el.textContent = formatStat(stat, 0); });
    const observer = new IntersectionObserver((entries) => {
      if (!entries.some((entry) => entry.isIntersecting)) return;
      observer.disconnect();
      statsCountUp(items);
    }, { threshold: 0.4 });
    observer.observe(list);
  }

  function initStatsIn(scope) {
    if (!('IntersectionObserver' in window)) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    document.querySelectorAll(scope + ' [data-ap-stats]').forEach(initStatsList);
  }

  initStatsIn('.ap-demo-stats');
})();
