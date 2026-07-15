<div x-data="{ adjustOpen: false, transferOpen: false, whOpen: false, editWh: null }">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Inventory</h1>
      <p class="page-sub">Stock levels by warehouse</p>
    </div>
    <?php if ($canManage): ?>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <button class="btn btn-outline" type="button" @click="whOpen=true">+ Warehouse</button>
        <button class="btn btn-outline" type="button" @click="adjustOpen=true">Adjust Stock</button>
        <button class="btn btn-primary" type="button" @click="transferOpen=true">Transfer Stock</button>
      </div>
    <?php endif; ?>
  </div>

  <div class="tabs">
    <?php foreach ($warehouses as $w): ?>
      <a class="tab <?= (int)$warehouseId === (int)$w['id'] ? 'active' : '' ?>"
         href="<?= url('inventory?warehouse_id=' . $w['id']) ?>"><?= e($w['name']) ?></a>
    <?php endforeach; ?>
  </div>

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
        <thead><tr><th>Vehicle</th><th>Variant</th><th>SKU</th><th>Available</th><th>Reserved</th><th>Min Level</th></tr></thead>
        <tbody>
        <?php foreach ($stock as $s): ?>
          <tr>
            <td><?= e($s['vehicle_name']) ?></td>
            <td><?= e($s['variant_name']) ?> <?= $s['color'] ? '(' . e($s['color']) . ')' : '' ?></td>
            <td><?= e($s['sku']) ?></td>
            <td><strong><?= (int)$s['quantity_available'] ?></strong>
              <?php if ((int)$s['quantity_available'] <= (int)$s['min_stock_level']): ?>
                <span class="chip chip-warning">Low</span>
              <?php endif; ?>
            </td>
            <td><?= (int)$s['quantity_reserved'] ?></td>
            <td><?= (int)$s['min_stock_level'] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$stock): ?><tr><td colspan="6" class="muted">No stock records for this warehouse. Use Adjust Stock to add inventory.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($canManage): ?>
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
                <option value="<?= (int)$v['id'] ?>"><?= e($v['vehicle_name'] . ' — ' . $v['name'] . ' (' . $v['sku'] . ')') ?></option>
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
            <select class="form-control" name="variant_id" required>
              <?php foreach ($variants as $v): ?>
                <option value="<?= (int)$v['id'] ?>"><?= e($v['vehicle_name'] . ' — ' . $v['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-grid">
            <div class="form-group"><label>From warehouse</label>
              <select class="form-control" name="from_warehouse_id" required>
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
          <div class="form-group"><label>Phone</label><input class="form-control" name="phone"></div>
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
          <div class="form-group"><label>Phone</label><input class="form-control" name="phone" :value="editWh?.phone"></div>
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
