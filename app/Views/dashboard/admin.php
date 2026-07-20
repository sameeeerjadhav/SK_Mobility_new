<div class="toolbar">
  <div>
    <h1 class="page-title">Dashboard</h1>
    <p class="page-sub">Overview of SK Mobility operations</p>
  </div>
</div>

<div class="stat-grid">
  <?php foreach ($stats as $s): ?>
    <div class="stat-card">
      <div class="stat-label"><?= e($s['label']) ?></div>
      <div class="stat-value">
        <?= ($s['fmt'] ?? '') === 'money' ? money($s['value']) : number_format((float)$s['value']) ?>
      </div>
      <?php if (!empty($s['hint'])): ?>
        <div class="muted" style="font-size:0.72rem;margin-top:0.35rem;"><?= e($s['hint']) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid-2">
  <div class="card">
    <h3 class="card-title">Revenue trend (12 months)</h3>
    <canvas id="salesChart" height="120"></canvas>
  </div>
  <div class="card">
    <h3 class="card-title">Lead sources</h3>
    <canvas id="leadsChart" height="120"></canvas>
  </div>
</div>

<div class="grid-2" style="margin-top:1rem;">
  <div class="card">
    <h3 class="card-title">Top dealers by revenue</h3>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Dealer</th><th>Code</th><th>Sell Orders</th><th>Revenue</th></tr></thead>
        <tbody>
        <?php if (!$topDealers): ?>
          <tr><td colspan="4" class="muted">No dealers yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($topDealers as $d): ?>
          <tr>
            <td><?= e($d['business_name']) ?></td>
            <td><?= e($d['dealer_code'] ?? '—') ?></td>
            <td><?= (int)$d['total_orders'] ?></td>
            <td><?= money($d['total_revenue']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <h3 class="card-title">Recent leads</h3>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Customer</th><th>Source</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$recentLeads): ?>
          <tr><td colspan="3" class="muted">No leads yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($recentLeads as $l): ?>
          <tr>
            <td><?= e($l['customer_name']) ?><div class="muted" style="font-size:0.75rem;"><?= e($l['customer_phone']) ?></div></td>
            <td><?= e($l['source_name'] ?? '—') ?></td>
            <td><?= status_chip($l['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card" style="margin-top:1rem;">
  <h3 class="card-title">Recent sell orders</h3>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Order #</th><th>Type</th><th>Party</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($recentOrders as $o): ?>
        <tr>
          <td><a href="<?= url('orders/' . $o['id']) ?>"><?= e($o['order_number']) ?></a></td>
          <td><?= e(ucfirst($o['order_type'])) ?></td>
          <td><?= e($o['business_name'] ?? $o['customer_name'] ?? '—') ?></td>
          <td><?= money($o['total_amount']) ?></td>
          <td><?= status_chip($o['status']) ?></td>
          <td><?= india_date($o['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$recentOrders): ?><tr><td colspan="6" class="muted">No sell orders yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(() => {
  const salesLabels = <?= json_encode(array_column($monthlySales, 'ym')) ?>;
  const salesData = <?= json_encode(array_map('floatval', array_column($monthlySales, 'total'))) ?>;
  const leadLabels = <?= json_encode(array_column($leadSources, 'name')) ?>;
  const leadData = <?= json_encode(array_map('intval', array_column($leadSources, 'cnt'))) ?>;

  if (document.getElementById('salesChart')) {
    new Chart(document.getElementById('salesChart'), {
      type: 'line',
      data: {
        labels: salesLabels.length ? salesLabels : ['No data'],
        datasets: [{
          label: 'Sales',
          data: salesData.length ? salesData : [0],
          borderColor: '#0d9488',
          backgroundColor: 'rgba(13,148,136,0.12)',
          fill: true,
          tension: 0.35
        }]
      },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
  }
  if (document.getElementById('leadsChart')) {
    new Chart(document.getElementById('leadsChart'), {
      type: 'doughnut',
      data: {
        labels: leadLabels.length ? leadLabels : ['No data'],
        datasets: [{
          data: leadData.length ? leadData : [1],
          backgroundColor: ['#0d9488','#14b8a6','#2dd4bf','#5eead4','#99f6e4','#ccfbf1']
        }]
      },
      options: { plugins: { legend: { position: 'bottom' } } }
    });
  }
})();
</script>
