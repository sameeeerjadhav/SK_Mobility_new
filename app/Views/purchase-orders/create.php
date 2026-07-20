<?php
$variantsJson = json_encode($variants, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('poCreatePage', () => ({
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
<div x-data="poCreatePage()">
  <div style="margin-bottom:0.75rem;"><a href="<?= url('purchase-orders') ?>">&larr; Purchase Orders</a></div>

  <div class="toolbar" style="margin-bottom:1rem;">
    <div>
      <h1 class="page-title" style="margin:0;">New Purchase Order</h1>
      <p class="page-sub" style="margin:0.25rem 0 0;">Record a vehicle purchase from a supplier — stock is added when you receive the PO</p>
    </div>
  </div>

  <?php if (!$variants): ?>
    <div class="alert alert-warning">Add vehicle variants under <a href="<?= url('vehicles') ?>">Vehicles</a> before creating a purchase order.</div>
  <?php endif; ?>

  <form method="post" action="<?= url('purchase-orders') ?>">
    <?= csrf_field() ?>

    <div class="card" style="margin-bottom:0.85rem;">
      <h3 class="card-title">1. Supplier &amp; invoice</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>PO date</label>
          <input class="form-control" type="date" name="po_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label>Supplier company *</label>
          <input class="form-control" name="supplier_name" required placeholder="e.g. Alphavector India Pvt. Ltd.">
        </div>
        <div class="form-group">
          <label>Supplier invoice no.</label>
          <input class="form-control" name="supplier_invoice_no" placeholder="e.g. EB/12227/FY27">
        </div>
        <div class="form-group">
          <label>Invoice date</label>
          <input class="form-control" type="date" name="supplier_invoice_date">
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:0.85rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.75rem;">
        <div>
          <h3 class="card-title" style="margin:0;">2. Line items</h3>
          <p class="muted" style="margin:0.25rem 0 0;font-size:0.82rem;">Unit rate is taxable amount · 5% GST applied automatically</p>
        </div>
        <button class="btn btn-sm btn-outline" type="button" @click="addItem()">+ Add line</button>
      </div>

      <template x-for="(it, idx) in items" :key="idx">
        <div class="card" style="padding:1rem;margin-bottom:0.75rem;background:var(--surface-2);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.65rem;">
            <strong style="font-size:0.88rem;">Item <span x-text="idx + 1"></span></strong>
            <button class="btn btn-sm btn-danger" type="button" @click="removeItem(idx)" x-show="items.length > 1">Remove</button>
          </div>
          <div class="form-grid">
            <div class="form-group" style="grid-column:1 / -1;">
              <label>Vehicle variant *</label>
              <select class="form-control" :name="'items['+idx+'][variant_id]'" x-model="it.variant_id" @change="onVariantChange(idx)" required>
                <option value="">Select variant</option>
                <template x-for="v in variants" :key="v.id">
                  <option :value="v.id" x-text="variantLabel(v)"></option>
                </template>
              </select>
            </div>
            <div class="form-group">
              <label>Color</label>
              <input class="form-control" type="text" :name="'items['+idx+'][color]'" x-model="it.color" placeholder="e.g. Red">
            </div>
            <div class="form-group">
              <label>Quantity *</label>
              <input class="form-control" type="number" min="1" :name="'items['+idx+'][quantity]'" x-model="it.quantity" required>
            </div>
            <div class="form-group">
              <label>Unit rate (taxable) *</label>
              <input class="form-control" type="number" step="0.01" min="0.01" :name="'items['+idx+'][unit_rate]'" x-model="it.unit_rate" required>
            </div>
            <div class="form-group">
              <label>HSN code</label>
              <input class="form-control" type="text" :name="'items['+idx+'][hsn_code]'" x-model="it.hsn_code">
            </div>
            <div class="form-group" style="grid-column:1 / -1;">
              <label>Description</label>
              <input class="form-control" type="text" :name="'items['+idx+'][description]'" x-model="it.description" placeholder="Optional — auto-filled from variant">
            </div>
          </div>
          <div class="muted" style="font-size:0.82rem;margin-top:0.35rem;">
            Taxable: <span x-text="fmt(lineCalc(it).taxable)"></span>
            · GST 5%: <span x-text="fmt(lineCalc(it).gst)"></span>
            · Line total: <strong x-text="fmt(lineCalc(it).total)"></strong>
          </div>
        </div>
      </template>
    </div>

    <div class="card" style="margin-bottom:0.85rem;">
      <h3 class="card-title">3. Totals &amp; notes</h3>
      <div style="display:grid;grid-template-columns:1fr 280px;gap:1.25rem;align-items:start;">
        <div class="form-group" style="margin:0;">
          <label>Notes</label>
          <textarea class="form-control" name="notes" rows="4" placeholder="Payment terms, delivery instructions…"></textarea>
        </div>
        <div style="padding:0.85rem 1rem;border-radius:12px;background:var(--surface-2);border:1px solid var(--border);">
          <div class="muted" style="display:flex;justify-content:space-between;margin-bottom:0.35rem;">
            <span>Taxable subtotal</span><span x-text="fmt(totals().taxable)"></span>
          </div>
          <div class="muted" style="display:flex;justify-content:space-between;margin-bottom:0.35rem;">
            <span>GST (5%)</span><span x-text="fmt(totals().gst)"></span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:1.05rem;font-weight:800;padding-top:0.5rem;border-top:1px solid var(--border);">
            <span>Grand total</span><span x-text="fmt(totals().total)"></span>
          </div>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <button class="btn btn-primary" type="submit" <?= !$variants ? 'disabled' : '' ?>>Create purchase order</button>
      <a class="btn btn-outline" href="<?= url('purchase-orders') ?>">Cancel</a>
    </div>
  </form>
</div>
