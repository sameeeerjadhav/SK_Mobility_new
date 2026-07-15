<?php
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
        'battery_capacity_kwh' => $vv['battery_capacity_kwh'] ?? '',
        'label' => $vv['vehicle_name'] . ' — ' . $vv['name']
            . ($vv['color'] ? ' (' . $vv['color'] . ')' : '')
            . ' / ' . money($vv['price']),
    ];
}
?>
<div x-data="{
  orderType: 'customer',
  items: [{ variant_id: '', quantity: 1 }],
  variantMap: <?= htmlspecialchars(json_encode($variantMap, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>,
  color: '',
  modelType: '',
  modelName: '',
  batteryType: '',
  get primary() {
    const id = String(this.items[0]?.variant_id || '');
    return this.variantMap[id] || null;
  },
  syncFromVariant() {
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
    if (!this.batteryType && v.battery_capacity_kwh) {
      this.batteryType = v.battery_capacity_kwh + ' kWh';
    }
  }
}">
  <div style="margin-bottom:0.75rem;"><a href="<?= url('orders') ?>">&larr; Orders</a></div>

  <div class="toolbar" style="margin-bottom:1rem;">
    <div>
      <h1 class="page-title" style="margin:0;">Create Order</h1>
      <p class="page-sub" style="margin:0.25rem 0 0;">Same tax-invoice fields for dealer &amp; customer · model type follows the variant</p>
    </div>
  </div>

  <form method="post" action="<?= url('orders') ?>">
    <?= csrf_field() ?>

    <div class="card" style="margin-bottom:0.85rem;">
      <h3 class="card-title">1. Order &amp; vehicle</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Order type</label>
          <select class="form-control" name="order_type" x-model="orderType" required>
            <?php if (!empty($isAdmin)): ?>
              <option value="dealer">Dealer Order</option>
            <?php endif; ?>
            <option value="customer">Customer Order</option>
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
        <div class="form-group full">
          <label>Vehicle / Variant *</label>
          <select class="form-control" name="variant_id[0]" x-model="items[0].variant_id" @change="syncFromVariant()" required>
            <option value="">Select variant</option>
            <?php foreach ($variants as $vv): ?>
              <option value="<?= (int)$vv['id'] ?>">
                <?= e($vv['vehicle_name'] . ' — ' . $vv['name'] . ($vv['color'] ? ' (' . $vv['color'] . ')' : '') . ' / ' . money($vv['price'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="quantity[0]" value="1">
        </div>
        <div class="form-group">
          <label>EV Model Name</label>
          <input class="form-control" :value="modelName" readonly placeholder="From variant">
        </div>
        <div class="form-group">
          <label>EV Model Type *</label>
          <input class="form-control" name="vehicle_model_type" x-model="modelType" readonly required placeholder="Matches selected variant">
          <p class="muted" style="margin:0.3rem 0 0;font-size:0.78rem;">Locked to the selected variant name</p>
        </div>
        <div class="form-group">
          <label>Model Color *</label>
          <input class="form-control" name="color" x-model="color" required placeholder="From variant (editable)">
        </div>
        <div class="form-group">
          <label>Expected Delivery</label>
          <input class="form-control" type="date" name="expected_delivery_date">
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:0.85rem;">
      <h3 class="card-title">2. Buyer (same for dealer &amp; customer)</h3>
      <div class="form-grid">
        <div class="form-group"><label>Cust. Name *</label><input class="form-control" name="customer_name" required></div>
        <div class="form-group"><label>Mob. *</label><input class="form-control" name="customer_phone" required></div>
        <div class="form-group"><label>Email</label><input class="form-control" name="customer_email" type="email"></div>
        <div class="form-group"><label>Aadhar No.</label><input class="form-control" name="customer_aadhaar"></div>
        <div class="form-group"><label>PAN No.</label><input class="form-control" name="customer_pan"></div>
        <div class="form-group full"><label>Add. (Address)</label><textarea class="form-control" name="customer_address" rows="2"></textarea></div>
        <div class="form-group full" x-show="orderType==='dealer'" x-cloak>
          <label>Delivery Address</label>
          <textarea class="form-control" name="delivery_address" rows="2" placeholder="If different from buyer address"></textarea>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:0.85rem;">
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
            <div class="form-group full"><label>Chassis No. *</label><input class="form-control" name="chassis_no" required></div>
          </div>
        </div>

        <div class="ow-block">
          <h4>Motor</h4>
          <div class="form-grid">
            <div class="form-group"><label>Motor No. *</label><input class="form-control" name="motor_no" required></div>
            <div class="form-group"><label>Warranty *</label><?php $wSelect('motor_warranty', '12 months'); ?></div>
          </div>
        </div>

        <div class="ow-block">
          <h4>Battery</h4>
          <div class="form-grid">
            <div class="form-group"><label>Battery Type *</label><input class="form-control" name="battery_capacity" x-model="batteryType" placeholder="e.g. Lithium 60V" required></div>
            <div class="form-group"><label>Battery No. *</label><input class="form-control" name="battery_no" required></div>
            <div class="form-group"><label>Warranty *</label><?php $wSelect('battery_warranty', '36 months'); ?></div>
          </div>
        </div>

        <div class="ow-block">
          <h4>Controller</h4>
          <div class="form-grid">
            <div class="form-group"><label>Controller No. *</label><input class="form-control" name="controller_no" required></div>
            <div class="form-group"><label>Warranty *</label><?php $wSelect('controller_warranty', '12 months'); ?></div>
          </div>
        </div>

        <div class="ow-block">
          <h4>Charger</h4>
          <div class="form-grid">
            <div class="form-group"><label>Charger No. *</label><input class="form-control" name="charger_no" required></div>
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
      <button class="btn btn-primary" type="submit">Create order &amp; tax invoice</button>
    </div>
  </form>
</div>
