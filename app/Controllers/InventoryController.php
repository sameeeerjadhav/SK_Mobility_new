<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Services\InventoryService;
use RuntimeException;

class InventoryController extends Controller
{
    public function index(): void
    {
        require_permission('view_inventory');
        $warehouseId = (int)($this->input('warehouse_id') ?: 0);
        $vehicleId = (int)($this->input('vehicle_id') ?: 0);
        $warehouses = $this->db()->query('SELECT * FROM warehouses WHERE is_active = 1 ORDER BY name')->fetchAll();
        if (!$warehouseId && $warehouses) {
            $warehouseId = (int)$warehouses[0]['id'];
        }

        $filterVehicle = null;
        if ($vehicleId > 0) {
            $fv = $this->db()->prepare('SELECT id, name FROM vehicles WHERE id = ?');
            $fv->execute([$vehicleId]);
            $filterVehicle = $fv->fetch() ?: null;
        }

        $stock = [];
        if ($warehouseId) {
            $sql = "SELECT i.*, v.name AS vehicle_name, vv.name AS variant_name, vv.sku, vv.color
                 FROM inventory i
                 JOIN vehicles v ON v.id = i.vehicle_id
                 JOIN vehicle_variants vv ON vv.id = i.variant_id
                 WHERE i.warehouse_id = ?";
            $params = [$warehouseId];
            if ($vehicleId > 0) {
                $sql .= ' AND i.vehicle_id = ?';
                $params[] = $vehicleId;
            }
            $sql .= ' ORDER BY v.name, vv.name';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);
            $stock = $stmt->fetchAll();
        }

        $variants = $this->db()->query(
            "SELECT vv.id, vv.name, vv.sku, v.name AS vehicle_name, v.id AS vehicle_id
             FROM vehicle_variants vv JOIN vehicles v ON v.id = vv.vehicle_id
             WHERE vv.is_active = 1 AND v.is_active = 1 ORDER BY v.name, vv.name"
        )->fetchAll();

