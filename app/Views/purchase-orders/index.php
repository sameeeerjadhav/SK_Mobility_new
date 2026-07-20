<?php
$statuses = ['draft', 'confirmed', 'partial', 'received', 'cancelled'];
$statusLabels = [
    'draft' => 'Draft',
    'confirmed' => 'Confirmed',
    'partial' => 'Partial',
    'received' => 'Received',
    'cancelled' => 'Cancelled',
];
$statusClass = [
    'draft' => 'chip-muted',
    'confirmed' => 'chip-info',
    'partial' => 'chip-warning',
    'received' => 'chip-success',
    'cancelled' => 'chip-danger',
];
?>
<div>
  <div class="toolbar">
    <div>
      <h1 class="page-title">Purchase Orders</h1>
      <p class="page-sub">Procure vehicle variants from suppliers — receipt updates inventory by warehouse &amp; color. Linked to <a href="<?= url('vehicles') ?>">Vehicles</a> and <a href="<?= url('inventory') ?>">Inventory</a>.</p>
    </div>
    <a class="btn btn-primary" href="<?= url('purchase-orders/create') ?>">+ New Purchase Order</a>
  </div>

  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-label">Open POs</div>
      <div class="stat-value"><?= (int)$stats['open'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Received (this month)</div>
      <div class="stat-value"><?= (int)$stats['received_month'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Units pending receipt</div>
      <div class="stat-value"><?= (int)$stats['pending_qty'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">PO value (this month)</div>
      <div class="stat-value"><?= money($stats['month_value']) ?></div>
    </div>
  </div>

  <form method="get" class="card" style="margin-bottom:1rem;">
    <h3 class="card-title" style="margin-bottom:0.65rem;">Filters</h3>
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;">
      <div class="form-group" style="margin:0;min-width:130px;">
        <label>Status</label>
        <select class="form-control" name="status">
          <option value="">All</option>
          <?php foreach ($statuses as $s): ?>
            <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($statusLabels[$s]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;min-width:180px;">
        <label>Supplier company</label>
        <input class="form-control" name="supplier" value="<?= e($supplier) ?>" placeholder="e.g. Alphavector India">
      </div>
      <div class="form-group" style="margin:0;min-width:140px;">
        <label>From</label>
        <input class="form-control" type="date" name="from" value="<?= e($from ?? '') ?>">
      </div>
      <div class="form-group" style="margin:0;min-width:140px;">
        <label>To</label>
        <input class="form-control" type="date" name="to" value="<?= e($to ?? '') ?>">
      </div>
      <div class="form-group" style="margin:0;flex:1;min-width:160px;">
        <label>Search</label>
        <input class="form-control" name="search" value="<?= e($search) ?>" placeholder="PO no., invoice, supplier">
      </div>
      <button class="btn btn-outline" type="submit">Apply</button>
      <a class="btn btn-outline" href="<?= url('purchase-orders') ?>">Reset</a>
    </div>
  </form>

  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>PO Number</th>
            <th>Date</th>
            <th>Supplier company</th>
            <th>Lines</th>
            <th>Qty</th>
            <th>Total (incl. GST)</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><strong><?= e($o['po_number']) ?></strong></td>
            <td><?= e(date('d M Y', strtotime($o['po_date']))) ?></td>
            <td><?= e($o['supplier_name'] ?? '—') ?></td>
            <td><?= (int)$o['line_count'] ?></td>
            <td><?= (int)$o['total_qty'] ?></td>
            <td><?= money($o['total_amount']) ?></td>
            <td><span class="chip <?= $statusClass[$o['status']] ?? 'chip-muted' ?>"><?= e($statusLabels[$o['status']] ?? $o['status']) ?></span></td>
            <td><a class="btn btn-sm btn-outline" href="<?= url('purchase-orders/' . $o['id']) ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?>
          <tr>
            <td colspan="8" class="muted">
              No purchase orders yet.
              <a href="<?= url('purchase-orders/create') ?>">Create your first PO</a>
            </td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
