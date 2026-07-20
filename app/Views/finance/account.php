<div x-data="{ editOpen: false, creditOpen: false, debitOpen: false }">
  <div class="toolbar">
    <div>
      <a class="muted" href="<?= url('finance?tab=banks') ?>" style="font-size:0.82rem;">← Bank accounts</a>
      <h1 class="page-title" style="margin:0.25rem 0 0;"><?= e($account['account_name']) ?></h1>
      <p class="page-sub" style="margin:0.25rem 0 0;"><?= e($account['bank_name']) ?> · <?= e($account['account_number']) ?><?= $account['ifsc_code'] ? ' · ' . e($account['ifsc_code']) : '' ?></p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <button class="btn btn-outline" type="button" @click="editOpen=true">Edit account</button>
      <button class="btn btn-primary" type="button" @click="creditOpen=true">+ Credit</button>
      <button class="btn btn-outline" type="button" @click="debitOpen=true">− Debit</button>
    </div>
  </div>

  <div class="stat-grid" style="margin-bottom:1rem;">
    <div class="stat-card">
      <div class="stat-label">Current balance</div>
      <div class="stat-value"><?= money($account['current_balance']) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Account type</div>
      <div class="stat-value" style="font-size:1.1rem;"><?= e(ucfirst($account['account_type'])) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Status</div>
      <div class="stat-value" style="font-size:1.1rem;"><?= (int)$account['is_active'] ? 'Active' : 'Inactive' ?></div>
    </div>
  </div>

  <div class="card">
    <h3 class="card-title">Transaction ledger</h3>
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Description</th>
            <th>Reference</th>
            <th>Amount</th>
            <th>Balance after</th>
            <th>By</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($transactions as $tx): ?>
          <tr>
            <td><?= e(date('d M Y', strtotime($tx['transaction_date']))) ?></td>
            <td>
              <?php if ($tx['transaction_type'] === 'credit'): ?>
                <span style="color:#15803d;font-weight:600;">Credit</span>
              <?php else: ?>
                <span style="color:#b45309;font-weight:600;">Debit</span>
              <?php endif; ?>
            </td>
            <td><?= e($tx['description']) ?></td>
            <td>
              <?php if ($tx['reference_type'] === 'sell_order' && $tx['reference_id']): ?>
                <a href="<?= url('orders/' . (int)$tx['reference_id']) ?>">Sell order</a>
              <?php elseif ($tx['reference_type'] === 'purchase_order' && $tx['reference_id']): ?>
                <a href="<?= url('purchase-orders/' . (int)$tx['reference_id']) ?>">Purchase order</a>
              <?php else: ?>
                <?= e(ucfirst(str_replace('_', ' ', $tx['reference_type']))) ?>
              <?php endif; ?>
            </td>
            <td><?= $tx['transaction_type'] === 'credit' ? '+' : '−' ?><?= money($tx['amount']) ?></td>
            <td><strong><?= money($tx['balance_after']) ?></strong></td>
            <td><?= e(trim($tx['first_name'] . ' ' . $tx['last_name'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$transactions): ?>
          <tr><td colspan="7" class="muted">No transactions yet.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:editOpen}" @click.self="editOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('finance/bank-accounts/' . $account['id']) ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Edit Bank Account</h3></div>
        <div class="modal-body form-grid">
          <div class="form-group"><label>Account name</label><input class="form-control" name="account_name" value="<?= e($account['account_name']) ?>" required></div>
          <div class="form-group"><label>Bank name</label><input class="form-control" name="bank_name" value="<?= e($account['bank_name']) ?>" required></div>
          <div class="form-group"><label>Account number</label><input class="form-control" name="account_number" value="<?= e($account['account_number']) ?>" required></div>
          <div class="form-group"><label>IFSC</label><input class="form-control" name="ifsc_code" value="<?= e($account['ifsc_code'] ?? '') ?>"></div>
          <div class="form-group"><label>Type</label>
            <select class="form-control" name="account_type">
              <?php foreach (['current','savings','overdraft'] as $t): ?>
                <option value="<?= $t ?>" <?= $account['account_type'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Active</label>
            <select class="form-control" name="is_active">
              <option value="1" <?= (int)$account['is_active'] ? 'selected' : '' ?>>Active</option>
              <option value="0" <?= !(int)$account['is_active'] ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="editOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Update</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:creditOpen}" @click.self="creditOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('finance/bank-accounts/' . $account['id'] . '/transactions') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="transaction_type" value="credit">
        <div class="modal-header"><h3 class="modal-title">Manual credit</h3></div>
        <div class="modal-body form-grid">
          <div class="form-group"><label>Amount (₹) *</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" required></div>
          <div class="form-group"><label>Date *</label><input class="form-control" type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required></div>
          <div class="form-group full"><label>Description *</label><input class="form-control" name="description" required placeholder="e.g. Cash deposit, refund received"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="creditOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Record credit</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:debitOpen}" @click.self="debitOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('finance/bank-accounts/' . $account['id'] . '/transactions') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="transaction_type" value="debit">
        <div class="modal-header"><h3 class="modal-title">Manual debit</h3></div>
        <div class="modal-body form-grid">
          <div class="form-group"><label>Amount (₹) *</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" required></div>
          <div class="form-group"><label>Date *</label><input class="form-control" type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required></div>
          <div class="form-group full"><label>Description *</label><input class="form-control" name="description" required placeholder="e.g. Supplier payment, bank charges"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="debitOpen=false">Cancel</button>
          <button class="btn btn-danger" type="submit">Record debit</button>
        </div>
      </form>
    </div>
  </div>
</div>
