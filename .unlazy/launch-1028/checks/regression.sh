#!/bin/sh
# Every check that encodes one of Joseph's noted items (2026-10-28/29), run one at a time against dev.
# Usage: sh .unlazy/launch-1028/checks/regression.sh  → reports/launch-1028/regression-<time>.log, last line REGRESSION n/N
cd "$(dirname "$0")/../../.." || exit 1
export MSYS_NO_PATHCONV=1
C=.unlazy/launch-1028/checks
L=reports/launch-1028/regression-$(date +%H%M).log
pass=0; total=0; failed=""
run() {
  total=$((total + 1))
  out=$(node $C/$1 2>&1); tok="$2"
  if printf '%s' "$out" | grep -q "$tok"; then pass=$((pass + 1)); echo "PASS $1" >> $L
  else failed="$failed $1"; echo "FAIL $1" >> $L; printf '%s\n' "$out" | grep -E '^FAIL' | head -8 | sed 's/^/    /' >> $L; fi
  sleep 4
}
echo "regression $(date)" > $L
run "qa-batch1.mjs" "QA BATCH1 PASSED"
run "qa-batch1.mjs --reduced" "QA BATCH1 REDUCED PASSED"
run "qa-batch2.mjs" "QA BATCH2 PASSED"
run "hero-gap.mjs 390 --expect 16" "HERO GAP PASSED"
run "footer-seam.mjs" "FOOTER SEAM PASSED"
run "bg-survey.mjs --gate" "BG SURVEY PASSED"
run "tutorials.mjs" "TUTORIALS PASSED"
run "noreveal.mjs" "NOREVEAL PASSED"
run "deploy-1115.mjs" "DEPLOY 1115 PASSED"
run "deploy-1117.mjs" "DEPLOY 1117 PASSED"
run "deploy-1124.mjs" "DEPLOY 1124 PASSED"
run "blog-posts.mjs" "BLOG POSTS PASSED"
run "partners.mjs" "PARTNERS PASSED"
run "parity-fixes.mjs" "PARITY FIXES PASSED"
run "round-1029b.mjs" "ROUND 1029B PASSED"
run "round-1029c.mjs" "ROUND 1029C PASSED"
run "figma-page.mjs --path /compare/ --markup .unlazy/launch-1028/pages/compare-page.html" "FIGMA PAGE CHECK PASSED"
echo "REGRESSION $pass/$total${failed:+ — failed:$failed}" >> $L
tail -1 $L
