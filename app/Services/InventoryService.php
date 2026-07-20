<?php

namespace App\Services;

use App\Core\Auth;
use PDO;
use RuntimeException;

class InventoryService
{
    public function __construct(private PDO $db)
    {
    }

    /** @param list<array{color: string, quantity: int}> $splits */
    public function splitVariantByColor(int $sourceVariantId, int $warehouseId, array $splits, ?string $notes = null): void
    {
        if (!$splits) {
            throw new RuntimeException('Add at least one color with quantity.');
        }

        $variantStmt = $this->db->prepare('SELECT * FROM vehicle_variants WHERE id = ? AND is_active = 1');
        $variantStmt->execute([$sourceVariantId]);
        $sourceVariant = $variantStmt->fetch(PDO::FETCH_ASSOC);
        if (!$sourceVariant) {
            throw new RuntimeException('Invalid variant.');
        }

        $invStmt = $this->db->prepare('SELECT * FROM inventory WHERE variant_id = ? AND warehouse_id = ?');
        $invStmt->execute([$sourceVariantId, $warehouseId]);
        $sourceInv = $invStmt->fetch(PDO::FETCH_ASSOC);
        if (!$sourceInv) {
            throw new RuntimeException('No stock row found for this variant in the selected warehouse.');
        }

        $available = (int)$sourceInv['quantity_available'];
        if ($available <= 0) {
            throw new RuntimeException('No available stock to split.');
        }

        $normalized = [];
        $totalSplit = 0;
        foreach ($splits as $i => $row) {
            $color = trim((string)($row['color'] ?? ''));
            $qty = (int)($row['quantity'] ?? 0);
            if ($color === '' || $qty <= 0) {
                continue;
            }
            $lineNo = $i + 1;
            foreach ($normalized as $existing) {
                if (strcasecmp($existing['color'], $color) === 0) {
                    throw new RuntimeException("Line {$lineNo}: duplicate color \"{$color}\" in split.");
                }
            }
            $totalSplit += $qty;
            $normalized[] = ['color' => $color, 'quantity' => $qty];
        }

        if (!$normalized) {
            throw new RuntimeException('Enter color and quantity for at least one split line.');
        }
        if ($totalSplit > $available) {
            throw new RuntimeException("Split total ({$totalSplit}) exceeds available stock ({$available}).");
        }

        $catalog = new VariantCatalogService($this->db);
        $unitRate = (float)$sourceVariant['price'] / 1.05;
        if ($unitRate <= 0) {
            $unitRate = (float)$sourceVariant['price'];
        }
        $noteBase = $notes ? trim($notes) : 'Split by color from variant #' . $sourceVariantId;

        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE inventory SET quantity_available = quantity_available - ? WHERE id = ?')
                ->execute([$totalSplit, $sourceInv['id']]);

            $this->db->prepare(
                'INSERT INTO inventory_movements (variant_id, warehouse_id, movement_type, quantity, notes, created_by)
                 VALUES (?,?,?,?,?,?)'
            )->execute([
                $sourceVariantId,
                $warehouseId,
                'adjustment',
                $totalSplit,
                'Split out ' . $totalSplit . ' unit(s): ' . $noteBase,
                Auth::id(),
            ]);

            foreach ($normalized as $split) {
                $resolved = $catalog->findOrCreateVariant(
                    (int)$sourceVariant['vehicle_id'],
                    (string)$sourceVariant['name'],
                    $split['color'],
                    $unitRate,
                    $sourceVariant['battery_type'],
                    $sourceVariant['battery_spec'],
                    $sourceVariant['range_km']
                );
                $targetVariantId = $resolved['variant_id'];
                $qty = $split['quantity'];

                $targetInv = $this->db->prepare(
                    'SELECT id FROM inventory WHERE variant_id = ? AND warehouse_id = ?'
                );
                $targetInv->execute([$targetVariantId, $warehouseId]);
                $targetInvId = $targetInv->fetchColumn();
                if ($targetInvId) {
                    $this->db->prepare(
                        'UPDATE inventory SET quantity_available = quantity_available + ? WHERE id = ?'
                    )->execute([$qty, $targetInvId]);
                } else {
                    $this->db->prepare(
                        'INSERT INTO inventory (vehicle_id, variant_id, warehouse_id, quantity_available) VALUES (?,?,?,?)'
                    )->execute([
                        (int)$sourceVariant['vehicle_id'],
                        $targetVariantId,
                        $warehouseId,
                        $qty,
                    ]);
                }

                if ($targetVariantId !== $sourceVariantId) {
                    $this->db->prepare(
                        'INSERT INTO inventory_movements (variant_id, warehouse_id, movement_type, quantity, notes, created_by)
                         VALUES (?,?,?,?,?,?)'
                    )->execute([
                        $targetVariantId,
                        $warehouseId,
                        'adjustment',
                        $qty,
                        'Split in ' . $qty . ' (' . $split['color'] . '): ' . $noteBase,
                        Auth::id(),
                    ]);
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
