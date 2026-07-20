<?php
$statusLabels = [
    'draft' => 'Draft',
    'confirmed' => 'Confirmed',
    'partial' => 'Partial',
    'received' => 'Received',
    'cancelled' => 'Cancelled',
];
$statusClass = [
    'draft' => 'chip-muted',
    'confirmed' => 'chip-info',
    'partial' => 'chip-warning',
    'received' => 'chip-success',
    'cancelled' => 'chip-danger',
];
$canReceive = !in_array($po['status'], ['received', 'cancelled'], true);
$pendingItems = array_filter($items, static fn($it) => (int)$it['quantity_ordered'] > (int)$it['quantity_received']);
$itemsJson = json_encode(array_values(array_map(static function ($it) {
    return [
        'id' => (int)$it['id'],
        'label' => trim($it['vehicle_name'] . ' — ' . $it['variant_name'] . ($it['color'] ? ' (' . $it['color'] . ')' : '')),
        'pending' => (int)$it['quantity_ordered'] - (int)$it['quantity_received'],
    ];
}, $pendingItems)), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$warehousesJson = json_encode($warehouses, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
?>
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('poShow', () => ({
    receiveOpen: false,
    poItems: <?= $itemsJson ?>,
    warehouses: <?= $warehousesJson ?>,
    notes: '',
    allocations: [],
    initReceive() {
      this.allocations = this.poItems.map(it => ({
        po_item_id: it.id,
        label: it.label,
        pending: it.pending,
        rows: [{ warehouse_id: this.warehouses[0]?.id || '', quantity: it.pending }],
      }));
      this.receiveOpen = true;
    },
    addWhRow(itemIdx) {
      this.allocations[itemIdx].rows.push({ warehouse_id: this.warehouses[0]?.id || '', quantity: '' });
    },
    removeWhRow(itemIdx, rowIdx) {
      if (this.allocations[itemIdx].rows.length <= 1) return;
      this.allocations[itemIdx].rows = this.allocations[itemIdx].rows.filter((_, i) => i !== rowIdx);
    },
    itemAllocated(item) {
      return item.rows.reduce((s, r) => s + (parseInt(r.quantity, 10) || 0), 0);
    },
  }));
});
</script>
<div x-data="poShow()">
  <div class="toolbar">
    <div>
      <a class="muted" href="<?= url('purchase-orders') ?>">&larr; Purchase Orders</a>
      <h1 class="page-title" style="margin-top:0.25rem;"><?= e($po['po_number']) ?></h1>
      <p class="page-sub">
        <?= e($po['partner_name'] ?? 'No supplier') ?>
        · <?= e(date('d M Y', strtotime($po['po_date']))) ?>
        · <span class="chip <?= $statusClass[$po['status']] ?? 'chip-muted' ?>"><?= e($statusLabels[$po['status']] ?? $po['status']) ?></span>
      </p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <?php if ($canReceive && $pendingItems): ?>
        <button class="btn btn-primary" type="button" @click="initReceive()">Receive Stock</button>
      <?php endif; ?>
      <?php if ($po['status'] !== 'received' && $po['status'] !== 'cancelled'): ?>
        <form method="post" action="<?= url('purchase-orders/' . $po['id'] . '/cancel') ?>" onsubmit="return confirm('Cancel this purchase order?')">
          <?= csrf_field() ?>
          <button class="btn btn-outline btn-danger" type="submit">Cancel PO</button>
        </form>
      <?php endif; ?>
      <a class="btn btn-outline" href="<?= url('inventory') ?>">View Inventory</a>
    </div>
  </div>

  <div class="stat-grid" style="margin-bottom:1rem;">
    <div class="stat-card">
      <div class="stat-label">Taxable value</div>
      <div class="stat-value"><?= money($po['subtotal']) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">GST (5%)</div>
      <div class="stat-value"><?= money($po['gst_amount']) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Invoice total</div>
      <div class="stat-value"><?= money($po['total_amount']) ?></div>
    </div>
    <?php if ($po['supplier_invoice_no']): ?>
    <div class="stat-card">
      <div class="stat-label">Supplier invoice</div>
      <div class="stat-value" style="font-size:1rem;"><?= e($po['supplier_invoice_no']) ?></div>
      <?php if ($po['supplier_invoice_date']): ?>
        <div class="muted" style="font-size:0.75rem;"><?= e(date('d M Y', strtotime($po['supplier_invoice_date']))) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($po['notes']): ?>
    <div class="card" style="margin-bottom:1rem;"><strong>Notes:</strong> <?= e($po['notes']) ?></div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:1rem;">
    <h3 class="card-title">Line items</h3>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>#</th>
            <th>HSN</th>
            <th>Description</th>
            <th>Color</th>
            <th>Qty ordered</th>
            <th>Received</th>
            <th>Pending</th>
            <th>Unit rate</th>
            <th>GST</th>
            <th>Taxable</th>
            <th>Line total</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i => $it): ?>
          <?php $pending = (int)$it['quantity_ordered'] - (int)$it['quantity_received']; ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= e($it['hsn_code']) ?></td>
            <td>
              <strong><?= e($it['vehicle_name']) ?></strong> — <?= e($it['variant_name']) ?>
              <a class="btn btn-sm btn-outline" style="margin-left:0.35rem;padding:0.1rem 0.4rem;font-size:0.72rem;" href="<?= url('vehicles/' . (int)$it['vehicle_id']) ?>">Vehicle</a>
              <?php if ($it['battery_type']): ?><span class="muted">(<?= e($it['battery_type']) ?>)</span><?php endif; ?>
              <?php if ($it['description'] && $it['description'] !== $it['variant_name']): ?>
                <div class="muted" style="font-size:0.78rem;"><?= e($it['description']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= e($it['color'] ?? '—') ?></td>
            <td><?= (int)$it['quantity_ordered'] ?></td>
            <td><?= (int)$it['quantity_received'] ?></td>
            <td><?= $pending > 0 ? '<strong>' . $pending . '</strong>' : '—' ?></td>
            <td><?= money($it['unit_rate']) ?></td>
            <td><?= number_format((float)$it['gst_percent'], 1) ?>%</td>
            <td><?= money($it['taxable_value']) ?></td>
            <td><?= money($it['line_total']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="9" style="text-align:right;"><strong>Totals</strong></td>
            <td><strong><?= money($po['subtotal']) ?></strong></td>
            <td><strong><?= money($po['total_amount']) ?></strong></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <?php if ($receipts): ?>
  <div class="card">
    <h3 class="card-title">Receipt history</h3>
    <?php foreach ($receipts as $r): ?>
      <div style="border-bottom:1px solid var(--border);padding:0.75rem 0;">
        <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
          <div>
            <strong>Receipt #<?= (int)$r['id'] ?></strong>
            <span class="muted"> · <?= e(date('d M Y, h:i A', strtotime($r['received_at']))) ?></span>
            <span class="muted"> · <?= e(trim($r['first_name'] . ' ' . $r['last_name'])) ?></span>
          </div>
        </div>
        <?php if ($r['notes']): ?><div class="muted" style="font-size:0.85rem;"><?= e($r['notes']) ?></div><?php endif; ?>
        <?php if (!empty($receiptLines[(int)$r['id']])): ?>
          <ul style="margin:0.5rem 0 0;padding-left:1.2rem;font-size:0.88rem;">
            <?php foreach ($receiptLines[(int)$r['id']] as $ln): ?>
              <li>
                <?= (int)$ln['quantity'] ?> × <?= e($ln['vehicle_name']) ?> — <?= e($ln['variant_name']) ?>
                → <strong><?= e($ln['warehouse_name']) ?></strong>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="modal-backdrop" :class="{ open: receiveOpen }" @click.self="receiveOpen=false">
    <div class="modal modal-lg">
      <form method="post" action="<?= url('purchase-orders/' . (int)$po['id'] . '/receive') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h3 class="modal-title">Receive stock into warehouses</h3>
          <button type="button" class="btn btn-sm btn-outline" @click="receiveOpen=false">Close</button>
        </div>
        <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
          <p class="muted" style="margin-top:0;">Split each line across one or more warehouses. Stock is tracked per variant + color in inventory.</p>

          <template x-for="(item, itemIdx) in allocations" :key="item.po_item_id">
            <div class="card" style="padding:0.75rem;margin-bottom:0.75rem;background:var(--surface-2);">
              <div style="display:flex;justify-content:space-between;align-items:start;gap:0.5rem;margin-bottom:0.5rem;">
                <div>
                  <strong x-text="item.label"></strong>
                  <div class="muted" style="font-size:0.8rem;">Pending: <span x-text="item.pending"></span> · Allocating: <span x-text="itemAllocated(item)"></span></div>
                </div>
                <button class="btn btn-sm btn-outline" type="button" @click="addWhRow(itemIdx)">+ Warehouse</button>
              </div>
              <template x-for="(row, rowIdx) in item.rows" :key="rowIdx">
                <div style="display:flex;gap:0.5rem;align-items:end;margin-bottom:0.4rem;">
                  <input type="hidden" :name="'allocations['+itemIdx+'_'+rowIdx+'][po_item_id]'" :value="item.po_item_id">
                  <div class="form-group" style="margin:0;flex:1;">
                    <label x-show="rowIdx===0">Warehouse</label>
                    <select class="form-control" :name="'allocations['+itemIdx+'_'+rowIdx+'][warehouse_id]'" x-model="row.warehouse_id" required>
                      <template x-for="w in warehouses" :key="w.id">
                        <option :value="w.id" x-text="w.name"></option>
                      </template>
                    </select>
                  </div>
                  <div class="form-group" style="margin:0;width:100px;">
                    <label x-show="rowIdx===0">Qty</label>
                    <input class="form-control" type="number" min="1" :name="'allocations['+itemIdx+'_'+rowIdx+'][quantity]'" x-model="row.quantity" required>
                  </div>
                  <button class="btn btn-sm btn-danger" type="button" @click="removeWhRow(itemIdx, rowIdx)" x-show="item.rows.length > 1" style="margin-bottom:0.35rem;">×</button>
                </div>
              </template>
            </div>
          </template>

          <div class="form-group">
            <label>Receipt notes</label>
            <textarea class="form-control" name="notes" rows="2" x-model="notes"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="receiveOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Confirm receipt</button>
        </div>
      </form>
    </div>
  </div>
</div>
