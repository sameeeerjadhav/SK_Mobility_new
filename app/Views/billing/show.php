<?php
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
$billCgst = (float)($bill['cgst_rate'] ?? ($productType === 'spare_part' ? 9 : 14));
$billSgst = (float)($bill['sgst_rate'] ?? ($productType === 'spare_part' ? 9 : 14));
$billTaxRate = (float)($bill['tax_rate'] ?? ($billCgst + $billSgst));
$isSpareBill = ($productType ?? 'vehicle') === 'spare_part';
$gstPreset = 'default';
foreach ([['28', 14, 14], ['18', 9, 9], ['12', 6, 6], ['5', 2.5, 2.5], ['0', 0, 0]] as [$key, $c, $s]) {
    if (abs($billCgst - $c) < 0.01 && abs($billSgst - $s) < 0.01) {
        $gstPreset = $key;
        break;
    }
}
if ($gstPreset === 'default' && (abs($billCgst - ($isSpareBill ? 9 : 14)) > 0.01 || abs($billSgst - ($isSpareBill ? 9 : 14)) > 0.01)) {
    $gstPreset = 'custom';
}
$paidCash = $paidCash ?? 0;
$paidBank = $paidBank ?? 0;
$paidLoan = $paidLoan ?? 0;
$batteryCapacity = $batteryCapacity ?? '';
$batteryNo = $batteryNo ?? '';
$subtotal = (float)($bill['subtotal'] ?? 0);
$editLines = [];
foreach ($items as $it) {
    $editLines[] = [
        'id' => (int)$it['id'],
        'description' => (string)($it['description'] ?? ''),
        'quantity' => max(1, (int)($it['quantity'] ?? 1)),
        'unit_price' => round((float)($it['unit_price'] ?? 0), 2),
    ];
}
if ($editLines === [] && $subtotal > 0) {
    $editLines[] = ['id' => 0, 'description' => 'Sell amount', 'quantity' => 1, 'unit_price' => $subtotal];
}
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
  <p style="margin-top:1rem;"><strong>GST:</strong> <?= e(rtrim(rtrim(number_format($billTaxRate, 2), '0'), '.')) ?>% (CGST <?= e(rtrim(rtrim(number_format($billCgst, 2), '0'), '.')) ?>% + SGST <?= e(rtrim(rtrim(number_format($billSgst, 2), '0'), '.')) ?>%) ·
    <strong>Loan portion:</strong> <?= money($bill['loan_amount'] ?? 0) ?> ·
    <strong>Total:</strong> <?= money($bill['total_amount']) ?> ·
    <?php if (($bill['payment_status'] ?? 'full') === 'partial'): ?>
      <strong>Paid:</strong> <?= money($bill['amount_paid'] ?? 0) ?> ·
      <strong>Due:</strong> <?= money($bill['amount_due'] ?? 0) ?> ·
    <?php else: ?>
      <strong>Payment:</strong> Full paid ·
    <?php endif; ?>
    <?php if (!empty($bill['payment_mode'])): ?>
      <strong>Mode:</strong> <?= e(str_replace('+', ' + ', $bill['payment_mode'])) ?> ·
    <?php endif; ?>
    <strong>In words:</strong> <?= e(amount_in_words($bill['total_amount'])) ?></p>
</div>

