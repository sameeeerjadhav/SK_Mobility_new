ALTER TABLE purchase_order_items
  ADD COLUMN item_type ENUM('vehicle_variant','spare_part') NOT NULL DEFAULT 'vehicle_variant' AFTER purchase_order_id,
  ADD COLUMN spare_part_id INT UNSIGNED NULL AFTER variant_id,
  MODIFY vehicle_id INT UNSIGNED NULL,
  MODIFY variant_id INT UNSIGNED NULL;

ALTER TABLE purchase_order_items
  ADD CONSTRAINT fk_po_items_spare_part FOREIGN KEY (spare_part_id) REFERENCES spare_parts(id) ON DELETE SET NULL;

ALTER TABLE purchase_order_receipt_lines
  MODIFY warehouse_id INT UNSIGNED NULL;
