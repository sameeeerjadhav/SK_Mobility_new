<?php
$productType = $productType ?? 'vehicle';
$isSparePo = $productType === 'spare_part';
$variantsJson = json_encode($variants, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$vehiclesJson = json_encode($vehicles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$vehicleCategoriesJson = json_encode($vehicleCategories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$sparePartsJson = json_encode($spareParts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$spareCategoriesJson = json_encode($spareCategories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$batteryTypes = ['Lithium Ion', 'Lead Acid'];
$defaultVariantMode = !empty($variants) ? 'existing' : 'new';
$defaultVehicleMode = !empty($vehicles) ? 'existing' : 'new';
$defaultSpareMode = !empty($spareParts) ? 'existing' : 'new';
$defaultCgst = $isSparePo ? 9 : 2.5;
$defaultSgst = $isSparePo ? 9 : 2.5;
$defaultGstPercent = $defaultCgst + $defaultSgst;
?>
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('poCreatePage', () => ({
    productType: '<?= e($productType) ?>',
    variants: <?= $variantsJson ?>,
    vehicles: <?= $vehiclesJson ?>,
    vehicleCategories: <?= $vehicleCategoriesJson ?>,
    spareParts: <?= $sparePartsJson ?>,
    spareCategories: <?= $spareCategoriesJson ?>,
    defaultVariantMode: '<?= $defaultVariantMode ?>',
    defaultVehicleMode: '<?= $defaultVehicleMode ?>',
    defaultSpareMode: '<?= $defaultSpareMode ?>',
    items: [],
    gstPreset: 'default',
    defaultCgst: <?= $defaultCgst ?>,
    defaultSgst: <?= $defaultSgst ?>,
    cgstRate: <?= $defaultCgst ?>,
    sgstRate: <?= $defaultSgst ?>,
    paymentStatus: 'unpaid',
    amountPaid: '',
    affectBank: false,
    init() {
      this.applyGstPreset();
      this.items = [this.blankItem()];
    },
    get totalGstPercent() {
      return Math.round(((parseFloat(this.cgstRate) || 0) + (parseFloat(this.sgstRate) || 0)) * 100) / 100;
    },
    applyGstPreset() {
      const presets = {
        default: [this.defaultCgst, this.defaultSgst],
        '28': [14, 14],
        '18': [9, 9],
        '12': [6, 6],
        '5': [2.5, 2.5],
        '0': [0, 0],
      };
      if (this.gstPreset === 'custom') return;
      const p = presets[this.gstPreset] || presets.default;
      this.cgstRate = p[0];
      this.sgstRate = p[1];
      this.syncGstToLines();
    },
    syncGstToLines() {
      const total = this.totalGstPercent;
      this.items.forEach(it => { it.gst_percent = total; });
    },
    blankItem() {
      const isSpare = this.productType === 'spare_part';
      return {
        item_type: isSpare ? 'spare_part' : 'vehicle_variant',
        variant_mode: this.defaultVariantMode,
        vehicle_mode: this.defaultVehicleMode,
        variant_id: '',
        vehicle_id: '',
        new_vehicle_name: '',
        vehicle_category_id: '',
        new_variant_name: '',
        battery_type: '',
        spare_mode: this.defaultSpareMode,
        spare_part_id: '',
        spare_category_id: '',
        new_part_name: '',
        color: '',
        quantity: 1,
        unit_rate: '',
        hsn_code: isSpare ? '85076000' : '87116020',
        gst_percent: this.totalGstPercent,
        description: '',
      };
    },
    variantLabel(v) {
      const parts = [v.vehicle_name, v.name];
      if (v.color) parts.push(v.color);
      if (v.battery_type) parts.push('(' + v.battery_type + ')');
      return parts.join(' — ');
    },
    spareLabel(sp) {
      return [sp.category_name, sp.name, sp.part_number ? '(' + sp.part_number + ')' : ''].filter(Boolean).join(' — ');
    },
    setItemType(idx, type) {
      const it = this.items[idx];
      it.item_type = type;
      if (type === 'vehicle_variant') {
        it.spare_part_id = '';
        it.new_part_name = '';
        it.spare_category_id = '';
        it.spare_mode = this.defaultSpareMode;
        it.hsn_code = '87116020';
        it.gst_percent = this.defaultGstForType('vehicle_variant');
      } else {
        it.variant_id = '';
        it.vehicle_id = '';
        it.new_variant_name = '';
        it.vehicle_mode = this.defaultVehicleMode;
        it.new_vehicle_name = '';
        it.vehicle_category_id = '';
        it.battery_type = '';
        it.variant_mode = this.defaultVariantMode;
        it.color = '';
        it.hsn_code = '85076000';
        it.gst_percent = this.defaultGstForType('spare_part');
      }
    },
    defaultGstForType(type) {
      return type === 'spare_part' ? 18 : 5;
    },
    applyDefaultGstToAll() {
      this.syncGstToLines();
    },
    setMode(idx, mode) {
      const it = this.items[idx];
      it.variant_mode = mode;
      if (mode === 'existing') {
        it.vehicle_id = '';
        it.new_variant_name = '';
        it.battery_type = '';
        it.vehicle_mode = 'existing';
        it.new_vehicle_name = '';
        it.vehicle_category_id = '';
      } else {
        it.variant_id = '';
        it.vehicle_mode = this.vehicles.length ? 'existing' : 'new';
      }
    },
    setVehicleMode(idx, mode) {
      const it = this.items[idx];
      it.vehicle_mode = mode;
      if (mode === 'existing') {
        it.new_vehicle_name = '';
        it.vehicle_category_id = '';
      } else {
        it.vehicle_id = '';
      }
      this.onNewVariantChange(idx);
    },
    setSpareMode(idx, mode) {
      const it = this.items[idx];
      it.spare_mode = mode;
      if (mode === 'existing') {
        it.new_part_name = '';
        it.spare_category_id = '';
      } else {
        it.spare_part_id = '';
      }
    },
    onVariantChange(idx) {
      const it = this.items[idx];
      const v = this.variants.find(x => String(x.id) === String(it.variant_id));
      if (!v) return;
      if (!it.color) it.color = v.color || '';
      if (!it.unit_rate && v.price) it.unit_rate = v.price;
      this.syncDescription(idx);
    },
    onNewVariantChange(idx) {
      this.syncDescription(idx);
    },
    onNewVehicleNameInput(idx) {
      const it = this.items[idx];
      if (it.vehicle_mode === 'new' && it.new_vehicle_name) {
        const key = it.new_vehicle_name.trim().toLowerCase();
        const sibling = this.items.find((row, i) =>
          i !== idx
          && row.item_type === 'vehicle_variant'
          && row.vehicle_mode === 'new'
          && row.new_vehicle_name.trim().toLowerCase() === key
        );
        if (sibling && sibling.vehicle_category_id) {
          it.vehicle_category_id = sibling.vehicle_category_id;
        }
      }
      this.onNewVariantChange(idx);
    },
    onColorChange(idx) {
      this.syncDescription(idx);
    },
    syncDescription(idx) {
      const it = this.items[idx];
      if (it.item_type !== 'vehicle_variant') return;
      let vehicleName = '';
      let variantName = '';
      if (it.variant_mode === 'existing') {
        const v = this.variants.find(x => String(x.id) === String(it.variant_id));
        if (!v) return;
        vehicleName = v.vehicle_name;
        variantName = v.name;
      } else {
        if (it.vehicle_mode === 'existing') {
          const vehicle = this.vehicles.find(v => String(v.id) === String(it.vehicle_id));
          if (!vehicle || !it.new_variant_name) return;
          vehicleName = vehicle.name;
        } else {
          if (!it.new_vehicle_name || !it.new_variant_name) return;
          vehicleName = it.new_vehicle_name;
        }
        variantName = it.new_variant_name;
      }
      it.description = this.describeVariant(vehicleName, variantName, it.battery_type, it.color);
    },
    onSpareChange(idx) {
      const it = this.items[idx];
      const sp = this.spareParts.find(x => String(x.id) === String(it.spare_part_id));
      if (!sp) return;
      if (!it.unit_rate && sp.unit_price) it.unit_rate = sp.unit_price;
      if (!it.description) it.description = sp.name;
    },
    onNewSpareChange(idx) {
      const it = this.items[idx];
      if (!it.new_part_name) return;
      if (!it.description) it.description = it.new_part_name;
    },
    describeVariant(vehicleName, variantName, batteryType, color) {
      let d = vehicleName + ' ' + variantName;
      if (color) d += ' — ' + color;
      else if (batteryType) d += ' (' + batteryType + ')';
      return d;
    },
    lastVehicleLine() {
      for (let i = this.items.length - 1; i >= 0; i--) {
        if (this.items[i].item_type === 'vehicle_variant') return this.items[i];
      }
      return null;
    },
    addColorLine(idx) {
      const src = this.items[idx];
      if (src.item_type !== 'vehicle_variant') {
        this.addItem();
        return;
      }
      const line = {
        item_type: 'vehicle_variant',
        variant_mode: src.variant_mode,
        vehicle_mode: src.vehicle_mode,
        variant_id: src.variant_id,
        vehicle_id: src.vehicle_id,
        new_vehicle_name: src.new_vehicle_name,
        vehicle_category_id: src.vehicle_category_id,
        new_variant_name: src.new_variant_name,
        battery_type: src.battery_type,
        spare_mode: this.defaultSpareMode,
        spare_part_id: '',
        spare_category_id: '',
        new_part_name: '',
        color: '',
        quantity: 1,
        unit_rate: src.unit_rate || '',
        hsn_code: '87116020',
        gst_percent: src.gst_percent || this.totalGstPercent,
        description: '',
      };
      this.items = [...this.items.slice(0, idx + 1), line, ...this.items.slice(idx + 1)];
    },
    addItem() {
      if (this.productType === 'spare_part') {
        this.items = [...this.items, this.blankItem()];
        return;
      }
      const last = this.lastVehicleLine();
      if (last) {
        const sameNewVehicle = last.vehicle_mode === 'new' && last.new_vehicle_name;
        this.items = [...this.items, {
          item_type: 'vehicle_variant',
          variant_mode: sameNewVehicle ? 'new' : last.variant_mode,
          vehicle_mode: last.vehicle_mode,
          variant_id: '',
          vehicle_id: '',
          new_vehicle_name: sameNewVehicle ? last.new_vehicle_name : '',
          vehicle_category_id: sameNewVehicle ? last.vehicle_category_id : '',
          new_variant_name: '',
          battery_type: '',
          spare_mode: this.defaultSpareMode,
          spare_part_id: '',
          spare_category_id: '',
          new_part_name: '',
          color: '',
          quantity: 1,
          unit_rate: last.unit_rate || '',
          hsn_code: '87116020',
          gst_percent: last.gst_percent || this.totalGstPercent,
          description: '',
        }];
        return;
      }
      this.items = [...this.items, this.blankItem()];
    },
    lineCalc(it) {
      const qty = parseInt(it.quantity, 10) || 0;
      const rate = parseFloat(it.unit_rate) || 0;
      const gstRate = parseFloat(it.gst_percent) || 0;
      const taxable = Math.round(rate * qty * 100) / 100;
      const gst = Math.round(taxable * gstRate / 100 * 100) / 100;
      return { taxable, gst, total: Math.round((taxable + gst) * 100) / 100, gstRate };
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
      <h1 class="page-title" style="margin:0;" x-text="productType === 'spare_part' ? 'New Spare Parts Purchase Order' : 'New Vehicle Purchase Order'"></h1>
      <p class="page-sub" style="margin:0.25rem 0 0;">Separate flow for vehicles and spare parts — stock is added on PO receive</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <a class="btn btn-sm" :class="productType === 'vehicle' ? 'btn-primary' : 'btn-outline'" href="<?= url('purchase-orders/create?product=vehicle') ?>">Vehicle</a>
      <a class="btn btn-sm" :class="productType === 'spare_part' ? 'btn-primary' : 'btn-outline'" href="<?= url('purchase-orders/create?product=spare_part') ?>">Spare Parts</a>
    </div>
  </div>

  <?php if (!$canCreate): ?>
    <div class="alert alert-warning">
      <?php if ($isSparePo): ?>
        Add spare categories under <a href="<?= url('spare-parts') ?>">Spare Parts</a> before creating a spare parts purchase order.
      <?php else: ?>
        Add vehicle categories under <a href="<?= url('vehicles') ?>">Vehicles</a> before creating a vehicle purchase order.
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= url('purchase-orders') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="product_type" :value="productType">

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
      <h3 class="card-title">GST</h3>
      <p class="muted" style="margin:-0.35rem 0 0.85rem;font-size:0.82rem;">Same presets as sell orders — applied to all line items (each line can still be edited).</p>
      <div class="form-grid">
        <div class="form-group">
          <label>GST option *</label>
          <select class="form-control" x-model="gstPreset" @change="applyGstPreset()">
            <option value="default">Default — <?= $isSparePo ? '18% (9+9) spare parts' : '5% (2.5+2.5) vehicles' ?></option>
            <option value="28">28% — CGST 14% + SGST 14%</option>
            <option value="18">18% — CGST 9% + SGST 9%</option>
            <option value="12">12% — CGST 6% + SGST 6%</option>
            <option value="5">5% — CGST 2.5% + SGST 2.5%</option>
            <option value="0">0% — No GST</option>
            <option value="custom">Custom rates</option>
          </select>
        </div>
        <template x-if="gstPreset === 'custom'">
          <div class="form-group">
            <label>CGST % *</label>
            <input class="form-control" type="number" step="0.01" min="0" max="100" name="cgst_rate" x-model="cgstRate" @input="syncGstToLines()" required>
          </div>
        </template>
        <template x-if="gstPreset === 'custom'">
          <div class="form-group">
            <label>SGST % *</label>
            <input class="form-control" type="number" step="0.01" min="0" max="100" name="sgst_rate" x-model="sgstRate" @input="syncGstToLines()" required>
          </div>
        </template>
        <template x-if="gstPreset !== 'custom'">
          <input type="hidden" name="cgst_rate" :value="cgstRate">
          <input type="hidden" name="sgst_rate" :value="sgstRate">
        </template>
        <div class="form-group">
          <label>Total GST</label>
          <input class="form-control" type="text" readonly tabindex="-1" style="background:#f8fafc;"
                 :value="totalGstPercent + '% · CGST ' + cgstRate + '% + SGST ' + sgstRate + '%'">
        </div>
        <div class="form-group" style="display:flex;align-items:end;">
          <button class="btn btn-sm btn-outline" type="button" @click="syncGstToLines()">Apply to all lines</button>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:0.85rem;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;flex-wrap:wrap;margin-bottom:0.75rem;">
        <div>
          <h3 class="card-title" style="margin:0;" x-text="productType === 'spare_part' ? '3. Spare parts / batteries' : '3. Vehicle variants'"></h3>
          <p class="muted" style="margin:0.25rem 0 0;font-size:0.82rem;" x-show="productType === 'vehicle'">Use color to split variants under one vehicle — each color is a separate catalog variant on receive.</p>
          <p class="muted" style="margin:0.25rem 0 0;font-size:0.82rem;" x-show="productType === 'spare_part'">Procure spare parts or batteries — stock updates on receive (no warehouse split).</p>
        </div>
        <button class="btn btn-sm btn-outline" type="button" @click="addItem()">+ Add line</button>
      </div>

      <template x-for="(it, idx) in items" :key="idx">
        <div class="card" style="padding:1rem;margin-bottom:0.75rem;background:var(--surface-2);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.65rem;gap:0.5rem;flex-wrap:wrap;">
            <strong style="font-size:0.88rem;">Item <span x-text="idx + 1"></span></strong>
            <button class="btn btn-sm btn-danger" type="button" @click="removeItem(idx)" x-show="items.length > 1">Remove</button>
          </div>

          <input type="hidden" :name="'items['+idx+'][item_type]'" :value="productType === 'spare_part' ? 'spare_part' : 'vehicle_variant'">
          <input type="hidden" :name="'items['+idx+'][variant_mode]'" :value="it.variant_mode" x-show="productType === 'vehicle'">
          <input type="hidden" :name="'items['+idx+'][vehicle_mode]'" :value="it.vehicle_mode" x-show="productType === 'vehicle' && it.variant_mode === 'new'">
          <input type="hidden" :name="'items['+idx+'][spare_mode]'" :value="it.spare_mode" x-show="productType === 'spare_part'">

          <template x-if="productType === 'vehicle'">
            <div>
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
                <div class="form-group" style="grid-column:1 / -1;">
                  <label style="display:block;margin-bottom:0.35rem;">Vehicle</label>
                  <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.65rem;">
                    <button class="btn btn-sm" type="button"
                      :class="it.vehicle_mode === 'existing' ? 'btn-primary' : 'btn-outline'"
                      @click="setVehicleMode(idx, 'existing')">Existing vehicle</button>
                    <button class="btn btn-sm" type="button"
                      :class="it.vehicle_mode === 'new' ? 'btn-primary' : 'btn-outline'"
                      @click="setVehicleMode(idx, 'new')">New vehicle</button>
                  </div>
                </div>

                <div class="form-group" style="grid-column:1 / -1;" x-show="it.vehicle_mode === 'existing'">
                  <label>Select vehicle *</label>
                  <select class="form-control"
                          :name="it.variant_mode === 'new' && it.vehicle_mode === 'existing' ? 'items['+idx+'][vehicle_id]' : null"
                          x-model="it.vehicle_id" @change="onNewVariantChange(idx)"
                          :required="it.variant_mode === 'new' && it.vehicle_mode === 'existing'">
                    <option value="">Select vehicle</option>
                    <template x-for="v in vehicles" :key="v.id">
                      <option :value="v.id" x-text="v.name"></option>
                    </template>
                  </select>
                  <div class="muted" style="font-size:0.75rem;margin-top:0.2rem;" x-show="!vehicles.length">
                    No vehicles yet — switch to “New vehicle”.
                  </div>
                </div>

                <div class="form-group" x-show="it.vehicle_mode === 'new'">
                  <label>Vehicle category *</label>
                  <select class="form-control"
                          :name="it.variant_mode === 'new' && it.vehicle_mode === 'new' ? 'items['+idx+'][vehicle_category_id]' : null"
                          x-model="it.vehicle_category_id"
                          :required="it.variant_mode === 'new' && it.vehicle_mode === 'new'">
                    <option value="">Select category</option>
                    <template x-for="c in vehicleCategories" :key="c.id">
                      <option :value="c.id" x-text="c.name"></option>
                    </template>
                  </select>
                </div>
                <div class="form-group" x-show="it.vehicle_mode === 'new'">
                  <label>Vehicle name *</label>
                  <input class="form-control" type="text"
                         :name="it.variant_mode === 'new' && it.vehicle_mode === 'new' ? 'items['+idx+'][new_vehicle_name]' : null"
                         x-model="it.new_vehicle_name" @input="onNewVehicleNameInput(idx)"
                         placeholder="e.g. NX3 Series"
                         :required="it.variant_mode === 'new' && it.vehicle_mode === 'new'">
                  <div class="muted" style="font-size:0.75rem;margin-top:0.2rem;" x-show="it.vehicle_mode === 'new'">
                    Same vehicle name on another line reuses one vehicle — enter a different variant or color per line.
                  </div>
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
                  <div class="muted" style="font-size:0.78rem;">New vehicle and variant are auto-created in Vehicles and used for stock on PO receive.</div>
                </div>
              </div>

              <div class="form-grid" style="margin-top:0.5rem;">
                <div class="form-group">
                  <label>Color *</label>
                  <input class="form-control" type="text" :name="'items['+idx+'][color]'" x-model="it.color"
                         @input="onColorChange(idx)" placeholder="e.g. Red" required>
                  <div class="muted" style="font-size:0.75rem;margin-top:0.2rem;">
                    Each color is a separate variant under the same vehicle in Vehicles catalog.
                  </div>
                </div>
                <div class="form-group" style="display:flex;align-items:end;">
                  <button class="btn btn-sm btn-outline" type="button" @click="addColorLine(idx)">+ Another color</button>
                </div>
              </div>
            </div>
          </template>

          <template x-if="productType === 'spare_part'">
            <div>
              <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:0.75rem;">
                <button class="btn btn-sm" type="button"
                  :class="it.spare_mode === 'existing' ? 'btn-primary' : 'btn-outline'"
                  @click="setSpareMode(idx, 'existing')">Existing part</button>
                <button class="btn btn-sm" type="button"
                  :class="it.spare_mode === 'new' ? 'btn-primary' : 'btn-outline'"
                  @click="setSpareMode(idx, 'new')">New part</button>
              </div>

              <div class="form-grid" x-show="it.spare_mode === 'existing'" x-cloak>
                <div class="form-group" style="grid-column:1 / -1;">
                  <label>Select spare part *</label>
                  <select class="form-control" :name="it.spare_mode === 'existing' ? 'items['+idx+'][spare_part_id]' : null"
                          x-model="it.spare_part_id" @change="onSpareChange(idx)"
                          :required="it.spare_mode === 'existing'">
                    <option value="">Choose from catalog</option>
                    <template x-for="sp in spareParts" :key="sp.id">
                      <option :value="sp.id" x-text="spareLabel(sp)"></option>
                    </template>
                  </select>
                  <div class="muted" style="font-size:0.75rem;margin-top:0.2rem;" x-show="!spareParts.length">
                    No spare parts yet — switch to “New part”.
                  </div>
                </div>
              </div>

              <div class="form-grid" x-show="it.spare_mode === 'new'" x-cloak>
                <div class="form-group">
                  <label>Category *</label>
                  <select class="form-control" :name="it.spare_mode === 'new' ? 'items['+idx+'][spare_category_id]' : null"
                          x-model="it.spare_category_id"
                          :required="it.spare_mode === 'new'">
                    <option value="">Select category</option>
                    <template x-for="c in spareCategories" :key="c.id">
                      <option :value="c.id" x-text="c.name"></option>
                    </template>
                  </select>
                </div>
                <div class="form-group">
                  <label>Part name *</label>
                  <input class="form-control" type="text"
                         :name="it.spare_mode === 'new' ? 'items['+idx+'][new_part_name]' : null"
                         x-model="it.new_part_name" @input="onNewSpareChange(idx)"
                         placeholder="e.g. 60V 32Ah Lithium Battery"
                         :required="it.spare_mode === 'new'">
                </div>
                <div class="form-group" style="grid-column:1 / -1;">
                  <div class="muted" style="font-size:0.78rem;">New part is auto-created in Spare Parts; stock is updated on PO receive (no warehouse split).</div>
                </div>
              </div>
            </div>
          </template>

          <div class="form-grid" style="margin-top:0.5rem;">
            <div class="form-group">
              <label>Quantity *</label>
              <input class="form-control" type="number" min="1" :name="'items['+idx+'][quantity]'" x-model="it.quantity" required>
            </div>
            <div class="form-group">
              <label>Unit rate (taxable) *</label>
              <input class="form-control" type="number" step="0.01" min="0.01" :name="'items['+idx+'][unit_rate]'" x-model="it.unit_rate" required>
            </div>
            <div class="form-group">
              <label>GST % *</label>
              <input class="form-control" type="number" step="0.01" min="0" max="100"
                     :name="'items['+idx+'][gst_percent]'" x-model="it.gst_percent" required>
            </div>
            <div class="form-group">
              <label>HSN code</label>
              <input class="form-control" type="text" :name="'items['+idx+'][hsn_code]'" x-model="it.hsn_code">
            </div>
            <div class="form-group" style="grid-column:1 / -1;">
              <label>Description</label>
              <input class="form-control" type="text" :name="'items['+idx+'][description]'" x-model="it.description" placeholder="Auto-filled from selection">
            </div>
          </div>

          <div class="muted" style="font-size:0.82rem;margin-top:0.35rem;">
            Taxable: <span x-text="fmt(lineCalc(it).taxable)"></span>
            · GST <span x-text="lineCalc(it).gstRate + '%'"></span>: <span x-text="fmt(lineCalc(it).gst)"></span>
            · Line total: <strong x-text="fmt(lineCalc(it).total)"></strong>
          </div>
        </div>
      </template>
    </div>

    <div class="card" style="margin-bottom:0.85rem;">
      <h3 class="card-title">4. Payment &amp; bank (optional)</h3>
      <p class="muted" style="margin:-0.35rem 0 0.85rem;font-size:0.82rem;">Track supplier payment status. Optionally debit a bank account when payment is made now.</p>
      <div class="form-grid">
        <div class="form-group full">
          <label>Payment status *</label>
          <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:0.35rem;">
            <label style="display:flex;align-items:center;gap:0.45rem;font-weight:600;cursor:pointer;">
              <input type="radio" name="payment_status" value="unpaid" x-model="paymentStatus"> Unpaid
            </label>
            <label style="display:flex;align-items:center;gap:0.45rem;font-weight:600;cursor:pointer;">
              <input type="radio" name="payment_status" value="full" x-model="paymentStatus"> Full paid
            </label>
            <label style="display:flex;align-items:center;gap:0.45rem;font-weight:600;cursor:pointer;">
              <input type="radio" name="payment_status" value="partial" x-model="paymentStatus"> Partial payment
            </label>
          </div>
        </div>
        <template x-if="paymentStatus === 'partial'">
          <div class="form-group" style="grid-column:1 / -1;">
            <label>Amount paid now (₹) *</label>
            <input class="form-control" type="number" step="0.01" min="0.01" name="amount_paid" x-model="amountPaid" placeholder="How much paid to supplier today" required>
          </div>
        </template>
        <?php if (!empty($bankAccounts)): ?>
        <div class="form-group full" style="grid-column:1 / -1;padding-top:0.35rem;border-top:1px solid var(--border);">
          <label style="display:flex;align-items:center;gap:0.45rem;font-weight:600;cursor:pointer;margin-bottom:0.5rem;">
            <input type="checkbox" name="affect_bank" value="1" x-model="affectBank"> Debit payment from bank account
          </label>
          <template x-if="affectBank">
            <div class="form-group" style="max-width:360px;">
              <label>Bank account *</label>
              <select class="form-control" name="bank_account_id" :required="affectBank">
                <option value="">Select account</option>
                <?php foreach ($bankAccounts as $ba): ?>
                  <option value="<?= (int)$ba['id'] ?>"><?= e($ba['account_name']) ?> — <?= e($ba['bank_name']) ?> (<?= money($ba['current_balance']) ?>)</option>
                <?php endforeach; ?>
              </select>
              <p class="muted" style="margin:0.3rem 0 0;font-size:0.78rem;">Full paid debits PO total; partial debits only the amount paid now. Unpaid does not affect the bank.</p>
            </div>
          </template>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card" style="margin-bottom:0.85rem;">
      <h3 class="card-title">5. Totals &amp; notes</h3>
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
            <span>Total GST</span><span x-text="fmt(totals().gst)"></span>
          </div>
          <div class="muted" style="font-size:0.78rem;margin-bottom:0.35rem;" x-text="'@ ' + totalGstPercent + '% (CGST ' + cgstRate + '% + SGST ' + sgstRate + '%)'"></div>
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
