<div class="toolbar">
  <div>
    <h1 class="page-title">Sell Orders</h1>
    <p class="page-sub">Dealer bulk &amp; customer sell orders — vehicles and spare parts</p>
  </div>
  <?php if ($canManage): ?>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <a class="btn btn-primary" href="<?= url('orders/create?product=vehicle') ?>">+ Vehicle Sell Order</a>
      <a class="btn btn-outline" href="<?= url('orders/create?product=spare_part') ?>">+ Spare Parts Sell Order</a>
    </div>
  <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
<div class="tabs">
  <a class="tab <?= ($orderType === '' && ($productType ?? '') === '') ? 'active' : '' ?>" href="<?= url('orders') ?>">All</a>
  <a class="tab <?= ($orderType === '' && ($productType ?? '') === 'vehicle') ? 'active' : '' ?>" href="<?= url('orders?product_type=vehicle') ?>">Vehicles</a>
  <a class="tab <?= ($orderType === '' && ($productType ?? '') === 'spare_part') ? 'active' : '' ?>" href="<?= url('orders?product_type=spare_part') ?>">Spare Parts</a>
  <a class="tab <?= $orderType === 'dealer' ? 'active' : '' ?>" href="<?= url('orders?order_type=dealer') ?>">Dealer Sell Orders</a>
  <a class="tab <?= $orderType === 'customer' ? 'active' : '' ?>" href="<?= url('orders?order_type=customer') ?>">Customer Sell Orders</a>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr>
          <th>Order #</th>
          <th>Type</th>
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
        <?php
          $isSpare = ($o['product_type'] ?? 'vehicle') === 'spare_part';
          $typeLabel = $isSpare ? 'Spare Parts' : 'Vehicle';
        ?>
        <tr>
          <td><?= e($o['order_number']) ?></td>
          <td><span class="chip chip-muted"><?= e($typeLabel) ?></span></td>
          <td><?= e($o['business_name'] ?? $o['customer_name'] ?? '—') ?></td>
          <td><?= money($o['total_amount']) ?></td>
          <td><?= status_chip($o['status']) ?></td>
          <td><?= india_date($o['created_at']) ?></td>
          <td><?= e($o['tracking_number'] ?? '—') ?></td>
          <td><a class="btn btn-sm btn-outline" href="<?= url('orders/' . $o['id']) ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$orders): ?><tr><td colspan="8" class="muted">No sell orders found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
