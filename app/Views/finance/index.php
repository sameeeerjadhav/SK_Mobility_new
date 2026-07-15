<div x-data="{ bankOpen: false, loanOpen: false }">
  <div class="toolbar">
    <div><h1 class="page-title">Finance</h1><p class="page-sub">Bank accounts & loans</p></div>
    <div style="display:flex;gap:0.5rem;">
      <?php if ($tab==='loans'): ?>
        <button class="btn btn-primary" type="button" @click="loanOpen=true">+ Loan</button>
      <?php else: ?>
        <button class="btn btn-primary" type="button" @click="bankOpen=true">+ Bank Account</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="stat-grid">
    <div class="stat-card"><div class="stat-label">Bank Balance</div><div class="stat-value"><?= money($stats['bank']) ?></div></div>
    <div class="stat-card"><div class="stat-label">Loan Outstanding</div><div class="stat-value"><?= money($stats['loans']) ?></div></div>
    <div class="stat-card"><div class="stat-label">Active Loans</div><div class="stat-value"><?= (int)$stats['active_loans'] ?></div></div>
  </div>
  <div class="tabs">
    <a class="tab <?= $tab==='banks'?'active':'' ?>" href="<?= url('finance?tab=banks') ?>">Bank Accounts</a>
    <a class="tab <?= $tab==='loans'?'active':'' ?>" href="<?= url('finance?tab=loans') ?>">Loans</a>
  </div>

  <?php if ($tab === 'loans'): ?>
  <div class="card"><div class="table-wrap"><table class="data">
    <thead><tr><th>Lender</th><th>Type</th><th>Principal</th><th>Outstanding</th><th>EMI</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($loans as $l): ?>
      <tr>
        <td><?= e($l['lender_name']) ?></td>
        <td><?= e(ucfirst($l['loan_type'])) ?></td>
        <td><?= money($l['principal_amount']) ?></td>
        <td><?= money($l['outstanding_amount']) ?></td>
        <td><?= money($l['emi_amount']) ?></td>
        <td><?= status_chip($l['status']) ?></td>
        <td>
          <form method="post" action="<?= url('finance/loans/'.$l['id'].'/delete') ?>" onsubmit="return confirm('Delete?')">
            <?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$loans): ?><tr><td colspan="7" class="muted">No loans.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
  <?php else: ?>
  <div class="card"><div class="table-wrap"><table class="data">
    <thead><tr><th>Account</th><th>Bank</th><th>Number</th><th>IFSC</th><th>Type</th><th>Balance</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($accounts as $a): ?>
      <tr>
        <td><?= e($a['account_name']) ?></td>
        <td><?= e($a['bank_name']) ?></td>
        <td><?= e($a['account_number']) ?></td>
        <td><?= e($a['ifsc_code'] ?? '—') ?></td>
        <td><?= e(ucfirst($a['account_type'])) ?></td>
        <td><strong><?= money($a['current_balance']) ?></strong></td>
        <td>
          <form method="post" action="<?= url('finance/bank-accounts/'.$a['id'].'/delete') ?>" onsubmit="return confirm('Delete?')">
            <?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$accounts): ?><tr><td colspan="7" class="muted">No bank accounts.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
  <?php endif; ?>

  <div class="modal-backdrop" :class="{open:bankOpen}" @click.self="bankOpen=false">
    <div class="modal"><form method="post" action="<?= url('finance/bank-accounts') ?>">
      <?= csrf_field() ?>
      <div class="modal-header"><h3 class="modal-title">Add Bank Account</h3></div>
      <div class="modal-body form-grid">
        <div class="form-group"><label>Account name</label><input class="form-control" name="account_name" required></div>
        <div class="form-group"><label>Bank name</label><input class="form-control" name="bank_name" required></div>
        <div class="form-group"><label>Account number</label><input class="form-control" name="account_number" required></div>
        <div class="form-group"><label>IFSC</label><input class="form-control" name="ifsc_code"></div>
        <div class="form-group"><label>Type</label>
          <select class="form-control" name="account_type"><option value="current">Current</option><option value="savings">Savings</option><option value="overdraft">Overdraft</option></select>
        </div>
        <div class="form-group"><label>Balance</label><input class="form-control" type="number" step="0.01" name="current_balance" value="0"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" @click="bankOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    </form></div>
  </div>

  <div class="modal-backdrop" :class="{open:loanOpen}" @click.self="loanOpen=false">
    <div class="modal"><form method="post" action="<?= url('finance/loans') ?>">
      <?= csrf_field() ?>
      <div class="modal-header"><h3 class="modal-title">Add Loan</h3></div>
      <div class="modal-body form-grid">
        <div class="form-group"><label>Lender</label><input class="form-control" name="lender_name" required></div>
        <div class="form-group"><label>Type</label>
          <select class="form-control" name="loan_type"><?php foreach (['vehicle','equipment','personal','business','other'] as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group"><label>Principal</label><input class="form-control" type="number" step="0.01" name="principal_amount" required></div>
        <div class="form-group"><label>Interest %</label><input class="form-control" type="number" step="0.01" name="interest_rate" value="0"></div>
        <div class="form-group"><label>Tenure (months)</label><input class="form-control" type="number" name="tenure_months" value="12"></div>
        <div class="form-group"><label>EMI</label><input class="form-control" type="number" step="0.01" name="emi_amount" value="0"></div>
        <div class="form-group"><label>Start date</label><input class="form-control" type="date" name="start_date"></div>
        <div class="form-group"><label>Outstanding</label><input class="form-control" type="number" step="0.01" name="outstanding_amount"></div>
        <div class="form-group full"><label>Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" @click="loanOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    </form></div>
  </div>
</div>
