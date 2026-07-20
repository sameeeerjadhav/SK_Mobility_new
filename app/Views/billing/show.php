<?php
$payment = strtolower((string)($bill['payment_mode'] ?? ''));
$paidCash = str_contains($payment, 'cash');
$paidCheque = str_contains($payment, 'cheque') || str_contains($payment, 'check');
$paymentStatus = strtolower((string)($bill['payment_status'] ?? 'full'));
$amountPaid = (float)($bill['amount_paid'] ?? 0);
$amountDue = (float)($bill['amount_due'] ?? 0);
if ($paymentStatus === 'full' && $amountPaid <= 0) {
    $amountPaid = (float)($bill['total_amount'] ?? 0);
}
$locationLabels = ['kokamthan' => 'Kokamthan', 'kopargaon' => 'Kopargaon'];
$billingLoc = $bill['billing_location'] ?? 'kokamthan';
$companyAddress = $billingLoc === 'kopargaon'
    ? ($bill['company_branch_address'] ?? '')
    : ($bill['company_address'] ?? '');
?>
<div style="margin-bottom:1rem;"><a href="<?= url('billing') ?>">&larr; Tax Invoices</a></div>

<div class="card" style="margin-bottom:1rem;">
  <div class="toolbar">
    <div>
      <h1 class="page-title" style="margin:0;"><?= e($bill['bill_number']) ?></h1>
      <p class="page-sub" style="margin:0.35rem 0 0;">
        Tax Invoice · <?= india_date($bill['vehicle_sale_date'] ?? $bill['created_at']) ?>
        <?php if (!empty($orderType)): ?>
          · <?= ($orderType === 'dealer' ? 'Dealer' : 'Customer') ?> order
        <?php endif; ?>
        · <?= e($locationLabels[$billingLoc] ?? 'Kokamthan') ?>
      </p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <a class="btn btn-outline" href="<?= url('billing/' . $bill['id'] . '/preview') ?>" target="_blank">Preview / Print</a>
      <a class="btn btn-primary" href="<?= url('billing/' . $bill['id'] . '/pdf') ?>" target="_blank">Download PDF</a>
      <?php if (!empty($bill['order_id'])): ?>
        <a class="btn btn-outline" href="<?= url('orders/' . (int)$bill['order_id']) ?>">View sell order</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid-2-eq">
    <div>
      <h4>Company (<?= e($locationLabels[$billingLoc] ?? 'Kokamthan') ?>)</h4>
      <p><?= e($bill['company_name']) ?><br><?= nl2br(e($companyAddress)) ?><br>GSTIN: <?= e($bill['company_gstin'] ?? '') ?> · State code <?= e($bill['company_state_code'] ?? '') ?></p>
    </div>
    <div>
      <h4>Customer</h4>
      <p><?= e($bill['customer_name']) ?><br><?= e(format_phone($bill['customer_phone'] ?? '') ?: '—') ?> · <?= e($bill['customer_email'] ?? '') ?><br><?= nl2br(e($bill['customer_address'] ?? '')) ?><br>Aadhar: <?= e(format_aadhar($bill['customer_aadhaar'] ?? '') ?: '—') ?> · PAN: <?= e($bill['customer_pan'] ?? '—') ?></p>
    </div>
  </div>

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
    <?php if (($bill['payment_status'] ?? 'full') === 'partial'): ?>
      <strong>Paid:</strong> <?= money($bill['amount_paid'] ?? 0) ?> ·
      <strong>Due:</strong> <?= money($bill['amount_due'] ?? 0) ?> ·
    <?php else: ?>
      <strong>Payment:</strong> Full paid ·
    <?php endif; ?>
    <strong>In words:</strong> <?= e(amount_in_words($bill['total_amount'])) ?></p>
</div>

