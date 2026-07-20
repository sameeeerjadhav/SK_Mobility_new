-- Performance indexes for list filters and dashboard date ranges.
-- Applied via /install.php?migrate_performance=1

ALTER TABLE notifications ADD INDEX idx_notifications_user_read (user_id, is_read);
ALTER TABLE dealers ADD INDEX idx_dealers_user (user_id);
ALTER TABLE dealers ADD INDEX idx_dealers_status_created (status, created_at);
ALTER TABLE orders ADD INDEX idx_orders_status_created (status, created_at);
ALTER TABLE orders ADD INDEX idx_orders_dealer_created (dealer_id, created_at);
ALTER TABLE payments ADD INDEX idx_payments_status_date (status, payment_date);
ALTER TABLE leads ADD INDEX idx_leads_status_created (status, created_at);
ALTER TABLE leads ADD INDEX idx_leads_dealer_status (dealer_id, status);
ALTER TABLE expenses ADD INDEX idx_expenses_date (expense_date);
ALTER TABLE partner_transactions ADD INDEX idx_partner_tx_type_date (transaction_type, date);
