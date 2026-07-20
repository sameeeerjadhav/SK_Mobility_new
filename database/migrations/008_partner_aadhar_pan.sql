-- Partner Aadhar & PAN fields
-- Run once on existing databases (or use /install.php?migrate_partners=1)

ALTER TABLE partners
  ADD COLUMN aadhar_number VARCHAR(20) NULL AFTER address,
  ADD COLUMN pan_number VARCHAR(20) NULL AFTER aadhar_number;
