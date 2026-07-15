<?php
$reportMeta = [
  'sales' => 'Sales',
  'revenue' => 'Revenue',
  'inventory' => 'Inventory',
  'leads' => 'Lead Conversion',
  'dealers' => 'Dealer Performance',
];
?>
<div x-data="{ preview: null }">
  <h1 class="page-title">Reports</h1>
  <p class="page-sub">Export operational reports</p>

  <form method="get" class="card" style="margin-bottom:1rem;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;">
    <div class="form-group" style="margin:0;"><label>From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="form-group" style="margin:0;"><label>To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
    <button class="btn btn-outline" type="submit">Apply</button>
    <a class="btn btn-outline" href="<?= url('reports?from='.date('Y-m-01').'&to='.date('Y-m-d')) ?>">This Month</a>
    <a class="btn btn-outline" href="<?= url('reports?from='.date('Y-m-d', strtotime('-3 months')).'&to='.date('Y-m-d')) ?>">Last 3 Months</a>
    <a class="btn btn-outline" href="<?= url('reports?from='.date('Y-01-01').'&to='.date('Y-m-d')) ?>">This Year</a>
  </form>

  <div class="vehicle-grid">
    <?php foreach ($reportMeta as $key => $label): ?>
      <div class="card">
        <h3 style="margin:0 0 0.5rem;"><?= e($label) ?></h3>
        <p class="muted" style="font-size:0.85rem;"><?= count($previews[$key] ?? []) ?> preview rows</p>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.75rem;">
          <button class="btn btn-outline btn-sm" type="button" @click="preview='<?= $key ?>'">Preview</button>
          <?php if ($canExport): ?>
            <a class="btn btn-primary btn-sm" href="<?= url('reports/export/'.$key.'?format=csv&from='.urlencode($from).'&to='.urlencode($to)) ?>">Export CSV</a>
          <?php endif; ?>
        </div>
        <div x-show="preview==='<?= $key ?>'" style="margin-top:1rem;" x-cloak>
          <div class="table-wrap"><table class="data">
            <?php $rows = $previews[$key] ?? []; if ($rows): ?>
              <thead><tr><?php foreach (array_keys($rows[0]) as $col): ?><th><?= e($col) ?></th><?php endforeach; ?></tr></thead>
              <tbody>
              <?php foreach ($rows as $row): ?>
                <tr><?php foreach ($row as $cell): ?><td><?= e((string)$cell) ?></td><?php endforeach; ?></tr>
              <?php endforeach; ?>
              </tbody>
            <?php else: ?>
              <tr><td class="muted">No data in range.</td></tr>
            <?php endif; ?>
          </table></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
