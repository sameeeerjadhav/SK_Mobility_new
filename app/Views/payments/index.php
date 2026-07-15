<div x-data="{ tab: 'records', payOpen: false }">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Payments</h1>
      <p class="page-sub">Revenue tracking & payment records</p>
    </div>
    <?php if ($canManage): ?>
      <button class="btn btn-primary" type="button" @click="payOpen=true">+ Record Payment</button>
    <?php endif; ?>
  </div>

  <div class="stat-grid">
    <div class="stat-card"><div class="stat-label">Total Revenue</div><div class="stat-value"><?= money($totals['all']) ?></div></div>
    <div class="stat-card"><div class="stat-label">This Month</div><div class="stat-value"><?= money($totals['month']) ?></div></div>
    <div class="stat-card"><div class="stat-label">This Year</div><div class="stat-value"><?= money($totals['year']) ?></div></div>
    <?php foreach ($byMethod as $m): ?>
      <div class="stat-card">
        <div class="stat-label"><?= e(ucwords(str_replace('_',' ',$m['payment_method']))) ?></div>
        <div class="stat-value" style="font-size:1.1rem;"><?= money($m['total']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($razorpayKey): ?>
    <div class="alert alert-info">Razorpay key configured (<?= e(substr($razorpayKey,0,8)) ?>…). Online checkout can be wired when activating payments on checkout.</div>
  <?php else: ?>
    <div class="alert alert-info">Razorpay keys not set in .env — online payments stub ready for later.</div>
  <?php endif; ?>

  <div class="tabs">
    <button type="button" class="tab" :class="{active: tab==='records'}" @click="tab='records'">Payment Records</button>
    <button type="button" class="tab" :class="{active: tab==='orders'}" @click="tab='orders'">Order Summaries</button>
  </div>

  <div class="card" x-show="tab==='records'">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Date</th><th>Order</th><th>Amount</th><th>Method</th><th>Ref</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><?= india_date($p['payment_date']) ?></td>
            <td><a href="<?= url('orders/' . $p['order_id']) ?>"><?= e($p['order_number']) ?></a></td>
            <td><?= money($p['amount']) ?></td>
            <td><?= e(ucwords(str_replace('_',' ',$p['payment_method']))) ?></td>
            <td><?= e($p['transaction_reference'] ?? '—') ?></td>
            <td><?= status_chip($p['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="6" class="muted">No payments recorded.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card" x-show="tab==='orders'" style="display:none;">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Order</th><th>Type</th><th>Total</th><th>Paid</th><th>Balance</th></tr></thead>
        <tbody>
        <?php foreach ($orderSummaries as $os): ?>
          <tr>
            <td><a href="<?= url('orders/' . $os['id']) ?>"><?= e($os['order_number']) ?></a></td>
            <td><?= e(ucfirst($os['order_type'])) ?></td>
            <td><?= money($os['total_amount']) ?></td>
            <td><?= money($os['paid']) ?></td>
            <td><?= money((float)$os['total_amount'] - (float)$os['paid']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($canManage): ?>
  <div class="modal-backdrop" :class="{ open: payOpen }" @click.self="payOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('payments') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Record Payment</h3><button type="button" class="btn btn-sm btn-outline" @click="payOpen=false">Close</button></div>
        <div class="modal-body">
          <div class="form-group">
            <label>Order</label>
            <select class="form-control" name="order_id" required>
              <option value="">Select order</option>
              <?php foreach ($orders as $o): ?>
                <option value="<?= (int)$o['id'] ?>"><?= e($o['order_number']) ?> — <?= money($o['total_amount']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-grid">
            <div class="form-group"><label>Amount</label><input class="form-control" type="number" step="0.01" name="amount" required></div>
            <div class="form-group"><label>Date</label><input class="form-control" type="date" name="payment_date" value="<?= date('Y-m-d') ?>"></div>
            <div class="form-group"><label>Method</label>
              <select class="form-control" name="payment_method">
                <?php foreach (['cash','bank_transfer','cheque','online','razorpay'] as $m): ?>
                  <option value="<?= $m ?>"><?= ucwords(str_replace('_',' ',$m)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label>Reference</label><input class="form-control" name="transaction_reference"></div>
            <div class="form-group full"><label>Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="payOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
