<?php
$productType = $productType ?? 'vehicle';
$variantMap = [];
foreach ($variants as $vv) {
    $variantMap[(string)(int)$vv['id']] = [
        'id' => (int)$vv['id'],
        'name' => $vv['name'],
        'sku' => $vv['sku'],
        'color' => $vv['color'] ?? '',
        'price' => (float)$vv['price'],
        'vehicle_name' => $vv['vehicle_name'],
        'category_name' => $vv['category_name'] ?? '',
        'battery_type' => $vv['battery_type'] ?? '',
        'battery_spec' => $vv['battery_spec'] ?? '',
        'label' => $vv['vehicle_name'] . ' — ' . $vv['name']
            . ($vv['color'] ? ' (' . $vv['color'] . ')' : ''),
    ];
}
$sparePartMap = [];
foreach ($spareParts ?? [] as $sp) {
    $sparePartMap[(string)(int)$sp['id']] = [
        'id' => (int)$sp['id'],
        'name' => $sp['name'],
        'part_number' => $sp['part_number'],
        'stock' => (int)$sp['quantity_in_stock'],
        'category_name' => $sp['category_name'] ?? '',
        'label' => ($sp['category_name'] ?? '') . ' — ' . $sp['name']
            . ' (' . $sp['part_number'] . ') · stock ' . (int)$sp['quantity_in_stock'],
    ];
}
?>
<div x-data="{
  productType: '<?= e($productType) ?>',
  orderType: 'customer',
  items: [{ variant_id: '', quantity: 1, unit_price: '' }],
  spareItems: [{ spare_part_id: '', quantity: 1, unit_price: '' }],
  variantMap: <?= htmlspecialchars(json_encode($variantMap, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>,
  sparePartMap: <?= htmlspecialchars(json_encode($sparePartMap, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>,
  color: '',
  modelType: '',
  modelName: '',
  batteryType: '',
  paymentStatus: 'full',
  paidCash: '',
  paidBank: '',
  paidLoan: '',
  bankAccountId: '',
  gstPreset: 'default',
  defaultCgst: <?= $productType === 'spare_part' ? '9' : '14' ?>,
  defaultSgst: <?= $productType === 'spare_part' ? '9' : '14' ?>,
  cgstRate: <?= $productType === 'spare_part' ? '9' : '14' ?>,
  sgstRate: <?= $productType === 'spare_part' ? '9' : '14' ?>,
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
  },
  get totalGstPercent() {
    return Math.round(((parseFloat(this.cgstRate) || 0) + (parseFloat(this.sgstRate) || 0)) * 100) / 100;
  },
  lineQty(it) {
    if (this.productType !== 'vehicle') return parseInt(it.quantity, 10) || 0;
    return this.orderType === 'customer' ? 1 : (parseInt(it.quantity, 10) || 0);
  },
  get lineSubtotal() {
    const rows = this.productType === 'spare_part' ? this.spareItems : this.items;
    return rows.reduce((sum, it) => {
      const price = parseFloat(it.unit_price) || 0;
      const qty = this.lineQty(it);
      return sum + price * qty;
    }, 0);
  },
  get taxableAmount() {
    return Math.max(0, this.lineSubtotal);
  },
  get gstAmount() {
    return Math.round(this.taxableAmount * this.totalGstPercent / 100 * 100) / 100;
  },
  get grandTotal() {
    return Math.round((this.taxableAmount + this.gstAmount) * 100) / 100;
  },
  get totalPaidNow() {
    return (parseFloat(this.paidCash) || 0) + (parseFloat(this.paidBank) || 0) + (parseFloat(this.paidLoan) || 0);
  },
  get balanceDue() {
    return Math.max(0, Math.round((this.grandTotal - this.totalPaidNow) * 100) / 100);
  },
  money(n) {
    return '₹' + (Math.round((parseFloat(n) || 0) * 100) / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  },
  get primary() {
    const id = String(this.items[0]?.variant_id || '');
    return this.variantMap[id] || null;
  },
  syncFromVariant(idx) {
    if (idx !== 0) return;
    const v = this.primary;
    if (!v) {
      this.modelType = '';
      this.modelName = '';
      this.color = '';
      return;
    }
    this.modelType = v.name;
    this.modelName = v.vehicle_name;
    this.color = v.color || '';
    if (v.battery_type && v.battery_spec) {
      this.batteryType = v.battery_type + ' · ' + v.battery_spec;
    } else if (v.battery_spec) {
      this.batteryType = v.battery_spec;
    } else if (v.battery_type) {
      this.batteryType = v.battery_type;
    } else {
      this.batteryType = '';
    }
  },
  onOrderTypeChange() {
    if (this.orderType === 'customer') {
      this.items = [{ variant_id: this.items[0]?.variant_id || '', quantity: 1, unit_price: this.items[0]?.unit_price || '' }];
      this.spareItems = [{ spare_part_id: this.spareItems[0]?.spare_part_id || '', quantity: 1, unit_price: this.spareItems[0]?.unit_price || '' }];
    }
  },
  addLine() {
    if (this.productType === 'spare_part') {
      this.spareItems = [...this.spareItems, { spare_part_id: '', quantity: 1, unit_price: '' }];
      return;
    }
    this.items = [...this.items, { variant_id: '', quantity: 1, unit_price: '' }];
  },
  removeLine(idx) {
    if (this.productType === 'spare_part') {
      if (this.spareItems.length <= 1) return;
      this.spareItems = this.spareItems.filter((_, i) => i !== idx);
      return;
    }
    if (this.items.length <= 1) return;
    this.items = this.items.filter((_, i) => i !== idx);
    this.syncFromVariant(0);
  },
  addSpareLine() { this.addLine(); },
  removeSpareLine(idx) { this.removeLine(idx); }
}">
  <div style="margin-bottom:0.75rem;"><a href="<?= url('orders') ?>">&larr; Sell Orders</a></div>

  <div class="toolbar" style="margin-bottom:1rem;">
    <div>
      <h1 class="page-title" style="margin:0;" x-text="productType === 'spare_part' ? 'Create Spare Parts Sell Order' : 'Create Vehicle Sell Order'"></h1>
      <p class="page-sub" style="margin:0.25rem 0 0;">Separate flow for vehicles and spare parts · same tax invoice rules (dealer vs customer)</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <a class="btn btn-sm" :class="productType === 'vehicle' ? 'btn-primary' : 'btn-outline'" href="<?= url('orders/create?product=vehicle') ?>">Vehicle</a>
      <a class="btn btn-sm" :class="productType === 'spare_part' ? 'btn-primary' : 'btn-outline'" href="<?= url('orders/create?product=spare_part') ?>">Spare Parts</a>
    </div>
  </div>

  <form method="post" action="<?= url('orders') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="product_type" :value="productType">

    <div class="card" style="margin-bottom:0.85rem;">
      <h3 class="card-title" x-text="productType === 'spare_part' ? '1. Sell order &amp; spare parts' : '1. Sell order &amp; vehicle'"></h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Sell order type</label>
          <select class="form-control" name="order_type" x-model="orderType" @change="onOrderTypeChange()" required>
            <?php if (!empty($isAdmin)): ?>
              <option value="dealer">Dealer Sell Order</option>
            <?php endif; ?>
            <option value="customer">Customer Sell Order</option>
          </select>
        </div>
        <div class="form-group" x-show="orderType==='dealer'" x-cloak>
          <label>Dealer *</label>
          <select class="form-control" name="dealer_id" :required="orderType==='dealer'">
            <option value="">Select dealer</option>
            <?php foreach ($dealers as $d): ?>
              <option value="<?= (int)$d['id'] ?>"><?= e($d['business_name']) ?> (<?= e($d['dealer_code'] ?? 'N/A') ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Booking No.</label>
          <input class="form-control" name="booking_no" placeholder="Optional — defaults to order no.">
        </div>
        <div class="form-group">
          <label>Date of Sale *</label>
          <input class="form-control" type="date" name="sale_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <template x-if="productType === 'vehicle' && orderType === 'customer'">
          <div class="form-group full" style="grid-column:1 / -1;padding:0.85rem 1rem;border-radius:12px;background:#f0fdf4;border:1px solid #86efac;">
            <div class="form-grid">
              <div class="form-group full">
                <label>Vehicle / Variant *</label>
                <select class="form-control" name="variant_id[0]" x-model="items[0].variant_id" @change="syncFromVariant(0)" required>
                  <option value="">Select variant</option>
                  <?php foreach ($variants as $vv): ?>
                    <option value="<?= (int)$vv['id'] ?>">
                      <?= e($vv['vehicle_name'] . ' — ' . $vv['name'] . ($vv['color'] ? ' (' . $vv['color'] . ')' : '')) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="quantity[0]" value="1">
              </div>
              <div class="form-group full">
                <label>Vehicle sell amount (₹) *</label>
                <input class="form-control" type="number" step="0.01" min="0.01" name="unit_price[0]" x-model="items[0].unit_price" required
                       placeholder="Enter the price at which you are selling this vehicle">
                <p class="muted" style="margin:0.35rem 0 0;font-size:0.82rem;">
                  This is your <strong>selling price</strong> (before GST) — not the purchase/PO cost.
                  <span x-show="primary"> Catalog reference: <span x-text="money(primary?.price || 0)"></span>.</span>
                </p>
              </div>
            </div>
          </div>
        </template>

        <template x-if="productType === 'vehicle' && orderType === 'dealer'">
          <div class="form-group full" style="grid-column:1 / -1;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;margin-bottom:0.65rem;flex-wrap:wrap;">
              <label style="margin:0;">Vehicles / variants *</label>
              <button class="btn btn-sm btn-outline" type="button" @click="addLine()">+ Add variant</button>
            </div>
            <template x-for="(it, idx) in items" :key="idx">
              <div style="display:flex;gap:0.5rem;align-items:end;margin-bottom:0.5rem;flex-wrap:wrap;">
                <div class="form-group" style="margin:0;flex:1;min-width:220px;">
                  <label x-show="idx === 0" style="font-size:0.82rem;">Variant</label>
                  <select class="form-control" :name="'variant_id['+idx+']'" x-model="it.variant_id" @change="syncFromVariant(idx)" required>
                    <option value="">Select variant</option>
                    <?php foreach ($variants as $vv): ?>
                      <option value="<?= (int)$vv['id'] ?>">
                        <?= e($vv['vehicle_name'] . ' — ' . $vv['name'] . ($vv['color'] ? ' (' . $vv['color'] . ')' : '')) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group" style="margin:0;width:120px;">
                  <label x-show="idx === 0" style="font-size:0.82rem;">Sell amount (₹) *</label>
                  <input class="form-control" type="number" step="0.01" min="0.01" :name="'unit_price['+idx+']'" x-model="it.unit_price" required placeholder="Selling price">
                </div>
                <div class="form-group" style="margin:0;width:100px;">
                  <label x-show="idx === 0" style="font-size:0.82rem;">Qty *</label>
                  <input class="form-control" type="number" min="1" :name="'quantity['+idx+']'" x-model="it.quantity" required>
                </div>
                <button class="btn btn-sm btn-danger" type="button" @click="removeLine(idx)" x-show="items.length > 1" style="margin-bottom:0.35rem;">×</button>
              </div>
            </template>
            <p class="muted" style="margin:0.25rem 0 0;font-size:0.78rem;">Enter the <strong>sell amount</strong> per line — the price you are selling at, not PO/purchase cost.</p>
          </div>
        </template>

        <template x-if="productType === 'spare_part'">
          <div class="form-group full" style="grid-column:1 / -1;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;margin-bottom:0.65rem;flex-wrap:wrap;">
              <label style="margin:0;">Spare parts / batteries *</label>
              <button class="btn btn-sm btn-outline" type="button" @click="addSpareLine()" x-show="orderType === 'dealer'">+ Add part</button>
            </div>
            <template x-for="(it, idx) in spareItems" :key="'sp-' + idx">
              <div style="display:flex;gap:0.5rem;align-items:end;margin-bottom:0.5rem;flex-wrap:wrap;">
                <div class="form-group" style="margin:0;flex:1;min-width:220px;">
                  <label x-show="idx === 0" style="font-size:0.82rem;">Part</label>
                  <select class="form-control" :name="'spare_part_id['+idx+']'" x-model="it.spare_part_id" required>
                    <option value="">Select spare part</option>
                    <?php foreach ($spareParts ?? [] as $sp): ?>
                      <option value="<?= (int)$sp['id'] ?>">
                        <?= e(($sp['category_name'] ?? '') . ' — ' . $sp['name'] . ' (' . $sp['part_number'] . ') · stock ' . (int)$sp['quantity_in_stock']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group" style="margin:0;width:120px;">
                  <label x-show="idx === 0" style="font-size:0.82rem;">Sell amount (₹) *</label>
                  <input class="form-control" type="number" step="0.01" min="0.01" :name="'unit_price['+idx+']'" x-model="it.unit_price" required placeholder="Selling price">
                </div>
                <div class="form-group" style="margin:0;width:100px;">
                  <label x-show="idx === 0" style="font-size:0.82rem;">Qty *</label>
                  <input class="form-control" type="number" min="1" :name="'quantity['+idx+']'" x-model="it.quantity" required>
                </div>
                <button class="btn btn-sm btn-danger" type="button" @click="removeSpareLine(idx)" x-show="orderType === 'dealer' && spareItems.length > 1" style="margin-bottom:0.35rem;">×</button>
              </div>
            </template>
            <p class="muted" style="margin:0.25rem 0 0;font-size:0.78rem;">Enter sell price per part — not catalog cost. Stock is reduced when the order is created.</p>
          </div>
        </template>

        <div class="form-group" x-show="productType === 'vehicle'" x-cloak>
          <label>EV Model Name</label>
          <input class="form-control" :value="modelName" readonly placeholder="From variant">
        </div>
        <div class="form-group" x-show="productType === 'vehicle'" x-cloak>
          <label>EV Model Type *</label>
          <input class="form-control" name="vehicle_model_type" x-model="modelType" readonly :required="productType === 'vehicle'" placeholder="Matches selected variant">
          <p class="muted" style="margin:0.3rem 0 0;font-size:0.78rem;">Locked to the selected variant name</p>
        </div>
        <div class="form-group" x-show="productType === 'vehicle'" x-cloak>
          <label>Model Color *</label>
          <input class="form-control" name="color" x-model="color" :required="productType === 'vehicle'" placeholder="From variant (editable)">
        </div>
        <div class="form-group" x-show="productType === 'vehicle'" x-cloak>
          <label>Expected Delivery</label>
          <input class="form-control" type="date" name="expected_delivery_date">
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:0.85rem;">
      <h3 class="card-title">Billing &amp; GST</h3>
      <div class="form-grid">
        <div class="form-group" style="max-width:280px;">
          <label>Billing location *</label>
          <select class="form-control" name="billing_location" required>
            <option value="kokamthan">Kokamthan</option>
            <option value="kopargaon">Kopargaon</option>
          </select>
          <p class="muted" style="margin:0.3rem 0 0;font-size:0.78rem;">Branch address printed on the tax invoice</p>
        </div>
        <div class="form-group">
          <label>GST option *</label>
          <select class="form-control" x-model="gstPreset" @change="applyGstPreset()">
            <option value="default">Default — <?= $productType === 'spare_part' ? '18% (9+9) spare parts' : '28% (14+14) vehicles' ?></option>
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
            <input class="form-control" type="number" step="0.01" min="0" max="100" name="cgst_rate" x-model="cgstRate" required>
          </div>
        </template>
        <template x-if="gstPreset === 'custom'">
          <div class="form-group">
            <label>SGST % *</label>
            <input class="form-control" type="number" step="0.01" min="0" max="100" name="sgst_rate" x-model="sgstRate" required>
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
        <div class="form-group full" x-show="productType === 'vehicle' && lineSubtotal > 0" style="grid-column:1 / -1;">
          <div style="padding:0.75rem 1rem;border-radius:10px;background:#f8fafc;border:1px solid var(--border);max-width:420px;">
            <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:0.3rem;">
              <span>Vehicle sell amount</span><strong x-text="money(lineSubtotal)"></strong>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:0.3rem;">
              <span x-text="'GST @ ' + totalGstPercent + '%'"></span><span x-text="money(gstAmount)"></span>
            </div>
            <div style="display:flex;justify-content:space-between;font-weight:800;padding-top:0.4rem;border-top:1px solid var(--border);">
              <span>Invoice total</span><span x-text="money(grandTotal)"></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:0.85rem;">
      <h3 class="card-title">2. Buyer (same for dealer &amp; customer)</h3>
      <div class="form-grid">
        <div class="form-group"><label>Cust. Name *</label><input class="form-control" name="customer_name" required></div>
        <div class="form-group"><label>Mob. *</label><input class="form-control contact-input" name="customer_phone" type="tel" maxlength="11" inputmode="numeric" placeholder="98765 43210" required></div>
        <div class="form-group"><label>Email</label><input class="form-control" name="customer_email" type="email"></div>
        <div class="form-group"><label>Aadhar No.</label><input class="form-control aadhar-input" name="customer_aadhaar" maxlength="14" inputmode="numeric" placeholder="1234 5678 9012"></div>
        <div class="form-group"><label>PAN No.</label><input class="form-control" name="customer_pan"></div>
        <div class="form-group full"><label>Add. (Address)</label><textarea class="form-control" name="customer_address" rows="2"></textarea></div>
        <div class="form-group full" x-show="orderType==='dealer'" x-cloak>
          <label>Delivery Address</label>
          <textarea class="form-control" name="delivery_address" rows="2" placeholder="If different from buyer address"></textarea>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:0.85rem;" x-show="productType === 'vehicle'" x-cloak>
      <h3 class="card-title">3. Parts &amp; warranty (printed on tax invoice)</h3>
      <p class="muted" style="margin:-0.35rem 0 0.85rem;font-size:0.82rem;">Each part has its own number and warranty — fill one block at a time.</p>
      <?php
        $warrantyOpts = ['6 months', '12 months', '18 months', '24 months', '36 months', 'N/A'];
        $wSelect = static function (string $name, string $default = '12 months') use ($warrantyOpts): void {
            echo '<select class="form-control" name="' . e($name) . '" required>';
            echo '<option value="">Select warranty</option>';
            foreach ($warrantyOpts as $opt) {
                $sel = $opt === $default ? ' selected' : '';
                echo '<option value="' . e($opt) . '"' . $sel . '>' . e($opt) . '</option>';
            }
            echo '</select>';
        };
      ?>
      <style>
        .ow-parts{display:flex;flex-direction:column;gap:.7rem}
        .ow-block{border:1px solid var(--border);border-radius:12px;padding:.75rem .85rem;background:#f8fffd}
        .ow-block h4{margin:0 0 .65rem;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#0f766e}
        .ow-block .form-grid{gap:.65rem}
        .ow-block.chassis{background:#fff}
      </style>
      <div class="ow-parts">
        <div class="ow-block chassis">
          <h4>Chassis</h4>
          <div class="form-grid">
            <div class="form-group full"><label>Chassis No. *</label><input class="form-control" name="chassis_no" :required="productType === 'vehicle'"></div>
          </div>
        </div>

        <div class="ow-block">
          <h4>Motor</h4>
          <div class="form-grid">
            <div class="form-group"><label>Motor No. *</label><input class="form-control" name="motor_no" :required="productType === 'vehicle'"></div>
            <div class="form-group"><label>Warranty *</label><?php $wSelect('motor_warranty', '12 months'); ?></div>
          </div>
        </div>

        <div class="ow-block">
          <h4>Battery</h4>
          <div class="form-grid">
            <div class="form-group"><label>Battery Type *</label><input class="form-control" name="battery_capacity" x-model="batteryType" placeholder="e.g. Lithium 60V" :required="productType === 'vehicle'"></div>
            <div class="form-group"><label>Battery No. *</label><input class="form-control" name="battery_no" :required="productType === 'vehicle'"></div>
            <div class="form-group"><label>Warranty *</label><?php $wSelect('battery_warranty', '36 months'); ?></div>
          </div>
        </div>

        <div class="ow-block">
          <h4>Controller</h4>
          <div class="form-grid">
            <div class="form-group"><label>Controller No. *</label><input class="form-control" name="controller_no" :required="productType === 'vehicle'"></div>
            <div class="form-group"><label>Warranty *</label><?php $wSelect('controller_warranty', '12 months'); ?></div>
          </div>
        </div>

        <div class="ow-block">
          <h4>Charger</h4>
          <div class="form-grid">
            <div class="form-group"><label>Charger No. *</label><input class="form-control" name="charger_no" :required="productType === 'vehicle'"></div>
            <div class="form-group"><label>Warranty *</label><?php $wSelect('charger_warranty', '12 months'); ?></div>
          </div>
        </div>

        <div class="ow-block chassis">
          <h4>Finance (optional)</h4>
          <div class="form-grid">
            <div class="form-group full"><label>H.P. Name</label><input class="form-control" name="hp_name" placeholder="Hire purchase / finance partner"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:0.85rem;">
      <h3 class="card-title">4. Payment</h3>
      <div class="form-grid">
        <div class="form-group full">
          <label>Payment status *</label>
          <div style="display:flex;gap:1.25rem;flex-wrap:wrap;margin-top:0.35rem;">
            <label style="display:flex;align-items:center;gap:0.45rem;font-weight:600;cursor:pointer;">
              <input type="radio" name="payment_status" value="full" x-model="paymentStatus"> Full paid
            </label>
            <label style="display:flex;align-items:center;gap:0.45rem;font-weight:600;cursor:pointer;">
              <input type="radio" name="payment_status" value="partial" x-model="paymentStatus"> Partial paid
            </label>
          </div>
        </div>

        <div class="form-group">
          <label>Cash — amount paid (₹)</label>
          <input class="form-control" type="number" step="0.01" min="0" name="paid_cash_amount" x-model="paidCash" placeholder="0">
        </div>
        <div class="form-group">
          <label>Bank (online) — amount paid (₹)</label>
          <input class="form-control" type="number" step="0.01" min="0" name="paid_bank_amount" x-model="paidBank" placeholder="0">
        </div>
        <div class="form-group">
          <label>From loan — amount given (₹)</label>
          <input class="form-control" type="number" step="0.01" min="0" name="paid_loan_amount" x-model="paidLoan" placeholder="0">
        </div>

        <?php if (!empty($bankAccounts)): ?>
        <div class="form-group full" x-show="parseFloat(paidBank) > 0" x-cloak>
          <label>Bank account (for online payment) *</label>
          <select class="form-control" name="bank_account_id" x-model="bankAccountId" :required="parseFloat(paidBank) > 0">
            <option value="">Select account</option>
            <?php foreach ($bankAccounts as $ba): ?>
              <option value="<?= (int)$ba['id'] ?>"><?= e($ba['account_name']) ?> — <?= e($ba['bank_name']) ?> (<?= money($ba['current_balance']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="form-group full">
          <div style="padding:0.85rem 1rem;border-radius:12px;background:var(--surface-2);border:1px solid var(--border);max-width:380px;">
            <div class="muted" style="display:flex;justify-content:space-between;margin-bottom:0.35rem;font-size:0.82rem;">
              <span>Order total</span><span x-text="money(grandTotal)"></span>
            </div>
            <div class="muted" style="display:flex;justify-content:space-between;margin-bottom:0.35rem;font-size:0.82rem;">
              <span>Cash + bank + loan</span><span x-text="money(totalPaidNow)"></span>
            </div>
            <div style="display:flex;justify-content:space-between;font-weight:800;padding-top:0.5rem;border-top:1px solid var(--border);color:#b45309;">
              <span>Balance due</span><span x-text="money(balanceDue)"></span>
            </div>
            <p class="muted" style="margin:0.5rem 0 0;font-size:0.75rem;" x-show="paymentStatus === 'full'">
              Full paid: cash + bank + loan must equal order total.
            </p>
            <p class="muted" style="margin:0.5rem 0 0;font-size:0.75rem;" x-show="paymentStatus === 'partial'">
              Partial paid: remaining balance is due later.
            </p>
          </div>
        </div>

        <div class="form-group full">
          <label>Notes</label>
          <textarea class="form-control" name="notes" rows="2"></textarea>
        </div>
      </div>
    </div>

    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <a class="btn btn-outline" href="<?= url('orders') ?>">Cancel</a>
      <button class="btn btn-primary" type="submit">Create sell order &amp; tax invoice</button>
    </div>
  </form>
</div>
