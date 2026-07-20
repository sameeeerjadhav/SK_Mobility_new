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
            . ($vv['color'] ? ' (' . $vv['color'] . ')' : '')
            . ' / ' . money($vv['price']),
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
            . ' (' . $sp['part_number'] . ') / ' . money($sp['unit_price'])
            . ' · stock ' . (int)$sp['quantity_in_stock'],
    ];
}
?>
<div x-data="{
  productType: '<?= e($productType) ?>',
  orderType: 'customer',
  items: [{ variant_id: '', quantity: 1 }],
  spareItems: [{ spare_part_id: '', quantity: 1 }],
  variantMap: <?= htmlspecialchars(json_encode($variantMap, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>,
  sparePartMap: <?= htmlspecialchars(json_encode($sparePartMap, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>,
  color: '',
  modelType: '',
  modelName: '',
  batteryType: '',
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
      this.items = [{ variant_id: this.items[0]?.variant_id || '', quantity: 1 }];
      this.spareItems = [{ spare_part_id: this.spareItems[0]?.spare_part_id || '', quantity: 1 }];
    }
  },
  addLine() {
    if (this.productType === 'spare_part') {
      this.spareItems = [...this.spareItems, { spare_part_id: '', quantity: 1 }];
      return;
    }
    this.items = [...this.items, { variant_id: '', quantity: 1 }];
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
        <div class="form-group full" x-show="productType === 'vehicle' && orderType === 'customer'" x-cloak>
          <label>Vehicle / Variant *</label>
          <select class="form-control" name="variant_id[0]" x-model="items[0].variant_id" @change="syncFromVariant(0)" required>
            <option value="">Select variant</option>
            <?php foreach ($variants as $vv): ?>
              <option value="<?= (int)$vv['id'] ?>">
                <?= e($vv['vehicle_name'] . ' — ' . $vv['name'] . ($vv['color'] ? ' (' . $vv['color'] . ')' : '') . ' / ' . money($vv['price'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="quantity[0]" value="1">
        </div>

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
                        <?= e($vv['vehicle_name'] . ' — ' . $vv['name'] . ($vv['color'] ? ' (' . $vv['color'] . ')' : '') . ' / ' . money($vv['price'])) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group" style="margin:0;width:100px;">
                  <label x-show="idx === 0" style="font-size:0.82rem;">Qty *</label>
                  <input class="form-control" type="number" min="1" :name="'quantity['+idx+']'" x-model="it.quantity" required>
                </div>
                <button class="btn btn-sm btn-danger" type="button" @click="removeLine(idx)" x-show="items.length > 1" style="margin-bottom:0.35rem;">×</button>
              </div>
            </template>
            <p class="muted" style="margin:0.25rem 0 0;font-size:0.78rem;">Each line appears on the tax invoice. Add multiple variants or increase quantity per line.</p>
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
                        <?= e(($sp['category_name'] ?? '') . ' — ' . $sp['name'] . ' (' . $sp['part_number'] . ') / ' . money($sp['unit_price']) . ' · stock ' . (int)$sp['quantity_in_stock']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group" style="margin:0;width:100px;">
                  <label x-show="idx === 0" style="font-size:0.82rem;">Qty *</label>
                  <input class="form-control" type="number" min="1" :name="'quantity['+idx+']'" x-model="it.quantity" required>
                </div>
                <button class="btn btn-sm btn-danger" type="button" @click="removeSpareLine(idx)" x-show="orderType === 'dealer' && spareItems.length > 1" style="margin-bottom:0.35rem;">×</button>
              </div>
            </template>
            <p class="muted" style="margin:0.25rem 0 0;font-size:0.78rem;">Each line appears on the tax invoice. Stock is reduced when the order is created.</p>
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
      <h3 class="card-title">Billing</h3>
      <div class="form-grid">
        <div class="form-group" style="max-width:280px;">
          <label>Billing location *</label>
          <select class="form-control" name="billing_location" required>
            <option value="kokamthan">Kokamthan</option>
            <option value="kopargaon">Kopargaon</option>
          </select>
          <p class="muted" style="margin:0.3rem 0 0;font-size:0.78rem;">Branch address printed on the tax invoice</p>
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
      <h3 class="card-title">4. Payment &amp; incentives</h3>
      <div class="form-grid">
        <div class="form-group"><label>Loan Amount (₹)</label><input class="form-control" type="number" step="0.01" name="loan_amount" value="0"></div>
        <div class="form-group"><label>Extra Disc. (₹)</label><input class="form-control" type="number" step="0.01" name="discount_amount" value="0"></div>
        <div class="form-group"><label>PM E-DRIVE Incentive (₹)</label><input class="form-control" type="number" step="0.01" name="pm_drive_incentive" value="0"></div>
        <div class="form-group"><label>State Subsidy (₹)</label><input class="form-control" type="number" step="0.01" name="state_subsidy" value="0"></div>
        <div class="form-group full" style="display:flex;gap:1.25rem;align-items:center;padding-top:0.25rem;">
          <label style="display:flex;align-items:center;gap:0.4rem;font-weight:600;"><input type="checkbox" name="paid_cash" value="1"> Paid in Cash</label>
          <label style="display:flex;align-items:center;gap:0.4rem;font-weight:600;"><input type="checkbox" name="paid_cheque" value="1"> Paid in Cheque</label>
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
