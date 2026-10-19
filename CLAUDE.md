# R1MC WFP Encoding System: notes for Claude

LAN web app that replaces the DOH "WFP Online Encoding Platform" Excel/Google Sheet for Region I Medical Center (Arellano-Bani, Dagupan City, Pangasinan). User: MIS (Miguel Karlo Fetiza). Reply in Taglish.

## User rules (always)
- Follow the Excel Encoding Sheet (columns A–S) and Print (GAA) / Print (Income) strictly. No extra features, no "AI slop".
- When asked for style/UI only, never change functions.
- Do not import the 2026 Excel data. No audit trail.
- Challenge first, tag claims [Certain]/[Likely]/[Guessing], uncomfortable truth first, no "Great question"/"Absolutely".
- Every change: commit as `meliodas2327 <meliodas2327@gmail.com>` and push to `main` of github.com/Hajitawatao/wfps, copy changed files to the PC folder `C:\Users\MIS-27\Desktop\Deployments\r1mc-wfp` (device_commit_files, never overwrite `start.local.bat`), then send one full-project zip (`git archive --prefix=r1mc-wfp/ HEAD`). Claude cannot delete files on the PC: list stale files for the user instead.

## Stack
- Vue 3 `<script setup>` + Vite 7 + Tailwind v4 + Vue Router (history). Built output `frontend/dist` is committed (server PC has no Node).
- Plain PHP 8.1+, one file per endpoint in `backend/api` (`guard([...])`, helpers in `backend/lib`). MySQL/MariaDB via XAMPP.
- `start.bat` runs `php -S` on the port in `start.local.bat` (use 8000+, Chrome blocks 87) and `backend/tools/install.php`, which upgrades the DB in place. Schema version 9 (`backend/lib/db.php` SCHEMA_VERSION; add a migration block in install.php for every schema change).
- Amounts in '000 PhP. Colors: graphite + teal tokens in `frontend/src/style.css` (light + dark); menu always dark (`--menu*`).

## How the WFP works (Excel and system)
- Dropdown lists (DOH: objectives, indicators, classifications, line items, fund sources) → areas encode activities (Encoding Sheet) → Print GAA / Print Income are generated (GAA = non-Income sources).
- Remaining MOOE/CO = General Info total − encoded cost, whole hospital (no per-area budget). Summary filter: all / submitted+approved / approved.
- Workflow: draft → submitted (by the area; counts as the area head's approval) → approved / returned by Planning.
- Signatories: one set for the whole hospital in General Info (Prepared by Planning Officer, Recommending: MCC, Approved: DOH USec). A one-area print shows an "AREA COPY" band. Per-area signatories and area heads were removed in v8.
- "Others, please insert note/remarks" in B or C: a text box appears and the typed text (objective_other / indicator_other) is shown on the Encoding Sheet and prints instead.
- Roles: admin (all), planning (all except Users and offices), encoder (own area only), viewer (read only). Access requests are granted by admin. Planning/admin get a notification bell for new submissions.

## Open questions for Planning (do not decide alone)
Income deducted from GAA budget? · MOOE 1,029,035 (NEP) vs 911,616 (Excel) · Document Code from QMS · signatories per fiscal year? · keep A–S letters? · "2 of Advocacies" wording · drop FY 2026? · Calendar of Activities, Research / WFP Form 2, Quick Look are not built yet.

## Testing
Build: `cd frontend && npm run build`. Lint: `php -l`. Local run: `php -S 127.0.0.1:86 -t frontend/dist server.php` with MariaDB (`service mariadb start`). Browser checks with Playwright (Chromium preinstalled).
