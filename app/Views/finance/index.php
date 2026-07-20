<div x-data="{ bankOpen: false, loanOpen: false, editBank: null, editLoan: null }">
  <div class="toolbar">
    <div><h1 class="page-title">Finance</h1><p class="page-sub">Bank accounts, ledger &amp; loans</p></div>
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
        <td style="white-space:nowrap;">
          <button class="btn btn-sm btn-outline" type="button" @click='editLoan = <?= json_encode($l, JSON_HEX_APOS | JSON_HEX_TAG) ?>'>Edit</button>
          <form method="post" action="<?= url('finance/loans/'.$l['id'].'/delete') ?>" style="display:inline;" onsubmit="return confirm('Delete?')">
            <?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$loans): ?><tr><td colspan="7" class="muted">No loans.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
  <?php else: ?>
  <p class="muted" style="margin:0 0 0.75rem;font-size:0.82rem;">Balances update automatically from sell orders, purchase orders, and manual credit/debit entries.</p>
  <div class="card"><div class="table-wrap"><table class="data">
    <thead><tr><th>Account</th><th>Bank</th><th>Number</th><th>IFSC</th><th>Type</th><th>Balance</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($accounts as $a): ?>
      <tr>
        <td><a href="<?= url('finance/bank-accounts/' . $a['id']) ?>"><strong><?= e($a['account_name']) ?></strong></a></td>
        <td><?= e($a['bank_name']) ?></td>
        <td><?= e($a['account_number']) ?></td>
        <td><?= e($a['ifsc_code'] ?? '—') ?></td>
        <td><?= e(ucfirst($a['account_type'])) ?></td>
        <td><strong><?= money($a['current_balance']) ?></strong></td>
        <td><?= (int)$a['is_active'] ? status_chip('active') : status_chip('inactive') ?></td>
        <td style="white-space:nowrap;">
          <a class="btn btn-sm btn-primary" href="<?= url('finance/bank-accounts/' . $a['id']) ?>">Ledger</a>
          <button class="btn btn-sm btn-outline" type="button" @click='editBank = <?= json_encode($a, JSON_HEX_APOS | JSON_HEX_TAG) ?>'>Edit</button>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$accounts): ?><tr><td colspan="8" class="muted">No bank accounts.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
  <?php endif; ?>

  <div class="modal-backdrop" :class="{open:bankOpen}" @click.self="bankOpen=false">
    <div class="modal"><form method="post" action="<?= url('finance/bank-accounts') ?>">
      <?= csrf_field() ?>
      <div class="modal-header"><h3 class="modal-title">Add Bank Account</h3></div>
      <div class="modal-body form-grid">
        <div class="form-group"><label>Account name</label><input class="form-control" name="account_name" required placeholder="e.g. Main Current"></div>
        <div class="form-group"><label>Bank name</label><input class="form-control" name="bank_name" required></div>
        <div class="form-group"><label>Account number</label><input class="form-control" name="account_number" required></div>
        <div class="form-group"><label>IFSC</label><input class="form-control" name="ifsc_code"></div>
        <div class="form-group"><label>Type</label>
          <select class="form-control" name="account_type"><option value="current">Current</option><option value="savings">Savings</option><option value="overdraft">Overdraft</option></select>
        </div>
        <div class="form-group"><label>Opening balance (₹)</label><input class="form-control" type="number" step="0.01" min="0" name="current_balance" value="0">
          <p class="muted" style="margin:0.25rem 0 0;font-size:0.75rem;">Recorded as an opening credit in the ledger.</p>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" @click="bankOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    </form></div>
  </div>

  <div class="modal-backdrop" :class="{open:!!editBank}" @click.self="editBank=null" x-show="editBank" x-cloak>
    <div class="modal" x-show="editBank">
      <form method="post" :action="'<?= url('finance/bank-accounts') ?>/'+editBank?.id">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Edit Bank Account</h3></div>
        <div class="modal-body form-grid">
          <div class="form-group"><label>Account name</label><input class="form-control" name="account_name" :value="editBank?.account_name" required></div>
          <div class="form-group"><label>Bank name</label><input class="form-control" name="bank_name" :value="editBank?.bank_name" required></div>
          <div class="form-group"><label>Account number</label><input class="form-control" name="account_number" :value="editBank?.account_number" required></div>
          <div class="form-group"><label>IFSC</label><input class="form-control" name="ifsc_code" :value="editBank?.ifsc_code"></div>
          <div class="form-group"><label>Type</label>
            <select class="form-control" name="account_type" x-model="editBank.account_type">
              <option value="current">Current</option><option value="savings">Savings</option><option value="overdraft">Overdraft</option>
            </select>
          </div>
          <div class="form-group"><label>Active</label>
            <select class="form-control" name="is_active" x-model="String(editBank.is_active)">
              <option value="1">Active</option><option value="0">Inactive</option>
            </select>
          </div>
          <div class="form-group full">
            <p class="muted" style="margin:0;font-size:0.78rem;">Balance: ₹<span x-text="editBank?.current_balance"></span> — use Ledger page for manual credit/debit.</p>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="editBank=null">Cancel</button><button class="btn btn-primary" type="submit">Update</button></div>
      </form>
    </div>
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

  <div class="modal-backdrop" :class="{open:!!editLoan}" @click.self="editLoan=null" x-show="editLoan" x-cloak>
    <div class="modal" x-show="editLoan">
      <form method="post" :action="'<?= url('finance/loans') ?>/'+editLoan?.id">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Edit Loan</h3></div>
        <div class="modal-body form-grid">
          <div class="form-group"><label>Lender</label><input class="form-control" name="lender_name" :value="editLoan?.lender_name" required></div>
          <div class="form-group"><label>Type</label>
            <select class="form-control" name="loan_type" x-model="editLoan.loan_type">
              <?php foreach (['vehicle','equipment','personal','business','other'] as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Principal</label><input class="form-control" type="number" step="0.01" name="principal_amount" :value="editLoan?.principal_amount"></div>
          <div class="form-group"><label>Interest %</label><input class="form-control" type="number" step="0.01" name="interest_rate" :value="editLoan?.interest_rate"></div>
          <div class="form-group"><label>Tenure</label><input class="form-control" type="number" name="tenure_months" :value="editLoan?.tenure_months"></div>
          <div class="form-group"><label>EMI</label><input class="form-control" type="number" step="0.01" name="emi_amount" :value="editLoan?.emi_amount"></div>
          <div class="form-group"><label>Start date</label><input class="form-control" type="date" name="start_date" :value="editLoan?.start_date"></div>
          <div class="form-group"><label>End date</label><input class="form-control" type="date" name="end_date" :value="editLoan?.end_date"></div>
          <div class="form-group"><label>Outstanding</label><input class="form-control" type="number" step="0.01" name="outstanding_amount" :value="editLoan?.outstanding_amount"></div>
          <div class="form-group"><label>Status</label>
            <select class="form-control" name="status" x-model="editLoan.status">
              <option value="active">Active</option><option value="closed">Closed</option><option value="defaulted">Defaulted</option>
            </select>
          </div>
          <div class="form-group full"><label>Notes</label><textarea class="form-control" name="notes" x-text="editLoan?.notes" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="editLoan=null">Cancel</button><button class="btn btn-primary" type="submit">Update</button></div>
      </form>
    </div>
  </div>
</div>
