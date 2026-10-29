#!/usr/bin/env bash
# Local integration test for asset-panda-site (Local app site, PHP 8.2, MySQL on 10005 running).
#
#   AP_TEST_LIVE=<folder with live curl captures: b_.html, h_*.txt, robots.txt.h> bash tests/run.sh
#
# 1. snapshot the DB (SHORTINIT, before anything is installed);
# 2. copy the plugin + a test mu-plugin into the site, activate, add test pages;
# 3. php -S on 127.0.0.1:$PORT; test.php sends requests with Host: www.assetpanda.com,
#    the staging host, localhost, … and calls the REST routes in-process;
# 4. delete the imported media files, restore the DB, compare checksums, remove the copies.
set -u
HERE="$(cd "$(dirname "$0")" && (pwd -W 2>/dev/null || pwd))"
PLUGIN="$(dirname "$HERE")"
REPO="$(cd "$PLUGIN/../../.." && (pwd -W 2>/dev/null || pwd))"
SITE="${AP_TEST_SITE:-C:/Users/josep/Local Sites/asset-panda-local/app/public}"
PHP="${AP_TEST_PHP:-C:/Users/josep/AppData/Roaming/Local/lightning-services/php-8.2.29+0/bin/win64/php.exe}"
INI="${AP_TEST_INI:-C:/Users/josep/AppData/Roaming/Local/run/hAad2ktsP/conf/php/php.ini}"
OUT="${AP_TEST_OUT:-$HERE/.out}"
PORT="${AP_TEST_PORT:-8898}"
LIVE="${AP_TEST_LIVE:?set AP_TEST_LIVE}"
mkdir -p "$OUT"
RUNPHP=("$PHP" -c "$INI" -d mysqli.default_port=10005 -d display_errors=stderr -d xdebug.mode=off)

if [ -e "$SITE/wp-content/plugins/asset-panda-site" ] || [ -e "$SITE/wp-content/mu-plugins/ap-site-test-env.php" ]; then
  echo "refusing: a previous copy is still installed in the site" >&2; exit 2
fi
"${RUNPHP[@]}" "$HERE/setup.php" "$SITE/wp-load.php" "$OUT/state.json" snapshot || { echo "snapshot failed" >&2; exit 3; }
mkdir -p "$SITE/wp-content/plugins/asset-panda-site"
cp -r "$PLUGIN/asset-panda-site.php" "$PLUGIN/uninstall.php" "$PLUGIN/readme.txt" "$PLUGIN/includes" "$PLUGIN/assets" "$SITE/wp-content/plugins/asset-panda-site/"
sed -e "s#__REPO__#$REPO#" -e "s#__TESTS__#$HERE#" "$HERE/env-mu-plugin.php" > "$SITE/wp-content/mu-plugins/ap-site-test-env.php"

cleanup() {
  [ -n "${SERVER_PID:-}" ] && { kill "$SERVER_PID" 2>/dev/null; wait "$SERVER_PID" 2>/dev/null; sleep 1; }
  "${RUNPHP[@]}" "$HERE/cleanup.php" "$SITE/wp-load.php" "$OUT/state.json" files
  rm -f "$SITE/wp-content/mu-plugins/ap-site-test-env.php"
  rm -r "$SITE/wp-content/plugins/asset-panda-site"
  "${RUNPHP[@]}" "$HERE/cleanup.php" "$SITE/wp-load.php" "$OUT/state.json" restore; CLEAN=$?
  echo "files removed; cleanup exit $CLEAN"
}

"${RUNPHP[@]}" "$HERE/setup.php" "$SITE/wp-load.php" "$OUT/state.json" activate || { echo "setup failed" >&2; cleanup; exit 3; }
"${RUNPHP[@]}" -S "127.0.0.1:$PORT" -t "$SITE" "$HERE/router.php" > "$OUT/server.log" 2>&1 &
SERVER_PID=$!
for i in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PORT/" -H "Host: asset-panda-local.local" && break; sleep 1; done
AP_TEST_BASE="http://127.0.0.1:$PORT" AP_TEST_LIVE="$LIVE" AP_TEST_STATE="$OUT" AP_TEST_TESTS="$HERE" \
  "${RUNPHP[@]}" "$HERE/test.php" "$SITE/wp-load.php" "$OUT/results.json"; RESULT=$?
grep -i -E "fatal|parse error|warning|deprecated|notice" "$OUT/server.log" | grep -v -i "imagick" | head -20
cleanup
exit $RESULT
