#!/bin/sh
# R1MC Work and Financial Plan on Linux / macOS (same steps as start.bat).
#   ./start.sh          check the database, then run the web server
#   ./start.sh backup   save a copy of the database to backups/
# Settings: export them, or put them in start.local.sh (WFP_PORT, PHP_BIN, WFP_DB_*).
set -u
cd "$(dirname "$0")"
[ -f start.local.sh ] && . ./start.local.sh
WFP_PORT="${WFP_PORT:-86}"

PHP="${PHP_BIN:-$(command -v php || true)}"
[ -n "$PHP" ] && [ -x "$PHP" ] || { echo "[ERROR] PHP was not found. Install php 8.1+ with pdo_mysql, or set PHP_BIN."; exit 1; }
"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.1.0", "<") ? 1 : 0);' || { echo "[ERROR] $PHP is older than PHP 8.1."; exit 1; }
"$PHP" -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);' || { echo "[ERROR] $PHP has no pdo_mysql extension (php-mysql package)."; exit 1; }

if [ "${1:-}" = "backup" ]; then exec "$PHP" backend/tools/backup.php; fi

[ -f frontend/dist/index.html ] || { echo "[ERROR] frontend/dist is missing. Run: npm install && npm run build"; exit 1; }

if command -v ss >/dev/null 2>&1 && ss -ltnp 2>/dev/null | grep -q ":$WFP_PORT "; then
  echo "[ERROR] Port $WFP_PORT is already used by: $(ss -ltnp 2>/dev/null | grep ":$WFP_PORT " | sed -n 's/.*users:((\"\([^\"]*\)\".*/\1/p' | head -1)"
  echo "        Set another port: WFP_PORT=8086 ./start.sh"
  exit 1
fi

"$PHP" backend/tools/install.php
case $? in
  0) ;;
  2) echo "[ERROR] MySQL is not running. Start it (sudo systemctl start mariadb) and try again."; exit 1 ;;
  3) printf "  Replacing it deletes everything in that old database. Type YES to replace it: "
     read -r ANSWER
     [ "$ANSWER" = "YES" ] || exit 1
     "$PHP" backend/tools/install.php --replace || exit 1 ;;
  *) exit 1 ;;
esac

echo
echo "  R1MC Work and Financial Plan is running on http://localhost:$WFP_PORT/"
for ip in $(hostname -I 2>/dev/null); do echo "  Other PCs: http://$ip:$WFP_PORT/"; done
echo "  Press Ctrl+C to stop."
echo
exec "$PHP" -S "0.0.0.0:$WFP_PORT" -t frontend/dist server.php
