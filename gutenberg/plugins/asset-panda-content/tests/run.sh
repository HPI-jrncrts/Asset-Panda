#!/usr/bin/env bash
# Local integration test for asset-panda-content (Local app site, PHP 8.2, MySQL on 10005).
#
#   AP_TEST_FIXTURES=<dir with snippet6-loader.php + snippet8.php> bash tests/run.sh
#
# 1. copies the plugin + a test mu-plugin (loads read-only copies of dev snippets 6 and 8) into the site;
# 2. setup.php: snapshots the DB (options, max ids, CHECKSUM TABLE), activates the plugin, adds
#    staging-like DB templates (home/archive/single built from the ap26 blocks);
# 3. starts `php -S` and runs test.php (REST in-process + HTTP against php -S);
# 4. cleanup.php restores the DB and checks the checksums; the copies are removed.
set -u
HERE="$(cd "$(dirname "$0")" && pwd)"
PLUGIN="$(dirname "$HERE")"
SITE="${AP_TEST_SITE:-C:/Users/josep/Local Sites/asset-panda-local/app/public}"
PHP="${AP_TEST_PHP:-C:/Users/josep/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe}"
INI="${AP_TEST_INI:-C:/Users/josep/AppData/Roaming/Local/run/hAad2ktsP/conf/php/php.ini}"
OUT="${AP_TEST_OUT:-$HERE/.out}"
PORT="${AP_TEST_PORT:-8899}"
FIX="${AP_TEST_FIXTURES:?set AP_TEST_FIXTURES}"
mkdir -p "$OUT"
RUNPHP=("$PHP" -c "$INI" -d mysqli.default_port=10005 -d display_errors=stderr -d xdebug.mode=off)

if [ -e "$SITE/wp-content/plugins/asset-panda-content" ] || [ -e "$SITE/wp-content/mu-plugins/ap-content-test-env.php" ]; then
  echo "refusing: a previous copy is still installed in the site" >&2; exit 2
fi
# Snapshot first, before any test code is installed (SHORTINIT: nothing runs but the DB layer).
"${RUNPHP[@]}" "$HERE/setup.php" "$SITE/wp-load.php" "$OUT/state.json" snapshot || { echo "snapshot failed" >&2; exit 3; }
mkdir -p "$SITE/wp-content/plugins/asset-panda-content"
cp -r "$PLUGIN/asset-panda-content.php" "$PLUGIN/uninstall.php" "$PLUGIN/includes" "$PLUGIN/assets" "$PLUGIN/data" "$SITE/wp-content/plugins/asset-panda-content/"
sed "s#__FIXTURES__#$FIX#" "$HERE/env-mu-plugin.php" > "$SITE/wp-content/mu-plugins/ap-content-test-env.php"

cleanup() {
  [ -n "${SERVER_PID:-}" ] && { kill "$SERVER_PID" 2>/dev/null; wait "$SERVER_PID" 2>/dev/null; sleep 1; }
  "${RUNPHP[@]}" "$HERE/cleanup.php" "$SITE/wp-load.php" "$OUT/state.json"; CLEAN=$?
  rm -f "$SITE/wp-content/mu-plugins/ap-content-test-env.php"
  rm -r "$SITE/wp-content/plugins/asset-panda-content"
  echo "files removed; cleanup exit $CLEAN"
}

"${RUNPHP[@]}" "$HERE/setup.php" "$SITE/wp-load.php" "$OUT/state.json" activate || { echo "setup failed" >&2; cleanup; exit 3; }
"${RUNPHP[@]}" -S "127.0.0.1:$PORT" -t "$SITE" "$HERE/router.php" > "$OUT/server.log" 2>&1 &
SERVER_PID=$!
for i in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PORT/" -H "Host: asset-panda-local.local" && break; sleep 1; done
AP_TEST_BASE="http://127.0.0.1:$PORT" "${RUNPHP[@]}" "$HERE/test.php" "$SITE/wp-load.php" "$OUT/results.json"; RESULT=$?
# Optional visual proof (1.1.2): real imported content, Playwright at 1440 and 390.
#   AP_TEST_VISUAL=1 AP_TEST_BATCHES=<import/batches> AP_VIS_OLD_CSS=<1.1.0 ap-content.css> AP_CHROMIUM_PATH=<chrome.exe>
if [ -n "${AP_TEST_VISUAL:-}" ]; then
  "${RUNPHP[@]}" "$HERE/visual-setup.php" "$SITE/wp-load.php" "${AP_TEST_BATCHES:?}" "$OUT/visual-targets.json"     && AP_TEST_PORT="$PORT" node "$HERE/visual.mjs" "$OUT/visual-targets.json" "$OUT/visual" "${AP_VIS_OLD_CSS:?}"
  VIS=$?; [ "$VIS" -ne 0 ] && RESULT=$VIS
fi
cleanup
exit $RESULT
