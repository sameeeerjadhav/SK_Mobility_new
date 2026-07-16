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
        <button class="btn btn-outline" type="button" onclick="document.getElementById('passwordModal').classList.add('open')">Set password</button>
        <form method="post" action="<?= url('dealers/' . $dealer['id'] . '/approve') ?>">
          <?= csrf_field() ?><input type="hidden" name="status" value="suspended">
          <button class="btn btn-danger" type="submit">Suspend</button>
        </form>
      </div>
    <?php else: ?>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
        <button class="btn btn-outline" type="button" onclick="document.getElementById('editDealerModal').classList.add('open')">Edit</button>
        <?php if (!empty($dealer['user_id']) || $dealer['status'] === 'approved'): ?>
          <button class="btn btn-outline" type="button" onclick="document.getElementById('passwordModal').classList.add('open')">Set password</button>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($linkedUser) || $dealer['status'] === 'approved'): ?>
<div class="card" style="margin-bottom:1rem;">
  <h3 class="card-title">Dealer login account</h3>
  <?php if (!empty($linkedUser)): ?>
    <div class="form-grid">
      <div><span class="muted">Login email</span><div style="font-weight:700;"><?= e($linkedUser['email']) ?></div></div>
      <div><span class="muted">Status</span><div><?= status_chip($linkedUser['is_active'] ? 'active' : 'inactive') ?></div></div>
      <div><span class="muted">Last login</span><div><?= india_datetime($linkedUser['last_login_at'] ?? null) ?></div></div>
      <div><span class="muted">Account created</span><div><?= india_datetime($linkedUser['created_at'] ?? null) ?></div></div>
    </div>
    <p class="muted" style="margin:0.85rem 0 0;font-size:0.85rem;">
      Passwords are encrypted and cannot be viewed. Use <strong>Set password</strong> to create a new one — it will be shown once after saving.
    </p>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.85rem;">
      <button class="btn btn-primary" type="button" onclick="document.getElementById('passwordModal').classList.add('open')">Set / reset password</button>
      <form method="post" action="<?= url('dealers/' . $dealer['id'] . '/toggle-login') ?>">
        <?= csrf_field() ?>
        <button class="btn btn-outline" type="submit"><?= $linkedUser['is_active'] ? 'Disable login' : 'Enable login' ?></button>
      </form>
    </div>
  <?php else: ?>
    <p class="muted">No login user yet. Set a password to create the dealer account.</p>
    <button class="btn btn-primary" type="button" onclick="document.getElementById('passwordModal').classList.add('open')">Create login &amp; set password</button>
  <?php endif; ?>
</div>
<?php endif; ?>
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
    <?php
      $primaryAddress = null;
      foreach ($addresses as $a) {
          if (!empty($a['is_primary'])) {
              $primaryAddress = $a;
              break;
          }
      }
      if (!$primaryAddress && $addresses) {
          $primaryAddress = $addresses[0];
      }
    ?>
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
        <div class="form-group full"><label>Address</label><textarea class="form-control" name="address_line1" rows="2" placeholder="Street, area, landmark"><?= e($primaryAddress['address_line1'] ?? '') ?></textarea></div>
        <div class="form-group full"><label>Address line 2</label><input class="form-control" name="address_line2" value="<?= e($primaryAddress['address_line2'] ?? '') ?>" placeholder="Optional"></div>
        <div class="form-group"><label>City</label><input class="form-control" name="city" value="<?= e($primaryAddress['city'] ?? '') ?>"></div>
        <div class="form-group"><label>State</label><input class="form-control" name="state" value="<?= e($primaryAddress['state'] ?? '') ?>"></div>
        <div class="form-group"><label>Pincode</label><input class="form-control" name="pincode" value="<?= e($primaryAddress['pincode'] ?? '') ?>"></div>
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

<div class="modal-backdrop" id="passwordModal" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="modal">
    <form method="post" action="<?= url('dealers/' . $dealer['id'] . '/password') ?>">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h3 class="modal-title">Set dealer password</h3>
        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('passwordModal').classList.remove('open')">Close</button>
      </div>
      <div class="modal-body">
        <p class="muted" style="margin-top:0;">Login email: <strong><?= e($dealer['email']) ?></strong></p>
        <div class="form-group">
          <label>New password</label>
          <input class="form-control" type="text" name="password" id="dealerNewPassword" placeholder="Leave blank to auto-generate" minlength="6" autocomplete="off">
        </div>
        <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.9rem;font-weight:600;">
          <input type="checkbox" name="generate" value="1" id="autoGenPass" onchange="document.getElementById('dealerNewPassword').disabled=this.checked; if(this.checked) document.getElementById('dealerNewPassword').value='';">
          Auto-generate a temporary password
        </label>
        <p class="muted" style="font-size:0.8rem;margin-bottom:0;">The new password will appear once in the green success banner after save. Copy it and share with the dealer.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('passwordModal').classList.remove('open')">Cancel</button>
        <button class="btn btn-primary" type="submit">Save password</button>
      </div>
    </form>
  </div>
</div>
