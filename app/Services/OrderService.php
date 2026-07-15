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
            $requiredInvoice = [
                'color' => 'Model Color',
                'sale_date' => 'Date of Sale',
                'chassis_no' => 'Chassis No.',
                'motor_no' => 'Motor No.',
                'motor_warranty' => 'Motor Warranty',
                'battery_capacity' => 'Battery Type',
                'battery_no' => 'Battery No.',
                'battery_warranty' => 'Battery Warranty',
                'controller_no' => 'Controller No.',
                'controller_warranty' => 'Controller Warranty',
                'charger_no' => 'Charger No.',
                'charger_warranty' => 'Charger Warranty',
            ];
            foreach ($requiredInvoice as $key => $label) {
                if (trim((string)($data[$key] ?? '')) === '') {
                    throw new RuntimeException($label . ' is required for the tax invoice.');
                }
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
                'model_code' => $variant['sku'] ?? null,
                'model_name' => $variant['vehicle_name'],
                'color' => $variant['color'] ?? null,
            ];
        }

        if (!$lineItems) {
            throw new RuntimeException('No valid items in order.');
        }

        $pmIncentive = $orderType === 'customer' ? (float)($data['pm_drive_incentive'] ?? 0) : 0.0;
        $stateSubsidy = $orderType === 'customer' ? (float)($data['state_subsidy'] ?? 0) : 0.0;
        $extraDisc = (float)($data['discount_amount'] ?? 0);
        $loanAmount = (float)($data['loan_amount'] ?? 0);
        $totalDisc = $pmIncentive + $stateSubsidy + $extraDisc;

        $taxable = max(0, $subtotal - $totalDisc);
        $cgst = round($taxable * 0.14, 2);
        $sgst = round($taxable * 0.14, 2);
        $taxAmount = round($cgst + $sgst, 2);
        $totalAmount = round($taxable + $taxAmount, 2);

        $prefix = $orderType === 'dealer' ? 'ORD' : 'CORD';
        $orderNumber = next_code($prefix, 'orders', 'order_number');
        $bookingNo = trim((string)($data['booking_no'] ?? '')) ?: $orderNumber;
        $saleDate = $data['sale_date'] ?: date('Y-m-d');
        $color = $data['color'] ?? ($lineItems[0]['color'] ?? null);
        $batteryTypeNo = trim(implode(' ', array_filter([
            $data['battery_capacity'] ?? null,
            $data['battery_no'] ?? null,
        ])));

        $paymentMode = self::normalizePaymentMode($data);

        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO orders (
                    order_number, booking_no, order_type, dealer_id,
                    customer_name, customer_phone, customer_email, customer_address,
                    customer_aadhaar, customer_pan, chassis_no, motor_no,
                    battery_capacity, battery_no, controller_no, charger_no,
                    motor_warranty, battery_warranty, controller_warranty, charger_warranty,
                    hp_name, color, vehicle_model_type,
                    pm_drive_incentive, state_subsidy, loan_amount, discount_amount,
                    payment_mode, sale_date, subtotal, tax_amount, total_amount,
                    status, delivery_address, notes, expected_delivery_date, created_by
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $orderNumber, $bookingNo, $orderType, $dealerId,
                $data['customer_name'] ?? null,
                $data['customer_phone'] ?? null,
                $data['customer_email'] ?? null,
                $data['customer_address'] ?? null,
                $data['customer_aadhaar'] ?? null,
                $data['customer_pan'] ?? null,
                $data['chassis_no'] ?? null,
                $data['motor_no'] ?? null,
                $data['battery_capacity'] ?? null,
                $data['battery_no'] ?? null,
                $data['controller_no'] ?? null,
                $data['charger_no'] ?? null,
                $data['motor_warranty'] ?? null,
                $data['battery_warranty'] ?? null,
                $data['controller_warranty'] ?? null,
                $data['charger_warranty'] ?? null,
                $data['hp_name'] ?? null,
                $color,
                $data['vehicle_model_type'] ?? null,
                $pmIncentive, $stateSubsidy, $loanAmount, $extraDisc,
                $paymentMode, $saleDate, $subtotal, $taxAmount, $totalAmount,
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
                    bill_number, bill_type, order_id, booking_no,
                    company_name, company_address, company_branch_address, company_phone, company_email,
                    company_gstin, company_state, company_state_code, brand_name, dealer_code,
                    customer_name, customer_phone, customer_email, customer_address,
                    customer_aadhaar, customer_pan,
                    vehicle_model, vehicle_model_type, color, chassis_no, motor_no,
                    battery_type_no, controller_no, charger_no,
                    motor_warranty, battery_warranty, controller_warranty, charger_warranty,
                    hp_name, vehicle_sale_date,
                    subtotal, tax_rate, cgst_rate, sgst_rate,
                    pm_drive_incentive, state_subsidy, loan_amount, discount_amount,
                    payment_mode, total_amount, created_by
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $billNumber, 'vehicle', $orderId, $bookingNo,
                setting('company_name'), setting('company_address'), setting('company_branch_address'),
                setting('company_phone'), setting('company_email'),
                setting('company_gstin'), setting('company_state', 'Maharashtra'), setting('company_state_code'),
                setting('brand_name'), $dealerCode,
                $customerName,
                $data['customer_phone'] ?? null,
                $data['customer_email'] ?? null,
                $data['customer_address'] ?? ($data['delivery_address'] ?? null),
                $data['customer_aadhaar'] ?? null,
                $data['customer_pan'] ?? null,
                $lineItems[0]['model_name'] ?? ($lineItems[0]['description'] ?? null),
                $data['vehicle_model_type'] ?? null,
                $color,
                $data['chassis_no'] ?? null,
                $data['motor_no'] ?? null,
                $batteryTypeNo !== '' ? $batteryTypeNo : null,
                $data['controller_no'] ?? null,
                $data['charger_no'] ?? null,
                $data['motor_warranty'] ?? null,
                $data['battery_warranty'] ?? null,
                $data['controller_warranty'] ?? null,
                $data['charger_warranty'] ?? null,
                $data['hp_name'] ?? null,
                $saleDate,
                $subtotal, 28, 14, 14,
                $pmIncentive, $stateSubsidy, $loanAmount, $extraDisc,
                $paymentMode, $totalAmount, $userId,
            ]);
            $billId = (int)$db->lastInsertId();

            $bi = $db->prepare(
                'INSERT INTO bill_items (bill_id, description, model_code, hsn_code, quantity, unit_price, discount, taxable_amount, cgst_amount, sgst_amount, total_price)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            foreach ($lineItems as $idx => $li) {
                $lineDisc = $idx === 0 ? $totalDisc : 0.0;
                $lineTaxable = max(0, $li['unit_price'] * $li['quantity'] - $lineDisc);
                $lineCgst = round($lineTaxable * 0.14, 2);
                $lineSgst = round($lineTaxable * 0.14, 2);
                $lineTotal = round($lineTaxable + $lineCgst + $lineSgst, 2);
                $bi->execute([
                    $billId, $li['description'], $li['model_code'], '87116020',
                    $li['quantity'], $li['unit_price'], $lineDisc,
                    $lineTaxable, $lineCgst, $lineSgst, $lineTotal,
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

    private static function normalizePaymentMode(array $data): ?string
    {
        if (!empty($data['payment_mode'])) {
            return (string)$data['payment_mode'];
        }
        $parts = [];
        if (!empty($data['paid_cash'])) {
            $parts[] = 'cash';
        }
        if (!empty($data['paid_cheque'])) {
            $parts[] = 'cheque';
        }
        return $parts ? implode('_', $parts) : null;
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
