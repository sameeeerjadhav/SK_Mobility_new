<div x-data="{ createOpen: false, statusOpen: false, followOpen: false, leadId: null }">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Leads</h1>
      <p class="page-sub">CRM pipeline & follow-ups</p>
    </div>
    <?php if ($canManage): ?>
      <button class="btn btn-primary" type="button" @click="createOpen=true">+ Create Lead</button>
    <?php endif; ?>
  </div>

  <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:1rem;">
    <?php foreach ($funnel as $k => $v): ?>
      <a class="chip chip-<?= $status === $k ? 'primary' : 'secondary' ?>" href="<?= url('leads?status=' . $k) ?>" style="padding:0.4rem 0.8rem;"><?= e(ucfirst($k)) ?>: <?= (int)$v ?></a>
    <?php endforeach; ?>
    <a class="chip chip-info" href="<?= url('leads') ?>" style="padding:0.4rem 0.8rem;">All</a>
  </div>

  <form method="get" class="card" style="margin-bottom:1rem;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;">
    <div class="form-group" style="margin:0;min-width:160px;"><label>Source</label>
      <select class="form-control" name="source_id">
        <option value="">All</option>
        <?php foreach ($sources as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (string)$sourceId === (string)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <div class="form-group" style="margin:0;flex:1;"><label>Search</label><input class="form-control" name="search" value="<?= e($search) ?>"></div>
    <button class="btn btn-outline" type="submit">Filter</button>
  </form>

  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Customer</th><th>Source</th><th>Vehicle</th><th>Status</th><th>Assigned</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($leads as $l): ?>
          <tr>
            <td><strong><?= e($l['customer_name']) ?></strong><div class="muted" style="font-size:0.75rem;"><?= e($l['customer_phone']) ?></div></td>
            <td><?= e($l['source_name'] ?? '—') ?></td>
            <td><?= e($l['vehicle_name'] ?? '—') ?></td>
            <td><?= status_chip($l['status']) ?></td>
            <td><?= e(trim(($l['assigned_first'] ?? '') . ' ' . ($l['assigned_last'] ?? '')) ?: '—') ?></td>
            <td><?= india_date($l['created_at']) ?></td>
            <td style="white-space:nowrap;">
              <?php if ($canManage): ?>
                <button class="btn btn-sm btn-outline" type="button" @click="leadId=<?= (int)$l['id'] ?>; statusOpen=true">Status</button>
                <button class="btn btn-sm btn-outline" type="button" @click="leadId=<?= (int)$l['id'] ?>; followOpen=true">Follow-up</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$leads): ?><tr><td colspan="7" class="muted">No leads found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php \App\Core\View::partial('partials/pagination', ['pagination' => $pagination ?? [], 'filters' => $filters ?? []]); ?>
  </div>

  <?php if ($canManage): ?>
  <div class="modal-backdrop" :class="{ open: createOpen }" @click.self="createOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('leads') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Create Lead</h3><button type="button" class="btn btn-sm btn-outline" @click="createOpen=false">Close</button></div>
        <div class="modal-body form-grid">
          <div class="form-group"><label>Name</label><input class="form-control" name="customer_name" required></div>
          <div class="form-group"><label>Phone</label><input class="form-control contact-input" name="customer_phone" type="tel" maxlength="11" inputmode="numeric" placeholder="98765 43210" required></div>
          <div class="form-group"><label>Email</label><input class="form-control" type="email" name="customer_email"></div>
          <div class="form-group"><label>Source</label>
            <select class="form-control" name="source_id"><?php foreach ($sources as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select>
          </div>
          <div class="form-group"><label>Interested vehicle</label>
            <select class="form-control" name="interested_vehicle_id"><option value="">—</option><?php foreach ($vehicles as $v): ?><option value="<?= (int)$v['id'] ?>"><?= e($v['name']) ?></option><?php endforeach; ?></select>
          </div>
          <?php if ($dealers): ?>
          <div class="form-group"><label>Dealer</label>
            <select class="form-control" name="dealer_id"><option value="">—</option><?php foreach ($dealers as $d): ?><option value="<?= (int)$d['id'] ?>"><?= e($d['business_name']) ?></option><?php endforeach; ?></select>
          </div>
          <?php endif; ?>
          <div class="form-group full"><label>Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="createOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{ open: statusOpen }" @click.self="statusOpen=false">
    <div class="modal">
      <form method="post" :action="'<?= url('leads') ?>/'+leadId+'/status'">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Update Status</h3></div>
        <div class="modal-body">
          <div class="form-group"><label>Status</label>
            <select class="form-control" name="status">
              <?php foreach (['new','contacted','qualified','converted','lost'] as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="statusOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Update</button></div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{ open: followOpen }" @click.self="followOpen=false">
    <div class="modal">
      <form method="post" :action="'<?= url('leads') ?>/'+leadId+'/followups'">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Add Follow-up</h3></div>
        <div class="modal-body">
          <div class="form-group"><label>Follow-up date</label><input class="form-control" type="date" name="follow_up_date"></div>
          <div class="form-group"><label>Note</label><textarea class="form-control" name="note" rows="3" required></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="followOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
