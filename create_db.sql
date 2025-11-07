-- WIPES old tables; run in phpMyAdmin or CLI
CREATE DATABASE IF NOT EXISTS self_invoice
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE self_invoice;

DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS invoices;

CREATE TABLE IF NOT EXISTS invoices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(32) NOT NULL,
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL,
  from_name    VARCHAR(255) NOT NULL,
  from_address VARCHAR(255) NOT NULL,
  from_phone   VARCHAR(50)  NOT NULL,
  from_email   VARCHAR(255) NULL,
  from_hst     VARCHAR(100) NOT NULL,
  bill_to_name    VARCHAR(255) NOT NULL,
  bill_to_address VARCHAR(255) NOT NULL,
  bill_to_phone   VARCHAR(50)  NOT NULL,
  bill_to_hst     VARCHAR(100) NOT NULL,
  subtotal_excl_hst DECIMAL(12,2) NOT NULL,
  hst_amount        DECIMAL(12,2) NOT NULL,
  total_incl_hst    DECIMAL(12,2) NOT NULL,
  client_ip   VARCHAR(64)  NULL,
  user_agent  VARCHAR(255) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id    INT UNSIGNED NOT NULL,
  line_no       SMALLINT UNSIGNED NOT NULL,
  description   VARCHAR(255) NOT NULL,
  days          INT UNSIGNED NOT NULL,
  rate_incl_hst DECIMAL(10,2) NOT NULL,
  amount_incl   DECIMAL(12,2) NOT NULL,
  KEY idx_invoice_id (invoice_id),
  CONSTRAINT fk_items_invoice FOREIGN KEY (invoice_id)
    REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
