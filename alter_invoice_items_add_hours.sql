ALTER TABLE invoice_items
  ADD COLUMN hours DECIMAL(10,2) NULL AFTER days;