<?php if (can('manage_billing')): ?>
<div class="card">
  <h3 class="card-title">Edit tax invoice fields</h3>
  <p class="muted" style="margin-top:0;">Fill every field that appears on the paper SAI KUBER bill, then Preview / Print.</p>
  <form method="post" action="<?= url('billing/' . $bill['id']) ?>">
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="form-group"><label>Booking No.</label><input class="form-control" name="booking_no" value="<?= e($bill['booking_no'] ?? '') ?>"></div>
      <div class="form-group"><label>Date of Sale</label><input class="form-control" type="date" name="vehicle_sale_date" value="<?= e($bill['vehicle_sale_date'] ?? '') ?>"></div>
      <div class="form-group"><label>Cust. Name</label><input class="form-control" name="customer_name" value="<?= e($bill['customer_name'] ?? '') ?>"></div>
      <div class="form-group"><label>Mob.</label><input class="form-control contact-input" name="customer_phone" type="tel" maxlength="11" inputmode="numeric" placeholder="98765 43210" value="<?= e(format_phone($bill['customer_phone'] ?? '')) ?>"></div>
      <div class="form-group"><label>Email</label><input class="form-control" name="customer_email" value="<?= e($bill['customer_email'] ?? '') ?>"></div>
      <div class="form-group full"><label>Add.</label><textarea class="form-control" name="customer_address" rows="2"><?= e($bill['customer_address'] ?? '') ?></textarea></div>
      <div class="form-group"><label>Aadhar No.</label><input class="form-control aadhar-input" name="customer_aadhaar" maxlength="14" inputmode="numeric" placeholder="1234 5678 9012" value="<?= e(format_aadhar($bill['customer_aadhaar'] ?? '')) ?>"></div>
      <div class="form-group"><label>PAN No.</label><input class="form-control" name="customer_pan" value="<?= e($bill['customer_pan'] ?? '') ?>"></div>
      <div class="form-group"><label>EV Model Name</label><input class="form-control" name="vehicle_model" value="<?= e($bill['vehicle_model'] ?? '') ?>"></div>
      <div class="form-group"><label>EV Model Type</label><input class="form-control" name="vehicle_model_type" value="<?= e($bill['vehicle_model_type'] ?? '') ?>"></div>
      <div class="form-group"><label>Model Color</label><input class="form-control" name="color" value="<?= e($bill['color'] ?? '') ?>"></div>
    </div>

    <?php
      $warrantyOpts = ['6 months', '12 months', '18 months', '24 months', '36 months', 'N/A'];
      $wEdit = static function (string $name, ?string $current) use ($warrantyOpts): void {
          $current = (string)($current ?? '');
          echo '<select class="form-control" name="' . e($name) . '">';
          echo '<option value="">Select warranty</option>';
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
    <style>
      .ow-parts{display:flex;flex-direction:column;gap:.7rem;margin:1rem 0}
      .ow-block{border:1px solid var(--border);border-radius:12px;padding:.75rem .85rem;background:#f8fffd}
      .ow-block h4{margin:0 0 .65rem;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#0f766e}
      .ow-block .form-grid{gap:.65rem}
      .ow-block.chassis{background:#fff}
    </style>
    <h4 style="margin:1rem 0 .35rem;font-size:.95rem;">Parts &amp; warranty</h4>
    <p class="muted" style="margin:0 0 .65rem;font-size:0.82rem;">Each part has its own number and warranty — fill one block at a time.</p>
    <div class="ow-parts">
      <div class="ow-block chassis">
        <h4>Chassis</h4>
        <div class="form-grid">
          <div class="form-group full"><label>Chassis No.</label><input class="form-control" name="chassis_no" value="<?= e($bill['chassis_no'] ?? '') ?>"></div>
        </div>
      </div>
      <div class="ow-block">
        <h4>Motor</h4>
        <div class="form-grid">
          <div class="form-group"><label>Motor No.</label><input class="form-control" name="motor_no" value="<?= e($bill['motor_no'] ?? '') ?>"></div>
          <div class="form-group"><label>Warranty</label><?php $wEdit('motor_warranty', $bill['motor_warranty'] ?? null); ?></div>
        </div>
      </div>
      <div class="ow-block">
        <h4>Battery</h4>
        <div class="form-grid">
          <div class="form-group"><label>Battery Type &amp; No.</label><input class="form-control" name="battery_type_no" value="<?= e($bill['battery_type_no'] ?? '') ?>"></div>
          <div class="form-group"><label>Warranty</label><?php $wEdit('battery_warranty', $bill['battery_warranty'] ?? null); ?></div>
        </div>
      </div>
      <div class="ow-block">
        <h4>Controller</h4>
        <div class="form-grid">
          <div class="form-group"><label>Controller No.</label><input class="form-control" name="controller_no" value="<?= e($bill['controller_no'] ?? '') ?>"></div>
          <div class="form-group"><label>Warranty</label><?php $wEdit('controller_warranty', $bill['controller_warranty'] ?? null); ?></div>
        </div>
      </div>
      <div class="ow-block">
        <h4>Charger</h4>
        <div class="form-grid">
          <div class="form-group"><label>Charger No.</label><input class="form-control" name="charger_no" value="<?= e($bill['charger_no'] ?? '') ?>"></div>
          <div class="form-group"><label>Warranty</label><?php $wEdit('charger_warranty', $bill['charger_warranty'] ?? null); ?></div>
        </div>
      </div>
      <div class="ow-block chassis">
        <h4>Finance (optional)</h4>
        <div class="form-grid">
          <div class="form-group full"><label>H.P. Name</label><input class="form-control" name="hp_name" value="<?= e($bill['hp_name'] ?? '') ?>"></div>
        </div>
      </div>
    </div>

    <div class="form-grid">
      <div class="form-group"><label>PM E-DRIVE (₹)</label><input class="form-control" type="number" step="0.01" min="0" name="pm_drive_incentive" value="<?= e((string)($bill['pm_drive_incentive'] ?? '0')) ?>"></div>
      <div class="form-group"><label>State Subsidy (₹)</label><input class="form-control" type="number" step="0.01" min="0" name="state_subsidy" value="<?= e((string)($bill['state_subsidy'] ?? '0')) ?>"></div>
      <div class="form-group"><label>Extra Disc. (₹)</label><input class="form-control" type="number" step="0.01" min="0" name="discount_amount" value="<?= e((string)($bill['discount_amount'] ?? '0')) ?>"></div>
      <div class="form-group"><label>Loan Amount (₹)</label><input class="form-control" type="number" step="0.01" min="0" name="loan_amount" value="<?= e((string)($bill['loan_amount'] ?? '0')) ?>"></div>

      <div class="form-group full" style="grid-column:1 / -1;padding-top:0.35rem;border-top:1px solid var(--border);">
        <label>Payment status</label>
        <div style="display:flex;gap:1.25rem;flex-wrap:wrap;margin-top:0.35rem;">
          <label style="display:flex;align-items:center;gap:0.45rem;font-weight:600;">
            <input type="radio" name="payment_status" value="full" <?= $paymentStatus !== 'partial' ? 'checked' : '' ?>> Full paid
          </label>
          <label style="display:flex;align-items:center;gap:0.45rem;font-weight:600;">
            <input type="radio" name="payment_status" value="partial" <?= $paymentStatus === 'partial' ? 'checked' : '' ?>> Partial payment
          </label>
        </div>
      </div>
      <div class="form-group partial-paid-field" style="<?= $paymentStatus === 'partial' ? '' : 'display:none;' ?>">
        <label>Amount paid (₹)</label>
        <input class="form-control" type="number" step="0.01" min="0.01" name="amount_paid" value="<?= e((string)($amountPaid > 0 ? $amountPaid : '')) ?>" placeholder="Received so far">
      </div>
      <div class="form-group partial-due-field" style="<?= $paymentStatus === 'partial' ? '' : 'display:none;' ?>">
        <label>Balance due (₹)</label>
        <input class="form-control" type="text" value="<?= e(money($amountDue)) ?>" readonly tabindex="-1" style="background:#f8fafc;">
        <p class="muted" style="margin:0.25rem 0 0;font-size:0.78rem;">Recalculated when you save.</p>
      </div>
      <div class="form-group full" style="display:flex;gap:1.25rem;align-items:center;flex-wrap:wrap;">
        <span style="font-weight:700;font-size:0.82rem;color:#64748b;">Payment mode</span>
        <label style="display:flex;align-items:center;gap:0.4rem;font-weight:600;"><input type="checkbox" name="paid_cash" value="1" <?= $paidCash ? 'checked' : '' ?>> Cash</label>
        <label style="display:flex;align-items:center;gap:0.4rem;font-weight:600;"><input type="checkbox" name="paid_cheque" value="1" <?= $paidCheque ? 'checked' : '' ?>> Cheque</label>
      </div>
    </div>
    <script>
      document.querySelectorAll('input[name="payment_status"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
          var partial = this.value === 'partial';
          document.querySelectorAll('.partial-paid-field, .partial-due-field').forEach(function (el) {
            el.style.display = partial ? '' : 'none';
          });
          var paidInput = document.querySelector('input[name="amount_paid"]');
          if (paidInput) paidInput.required = partial;
        });
      });
      (function () {
        var partial = document.querySelector('input[name="payment_status"][value="partial"]');
        var paidInput = document.querySelector('input[name="amount_paid"]');
        if (paidInput && partial && partial.checked) paidInput.required = true;
      })();
    </script>
    <div style="margin-top:1rem;"><button class="btn btn-primary" type="submit">Save invoice fields</button></div>
  </form>
</div>
<?php endif; ?>
