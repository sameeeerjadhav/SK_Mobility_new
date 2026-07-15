<div x-data="{ empOpen: false, salOpen: false, editEmp: null }">
  <div class="toolbar">
    <div><h1 class="page-title">HR Management</h1><p class="page-sub">Employees & payroll</p></div>
    <div style="display:flex;gap:0.5rem;">
      <button class="btn btn-outline" type="button" @click="salOpen=true">+ Salary Payment</button>
      <button class="btn btn-primary" type="button" @click="empOpen=true">+ Employee</button>
    </div>
  </div>
  <div class="stat-grid">
    <div class="stat-card"><div class="stat-label">Total Employees</div><div class="stat-value"><?= (int)$stats['employees'] ?></div></div>
    <div class="stat-card"><div class="stat-label">Payroll This Month</div><div class="stat-value"><?= money($stats['payroll']) ?></div></div>
    <div class="stat-card"><div class="stat-label">Average Salary</div><div class="stat-value"><?= money($stats['avg']) ?></div></div>
  </div>
  <div class="tabs">
    <a class="tab <?= $tab==='employees'?'active':'' ?>" href="<?= url('hr?tab=employees') ?>">Employees</a>
    <a class="tab <?= $tab==='salaries'?'active':'' ?>" href="<?= url('hr?tab=salaries') ?>">Salary Records</a>
  </div>
  <?php if ($tab === 'salaries'): ?>
  <div class="card"><div class="table-wrap"><table class="data">
    <thead><tr><th>Employee</th><th>Month</th><th>Basic</th><th>Allowances</th><th>Deductions</th><th>Net</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($salaries as $s): ?>
      <tr>
        <td><?= e($s['first_name'].' '.$s['last_name']) ?> <span class="muted"><?= e($s['employee_code']) ?></span></td>
        <td><?= (int)$s['month'] ?>/<?= (int)$s['year'] ?></td>
        <td><?= money($s['basic_salary']) ?></td>
        <td><?= money($s['allowances']) ?></td>
        <td><?= money($s['deductions']) ?></td>
        <td><strong><?= money($s['net_salary']) ?></strong></td>
        <td><?= india_date($s['payment_date']) ?></td>
        <td>
          <form method="post" action="<?= url('hr/salaries/'.$s['id'].'/delete') ?>" onsubmit="return confirm('Delete?')">
            <?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$salaries): ?><tr><td colspan="8" class="muted">No salary records.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
  <?php else: ?>
  <div class="card"><div class="table-wrap"><table class="data">
    <thead><tr><th>Code</th><th>Name</th><th>Dept</th><th>Designation</th><th>Salary</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($employees as $e): ?>
      <tr>
        <td><?= e($e['employee_code']) ?></td>
        <td><?= e($e['first_name'].' '.$e['last_name']) ?></td>
        <td><?= e($e['department'] ?? '—') ?></td>
        <td><?= e($e['designation'] ?? '—') ?></td>
        <td><?= money($e['basic_salary']) ?></td>
        <td><?= status_chip($e['status']) ?></td>
        <td style="white-space:nowrap;">
          <button class="btn btn-sm btn-outline" type="button" @click='editEmp = <?= json_encode($e, JSON_HEX_APOS | JSON_HEX_TAG) ?>'>Edit</button>
          <form method="post" action="<?= url('hr/employees/'.$e['id'].'/delete') ?>" style="display:inline;" onsubmit="return confirm('Delete employee?')">
            <?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$employees): ?><tr><td colspan="7" class="muted">No employees.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
  <?php endif; ?>

  <div class="modal-backdrop" :class="{open:empOpen}" @click.self="empOpen=false">
    <div class="modal"><form method="post" action="<?= url('hr/employees') ?>">
      <?= csrf_field() ?>
      <div class="modal-header"><h3 class="modal-title">Add Employee</h3></div>
      <div class="modal-body form-grid">
        <div class="form-group"><label>First name</label><input class="form-control" name="first_name" required></div>
        <div class="form-group"><label>Last name</label><input class="form-control" name="last_name" required></div>
        <div class="form-group"><label>Email</label><input class="form-control" name="email"></div>
        <div class="form-group"><label>Phone</label><input class="form-control" name="phone"></div>
        <div class="form-group"><label>Department</label><input class="form-control" name="department"></div>
        <div class="form-group"><label>Designation</label><input class="form-control" name="designation"></div>
        <div class="form-group"><label>Joining date</label><input class="form-control" type="date" name="date_of_joining"></div>
        <div class="form-group"><label>Basic salary</label><input class="form-control" type="number" step="0.01" name="basic_salary" required></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" @click="empOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    </form></div>
  </div>

  <div class="modal-backdrop" :class="{open:!!editEmp}" @click.self="editEmp=null" x-show="editEmp" x-cloak>
    <div class="modal" x-show="editEmp">
      <form method="post" :action="'<?= url('hr/employees') ?>/'+editEmp?.id">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Edit Employee</h3></div>
        <div class="modal-body form-grid">
          <div class="form-group"><label>First name</label><input class="form-control" name="first_name" :value="editEmp?.first_name" required></div>
          <div class="form-group"><label>Last name</label><input class="form-control" name="last_name" :value="editEmp?.last_name" required></div>
          <div class="form-group"><label>Email</label><input class="form-control" name="email" :value="editEmp?.email"></div>
          <div class="form-group"><label>Phone</label><input class="form-control" name="phone" :value="editEmp?.phone"></div>
          <div class="form-group"><label>Department</label><input class="form-control" name="department" :value="editEmp?.department"></div>
          <div class="form-group"><label>Designation</label><input class="form-control" name="designation" :value="editEmp?.designation"></div>
          <div class="form-group"><label>Joining date</label><input class="form-control" type="date" name="date_of_joining" :value="editEmp?.date_of_joining"></div>
          <div class="form-group"><label>Basic salary</label><input class="form-control" type="number" step="0.01" name="basic_salary" :value="editEmp?.basic_salary" required></div>
          <div class="form-group"><label>Status</label>
            <select class="form-control" name="status" :value="editEmp?.status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="terminated">Terminated</option>
            </select>
          </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="editEmp=null">Cancel</button><button class="btn btn-primary" type="submit">Update</button></div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:salOpen}" @click.self="salOpen=false">
    <div class="modal"><form method="post" action="<?= url('hr/salaries') ?>">
      <?= csrf_field() ?>
      <div class="modal-header"><h3 class="modal-title">Record Salary</h3></div>
      <div class="modal-body form-grid">
        <div class="form-group full"><label>Employee</label>
          <select class="form-control" name="employee_id" required>
            <?php foreach ($employees as $e): ?><option value="<?= (int)$e['id'] ?>"><?= e($e['employee_code'].' — '.$e['first_name'].' '.$e['last_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Month</label><input class="form-control" type="number" min="1" max="12" name="month" value="<?= date('n') ?>" required></div>
        <div class="form-group"><label>Year</label><input class="form-control" type="number" name="year" value="<?= date('Y') ?>" required></div>
        <div class="form-group"><label>Basic</label><input class="form-control" type="number" step="0.01" name="basic_salary" required></div>
        <div class="form-group"><label>Allowances</label><input class="form-control" type="number" step="0.01" name="allowances" value="0"></div>
        <div class="form-group"><label>Deductions</label><input class="form-control" type="number" step="0.01" name="deductions" value="0"></div>
        <div class="form-group"><label>Payment date</label><input class="form-control" type="date" name="payment_date" value="<?= date('Y-m-d') ?>"></div>
        <div class="form-group"><label>Mode</label><select class="form-control" name="payment_mode"><option value="bank">Bank</option><option value="cash">Cash</option><option value="cheque">Cheque</option></select></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" @click="salOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    </form></div>
  </div>
</div>
