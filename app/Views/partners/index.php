<div x-data="{ partnerOpen: false, txOpen: false }">
  <div class="toolbar">
    <div><h1 class="page-title">Partners</h1><p class="page-sub">Vendors & transactions</p></div>
    <div style="display:flex;gap:0.5rem;">
      <button class="btn btn-outline" type="button" @click="txOpen=true">+ Transaction</button>
      <button class="btn btn-primary" type="button" @click="partnerOpen=true">+ Partner</button>
    </div>
  </div>
  <div class="stat-grid">
    <div class="stat-card"><div class="stat-label">Partners</div><div class="stat-value"><?= (int)$stats['partners'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Total Paid</div><div class="stat-value"><?= money($stats['paid']) ?></div></div>
    <div class="stat-card"><div class="stat-label">Total Received</div><div class="stat-value"><?= money($stats['received']) ?></div></div>
  </div>
  <div class="grid-2">
    <div class="card"><h3 class="card-title">Partners</h3>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Name</th><th>Type</th><th>Contact</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($partners as $p): ?>
          <tr>
            <td><?= e($p['name']) ?></td>
            <td><span class="chip chip-info"><?= e(ucfirst($p['type'])) ?></span></td>
            <td><?= e($p['contact_person'] ?? '—') ?><div class="muted" style="font-size:0.75rem;"><?= e($p['phone'] ?? '') ?></div></td>
            <td>
              <form method="post" action="<?= url('partners/'.$p['id'].'/delete') ?>" onsubmit="return confirm('Delete?')">
                <?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$partners): ?><tr><td colspan="4" class="muted">No partners.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
    <div class="card"><h3 class="card-title">Recent transactions</h3>
      <div class="table-wrap"><table class="data">
        <thead><tr><th>Partner</th><th>Type</th><th>Amount</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($transactions as $t): ?>
          <tr>
            <td><?= e($t['partner_name']) ?></td>
            <td><?= e(ucfirst($t['transaction_type'])) ?></td>
            <td><?= money($t['amount']) ?></td>
            <td><?= india_date($t['date']) ?></td>
            <td>
              <form method="post" action="<?= url('partner-transactions/'.$t['id'].'/delete') ?>" onsubmit="return confirm('Delete?')">
                <?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit">×</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:partnerOpen}" @click.self="partnerOpen=false">
    <div class="modal"><form method="post" action="<?= url('partners') ?>">
      <?= csrf_field() ?>
      <div class="modal-header"><h3 class="modal-title">Add Partner</h3></div>
      <div class="modal-body form-grid">
        <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
        <div class="form-group"><label>Type</label>
          <select class="form-control" name="type"><?php foreach (['vendor','distributor','supplier','other'] as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group"><label>Contact</label><input class="form-control" name="contact_person"></div>
        <div class="form-group"><label>Phone</label><input class="form-control" name="phone"></div>
        <div class="form-group"><label>Email</label><input class="form-control" name="email"></div>
        <div class="form-group"><label>GST</label><input class="form-control" name="gst_number"></div>
        <div class="form-group full"><label>Address</label><textarea class="form-control" name="address" rows="2"></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" @click="partnerOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    </form></div>
  </div>

  <div class="modal-backdrop" :class="{open:txOpen}" @click.self="txOpen=false">
    <div class="modal"><form method="post" action="<?= url('partner-transactions') ?>">
      <?= csrf_field() ?>
      <div class="modal-header"><h3 class="modal-title">Record Transaction</h3></div>
      <div class="modal-body form-grid">
        <div class="form-group full"><label>Partner</label>
          <select class="form-control" name="partner_id" required><?php foreach ($partners as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group"><label>Type</label>
          <select class="form-control" name="transaction_type"><option value="payment">Payment</option><option value="receipt">Receipt</option><option value="adjustment">Adjustment</option></select>
        </div>
        <div class="form-group"><label>Amount</label><input class="form-control" type="number" step="0.01" name="amount" required></div>
        <div class="form-group"><label>Date</label><input class="form-control" type="date" name="date" value="<?= date('Y-m-d') ?>"></div>
        <div class="form-group"><label>Reference</label><input class="form-control" name="reference_number"></div>
        <div class="form-group full"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" @click="txOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    </form></div>
  </div>
</div>
