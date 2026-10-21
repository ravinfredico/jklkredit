-- MySQL 8 schema (InnoDB). Money in whole rupiah; flat rate in basis points (500 = 5.00% p.a.).

CREATE TABLE dealers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  city VARCHAR(60) NOT NULL,
  bank_name VARCHAR(60) NOT NULL,
  bank_account VARCHAR(30) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('MARKETING','ATASAN','ADMIN_BO','DEALER') NOT NULL,
  supervisor_id INT NULL,
  dealer_id INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_user_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id),
  CONSTRAINT fk_user_dealer FOREIGN KEY (dealer_id) REFERENCES dealers(id)
) ENGINE=InnoDB;

CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nik CHAR(16) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  birth_date DATE NOT NULL,
  marital_status ENUM('BELUM_KAWIN','KAWIN','CERAI') NOT NULL,
  phone VARCHAR(16) NOT NULL,
  address VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE customer_spouses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL UNIQUE,
  nik CHAR(16) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  CONSTRAINT fk_spouse_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE loan_applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  app_no VARCHAR(20) NOT NULL UNIQUE,
  customer_id INT NOT NULL,
  marketing_id INT NOT NULL,
  dealer_id INT NOT NULL,
  spk_no VARCHAR(40) NOT NULL,
  tanda_jadi BIGINT NOT NULL DEFAULT 0,
  otr_price BIGINT NOT NULL,
  down_payment BIGINT NOT NULL,
  tenor_months INT NOT NULL,
  flat_rate_bp INT NOT NULL,
  insurance_type ENUM('ALL_RISK','TLO') NOT NULL,
  principal BIGINT NOT NULL,
  total_interest BIGINT NOT NULL,
  installment BIGINT NOT NULL,
  status ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED','DOCS_PRINTED','SIGNED','DISBURSED') NOT NULL DEFAULT 'DRAFT',
  approved_by INT NULL,
  approved_at DATETIME NULL,
  rejection_reason VARCHAR(255) NULL,
  disbursement_ref VARCHAR(40) NULL UNIQUE,
  disbursed_by INT NULL,
  disbursed_at DATETIME NULL,
  version INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX ix_app_status (status),
  INDEX ix_app_marketing (marketing_id),
  CONSTRAINT fk_app_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_app_marketing FOREIGN KEY (marketing_id) REFERENCES users(id),
  CONSTRAINT fk_app_dealer FOREIGN KEY (dealer_id) REFERENCES dealers(id),
  CONSTRAINT fk_app_approver FOREIGN KEY (approved_by) REFERENCES users(id),
  CONSTRAINT fk_app_disburser FOREIGN KEY (disbursed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE vehicles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL UNIQUE,
  brand VARCHAR(40) NOT NULL,
  model VARCHAR(40) NOT NULL,
  type VARCHAR(40) NOT NULL,
  color VARCHAR(30) NOT NULL,
  year INT NOT NULL,
  CONSTRAINT fk_vehicle_app FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE application_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  doc_type ENUM('KTP','KK','BUKTI_TANDA_JADI','KONTRAK','PO','KONTRAK_SIGNED') NOT NULL,
  original_name VARCHAR(150) NOT NULL,
  mime VARCHAR(40) NOT NULL,
  content_b64 LONGTEXT NOT NULL,
  uploaded_by INT NOT NULL,
  uploaded_at DATETIME NOT NULL,
  CONSTRAINT fk_doc_app FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE purchase_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL UNIQUE,
  po_no VARCHAR(20) NOT NULL UNIQUE,
  dealer_id INT NOT NULL,
  amount BIGINT NOT NULL,
  issued_at DATETIME NOT NULL,
  acknowledged_at DATETIME NULL,
  CONSTRAINT fk_po_app FOREIGN KEY (application_id) REFERENCES loan_applications(id),
  CONSTRAINT fk_po_dealer FOREIGN KEY (dealer_id) REFERENCES dealers(id)
) ENGINE=InnoDB;

CREATE TABLE status_histories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  from_status VARCHAR(14) NULL,
  to_status VARCHAR(14) NOT NULL,
  actor_id INT NOT NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  INDEX ix_hist_app (application_id),
  CONSTRAINT fk_hist_app FOREIGN KEY (application_id) REFERENCES loan_applications(id) ON DELETE CASCADE,
  CONSTRAINT fk_hist_actor FOREIGN KEY (actor_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE parameters (
  name VARCHAR(40) PRIMARY KEY,
  value VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE sessions (
  id VARCHAR(128) PRIMARY KEY,
  data MEDIUMTEXT NOT NULL,
  updated_at INT NOT NULL
) ENGINE=InnoDB;
