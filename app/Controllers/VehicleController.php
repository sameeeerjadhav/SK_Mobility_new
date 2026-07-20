<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Upload;

class VehicleController extends Controller
{
    public function index(): void
    {
        require_permission('view_vehicles');
        $search = $this->input('search');
        $categoryId = $this->input('category_id');

        $where = ['v.is_active = 1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(v.name LIKE ? OR v.brand LIKE ?)';
            $q = '%' . $search . '%';
            $params[] = $q;
            $params[] = $q;
        }
        if ($categoryId !== '') {
            $where[] = 'v.category_id = ?';
            $params[] = (int)$categoryId;
        }
        $sqlWhere = implode(' AND ', $where);

        $stmt = $this->db()->prepare(
            "SELECT v.*, c.name AS category_name,
                (SELECT vi.image_url FROM vehicle_images vi
                  WHERE vi.vehicle_id = v.id
                  ORDER BY vi.is_primary DESC, (vi.variant_id IS NULL) DESC, vi.id ASC
                  LIMIT 1) AS image_url,
                (SELECT COUNT(*) FROM vehicle_variants vv WHERE vv.vehicle_id = v.id AND vv.is_active = 1) AS variant_count,
                (SELECT MIN(price) FROM vehicle_variants vv WHERE vv.vehicle_id = v.id AND vv.is_active = 1) AS min_price
             FROM vehicles v
             JOIN vehicle_categories c ON c.id = v.category_id
             WHERE {$sqlWhere}
             ORDER BY v.created_at DESC"
        );
        $stmt->execute($params);
        $vehicles = $stmt->fetchAll();
        $categories = $this->db()->query('SELECT * FROM vehicle_categories WHERE is_active = 1 ORDER BY sort_order')->fetchAll();

        $this->view('vehicles/index', [
            'title' => 'Vehicles',
            'vehicles' => $vehicles,
            'categories' => $categories,
            'search' => $search,
            'categoryId' => $categoryId,
            'canManage' => can('manage_vehicles'),
        ]);
    }

