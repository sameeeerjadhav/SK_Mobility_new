<?php

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use PDO;
use RuntimeException;

class OrderService
{
    public static function create(array $data, int $userId): array
    {
        $db = Database::connection();
        $orderType = $data['order_type'] ?? '';

        if (!in_array($orderType, ['dealer', 'customer'], true)) {
            throw new RuntimeException('Invalid order type.');
        }

        $items = $data['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            throw new RuntimeException('Add at least one order item.');
        }

        $dealerId = null;
        if ($orderType === 'dealer') {
            $dealerId = (int)($data['dealer_id'] ?? 0);
            if ($dealerId <= 0) {
                throw new RuntimeException('Dealer is required for dealer orders.');
            }
        } else {
            if (empty($data['customer_name']) || empty($data['customer_phone'])) {
                throw new RuntimeException('Customer name and phone are required.');
            }
        }

        $lineItems = [];
        $subtotal = 0.0;
        foreach ($items as $item) {
            $variantId = (int)($item['variant_id'] ?? 0);
            $qty = max(1, (int)($item['quantity'] ?? 1));
            if ($variantId <= 0) {
                continue;
            }
            $stmt = $db->prepare(
                'SELECT vv.*, v.id AS vehicle_id, v.name AS vehicle_name
                 FROM vehicle_variants vv
                 JOIN vehicles v ON v.id = vv.vehicle_id
                 WHERE vv.id = ? AND vv.is_active = 1'
            );
            $stmt->execute([$variantId]);
            $variant = $stmt->fetch();
            if (!$variant) {
                throw new RuntimeException('Invalid vehicle variant selected.');
            }
            $unit = (float)$variant['price'];
            $total = $unit * $qty;
            $subtotal += $total;
            $lineItems[] = [
                'vehicle_id' => (int)$variant['vehicle_id'],
                'variant_id' => $variantId,
                'quantity' => $qty,
                'unit_price' => $unit,
                'total_price' => $total,
                'description' => $variant['vehicle_name'] . ' — ' . $variant['name'] . ($variant['color'] ? ' (' . $variant['color'] . ')' : ''),
            ];
        }

        if (!$lineItems) {
            throw new RuntimeException('No valid items in order.');
        }

        $pmIncentive = $orderType === 'customer' ? (float)($data['pm_drive_incentive'] ?? 0) : 0.0;
        $stateSubsidy = $orderType === 'customer' ? (float)($data['state_subsidy'] ?? 0) : 0.0;
        $taxable = max(0, $subtotal - $pmIncentive - $stateSubsidy);
        $taxAmount = round($taxable * 0.28, 2);
        $totalAmount = round($taxable + $taxAmount, 2);

        $prefix = $orderType === 'dealer' ? 'ORD' : 'CORD';
        $orderNumber = next_code($prefix, 'orders', 'order_number');

        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO orders (
                    order_number, order_type, dealer_id,
                    customer_name, customer_phone, customer_email, customer_address,
                    customer_aadhaar, customer_pan, chassis_no, motor_no, battery_capacity, color,
                    pm_drive_incentive, state_subsidy, subtotal, tax_amount, total_amount,
                    status, delivery_address, notes, expected_delivery_date, created_by
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $orderNumber, $orderType, $dealerId,
                $data['customer_name'] ?? null,
                $data['customer_phone'] ?? null,
                $data['customer_email'] ?? null,
                $data['customer_address'] ?? null,
                $data['customer_aadhaar'] ?? null,
                $data['customer_pan'] ?? null,
                $data['chassis_no'] ?? null,
                $data['motor_no'] ?? null,
                $data['battery_capacity'] ?? null,
                $data['color'] ?? null,
                $pmIncentive, $stateSubsidy, $subtotal, $taxAmount, $totalAmount,
                'pending',
                $data['delivery_address'] ?? null,
                $data['notes'] ?? null,
                $data['expected_delivery_date'] ?: null,
                $userId,
            ]);
            $orderId = (int)$db->lastInsertId();

            $itemStmt = $db->prepare(
                'INSERT INTO order_items (order_id, vehicle_id, variant_id, quantity, unit_price, total_price)
                 VALUES (?,?,?,?,?,?)'
            );
            foreach ($lineItems as $li) {
                $itemStmt->execute([
                    $orderId, $li['vehicle_id'], $li['variant_id'],
                    $li['quantity'], $li['unit_price'], $li['total_price'],
                ]);
            }

