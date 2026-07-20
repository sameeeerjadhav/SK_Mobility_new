<div class="toolbar">
  <div>
    <h1 class="page-title">Notifications</h1>
    <p class="page-sub">Your alerts & updates</p>
  </div>
  <form method="post" action="<?= url('notifications/read-all') ?>">
    <?= csrf_field() ?>
    <button class="btn btn-outline" type="submit">Mark all read</button>
  </form>
</div>

<div class="card">
  <?php if (!$notifications): ?>
    <p class="muted">No notifications yet.</p>
  <?php endif; ?>
  <?php foreach ($notifications as $n): ?>
    <div style="display:flex;justify-content:space-between;gap:1rem;padding:0.85rem 0;border-bottom:1px solid #f1f5f9;<?= $n['is_read']?'opacity:.65':'' ?>">
      <div>
        <strong><?= e($n['title']) ?></strong>
        <?php if (!$n['is_read']): ?><span class="chip chip-primary">New</span><?php endif; ?>
        <div class="muted" style="font-size:0.85rem;margin-top:0.25rem;"><?= e($n['message']) ?></div>
        <div class="muted" style="font-size:0.75rem;margin-top:0.25rem;"><?= india_datetime($n['created_at']) ?></div>
      </div>
      <?php if (!$n['is_read']): ?>
        <form method="post" action="<?= url('notifications/'.$n['id'].'/read') ?>">
          <?= csrf_field() ?>
          <button class="btn btn-sm btn-outline" type="submit">Mark read</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php \App\Core\View::partial('partials/pagination', ['pagination' => $pagination ?? [], 'filters' => $filters ?? []]); ?>
</div>
