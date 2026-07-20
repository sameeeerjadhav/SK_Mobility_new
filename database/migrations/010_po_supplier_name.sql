ALTER TABLE purchase_orders ADD COLUMN supplier_name VARCHAR(200) NULL AFTER po_number;

UPDATE purchase_orders po
LEFT JOIN partners p ON p.id = po.partner_id
SET po.supplier_name = p.name
WHERE po.partner_id IS NOT NULL AND (po.supplier_name IS NULL OR po.supplier_name = '');
