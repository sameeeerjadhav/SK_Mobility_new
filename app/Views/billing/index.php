<?php
$locationLabels = [
    'kokamthan' => 'Kokamthan',
    'kopargaon' => 'Kopargaon',
];
?>
<div class="toolbar">
  <div>
    <h1 class="page-title">Tax Invoices</h1>
    <p class="page-sub">SAI KUBER MOBILITY invoices from dealer &amp; customer orders</p>
  </div>
</div>

<div class="stat-grid" style="margin-bottom:1rem;">
  <div class="stat-card">
    <div class="stat-label">Matching invoices</div>
    <div class="stat-value"><?= (int)$invoiceCount ?></div>
  </div>
</div>

<form method="get" class="card" style="margin-bottom:1rem;">
  <h3 class="card-title" style="margin-bottom:0.65rem;">Filters</h3>
  <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;">
    <div class="form-group" style="margin:0;min-width:160px;">
      <label>Order type</label>
      <select class="form-control" name="order_type">
        <option value="">All</option>
        <option value="customer" <?= ($orderType ?? '') === 'customer' ? 'selected' : '' ?>>Customer order</option>
        <option value="dealer" <?= ($orderType ?? '') === 'dealer' ? 'selected' : '' ?>>Dealer order</option>
      </select>
    </div>
    <div class="form-group" style="margin:0;min-width:160px;">
      <label>Billing location</label>
      <select class="form-control" name="billing_location">
        <option value="">All locations</option>
        <option value="kokamthan" <?= ($billingLocation ?? '') === 'kokamthan' ? 'selected' : '' ?>>Kokamthan</option>
        <option value="kopargaon" <?= ($billingLocation ?? '') === 'kopargaon' ? 'selected' : '' ?>>Kopargaon</option>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">Filter</button>
    <?php if (($orderType ?? '') !== '' || ($billingLocation ?? '') !== ''): ?>
      <a class="btn btn-outline" href="<?= url('billing') ?>">Clear</a>
    <?php endif; ?>
  </div>
</form>

<div class="card">
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr>
          <th>Invoice #</th>
          <th>Order type</th>
          <th>Location</th>
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
          <td>
            <?php if (($b['order_type'] ?? '') === 'dealer'): ?>
              <span class="chip chip-info">Dealer</span>
            <?php elseif (($b['order_type'] ?? '') === 'customer'): ?>
              <span class="chip chip-success">Customer</span>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
          <td><?= e($locationLabels[$b['billing_location'] ?? ''] ?? '—') ?></td>
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
        <tr><td colspan="7" class="muted">No tax invoices match these filters. Invoices are created automatically when you place an order.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
