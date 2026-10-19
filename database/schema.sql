-- R1MC Work and Financial Plan (WFP) Encoding System
-- MySQL 5.7+ / MariaDB 10.3+ (XAMPP)
-- Mirrors the DOH WFP template: "General Info" sheet -> settings,
-- "Encoding Sheet" columns A..S -> activities, hidden "Validation" sheet -> reference lists.
-- Money is stored exactly as in the template: in '000 PhP.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS app_meta;
DROP TABLE IF EXISTS access_requests;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS activity_research;
DROP TABLE IF EXISTS activity_items;
DROP TABLE IF EXISTS activities;
DROP TABLE IF EXISTS budget_ceilings;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS indicators;
DROP TABLE IF EXISTS objectives;
DROP TABLE IF EXISTS classifications;
DROP TABLE IF EXISTS line_items;
DROP TABLE IF EXISTS fund_sources;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS offices;
SET FOREIGN_KEY_CHECKS = 1;

-- Areas / offices that encode (each has its own login)
CREATE TABLE offices (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  code      VARCHAR(20)  NOT NULL UNIQUE,
  name      VARCHAR(150) NOT NULL,
  active    TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- admin: everything | planning: all offices, approve, print | encoder: own office only | viewer: read-only
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(150) NOT NULL,
  position      VARCHAR(150) NULL,
  role          ENUM('admin','planning','encoder','viewer') NOT NULL DEFAULT 'encoder',
  office_id     INT NULL,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  must_change   TINYINT(1) NOT NULL DEFAULT 0,   -- set after an admin reset: user picks a new password at next login
  reset_requested_at DATETIME NULL,              -- "Forgot your password?" request waiting for the admin
  failed_logins TINYINT NOT NULL DEFAULT 0,      -- 5 wrong passwords in a row lock the account for 15 minutes
  locked_until  DATETIME NULL,
  last_login    DATETIME NULL,
  notes_seen_at DATETIME NULL,                   -- Planning/admin: submissions after this are shown as new
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_office FOREIGN KEY (office_id) REFERENCES offices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Column A -> B -> C  (Validation!A:C)
CREATE TABLE objectives (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  function_type ENUM('Strategic','Core','Support') NOT NULL,
  title         VARCHAR(500) NOT NULL,
  sort_order    INT NOT NULL DEFAULT 0,
  active        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE indicators (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  objective_id INT NOT NULL,
  title        VARCHAR(600) NOT NULL,
  sort_order   INT NOT NULL DEFAULT 0,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_ind_obj FOREIGN KEY (objective_id) REFERENCES objectives(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Column E (Validation!E)
CREATE TABLE classifications (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(200) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Column F (Validation!F)
CREATE TABLE line_items (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(200) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Column Q (Validation!R). is_income = 1 -> printed on Print (Income), otherwise on Print (GAA)
CREATE TABLE fund_sources (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  is_income  TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  active     TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- "General Info" sheet, one row per fiscal year. Budgets in '000 PhP.
CREATE TABLE settings (
  fiscal_year       SMALLINT PRIMARY KEY,
  office_name       VARCHAR(200) NOT NULL DEFAULT 'Region I Medical Center',
  prepared_by       VARCHAR(200) NULL,
  prepared_pos      VARCHAR(200) NULL,
  recommending_by   VARCHAR(300) NULL,
  recommending_pos  VARCHAR(200) NULL,
  approved_by       VARCHAR(300) NULL,
  approved_pos      VARCHAR(200) NULL,
  document_code     VARCHAR(100) NULL,
  mooe_budget       DECIMAL(14,2) NOT NULL DEFAULT 0,   -- General Info E13
  co_budget         DECIMAL(14,2) NOT NULL DEFAULT 0,   -- E15
  conap_mooe        DECIMAL(14,2) NOT NULL DEFAULT 0,   -- E17
  conap_co          DECIMAL(14,2) NOT NULL DEFAULT 0,   -- E19
  other_fund        DECIMAL(14,2) NOT NULL DEFAULT 0,   -- E21
  encoding_open     TINYINT(1) NOT NULL DEFAULT 1,
  encoding_deadline DATE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row = one row of the Encoding Sheet (columns A..S)
CREATE TABLE activities (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  fiscal_year       SMALLINT NOT NULL,
  office_id         INT NOT NULL,                                   -- which area encoded it
  function_type     ENUM('Strategic','Core','Support') NOT NULL,     -- A
  objective_id      INT NOT NULL,                                   -- B
  indicator_id      INT NOT NULL,                                   -- C
  objective_other   VARCHAR(600) NULL,  -- B typed when "Others, please insert note/remarks" is chosen
  indicator_other   VARCHAR(600) NULL,  -- C typed when "Others, please insert note/remarks" is chosen
  title             VARCHAR(600) NOT NULL,                          -- D
  classification_id INT NOT NULL,                                   -- E
  line_item_id      INT NOT NULL,                                   -- F
  saa               TINYINT(1) NOT NULL DEFAULT 0,                  -- G
  expense_class     ENUM('MOOE','CO') NOT NULL DEFAULT 'MOOE',      -- H
  start_date        DATE NOT NULL,                                  -- I
  end_date          DATE NOT NULL,                                  -- J
  target_q1         VARCHAR(50) NULL,                               -- K
  target_q2         VARCHAR(50) NULL,                               -- L
  target_q3         VARCHAR(50) NULL,                               -- M
  target_q4         VARCHAR(50) NULL,                               -- N
  target_unit       VARCHAR(200) NOT NULL,                          -- O
  cost              DECIMAL(14,2) NOT NULL DEFAULT 0,               -- P  ('000 PhP)
  fund_source_id    INT NOT NULL,                                   -- Q
  responsible       VARCHAR(300) NOT NULL,                          -- R
  impl_status       ENUM('Obligated','Disbursed','Deferred','Ongoing','Conducted') NOT NULL DEFAULT 'Ongoing', -- S
  workflow          ENUM('draft','submitted','returned','approved') NOT NULL DEFAULT 'draft',
  review_note       TEXT NULL,
  created_by        INT NULL,
  updated_by        INT NULL,
  reviewed_by       INT NULL,
  submitted_by      INT NULL,                                       -- who pressed Submit (for the notification)
  submitted_at      DATETIME NULL,
  reviewed_at       DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_act_fy_office (fiscal_year, office_id),
  KEY ix_act_workflow (workflow),
  CONSTRAINT fk_act_office FOREIGN KEY (office_id) REFERENCES offices(id),
  CONSTRAINT fk_act_obj    FOREIGN KEY (objective_id) REFERENCES objectives(id),
  CONSTRAINT fk_act_ind    FOREIGN KEY (indicator_id) REFERENCES indicators(id),
  CONSTRAINT fk_act_class  FOREIGN KEY (classification_id) REFERENCES classifications(id),
  CONSTRAINT fk_act_line   FOREIGN KEY (line_item_id) REFERENCES line_items(id),
  CONSTRAINT fk_act_fund   FOREIGN KEY (fund_source_id) REFERENCES fund_sources(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- "Request access" on the sign-in page: waits here until the administrator grants or declines it
CREATE TABLE IF NOT EXISTS access_requests (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(150) NOT NULL,
  position      VARCHAR(150) NULL,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,             -- chosen by the requester; becomes the account password when granted
  office_id     INT NULL,                          -- an existing area ...
  new_office    VARCHAR(150) NULL,                 -- ... or a new one, created when access is granted
  role          ENUM('encoder','viewer') NOT NULL DEFAULT 'encoder',  -- level asked for; the admin may grant another
  note          VARCHAR(300) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Schema version: backend/tools/install.php compares it with SCHEMA_VERSION in backend/lib/db.php
CREATE TABLE app_meta (
  name  VARCHAR(50) PRIMARY KEY,
  value VARCHAR(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO app_meta (name, value) VALUES ('schema_version', '9');
