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
            $lineNo = $i + 1;
            $itemType = ($row['item_type'] ?? '') === 'spare_part' ? 'spare_part' : 'vehicle_variant';
            $qty = (int)($row['quantity'] ?? 0);
            $unitRate = round((float)($row['unit_rate'] ?? 0), 2);

            if ($qty <= 0 || $unitRate <= 0) {
                continue;
            }

            $gstPercent = round((float)($row['gst_percent'] ?? self::GST_RATE), 2);
            if ($gstPercent < 0 || $gstPercent > 100) {
                throw new RuntimeException("Line {$lineNo}: GST percent must be between 0 and 100.");
            }
            $taxable = round($unitRate * $qty, 2);
            $gst = round($taxable * ($gstPercent / 100), 2);
            $lineTotal = round($taxable + $gst, 2);

            $base = [
                'item_type' => $itemType,
                'hsn_code' => trim((string)($row['hsn_code'] ?? '')) ?: ($itemType === 'spare_part' ? '85076000' : '87116020'),
                'description' => trim((string)($row['description'] ?? '')),
                'quantity' => $qty,
                'unit_rate' => $unitRate,
                'gst_percent' => $gstPercent,
                'taxable_value' => $taxable,
                'gst_amount' => $gst,
                'line_total' => $lineTotal,
                'sort_order' => $i,
            ];

            if ($itemType === 'spare_part') {
                $items[] = array_merge($base, $this->calcSpareLine($row, $lineNo));
            } else {
                $items[] = array_merge($base, $this->calcVehicleLine($row, $lineNo));
            }

            $subtotal += $taxable;
            $gstTotal += $gst;
        }

        if (!$items) {
            throw new RuntimeException('Add at least one valid line item with quantity and unit rate.');
        }

        return [
            'subtotal' => round($subtotal, 2),
            'gst_amount' => round($gstTotal, 2),
            'total_amount' => round($subtotal + $gstTotal, 2),
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    private function calcVehicleLine(array $row, int $lineNo): array
    {
        $mode = ($row['variant_mode'] ?? '') === 'new' ? 'new' : 'existing';
        $variantId = (int)($row['variant_id'] ?? 0);
        $vehicleId = (int)($row['vehicle_id'] ?? 0);
        $newVariantName = trim((string)($row['new_variant_name'] ?? ''));
        $vehicleMode = ($row['vehicle_mode'] ?? '') === 'new' ? 'new' : 'existing';
        $newVehicleName = trim((string)($row['new_vehicle_name'] ?? ''));
        $vehicleCategoryId = (int)($row['vehicle_category_id'] ?? 0);

        if ($mode === 'existing') {
            if ($variantId <= 0) {
                throw new RuntimeException("Line {$lineNo}: select an existing variant or switch to New variant.");
            }
            if ($newVariantName !== '') {
                throw new RuntimeException("Line {$lineNo}: use either existing variant or new variant name, not both.");
            }
            $vehicleMode = 'existing';
            $newVehicleName = '';
            $vehicleCategoryId = 0;
        } else {
            if ($variantId > 0) {
                throw new RuntimeException("Line {$lineNo}: clear existing variant selection when adding a new variant.");
            }
            if ($newVariantName === '') {
                throw new RuntimeException("Line {$lineNo}: new variant name is required.");
            }
            if ($vehicleMode === 'existing') {
                if ($vehicleId <= 0) {
                    throw new RuntimeException("Line {$lineNo}: select an existing vehicle or switch to New vehicle.");
                }
                if ($newVehicleName !== '') {
                    throw new RuntimeException("Line {$lineNo}: use either existing vehicle or new vehicle name, not both.");
                }
            } else {
                if ($vehicleId > 0) {
                    throw new RuntimeException("Line {$lineNo}: clear existing vehicle selection when adding a new vehicle.");
                }
                if ($newVehicleName === '' || $vehicleCategoryId <= 0) {
                    throw new RuntimeException("Line {$lineNo}: vehicle name and category are required for a new vehicle.");
                }
            }
        }

        return [
            'variant_mode' => $mode,
            'variant_id' => $variantId,
            'vehicle_id' => $vehicleId,
            'vehicle_mode' => $vehicleMode,
            'new_vehicle_name' => $newVehicleName,
            'vehicle_category_id' => $vehicleCategoryId,
            'new_variant_name' => $newVariantName,
            'battery_type' => trim((string)($row['battery_type'] ?? '')) ?: null,
            'battery_spec' => trim((string)($row['battery_spec'] ?? '')) ?: null,
            'color' => trim((string)($row['color'] ?? '')),
            'spare_mode' => null,
            'spare_part_id' => 0,
            'spare_category_id' => 0,
            'new_part_name' => '',
        ];
    }

    /** @return array<string, mixed> */
    private function calcSpareLine(array $row, int $lineNo): array
    {
        $mode = ($row['spare_mode'] ?? '') === 'new' ? 'new' : 'existing';
        $sparePartId = (int)($row['spare_part_id'] ?? 0);
        $newPartName = trim((string)($row['new_part_name'] ?? ''));
        $categoryId = (int)($row['spare_category_id'] ?? 0);

        if ($mode === 'existing') {
            if ($sparePartId <= 0) {
                throw new RuntimeException("Line {$lineNo}: select an existing spare part or switch to New part.");
            }
            if ($newPartName !== '') {
                throw new RuntimeException("Line {$lineNo}: use either existing spare part or new part name, not both.");
            }
        } else {
            if ($sparePartId > 0) {
                throw new RuntimeException("Line {$lineNo}: clear existing spare part when adding a new part.");
            }
            if ($newPartName === '' || $categoryId <= 0) {
                throw new RuntimeException("Line {$lineNo}: part name and category are required for a new spare part.");
            }
        }

        return [
            'variant_mode' => null,
            'variant_id' => 0,
            'vehicle_id' => 0,
            'new_variant_name' => '',
            'battery_type' => null,
            'battery_spec' => null,
            'color' => '',
            'spare_mode' => $mode,
            'spare_part_id' => $sparePartId,
            'spare_category_id' => $categoryId,
            'new_part_name' => $newPartName,
        ];
    }

    /** @param list<array{po_item_id: int, warehouse_id: int, quantity: int}> $allocations */
    public function receive(int $poId, array $allocations, ?string $notes = null): void
    {
        if (!$allocations) {
            throw new RuntimeException('Enter quantity for at least one line item.');
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
            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }
            if (!isset($byId[$itemId])) {
                throw new RuntimeException('Invalid line item in receipt.');
            }
            $isSpare = ($byId[$itemId]['item_type'] ?? 'vehicle_variant') === 'spare_part';
            if (!$isSpare && $warehouseId <= 0) {
                continue;
            }
            $pendingByItem[$itemId] = ($pendingByItem[$itemId] ?? 0) + $qty;
        }

        if (!$pendingByItem) {
            throw new RuntimeException('Enter quantity for at least one line item.');
        }

        foreach ($pendingByItem as $itemId => $qty) {
            $item = $byId[$itemId];
            $remaining = (int)$item['quantity_ordered'] - (int)$item['quantity_received'];
            if ($qty > $remaining) {
                throw new RuntimeException(
                    'Receive quantity exceeds pending amount for ' . $this->itemLabel($item)
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
                if ($itemId <= 0 || $qty <= 0) {
                    continue;
                }

                $item = $byId[$itemId];
                $isSpare = ($item['item_type'] ?? 'vehicle_variant') === 'spare_part';
                if (!$isSpare && $warehouseId <= 0) {
                    continue;
                }

                $this->db->prepare(
                    'INSERT INTO purchase_order_receipt_lines (receipt_id, po_item_id, warehouse_id, quantity) VALUES (?,?,?,?)'
                )->execute([$receiptId, $itemId, $isSpare ? null : $warehouseId, $qty]);

                $this->db->prepare(
                    'UPDATE purchase_order_items SET quantity_received = quantity_received + ? WHERE id = ?'
                )->execute([$qty, $itemId]);

                if ($isSpare) {
                    $this->db->prepare(
                        'UPDATE spare_parts SET quantity_in_stock = quantity_in_stock + ? WHERE id = ?'
                    )->execute([$qty, (int)$item['spare_part_id']]);
                    continue;
                }

                $variantId = (int)$item['variant_id'];
                $vehicleId = (int)$item['vehicle_id'];

                $vCheck = $this->db->prepare('SELECT vehicle_id FROM vehicle_variants WHERE id = ?');
                $vCheck->execute([$variantId]);
                $linkedVehicleId = (int)$vCheck->fetchColumn();
                if ($linkedVehicleId > 0) {
                    $vehicleId = $linkedVehicleId;
                }

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
                purchase_order_id, item_type, vehicle_id, variant_id, spare_part_id, color, hsn_code, description,
                quantity_ordered, unit_rate, gst_percent, taxable_value, gst_amount, line_total, sort_order
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );

        foreach ($items as $item) {
            $itemType = $item['item_type'] ?? 'vehicle_variant';
            if ($itemType === 'spare_part') {
                $sparePartId = $this->resolveSparePart($item);
                $vehicleId = null;
                $variantId = null;
                $color = null;
            } else {
                $resolved = $this->resolveLineVariant($item);
                $sparePartId = null;
                $vehicleId = $resolved['vehicle_id'];
                $variantId = $resolved['variant_id'];
                $color = $resolved['color'] ?: null;
            }

            $stmt->execute([
                $poId,
                $itemType,
                $vehicleId,
                $variantId,
                $sparePartId,
                $color,
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

    private function resolveSparePart(array $item): int
    {
        if (($item['spare_mode'] ?? '') === 'new') {
            return $this->findOrCreateSparePart(
                (int)$item['spare_category_id'],
                (string)$item['new_part_name'],
                (float)$item['unit_rate']
            );
        }

        $sparePartId = (int)($item['spare_part_id'] ?? 0);
        if ($sparePartId <= 0) {
            throw new RuntimeException('Invalid spare part selected.');
        }
        $check = $this->db->prepare('SELECT id FROM spare_parts WHERE id = ? AND is_active = 1');
        $check->execute([$sparePartId]);
        if (!$check->fetchColumn()) {
            throw new RuntimeException('Invalid spare part selected.');
        }
        return $sparePartId;
    }

    private function findOrCreateSparePart(int $categoryId, string $name, float $unitRate): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Spare part name is required.');
        }

        $cat = $this->db->prepare('SELECT id FROM spare_categories WHERE id = ? AND is_active = 1');
        $cat->execute([$categoryId]);
        if (!$cat->fetchColumn()) {
            throw new RuntimeException('Invalid spare part category selected.');
        }

        $find = $this->db->prepare(
            'SELECT id FROM spare_parts WHERE category_id = ? AND LOWER(TRIM(name)) = LOWER(?) LIMIT 1'
        );
        $find->execute([$categoryId, $name]);
        $existing = $find->fetchColumn();
        if ($existing) {
            return (int)$existing;
        }

        $partBase = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', slugify($name)) ?: 'SP', 0, 10));
        $partNumber = $partBase . '-' . random_int(100, 999);
        $skuCheck = $this->db->prepare('SELECT COUNT(*) FROM spare_parts WHERE part_number = ?');
        for ($i = 0; $i < 5; $i++) {
            $skuCheck->execute([$partNumber]);
            if ((int)$skuCheck->fetchColumn() === 0) {
                break;
            }
            $partNumber = $partBase . '-' . random_int(100, 999);
        }

        $this->db->prepare(
            'INSERT INTO spare_parts (category_id, name, part_number, unit_price, quantity_in_stock, is_active)
             VALUES (?,?,?,?,0,1)'
        )->execute([$categoryId, $name, $partNumber, $unitRate > 0 ? $unitRate : 0]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $item
     * @return array{variant_id: int, vehicle_id: int, color: string}
     */
    private function resolveLineVariant(array $item): array
    {
        if (($item['variant_mode'] ?? '') === 'new') {
            return $this->resolveNewVariant($item);
        }

        return $this->resolveExistingVariant(
            (int)$item['variant_id'],
            (string)($item['color'] ?? ''),
            (float)$item['unit_rate']
        );
    }

    /** @return array{variant_id: int, vehicle_id: int, color: string} */
    private function resolveExistingVariant(int $variantId, string $color, float $unitRate): array
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

        return $this->findOrCreateVariant(
            (int)$base['vehicle_id'],
            (string)$base['name'],
            $color,
            $unitRate,
            $base['battery_type'],
            $base['battery_spec'],
            $base['range_km']
        );
    }

    /** @param array<string, mixed> $item @return array{variant_id: int, vehicle_id: int, color: string} */
    private function resolveNewVariant(array $item): array
    {
        $vehicleId = $this->resolveVehicleId($item);

        return $this->findOrCreateVariant(
            $vehicleId,
            trim((string)$item['new_variant_name']),
            trim((string)($item['color'] ?? '')),
            (float)$item['unit_rate'],
            $item['battery_type'] ?? null,
            $item['battery_spec'] ?? null,
            null
        );
    }

    private function resolveVehicleId(array $item): int
    {
        if (($item['vehicle_mode'] ?? '') === 'new') {
            return $this->findOrCreateVehicle(
                (int)$item['vehicle_category_id'],
                (string)$item['new_vehicle_name'],
                (float)$item['unit_rate']
            );
        }

        $vehicleId = (int)($item['vehicle_id'] ?? 0);
        if ($vehicleId <= 0) {
            throw new RuntimeException('Invalid vehicle selected for new variant.');
        }

        $vehicle = $this->db->prepare('SELECT id FROM vehicles WHERE id = ? AND is_active = 1');
        $vehicle->execute([$vehicleId]);
        if (!$vehicle->fetchColumn()) {
            throw new RuntimeException('Invalid vehicle selected for new variant.');
        }

        return $vehicleId;
    }

    private function findOrCreateVehicle(int $categoryId, string $name, float $basePrice): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Vehicle name is required.');
        }

        $cat = $this->db->prepare('SELECT id FROM vehicle_categories WHERE id = ? AND is_active = 1');
        $cat->execute([$categoryId]);
        if (!$cat->fetchColumn()) {
            throw new RuntimeException('Invalid vehicle category selected.');
        }

        $find = $this->db->prepare(
            'SELECT id FROM vehicles WHERE category_id = ? AND LOWER(TRIM(name)) = LOWER(?) LIMIT 1'
        );
        $find->execute([$categoryId, $name]);
        $existing = $find->fetchColumn();
        if ($existing) {
            return (int)$existing;
        }

        $slug = slugify($name);
        $slugCheck = $this->db->prepare('SELECT COUNT(*) FROM vehicles WHERE slug = ?');
        $slugCheck->execute([$slug]);
        if ((int)$slugCheck->fetchColumn() > 0) {
            $slug .= '-' . random_int(100, 999);
        }

        $this->db->prepare(
            'INSERT INTO vehicles (category_id, name, slug, brand, base_price, is_active)
             VALUES (?,?,?,?,?,1)'
        )->execute([
            $categoryId,
            $name,
            $slug,
            'SK Mobility',
            $basePrice > 0 ? $basePrice : 0,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * @return array{variant_id: int, vehicle_id: int, color: string}
     */
    private function findOrCreateVariant(
        int $vehicleId,
        string $name,
        string $color,
        float $unitRate,
        ?string $batteryType,
        ?string $batterySpec,
        mixed $rangeKm
    ): array {
        if ($name === '') {
            throw new RuntimeException('Variant name is required.');
        }

        $find = $this->db->prepare(
            'SELECT id, vehicle_id, color FROM vehicle_variants
             WHERE vehicle_id = ? AND name = ? AND (battery_type <=> ?)
               AND LOWER(TRIM(COALESCE(color, \'\'))) = LOWER(?)
             LIMIT 1'
        );
        $find->execute([$vehicleId, $name, $batteryType, $color]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return [
                'variant_id' => (int)$existing['id'],
                'vehicle_id' => (int)$existing['vehicle_id'],
                'color' => (string)($existing['color'] ?? $color),
            ];
        }

        $skuBase = strtoupper(substr(slugify($name . '-' . ($color ?: 'NA')), 0, 12));
        $sku = $skuBase . '-' . random_int(100, 999);
        $skuCheck = $this->db->prepare('SELECT COUNT(*) FROM vehicle_variants WHERE sku = ?');
        for ($i = 0; $i < 5; $i++) {
            $skuCheck->execute([$sku]);
            if ((int)$skuCheck->fetchColumn() === 0) {
                break;
            }
            $sku = $skuBase . '-' . random_int(100, 999);
        }

        $sellPrice = $unitRate > 0 ? round($unitRate * 1.05, 2) : 0;
        $this->db->prepare(
            'INSERT INTO vehicle_variants (vehicle_id, name, sku, color, price, battery_type, battery_spec, range_km, is_active)
             VALUES (?,?,?,?,?,?,?,?,1)'
        )->execute([
            $vehicleId,
            $name,
            $sku,
            $color ?: null,
            $sellPrice,
            $batteryType,
            $batterySpec,
            $rangeKm !== null && $rangeKm !== '' ? (int)$rangeKm : null,
        ]);

        return [
            'variant_id' => (int)$this->db->lastInsertId(),
            'vehicle_id' => $vehicleId,
            'color' => $color,
        ];
    }

    /** @param array<string, mixed> $item */
    private function itemLabel(array $item): string
    {
        if (($item['item_type'] ?? 'vehicle_variant') === 'spare_part') {
            $label = trim((string)($item['spare_part_name'] ?? ''));
            if ($label === '') {
                $label = trim((string)($item['description'] ?? 'spare part'));
            }
            return $label;
        }

        return trim((string)($item['description'] ?: ($item['variant_name'] ?? 'line item')));
    }

    /** @return array<string, mixed> */
    public function findPo(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT po.*, u.first_name, u.last_name
             FROM purchase_orders po
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
            'SELECT poi.*,
                    v.name AS vehicle_name, vv.name AS variant_name, vv.sku, vv.battery_type,
                    sp.name AS spare_part_name, sp.part_number AS spare_part_number,
                    sc.name AS spare_category_name
             FROM purchase_order_items poi
             LEFT JOIN vehicles v ON v.id = poi.vehicle_id
             LEFT JOIN vehicle_variants vv ON vv.id = poi.variant_id
             LEFT JOIN spare_parts sp ON sp.id = poi.spare_part_id
             LEFT JOIN spare_categories sc ON sc.id = sp.category_id
             WHERE poi.purchase_order_id = ?
             ORDER BY poi.sort_order, poi.id'
        );
        $stmt->execute([$poId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
