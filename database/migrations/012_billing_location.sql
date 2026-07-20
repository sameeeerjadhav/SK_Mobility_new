ALTER TABLE orders
  ADD COLUMN billing_location ENUM('kokamthan','kopargaon') NOT NULL DEFAULT 'kokamthan' AFTER order_type;

ALTER TABLE bills
  ADD COLUMN billing_location ENUM('kokamthan','kopargaon') NOT NULL DEFAULT 'kokamthan' AFTER bill_type;
