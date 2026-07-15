<div style="margin-bottom:1rem;"><a href="<?= url('dealers') ?>">&larr; Back to dealers</a></div>

<div class="card" style="margin-bottom:1rem;">
  <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;align-items:start;">
    <div>
      <h1 class="page-title" style="margin:0;"><?= e($dealer['business_name']) ?></h1>
      <p class="page-sub" style="margin:0.35rem 0 0;">
        <?= status_chip($dealer['status']) ?>
        &nbsp; Code: <strong><?= e($dealer['dealer_code'] ?? 'Pending') ?></strong>
      </p>
      <p class="muted" style="margin:0.5rem 0 0;">
        <?= e($dealer['contact_person']) ?> · <?= e($dealer['phone']) ?> · <?= e($dealer['email']) ?>
      </p>
    </div>
    <?php if ($dealer['status'] === 'pending'): ?>
      <div style="display:flex;gap:0.5rem;">
        <form method="post" action="<?= url('dealers/' . $dealer['id'] . '/approve') ?>">
          <?= csrf_field() ?><input type="hidden" name="status" value="approved">
          <button class="btn btn-primary" type="submit">Approve</button>
        </form>
        <form method="post" action="<?= url('dealers/' . $dealer['id'] . '/approve') ?>">
          <?= csrf_field() ?><input type="hidden" name="status" value="rejected">
          <button class="btn btn-danger" type="submit">Reject</button>
        </form>
      </div>
    <?php elseif ($dealer['status'] === 'approved'): ?>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <button class="btn btn-outline" type="button" onclick="document.getElementById('editDealerModal').classList.add('open')">Edit</button>
        <form method="post" action="<?= url('dealers/' . $dealer['id'] . '/approve') ?>">
          <?= csrf_field() ?><input type="hidden" name="status" value="suspended">
          <button class="btn btn-danger" type="submit">Suspend</button>
        </form>
      </div>
    <?php else: ?>
      <button class="btn btn-outline" type="button" onclick="document.getElementById('editDealerModal').classList.add('open')">Edit</button>
    <?php endif; ?>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-label">Total Orders</div><div class="stat-value"><?= (int)$dealer['total_orders'] ?></div></div>
  <div class="stat-card"><div class="stat-label">Total Revenue</div><div class="stat-value"><?= money($dealer['total_revenue']) ?></div></div>
  <div class="stat-card"><div class="stat-label">Total Leads</div><div class="stat-value"><?= (int)$totalLeads ?></div></div>
  <div class="stat-card"><div class="stat-label">Performance</div><div class="stat-value"><?= (int)$dealer['performance_score'] ?></div></div>
</div>

<div class="grid-2-eq" x-data="{ tab: 'profile' }">
  <div class="card">
    <div class="tabs">
      <button type="button" class="tab" :class="{ active: tab==='profile' }" @click="tab='profile'">Profile</button>
      <button type="button" class="tab" :class="{ active: tab==='orders' }" @click="tab='orders'">Recent Orders</button>
      <button type="button" class="tab" :class="{ active: tab==='status' }" @click="tab='status'">Status Breakdown</button>
    </div>

    <div x-show="tab==='profile'">
      <p><strong>GST:</strong> <?= e($dealer['gst_number'] ?: '—') ?></p>
      <p><strong>PAN:</strong> <?= e($dealer['pan_number'] ?: '—') ?></p>
      <h4>Addresses</h4>
      <?php if (!$addresses): ?><p class="muted">No addresses.</p><?php endif; ?>
      <?php foreach ($addresses as $a): ?>
        <p><?= e($a['address_line1']) ?><?= $a['address_line2'] ? ', ' . e($a['address_line2']) : '' ?><br>
        <?= e($a['city']) ?>, <?= e($a['state']) ?> — <?= e($a['pincode']) ?></p>
      <?php endforeach; ?>
    </div>

    <div x-show="tab==='orders'" style="display:none;" x-cloak>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Order</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($recentOrders as $o): ?>
            <tr>
              <td><a href="<?= url('orders/' . $o['id']) ?>"><?= e($o['order_number']) ?></a></td>
              <td><?= money($o['total_amount']) ?></td>
              <td><?= status_chip($o['status']) ?></td>
              <td><?= india_date($o['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$recentOrders): ?><tr><td colspan="4" class="muted">No orders.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div x-show="tab==='status'" style="display:none;" x-cloak>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Status</th><th>Count</th></tr></thead>
          <tbody>
          <?php foreach ($statusBreakdown as $b): ?>
            <tr><td><?= status_chip($b['status']) ?></td><td><?= (int)$b['cnt'] ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$statusBreakdown): ?><tr><td colspan="2" class="muted">No data.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <h3 class="card-title">Documents</h3>
    <form method="post" action="<?= url('dealers/' . $dealer['id'] . '/documents') ?>" enctype="multipart/form-data" style="margin-bottom:1rem;">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>Type</label>
        <select class="form-control" name="document_type">
          <?php foreach (['gst','pan','aadhar','bank','license','other'] as $t): ?>
            <option value="<?= $t ?>"><?= strtoupper($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>File</label>
        <input class="form-control" type="file" name="document" required>
      </div>
      <button class="btn btn-primary" type="submit">Upload</button>
    </form>
    <ul style="padding-left:1.1rem;margin:0;">
      <?php foreach ($documents as $doc): ?>
        <li style="margin-bottom:0.4rem;">
          <?= e(strtoupper($doc['document_type'])) ?> —
          <a href="<?= asset($doc['file_url']) ?>" target="_blank">View file</a>
          <span class="muted">(<?= india_date($doc['created_at']) ?>)</span>
        </li>
      <?php endforeach; ?>
      <?php if (!$documents): ?><li class="muted">No documents uploaded.</li><?php endif; ?>
    </ul>
  </div>
</div>

<div class="modal-backdrop" id="editDealerModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal">
    <form method="post" action="<?= url('dealers/' . $dealer['id']) ?>">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h3 class="modal-title">Edit Dealer</h3>
        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('editDealerModal').classList.remove('open')">Close</button>
      </div>
      <div class="modal-body form-grid">
        <div class="form-group full"><label>Business name</label><input class="form-control" name="business_name" value="<?= e($dealer['business_name']) ?>" required></div>
        <div class="form-group"><label>Contact person</label><input class="form-control" name="contact_person" value="<?= e($dealer['contact_person']) ?>" required></div>
        <div class="form-group"><label>Phone</label><input class="form-control" name="phone" value="<?= e($dealer['phone']) ?>" required></div>
        <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" value="<?= e($dealer['email']) ?>" required></div>
        <div class="form-group"><label>GST</label><input class="form-control" name="gst_number" value="<?= e($dealer['gst_number'] ?? '') ?>"></div>
        <div class="form-group"><label>PAN</label><input class="form-control" name="pan_number" value="<?= e($dealer['pan_number'] ?? '') ?>"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('editDealerModal').classList.remove('open')">Cancel</button>
        <button class="btn btn-primary" type="submit">Save changes</button>
      </div>
    </form>
  </div>
</div>
<script>
  if (location.hash === '#edit') {
    document.getElementById('editDealerModal')?.classList.add('open');
  }
</script>
