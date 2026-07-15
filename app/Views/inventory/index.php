<div x-data="{ adjustOpen: false, transferOpen: false }">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Inventory</h1>
      <p class="page-sub">Stock levels by warehouse</p>
    </div>
    <?php if ($canManage): ?>
      <div style="display:flex;gap:0.5rem;">
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
  <?php endif; ?>
</div>
