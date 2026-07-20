<?php

namespace App\Services;

use PDO;
use RuntimeException;

class VariantCatalogService
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array{variant_id: int, vehicle_id: int, color: string}
     */
    public function findOrCreateVariant(
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
}
