ALTER TABLE purchase_orders
  ADD COLUMN cgst_rate DECIMAL(5,2) NOT NULL DEFAULT 2.5 AFTER gst_amount,
  ADD COLUMN sgst_rate DECIMAL(5,2) NOT NULL DEFAULT 2.5 AFTER cgst_rate,
  ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 5 AFTER sgst_rate;

UPDATE purchase_orders
SET cgst_rate = IF(product_type = 'spare_part', 9, 2.5),
    sgst_rate = IF(product_type = 'spare_part', 9, 2.5),
    tax_rate = IF(product_type = 'spare_part', 18, 5)
WHERE tax_rate = 5 OR tax_rate = 0;
