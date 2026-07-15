<div style="margin-bottom:1rem;"><a href="<?= url('billing') ?>">&larr; Billing</a></div>

<div class="card">
  <div class="toolbar">
    <div>
      <h1 class="page-title" style="margin:0;"><?= e($bill['bill_number']) ?></h1>
      <p class="page-sub" style="margin:0.35rem 0 0;"><?= e(ucfirst($bill['bill_type'])) ?> · <?= india_date($bill['created_at']) ?></p>
    </div>
    <div style="display:flex;gap:0.5rem;">
      <a class="btn btn-outline" href="<?= url('billing/' . $bill['id'] . '/preview') ?>" target="_blank">Preview / Print</a>
      <a class="btn btn-primary" href="<?= url('billing/' . $bill['id'] . '/pdf') ?>" target="_blank">Download PDF</a>
    </div>
  </div>

  <div class="grid-2-eq">
    <div>
      <h4>Company</h4>
      <p><?= e($bill['company_name']) ?><br><?= nl2br(e($bill['company_address'] ?? '')) ?><br>GSTIN: <?= e($bill['company_gstin'] ?? '') ?></p>
    </div>
    <div>
      <h4>Customer</h4>
      <p><?= e($bill['customer_name']) ?><br><?= e($bill['customer_phone'] ?? '') ?><br><?= nl2br(e($bill['customer_address'] ?? '')) ?></p>
    </div>
  </div>

  <?php if ($bill['bill_type'] === 'warranty'): ?>
    <p><strong>Vehicle:</strong> <?= e($bill['vehicle_model']) ?> · Chassis <?= e($bill['chassis_no'] ?? '—') ?></p>
    <p><strong>Warranty:</strong> <?= india_date($bill['warranty_start']) ?> → <?= india_date($bill['warranty_end']) ?> (<?= e($bill['warranty_period'] ?? '') ?>)</p>
  <?php else: ?>
    <div class="table-wrap" style="margin-top:1rem;">
      <table class="data">
        <thead><tr><th>Description</th><th>HSN</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= e($it['description']) ?></td>
            <td><?= e($it['hsn_code']) ?></td>
            <td><?= (int)$it['quantity'] ?></td>
            <td><?= money($it['unit_price']) ?></td>
            <td><?= money($it['total_price']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="margin-top:1rem;"><strong>Subtotal:</strong> <?= money($bill['subtotal']) ?> ·
      <strong>Incentives:</strong> <?= money($bill['pm_drive_incentive']) ?> / <?= money($bill['state_subsidy']) ?> ·
      <strong>Total:</strong> <?= money($bill['total_amount']) ?></p>
  <?php endif; ?>
</div>
