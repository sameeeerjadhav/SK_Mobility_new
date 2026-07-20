ALTER TABLE purchase_orders
  ADD COLUMN product_type ENUM('vehicle','spare_part') NOT NULL DEFAULT 'vehicle' AFTER po_number;

-- Backfill (only if purchase_order_items.item_type exists — see install.php migrate_po_product_type)
