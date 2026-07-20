<div class="toolbar">
  <div>
    <h1 class="page-title">Tax Invoices</h1>
    <p class="page-sub">SAI KUBER MOBILITY invoices from dealer &amp; customer orders</p>
  </div>
</div>

<div class="stat-grid" style="margin-bottom:1rem;">
  <div class="stat-card">
    <div class="stat-label">Total invoices</div>
    <div class="stat-value"><?= (int)$invoiceCount ?></div>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr>
          <th>Invoice #</th>
          <th>Customer</th>
          <th>Amount</th>
          <th>Sale date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($bills as $b): ?>
        <tr>
          <td><strong><?= e($b['bill_number']) ?></strong></td>
          <td><?= e($b['customer_name'] ?? '—') ?></td>
          <td><?= money($b['total_amount']) ?></td>
          <td><?= india_date($b['vehicle_sale_date'] ?? $b['created_at']) ?></td>
          <td style="white-space:nowrap;">
            <a class="btn btn-sm btn-outline" href="<?= url('billing/' . $b['id']) ?>">View</a>
            <a class="btn btn-sm btn-outline" href="<?= url('billing/' . $b['id'] . '/preview') ?>" target="_blank">Print</a>
            <a class="btn btn-sm btn-primary" href="<?= url('billing/' . $b['id'] . '/pdf') ?>" target="_blank">PDF</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$bills): ?>
        <tr><td colspan="5" class="muted">No tax invoices yet. Invoices are created automatically when you place an order.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
