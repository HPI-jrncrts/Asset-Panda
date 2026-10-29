#!/usr/bin/env bash
# Local integration + browser test for ap-template-library (Local app site, PHP 8.2, MySQL on 10005).
#
#   AP_TEST_OUT=<dir> AP_CHROMIUM_PATH=<chrome.exe> bash tests/run.sh          full run, then cleanup
#   AP_TEST_KEEP=1 …  bash tests/run.sh                                          set up + test, leave the site up
#   AP_TEST_TEARDOWN=1 AP_TEST_OUT=<dir> bash tests/run.sh                       cleanup only (after KEEP)
#
# 1. snapshot.php records the DB; the uploads folder listing (and the kit's CSS cache) is recorded too;
# 2. copies into the site: this plugin, the UI kit from dist/asset-panda-ui-kit (unchanged copy),
#    asset-panda-content (for the resource post type), a test mu-plugin (no cron, no outbound HTTP);
# 3. setup.php activates them, creates the reference fixtures, test users and auth cookies;
# 4. php -S serves the site; test.php (PHP + HTTP), e2e.mjs (Playwright), verify-e2e.php (DB + uninstall);
# 5. cleanup.php restores the DB exactly; every copied or uploaded file is removed.
set -u
HERE="$(cd "$(dirname "$0")" && pwd)"
PLUGIN="$(dirname "$HERE")"
REPO="${AP_TEST_REPO:-C:/Sample Code}"
SITE="${AP_TEST_SITE:-C:/Users/josep/Local Sites/asset-panda-local/app/public}"
PHP="${AP_TEST_PHP:-C:/Users/josep/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe}"
INI="${AP_TEST_INI:-C:/Users/josep/AppData/Roaming/Local/run/hAad2ktsP/conf/php/php.ini}"
OUT="${AP_TEST_OUT:?set AP_TEST_OUT}"
PORT="${AP_TEST_PORT:-8898}"
KIT="$REPO/dist/asset-panda-ui-kit"
CONTENT="$REPO/gutenberg/plugins/asset-panda-content"
mkdir -p "$OUT"
RUNPHP=("$PHP" -c "$INI" -d mysqli.default_port=10005 -d display_errors=stderr -d xdebug.mode=off -d memory_limit=512M)
P="$SITE/wp-content/plugins"
MU="$SITE/wp-content/mu-plugins/ap-tl-test-env.php"
FIX="$SITE/wp-content/uploads/ap-tl-fixtures"
CACHE="$SITE/wp-content/uploads/ap-ui-cache"
RESULT=0

stop_server() {
  # The server is a native php.exe: find it by its listening port.
  for pid in $(netstat -ano 2>/dev/null | grep "127.0.0.1:$PORT " | grep LISTENING | awk '{print $NF}' | sort -u); do
    taskkill //F //PID "$pid" > /dev/null 2>&1
  done
  sleep 1
}

cleanup() {
  stop_server
  "${RUNPHP[@]}" "$HERE/cleanup.php" "$SITE/wp-load.php" "$OUT/state.json"; CLEAN=$?
  rm -f "$MU"
  rm -r "$P/ap-template-library" "$P/asset-panda-ui-kit" "$P/asset-panda-content" "$FIX" 2>/dev/null
  find "$SITE/wp-content/uploads" -type f | sort > "$OUT/uploads-after.txt"
  comm -13 "$OUT/uploads-before.txt" "$OUT/uploads-after.txt" > "$OUT/uploads-new.txt"
  while IFS= read -r f; do [ -n "$f" ] && rm -f "$f"; done < "$OUT/uploads-new.txt"
  if [ -d "$OUT/ap-ui-cache-backup" ]; then cp -rpn "$OUT/ap-ui-cache-backup/." "$CACHE/"; else rmdir "$CACHE" 2>/dev/null; fi
  find "$SITE/wp-content/uploads" -type d -empty | sort | comm -23 - "$OUT/uploads-dirs-before.txt" | while IFS= read -r d; do rmdir "$d" 2>/dev/null; done
  find "$SITE/wp-content/uploads" -type f | sort > "$OUT/uploads-final.txt"
  find "$SITE/wp-content/uploads" -type d | sort > "$OUT/uploads-dirs-final.txt"
  cmp -s "$OUT/uploads-dirs-before.txt" "$OUT/uploads-dirs-final.txt" || { echo "upload folders: DIFFER" >&2; CLEAN=1; }
  if cmp -s "$OUT/uploads-before.txt" "$OUT/uploads-final.txt"; then echo "uploads: identical to before"; else echo "uploads: DIFFER" >&2; CLEAN=1; fi
  echo "files removed; cleanup exit $CLEAN"
  [ "$CLEAN" -ne 0 ] && RESULT=1
}

