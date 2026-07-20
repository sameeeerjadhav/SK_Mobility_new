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
?>
<div x-data="{ expOpen: false, catOpen: false, editExp: null }">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Assets &amp; Expenditure</h1>
      <p class="page-sub">Track capital assets and day-to-day office spending</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
      <button class="btn btn-outline" type="button" @click="catOpen=true">+ Category</button>
      <button class="btn btn-primary" type="button" @click="expOpen=true">+ Record</button>
    </div>
  </div>

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
      <div class="stat-label">Assets (this year)</div>
      <div class="stat-value" style="font-size:1.1rem;"><?= money($stats['year_assets']) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Expenditure (this year)</div>
      <div class="stat-value" style="font-size:1.1rem;"><?= money($stats['year_expenditure']) ?></div>
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
        <input class="form-control" type="search" name="search" value="<?= e($search) ?>" placeholder="Description or category">
      </div>
      <button class="btn btn-primary" type="submit">Apply</button>
      <?php if ($filterQuery !== ''): ?>
        <a class="btn btn-outline" href="<?= url('expenses') ?>">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Category</th>
            <th>Amount</th>
            <th>Mode</th>
            <th>Description</th>
            <th>Receipt</th>
            <th>Recorded by</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($expenses as $e): ?>
          <tr>
            <td><?= india_date($e['expense_date']) ?></td>
            <td>
              <?php if (($e['record_type'] ?? 'expenditure') === 'asset'): ?>
                <span class="chip chip-info">Asset</span>
              <?php else: ?>
                <span class="chip chip-warning">Expenditure</span>
              <?php endif; ?>
            </td>
            <td><span class="chip chip-primary"><?= e($e['category_name']) ?></span></td>
            <td><strong><?= money($e['amount']) ?></strong></td>
            <td><?= e(ucfirst($e['payment_mode'])) ?></td>
            <td><?= e($e['description'] ?? '—') ?></td>
            <td>
              <?php if (!empty($e['receipt_url'])): ?>
                <a href="<?= asset($e['receipt_url']) ?>" target="_blank" rel="noopener">View</a>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
            <td class="muted" style="font-size:0.82rem;"><?= e(trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''))) ?></td>
            <td style="white-space:nowrap;">
              <button class="btn btn-sm btn-outline" type="button" @click='editExp = <?= json_encode($e, JSON_HEX_APOS | JSON_HEX_TAG) ?>'>Edit</button>
              <form method="post" action="<?= url('expenses/' . $e['id'] . '/delete') ?>" style="display:inline;" onsubmit="return confirm('Delete this record?')">
                <?= csrf_field() ?>
                <?php if ($filterQuery !== ''): ?><input type="hidden" name="return_filters" value="<?= e($filterQuery) ?>"><?php endif; ?>
                <button class="btn btn-sm btn-danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$expenses): ?>
          <tr><td colspan="9" class="muted">No records match your filters.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal-backdrop" :class="{open:expOpen}" @click.self="expOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('expenses') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($filterQuery !== ''): ?><input type="hidden" name="return_filters" value="<?= e($filterQuery) ?>"><?php endif; ?>
        <div class="modal-header"><h3 class="modal-title">Add record</h3></div>
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
          <div class="form-group"><label>Amount *</label><input class="form-control" type="number" step="0.01" name="amount" required></div>
          <div class="form-group"><label>Date</label><input class="form-control" type="date" name="expense_date" value="<?= date('Y-m-d') ?>"></div>
          <div class="form-group">
            <label>Payment mode</label>
            <select class="form-control" name="payment_mode">
              <?php foreach ($paymentModes as $m): ?>
                <option value="<?= $m ?>"><?= ucfirst($m) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group full"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
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
    <div class="modal" x-show="editExp">
      <form method="post" :action="'<?= url('expenses') ?>/' + editExp?.id" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($filterQuery !== ''): ?><input type="hidden" name="return_filters" value="<?= e($filterQuery) ?>"><?php endif; ?>
        <div class="modal-header"><h3 class="modal-title">Edit record</h3></div>
        <div class="modal-body form-grid">
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
          <div class="form-group"><label>Amount *</label><input class="form-control" type="number" step="0.01" name="amount" x-model="editExp.amount" required></div>
          <div class="form-group"><label>Date</label><input class="form-control" type="date" name="expense_date" x-model="editExp.expense_date"></div>
          <div class="form-group">
            <label>Payment mode</label>
            <select class="form-control" name="payment_mode" x-model="editExp.payment_mode">
              <?php foreach ($paymentModes as $m): ?>
                <option value="<?= $m ?>"><?= ucfirst($m) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group full"><label>Description</label><textarea class="form-control" name="description" x-model="editExp.description" rows="2"></textarea></div>
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
          <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
          <div class="form-group"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="catOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
