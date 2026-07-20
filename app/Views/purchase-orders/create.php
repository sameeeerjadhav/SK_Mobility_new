<?php
$variantsJson = json_encode($variants, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$vehiclesJson = json_encode($vehicles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$batteryTypes = ['Lithium Ion', 'Lead Acid'];
$canCreate = !empty($vehicles);
$defaultMode = !empty($variants) ? 'existing' : 'new';
?>
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('poCreatePage', () => ({
    variants: <?= $variantsJson ?>,
    vehicles: <?= $vehiclesJson ?>,
    items: [{
      variant_mode: '<?= $defaultMode ?>',
      variant_id: '',
      vehicle_id: '',
      new_variant_name: '',
      battery_type: '',
      color: '',
      quantity: 1,
      unit_rate: '',
      hsn_code: '87116020',
      description: '',
    }],
    gstRate: 5,
    variantLabel(v) {
      const parts = [v.vehicle_name, v.name];
      if (v.color) parts.push(v.color);
      if (v.battery_type) parts.push('(' + v.battery_type + ')');
      return parts.join(' — ');
    },
    setMode(idx, mode) {
      const it = this.items[idx];
      it.variant_mode = mode;
      if (mode === 'existing') {
        it.vehicle_id = '';
        it.new_variant_name = '';
        it.battery_type = '';
      } else {
        it.variant_id = '';
      }
    },
    onVariantChange(idx) {
      const it = this.items[idx];
      const v = this.variants.find(x => String(x.id) === String(it.variant_id));
      if (!v) return;
      it.color = v.color || '';
      if (!it.unit_rate && v.price) it.unit_rate = v.price;
      it.description = this.describeVariant(v.vehicle_name, v.name, v.battery_type);
    },
    onNewVariantChange(idx) {
      const it = this.items[idx];
      const vehicle = this.vehicles.find(v => String(v.id) === String(it.vehicle_id));
      if (!vehicle || !it.new_variant_name) return;
      if (!it.description) {
        it.description = this.describeVariant(vehicle.name, it.new_variant_name, it.battery_type);
      }
    },
    describeVariant(vehicleName, variantName, batteryType) {
      let d = vehicleName + ' ' + variantName;
      if (batteryType) d += ' (' + batteryType + ')';
      return d;
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
      this.items = [...this.items, {
        variant_mode: this.variants.length ? 'existing' : 'new',
        variant_id: '',
        vehicle_id: '',
        new_variant_name: '',
        battery_type: '',
        color: '',
        quantity: 1,
        unit_rate: '',
        hsn_code: '87116020',
        description: '',
      }];
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
      <p class="page-sub" style="margin:0.25rem 0 0;">Record a vehicle purchase — new variants are auto-added to Vehicles; stock is added on PO receive</p>
    </div>
  </div>

  <?php if (!$canCreate): ?>
    <div class="alert alert-warning">Add at least one vehicle under <a href="<?= url('vehicles') ?>">Vehicles</a> before creating a purchase order.</div>
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
          <p class="muted" style="margin:0.25rem 0 0;font-size:0.82rem;">Choose existing variant OR add a new variant — not both on the same line</p>
        </div>
        <button class="btn btn-sm btn-outline" type="button" @click="addItem()">+ Add line</button>
      </div>

      <template x-for="(it, idx) in items" :key="idx">
        <div class="card" style="padding:1rem;margin-bottom:0.75rem;background:var(--surface-2);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.65rem;gap:0.5rem;flex-wrap:wrap;">
            <strong style="font-size:0.88rem;">Item <span x-text="idx + 1"></span></strong>
            <button class="btn btn-sm btn-danger" type="button" @click="removeItem(idx)" x-show="items.length > 1">Remove</button>
          </div>

          <input type="hidden" :name="'items['+idx+'][variant_mode]'" :value="it.variant_mode">

          <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.75rem;">
            <button class="btn btn-sm" type="button"
              :class="it.variant_mode === 'existing' ? 'btn-primary' : 'btn-outline'"
              @click="setMode(idx, 'existing')">Existing variant</button>
            <button class="btn btn-sm" type="button"
              :class="it.variant_mode === 'new' ? 'btn-primary' : 'btn-outline'"
              @click="setMode(idx, 'new')">New variant</button>
          </div>

          <div class="form-grid" x-show="it.variant_mode === 'existing'" x-cloak>
            <div class="form-group" style="grid-column:1 / -1;">
              <label>Select variant *</label>
              <select class="form-control" :name="it.variant_mode === 'existing' ? 'items['+idx+'][variant_id]' : null"
                      x-model="it.variant_id" @change="onVariantChange(idx)"
                      :required="it.variant_mode === 'existing'">
                <option value="">Choose from catalog</option>
                <template x-for="v in variants" :key="v.id">
                  <option :value="v.id" x-text="variantLabel(v)"></option>
                </template>
              </select>
              <div class="muted" style="font-size:0.75rem;margin-top:0.2rem;" x-show="!variants.length">
                No variants yet — switch to “New variant”.
              </div>
            </div>
          </div>

          <div class="form-grid" x-show="it.variant_mode === 'new'" x-cloak>
            <div class="form-group">
              <label>Vehicle *</label>
              <select class="form-control" :name="it.variant_mode === 'new' ? 'items['+idx+'][vehicle_id]' : null"
                      x-model="it.vehicle_id" @change="onNewVariantChange(idx)"
                      :required="it.variant_mode === 'new'">
                <option value="">Select vehicle</option>
                <template x-for="v in vehicles" :key="v.id">
                  <option :value="v.id" x-text="v.name"></option>
                </template>
              </select>
            </div>
            <div class="form-group">
              <label>Variant name *</label>
              <input class="form-control" type="text"
                     :name="it.variant_mode === 'new' ? 'items['+idx+'][new_variant_name]' : null"
                     x-model="it.new_variant_name" @input="onNewVariantChange(idx)"
                     placeholder="e.g. NX3 Pro 1210"
                     :required="it.variant_mode === 'new'">
            </div>
            <div class="form-group">
              <label>Battery type</label>
              <select class="form-control" :name="it.variant_mode === 'new' ? 'items['+idx+'][battery_type]' : null"
                      x-model="it.battery_type" @change="onNewVariantChange(idx)">
                <option value="">Select</option>
                <?php foreach ($batteryTypes as $bt): ?>
                  <option value="<?= e($bt) ?>"><?= e($bt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="grid-column:1 / -1;">
              <div class="muted" style="font-size:0.78rem;">New variant is auto-created in Vehicles and used for stock on PO receive.</div>
            </div>
          </div>

          <div class="form-grid" style="margin-top:0.5rem;">
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
              <input class="form-control" type="text" :name="'items['+idx+'][description]'" x-model="it.description" placeholder="Auto-filled from variant">
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
      <button class="btn btn-primary" type="submit" <?= !$canCreate ? 'disabled' : '' ?>>Create purchase order</button>
      <a class="btn btn-outline" href="<?= url('purchase-orders') ?>">Cancel</a>
    </div>
  </form>
</div>
