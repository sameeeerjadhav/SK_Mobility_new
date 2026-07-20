ALTER TABLE purchase_orders
  ADD COLUMN product_type ENUM('vehicle','spare_part') NOT NULL DEFAULT 'vehicle' AFTER po_number;

UPDATE purchase_orders po
SET product_type = 'spare_part'
WHERE EXISTS (
    SELECT 1 FROM purchase_order_items poi
    WHERE poi.purchase_order_id = po.id AND poi.item_type = 'spare_part'
)
AND NOT EXISTS (
    SELECT 1 FROM purchase_order_items poi
    WHERE poi.purchase_order_id = po.id AND poi.item_type = 'vehicle_variant'
);
