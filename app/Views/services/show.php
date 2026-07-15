<div style="margin-bottom:1rem;"><a href="<?= url('services') ?>">&larr; Services</a></div>

<div class="card" style="margin-bottom:1rem;">
  <h1 class="page-title" style="margin:0;"><?= e($request['request_number']) ?></h1>
  <p class="page-sub"><?= status_chip($request['status']) ?> · <?= e($request['customer_name']) ?> · <?= e($request['customer_phone']) ?></p>
  <p><strong>Vehicle:</strong> <?= e($request['vehicle_model'] ?? '—') ?> / VIN <?= e($request['vehicle_vin'] ?? '—') ?></p>
  <p><?= nl2br(e($request['issue_description'])) ?></p>
</div>

<div class="grid-2">
  <div class="card">
    <h3 class="card-title">Job cards</h3>
    <?php foreach ($jobCards as $jc): ?>
      <div style="border:1px solid #f1f5f9;border-radius:12px;padding:1rem;margin-bottom:0.75rem;">
        <div style="display:flex;justify-content:space-between;gap:0.5rem;">
          <strong><?= e($jc['job_card_number']) ?></strong>
          <?= status_chip($jc['status']) ?>
        </div>
        <p class="muted" style="margin:0.35rem 0;">Tech: <?= e($jc['technician_name'] ?? 'Unassigned') ?></p>
        <p><?= nl2br(e($jc['work_description'] ?? '')) ?></p>
        <p><strong>Cost:</strong> <?= money($jc['total_cost']) ?> (Labour <?= money($jc['labour_cost']) ?> + Parts <?= money($jc['parts_cost']) ?>)</p>
        <?php if ($canManage): ?>
          <form method="post" action="<?= url('job-cards/' . $jc['id']) ?>" style="margin-top:0.75rem;">
            <?= csrf_field() ?>
            <div class="form-grid">
              <div class="form-group"><label>Technician</label>
                <select class="form-control" name="technician_id">
                  <option value="">—</option>
                  <?php foreach ($technicians as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= (int)$jc['technician_id'] === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group"><label>Status</label>
                <select class="form-control" name="status">
                  <?php foreach (['open','in_progress','completed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $jc['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group full"><label>Work description</label><textarea class="form-control" name="work_description" rows="2"><?= e($jc['work_description'] ?? '') ?></textarea></div>
              <div class="form-group full"><label>Parts used</label><textarea class="form-control" name="parts_used" rows="2"><?= e($jc['parts_used'] ?? '') ?></textarea></div>
              <div class="form-group"><label>Labour cost</label><input class="form-control" type="number" step="0.01" name="labour_cost" value="<?= e($jc['labour_cost']) ?>"></div>
              <div class="form-group"><label>Parts cost</label><input class="form-control" type="number" step="0.01" name="parts_cost" value="<?= e($jc['parts_cost']) ?>"></div>
            </div>
            <button class="btn btn-primary btn-sm" type="submit">Update job card</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$jobCards): ?><p class="muted">No job cards yet.</p><?php endif; ?>
  </div>

  <?php if ($canManage): ?>
  <div class="card">
    <h3 class="card-title">Create job card</h3>
    <form method="post" action="<?= url('services/' . $request['id'] . '/job-cards') ?>">
      <?= csrf_field() ?>
      <div class="form-group"><label>Technician</label>
        <select class="form-control" name="technician_id">
          <option value="">—</option>
          <?php foreach ($technicians as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Work description</label><textarea class="form-control" name="work_description" rows="3"></textarea></div>
      <button class="btn btn-primary" type="submit">Create</button>
    </form>
  </div>
  <?php endif; ?>
</div>
