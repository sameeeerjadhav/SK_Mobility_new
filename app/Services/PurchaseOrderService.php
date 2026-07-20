<?php

namespace App\Services;

use App\Core\Auth;
use PDO;
use RuntimeException;

class PurchaseOrderService
{
    private const GST_RATE = 5.0;

    public function __construct(private PDO $db)
    {
    }

    /** @return array{subtotal: float, gst_amount: float, total_amount: float, items: list<array>} */
    public function calcLines(array $rawItems): array
    {
        $items = [];
        $subtotal = 0.0;
        $gstTotal = 0.0;

        foreach ($rawItems as $i => $row) {
            $variantId = (int)($row['variant_id'] ?? 0);
            $qty = (int)($row['quantity'] ?? 0);
            $unitRate = round((float)($row['unit_rate'] ?? 0), 2);
            if ($variantId <= 0 || $qty <= 0 || $unitRate <= 0) {
                continue;
            }

            $gstPercent = (float)($row['gst_percent'] ?? self::GST_RATE);
            $taxable = round($unitRate * $qty, 2);
            $gst = round($taxable * ($gstPercent / 100), 2);
            $lineTotal = round($taxable + $gst, 2);

            $items[] = [
                'variant_id' => $variantId,
                'vehicle_id' => (int)($row['vehicle_id'] ?? 0),
                'color' => trim((string)($row['color'] ?? '')),
                'hsn_code' => trim((string)($row['hsn_code'] ?? '87116020')) ?: '87116020',
                'description' => trim((string)($row['description'] ?? '')),
                'quantity' => $qty,
                'unit_rate' => $unitRate,
                'gst_percent' => $gstPercent,
                'taxable_value' => $taxable,
                'gst_amount' => $gst,
                'line_total' => $lineTotal,
                'sort_order' => $i,
            ];
            $subtotal += $taxable;
            $gstTotal += $gst;
        }

        if (!$items) {
            throw new RuntimeException('Add at least one line item with variant, quantity and unit rate.');
        }

        return [
            'subtotal' => round($subtotal, 2),
            'gst_amount' => round($gstTotal, 2),
            'total_amount' => round($subtotal + $gstTotal, 2),
            'items' => $items,
        ];
    }

