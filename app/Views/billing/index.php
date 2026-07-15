<div x-data="{ warrantyOpen: false }">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Billing</h1>
      <p class="page-sub">Invoices & warranty certificates</p>
    </div>
    <?php if (\App\Core\Auth::role() === 'super_admin'): ?>
      <button class="btn btn-primary" type="button" @click="warrantyOpen=true">+ Warranty Certificate</button>
    <?php endif; ?>
  </div>

  <div class="tabs">
    <a class="tab <?= $billType === '' ? 'active' : '' ?>" href="<?= url('billing') ?>">All (<?= (int)$counts['all'] ?>)</a>
    <a class="tab <?= $billType === 'vehicle' ? 'active' : '' ?>" href="<?= url('billing?bill_type=vehicle') ?>">Vehicle (<?= (int)$counts['vehicle'] ?>)</a>
    <a class="tab <?= $billType === 'warranty' ? 'active' : '' ?>" href="<?= url('billing?bill_type=warranty') ?>">Warranty (<?= (int)$counts['warranty'] ?>)</a>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Bill #</th><th>Type</th><th>Customer</th><th>Amount</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($bills as $b): ?>
          <tr>
            <td><?= e($b['bill_number']) ?></td>
            <td><span class="chip chip-<?= $b['bill_type'] === 'warranty' ? 'primary' : 'info' ?>"><?= e(ucfirst($b['bill_type'])) ?></span></td>
            <td><?= e($b['customer_name'] ?? '—') ?></td>
            <td><?= money($b['total_amount']) ?></td>
            <td><?= india_date($b['created_at']) ?></td>
            <td style="white-space:nowrap;">
              <a class="btn btn-sm btn-outline" href="<?= url('billing/' . $b['id']) ?>">View</a>
              <a class="btn btn-sm btn-outline" href="<?= url('billing/' . $b['id'] . '/preview') ?>" target="_blank">Preview</a>
              <a class="btn btn-sm btn-primary" href="<?= url('billing/' . $b['id'] . '/pdf') ?>" target="_blank">PDF</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$bills): ?><tr><td colspan="6" class="muted">No bills yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (\App\Core\Auth::role() === 'super_admin'): ?>
  <div class="modal-backdrop" :class="{ open: warrantyOpen }" @click.self="warrantyOpen=false">
    <div class="modal modal-lg">
      <form method="post" action="<?= url('billing/warranty') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Create Warranty Certificate</h3><button type="button" class="btn btn-sm btn-outline" @click="warrantyOpen=false">Close</button></div>
        <div class="modal-body form-grid">
          <div class="form-group"><label>Customer Name</label><input class="form-control" name="customer_name" required></div>
          <div class="form-group"><label>Phone</label><input class="form-control" name="customer_phone"></div>
          <div class="form-group full"><label>Address</label><textarea class="form-control" name="customer_address" rows="2"></textarea></div>
          <div class="form-group"><label>Vehicle Model</label><input class="form-control" name="vehicle_model" required></div>
          <div class="form-group"><label>Chassis No</label><input class="form-control" name="chassis_no"></div>
          <div class="form-group"><label>Motor No</label><input class="form-control" name="motor_no"></div>
          <div class="form-group"><label>Registration No</label><input class="form-control" name="registration_no"></div>
          <div class="form-group"><label>Warranty Start</label><input class="form-control" type="date" name="warranty_start" value="<?= date('Y-m-d') ?>"></div>
          <div class="form-group"><label>Period</label><input class="form-control" name="warranty_period" value="24 months"></div>
          <div class="form-group full"><label>Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="warrantyOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Create</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
