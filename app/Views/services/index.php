<div x-data="{ createOpen: false, techOpen: false }">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Services</h1>
      <p class="page-sub">Service requests & job cards</p>
    </div>
    <?php if ($canManage): ?>
      <div style="display:flex;gap:0.5rem;">
        <button class="btn btn-outline" type="button" @click="techOpen=true">+ Technician</button>
        <button class="btn btn-primary" type="button" @click="createOpen=true">+ Service Request</button>
      </div>
    <?php endif; ?>
  </div>

  <form method="get" class="card" style="margin-bottom:1rem;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;">
    <div class="form-group" style="margin:0;min-width:160px;"><label>Status</label>
      <select class="form-control" name="status">
        <option value="">All</option>
        <?php foreach (['pending','in_progress','completed','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;"><label>Search</label><input class="form-control" name="search" value="<?= e($search) ?>"></div>
    <button class="btn btn-outline" type="submit">Filter</button>
  </form>

  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>SR #</th><th>Customer</th><th>Vehicle</th><th>Issue</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($requests as $r): ?>
          <tr>
            <td><?= e($r['request_number']) ?></td>
            <td><?= e($r['customer_name']) ?><div class="muted" style="font-size:0.75rem;"><?= e($r['customer_phone']) ?></div></td>
            <td><?= e($r['vehicle_model'] ?? '—') ?><div class="muted" style="font-size:0.75rem;"><?= e($r['vehicle_vin'] ?? '') ?></div></td>
            <td><?= e(mb_strimwidth($r['issue_description'], 0, 60, '…')) ?></td>
            <td><?= status_chip($r['status']) ?></td>
            <td><?= india_date($r['created_at']) ?></td>
            <td><a class="btn btn-sm btn-outline" href="<?= url('services/' . $r['id']) ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$requests): ?><tr><td colspan="7" class="muted">No service requests.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($canManage): ?>
  <div class="modal-backdrop" :class="{ open: createOpen }" @click.self="createOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('services') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Create Service Request</h3><button type="button" class="btn btn-sm btn-outline" @click="createOpen=false">Close</button></div>
        <div class="modal-body form-grid">
          <div class="form-group"><label>Customer name</label><input class="form-control" name="customer_name" required></div>
          <div class="form-group"><label>Phone</label><input class="form-control" name="customer_phone" required></div>
          <div class="form-group"><label>Vehicle model</label><input class="form-control" name="vehicle_model"></div>
          <div class="form-group"><label>VIN</label><input class="form-control" name="vehicle_vin"></div>
          <div class="form-group full"><label>Issue description</label><textarea class="form-control" name="issue_description" rows="3" required></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="createOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Create</button></div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{ open: techOpen }" @click.self="techOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('services/technicians') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Add Technician</h3></div>
        <div class="modal-body form-grid">
          <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
          <div class="form-group"><label>Phone</label><input class="form-control" name="phone"></div>
          <div class="form-group"><label>Email</label><input class="form-control" name="email"></div>
          <div class="form-group"><label>Specialization</label><input class="form-control" name="specialization"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="techOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
