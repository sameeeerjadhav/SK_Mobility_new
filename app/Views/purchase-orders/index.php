<?php
$statuses = ['draft', 'confirmed', 'partial', 'received', 'cancelled'];
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
$variantsJson = json_encode($variants, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
?>
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('poPage', () => ({
    poOpen: false,
    variants: <?= $variantsJson ?>,
    items: [{ variant_id: '', color: '', quantity: 1, unit_rate: '', hsn_code: '87116020', description: '' }],
    gstRate: 5,
    variantLabel(v) {
      const parts = [v.vehicle_name, v.name];
      if (v.color) parts.push(v.color);
      if (v.battery_type) parts.push('(' + v.battery_type + ')');
      return parts.join(' — ');
    },
    onVariantChange(idx) {
      const it = this.items[idx];
      const v = this.variants.find(x => String(x.id) === String(it.variant_id));
      if (!v) return;
      it.color = v.color || '';
      if (!it.unit_rate && v.price) it.unit_rate = v.price;
      if (!it.description) {
        let d = v.vehicle_name + ' ' + v.name;
        if (v.battery_type) d += ' (' + v.battery_type + ')';
        it.description = d;
      }
    },
    lineCalc(it) {
      const qty = parseInt(it.quantity, 10) || 0;
      const rate = parseFloat(it.unit_rate) || 0;
      const taxable = Math.round(rate * qty * 100) / 100;
      const gst = Math.round(taxable * this.gstRate / 100 * 100) / 100;
      return { taxable, gst, total: Math.round((taxable + gst) * 100) / 100 };
    },
    totals() {
      let taxable = 0, gst = 0;
      this.items.forEach(it => {
        const c = this.lineCalc(it);
        taxable += c.taxable;
        gst += c.gst;
      });
      return {
        taxable: Math.round(taxable * 100) / 100,
        gst: Math.round(gst * 100) / 100,
        total: Math.round((taxable + gst) * 100) / 100,
      };
    },
    fmt(n) {
      return '\u20B9' + (parseFloat(n) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    addItem() {
      this.items = [...this.items, { variant_id: '', color: '', quantity: 1, unit_rate: '', hsn_code: '87116020', description: '' }];
    },
    removeItem(idx) {
      if (this.items.length <= 1) return;
      this.items = this.items.filter((_, i) => i !== idx);
    },
  }));
});
</script>
<div x-data="poPage()">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Purchase Orders</h1>
      <p class="page-sub">Procure vehicle variants from suppliers — receipt updates inventory by warehouse &amp; color. Linked to <a href="<?= url('vehicles') ?>">Vehicles</a> and <a href="<?= url('inventory') ?>">Inventory</a>.</p>
    </div>
    <button class="btn btn-primary" type="button" @click="poOpen=true" <?= !$variants ? 'disabled title="Add vehicle variants first"' : '' ?>>+ Purchase Order</button>
  </div>

  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-label">Open POs</div>
      <div class="stat-value"><?= (int)$stats['open'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Received (this month)</div>
      <div class="stat-value"><?= (int)$stats['received_month'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Units pending receipt</div>
      <div class="stat-value"><?= (int)$stats['pending_qty'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">PO value (this month)</div>
      <div class="stat-value"><?= money($stats['month_value']) ?></div>
    </div>
  </div>

  <form method="get" class="card" style="margin-bottom:1rem;">
    <h3 class="card-title" style="margin-bottom:0.65rem;">Filters</h3>
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;">
      <div class="form-group" style="margin:0;min-width:130px;">
        <label>Status</label>
        <select class="form-control" name="status">
          <option value="">All</option>
          <?php foreach ($statuses as $s): ?>
            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($statusLabels[$s]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:160px;">
        <label>Supplier</label>
        <select class="form-control" name="partner_id">
          <option value="">All</option>
          <?php foreach ($partners as $p): ?>
            <option value="<?= (int)$p['id'] ?>" <?= (int)$partnerId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:140px;">
        <label>From</label>
        <input class="form-control" type="date" name="from" value="<?= e($from ?? '') ?>">
      </div>
      <div class="form-group" style="margin:0;min-width:140px;">
        <label>To</label>
        <input class="form-control" type="date" name="to" value="<?= e($to ?? '') ?>">
      </div>
      <div class="form-group" style="margin:0;flex:1;min-width:160px;">
        <label>Search</label>
        <input class="form-control" name="search" value="<?= e($search) ?>" placeholder="PO no., invoice, supplier">
      </div>
      <button class="btn btn-outline" type="submit">Apply</button>
      <a class="btn btn-outline" href="<?= url('purchase-orders') ?>">Reset</a>
    </div>
  </form>

  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>PO Number</th>
            <th>Date</th>
            <th>Supplier</th>
            <th>Lines</th>
            <th>Qty</th>
            <th>Total (incl. 5% GST)</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><strong><?= e($o['po_number']) ?></strong></td>
            <td><?= e(date('d M Y', strtotime($o['po_date']))) ?></td>
            <td><?= e($o['partner_name'] ?? '—') ?></td>
            <td><?= (int)$o['line_count'] ?></td>
            <td><?= (int)$o['total_qty'] ?></td>
            <td><?= money($o['total_amount']) ?></td>
            <td><span class="chip <?= $statusClass[$o['status']] ?? 'chip-muted' ?>"><?= e($statusLabels[$o['status']] ?? $o['status']) ?></span></td>
            <td><a class="btn btn-sm btn-outline" href="<?= url('purchase-orders/' . $o['id']) ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
          <tr><td colspan="8" class="muted">No purchase orders yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal-backdrop" :class="{ open: poOpen }" @click.self="poOpen=false">
    <div class="modal modal-lg">
      <form method="post" action="<?= url('purchase-orders') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h3 class="modal-title">New Purchase Order</h3>
          <button type="button" class="btn btn-sm btn-outline" @click="poOpen=false">Close</button>
        </div>
        <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem;margin-bottom:1rem;">
            <div class="form-group" style="margin:0;">
              <label>PO Date</label>
              <input class="form-control" type="date" name="po_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group" style="margin:0;">
              <label>Supplier</label>
              <select class="form-control" name="partner_id">
                <option value="">Select supplier</option>
                <?php foreach ($partners as $p): ?>
                  <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="margin:0;">
              <label>Supplier Invoice No.</label>
              <input class="form-control" name="supplier_invoice_no" placeholder="e.g. EB/12227/FY27">
            </div>
            <div class="form-group" style="margin:0;">
              <label>Invoice Date</label>
              <input class="form-control" type="date" name="supplier_invoice_date">
            </div>
          </div>

          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
            <h4 style="margin:0;font-size:0.95rem;">Line items <span class="muted">(5% GST on unit rate)</span></h4>
            <button class="btn btn-sm btn-outline" type="button" @click="addItem()">+ Add line</button>
          </div>

          <template x-for="(it, idx) in items" :key="idx">
            <div class="card" style="padding:0.75rem;margin-bottom:0.65rem;background:var(--surface-2);">
              <div style="display:grid;grid-template-columns:2fr 1fr 80px 120px 100px auto;gap:0.5rem;align-items:end;">
                <div class="form-group" style="margin:0;">
                  <label x-show="idx===0">Variant</label>
                  <select class="form-control" :name="'items['+idx+'][variant_id]'" x-model="it.variant_id" @change="onVariantChange(idx)" required>
                    <option value="">Select variant</option>
                    <template x-for="v in variants" :key="v.id">
                      <option :value="v.id" x-text="variantLabel(v)"></option>
                    </template>
                  </select>
                </div>
                <div class="form-group" style="margin:0;">
                  <label x-show="idx===0">Color</label>
                  <input class="form-control" type="text" :name="'items['+idx+'][color]'" x-model="it.color" placeholder="Color">
                </div>
                <div class="form-group" style="margin:0;">
                  <label x-show="idx===0">Qty</label>
                  <input class="form-control" type="number" min="1" :name="'items['+idx+'][quantity]'" x-model="it.quantity" required>
                </div>
                <div class="form-group" style="margin:0;">
                  <label x-show="idx===0">Unit rate</label>
                  <input class="form-control" type="number" step="0.01" min="0.01" :name="'items['+idx+'][unit_rate]'" x-model="it.unit_rate" required>
                </div>
                <div class="form-group" style="margin:0;">
                  <label x-show="idx===0">HSN</label>
                  <input class="form-control" type="text" :name="'items['+idx+'][hsn_code]'" x-model="it.hsn_code">
                </div>
                <div style="padding-bottom:0.35rem;">
                  <button class="btn btn-sm btn-danger" type="button" @click="removeItem(idx)" x-show="items.length > 1">×</button>
                </div>
              </div>
              <div class="form-group" style="margin:0.5rem 0 0;">
                <input class="form-control" type="text" :name="'items['+idx+'][description]'" x-model="it.description" placeholder="Description (optional)">
              </div>
              <div class="muted" style="font-size:0.78rem;margin-top:0.35rem;">
                Taxable: <span x-text="fmt(lineCalc(it).taxable)"></span>
                · GST 5%: <span x-text="fmt(lineCalc(it).gst)"></span>
                · Line total: <strong x-text="fmt(lineCalc(it).total)"></strong>
              </div>
            </div>
          </template>

          <div style="text-align:right;margin:0.75rem 0;">
            <div class="muted">Taxable subtotal: <span x-text="fmt(totals().taxable)"></span></div>
            <div class="muted">GST (5%): <span x-text="fmt(totals().gst)"></span></div>
            <div style="font-size:1.05rem;"><strong>Grand total: <span x-text="fmt(totals().total)"></span></strong></div>
          </div>

          <div class="form-group">
            <label>Notes</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="Payment terms, delivery notes…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="poOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Create PO</button>
        </div>
      </form>
    </div>
  </div>
</div>