<?php if (can('manage_billing')): ?>
<style>
  .ow-parts{display:flex;flex-direction:column;gap:.7rem;margin:0.5rem 0 1rem}
  .ow-block{border:1px solid var(--border);border-radius:12px;padding:.75rem .85rem;background:#f8fffd}
  .ow-block h4{margin:0 0 .65rem;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#0f766e}
  .ow-block .form-grid{gap:.65rem}
  .ow-block.chassis{background:#fff}
</style>
<div class="card" x-data="{
  productType: '<?= e($productType ?? 'vehicle') ?>',
  paymentStatus: '<?= e($paymentStatus) ?>',
  paidCash: '<?= e($paidCash > 0 ? (string)$paidCash : '') ?>',
  paidBank: '<?= e($paidBank > 0 ? (string)$paidBank : '') ?>',
  paidLoan: '<?= e($paidLoan > 0 ? (string)$paidLoan : '') ?>',
  bankAccountId: '<?= e((string)($order['bank_account_id'] ?? '')) ?>',
  gstPreset: '<?= e($gstPreset) ?>',
  defaultCgst: <?= $isSpareBill ? '9' : '14' ?>,
  defaultSgst: <?= $isSpareBill ? '9' : '14' ?>,
  cgstRate: <?= json_encode($billCgst) ?>,
  sgstRate: <?= json_encode($billSgst) ?>,
  lines: <?= htmlspecialchars(json_encode($editLines, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>,
  applyGstPreset() {
    const presets = { default: [this.defaultCgst, this.defaultSgst], '28': [14,14], '18': [9,9], '12': [6,6], '5': [2.5,2.5], '0': [0,0] };
    if (this.gstPreset === 'custom') return;
    const p = presets[this.gstPreset] || presets.default;
    this.cgstRate = p[0]; this.sgstRate = p[1];
  },
  get subtotal() {
    return Math.round(this.lines.reduce((s, it) => s + (parseFloat(it.unit_price)||0) * (parseInt(it.quantity,10)||1), 0) * 100) / 100;
  },
  get totalGstPercent() { return Math.round(((parseFloat(this.cgstRate)||0)+(parseFloat(this.sgstRate)||0))*100)/100; },
  get gstAmount() { return Math.round(this.subtotal * this.totalGstPercent / 100 * 100) / 100; },
  get grandTotal() { return Math.round((this.subtotal + this.gstAmount) * 100) / 100; },
  get totalPaidNow() { return (parseFloat(this.paidCash)||0)+(parseFloat(this.paidBank)||0)+(parseFloat(this.paidLoan)||0); },
  get balanceDue() { return Math.max(0, Math.round((this.grandTotal - this.totalPaidNow)*100)/100); },
  fillInvoiceTotal() {
    if (this.grandTotal <= 0) return;
    this.paidCash = String(this.grandTotal); this.paidBank = ''; this.paidLoan = ''; this.paymentStatus = 'full';
  },
  money(n) { return '₹' + (Math.round((parseFloat(n)||0)*100)/100).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }
}">
  <h3 class="card-title">Edit sell order / tax invoice</h3>
  <p class="muted" style="margin-top:0;">Same fields as create sell order — update sell amount, buyer, parts, GST, and payment.</p>
  <form method="post" action="<?= url('billing/' . $bill['id']) ?>">
    <?= csrf_field() ?>

    <h4 style="margin:1rem 0 0.5rem;font-size:0.95rem;">Order &amp; billing</h4>
    <div class="form-grid">
      <div class="form-group"><label>Booking No.</label><input class="form-control" name="booking_no" value="<?= e($bill['booking_no'] ?? '') ?>"></div>
      <div class="form-group"><label>Date of Sale *</label><input class="form-control" type="date" name="vehicle_sale_date" value="<?= e($bill['vehicle_sale_date'] ?? '') ?>" required></div>
      <div class="form-group">
        <label>Billing location *</label>
        <select class="form-control" name="billing_location" required>
          <option value="kokamthan" <?= $billingLoc === 'kokamthan' ? 'selected' : '' ?>>Kokamthan</option>
          <option value="kopargaon" <?= $billingLoc === 'kopargaon' ? 'selected' : '' ?>>Kopargaon</option>
        </select>
      </div>
      <?php if (!$isSpareBill): ?>
      <div class="form-group"><label>EV Model Name</label><input class="form-control" name="vehicle_model" value="<?= e($bill['vehicle_model'] ?? '') ?>"></div>
      <div class="form-group"><label>EV Model Type</label><input class="form-control" name="vehicle_model_type" value="<?= e($bill['vehicle_model_type'] ?? '') ?>"></div>
      <div class="form-group"><label>Model Color</label><input class="form-control" name="color" value="<?= e($bill['color'] ?? '') ?>"></div>
      <?php endif; ?>
      <div class="form-group full" style="grid-column:1 / -1;padding:0.85rem 1rem;border-radius:12px;background:#f0fdf4;border:1px solid #86efac;">
        <template x-if="lines.length === 1 && productType === 'vehicle'">
          <div class="form-group" style="margin:0;">
            <label>Vehicle sell amount (₹) *</label>
            <input type="hidden" :name="'item_id[0]'" :value="lines[0].id">
            <input type="hidden" :name="'quantity[0]'" :value="lines[0].quantity">
            <input class="form-control" type="number" step="0.01" min="0.01" name="unit_price[0]" x-model="lines[0].unit_price" required
                   placeholder="Enter the price at which you are selling this vehicle">
            <p class="muted" style="margin:0.35rem 0 0;font-size:0.82rem;">This is your <strong>selling price</strong> (before GST) — not the purchase/PO cost.</p>
          </div>
        </template>
        <template x-if="!(lines.length === 1 && productType === 'vehicle')">
          <div>
            <label style="display:block;margin-bottom:0.5rem;">Sell amount (₹) *</label>
            <template x-for="(it, idx) in lines" :key="it.id || idx">
              <div class="form-grid" style="margin-bottom:0.5rem;align-items:end;">
                <div class="form-group" style="margin:0;">
                  <label style="font-size:0.82rem;" x-text="it.description || ('Line ' + (idx+1))"></label>
                  <input type="hidden" :name="'item_id['+idx+']'" :value="it.id">
                  <input type="hidden" :name="'quantity['+idx+']'" :value="it.quantity">
                  <input class="form-control" type="number" step="0.01" min="0.01" :name="'unit_price['+idx+']'" x-model="it.unit_price" required placeholder="Selling price">
                </div>
                <div class="form-group" style="margin:0;" x-show="(parseInt(it.quantity,10)||1) > 1">
                  <label style="font-size:0.82rem;">Qty</label>
                  <input class="form-control" type="number" :value="it.quantity" readonly>
                </div>
              </div>
            </template>
            <p class="muted" style="margin:0.25rem 0 0;font-size:0.78rem;">Enter the <strong>sell amount</strong> per line — the price you are selling at, not PO/purchase cost.</p>
          </div>
        </template>
      </div>
    </div>

    <h4 style="margin:1.25rem 0 0.5rem;font-size:0.95rem;">Buyer</h4>
    <div class="form-grid">
      <div class="form-group"><label>Cust. Name *</label><input class="form-control" name="customer_name" value="<?= e($bill['customer_name'] ?? '') ?>" required></div>
      <div class="form-group"><label>Mob. *</label><input class="form-control contact-input" name="customer_phone" type="tel" maxlength="11" inputmode="numeric" placeholder="98765 43210" value="<?= e(format_phone($bill['customer_phone'] ?? '')) ?>" required></div>
      <div class="form-group"><label>Email</label><input class="form-control" name="customer_email" type="email" value="<?= e($bill['customer_email'] ?? '') ?>"></div>
      <div class="form-group"><label>Aadhar No.</label><input class="form-control aadhar-input" name="customer_aadhaar" maxlength="14" inputmode="numeric" placeholder="1234 5678 9012" value="<?= e(format_aadhar($bill['customer_aadhaar'] ?? '')) ?>"></div>
      <div class="form-group"><label>PAN No.</label><input class="form-control" name="customer_pan" value="<?= e($bill['customer_pan'] ?? '') ?>"></div>
      <div class="form-group full"><label>Add. (Address)</label><textarea class="form-control" name="customer_address" rows="2"><?= e($bill['customer_address'] ?? '') ?></textarea></div>
    </div>

    <?php if (!$isSpareBill): ?>
    <?php
      $warrantyOpts = ['6 months', '12 months', '18 months', '24 months', '36 months', 'N/A'];
      $wEdit = static function (string $name, ?string $current) use ($warrantyOpts): void {
          $current = (string)($current ?? '');
          echo '<select class="form-control" name="' . e($name) . '">';
          echo '<option value="">Select warranty</option>';
          foreach ($warrantyOpts as $opt) {
              echo '<option value="' . e($opt) . '"' . ($opt === $current ? ' selected' : '') . '>' . e($opt) . '</option>';
          }
          if ($current !== '' && !in_array($current, $warrantyOpts, true)) {
              echo '<option value="' . e($current) . '" selected>' . e($current) . '</option>';
          }
          echo '</select>';
      };
    ?>
    <h4 style="margin:1.25rem 0 0.5rem;font-size:0.95rem;">Parts &amp; warranty</h4>
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
          <div class="form-group"><label>Battery Type</label><input class="form-control" name="battery_capacity" value="<?= e($batteryCapacity) ?>" placeholder="e.g. Lithium 60V"></div>
          <div class="form-group"><label>Battery No.</label><input class="form-control" name="battery_no" value="<?= e($batteryNo) ?>"></div>
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
    <?php endif; ?>

    <h4 style="margin:1.25rem 0 0.5rem;font-size:0.95rem;">GST</h4>
    <div class="form-grid">
      <div class="form-group">
        <label>GST option *</label>
        <select class="form-control" x-model="gstPreset" @change="applyGstPreset()">
          <option value="default">Default — <?= $isSpareBill ? '18% (9+9)' : '28% (14+14)' ?></option>
          <option value="28">28% — CGST 14% + SGST 14%</option>
          <option value="18">18% — CGST 9% + SGST 9%</option>
          <option value="12">12% — CGST 6% + SGST 6%</option>
          <option value="5">5% — CGST 2.5% + SGST 2.5%</option>
          <option value="0">0% — No GST</option>
          <option value="custom">Custom rates</option>
        </select>
      </div>
      <template x-if="gstPreset === 'custom'">
        <div class="form-group"><label>CGST % *</label><input class="form-control" type="number" step="0.01" min="0" max="100" name="cgst_rate" x-model="cgstRate" required></div>
      </template>
      <template x-if="gstPreset === 'custom'">
        <div class="form-group"><label>SGST % *</label><input class="form-control" type="number" step="0.01" min="0" max="100" name="sgst_rate" x-model="sgstRate" required></div>
      </template>
      <template x-if="gstPreset !== 'custom'">
        <div style="display:none">
          <input type="hidden" name="cgst_rate" :value="cgstRate">
          <input type="hidden" name="sgst_rate" :value="sgstRate">
        </div>
      </template>
      <div class="form-group full">
        <div style="padding:0.75rem 1rem;border-radius:10px;background:#f8fafc;border:1px solid var(--border);max-width:420px;">
          <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:0.25rem;"><span>Sell amount</span><span x-text="money(subtotal)"></span></div>
          <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:0.25rem;"><span x-text="'GST @ ' + totalGstPercent + '%'"></span><span x-text="money(gstAmount)"></span></div>
          <div style="display:flex;justify-content:space-between;font-weight:800;padding-top:0.35rem;border-top:1px solid var(--border);"><span>Invoice total</span><span x-text="money(grandTotal)"></span></div>
        </div>
      </div>
    </div>

    <h4 style="margin:1.25rem 0 0.5rem;font-size:0.95rem;">Payment</h4>
    <p class="muted" style="margin:-0.25rem 0 0.65rem;font-size:0.82rem;">Payment is against the invoice total (sell amount + GST).</p>
    <div class="form-grid">
      <div class="form-group full">
        <label>Payment status *</label>
        <div style="display:flex;gap:1.25rem;flex-wrap:wrap;margin-top:0.35rem;align-items:center;">
          <label style="display:flex;align-items:center;gap:0.45rem;font-weight:600;cursor:pointer;">
            <input type="radio" name="payment_status" value="full" x-model="paymentStatus"> Full paid
          </label>
          <label style="display:flex;align-items:center;gap:0.45rem;font-weight:600;cursor:pointer;">
            <input type="radio" name="payment_status" value="partial" x-model="paymentStatus"> Partial paid
          </label>
          <button class="btn btn-sm btn-outline" type="button" @click="fillInvoiceTotal()" x-show="grandTotal > 0">Use invoice total in cash</button>
        </div>
      </div>
      <div class="form-group">
        <label>Cash — amount paid (₹)</label>
        <input class="form-control" type="number" step="0.01" min="0" name="paid_cash_amount" x-model="paidCash" placeholder="0">
      </div>
      <div class="form-group">
        <label>Bank (online) — amount paid (₹)</label>
        <input class="form-control" type="number" step="0.01" min="0" name="paid_bank_amount" x-model="paidBank" placeholder="0">
      </div>
      <div class="form-group">
        <label>From loan — amount given (₹)</label>
        <input class="form-control" type="number" step="0.01" min="0" name="paid_loan_amount" x-model="paidLoan" placeholder="0">
      </div>
      <?php if (!empty($bankAccounts)): ?>
      <div class="form-group full" x-show="parseFloat(paidBank) > 0" x-cloak>
        <label>Bank account (for online payment) *</label>
        <select class="form-control" name="bank_account_id" x-model="bankAccountId" :required="parseFloat(paidBank) > 0">
          <option value="">Select account</option>
          <?php foreach ($bankAccounts as $ba): ?>
            <option value="<?= (int)$ba['id'] ?>"><?= e($ba['account_name']) ?> — <?= e($ba['bank_name']) ?> (<?= money($ba['current_balance']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="form-group full">
        <div style="padding:0.85rem 1rem;border-radius:12px;background:var(--surface-2,#f8fafc);border:1px solid var(--border);max-width:420px;">
          <div class="muted" style="display:flex;justify-content:space-between;margin-bottom:0.35rem;font-size:0.82rem;"><span>Invoice total</span><span x-text="money(grandTotal)"></span></div>
          <div class="muted" style="display:flex;justify-content:space-between;margin-bottom:0.35rem;font-size:0.82rem;"><span>Cash + bank + loan entered</span><span x-text="money(totalPaidNow)"></span></div>
          <div style="display:flex;justify-content:space-between;font-weight:800;padding-top:0.5rem;border-top:1px solid var(--border);">
            <span x-text="paymentStatus === 'full' ? 'Must equal invoice total' : 'Balance due'"></span>
            <span x-text="paymentStatus === 'full' ? money(Math.abs(grandTotal - totalPaidNow)) : money(balanceDue)"></span>
          </div>
        </div>
      </div>
    </div>

    <div style="margin-top:1rem;"><button class="btn btn-primary" type="submit">Save sell order / invoice</button></div>
  </form>
</div>
<?php endif; ?>
