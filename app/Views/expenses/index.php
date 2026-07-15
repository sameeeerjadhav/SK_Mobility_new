<div x-data="{ expOpen: false, catOpen: false }">
  <div class="toolbar">
    <div><h1 class="page-title">Office Expenses</h1><p class="page-sub">Track office spending</p></div>
    <div style="display:flex;gap:0.5rem;">
      <button class="btn btn-outline" type="button" @click="catOpen=true">+ Category</button>
      <button class="btn btn-primary" type="button" @click="expOpen=true">+ Expense</button>
    </div>
  </div>
  <div class="stat-grid">
    <div class="stat-card"><div class="stat-label">This Month</div><div class="stat-value"><?= money($stats['month']) ?></div></div>
    <div class="stat-card"><div class="stat-label">This Year</div><div class="stat-value"><?= money($stats['year']) ?></div></div>
    <?php foreach ($byCat as $c): ?>
      <div class="stat-card"><div class="stat-label"><?= e($c['name']) ?></div><div class="stat-value" style="font-size:1.1rem;"><?= money($c['total']) ?></div></div>
    <?php endforeach; ?>
  </div>
  <form method="get" class="card" style="margin-bottom:1rem;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;">
    <div class="form-group" style="margin:0;"><label>Category</label>
      <select class="form-control" name="category_id"><option value="">All</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (string)$categoryId===(string)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;"><label>From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="form-group" style="margin:0;"><label>To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
    <button class="btn btn-outline" type="submit">Filter</button>
  </form>
  <div class="card"><div class="table-wrap"><table class="data">
    <thead><tr><th>Date</th><th>Category</th><th>Amount</th><th>Mode</th><th>Description</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($expenses as $e): ?>
      <tr>
        <td><?= india_date($e['expense_date']) ?></td>
        <td><span class="chip chip-primary"><?= e($e['category_name']) ?></span></td>
        <td><?= money($e['amount']) ?></td>
        <td><?= e(ucfirst($e['payment_mode'])) ?></td>
        <td><?= e($e['description'] ?? '—') ?></td>
        <td>
          <form method="post" action="<?= url('expenses/'.$e['id'].'/delete') ?>" onsubmit="return confirm('Delete?')">
            <?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$expenses): ?><tr><td colspan="6" class="muted">No expenses.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>

  <div class="modal-backdrop" :class="{open:expOpen}" @click.self="expOpen=false">
    <div class="modal"><form method="post" action="<?= url('expenses') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="modal-header"><h3 class="modal-title">Record Expense</h3></div>
      <div class="modal-body form-grid">
        <div class="form-group"><label>Category</label>
          <select class="form-control" name="category_id" required><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group"><label>Amount</label><input class="form-control" type="number" step="0.01" name="amount" required></div>
        <div class="form-group"><label>Date</label><input class="form-control" type="date" name="expense_date" value="<?= date('Y-m-d') ?>"></div>
        <div class="form-group"><label>Payment mode</label>
          <select class="form-control" name="payment_mode"><?php foreach (['cash','bank','upi','card','cheque'] as $m): ?><option value="<?= $m ?>"><?= ucfirst($m) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-group full"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
        <div class="form-group full"><label>Receipt</label><input class="form-control" type="file" name="receipt"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" @click="expOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    </form></div>
  </div>

  <div class="modal-backdrop" :class="{open:catOpen}" @click.self="catOpen=false">
    <div class="modal"><form method="post" action="<?= url('expenses/categories') ?>">
      <?= csrf_field() ?>
      <div class="modal-header"><h3 class="modal-title">Add Category</h3></div>
      <div class="modal-body">
        <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
        <div class="form-group"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline" @click="catOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
    </form></div>
  </div>
</div>
