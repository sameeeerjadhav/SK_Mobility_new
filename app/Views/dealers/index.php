<div x-data="{ createOpen: false }">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Dealers</h1>
      <p class="page-sub">Onboard and manage EV dealers</p>
    </div>
    <button class="btn btn-primary" type="button" @click="createOpen = true">+ Create Dealer</button>
  </div>

  <form class="card" method="get" style="margin-bottom:1rem;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;">
    <div class="form-group" style="margin:0;min-width:180px;">
      <label>Status</label>
      <select class="form-control" name="status">
        <option value="">All</option>
        <?php foreach (['pending','approved','rejected','suspended'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:200px;">
      <label>Search</label>
      <input class="form-control" name="search" value="<?= e($search) ?>" placeholder="Name, code, email, phone">
    </div>
    <button class="btn btn-outline" type="submit">Filter</button>
  </form>

  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr><th>Business</th><th>Code</th><th>Contact</th><th>Status</th><th>Sell Orders</th><th>Revenue</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($dealers as $d): ?>
          <tr>
            <td><strong><?= e($d['business_name']) ?></strong></td>
            <td><?= e($d['dealer_code'] ?? '—') ?></td>
            <td><?= e($d['contact_person']) ?><div class="muted" style="font-size:0.75rem;"><?= e($d['phone']) ?></div></td>
            <td><?= status_chip($d['status']) ?></td>
            <td><?= (int)$d['total_orders'] ?></td>
            <td><?= money($d['total_revenue']) ?></td>
            <td style="white-space:nowrap;">
              <a class="btn btn-sm btn-outline" href="<?= url('dealers/' . $d['id']) ?>">View</a>
              <a class="btn btn-sm btn-outline" href="<?= url('dealers/' . $d['id']) ?>#edit">Edit</a>
              <?php if ($d['status'] === 'pending'): ?>
                <form method="post" action="<?= url('dealers/' . $d['id'] . '/approve') ?>" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="status" value="approved">
                  <button class="btn btn-sm btn-primary" type="submit">Approve</button>
                </form>
                <form method="post" action="<?= url('dealers/' . $d['id'] . '/approve') ?>" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="status" value="rejected">
                  <button class="btn btn-sm btn-danger" type="submit">Reject</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$dealers): ?><tr><td colspan="7" class="muted">No dealers found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalPages > 1): ?>
      <div style="margin-top:1rem;display:flex;gap:0.5rem;">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>"
             href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="modal-backdrop" :class="{ open: createOpen }" @click.self="createOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('dealers') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Create Dealer</h3><button type="button" class="btn btn-outline btn-sm" @click="createOpen=false">Close</button></div>
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group full"><label>Business Name</label><input class="form-control" name="business_name" required></div>
            <div class="form-group"><label>Contact Person</label><input class="form-control" name="contact_person" required></div>
            <div class="form-group"><label>Phone</label><input class="form-control contact-input" name="phone" type="tel" maxlength="11" inputmode="numeric" placeholder="98765 43210" required></div>
            <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" required></div>
            <div class="form-group"><label>GST</label><input class="form-control" name="gst_number"></div>
            <div class="form-group"><label>PAN</label><input class="form-control" name="pan_number"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="createOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
