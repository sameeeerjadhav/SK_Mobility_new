<?php
$partnerInitials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = strtoupper(substr($parts[0] ?? 'P', 0, 1));
    $second = strtoupper(substr($parts[1] ?? '', 0, 1));
    return $first . $second;
};
$partnersJson = json_encode($partners, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$transactionsJson = json_encode($transactions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
$txTypeChip = static function (string $type): string {
    return match ($type) {
        'payment' => 'chip-warning',
        'receipt' => 'chip-success',
        default => 'chip-info',
    };
};
?>
<style>
.pn-page { --pn-gap: 1rem; }
.pn-stats {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.85rem;
  margin-bottom: 1rem;
}
.pn-stat {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1rem 1.05rem;
  box-shadow: var(--shadow-sm);
  min-width: 0;
}
.pn-stat .k {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--muted);
  margin-bottom: 0.35rem;
}
.pn-stat .v {
  font-size: clamp(1rem, 1.8vw, 1.35rem);
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1.25;
  word-break: break-word;
  overflow-wrap: anywhere;
}
.pn-stat .hint { font-size: 0.78rem; color: var(--muted); margin-top: 0.25rem; }
.pn-stat.paid .v { color: #b45309; }
.pn-stat.received .v { color: #047857; }
.pn-stat.net .v { color: #0f766e; }
.pn-layout { display: grid; grid-template-columns: 1.15fr 0.85fr; gap: var(--pn-gap); align-items: start; }
.pn-card { background: #fff; border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--shadow-sm); overflow: hidden; }
.pn-card-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
  padding: 0.95rem 1rem;
  border-bottom: 1px solid var(--border);
  background: linear-gradient(180deg, #fafcfb 0%, #fff 100%);
}
.pn-card-head h3 { margin: 0; font-size: 0.98rem; font-weight: 800; letter-spacing: -0.02em; }
.pn-search {
  min-width: 200px;
  flex: 1;
  max-width: 280px;
}
.pn-list { display: grid; gap: 0; }
.pn-partner {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: 0.85rem;
  padding: 0.95rem 1rem;
  border-bottom: 1px solid #eef2f6;
  align-items: start;
}
.pn-partner:last-child { border-bottom: 0; }
.pn-partner:hover { background: #f8fafc; }
.pn-avatar {
  width: 42px;
  height: 42px;
  border-radius: 11px;
  background: linear-gradient(135deg, var(--primary-light), var(--primary));
  color: #fff;
  font-weight: 800;
  font-size: 0.82rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.pn-partner.inactive .pn-avatar { background: linear-gradient(135deg, #cbd5e1, #94a3b8); }
.pn-name { font-weight: 800; font-size: 0.95rem; letter-spacing: -0.02em; margin: 0 0 0.25rem; }
.pn-meta { display: flex; flex-wrap: wrap; gap: 0.45rem 0.85rem; font-size: 0.82rem; color: #475569; }
.pn-meta span { display: inline-flex; align-items: center; gap: 0.25rem; }
.pn-docs { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.45rem; }
.pn-doc {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 0.18rem 0.45rem;
  border-radius: 999px;
  background: #f1f5f9;
  color: #475569;
}
.pn-actions { display: flex; gap: 0.35rem; flex-wrap: wrap; justify-content: flex-end; }
.pn-empty { padding: 2rem 1rem; text-align: center; color: var(--muted); }
.pn-tx-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.pn-tx-table th {
  text-align: left;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--muted);
  padding: 0.65rem 1rem;
  border-bottom: 1px solid var(--border);
  background: #fafcfb;
}
.pn-tx-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #eef2f6; vertical-align: middle; }
.pn-tx-table tr:last-child td { border-bottom: 0; }
.pn-tx-table tr:hover td { background: #f8fafc; }
.pn-amt { font-weight: 800; white-space: nowrap; }
.pn-amt.payment { color: #b45309; }
.pn-amt.receipt { color: #047857; }
.pn-amt.adjustment { color: #0369a1; }
.pn-tx-actions { display: flex; gap: 0.3rem; flex-wrap: wrap; justify-content: flex-end; white-space: nowrap; }
.pn-view-rows { display: grid; gap: 0.55rem; }
.pn-view-row { display: grid; grid-template-columns: 7.5rem 1fr; gap: 0.65rem; font-size: 0.9rem; }
.pn-view-row .k { color: var(--muted); font-weight: 600; font-size: 0.82rem; }
.pn-view-row .v { font-weight: 600; }
@media (max-width: 960px) {
  .pn-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .pn-layout { grid-template-columns: 1fr; }
  .pn-partner { grid-template-columns: auto 1fr; }
  .pn-actions { grid-column: 1 / -1; justify-content: flex-start; }
}
</style>

<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('partnerPage', () => ({
    partnerOpen: false,
    txOpen: false,
    editPartner: null,
    viewTx: null,
    editTx: null,
    txSearch: '',
    search: '',
    partners: <?= $partnersJson ?>,
    transactions: <?= $transactionsJson ?>,
    matches(partner) {
      const q = this.search.trim().toLowerCase();
      if (!q) return true;
      const hay = [
        partner.name,
        partner.phone,
        partner.email,
        partner.address,
        partner.aadhar_number,
        partner.pan_number,
      ].join(' ').toLowerCase();
      return hay.includes(q);
    },
    openEdit(partner) {
      this.editPartner = JSON.parse(JSON.stringify(partner));
      if (this.editPartner?.aadhar_number) {
        this.editPartner.aadhar_number = window.formatAadharValue(this.editPartner.aadhar_number);
      }
      if (this.editPartner?.phone) {
        this.editPartner.phone = window.formatContactValue(this.editPartner.phone);
      }
    },
    openEditById(id) {
      const partner = this.partners.find(p => Number(p.id) === Number(id));
      if (partner) this.openEdit(partner);
    },
    matchesById(id) {
      const partner = this.partners.find(p => Number(p.id) === Number(id));
      return partner ? this.matches(partner) : false;
    },
    matchesTx(tx) {
      const q = this.txSearch.trim().toLowerCase();
      if (!q) return true;
      const hay = [
        tx.partner_name,
        tx.transaction_type,
        tx.reference_number,
        tx.description,
        tx.amount,
      ].join(' ').toLowerCase();
      return hay.includes(q);
    },
    matchesTxById(id) {
      const tx = this.transactions.find(t => Number(t.id) === Number(id));
      return tx ? this.matchesTx(tx) : false;
    },
    openViewTx(id) {
      const tx = this.transactions.find(t => Number(t.id) === Number(id));
      if (tx) this.viewTx = JSON.parse(JSON.stringify(tx));
    },
    openEditTx(id) {
      const tx = this.transactions.find(t => Number(t.id) === Number(id));
      if (tx) this.editTx = JSON.parse(JSON.stringify(tx));
    },
    txTypeLabel(type) {
      return ({ payment: 'Payment (outgoing)', receipt: 'Receipt (incoming)', adjustment: 'Adjustment' })[type] || type;
    },
    initials(name) {
      const parts = (name || '').trim().split(/\s+/);
      return ((parts[0]?.[0] || 'P') + (parts[1]?.[0] || '')).toUpperCase();
    },
  }));
});
</script>

<div class="pn-page" x-data="partnerPage()">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Partners</h1>
      <p class="page-sub">Manage business partners, contact details, and payment history</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <button class="btn btn-outline" type="button" @click="txOpen=true" <?= !$partners ? 'disabled title="Add a partner first"' : '' ?>>+ Transaction</button>
      <button class="btn btn-primary" type="button" @click="partnerOpen=true">+ Partner</button>
    </div>
  </div>

  <div class="pn-stats">
    <div class="pn-stat">
      <div class="k">Active partners</div>
      <div class="v"><?= (int)$stats['partners'] ?></div>
      <div class="hint"><?= (int)$stats['total_partners'] ?> total registered</div>
    </div>
    <div class="pn-stat paid">
      <div class="k">Total paid</div>
      <div class="v"><?= money($stats['paid']) ?></div>
      <div class="hint">Outgoing payments</div>
    </div>
    <div class="pn-stat received">
      <div class="k">Total received</div>
      <div class="v"><?= money($stats['received']) ?></div>
      <div class="hint">Incoming receipts</div>
    </div>
    <div class="pn-stat net">
      <div class="k">Net balance</div>
      <div class="v"><?= money($stats['net']) ?></div>
      <div class="hint"><?= (int)$stats['transactions'] ?> transactions logged</div>
    </div>
  </div>

  <div class="pn-layout">
    <div class="pn-card">
      <div class="pn-card-head">
        <h3>Partner directory</h3>
        <input class="form-control pn-search" type="search" placeholder="Search name, phone, email, PAN…" x-model="search">
      </div>
      <div class="pn-list">
        <?php foreach ($partners as $p): ?>
          <div class="pn-partner <?= empty($p['is_active']) ? 'inactive' : '' ?>"
               x-show="matchesById(<?= (int)$p['id'] ?>)"
               x-cloak>
            <div class="pn-avatar"><?= e($partnerInitials($p['name'])) ?></div>
            <div>
              <p class="pn-name">
                <?= e($p['name']) ?>
                <?= !empty($p['is_active']) ? status_chip('active') : status_chip('inactive') ?>
              </p>
              <div class="pn-meta">
                <?php if (!empty($p['phone'])): ?>
                  <span><?= e(format_phone($p['phone'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($p['email'])): ?>
                  <span><?= e($p['email']) ?></span>
                <?php endif; ?>
              </div>
              <?php if (!empty($p['address'])): ?>
                <div class="muted" style="font-size:0.8rem;margin-top:0.35rem;line-height:1.45;"><?= e($p['address']) ?></div>
              <?php endif; ?>
              <?php if (!empty($p['aadhar_number']) || !empty($p['pan_number'])): ?>
                <div class="pn-docs">
                  <?php if (!empty($p['aadhar_number'])): ?>
                    <span class="pn-doc">Aadhar: <?= e(format_aadhar($p['aadhar_number'])) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($p['pan_number'])): ?>
                    <span class="pn-doc">PAN: <?= e(strtoupper($p['pan_number'])) ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="pn-actions">
              <button class="btn btn-sm btn-outline" type="button" @click="openEditById(<?= (int)$p['id'] ?>)">Edit</button>
              <form method="post" action="<?= url('partners/' . $p['id'] . '/delete') ?>" onsubmit="return confirm('Delete this partner and all transactions?')">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-danger" type="submit">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$partners): ?>
          <div class="pn-empty">No partners yet. Click <strong>+ Partner</strong> to add one.</div>
        <?php else: ?>
          <div class="pn-empty" x-show="search.trim() && !partners.some(p => matches(p))" x-cloak>No partners match your search.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="pn-card">
      <div class="pn-card-head">
        <h3>Recent transactions</h3>
        <input class="form-control pn-search" type="search" placeholder="Search transactions…" x-model="txSearch">
      </div>
      <?php if ($transactions): ?>
        <div class="table-wrap">
          <table class="pn-tx-table">
            <thead>
              <tr>
                <th>Partner</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Reference</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $t): ?>
              <?php $txType = $t['transaction_type']; ?>
              <tr x-show="matchesTxById(<?= (int)$t['id'] ?>)" x-cloak>
                <td><strong><?= e($t['partner_name']) ?></strong></td>
                <td><span class="chip <?= $txTypeChip($txType) ?>"><?= e(ucfirst($txType)) ?></span></td>
                <td class="pn-amt <?= e($txType) ?>"><?= money($t['amount']) ?></td>
                <td><?= india_date($t['date']) ?></td>
                <td class="muted" style="font-size:0.82rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($t['reference_number'] ?: '—') ?></td>
                <td>
                  <div class="pn-tx-actions">
                    <button class="btn btn-sm btn-outline" type="button" @click="openViewTx(<?= (int)$t['id'] ?>)">View</button>
                    <button class="btn btn-sm btn-outline" type="button" @click="openEditTx(<?= (int)$t['id'] ?>)">Edit</button>
                    <form method="post" action="<?= url('partner-transactions/' . $t['id'] . '/delete') ?>" onsubmit="return confirm('Delete this transaction?')">
                      <?= csrf_field() ?>
                      <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="pn-empty" x-show="txSearch.trim() && !transactions.some(t => matchesTx(t))" x-cloak>No transactions match your search.</div>
        <?php \App\Core\View::partial('partials/pagination', ['pagination' => $pagination ?? [], 'filters' => $filters ?? []]); ?>
      <?php else: ?>
        <div class="pn-empty">No transactions recorded yet.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:partnerOpen}" @click.self="partnerOpen=false">
    <div class="modal" style="max-width:560px;">
      <form method="post" action="<?= url('partners') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Add partner</h3></div>
        <div class="modal-body form-grid">
          <div class="form-group full"><label>Name *</label><input class="form-control" name="name" required placeholder="Full name or business name"></div>
          <div class="form-group"><label>Contact No.</label><input class="form-control contact-input" name="phone" type="tel" maxlength="11" inputmode="numeric" placeholder="98765 43210"></div>
          <div class="form-group"><label>Email ID</label><input class="form-control" name="email" type="email" placeholder="name@example.com"></div>
          <div class="form-group full"><label>Address</label><textarea class="form-control" name="address" rows="2" placeholder="Street, city, pin code"></textarea></div>
          <div class="form-group"><label>Aadhar No.</label><input class="form-control aadhar-input" name="aadhar_number" maxlength="14" inputmode="numeric" placeholder="1234 5678 9012"></div>
          <div class="form-group"><label>PAN Number</label><input class="form-control" name="pan_number" maxlength="10" style="text-transform:uppercase;" placeholder="ABCDE1234F"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="partnerOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Save partner</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:!!editPartner}" @click.self="editPartner=null" x-show="editPartner" x-cloak>
    <div class="modal" style="max-width:560px;" x-show="editPartner">
      <form method="post" :action="'<?= url('partners') ?>/' + editPartner?.id">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Edit partner</h3></div>
        <div class="modal-body form-grid" x-show="editPartner">
          <div class="form-group full"><label>Name *</label><input class="form-control" name="name" x-model="editPartner.name" required></div>
          <div class="form-group"><label>Contact No.</label><input class="form-control contact-input" name="phone" type="tel" maxlength="11" inputmode="numeric" placeholder="98765 43210" x-model="editPartner.phone" @input="editPartner.phone = formatContactValue($event.target.value)"></div>
          <div class="form-group"><label>Email ID</label><input class="form-control" name="email" type="email" x-model="editPartner.email"></div>
          <div class="form-group full"><label>Address</label><textarea class="form-control" name="address" x-model="editPartner.address" rows="2"></textarea></div>
          <div class="form-group"><label>Aadhar No.</label><input class="form-control aadhar-input" name="aadhar_number" maxlength="14" inputmode="numeric" placeholder="1234 5678 9012" x-model="editPartner.aadhar_number" @input="editPartner.aadhar_number = formatAadharValue($event.target.value)"></div>
          <div class="form-group"><label>PAN Number</label><input class="form-control" name="pan_number" maxlength="10" style="text-transform:uppercase;" x-model="editPartner.pan_number"></div>
          <div class="form-group"><label>Status</label>
            <select class="form-control" name="is_active" x-model="editPartner.is_active">
              <option :value="1">Active</option>
              <option :value="0">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="editPartner=null">Cancel</button>
          <button class="btn btn-primary" type="submit">Update partner</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:txOpen}" @click.self="txOpen=false">
    <div class="modal" style="max-width:560px;">
      <form method="post" action="<?= url('partner-transactions') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Record transaction</h3></div>
        <div class="modal-body form-grid">
          <div class="form-group full">
            <label>Partner *</label>
            <select class="form-control" name="partner_id" required>
              <?php foreach ($partners as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Type *</label>
            <select class="form-control" name="transaction_type">
              <option value="payment">Payment (outgoing)</option>
              <option value="receipt">Receipt (incoming)</option>
              <option value="adjustment">Adjustment</option>
            </select>
          </div>
          <div class="form-group"><label>Amount *</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" required></div>
          <div class="form-group"><label>Date</label><input class="form-control" type="date" name="date" value="<?= date('Y-m-d') ?>"></div>
          <div class="form-group full"><label>Reference no.</label><input class="form-control" name="reference_number" placeholder="Cheque / UTR / invoice no."></div>
          <div class="form-group full"><label>Description</label><textarea class="form-control" name="description" rows="2" placeholder="Optional notes"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="txOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Save transaction</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:!!viewTx}" @click.self="viewTx=null" x-show="viewTx" x-cloak>
    <div class="modal" style="max-width:520px;" x-show="viewTx">
      <div class="modal-header">
        <h3 class="modal-title">Transaction details</h3>
      </div>
      <div class="modal-body" x-show="viewTx">
        <div class="pn-view-rows">
          <div class="pn-view-row"><span class="k">Partner</span><span class="v" x-text="viewTx?.partner_name"></span></div>
          <div class="pn-view-row"><span class="k">Type</span><span class="v" x-text="viewTx ? viewTx.transaction_type.charAt(0).toUpperCase() + viewTx.transaction_type.slice(1) : ''"></span></div>
          <div class="pn-view-row"><span class="k">Amount</span><span class="v" x-text="viewTx ? '₹' + (parseFloat(viewTx.amount)||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}) : ''"></span></div>
          <div class="pn-view-row"><span class="k">Date</span><span class="v" x-text="viewTx?.date"></span></div>
          <div class="pn-view-row"><span class="k">Reference</span><span class="v" x-text="viewTx?.reference_number || '—'"></span></div>
          <div class="pn-view-row"><span class="k">Description</span><span class="v" x-text="viewTx?.description || '—'"></span></div>
          <div class="pn-view-row"><span class="k">Recorded by</span><span class="v" x-text="viewTx ? ((viewTx.first_name || '') + ' ' + (viewTx.last_name || '')).trim() || '—' : ''"></span></div>
          <div class="pn-view-row"><span class="k">Transaction ID</span><span class="v" x-text="viewTx ? '#' + viewTx.id : ''"></span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" @click="viewTx=null">Close</button>
        <button type="button" class="btn btn-primary" @click="openEditTx(viewTx.id); viewTx=null">Edit</button>
      </div>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:!!editTx}" @click.self="editTx=null" x-show="editTx" x-cloak>
    <div class="modal" style="max-width:560px;" x-show="editTx">
      <form method="post" :action="'<?= url('partner-transactions') ?>/' + editTx?.id">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Edit transaction</h3></div>
        <div class="modal-body form-grid" x-show="editTx">
          <div class="form-group full">
            <label>Partner *</label>
            <select class="form-control" name="partner_id" x-model="editTx.partner_id" required>
              <?php foreach ($partners as $p): ?>
                <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Type *</label>
            <select class="form-control" name="transaction_type" x-model="editTx.transaction_type">
              <option value="payment">Payment (outgoing)</option>
              <option value="receipt">Receipt (incoming)</option>
              <option value="adjustment">Adjustment</option>
            </select>
          </div>
          <div class="form-group"><label>Amount *</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" x-model="editTx.amount" required></div>
          <div class="form-group"><label>Date</label><input class="form-control" type="date" name="date" x-model="editTx.date"></div>
          <div class="form-group full"><label>Reference no.</label><input class="form-control" name="reference_number" x-model="editTx.reference_number" placeholder="Cheque / UTR / invoice no."></div>
          <div class="form-group full"><label>Description</label><textarea class="form-control" name="description" x-model="editTx.description" rows="2" placeholder="Optional notes"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="editTx=null">Cancel</button>
          <button class="btn btn-primary" type="submit">Update transaction</button>
        </div>
      </form>
    </div>
  </div>
</div>