if [ -n "${AP_TEST_TEARDOWN:-}" ]; then
  cleanup
  echo "RESULT $RESULT"
  exit $RESULT
fi

for d in "$P/ap-template-library" "$P/asset-panda-ui-kit" "$P/asset-panda-content" "$MU" "$FIX"; do
  if [ -e "$d" ]; then echo "refusing: $d already exists in the site" >&2; exit 2; fi
done

rm -f "$OUT/blocked-http.log"
"${RUNPHP[@]}" "$HERE/snapshot.php" "$SITE/wp-load.php" "$OUT/state.json" || { echo "snapshot failed" >&2; exit 3; }
find "$SITE/wp-content/uploads" -type f | sort > "$OUT/uploads-before.txt"
find "$SITE/wp-content/uploads" -type d | sort > "$OUT/uploads-dirs-before.txt"
# The kit keeps a minified-CSS cache in uploads/ap-ui-cache and prunes older copies when it
# renders; keep a copy so the folder is put back exactly as it was.
rm -rf "$OUT/ap-ui-cache-backup"
[ -d "$CACHE" ] && cp -rp "$CACHE" "$OUT/ap-ui-cache-backup"

mkdir -p "$P/ap-template-library" "$P/asset-panda-content"
cp -r "$PLUGIN/ap-template-library.php" "$PLUGIN/uninstall.php" "$PLUGIN/includes" "$PLUGIN/assets" "$P/ap-template-library/"
cp -r "$KIT" "$P/asset-panda-ui-kit"
cp -r "$CONTENT/asset-panda-content.php" "$CONTENT/uninstall.php" "$CONTENT/includes" "$CONTENT/assets" "$CONTENT/data" "$P/asset-panda-content/"
sed "s#__OUT__#$OUT#" "$HERE/env-mu-plugin.php" > "$MU"
node "$HERE/make-fixtures.mjs" "$FIX" "$OUT" || echo "fixtures failed" >&2

"${RUNPHP[@]}" "$HERE/setup.php" "$SITE/wp-load.php" "$OUT" activate && "${RUNPHP[@]}" "$HERE/setup.php" "$SITE/wp-load.php" "$OUT" fixtures || { echo "setup failed" >&2; RESULT=3; cleanup; exit $RESULT; }

"${RUNPHP[@]}" -S "127.0.0.1:$PORT" -t "$SITE" "$HERE/router.php" > "$OUT/server.log" 2>&1 &
for i in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PORT/" -H "Host: asset-panda-local.local" && break; sleep 1; done

AP_TEST_BASE="http://127.0.0.1:$PORT" "${RUNPHP[@]}" "$HERE/test.php" "$SITE/wp-load.php" "$OUT" || RESULT=1
if [ -z "${AP_TEST_SKIP_E2E:-}" ]; then
  AP_TEST_PORT="$PORT" node "$HERE/e2e.mjs" "$OUT" || RESULT=1
  if [ -z "${AP_TEST_KEEP:-}" ]; then
    "${RUNPHP[@]}" "$HERE/verify-e2e.php" "$SITE/wp-load.php" "$OUT" || RESULT=1
  fi
fi
if [ -n "${AP_TEST_KEEP:-}" ]; then
  echo "KEPT: site is up on port $PORT; run with AP_TEST_TEARDOWN=1 to clean up"
  exit $RESULT
fi
cleanup
echo "RESULT $RESULT"
exit $RESULT
