<?php
$payment = strtolower((string)($bill['payment_mode'] ?? ''));
$paidCash = str_contains($payment, 'cash');
$paidCheque = str_contains($payment, 'cheque') || str_contains($payment, 'check');
?>
<div style="margin-bottom:1rem;"><a href="<?= url('billing') ?>">&larr; Billing</a></div>

<div class="card" style="margin-bottom:1rem;">
  <div class="toolbar">
    <div>
      <h1 class="page-title" style="margin:0;"><?= e($bill['bill_number']) ?></h1>
      <p class="page-sub" style="margin:0.35rem 0 0;"><?= e(ucfirst($bill['bill_type'])) ?> · <?= india_date($bill['vehicle_sale_date'] ?? $bill['created_at']) ?></p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <a class="btn btn-outline" href="<?= url('billing/' . $bill['id'] . '/preview') ?>" target="_blank">Preview / Print (Tax Invoice)</a>
      <a class="btn btn-primary" href="<?= url('billing/' . $bill['id'] . '/pdf') ?>" target="_blank">Download PDF</a>
    </div>
  </div>

  <div class="grid-2-eq">
    <div>
      <h4>Company</h4>
      <p><?= e($bill['company_name']) ?><br><?= nl2br(e($bill['company_address'] ?? '')) ?><br><?= nl2br(e($bill['company_branch_address'] ?? '')) ?><br>GSTIN: <?= e($bill['company_gstin'] ?? '') ?> · State code <?= e($bill['company_state_code'] ?? '') ?></p>
    </div>
    <div>
      <h4>Customer</h4>
      <p><?= e($bill['customer_name']) ?><br><?= e($bill['customer_phone'] ?? '') ?> · <?= e($bill['customer_email'] ?? '') ?><br><?= nl2br(e($bill['customer_address'] ?? '')) ?><br>Aadhar: <?= e($bill['customer_aadhaar'] ?? '—') ?> · PAN: <?= e($bill['customer_pan'] ?? '—') ?></p>
    </div>
  </div>

  <?php if ($bill['bill_type'] === 'warranty'): ?>
    <p><strong>Vehicle:</strong> <?= e($bill['vehicle_model']) ?> · Chassis <?= e($bill['chassis_no'] ?? '—') ?></p>
    <p><strong>Warranty:</strong> <?= india_date($bill['warranty_start']) ?> → <?= india_date($bill['warranty_end']) ?> (<?= e($bill['warranty_period'] ?? '') ?>)</p>
  <?php else: ?>
    <div class="table-wrap" style="margin-top:1rem;">
      <table class="data">
        <thead><tr><th>Model / Code</th><th>Qty</th><th>Unit</th><th>Disc</th><th>Taxable</th><th>CGST</th><th>SGST</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= e($it['description']) ?><?php if (!empty($it['model_code'])): ?><br><span class="muted"><?= e($it['model_code']) ?></span><?php endif; ?></td>
            <td><?= (int)$it['quantity'] ?></td>
            <td><?= money($it['unit_price']) ?></td>
            <td><?= money($it['discount'] ?? 0) ?></td>
            <td><?= money($it['taxable_amount'] ?? 0) ?></td>
            <td><?= money($it['cgst_amount'] ?? 0) ?></td>
            <td><?= money($it['sgst_amount'] ?? 0) ?></td>
            <td><?= money($it['total_price']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="margin-top:1rem;"><strong>Loan:</strong> <?= money($bill['loan_amount'] ?? 0) ?> ·
      <strong>Total:</strong> <?= money($bill['total_amount']) ?> ·
      <strong>In words:</strong> <?= e(amount_in_words($bill['total_amount'])) ?></p>
  <?php endif; ?>
</div>

<?php if ($bill['bill_type'] === 'vehicle' && can('manage_billing')): ?>
<div class="card">
  <h3 class="card-title">Edit tax invoice fields</h3>
  <p class="muted" style="margin-top:0;">Fill every field that appears on the paper SAI KUBER bill, then Preview / Print.</p>
  <form method="post" action="<?= url('billing/' . $bill['id']) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-group"><label>Booking No.</label><input class="form-control" name="booking_no" value="<?= e($bill['booking_no'] ?? '') ?>"></div>
      <div class="form-group"><label>Date of Sale</label><input class="form-control" type="date" name="vehicle_sale_date" value="<?= e($bill['vehicle_sale_date'] ?? '') ?>"></div>
      <div class="form-group"><label>Cust. Name</label><input class="form-control" name="customer_name" value="<?= e($bill['customer_name'] ?? '') ?>"></div>
      <div class="form-group"><label>Mob.</label><input class="form-control" name="customer_phone" value="<?= e($bill['customer_phone'] ?? '') ?>"></div>
      <div class="form-group"><label>Email</label><input class="form-control" name="customer_email" value="<?= e($bill['customer_email'] ?? '') ?>"></div>
      <div class="form-group full"><label>Add.</label><textarea class="form-control" name="customer_address" rows="2"><?= e($bill['customer_address'] ?? '') ?></textarea></div>
      <div class="form-group"><label>Aadhar No.</label><input class="form-control" name="customer_aadhaar" value="<?= e($bill['customer_aadhaar'] ?? '') ?>"></div>
      <div class="form-group"><label>PAN No.</label><input class="form-control" name="customer_pan" value="<?= e($bill['customer_pan'] ?? '') ?>"></div>
      <div class="form-group"><label>EV Model Name</label><input class="form-control" name="vehicle_model" value="<?= e($bill['vehicle_model'] ?? '') ?>"></div>
      <div class="form-group"><label>EV Model Type</label><input class="form-control" name="vehicle_model_type" value="<?= e($bill['vehicle_model_type'] ?? '') ?>"></div>
      <div class="form-group"><label>Model Color</label><input class="form-control" name="color" value="<?= e($bill['color'] ?? '') ?>"></div>
      <div class="form-group"><label>Chassis No.</label><input class="form-control" name="chassis_no" value="<?= e($bill['chassis_no'] ?? '') ?>"></div>
      <div class="form-group"><label>Motor No.</label><input class="form-control" name="motor_no" value="<?= e($bill['motor_no'] ?? '') ?>"></div>
      <?php
        $warrantyOpts = ['6 months', '12 months', '18 months', '24 months', '36 months', 'N/A'];
        $wEdit = static function (string $name, ?string $current) use ($warrantyOpts): void {
            $current = (string)($current ?? '');
            echo '<select class="form-control" name="' . e($name) . '">';
            echo '<option value="">Select</option>';
            foreach ($warrantyOpts as $opt) {
                $sel = $opt === $current ? ' selected' : '';
                echo '<option value="' . e($opt) . '"' . $sel . '>' . e($opt) . '</option>';
            }
            if ($current !== '' && !in_array($current, $warrantyOpts, true)) {
                echo '<option value="' . e($current) . '" selected>' . e($current) . '</option>';
            }
            echo '</select>';
        };
      ?>
      <div class="form-group"><label>Motor Warrenty</label><?php $wEdit('motor_warranty', $bill['motor_warranty'] ?? null); ?></div>
      <div class="form-group"><label>Battery Type &amp; No.</label><input class="form-control" name="battery_type_no" value="<?= e($bill['battery_type_no'] ?? '') ?>"></div>
      <div class="form-group"><label>Battery Warrenty</label><?php $wEdit('battery_warranty', $bill['battery_warranty'] ?? null); ?></div>
      <div class="form-group"><label>Controller No.</label><input class="form-control" name="controller_no" value="<?= e($bill['controller_no'] ?? '') ?>"></div>
      <div class="form-group"><label>Controller Warrenty</label><?php $wEdit('controller_warranty', $bill['controller_warranty'] ?? null); ?></div>
      <div class="form-group"><label>Charger No.</label><input class="form-control" name="charger_no" value="<?= e($bill['charger_no'] ?? '') ?>"></div>
      <div class="form-group"><label>Charger Warrenty</label><?php $wEdit('charger_warranty', $bill['charger_warranty'] ?? null); ?></div>
      <div class="form-group"><label>H.P. Name</label><input class="form-control" name="hp_name" value="<?= e($bill['hp_name'] ?? '') ?>"></div>
      <div class="form-group"><label>Loan Amount (₹)</label><input class="form-control" type="number" step="0.01" name="loan_amount" value="<?= e((string)($bill['loan_amount'] ?? '0')) ?>"></div>
      <div class="form-group"><label>Extra Disc. (₹)</label><input class="form-control" type="number" step="0.01" name="discount_amount" value="<?= e((string)($bill['discount_amount'] ?? '0')) ?>"></div>
      <div class="form-group"><label>PM E-DRIVE (₹)</label><input class="form-control" type="number" step="0.01" name="pm_drive_incentive" value="<?= e((string)($bill['pm_drive_incentive'] ?? '0')) ?>"></div>
      <div class="form-group"><label>State Subsidy (₹)</label><input class="form-control" type="number" step="0.01" name="state_subsidy" value="<?= e((string)($bill['state_subsidy'] ?? '0')) ?>"></div>
      <div class="form-group full" style="display:flex;gap:1.25rem;align-items:center;">
        <label style="display:flex;align-items:center;gap:0.4rem;font-weight:600;"><input type="checkbox" name="paid_cash" value="1" <?= $paidCash ? 'checked' : '' ?>> Paid in Cash</label>
        <label style="display:flex;align-items:center;gap:0.4rem;font-weight:600;"><input type="checkbox" name="paid_cheque" value="1" <?= $paidCheque ? 'checked' : '' ?>> Paid in Cheque</label>
      </div>
    </div>
    <div style="margin-top:1rem;"><button class="btn btn-primary" type="submit">Save invoice fields</button></div>
  </form>
</div>
<?php endif; ?>