    public function show(string $id): void
    {
        require_permission('view_vehicles');
        $vehicleId = (int)$id;
        $stmt = $this->db()->prepare(
            'SELECT v.*, c.name AS category_name FROM vehicles v
             JOIN vehicle_categories c ON c.id = v.category_id WHERE v.id = ?'
        );
        $stmt->execute([$vehicleId]);
        $vehicle = $stmt->fetch();
        if (!$vehicle) {
            flash('error', 'Vehicle not found.');
            $this->redirect('/vehicles');
        }

        $variants = $this->db()->prepare('SELECT * FROM vehicle_variants WHERE vehicle_id = ? ORDER BY id DESC');
        $variants->execute([$vehicleId]);
        $variants = $variants->fetchAll();

        $images = $this->db()->prepare(
            'SELECT * FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_primary DESC, sort_order, id'
        );
        $images->execute([$vehicleId]);
        $allImages = $images->fetchAll();

        $vehicleImages = [];
        $imagesByVariant = [];
        foreach ($allImages as $img) {
            $vid = $img['variant_id'] !== null && $img['variant_id'] !== '' ? (int)$img['variant_id'] : null;
            if ($vid) {
                $imagesByVariant[$vid][] = $img;
            } else {
                $vehicleImages[] = $img;
            }
        }

        $coverStmt = $this->db()->prepare(
            'SELECT image_url FROM vehicle_images WHERE vehicle_id = ?
             ORDER BY is_primary DESC, (variant_id IS NULL) DESC, id ASC LIMIT 1'
        );
        $coverStmt->execute([$vehicleId]);
        $coverImage = $coverStmt->fetchColumn() ?: null;

        $activeVariants = array_values(array_filter($variants, static fn ($vv) => (int)$vv['is_active'] === 1));
        $minPrice = null;
        foreach ($activeVariants as $vv) {
            $p = (float)$vv['price'];
            if ($minPrice === null || $p < $minPrice) {
                $minPrice = $p;
            }
        }

        $stockRows = $this->db()->prepare(
            'SELECT variant_id,
                    SUM(quantity_available) AS available,
                    SUM(quantity_reserved) AS reserved
             FROM inventory WHERE vehicle_id = ?
             GROUP BY variant_id'
        );
        $stockRows->execute([$vehicleId]);
        $stockByVariant = [];
        $totalStock = 0;
        foreach ($stockRows->fetchAll() as $row) {
            $stockByVariant[(int)$row['variant_id']] = $row;
            $totalStock += (int)$row['available'];
        }

        $pendingPo = $this->db()->prepare(
            'SELECT poi.variant_id,
                    SUM(GREATEST(poi.quantity_ordered - poi.quantity_received, 0)) AS pending
             FROM purchase_order_items poi
             JOIN purchase_orders po ON po.id = poi.purchase_order_id
             WHERE poi.vehicle_id = ? AND po.status NOT IN (\'cancelled\', \'received\')
             GROUP BY poi.variant_id'
        );
        $pendingPo->execute([$vehicleId]);
        $pendingPoByVariant = [];
        $totalPendingPo = 0;
        foreach ($pendingPo->fetchAll() as $row) {
            $pendingPoByVariant[(int)$row['variant_id']] = (int)$row['pending'];
            $totalPendingPo += (int)$row['pending'];
        }

        $recentPos = $this->db()->prepare(
            'SELECT po.id, po.po_number, po.po_date, po.status, po.total_amount,
                    SUM(poi.quantity_ordered) AS qty_ordered,
                    SUM(poi.quantity_received) AS qty_received
             FROM purchase_orders po
             JOIN purchase_order_items poi ON poi.purchase_order_id = po.id
             WHERE poi.vehicle_id = ?
             GROUP BY po.id
             ORDER BY po.po_date DESC, po.id DESC
             LIMIT 8'
        );
        $recentPos->execute([$vehicleId]);
        $recentPos = $recentPos->fetchAll();

        $this->view('vehicles/show', [
            'title' => $vehicle['name'],
            'vehicle' => $vehicle,
            'variants' => $variants,
            'images' => $vehicleImages,
            'imagesByVariant' => $imagesByVariant,
            'coverImage' => $coverImage,
            'minPrice' => $minPrice ?? (float)$vehicle['base_price'],
            'variantCount' => count($variants),
            'stockByVariant' => $stockByVariant,
            'pendingPoByVariant' => $pendingPoByVariant,
            'totalStock' => $totalStock,
            'totalPendingPo' => $totalPendingPo,
            'recentPos' => $recentPos,
            'canManage' => can('manage_vehicles'),
            'categories' => $this->db()->query('SELECT * FROM vehicle_categories WHERE is_active = 1')->fetchAll(),
        ]);
    }

    public function store(): void
    {
        require_permission('manage_vehicles');
        $this->validateCsrf();
        $name = $this->input('name');
        $slug = slugify($name);
        $check = $this->db()->prepare('SELECT id FROM vehicles WHERE slug = ?');
        $check->execute([$slug]);
        if ($check->fetch()) {
            $slug .= '-' . time();
        }

        $this->db()->prepare(
            'INSERT INTO vehicles (category_id, name, slug, brand, description, base_price, is_active)
             VALUES (?,?,?,?,?,?,1)'
        )->execute([
            (int)$this->input('category_id'),
            $name,
            $slug,
            $this->input('brand') ?: 'SK Mobility',
            $this->input('description'),
            (float)$this->input('base_price'),
        ]);
        $id = (int)$this->db()->lastInsertId();
        Audit::log('create', 'vehicles', 'vehicles', $id);
        flash('success', 'Vehicle created.');
        $this->redirect('/vehicles/' . $id);
    }

    public function update(string $id): void
    {
        require_permission('manage_vehicles');
        $this->validateCsrf();
        $vehicleId = (int)$id;
        $this->db()->prepare(
            'UPDATE vehicles SET category_id=?, name=?, brand=?, description=?, base_price=? WHERE id=?'
        )->execute([
            (int)$this->input('category_id'),
            $this->input('name'),
            $this->input('brand'),
            $this->input('description'),
            (float)$this->input('base_price'),
            $vehicleId,
        ]);
        Audit::log('update', 'vehicles', 'vehicles', $vehicleId);
        flash('success', 'Vehicle updated.');
        $this->redirect('/vehicles/' . $vehicleId);
    }

    public function destroy(string $id): void
    {
        require_permission('manage_vehicles');
        $this->validateCsrf();
        $vehicleId = (int)$id;

        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM order_items WHERE vehicle_id = ?');
        $stmt->execute([$vehicleId]);
        $used = (int)$stmt->fetchColumn();
        if ($used > 0) {
            flash('error', 'Cannot delete: this vehicle is used in ' . $used . ' order line(s).');
            $this->redirect('/vehicles/' . $vehicleId);
        }

        $vids = $this->db()->prepare('SELECT id FROM vehicle_variants WHERE vehicle_id = ?');
        $vids->execute([$vehicleId]);
        $variantIds = array_map('intval', array_column($vids->fetchAll(), 'id'));
        if ($variantIds) {
            $in = implode(',', array_fill(0, count($variantIds), '?'));
            $this->db()->prepare("DELETE FROM inventory_movements WHERE variant_id IN ({$in})")->execute($variantIds);
            $this->db()->prepare("DELETE FROM inventory WHERE variant_id IN ({$in})")->execute($variantIds);
        }
        $this->db()->prepare('DELETE FROM inventory WHERE vehicle_id = ?')->execute([$vehicleId]);
        $this->db()->prepare('UPDATE leads SET interested_vehicle_id = NULL WHERE interested_vehicle_id = ?')
            ->execute([$vehicleId]);
        $this->db()->prepare('DELETE FROM vehicle_images WHERE vehicle_id = ?')->execute([$vehicleId]);
        $this->db()->prepare('DELETE FROM vehicle_variants WHERE vehicle_id = ?')->execute([$vehicleId]);
        $this->db()->prepare('DELETE FROM vehicles WHERE id = ?')->execute([$vehicleId]);

        Audit::log('delete', 'vehicles', 'vehicles', $vehicleId);
        flash('success', 'Vehicle deleted.');
        $this->redirect('/vehicles');
    }

    public function addVariant(string $id): void
    {
        require_permission('manage_vehicles');
        $this->validateCsrf();
        $vehicleId = (int)$id;
        $sku = $this->input('sku') ?: strtoupper(substr(slugify($this->input('name')), 0, 8)) . '-' . random_int(100, 999);

        $batteryType = $this->validBatteryType($this->input('battery_type'));
        $batterySpec = $this->validBatterySpec($batteryType, $this->input('battery_spec'));

        $this->db()->prepare(
            'INSERT INTO vehicle_variants (vehicle_id, name, sku, color, price, battery_type, battery_spec, range_km, is_active)
             VALUES (?,?,?,?,?,?,?,?,1)'
        )->execute([
            $vehicleId,
            $this->input('name'),
            $sku,
            $this->input('color'),
            (float)$this->input('price'),
            $batteryType,
            $batterySpec,
            $this->input('range_km') !== '' ? (int)$this->input('range_km') : null,
        ]);
        Audit::log('create', 'vehicles', 'vehicle_variants', (int)$this->db()->lastInsertId());
        flash('success', 'Variant added.');
        $this->redirect('/vehicles/' . $vehicleId);
    }

    public function updateVariant(string $id, string $vid): void
    {
        require_permission('manage_vehicles');
        $this->validateCsrf();
        $vehicleId = (int)$id;
        $variantId = (int)$vid;

        $check = $this->db()->prepare('SELECT id FROM vehicle_variants WHERE id = ? AND vehicle_id = ?');
        $check->execute([$variantId, $vehicleId]);
        if (!$check->fetch()) {
            flash('error', 'Variant not found.');
            $this->redirect('/vehicles/' . $vehicleId);
        }

        $sku = $this->input('sku');
        if ($sku === '') {
            $sku = strtoupper(substr(slugify($this->input('name')), 0, 8)) . '-' . random_int(100, 999);
        }

        $batteryType = $this->validBatteryType($this->input('battery_type'));
        $batterySpec = $this->validBatterySpec($batteryType, $this->input('battery_spec'));

        $this->db()->prepare(
            'UPDATE vehicle_variants SET name=?, sku=?, color=?, price=?, battery_type=?, battery_spec=?, range_km=?, is_active=?
             WHERE id=? AND vehicle_id=?'
        )->execute([
            $this->input('name'),
            $sku,
            $this->input('color'),
            (float)$this->input('price'),
            $batteryType,
            $batterySpec,
            $this->input('range_km') !== '' ? (int)$this->input('range_km') : null,
            (int)$this->input('is_active', '1'),
            $variantId,
            $vehicleId,
        ]);
        Audit::log('update', 'vehicles', 'vehicle_variants', $variantId);
        flash('success', 'Variant updated.');
        $this->redirect('/vehicles/' . $vehicleId);
    }

    public function destroyVariant(string $id, string $vid): void
    {
        require_permission('manage_vehicles');
        $this->validateCsrf();
        $vehicleId = (int)$id;
        $variantId = (int)$vid;

        $check = $this->db()->prepare('SELECT id FROM vehicle_variants WHERE id = ? AND vehicle_id = ?');
        $check->execute([$variantId, $vehicleId]);
        if (!$check->fetch()) {
            flash('error', 'Variant not found.');
            $this->redirect('/vehicles/' . $vehicleId);
        }

        $used = $this->db()->prepare('SELECT COUNT(*) FROM order_items WHERE variant_id = ?');
        $used->execute([$variantId]);
        if ((int)$used->fetchColumn() > 0) {
            flash('error', 'Cannot delete: this variant is used in existing orders.');
            $this->redirect('/vehicles/' . $vehicleId);
        }

        $this->db()->prepare('DELETE FROM inventory_movements WHERE variant_id = ?')->execute([$variantId]);
        $this->db()->prepare('DELETE FROM inventory WHERE variant_id = ?')->execute([$variantId]);
        $this->db()->prepare('DELETE FROM vehicle_images WHERE variant_id = ?')->execute([$variantId]);
        $this->db()->prepare('DELETE FROM vehicle_variants WHERE id = ? AND vehicle_id = ?')->execute([$variantId, $vehicleId]);

        Audit::log('delete', 'vehicles', 'vehicle_variants', $variantId);
        flash('success', 'Variant deleted.');
        $this->redirect('/vehicles/' . $vehicleId);
    }

    public function uploadImage(string $id): void
    {
        require_permission('manage_vehicles');
        $this->validateCsrf();
        $vehicleId = (int)$id;
        $variantId = (int)$this->input('variant_id');
        if ($variantId > 0) {
            $check = $this->db()->prepare('SELECT id FROM vehicle_variants WHERE id = ? AND vehicle_id = ?');
            $check->execute([$variantId, $vehicleId]);
            if (!$check->fetch()) {
                flash('error', 'Invalid variant.');
                $this->redirect('/vehicles/' . $vehicleId);
            }
        } else {
            $variantId = null;
        }

        $path = Upload::store($_FILES['image'] ?? [], 'vehicles', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        if (!$path) {
            flash('error', 'Image upload failed. Use JPG, PNG, GIF, or WebP.');
            $this->redirect('/vehicles/' . $vehicleId);
        }

        $isPrimary = isset($_POST['is_primary']) ? 1 : 0;

        // Auto-primary so catalog cards always get a cover image when none exist yet
        if (!$isPrimary) {
            if ($variantId) {
                $cnt = $this->db()->prepare('SELECT COUNT(*) FROM vehicle_images WHERE vehicle_id = ? AND variant_id = ?');
                $cnt->execute([$vehicleId, $variantId]);
            } else {
                $cnt = $this->db()->prepare('SELECT COUNT(*) FROM vehicle_images WHERE vehicle_id = ? AND variant_id IS NULL');
                $cnt->execute([$vehicleId]);
            }
            if ((int)$cnt->fetchColumn() === 0) {
                $isPrimary = 1;
            }
        }

        // If this is the first image on the whole vehicle, mark it for catalog cover
        $any = $this->db()->prepare('SELECT COUNT(*) FROM vehicle_images WHERE vehicle_id = ?');
        $any->execute([$vehicleId]);
        $isFirstOnVehicle = (int)$any->fetchColumn() === 0;
        if ($isFirstOnVehicle) {
            $isPrimary = 1;
        }

        if ($isPrimary) {
            if ($variantId) {
                $this->db()->prepare(
                    'UPDATE vehicle_images SET is_primary = 0 WHERE vehicle_id = ? AND variant_id = ?'
                )->execute([$vehicleId, $variantId]);
            } else {
                $this->db()->prepare(
                    'UPDATE vehicle_images SET is_primary = 0 WHERE vehicle_id = ? AND variant_id IS NULL'
                )->execute([$vehicleId]);
            }
            // Also clear other catalog primaries so one clear cover exists
            if ($isFirstOnVehicle || !$variantId) {
                $this->db()->prepare(
                    'UPDATE vehicle_images SET is_primary = 0 WHERE vehicle_id = ?'
                )->execute([$vehicleId]);
            }
        }

        $this->db()->prepare(
            'INSERT INTO vehicle_images (vehicle_id, variant_id, image_url, is_primary) VALUES (?,?,?,?)'
        )->execute([$vehicleId, $variantId, $path, $isPrimary]);

        Audit::log('create', 'vehicles', 'vehicle_images', (int)$this->db()->lastInsertId());
        flash('success', $variantId ? 'Variant image uploaded.' : 'Vehicle image uploaded — it will show on the catalog card.');
        $this->redirect('/vehicles/' . $vehicleId);
    }

    public function destroyImage(string $id, string $imageId): void
    {
        require_permission('manage_vehicles');
        $this->validateCsrf();
        $vehicleId = (int)$id;
        $imgId = (int)$imageId;

        $stmt = $this->db()->prepare('SELECT * FROM vehicle_images WHERE id = ? AND vehicle_id = ?');
        $stmt->execute([$imgId, $vehicleId]);
        $img = $stmt->fetch();
        if (!$img) {
            flash('error', 'Image not found.');
            $this->redirect('/vehicles/' . $vehicleId);
        }

        $this->db()->prepare('DELETE FROM vehicle_images WHERE id = ?')->execute([$imgId]);
        Audit::log('delete', 'vehicles', 'vehicle_images', $imgId);
        flash('success', 'Image deleted.');
        $this->redirect('/vehicles/' . $vehicleId);
    }

    private function validBatteryType(?string $type): ?string
    {
        $allowed = ['Lithium Ion', 'Lead Acid'];
        return in_array($type, $allowed, true) ? $type : null;
    }

    /** @return array<string, list<string>> */
    public static function batterySpecOptions(): array
    {
        return [
            'Lithium Ion' => [
                '60V / 30AH',
                '72V / 30AH',
                '60VH / 40AH',
            ],
            'Lead Acid' => [
                '48V / 32AH',
                '60V / 32AH',
                '72V / 32AH (7.0kg)',
                '72V / 45AH (9.0kg)',
            ],
        ];
    }

    private function validBatterySpec(?string $batteryType, ?string $spec): ?string
    {
        if ($batteryType === null || $spec === null || $spec === '') {
            return null;
        }
        $options = self::batterySpecOptions()[$batteryType] ?? [];
        return in_array($spec, $options, true) ? $spec : null;
    }
}
