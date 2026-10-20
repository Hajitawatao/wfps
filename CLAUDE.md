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
- `start.bat` runs `php -S` on the port in `start.local.bat` (use 8000+, Chrome blocks 87) and `backend/tools/install.php`, which upgrades the DB in place. Schema version 10 (`backend/lib/db.php` SCHEMA_VERSION; add a migration block in install.php for every schema change).
- Amounts in '000 PhP. Colors: graphite + teal tokens in `frontend/src/style.css` (light + dark); menu always dark (`--menu*`).

## How the WFP works (Excel and system)
- Dropdown lists (DOH: objectives, indicators, classifications, line items, fund sources) → areas encode activities (Encoding Sheet) → Print GAA / Print Income are generated (GAA = non-Income sources).
- Remaining MOOE/CO = General Info total − encoded cost, whole hospital. Summary filter: all / submitted+approved / approved.
- Budget per area (v10, R1MC rule, not in the DOH Excel): Procurement gives each area MOOE and CO (`office_budgets`); the areas together may not exceed the hospital total (blocked). An area over its own budget only gets a warning. Area users see their area budget + the hospital total on the Summary and the Encode form; Planning/Procurement see every area's budget via the area filter, the Per area table and Budget per area.
- Workflow: draft → submitted (by the area; counts as the area head's approval) → approved / returned by Planning.
- Signatories: one set for the whole hospital in General Info (Prepared by Planning Officer, Recommending: MCC, Approved: DOH USec). A one-area print shows an "AREA COPY" band. Per-area signatories and area heads were removed in v8.
- "Others, please insert note/remarks" in B or C: a text box appears and the typed text (objective_other / indicator_other) is shown on the Encoding Sheet and prints instead.
- Encode many (`/encoding-sheet/many`, `api/activities-bulk.php`): for PPMP lists. A–C, E–H, Q–S set once; each row has D, I–J, K–N, O, P; Add row, Paste from Excel (TSV in that order, or paste into a cell); all rows saved or none, errors per row; "Save all as draft" / "Save all and submit".
- "Others" → Dropdown lists: when Planning approves an activity with typed Others text, a checkbox (on by default) adds the objective (above "Others" of the same type of function) and the indicator to the Dropdown lists, reusing an existing entry with the same title, and moves the activity onto it (`activity-status.php`, `add_to_list`). Submitted activities with Others text are flagged for Planning: "Others, please verify" with the typed B/C in the bell, and an "Others: verify B/C" tag in For review.
- Roles: admin (all), planning (all areas, review/approve, General Info, dropdown lists, adds/renames areas on the "Areas" page; sees area budgets read-only; no user accounts, cannot delete areas), procurement (gives area budgets; views all; cannot encode, approve or add areas), encoder (own area only), viewer (read only). Access requests are granted by admin. Notification bell: Planning/admin see submissions waiting for review; encoders see their area's approved/returned activities with the note. Menu counts: "For review" (waiting) and "Encoding Sheet" (returned to the area). Viewers get none.

## Open questions for Planning (do not decide alone)
Income deducted from GAA budget? · MOOE 1,029,035 (NEP) vs 911,616 (Excel) · Document Code from QMS · signatories per fiscal year? · keep A–S letters? · "2 of Advocacies" wording · drop FY 2026? · Calendar of Activities, Research / WFP Form 2, Quick Look are not built yet.

## Testing
Build: `cd frontend && npm run build`. Lint: `php -l`. Local run: `php -S 127.0.0.1:86 -t frontend/dist server.php` with MariaDB (`service mariadb start`). Browser checks with Playwright (Chromium preinstalled).
