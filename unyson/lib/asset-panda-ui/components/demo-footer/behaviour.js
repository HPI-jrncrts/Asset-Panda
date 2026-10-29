/*
 * demo-footer — moves the compact footer into the global footer's inner wrapper (live page/footer.js), so it
 * sits on that footer's decorative background; style.css hides the CTA band and link columns and,
 * once moved (html.ap-demo-footer-moved), trims that footer's padding. Without the global footer it
 * stays where it is. Keys off [data-ap-demo-footer]; idempotent.
 */
(function () {
  'use strict';

  function moveFooter() {
    const own = document.querySelector('[data-ap-demo-footer]');
    const inner = document.querySelector('.ap-final-footer-inner');
    if (!own || !inner || inner.contains(own)) return;
    inner.appendChild(own);
    document.documentElement.classList.add('ap-demo-footer-moved');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', moveFooter);
  else moveFooter();
})();
