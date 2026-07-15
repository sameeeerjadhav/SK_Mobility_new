<h1 class="page-title">Service Dashboard</h1>
<p class="page-sub">Service operations overview</p>
<div class="stat-grid">
  <?php foreach ($stats as $s): ?>
    <div class="stat-card">
      <div class="stat-label"><?= e($s['label']) ?></div>
      <div class="stat-value"><?= number_format((float)$s['value']) ?></div>
    </div>
  <?php endforeach; ?>
</div>
<div class="card"><p class="muted">Full service module arrives in Phase 3.</p></div>
