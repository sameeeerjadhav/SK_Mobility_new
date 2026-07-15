<div class="od">
  <a class="od-back" href="<?= url('orders') ?>">&larr; Orders</a>

  <div class="od-head">
    <div class="od-head-main">
      <h1 class="od-title"><?= e($order['order_number']) ?></h1>
      <div class="od-meta">
        <?= status_chip($order['status']) ?>
        <span><?= e(ucfirst($order['order_type'])) ?> order</span>
        <span class="od-dot">·</span>
        <span><?= india_datetime($order['created_at'] ?? null) ?></span>
      </div>
    </div>
    <div class="od-actions">
      <a class="btn btn-sm btn-outline" href="<?= url('orders/' . $order['id'] . '/print') ?>" target="_blank">Print</a>
      <?php if ($bill): ?>
        <a class="btn btn-sm btn-primary" href="<?= url('billing/' . $bill['id'] . '/pdf') ?>" target="_blank">Bill <?= e($bill['bill_number']) ?></a>
      <?php endif; ?>
    </div>
  </div>

  <div class="od-layout">
    <div class="od-main">
      <section class="od-panel">
        <h2 class="od-section">Details</h2>
        <dl class="od-dl">
          <?php if ($order['order_type'] === 'dealer'): ?>
            <div><dt>Dealer</dt><dd><?= e($order['business_name'] ?? '—') ?> <span class="muted"><?= e($order['dealer_code'] ?? '') ?></span></dd></div>
            <div><dt>Delivery</dt><dd><?= e($order['delivery_address'] ?? '—') ?></dd></div>
          <?php else: ?>
            <div><dt>Customer</dt><dd><?= e($order['customer_name']) ?> · <?= e($order['customer_phone']) ?></dd></div>
            <div><dt>Address</dt><dd><?= e($order['customer_address'] ?? '—') ?></dd></div>
            <div><dt>Chassis / Motor</dt><dd><?= e($order['chassis_no'] ?? '—') ?> / <?= e($order['motor_no'] ?? '—') ?></dd></div>
            <div><dt>Subsidies</dt><dd>PM <?= money($order['pm_drive_incentive']) ?> · State <?= money($order['state_subsidy']) ?></dd></div>
          <?php endif; ?>
        </dl>

        <div class="od-totals">
          <div><span>Subtotal</span><strong><?= money($order['subtotal']) ?></strong></div>
          <div><span>GST 28%</span><strong><?= money($order['tax_amount']) ?></strong></div>
          <div class="od-total-row"><span>Total</span><strong><?= money($order['total_amount']) ?></strong></div>
        </div>
      </section>

      <section class="od-panel">
        <h2 class="od-section">Items</h2>
        <div class="table-wrap">
          <table class="data od-table">
            <thead><tr><th>Vehicle</th><th>Variant</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td><?= e($it['vehicle_name']) ?></td>
                <td><?= e($it['variant_name']) ?><?= $it['color'] ? ' (' . e($it['color']) . ')' : '' ?></td>
                <td><?= (int)$it['quantity'] ?></td>
                <td><?= money($it['unit_price']) ?></td>
                <td><?= money($it['total_price']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <aside class="od-side">
      <section class="od-panel">
        <h2 class="od-section">Status history</h2>
        <ul class="od-timeline">
          <?php foreach ($history as $h): ?>
            <li>
              <div class="od-timeline-top">
                <?= status_chip($h['status']) ?>
                <span class="muted"><?= india_datetime($h['created_at']) ?></span>
              </div>
              <div class="od-timeline-who muted"><?= e(trim(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? ''))) ?></div>
              <?php if ($h['notes']): ?><div class="od-timeline-note"><?= e($h['notes']) ?></div><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>

      <?php if ($canManage): ?>
      <section class="od-panel">
        <h2 class="od-section">Update status</h2>
        <form method="post" action="<?= url('orders/' . $order['id'] . '/status') ?>" class="od-form">
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
            <textarea class="form-control" name="notes" rows="2" placeholder="Optional note"></textarea>
          </div>
          <button class="btn btn-sm btn-primary" type="submit">Update</button>
        </form>
      </section>
      <?php endif; ?>
    </aside>
  </div>
</div>