    /** @param list<array{po_item_id: int, warehouse_id: int, quantity: int}> $allocations */
    public function receive(int $poId, array $allocations, ?string $notes = null): void
    {
        if (!$allocations) {
            throw new RuntimeException('Add at least one warehouse allocation.');
        }

        $po = $this->findPo($poId);
        if (in_array($po['status'], ['received', 'cancelled'], true)) {
            throw new RuntimeException('This purchase order cannot receive stock.');
        }

        $items = $this->itemsForPo($poId);
        $byId = [];
        foreach ($items as $item) {
            $byId[(int)$item['id']] = $item;
        }

        $pendingByItem = [];
        foreach ($allocations as $row) {
            $itemId = (int)($row['po_item_id'] ?? 0);
            $warehouseId = (int)($row['warehouse_id'] ?? 0);
            $qty = (int)($row['quantity'] ?? 0);
            if ($itemId <= 0 || $warehouseId <= 0 || $qty <= 0) {
                continue;
            }
            if (!isset($byId[$itemId])) {
                throw new RuntimeException('Invalid line item in receipt.');
            }
            $pendingByItem[$itemId] = ($pendingByItem[$itemId] ?? 0) + $qty;
        }

        if (!$pendingByItem) {
            throw new RuntimeException('Enter quantity for at least one warehouse allocation.');
        }

        foreach ($pendingByItem as $itemId => $qty) {
            $item = $byId[$itemId];
            $remaining = (int)$item['quantity_ordered'] - (int)$item['quantity_received'];
            if ($qty > $remaining) {
                throw new RuntimeException(
                    'Receive quantity exceeds pending amount for ' . ($item['description'] ?: $item['variant_name'])
                );
            }
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO purchase_order_receipts (purchase_order_id, notes, created_by) VALUES (?,?,?)'
            )->execute([$poId, $notes, Auth::id()]);
            $receiptId = (int)$this->db->lastInsertId();

            foreach ($allocations as $row) {
                $itemId = (int)($row['po_item_id'] ?? 0);
                $warehouseId = (int)($row['warehouse_id'] ?? 0);
                $qty = (int)($row['quantity'] ?? 0);
                if ($itemId <= 0 || $warehouseId <= 0 || $qty <= 0) {
                    continue;
                }

                $item = $byId[$itemId];
                $variantId = (int)$item['variant_id'];
                $vehicleId = (int)$item['vehicle_id'];

                // Keep inventory row aligned with vehicle catalog
                $vCheck = $this->db->prepare('SELECT vehicle_id FROM vehicle_variants WHERE id = ?');
                $vCheck->execute([$variantId]);
                $linkedVehicleId = (int)$vCheck->fetchColumn();
                if ($linkedVehicleId > 0) {
                    $vehicleId = $linkedVehicleId;
                }

                $this->db->prepare(
                    'INSERT INTO purchase_order_receipt_lines (receipt_id, po_item_id, warehouse_id, quantity) VALUES (?,?,?,?)'
                )->execute([$receiptId, $itemId, $warehouseId, $qty]);

                $this->db->prepare(
                    'UPDATE purchase_order_items SET quantity_received = quantity_received + ? WHERE id = ?'
                )->execute([$qty, $itemId]);

                $inv = $this->db->prepare(
                    'SELECT id FROM inventory WHERE variant_id = ? AND warehouse_id = ?'
                );
                $inv->execute([$variantId, $warehouseId]);
                $invId = $inv->fetchColumn();
                if ($invId) {
                    $this->db->prepare(
                        'UPDATE inventory SET quantity_available = quantity_available + ? WHERE id = ?'
                    )->execute([$qty, $invId]);
                } else {
                    $this->db->prepare(
                        'INSERT INTO inventory (vehicle_id, variant_id, warehouse_id, quantity_available) VALUES (?,?,?,?)'
                    )->execute([$vehicleId, $variantId, $warehouseId, $qty]);
                }

                $this->db->prepare(
                    'INSERT INTO inventory_movements (variant_id, warehouse_id, movement_type, quantity, reference_id, notes, created_by)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([
                    $variantId,
                    $warehouseId,
                    'stock_in',
                    $qty,
                    $poId,
                    'PO ' . $po['po_number'] . ' receipt #' . $receiptId,
                    Auth::id(),
                ]);
            }

            $counts = $this->db->prepare(
                'SELECT
                    SUM(quantity_ordered) AS ordered,
                    SUM(quantity_received) AS received
                 FROM purchase_order_items WHERE purchase_order_id = ?'
            );
            $counts->execute([$poId]);
            $totals = $counts->fetch(PDO::FETCH_ASSOC);
            $ordered = (int)($totals['ordered'] ?? 0);
            $received = (int)($totals['received'] ?? 0);

            $status = 'partial';
            if ($received >= $ordered && $ordered > 0) {
                $status = 'received';
            } elseif ($received === 0) {
                $status = $po['status'] === 'draft' ? 'confirmed' : $po['status'];
            }

