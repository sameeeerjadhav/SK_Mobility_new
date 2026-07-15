<div style="margin-bottom:1rem;"><a href="<?= url('orders') ?>">&larr; Orders</a></div>

<div class="card" style="margin-bottom:1rem;">
  <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
      <h1 class="page-title" style="margin:0;"><?= e($order['order_number']) ?></h1>
      <p class="page-sub" style="margin:0.35rem 0 0;"><?= status_chip($order['status']) ?> · <?= e(ucfirst($order['order_type'])) ?> order</p>
    </div>
    <div style="display:flex;gap:0.5rem;">
      <a class="btn btn-outline" href="<?= url('orders/' . $order['id'] . '/print') ?>" target="_blank">Print</a>
      <?php if ($bill): ?>
        <a class="btn btn-primary" href="<?= url('billing/' . $bill['id'] . '/pdf') ?>" target="_blank">Bill <?= e($bill['bill_number']) ?></a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h3 class="card-title">Details</h3>
    <?php if ($order['order_type'] === 'dealer'): ?>
      <p><strong>Dealer:</strong> <?= e($order['business_name'] ?? '—') ?> (<?= e($order['dealer_code'] ?? '') ?>)</p>
      <p><strong>Delivery:</strong> <?= e($order['delivery_address'] ?? '—') ?></p>
    <?php else: ?>
      <p><strong>Customer:</strong> <?= e($order['customer_name']) ?> · <?= e($order['customer_phone']) ?></p>
      <p><strong>Address:</strong> <?= e($order['customer_address'] ?? '—') ?></p>
      <p><strong>Chassis / Motor:</strong> <?= e($order['chassis_no'] ?? '—') ?> / <?= e($order['motor_no'] ?? '—') ?></p>
      <p><strong>Subsidies:</strong> PM <?= money($order['pm_drive_incentive']) ?> · State <?= money($order['state_subsidy']) ?></p>
    <?php endif; ?>
    <p><strong>Subtotal:</strong> <?= money($order['subtotal']) ?></p>
    <p><strong>GST (28%):</strong> <?= money($order['tax_amount']) ?></p>
    <p><strong>Total:</strong> <?= money($order['total_amount']) ?></p>

    <h3 class="card-title" style="margin-top:1.25rem;">Items</h3>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Vehicle</th><th>Variant</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= e($it['vehicle_name']) ?></td>
            <td><?= e($it['variant_name']) ?> <?= $it['color'] ? '(' . e($it['color']) . ')' : '' ?></td>
            <td><?= (int)$it['quantity'] ?></td>
            <td><?= money($it['unit_price']) ?></td>
            <td><?= money($it['total_price']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div>
    <div class="card">
      <h3 class="card-title">Status history</h3>
      <ul style="padding-left:1.1rem;margin:0;">
        <?php foreach ($history as $h): ?>
          <li style="margin-bottom:0.5rem;">
            <?= status_chip($h['status']) ?>
            <span class="muted"><?= india_datetime($h['created_at']) ?> · <?= e($h['first_name'] . ' ' . $h['last_name']) ?></span>
            <?php if ($h['notes']): ?><div><?= e($h['notes']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <?php if ($canManage): ?>
    <div class="card" style="margin-top:1rem;">
      <h3 class="card-title">Update status</h3>
      <form method="post" action="<?= url('orders/' . $order['id'] . '/status') ?>">
        <?= csrf_field() ?>
        <div class="form-group">
          <label>Status</label>
          <select class="form-control" name="status">
            <?php foreach (['pending','approved','processing','shipped','delivered','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea class="form-control" name="notes" rows="2"></textarea>
        </div>
        <button class="btn btn-primary" type="submit">Update</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
