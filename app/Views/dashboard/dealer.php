<h1 class="page-title">Dashboard</h1>
<p class="page-sub">Your dealer performance at a glance</p>

<div class="stat-grid">
  <?php foreach ($stats as $s): ?>
    <div class="stat-card">
      <div class="stat-label"><?= e($s['label']) ?></div>
      <div class="stat-value">
        <?= ($s['fmt'] ?? '') === 'money' ? money($s['value']) : number_format((float)$s['value']) ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h3 class="card-title">Recent orders</h3>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Order #</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td><a href="<?= url('orders/' . $o['id']) ?>"><?= e($o['order_number']) ?></a></td>
          <td><?= money($o['total_amount']) ?></td>
          <td><?= status_chip($o['status']) ?></td>
          <td><?= india_date($o['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recentOrders): ?><tr><td colspan="4" class="muted">No orders yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