        $this->view('inventory/index', [
            'title' => 'Inventory',
            'warehouses' => $warehouses,
            'warehouseId' => $warehouseId,
            'vehicleId' => $vehicleId,
            'filterVehicle' => $filterVehicle,
            'stock' => $stock,
            'variants' => $variants,
            'canManage' => can('manage_inventory'),
        ]);
    }

    public function adjust(): void
    {
        require_permission('manage_inventory');
        $this->validateCsrf();

        $variantId = (int)$this->input('variant_id');
        $warehouseId = (int)$this->input('warehouse_id');
        $qty = (int)$this->input('quantity');
        $notes = $this->input('notes');

        if ($variantId <= 0 || $warehouseId <= 0 || $qty === 0) {
            flash('error', 'Variant, warehouse and non-zero quantity are required.');
            $this->redirect('/inventory?warehouse_id=' . $warehouseId);
        }

        $vStmt = $this->db()->prepare('SELECT vehicle_id FROM vehicle_variants WHERE id = ?');
        $vStmt->execute([$variantId]);
        $vehicleId = (int)$vStmt->fetchColumn();
        if (!$vehicleId) {
            flash('error', 'Invalid variant.');
            $this->redirect('/inventory');
        }

        $row = $this->db()->prepare('SELECT * FROM inventory WHERE variant_id = ? AND warehouse_id = ?');
        $row->execute([$variantId, $warehouseId]);
        $inv = $row->fetch();

        if ($inv) {
            $newQty = (int)$inv['quantity_available'] + $qty;
            if ($newQty < 0) {
                flash('error', 'Insufficient stock.');
                $this->redirect('/inventory?warehouse_id=' . $warehouseId);
            }
            $this->db()->prepare('UPDATE inventory SET quantity_available = ? WHERE id = ?')
                ->execute([$newQty, $inv['id']]);
        } else {
            if ($qty < 0) {
                flash('error', 'Cannot reduce stock that does not exist.');
                $this->redirect('/inventory?warehouse_id=' . $warehouseId);
            }
            $this->db()->prepare(
                'INSERT INTO inventory (vehicle_id, variant_id, warehouse_id, quantity_available) VALUES (?,?,?,?)'
            )->execute([$vehicleId, $variantId, $warehouseId, $qty]);
        }

        $this->db()->prepare(
            'INSERT INTO inventory_movements (variant_id, warehouse_id, movement_type, quantity, notes, created_by)
             VALUES (?,?,?,?,?,?)'
        )->execute([
            $variantId, $warehouseId, 'adjustment', abs($qty),
            ($qty > 0 ? '+' : '-') . abs($qty) . ($notes ? ': ' . $notes : ''),
            Auth::id(),
        ]);

        Audit::log('update', 'inventory', 'inventory', $variantId, null, compact('warehouseId', 'qty'));
        flash('success', 'Stock adjusted.');
        $this->redirect('/inventory?warehouse_id=' . $warehouseId);
    }

    public function transfer(): void
    {
        require_permission('manage_inventory');
        $this->validateCsrf();

        $variantId = (int)$this->input('variant_id');
        $fromId = (int)$this->input('from_warehouse_id');
        $toId = (int)$this->input('to_warehouse_id');
        $qty = abs((int)$this->input('quantity'));
        $notes = $this->input('notes');

        if ($variantId <= 0 || $fromId <= 0 || $toId <= 0 || $fromId === $toId || $qty <= 0) {
            flash('error', 'Invalid transfer details.');
            $this->redirect('/inventory?warehouse_id=' . $fromId);
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            $from = $db->prepare('SELECT * FROM inventory WHERE variant_id = ? AND warehouse_id = ?');
            $from->execute([$variantId, $fromId]);
            $fromRow = $from->fetch();
            if (!$fromRow || (int)$fromRow['quantity_available'] < $qty) {
                throw new \RuntimeException('Insufficient stock at source warehouse.');
            }

            $db->prepare('UPDATE inventory SET quantity_available = quantity_available - ? WHERE id = ?')
                ->execute([$qty, $fromRow['id']]);

            $to = $db->prepare('SELECT * FROM inventory WHERE variant_id = ? AND warehouse_id = ?');
            $to->execute([$variantId, $toId]);
            $toRow = $to->fetch();
            if ($toRow) {
                $db->prepare('UPDATE inventory SET quantity_available = quantity_available + ? WHERE id = ?')
                    ->execute([$qty, $toRow['id']]);
            } else {
                $db->prepare(
                    'INSERT INTO inventory (vehicle_id, variant_id, warehouse_id, quantity_available) VALUES (?,?,?,?)'
                )->execute([(int)$fromRow['vehicle_id'], $variantId, $toId, $qty]);
            }

            $db->prepare(
                'INSERT INTO inventory_movements (variant_id, warehouse_id, movement_type, quantity, notes, created_by)
                 VALUES (?,?,?,?,?,?)'
            )->execute([$variantId, $fromId, 'transfer_out', $qty, $notes, Auth::id()]);
            $db->prepare(
                'INSERT INTO inventory_movements (variant_id, warehouse_id, movement_type, quantity, notes, created_by)
                 VALUES (?,?,?,?,?,?)'
            )->execute([$variantId, $toId, 'transfer_in', $qty, $notes, Auth::id()]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            flash('error', $e->getMessage());
            $this->redirect('/inventory?warehouse_id=' . $fromId);
        }

        Audit::log('update', 'inventory', 'inventory', $variantId, null, ['from' => $fromId, 'to' => $toId, 'qty' => $qty]);
        flash('success', 'Stock transferred.');
        $this->redirect('/inventory?warehouse_id=' . $toId);
    }

    public function splitVariant(): void
    {
        require_permission('manage_inventory');
        $this->validateCsrf();

        $variantId = (int)$this->input('variant_id');
        $warehouseId = (int)$this->input('warehouse_id');
        $notes = trim((string)$this->input('notes')) ?: null;

        // Must read $_POST directly — Controller::input() casts to string and breaks arrays.
        $splits = [];
        $raw = $_POST['splits'] ?? [];
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $splits[] = [
                    'color' => trim((string)($row['color'] ?? '')),
                    'quantity' => (int)($row['quantity'] ?? 0),
                ];
            }
        }

        try {
            (new InventoryService($this->db()))->splitVariantByColor($variantId, $warehouseId, $splits, $notes);
            Audit::log('update', 'inventory', 'inventory', $variantId, null, [
                'action' => 'split_by_color',
                'warehouse_id' => $warehouseId,
                'splits' => $splits,
            ]);
            flash('success', 'Stock split by color. New color variants appear under Vehicles and in this warehouse.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }

        $vehicleId = (int)$this->input('vehicle_id');
        $redirect = '/inventory?warehouse_id=' . $warehouseId;
        if ($vehicleId > 0) {
            $redirect .= '&vehicle_id=' . $vehicleId;
        }
        $this->redirect($redirect);
    }

    public function storeWarehouse(): void
    {
        require_permission('manage_inventory');
        $this->validateCsrf();
        $this->db()->prepare(
            'INSERT INTO warehouses (name, location, address, manager_name, phone, is_active) VALUES (?,?,?,?,?,1)'
        )->execute([
            $this->input('name'),
            $this->input('location'),
            $this->input('address'),
            $this->input('manager_name'),
            trim((string)$this->input('phone')) !== '' ? format_phone($this->input('phone')) : null,
        ]);
        Audit::log('create', 'inventory', 'warehouses', (int)$this->db()->lastInsertId());
        flash('success', 'Warehouse created.');
        $this->redirect('/inventory');
    }

    public function updateWarehouse(string $id): void
    {
        require_permission('manage_inventory');
        $this->validateCsrf();
        $this->db()->prepare(
            'UPDATE warehouses SET name=?, location=?, address=?, manager_name=?, phone=?, is_active=? WHERE id=?'
        )->execute([
            $this->input('name'),
            $this->input('location'),
            $this->input('address'),
            $this->input('manager_name'),
            trim((string)$this->input('phone')) !== '' ? format_phone($this->input('phone')) : null,
            (int)$this->input('is_active'),
            (int)$id,
        ]);
        Audit::log('update', 'inventory', 'warehouses', (int)$id);
        flash('success', 'Warehouse updated.');
        $this->redirect('/inventory?warehouse_id=' . (int)$id);
    }

    public function deleteWarehouse(string $id): void
    {
        require_permission('manage_inventory');
        $this->validateCsrf();
        $this->db()->prepare('UPDATE warehouses SET is_active = 0 WHERE id = ?')->execute([(int)$id]);
        Audit::log('delete', 'inventory', 'warehouses', (int)$id);
        flash('success', 'Warehouse deactivated.');
        $this->redirect('/inventory');
    }
}
