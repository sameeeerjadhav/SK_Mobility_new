ALTER TABLE orders
  ADD COLUMN product_type ENUM('vehicle','spare_part') NOT NULL DEFAULT 'vehicle' AFTER order_type;

ALTER TABLE order_items
  ADD COLUMN item_type ENUM('vehicle_variant','spare_part') NOT NULL DEFAULT 'vehicle_variant' AFTER order_id,
  ADD COLUMN spare_part_id INT UNSIGNED NULL AFTER variant_id;

ALTER TABLE order_items
  MODIFY vehicle_id INT UNSIGNED NULL,
  MODIFY variant_id INT UNSIGNED NULL;

ALTER TABLE order_items
  ADD CONSTRAINT fk_order_items_spare_part FOREIGN KEY (spare_part_id) REFERENCES spare_parts(id) ON DELETE SET NULL;

ALTER TABLE bills
  MODIFY bill_type ENUM('vehicle','warranty','spare') NOT NULL DEFAULT 'vehicle';
