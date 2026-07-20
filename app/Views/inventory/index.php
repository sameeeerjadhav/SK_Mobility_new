<?php
$transferStockJson = json_encode(array_map(static function (array $row): array {
    return [
        'variant_id' => (int)$row['variant_id'],
        'warehouse_id' => (int)$row['warehouse_id'],
        'quantity_available' => (int)$row['quantity_available'],
        'label' => variant_option_label($row),
    ];
}, $transferStock ?? []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
?>
<script>
function inventoryPage() {
  return {
    adjustOpen: false,
    transferOpen: false,
    whOpen: false,
    editWh: null,
    splitOpen: false,
    splitRow: null,
    splitLines: [{ color: '', quantity: '' }],
    fromWarehouseId: <?= (int)$warehouseId ?>,
    transferVariantId: '',
    transferStock: <?= $transferStockJson ?>,
    openTransfer() {
      this.fromWarehouseId = <?= (int)$warehouseId ?>;
      this.transferVariantId = '';
      this.transferOpen = true;
    },
    transferOptions() {
      return this.transferStock.filter((row) => Number(row.warehouse_id) === Number(this.fromWarehouseId));
    },
    transferOptionLabel(row) {
      return row.label + ' · ' + row.quantity_available + ' avail';
    },
    openSplit(row) {
      this.splitRow = row;
      this.splitLines = [{ color: '', quantity: '' }, { color: '', quantity: '' }];
      this.splitOpen = true;
    },
    addSplitLine() {
      this.splitLines = [...this.splitLines, { color: '', quantity: '' }];
    },
    removeSplitLine(idx) {
      if (this.splitLines.length <= 1) return;
      this.splitLines = this.splitLines.filter((_, i) => i !== idx);
    },
    splitAllocated() {
      return this.splitLines.reduce((s, r) => s + (parseInt(r.quantity, 10) || 0), 0);
    }
  };
}
</script>
<div x-data="inventoryPage()">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Inventory</h1>
      <p class="page-sub">Stock levels by warehouse · split combined stock into color-wise variants</p>
    </div>
    <?php if ($canManage): ?>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <button class="btn btn-outline" type="button" @click="whOpen=true">+ Warehouse</button>
        <button class="btn btn-outline" type="button" @click="adjustOpen=true">Adjust Stock</button>
        <button class="btn btn-primary" type="button" @click="openTransfer()">Transfer Stock</button>
      </div>
    <?php endif; ?>
  </div>

  <div class="tabs">
    <?php foreach ($warehouses as $w): ?>
      <a class="tab <?= (int)$warehouseId === (int)$w['id'] ? 'active' : '' ?>"
         href="<?= url('inventory?warehouse_id=' . $w['id'] . ($vehicleId ? '&vehicle_id=' . (int)$vehicleId : '')) ?>"><?= e($w['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($filterVehicle): ?>
    <div class="card" style="margin-bottom:1rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
      <div>
        <strong>Filtered:</strong> <?= e($filterVehicle['name']) ?>
        <span class="muted"> · stock in this warehouse</span>
      </div>
      <div style="display:flex;gap:0.5rem;">
        <a class="btn btn-sm btn-outline" href="<?= url('vehicles/' . (int)$filterVehicle['id']) ?>">Vehicle details</a>
        <a class="btn btn-sm btn-outline" href="<?= url('inventory?warehouse_id=' . (int)$warehouseId) ?>">Clear filter</a>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($canManage && $warehouseId): ?>
    <?php
      $currentWh = null;
      foreach ($warehouses as $w) {
          if ((int)$w['id'] === (int)$warehouseId) { $currentWh = $w; break; }
      }
    ?>
    <?php if ($currentWh): ?>
    <div class="card" style="margin-bottom:1rem;display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:center;">
      <div>
        <strong><?= e($currentWh['name']) ?></strong>
        <span class="muted"> · <?= e($currentWh['location'] ?? '') ?> · <?= e($currentWh['manager_name'] ?? '') ?></span>
      </div>
      <div style="display:flex;gap:0.5rem;">
        <button class="btn btn-sm btn-outline" type="button"
          @click='editWh = <?= json_encode($currentWh, JSON_HEX_APOS | JSON_HEX_TAG) ?>'>Edit warehouse</button>
        <form method="post" action="<?= url('inventory/warehouses/' . $currentWh['id'] . '/delete') ?>" onsubmit="return confirm('Deactivate this warehouse?')">
          <?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit">Deactivate</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  <?php endif; ?>
  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Vehicle</th><th>Variant</th><th>SKU</th><th>Color</th><th>Available</th><th>Reserved</th><th>Min Level</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($stock as $s): ?>
          <tr>
            <td><a href="<?= url('vehicles/' . (int)$s['vehicle_id']) ?>"><?= e($s['vehicle_name']) ?></a></td>
            <td><?= e($s['variant_name']) ?></td>
            <td><?= e($s['sku']) ?></td>
            <td><?= e($s['color'] ?: '—') ?></td>
            <td><strong><?= (int)$s['quantity_available'] ?></strong>
              <?php if ((int)$s['quantity_available'] <= (int)$s['min_stock_level']): ?>
                <span class="chip chip-warning">Low</span>
              <?php endif; ?>
            </td>
            <td><?= (int)$s['quantity_reserved'] ?></td>
            <td><?= (int)$s['min_stock_level'] ?></td>
            <?php if ($canManage): ?>
            <td style="white-space:nowrap;">
              <?php if ((int)$s['quantity_available'] > 0): ?>
                <button class="btn btn-sm btn-outline" type="button"
                  @click='openSplit(<?= json_encode([
                    'variant_id' => (int)$s['variant_id'],
                    'vehicle_id' => (int)$s['vehicle_id'],
                    'vehicle_name' => $s['vehicle_name'],
                    'variant_name' => $s['variant_name'],
                    'color' => $s['color'] ?? '',
                    'quantity_available' => (int)$s['quantity_available'],
                    'warehouse_id' => (int)$warehouseId,
                  ], JSON_HEX_APOS | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>)'>
                  Split by color
                </button>
              <?php endif; ?>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        <?php if (!$stock): ?><tr><td colspan="<?= $canManage ? 8 : 7 ?>" class="muted">No stock records for this warehouse. Receive a purchase order or use Adjust Stock to add inventory.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($canManage): ?>
  <div class="modal-backdrop" :class="{ open: splitOpen }" @click.self="splitOpen=false">
    <div class="modal modal-lg">
      <form method="post" action="<?= url('inventory/split-variant') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h3 class="modal-title">Split stock by color</h3>
          <button type="button" class="btn btn-sm btn-outline" @click="splitOpen=false">Close</button>
        </div>
        <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
          <template x-if="splitRow">
            <div>
              <p class="muted" style="margin-top:0;">
                Move units from <strong x-text="splitRow.vehicle_name + ' — ' + splitRow.variant_name"></strong>
                <span x-show="splitRow.color"> (<span x-text="splitRow.color"></span>)</span>
                into separate color variants under the same vehicle. New colors appear on <strong>Vehicles → Variants</strong>.
              </p>
              <p style="margin:0.5rem 0 1rem;">Available in this warehouse: <strong x-text="splitRow.quantity_available"></strong>
                · Allocating: <strong x-text="splitAllocated()"></strong></p>
              <input type="hidden" name="variant_id" :value="splitRow?.variant_id">
              <input type="hidden" name="warehouse_id" :value="splitRow?.warehouse_id">
              <input type="hidden" name="vehicle_id" value="<?= (int)$vehicleId ?>">

              <template x-for="(line, idx) in splitLines" :key="idx">
                <div style="display:flex;gap:0.5rem;align-items:end;margin-bottom:0.5rem;flex-wrap:wrap;">
                  <div class="form-group" style="margin:0;flex:1;min-width:140px;">
                    <label x-show="idx===0">Color *</label>
                    <input class="form-control" type="text" :name="'splits['+idx+'][color]'" x-model="line.color"
                           placeholder="e.g. Red" required>
                  </div>
                  <div class="form-group" style="margin:0;width:100px;">
                    <label x-show="idx===0">Qty *</label>
                    <input class="form-control" type="number" min="1" :name="'splits['+idx+'][quantity]'" x-model="line.quantity" required>
                  </div>
                  <button class="btn btn-sm btn-danger" type="button" @click="removeSplitLine(idx)" x-show="splitLines.length > 1" style="margin-bottom:0.35rem;">×</button>
                </div>
              </template>
              <button class="btn btn-sm btn-outline" type="button" @click="addSplitLine()">+ Add color</button>

              <div class="form-group" style="margin-top:1rem;">
                <label>Notes</label>
                <textarea class="form-control" name="notes" rows="2" placeholder="Optional reason for split"></textarea>
              </div>
            </div>
          </template>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="splitOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Split stock</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{ open: adjustOpen }" @click.self="adjustOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('inventory/adjust') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Adjust Stock</h3><button type="button" class="btn btn-sm btn-outline" @click="adjustOpen=false">Close</button></div>
        <div class="modal-body">
          <input type="hidden" name="warehouse_id" value="<?= (int)$warehouseId ?>">
          <div class="form-group"><label>Variant</label>
            <select class="form-control" name="variant_id" required>
              <option value="">Select</option>
              <?php foreach ($variants as $v): ?>
                <option value="<?= (int)$v['id'] ?>"><?= e(variant_option_label($v)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Quantity (+ add / − remove)</label><input class="form-control" type="number" name="quantity" required></div>
          <div class="form-group"><label>Reason / notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="adjustOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{ open: transferOpen }" @click.self="transferOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('inventory/transfer') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Transfer Stock</h3><button type="button" class="btn btn-sm btn-outline" @click="transferOpen=false">Close</button></div>
        <div class="modal-body">
          <div class="form-group"><label>Variant</label>
            <select class="form-control" name="variant_id" required x-model="transferVariantId">
              <option value="">Select variant with stock</option>
              <template x-for="row in transferOptions()" :key="row.variant_id + '-' + row.warehouse_id">
                <option :value="row.variant_id" x-text="transferOptionLabel(row)"></option>
              </template>
            </select>
            <p class="muted" style="margin:0.35rem 0 0;font-size:0.82rem;" x-show="transferOptions().length === 0" x-cloak>
              No stock available in the selected source warehouse.
            </p>
          </div>
          <div class="form-grid">
            <div class="form-group"><label>From warehouse</label>
              <select class="form-control" name="from_warehouse_id" required x-model.number="fromWarehouseId" @change="transferVariantId=''">
                <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)$warehouseId === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label>To warehouse</label>
              <select class="form-control" name="to_warehouse_id" required>
                <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group"><label>Quantity</label><input class="form-control" type="number" min="1" name="quantity" required></div>
          <div class="form-group"><label>Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="transferOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Transfer</button></div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{ open: whOpen }" @click.self="whOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('inventory/warehouses') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Add Warehouse</h3></div>
        <div class="modal-body form-grid">
          <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
          <div class="form-group"><label>Location</label><input class="form-control" name="location"></div>
          <div class="form-group"><label>Manager</label><input class="form-control" name="manager_name"></div>
          <div class="form-group"><label>Phone</label><input class="form-control contact-input" name="phone" type="tel" maxlength="11" inputmode="numeric" placeholder="98765 43210"></div>
          <div class="form-group full"><label>Address</label><textarea class="form-control" name="address" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="whOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{ open: !!editWh }" @click.self="editWh=null" x-show="editWh" x-cloak>
    <div class="modal" x-show="editWh">
      <form method="post" :action="'<?= url('inventory/warehouses') ?>/'+editWh?.id">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Edit Warehouse</h3></div>
        <div class="modal-body form-grid">
          <div class="form-group"><label>Name</label><input class="form-control" name="name" :value="editWh?.name" required></div>
          <div class="form-group"><label>Location</label><input class="form-control" name="location" :value="editWh?.location"></div>
          <div class="form-group"><label>Manager</label><input class="form-control" name="manager_name" :value="editWh?.manager_name"></div>
          <div class="form-group"><label>Phone</label><input class="form-control contact-input" name="phone" type="tel" maxlength="11" inputmode="numeric" placeholder="98765 43210" :value="editWh?.phone"></div>
          <div class="form-group full"><label>Address</label><textarea class="form-control" name="address" :value="editWh?.address" rows="2"></textarea></div>
          <div class="form-group"><label>Active</label>
            <select class="form-control" name="is_active">
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="editWh=null">Cancel</button><button class="btn btn-primary" type="submit">Update</button></div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
