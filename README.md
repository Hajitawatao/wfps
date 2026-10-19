# R1MC Work and Financial Plan Encoding System

Replaces the DOH WFP Excel file. Each office (area) of Region I Medical Center encodes its own rows of the
**Encoding Sheet** (columns A–S). The Planning Office reviews them and prints **WFP Form 1** exactly like the
**Print (GAA)** and **Print (Income)** sheets.

## Run it (Windows, XAMPP)
1. XAMPP installed at `C:\xampp` (PHP 8.1+ and MySQL/MariaDB). Apache is not needed.
2. Unzip this folder anywhere, e.g. the Desktop.
3. Double-click **start.bat**. It finds PHP, starts XAMPP's MySQL if it is off, creates or upgrades the
   database `r1mc_wfp`, then serves the system on port 86 and opens the browser.
4. Other PCs open `http://<this PC's IP>:86/`. If they cannot connect, run **allow-lan.bat** once as administrator.
5. Keep the start.bat window open. Closing it stops the system.

First sign-in: `admin / admin123` and `planning / wfp2026` (each must set a new password).
Different port, PHP or MySQL password on this PC: copy `start.local.bat.example` to `start.local.bat` and edit it.
Backup: **backup.bat** writes `backups\wfp_YYYYMMDD_HHMMSS.sql` (restore with phpMyAdmin > Import).

## Permanent server (recommended): Apache
See `deploy/apache-wfp.conf`: a VirtualHost on port 86 with `frontend/dist` as the site and `/api` mapped to
`backend/api`. Run `php backend/tools/install.php` once to create the database.

## Layout
```
start.bat / start.sh      launcher: php -S 0.0.0.0:86 -t frontend/dist server.php
server.php                router for php -S: /api/<name>.php -> backend/api, other paths -> index.html
backend/api/*.php         one file per endpoint; each requires cors.php and calls guard([...])
backend/lib/              db.php (PDO), auth.php (sessions, roles, 5-try lockout), wfp.php (Encoding Sheet rules)
backend/tools/            install.php (create/upgrade database), backup.php
backend/config.php        settings from environment variables WFP_DB_HOST/PORT/NAME/USER/PASS, APP_TZ
database/                 schema.sql, seed.sql (dropdown lists from the Validation sheet, General Info names)
frontend/src/             (icons: Lucide, ISC license, inlined in components/Icon.vue)
frontend/src/             Vue 3 + Vue Router (history mode) + Tailwind CSS v4, built by Vite to frontend/dist
  composables/            useApi, useAuth, useReference, useLayout, useTheme, useDialog, useToast
  lib/                    format.js, gate.js (= can_edit in wfp.php), options.js (= lists in wfp.php)
tools/                    dev.mjs (npm run dev), build_seed.py + make_seed.sh (rebuild seed.sql from the Excel file)
```

## Develop
```
npm install          # also installs frontend/
npm run dev          # PHP on 127.0.0.1:8001 + Vite on http://localhost:5173 (/api proxied)
npm run build        # frontend/dist, which start.bat serves
```
