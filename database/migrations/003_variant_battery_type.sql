-- Battery chemistry type + V/AH capacity options on vehicle variants
-- Run once on existing databases (or use /install.php?migrate_variant=1)

ALTER TABLE vehicle_variants
  ADD COLUMN IF NOT EXISTS battery_type ENUM('Lithium Ion','Lead Acid') NULL AFTER battery_capacity_kwh,
  ADD COLUMN IF NOT EXISTS battery_spec VARCHAR(100) NULL AFTER battery_type;