            $db->prepare(
                'INSERT INTO order_status_history (order_id, status, notes, changed_by) VALUES (?,?,?,?)'
            )->execute([$orderId, 'pending', 'Order created', $userId]);

            // Auto-create bill
            $billNumber = next_code('INV', 'bills', 'bill_number');
            $customerName = $orderType === 'dealer'
                ? (self::dealerName($db, $dealerId) ?? 'Dealer')
                : ($data['customer_name'] ?? '');

            $dealerCode = null;
            if ($dealerId) {
                $cs = $db->prepare('SELECT dealer_code FROM dealers WHERE id = ?');
                $cs->execute([$dealerId]);
                $dealerCode = $cs->fetchColumn() ?: null;
            }

            $db->prepare(
                'INSERT INTO bills (
                    bill_number, bill_type, order_id,
                    company_name, company_address, company_phone, company_email,
                    company_gstin, company_state_code, brand_name, dealer_code,
                    customer_name, customer_phone, customer_address,
                    customer_aadhaar, customer_pan,
                    vehicle_model, chassis_no, motor_no, vehicle_sale_date,
                    subtotal, tax_rate, cgst_rate, sgst_rate,
                    pm_drive_incentive, state_subsidy, total_amount, created_by
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $billNumber, 'vehicle', $orderId,
                setting('company_name'), setting('company_address'),
                setting('company_phone'), setting('company_email'),
                setting('company_gstin'), setting('company_state_code'),
                setting('brand_name'), $dealerCode,
                $customerName,
                $data['customer_phone'] ?? null,
                $data['customer_address'] ?? ($data['delivery_address'] ?? null),
                $data['customer_aadhaar'] ?? null,
                $data['customer_pan'] ?? null,
                $lineItems[0]['description'] ?? null,
                $data['chassis_no'] ?? null,
                $data['motor_no'] ?? null,
                date('Y-m-d'),
                $subtotal, 28, 14, 14,
                $pmIncentive, $stateSubsidy, $totalAmount, $userId,
            ]);
            $billId = (int)$db->lastInsertId();

            $bi = $db->prepare(
                'INSERT INTO bill_items (bill_id, description, hsn_code, quantity, unit_price, total_price)
                 VALUES (?,?,?,?,?,?)'
            );
            foreach ($lineItems as $li) {
                $bi->execute([
                    $billId, $li['description'], '87116020',
                    $li['quantity'], $li['unit_price'], $li['total_price'],
                ]);
            }

            if ($dealerId) {
                $db->prepare(
                    'UPDATE dealers SET total_orders = total_orders + 1, total_revenue = total_revenue + ? WHERE id = ?'
                )->execute([$totalAmount, $dealerId]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        NotificationService::notifyRole(
            'super_admin',
            'New Order',
            "Order {$orderNumber} created for " . money($totalAmount),
            'order',
            'orders',
            $orderId
        );

        Audit::log('create', 'orders', 'orders', $orderId, null, [
            'order_number' => $orderNumber,
            'total' => $totalAmount,
        ]);

        return [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'bill_id' => $billId,
            'bill_number' => $billNumber,
            'total_amount' => $totalAmount,
        ];
    }

    private static function dealerName(PDO $db, int $dealerId): ?string
    {
        $stmt = $db->prepare('SELECT business_name FROM dealers WHERE id = ?');
        $stmt->execute([$dealerId]);
        return $stmt->fetchColumn() ?: null;
    }

    public static function updateStatus(int $orderId, string $status, int $userId, ?string $notes = null): void
    {
        $allowed = ['pending', 'approved', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('Invalid status.');
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        if (Auth::role() === 'dealer' && (int)$order['dealer_id'] !== Auth::dealerId()) {
            throw new RuntimeException('Unauthorized.');
        }

        $db->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $orderId]);
        $db->prepare(
            'INSERT INTO order_status_history (order_id, status, notes, changed_by) VALUES (?,?,?,?)'
        )->execute([$orderId, $status, $notes, $userId]);

        NotificationService::notifyRole(
            'super_admin',
            'Order Status Updated',
            "Order {$order['order_number']} is now {$status}",
            'order',
            'orders',
            $orderId
        );

        Audit::log('update', 'orders', 'orders', $orderId, ['status' => $order['status']], ['status' => $status]);
    }
}
