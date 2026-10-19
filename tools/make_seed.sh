#!/bin/sh
# Rebuilds database/seed.sql:  sh tools/make_seed.sh /path/to/PRINT_GAA.xlsx [fiscal year]
set -e
XLSX="$(cd "$(dirname "$1")" && pwd)/$(basename "$1")"
cd "$(dirname "$0")/../database"
FY="${2:-2027}"
PREV=$((FY - 1))
python3 -I ../tools/build_seed.py "$XLSX" --fy "$FY" > seed.sql
cat >> seed.sql <<EOF

-- FY $PREV is also open (blank), with the same names. Budgets start at 0.
INSERT INTO settings (fiscal_year,office_name,prepared_by,prepared_pos,recommending_by,recommending_pos,approved_by,approved_pos)
  SELECT $PREV,office_name,prepared_by,prepared_pos,recommending_by,recommending_pos,approved_by,approved_pos FROM settings WHERE fiscal_year=$FY;
EOF
H_ADMIN=$(php -r 'echo password_hash("admin123", PASSWORD_BCRYPT);')
H_DEF=$(php -r 'echo password_hash("wfp2026", PASSWORD_BCRYPT);')
cat >> seed.sql <<EOF

-- Default accounts. CHANGE THE PASSWORDS after the first login.
--   admin / admin123      planning / wfp2026      mis / wfp2026
-- Encoder accounts for the other areas are created in the app: Users & offices > Add areas.
INSERT INTO users (username,password_hash,full_name,position,role,office_id) VALUES ('admin','$H_ADMIN','System Administrator',NULL,'admin',2);
INSERT INTO users (username,password_hash,full_name,position,role,office_id) VALUES ('planning','$H_DEF','Planning Office',NULL,'planning',1);
INSERT INTO users (username,password_hash,full_name,position,role,office_id) VALUES ('mis','$H_DEF','MIS Encoder',NULL,'encoder',2);
UPDATE users SET must_change=1;
EOF
echo "database/seed.sql written (FY $PREV and $FY)"
