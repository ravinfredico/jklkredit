# Berkas Kredit JKL

A web app for PT. JKL's vehicle credit applications, built for question 2a of the BCA Finance PDP Technology coding test (2025 past paper). It replaces the paper flow with one digital workflow:

DRAFT → SUBMITTED → APPROVED → DOCS_PRINTED → SIGNED → DISBURSED (with SUBMITTED → REJECTED → DRAFT for revisions)

## Run it

Requirements: PHP 8.1+ with `pdo_sqlite` (or `pdo_mysql`) and `gd`.

```bash
php bin/setup.php                                   # create the database and load demo data
php -S 127.0.0.1:8080 -t public public/index.php    # open http://127.0.0.1:8080
php tests/run.php                                   # 33 workflow tests
```

Demo accounts, all with the password `rahasia123`:

| Username | Role | Try this |
| --- | --- | --- |
| rina | Marketing | Create an application, upload KTP/KK/tanda jadi, submit |
| dimas | Marketing | A second marketer, to check that each one sees only their own files |
| budi | Atasan Marketing | Approve or reject your team's submissions |
| sari | Admin Backoffice | Generate the contract and PO, then disburse |
| dealer.sunter | Dealer | See and acknowledge POs for Sunter Motor |

### Using MySQL instead of SQLite

```bash
mysql -e "CREATE DATABASE jkl_kredit CHARACTER SET utf8mb4"
export DB_DSN="mysql:host=127.0.0.1;dbname=jkl_kredit;charset=utf8mb4" DB_USER=root DB_PASS=secret
php bin/setup.php     # loads database/schema.mysql.sql
```

## How it is built

- **Stack:** plain PHP 8 with PDO, plus FPDF for the contract and PO. There is no framework because Composer packages were unavailable where this was built. The structure (front controller, services, views) maps one-to-one onto Laravel routes, controllers and Blade views.
- **`src/workflow.php`:** every status change goes through `transition()`. It checks the allowed move, the role, ownership and the team (only the marketer's own supervisor can decide), and the maker-checker rule. It then writes the new status and a `status_histories` row in one transaction. An optimistic lock (`WHERE status = ? AND version = ?`) stops double approvals and double disbursements. The disbursement reference is also unique.
- **`src/calc.php`:** the flat-rate installment calculation, in whole rupiah integers (no floats):
  pokok = OTR − DP, bunga = pokok × rate × tenor / 12, angsuran = (pokok + bunga) / tenor rounded up to Rp100.
  The form's live preview calls the same server function. The rate comes from `parameters` and is stored on each application.
- **`src/validation.php`:** the rules are:
  - NIK is 16 digits, and the applicant is at least 21.
  - If married, spouse data is required.
  - DP is at least the `MIN_DP_PCT` parameter, the tenor is from the allowed list, and tanda jadi cannot exceed the DP.
  - JPG, PNG and PDF files are checked by MIME type, up to 5 MB.
- **Files:** stored under `storage/`, outside the public web root. They are served only through `/documents/{id}` after an access check.
- **Security:** passwords are hashed with `password_hash`, sessions are regenerated on login, and every POST carries a CSRF token. All SQL uses prepared statements, and all output is escaped.

## Folders

```
public/      index.php (router), assets (CSS, self-hosted fonts)
src/         db, auth, calc, validation, workflow, documents, view helpers
views/       page templates
database/    schema.sqlite.sql, schema.mysql.sql
bin/         setup.php (schema + demo data, run through the real workflow)
tests/       run.php
lib/fpdf/    FPDF 1.86 (permissive licence)
storage/     database and uploaded or generated files (created by setup)
```

Fonts: Public Sans and Archivo Narrow, SIL Open Font License (see `public/assets/fonts`).
