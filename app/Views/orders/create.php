<div x-data="{
  orderType: '<?= Auth::role() === 'dealer' ? 'dealer' : 'customer' ?>',
  items: [{ variant_id: '', quantity: 1 }]
}">
  <div style="margin-bottom:0.75rem;"><a href="<?= url('orders') ?>">&larr; Orders</a></div>

  <div class="toolbar" style="margin-bottom:1rem;">
    <div>
      <h1 class="page-title" style="margin:0;">Create Order</h1>
      <p class="page-sub" style="margin:0.25rem 0 0;">Fill tax-invoice vehicle details for customer sales</p>
    </div>
  </div>

  <form method="post" action="<?= url('orders') ?>">
    <?= csrf_field() ?>

    <div class="card" style="margin-bottom:0.85rem;">
      <h3 class="card-title">Order type</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Type</label>
          <select class="form-control" name="order_type" x-model="orderType" required>
            <?php if ($isAdmin): ?>
              <option value="dealer">Dealer Order</option>
            <?php endif; ?>
            <option value="customer" selected>Customer Order (Tax Invoice)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Booking No.</label>
          <input class="form-control" name="booking_no" placeholder="Optional — defaults to order no.">
        </div>
        <div class="form-group">
          <label>Expected Delivery</label>
          <input class="form-control" type="date" name="expected_delivery_date">
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:0.85rem;" x-show="orderType==='dealer'" x-cloak>
      <h3 class="card-title">Dealer details</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Dealer</label>
          <select class="form-control" name="dealer_id">
            <option value="">Select dealer</option>
            <?php foreach ($dealers as $d): ?>
              <option value="<?= (int)$d['id'] ?>"><?= e($d['business_name']) ?> (<?= e($d['dealer_code'] ?? 'N/A') ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full">
          <label>Delivery Address</label>
          <textarea class="form-control" name="delivery_address" rows="2"></textarea>
        </div>
      </div>
    </div>

    <div x-show="orderType==='customer'" x-cloak>
      <div class="card" style="margin-bottom:0.85rem;">
        <h3 class="card-title">Customer (Tax Invoice)</h3>
        <div class="form-grid">
          <div class="form-group"><label>Cust. Name *</label><input class="form-control" name="customer_name" required></div>
          <div class="form-group"><label>Mob. *</label><input class="form-control" name="customer_phone" required></div>
          <div class="form-group"><label>Email</label><input class="form-control" name="customer_email" type="email"></div>
          <div class="form-group"><label>Aadhar No.</label><input class="form-control" name="customer_aadhaar"></div>
          <div class="form-group"><label>PAN No.</label><input class="form-control" name="customer_pan"></div>
          <div class="form-group full"><label>Add. (Address)</label><textarea class="form-control" name="customer_address" rows="2"></textarea></div>
        </div>
      </div>

      <div class="card" style="margin-bottom:0.85rem;">
        <h3 class="card-title">Vehicle details (printed on bill)</h3>
        <p class="muted" style="margin:-0.35rem 0 0.85rem;font-size:0.82rem;">These fields appear on the SAI KUBER tax invoice. Required for customer orders.</p>
        <div class="form-grid">
          <div class="form-group"><label>EV Model Type</label><input class="form-control" name="vehicle_model_type" placeholder="e.g. Electric Scooter"></div>
          <div class="form-group"><label>Model Color *</label><input class="form-control" name="color" required></div>
          <div class="form-group"><label>Date of Sale *</label><input class="form-control" type="date" name="sale_date" value="<?= date('Y-m-d') ?>" required></div>
          <div class="form-group full"><label>Chassis No. *</label><input class="form-control" name="chassis_no" required></div>
          <div class="form-group"><label>Motor No. *</label><input class="form-control" name="motor_no" required></div>
          <div class="form-group"><label>Motor Warrenty *</label><input class="form-control" name="motor_warranty" placeholder="e.g. 12 months" required></div>
          <div class="form-group"><label>Battery Type *</label><input class="form-control" name="battery_capacity" placeholder="e.g. Lithium 60V" required></div>
          <div class="form-group"><label>Battery No. *</label><input class="form-control" name="battery_no" required></div>
          <div class="form-group"><label>Battery Warrenty *</label><input class="form-control" name="battery_warranty" required></div>
          <div class="form-group"><label>Controller No. *</label><input class="form-control" name="controller_no" required></div>
          <div class="form-group"><label>Controller Warrenty *</label><input class="form-control" name="controller_warranty" required></div>
          <div class="form-group"><label>Charger No. *</label><input class="form-control" name="charger_no" required></div>
          <div class="form-group"><label>Charger Warrenty *</label><input class="form-control" name="charger_warranty" required></div>
          <div class="form-group"><label>H.P. Name (Finance)</label><input class="form-control" name="hp_name"></div>
        </div>
      </div>

      <div class="card" style="margin-bottom:0.85rem;">
        <h3 class="card-title">Payment &amp; incentives</h3>
        <div class="form-grid">
          <div class="form-group"><label>Loan Amount (₹)</label><input class="form-control" type="number" step="0.01" name="loan_amount" value="0"></div>
          <div class="form-group"><label>Extra Disc. (₹)</label><input class="form-control" type="number" step="0.01" name="discount_amount" value="0"></div>
          <div class="form-group"><label>PM E-DRIVE Incentive (₹)</label><input class="form-control" type="number" step="0.01" name="pm_drive_incentive" value="0"></div>
          <div class="form-group"><label>State Subsidy (₹)</label><input class="form-control" type="number" step="0.01" name="state_subsidy" value="0"></div>
          <div class="form-group full" style="display:flex;gap:1.25rem;align-items:center;padding-top:0.25rem;">
            <label style="display:flex;align-items:center;gap:0.4rem;font-weight:600;"><input type="checkbox" name="paid_cash" value="1"> Paid in Cash</label>
            <label style="display:flex;align-items:center;gap:0.4rem;font-weight:600;"><input type="checkbox" name="paid_cheque" value="1"> Paid in Cheque</label>
          </div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:0.85rem;">
      <h3 class="card-title">Items</h3>
      <template x-for="(item, index) in items" :key="index">
        <div class="form-grid" style="margin-bottom:0.5rem;">
          <div class="form-group">
            <label>Variant *</label>
            <select class="form-control" :name="'variant_id['+index+']'" x-model="item.variant_id" required>
              <option value="">Select vehicle variant</option>
              <?php foreach ($variants as $vv): ?>
                <option value="<?= (int)$vv['id'] ?>"><?= e($vv['vehicle_name'] . ' — ' . $vv['name'] . ($vv['color'] ? ' (' . $vv['color'] . ')' : '') . ' / ' . money($vv['price'])) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Qty</label>
            <input class="form-control" type="number" min="1" :name="'quantity['+index+']'" x-model="item.quantity">
          </div>
        </div>
      </template>
      <button type="button" class="btn btn-outline btn-sm" @click="items.push({variant_id:'',quantity:1})">+ Add item</button>
      <div class="form-group" style="margin-top:1rem;">
        <label>Notes</label>
        <textarea class="form-control" name="notes" rows="2"></textarea>
      </div>
    </div>

    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <a class="btn btn-outline" href="<?= url('orders') ?>">Cancel</a>
      <button class="btn btn-primary" type="submit">Create order &amp; tax invoice</button>
    </div>
  </form>
</div>
