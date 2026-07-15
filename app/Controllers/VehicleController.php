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
                (SELECT image_url FROM vehicle_images vi WHERE vi.vehicle_id = v.id AND vi.is_primary = 1 LIMIT 1) AS image_url,
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
        $images = $this->db()->prepare('SELECT * FROM vehicle_images WHERE vehicle_id = ? ORDER BY is_primary DESC, sort_order');
        $images->execute([$vehicleId]);

        $this->view('vehicles/show', [
            'title' => $vehicle['name'],
            'vehicle' => $vehicle,
            'variants' => $variants->fetchAll(),
            'images' => $images->fetchAll(),
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

        $this->db()->prepare(
            'INSERT INTO vehicle_variants (vehicle_id, name, sku, color, price, battery_capacity_kwh, range_km, is_active)
             VALUES (?,?,?,?,?,?,?,1)'
        )->execute([
            $vehicleId,
            $this->input('name'),
            $sku,
            $this->input('color'),
            (float)$this->input('price'),
            $this->input('battery_capacity_kwh') !== '' ? (float)$this->input('battery_capacity_kwh') : null,
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

        $this->db()->prepare(
            'UPDATE vehicle_variants SET name=?, sku=?, color=?, price=?, battery_capacity_kwh=?, range_km=?, is_active=?
             WHERE id=? AND vehicle_id=?'
        )->execute([
            $this->input('name'),
            $sku,
            $this->input('color'),
            (float)$this->input('price'),
            $this->input('battery_capacity_kwh') !== '' ? (float)$this->input('battery_capacity_kwh') : null,
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
        $this->db()->prepare('UPDATE vehicle_images SET variant_id = NULL WHERE variant_id = ?')->execute([$variantId]);
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
        $path = Upload::store($_FILES['image'] ?? [], 'vehicles', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        if (!$path) {
            flash('error', 'Image upload failed.');
            $this->redirect('/vehicles/' . $vehicleId);
        }
        $isPrimary = isset($_POST['is_primary']) ? 1 : 0;
        if ($isPrimary) {
            $this->db()->prepare('UPDATE vehicle_images SET is_primary = 0 WHERE vehicle_id = ?')->execute([$vehicleId]);
        }
        $this->db()->prepare(
            'INSERT INTO vehicle_images (vehicle_id, image_url, is_primary) VALUES (?,?,?)'
        )->execute([$vehicleId, $path, $isPrimary]);
        flash('success', 'Image uploaded.');
        $this->redirect('/vehicles/' . $vehicleId);
    }
}
