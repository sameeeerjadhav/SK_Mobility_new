<div x-data="{
  createOpen: <?= !empty($successOrder) ? 'true' : 'false' ?>,
  orderOpen: false,
  orderType: 'dealer',
  items: [{ variant_id: '', quantity: 1 }]
}">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Orders</h1>
      <p class="page-sub">Dealer bulk & customer orders</p>
    </div>
    <?php if ($canManage): ?>
      <button class="btn btn-primary" type="button" @click="orderOpen=true">+ Create Order</button>
    <?php endif; ?>
  </div>

  <?php if (!empty($successOrder)): ?>
  <div class="modal-backdrop open" x-show="createOpen" @click.self="createOpen=false">
    <div class="modal">
      <div class="modal-header"><h3 class="modal-title">Order Created</h3></div>
      <div class="modal-body">
        <p>Order number: <strong><?= e($successOrder['order_number']) ?></strong></p>
        <p>Bill number: <strong><?= e($successOrder['bill_number']) ?></strong></p>
        <p>Total: <strong><?= money($successOrder['total_amount']) ?></strong></p>
      </div>
      <div class="modal-footer">
        <a class="btn btn-outline" href="<?= url('billing/' . $successOrder['bill_id'] . '/pdf') ?>" target="_blank">Download Bill PDF</a>
        <a class="btn btn-primary" href="<?= url('orders/' . $successOrder['order_id']) ?>">View Order</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($isAdmin): ?>
  <div class="tabs">
    <a class="tab <?= $orderType === '' ? 'active' : '' ?>" href="<?= url('orders') ?>">All</a>
    <a class="tab <?= $orderType === 'dealer' ? 'active' : '' ?>" href="<?= url('orders?order_type=dealer') ?>">Dealer Orders</a>
    <a class="tab <?= $orderType === 'customer' ? 'active' : '' ?>" href="<?= url('orders?order_type=customer') ?>">Customer Orders</a>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Order #</th>
            <th>Dealer / Customer</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Date</th>
            <th>Tracking</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><?= e($o['order_number']) ?></td>
            <td><?= e($o['business_name'] ?? $o['customer_name'] ?? '—') ?></td>
            <td><?= money($o['total_amount']) ?></td>
            <td><?= status_chip($o['status']) ?></td>
            <td><?= india_date($o['created_at']) ?></td>
            <td><?= e($o['tracking_number'] ?? '—') ?></td>
            <td><a class="btn btn-sm btn-outline" href="<?= url('orders/' . $o['id']) ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="7" class="muted">No orders found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($canManage): ?>
  <div class="modal-backdrop" :class="{ open: orderOpen }" @click.self="orderOpen=false">
    <div class="modal modal-lg">
      <form method="post" action="<?= url('orders') ?>">
        <?= csrf_field() ?>
        <div class="modal-header">
          <h3 class="modal-title">Create Order</h3>
          <button type="button" class="btn btn-sm btn-outline" @click="orderOpen=false">Close</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label>Order Type</label>
            <select class="form-control" name="order_type" x-model="orderType">
              <option value="dealer">Dealer Order</option>
              <option value="customer">Customer Order</option>
            </select>
          </div>

          <div x-show="orderType==='dealer'">
            <div class="form-group">
              <label>Dealer</label>
              <select class="form-control" name="dealer_id">
                <option value="">Select dealer</option>
                <?php foreach ($dealers as $d): ?>
                  <option value="<?= (int)$d['id'] ?>"><?= e($d['business_name']) ?> (<?= e($d['dealer_code'] ?? 'N/A') ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Delivery Address</label>
              <textarea class="form-control" name="delivery_address" rows="2"></textarea>
            </div>
          </div>

          <div x-show="orderType==='customer'" style="display:none;">
            <div class="form-grid">
              <div class="form-group"><label>Customer Name</label><input class="form-control" name="customer_name"></div>
              <div class="form-group"><label>Phone</label><input class="form-control" name="customer_phone"></div>
              <div class="form-group"><label>Email</label><input class="form-control" name="customer_email"></div>
              <div class="form-group"><label>Aadhaar</label><input class="form-control" name="customer_aadhaar"></div>
              <div class="form-group"><label>PAN</label><input class="form-control" name="customer_pan"></div>
              <div class="form-group full"><label>Address</label><textarea class="form-control" name="customer_address" rows="2"></textarea></div>
              <div class="form-group"><label>Chassis No</label><input class="form-control" name="chassis_no"></div>
              <div class="form-group"><label>Motor No</label><input class="form-control" name="motor_no"></div>
              <div class="form-group"><label>Battery</label><input class="form-control" name="battery_capacity"></div>
              <div class="form-group"><label>Color</label><input class="form-control" name="color"></div>
              <div class="form-group"><label>PM E-DRIVE Incentive (₹)</label><input class="form-control" type="number" step="0.01" name="pm_drive_incentive" value="0"></div>
              <div class="form-group"><label>State Subsidy (₹)</label><input class="form-control" type="number" step="0.01" name="state_subsidy" value="0"></div>
            </div>
          </div>

          <h4 style="margin:1rem 0 0.5rem;">Items</h4>
          <template x-for="(item, index) in items" :key="index">
            <div class="form-grid" style="margin-bottom:0.5rem;">
              <div class="form-group" style="grid-column: span 1;">
                <label>Variant</label>
                <select class="form-control" :name="'variant_id['+index+']'" x-model="item.variant_id" required>
                  <option value="">Select</option>
                  <?php foreach ($variants as $vv): ?>
                    <option value="<?= (int)$vv['id'] ?>"><?= e($vv['vehicle_name'] . ' — ' . $vv['name'] . ' / ' . money($vv['price'])) ?></option>
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
          <div class="form-group">
            <label>Expected Delivery</label>
            <input class="form-control" type="date" name="expected_delivery_date">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="orderOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Create Order</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
