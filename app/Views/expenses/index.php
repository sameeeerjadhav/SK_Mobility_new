<?php
$filterQuery = http_build_query(array_filter([
    'category_id' => $categoryId,
    'record_type' => $recordType,
    'payment_mode' => $paymentMode,
    'search' => $search,
    'from' => $from,
    'to' => $to,
], static fn($v) => $v !== null && $v !== ''));
$paymentModes = ['cash', 'bank', 'upi', 'card', 'cheque'];
$expenseTotal = static function (array $e): float {
    $total = (float)($e['total_amount'] ?? 0);
    return $total > 0 ? $total : (float)$e['amount'];
};
$editExpenseJson = $editExpense
    ? json_encode($editExpense, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
    : 'null';
?>
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('expensePage', () => ({
    expOpen: false,
    catOpen: false,
    editCat: null,
    editExp: null,
    gstApplicable: false,
    items: [{ name: '', amount: '' }],
    init() {
      const seed = <?= $editExpenseJson ?>;
      if (seed) this.openEdit(seed);
    },
    gstCalc(amount, on) {
      const base = parseFloat(amount) || 0;
      if (!on || base <= 0) return { cgst: 0, sgst: 0, total: base };
      const cgst = Math.round(base * 0.09 * 100) / 100;
      const sgst = Math.round(base * 0.09 * 100) / 100;
      return { cgst, sgst, total: Math.round((base + cgst + sgst) * 100) / 100 };
    },
    itemsBase(list) {
      return (list || []).reduce((sum, it) => sum + (parseFloat(it.amount) || 0), 0);
    },
    itemsTotal(list, on) {
      return this.gstCalc(this.itemsBase(list), on);
    },
    fmt(n) {
      return '\u20B9' + (parseFloat(n) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    openAdd() {
      this.expOpen = true;
      this.gstApplicable = false;
      this.items = [{ name: '', amount: '' }];
    },
    addRecordItem() {
      this.items = [...this.items, { name: '', amount: '' }];
    },
    removeRecordItem(idx) {
      if (this.items.length <= 1) return;
      this.items = this.items.filter((_, i) => i !== idx);
    },
    addEditItem() {
      if (!this.editExp) return;
      this.editExp.items = [...this.editExp.items, { name: '', amount: '' }];
    },
    removeEditItem(idx) {
      if (!this.editExp || this.editExp.items.length <= 1) return;
      this.editExp.items = this.editExp.items.filter((_, i) => i !== idx);
    },
    openEdit(row) {
      const copy = JSON.parse(JSON.stringify(row));
      if (!copy.items || !copy.items.length) {
        copy.items = [{ name: copy.name || '', amount: copy.amount || '' }];
      } else {
        copy.items = copy.items.map(it => ({ name: it.name || '', amount: it.amount || '' }));
      }
      this.editExp = copy;
    },
  }));
});
</script>
<div x-data="expensePage()">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Assets &amp; Expenditure</h1>
      <p class="page-sub">Track capital assets and day-to-day office spending</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <button class="btn btn-outline" type="button" @click="catOpen=true">+ Category</button>
      <button class="btn btn-primary" type="button" @click="openAdd()" <?= !$categories ? 'disabled title="Add a category first"' : '' ?>>+ Record</button>
    </div>
  </div>

  <?php if (!$categories): ?>
    <div class="alert alert-warning" style="margin-bottom:1rem;">Add at least one category before recording expenses.</div>
  <?php endif; ?>

  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-label">Assets (this month)</div>
      <div class="stat-value"><?= money($stats['month_assets']) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Expenditure (this month)</div>
      <div class="stat-value"><?= money($stats['month_expenditure']) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total (this month)</div>
      <div class="stat-value"><?= money($stats['month_total']) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Filtered total</div>
      <div class="stat-value" style="font-size:1.1rem;"><?= money($filteredSum) ?></div>
      <div class="muted" style="font-size:0.75rem;margin-top:0.2rem;"><?= (int)$filteredCount ?> record<?= (int)$filteredCount === 1 ? '' : 's' ?></div>
    </div>
  </div>

  <form method="get" class="card" style="margin-bottom:1rem;">
    <h3 class="card-title" style="margin-bottom:0.65rem;">Filters</h3>
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;">
      <div class="form-group" style="margin:0;min-width:140px;">
        <label>Type</label>
        <select class="form-control" name="record_type">
          <option value="">All types</option>
          <option value="asset" <?= $recordType === 'asset' ? 'selected' : '' ?>>Assets</option>
          <option value="expenditure" <?= $recordType === 'expenditure' ? 'selected' : '' ?>>Expenditure</option>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:140px;">
        <label>Category</label>
        <select class="form-control" name="category_id">
          <option value="">All categories</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (string)$categoryId === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:130px;">
        <label>Payment</label>
        <select class="form-control" name="payment_mode">
          <option value="">All modes</option>
          <?php foreach ($paymentModes as $m): ?>
            <option value="<?= $m ?>" <?= $paymentMode === $m ? 'selected' : '' ?>><?= ucfirst($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:140px;">
        <label>From</label>
        <input class="form-control" type="date" name="from" value="<?= e($from) ?>">
      </div>
      <div class="form-group" style="margin:0;min-width:140px;">
        <label>To</label>
        <input class="form-control" type="date" name="to" value="<?= e($to) ?>">
      </div>
      <div class="form-group" style="margin:0;min-width:180px;flex:1;">
        <label>Search</label>
        <input class="form-control" type="search" name="search" value="<?= e($search) ?>" placeholder="Name, notes or category">
      </div>
      <button class="btn btn-primary" type="submit">Apply</button>
      <?php if ($filterQuery !== ''): ?>
        <a class="btn btn-outline" href="<?= url('expenses') ?>">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="card" style="margin-bottom:1rem;">
    <h3 class="card-title">Categories</h3>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Name</th><th>Description</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($allCategories as $c): ?>
          <tr>
            <td><strong><?= e($c['name']) ?></strong></td>
            <td class="muted"><?= e($c['description'] ?: '—') ?></td>
            <td><?= !empty($c['is_active']) ? status_chip('active') : status_chip('inactive') ?></td>
            <td style="white-space:nowrap;">
              <?php if (!empty($c['is_active'])): ?>
                <button type="button" class="btn btn-sm btn-outline" @click='editCat = <?= json_encode($c, JSON_HEX_APOS | JSON_HEX_TAG) ?>'>Edit</button>
                <form method="post" action="<?= url('expenses/categories/' . $c['id'] . '/delete') ?>" style="display:inline;" onsubmit="return confirm('Deactivate this category?')">
                  <?= csrf_field() ?>
                  <button class="btn btn-sm btn-danger" type="submit">Deactivate</button>
                </form>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$allCategories): ?><tr><td colspan="4" class="muted">No categories yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Date</th>
            <th>Name</th>
            <th>Type</th>
            <th>Category</th>
            <th>Base</th>
            <th>GST</th>
            <th>Total</th>
            <th>Mode</th>
            <th>Receipt</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($expenses as $e): ?>
          <tr>
            <td><?= india_date($e['expense_date']) ?></td>
            <td>
              <strong><a href="<?= url('expenses/' . $e['id']) ?>"><?= e($e['name'] ?: '—') ?></a></strong>
              <?php if (!empty($e['items']) && count($e['items']) > 1): ?>
                <?php foreach ($e['items'] as $item): ?>
                  <div class="muted" style="font-size:0.78rem;"><?= e($item['name']) ?> · <?= money($item['amount']) ?></div>
                <?php endforeach; ?>
              <?php elseif (!empty($e['description'])): ?>
                <div class="muted" style="font-size:0.78rem;"><?= e($e['description']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if (($e['record_type'] ?? 'expenditure') === 'asset'): ?>
                <span class="chip chip-info">Asset</span>
              <?php else: ?>
                <span class="chip chip-warning">Expenditure</span>
              <?php endif; ?>
            </td>
            <td><span class="chip chip-primary"><?= e($e['category_name']) ?></span></td>
            <td><?= money($e['amount']) ?></td>
            <td>
              <?php if (!empty($e['gst_applicable'])): ?>
                <span class="muted" style="font-size:0.78rem;">CGST <?= money($e['cgst_amount'] ?? 0) ?><br>SGST <?= money($e['sgst_amount'] ?? 0) ?></span>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td><strong><?= money($expenseTotal($e)) ?></strong></td>
            <td><?= e(ucfirst($e['payment_mode'])) ?></td>
            <td>
              <?php if (!empty($e['receipt_url'])): ?>
                <a href="<?= asset($e['receipt_url']) ?>" target="_blank" rel="noopener">File</a>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;">
              <a class="btn btn-sm btn-outline" href="<?= url('expenses/' . $e['id']) ?>">View</a>
              <button class="btn btn-sm btn-outline" type="button" @click='openEdit(<?= json_encode($e, JSON_HEX_APOS | JSON_HEX_TAG) ?>)'>Edit</button>
              <form method="post" action="<?= url('expenses/' . $e['id'] . '/delete') ?>" style="display:inline;" onsubmit="return confirm('Delete this record?')">
                <?= csrf_field() ?>
                <?php if ($filterQuery !== ''): ?><input type="hidden" name="return_filters" value="<?= e($filterQuery) ?>"><?php endif; ?>
                <button class="btn btn-sm btn-danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$expenses): ?>
          <tr><td colspan="10" class="muted">No records match your filters.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:expOpen}" @click.self="expOpen=false">
    <div class="modal" style="max-width:640px;">
      <form method="post" action="<?= url('expenses') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($filterQuery !== ''): ?><input type="hidden" name="return_filters" value="<?= e($filterQuery) ?>"><?php endif; ?>
        <div class="modal-header">
          <h3 class="modal-title">Add record</h3>
          <p class="muted" style="margin:0.35rem 0 0;font-size:0.82rem;">One receipt = one record. Use <strong>+ Add item</strong> for multiple products on the same bill.</p>
        </div>
        <div class="modal-body form-grid">
          <div class="form-group">
            <label>Type *</label>
            <select class="form-control" name="record_type" required>
              <option value="expenditure">Expenditure</option>
              <option value="asset">Asset</option>
            </select>
          </div>
          <div class="form-group">
            <label>Category *</label>
            <select class="form-control" name="category_id" required>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group full" style="padding:0.75rem;border:1px solid var(--border);border-radius:10px;background:#fafafa;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;margin-bottom:0.55rem;">
              <div>
                <label style="margin:0;font-weight:700;">Items in this record *</label>
                <p class="muted" style="margin:0.2rem 0 0;font-size:0.78rem;">e.g. Laptop and Printer on one receipt</p>
              </div>
              <button type="button" class="btn btn-sm btn-primary" @click="addRecordItem()">+ Add item</button>
            </div>
            <template x-for="(item, idx) in items" :key="'add-' + idx">
              <div style="display:grid;grid-template-columns:1fr 140px auto;gap:0.45rem;margin-bottom:0.45rem;align-items:end;">
                <div class="form-group" style="margin:0;">
                  <label x-text="'Item ' + (idx + 1)" style="font-size:0.75rem;"></label>
                  <input class="form-control" name="item_name[]" x-model="item.name" required placeholder="e.g. Laptop">
                </div>
                <div class="form-group" style="margin:0;">
                  <label style="font-size:0.75rem;">Base amount</label>
                  <input class="form-control" type="number" step="0.01" min="0.01" name="item_amount[]" x-model="item.amount" required>
                </div>
                <button type="button" class="btn btn-sm btn-danger" style="margin-bottom:0;" @click="removeRecordItem(idx)" :disabled="items.length <= 1" title="Remove item">×</button>
              </div>
            </template>
          </div>

          <div class="form-group full">
            <label style="display:flex;align-items:center;gap:0.5rem;font-weight:600;cursor:pointer;">
              <input type="checkbox" name="gst_applicable" value="1" x-model="gstApplicable">
              Include GST (9% CGST + 9% SGST) on total base
            </label>
          </div>

          <div class="form-group full" style="padding:0.65rem 0.75rem;border:1px solid var(--border);border-radius:10px;background:#f8fffd;">
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.5rem;font-size:0.88rem;">
              <div><span class="muted">Base total</span><br><strong x-text="fmt(itemsBase(items))"></strong></div>
              <div x-show="gstApplicable"><span class="muted">CGST 9%</span><br><strong x-text="fmt(itemsTotal(items, gstApplicable).cgst)"></strong></div>
              <div x-show="gstApplicable"><span class="muted">SGST 9%</span><br><strong x-text="fmt(itemsTotal(items, gstApplicable).sgst)"></strong></div>
              <div><span class="muted">Total payable</span><br><strong style="color:#0f766e;" x-text="fmt(itemsTotal(items, gstApplicable).total)"></strong></div>
            </div>
          </div>

          <div class="form-group"><label>Date</label><input class="form-control" type="date" name="expense_date" value="<?= date('Y-m-d') ?>"></div>
          <div class="form-group">
            <label>Payment mode</label>
            <select class="form-control" name="payment_mode">
              <?php foreach ($paymentModes as $m): ?>
                <option value="<?= $m ?>"><?= ucfirst($m) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group full"><label>Notes</label><textarea class="form-control" name="description" rows="2" placeholder="Optional extra details"></textarea></div>
          <div class="form-group full"><label>Receipt</label><input class="form-control" type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="expOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:!!editExp}" @click.self="editExp=null" x-show="editExp" x-cloak>
    <div class="modal" style="max-width:640px;" x-show="editExp">
      <form method="post" :action="'<?= url('expenses') ?>/' + editExp?.id" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($filterQuery !== ''): ?><input type="hidden" name="return_filters" value="<?= e($filterQuery) ?>"><?php endif; ?>
        <div class="modal-header">
          <h3 class="modal-title">Edit record</h3>
          <p class="muted" style="margin:0.35rem 0 0;font-size:0.82rem;">Update items in this single record. Use <strong>+ Add item</strong> for more lines on the same receipt.</p>
        </div>
        <div class="modal-body form-grid" x-show="editExp">
          <div class="form-group">
            <label>Type *</label>
            <select class="form-control" name="record_type" x-model="editExp.record_type">
              <option value="expenditure">Expenditure</option>
              <option value="asset">Asset</option>
            </select>
          </div>
          <div class="form-group">
            <label>Category *</label>
            <select class="form-control" name="category_id" x-model="editExp.category_id">
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group full" x-show="editExp?.items" style="padding:0.75rem;border:1px solid var(--border);border-radius:10px;background:#fafafa;">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;margin-bottom:0.55rem;">
              <label style="margin:0;font-weight:700;">Items in this record *</label>
              <button type="button" class="btn btn-sm btn-primary" @click="addEditItem()">+ Add item</button>
            </div>
            <template x-for="(item, idx) in editExp.items" :key="'edit-' + idx">
              <div style="display:grid;grid-template-columns:1fr 140px auto;gap:0.45rem;margin-bottom:0.45rem;align-items:end;">
                <div class="form-group" style="margin:0;">
                  <label x-text="'Item ' + (idx + 1)" style="font-size:0.75rem;"></label>
                  <input class="form-control" name="item_name[]" x-model="item.name" required placeholder="e.g. Laptop">
                </div>
                <div class="form-group" style="margin:0;">
                  <label style="font-size:0.75rem;">Base amount</label>
                  <input class="form-control" type="number" step="0.01" min="0.01" name="item_amount[]" x-model="item.amount" required>
                </div>
                <button type="button" class="btn btn-sm btn-danger" style="margin-bottom:0;" @click="removeEditItem(idx)" :disabled="editExp.items.length <= 1" title="Remove item">×</button>
              </div>
            </template>
          </div>

          <div class="form-group full">
            <label style="display:flex;align-items:center;gap:0.5rem;font-weight:600;cursor:pointer;">
              <input type="checkbox" name="gst_applicable" value="1" :checked="editExp.gst_applicable == 1" @change="editExp.gst_applicable = $event.target.checked ? 1 : 0">
              Include GST (9% CGST + 9% SGST) on total base
            </label>
          </div>

          <div class="form-group full" style="padding:0.65rem 0.75rem;border:1px solid var(--border);border-radius:10px;background:#f8fffd;">
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.5rem;font-size:0.88rem;">
              <div><span class="muted">Base total</span><br><strong x-text="fmt(itemsBase(editExp.items))"></strong></div>
              <div x-show="editExp.gst_applicable == 1"><span class="muted">CGST 9%</span><br><strong x-text="fmt(itemsTotal(editExp.items, editExp.gst_applicable == 1).cgst)"></strong></div>
              <div x-show="editExp.gst_applicable == 1"><span class="muted">SGST 9%</span><br><strong x-text="fmt(itemsTotal(editExp.items, editExp.gst_applicable == 1).sgst)"></strong></div>
              <div><span class="muted">Total payable</span><br><strong style="color:#0f766e;" x-text="fmt(itemsTotal(editExp.items, editExp.gst_applicable == 1).total)"></strong></div>
            </div>
          </div>

          <div class="form-group"><label>Date</label><input class="form-control" type="date" name="expense_date" x-model="editExp.expense_date"></div>
          <div class="form-group">
            <label>Payment mode</label>
            <select class="form-control" name="payment_mode" x-model="editExp.payment_mode">
              <?php foreach ($paymentModes as $m): ?>
                <option value="<?= $m ?>"><?= ucfirst($m) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group full"><label>Notes</label><textarea class="form-control" name="description" x-model="editExp.description" rows="2"></textarea></div>
          <div class="form-group full"><label>Replace receipt</label><input class="form-control" type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="editExp=null">Cancel</button>
          <button class="btn btn-primary" type="submit">Update</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:catOpen}" @click.self="catOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('expenses/categories') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Add category</h3></div>
        <div class="modal-body">
          <div class="form-group"><label>Name *</label><input class="form-control" name="name" required></div>
          <div class="form-group"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="catOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:!!editCat}" @click.self="editCat=null" x-show="editCat" x-cloak>
    <div class="modal" x-show="editCat">
      <form method="post" :action="'<?= url('expenses/categories') ?>/' + editCat?.id">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Edit category</h3></div>
        <div class="modal-body">
          <div class="form-group"><label>Name *</label><input class="form-control" name="name" x-model="editCat.name" required></div>
          <div class="form-group"><label>Description</label><textarea class="form-control" name="description" x-model="editCat.description" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="editCat=null">Cancel</button>
          <button class="btn btn-primary" type="submit">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>
