-- SQLite schema for local demo. Same structure as schema.mysql.sql.
-- Money is stored in whole rupiah (INTEGER); the flat rate in basis points (500 = 5.00% p.a.).

CREATE TABLE dealers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  city VARCHAR(60) NOT NULL,
  bank_name VARCHAR(60) NOT NULL,
  bank_account VARCHAR(30) NOT NULL
);

CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(12) NOT NULL CHECK (role IN ('MARKETING','ATASAN','ADMIN_BO','DEALER')),
  supervisor_id INTEGER NULL REFERENCES users(id),
  dealer_id INTEGER NULL REFERENCES dealers(id),
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL
);

CREATE TABLE customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nik CHAR(16) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  birth_date DATE NOT NULL,
  marital_status VARCHAR(12) NOT NULL CHECK (marital_status IN ('BELUM_KAWIN','KAWIN','CERAI')),
  phone VARCHAR(16) NOT NULL,
  address VARCHAR(255) NOT NULL
);

CREATE TABLE customer_spouses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  customer_id INTEGER NOT NULL UNIQUE REFERENCES customers(id) ON DELETE CASCADE,
  nik CHAR(16) NOT NULL,
  full_name VARCHAR(100) NOT NULL
);

CREATE TABLE loan_applications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  app_no VARCHAR(20) NOT NULL UNIQUE,
  customer_id INTEGER NOT NULL REFERENCES customers(id),
  marketing_id INTEGER NOT NULL REFERENCES users(id),
  dealer_id INTEGER NOT NULL REFERENCES dealers(id),
  spk_no VARCHAR(40) NOT NULL,
  tanda_jadi INTEGER NOT NULL DEFAULT 0,
  otr_price INTEGER NOT NULL,
  down_payment INTEGER NOT NULL,
  tenor_months INTEGER NOT NULL,
  flat_rate_bp INTEGER NOT NULL,
  insurance_type VARCHAR(10) NOT NULL CHECK (insurance_type IN ('ALL_RISK','TLO')),
  principal INTEGER NOT NULL,
  total_interest INTEGER NOT NULL,
  installment INTEGER NOT NULL,
  status VARCHAR(14) NOT NULL DEFAULT 'DRAFT'
    CHECK (status IN ('DRAFT','SUBMITTED','APPROVED','REJECTED','DOCS_PRINTED','SIGNED','DISBURSED')),
  approved_by INTEGER NULL REFERENCES users(id),
  approved_at DATETIME NULL,
  rejection_reason VARCHAR(255) NULL,
  disbursement_ref VARCHAR(40) NULL UNIQUE,
  disbursed_by INTEGER NULL REFERENCES users(id),
  disbursed_at DATETIME NULL,
  version INTEGER NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);
CREATE INDEX ix_app_status ON loan_applications(status);
CREATE INDEX ix_app_marketing ON loan_applications(marketing_id);

CREATE TABLE vehicles (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  application_id INTEGER NOT NULL UNIQUE REFERENCES loan_applications(id) ON DELETE CASCADE,
  brand VARCHAR(40) NOT NULL,
  model VARCHAR(40) NOT NULL,
  type VARCHAR(40) NOT NULL,
  color VARCHAR(30) NOT NULL,
  year INTEGER NOT NULL
);

CREATE TABLE application_documents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  application_id INTEGER NOT NULL REFERENCES loan_applications(id) ON DELETE CASCADE,
  doc_type VARCHAR(20) NOT NULL
    CHECK (doc_type IN ('KTP','KK','BUKTI_TANDA_JADI','KONTRAK','PO','KONTRAK_SIGNED')),
  original_name VARCHAR(150) NOT NULL,
  mime VARCHAR(40) NOT NULL,
  content_b64 TEXT NOT NULL,
  uploaded_by INTEGER NOT NULL REFERENCES users(id),
  uploaded_at DATETIME NOT NULL
);

CREATE TABLE purchase_orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  application_id INTEGER NOT NULL UNIQUE REFERENCES loan_applications(id),
  po_no VARCHAR(20) NOT NULL UNIQUE,
  dealer_id INTEGER NOT NULL REFERENCES dealers(id),
  amount INTEGER NOT NULL,
  issued_at DATETIME NOT NULL,
  acknowledged_at DATETIME NULL
);

CREATE TABLE status_histories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  application_id INTEGER NOT NULL REFERENCES loan_applications(id) ON DELETE CASCADE,
  from_status VARCHAR(14) NULL,
  to_status VARCHAR(14) NOT NULL,
  actor_id INTEGER NOT NULL REFERENCES users(id),
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL
);
CREATE INDEX ix_hist_app ON status_histories(application_id);

CREATE TABLE parameters (
  name VARCHAR(40) PRIMARY KEY,
  value VARCHAR(100) NOT NULL
);

CREATE TABLE sessions (
  id VARCHAR(128) PRIMARY KEY,
  data TEXT NOT NULL,
  updated_at INTEGER NOT NULL
);