            $this->db->prepare('UPDATE purchase_orders SET status = ? WHERE id = ?')
                ->execute([$status, $poId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function insertItems(int $poId, array $items): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO purchase_order_items (
                purchase_order_id, vehicle_id, variant_id, color, hsn_code, description,
                quantity_ordered, unit_rate, gst_percent, taxable_value, gst_amount, line_total, sort_order
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );

        foreach ($items as $item) {
            $resolved = $this->resolveVariant(
                (int)$item['variant_id'],
                (string)($item['color'] ?? ''),
                (float)$item['unit_rate']
            );
            $item['variant_id'] = $resolved['variant_id'];
            $item['vehicle_id'] = $resolved['vehicle_id'];
            $item['color'] = $resolved['color'];

            $stmt->execute([
                $poId,
                $item['vehicle_id'],
                $item['variant_id'],
                $item['color'] ?: null,
                $item['hsn_code'],
                $item['description'] ?: null,
                $item['quantity'],
                $item['unit_rate'],
                $item['gst_percent'],
                $item['taxable_value'],
                $item['gst_amount'],
                $item['line_total'],
                $item['sort_order'],
            ]);
        }
    }

    /**
     * Tie PO line to the correct vehicle variant (by color). Creates a new variant when needed.
     *
     * @return array{variant_id: int, vehicle_id: int, color: string}
     */
    public function resolveVariant(int $variantId, string $color, float $unitRate): array
    {
        $stmt = $this->db->prepare('SELECT * FROM vehicle_variants WHERE id = ?');
        $stmt->execute([$variantId]);
        $base = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$base) {
            throw new RuntimeException('Invalid vehicle variant selected.');
        }

        $color = trim($color);
        $baseColor = trim((string)($base['color'] ?? ''));
        if ($color === '' || strcasecmp($color, $baseColor) === 0) {
            return [
                'variant_id' => $variantId,
                'vehicle_id' => (int)$base['vehicle_id'],
                'color' => $baseColor ?: $color,
            ];
        }

        $find = $this->db->prepare(
            'SELECT id, vehicle_id, color FROM vehicle_variants
             WHERE vehicle_id = ? AND name = ? AND (battery_type <=> ?)
               AND LOWER(TRIM(COALESCE(color, \'\'))) = LOWER(?)
             LIMIT 1'
        );
        $find->execute([$base['vehicle_id'], $base['name'], $base['battery_type'], $color]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return [
                'variant_id' => (int)$existing['id'],
                'vehicle_id' => (int)$existing['vehicle_id'],
                'color' => (string)($existing['color'] ?? $color),
            ];
        }

        $skuBase = strtoupper(substr(slugify($base['name'] . '-' . $color), 0, 12));
        $sku = $skuBase . '-' . random_int(100, 999);
        $skuCheck = $this->db->prepare('SELECT COUNT(*) FROM vehicle_variants WHERE sku = ?');
        for ($i = 0; $i < 5; $i++) {
            $skuCheck->execute([$sku]);
            if ((int)$skuCheck->fetchColumn() === 0) {
                break;
            }
            $sku = $skuBase . '-' . random_int(100, 999);
        }

        $sellPrice = $unitRate > 0 ? round($unitRate * 1.05, 2) : (float)$base['price'];
        $this->db->prepare(
            'INSERT INTO vehicle_variants (vehicle_id, name, sku, color, price, battery_type, battery_spec, range_km, is_active)
             VALUES (?,?,?,?,?,?,?,?,1)'
        )->execute([
            $base['vehicle_id'],
            $base['name'],
            $sku,
            $color,
            $sellPrice,
            $base['battery_type'],
            $base['battery_spec'],
            $base['range_km'],
        ]);

        return [
            'variant_id' => (int)$this->db->lastInsertId(),
            'vehicle_id' => (int)$base['vehicle_id'],
            'color' => $color,
        ];
    }

    /** @return array<string, mixed> */
    public function findPo(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT po.*, p.name AS partner_name, u.first_name, u.last_name
             FROM purchase_orders po
             LEFT JOIN partners p ON p.id = po.partner_id
             JOIN users u ON u.id = po.created_by
             WHERE po.id = ?'
        );
        $stmt->execute([$id]);
        $po = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$po) {
            throw new RuntimeException('Purchase order not found.');
        }
        return $po;
    }

    /** @return list<array<string, mixed>> */
    public function itemsForPo(int $poId): array
    {
        $stmt = $this->db->prepare(
            'SELECT poi.*, v.name AS vehicle_name, vv.name AS variant_name, vv.sku, vv.battery_type
             FROM purchase_order_items poi
             JOIN vehicles v ON v.id = poi.vehicle_id
             JOIN vehicle_variants vv ON vv.id = poi.variant_id
             WHERE poi.purchase_order_id = ?
             ORDER BY poi.sort_order, poi.id'
        );
        $stmt->execute([$poId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
