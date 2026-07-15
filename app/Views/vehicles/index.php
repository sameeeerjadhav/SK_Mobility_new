<div x-data="{ createOpen: false }">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Vehicles</h1>
      <p class="page-sub">EV catalog & variants</p>
    </div>
    <?php if ($canManage): ?>
      <button class="btn btn-primary" type="button" @click="createOpen=true">+ Create Vehicle</button>
    <?php endif; ?>
  </div>

  <form method="get" class="card" style="margin-bottom:1rem;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;">
    <div class="form-group" style="margin:0;min-width:180px;">
      <label>Category</label>
      <select class="form-control" name="category_id">
        <option value="">All</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (string)$categoryId === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:200px;">
      <label>Search</label>
      <input class="form-control" name="search" value="<?= e($search) ?>">
    </div>
    <button class="btn btn-outline" type="submit">Filter</button>
  </form>

  <div class="vehicle-grid">
    <?php foreach ($vehicles as $v): ?>
      <a class="card vehicle-card" href="<?= url('vehicles/' . $v['id']) ?>" style="display:block;color:inherit;">
        <?php if ($v['image_url']): ?>
          <img src="<?= asset($v['image_url']) ?>" alt="<?= e($v['name']) ?>">
        <?php else: ?>
          <div style="height:160px;border-radius:12px;background:linear-gradient(135deg,#ccfbf1,#f0faf8);display:grid;place-items:center;color:#0d9488;font-weight:700;">No image</div>
        <?php endif; ?>
        <div class="muted" style="font-size:0.75rem;margin-top:0.75rem;"><?= e($v['category_name']) ?></div>
        <h3><?= e($v['name']) ?></h3>
        <div style="font-weight:700;color:#0d9488;"><?= money($v['min_price'] ?? $v['base_price']) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if (!$vehicles): ?><div class="card"><p class="muted">No vehicles found. Create one to get started.</p></div><?php endif; ?>

  <?php if ($canManage): ?>
  <div class="modal-backdrop" :class="{ open: createOpen }" @click.self="createOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('vehicles') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Create Vehicle</h3><button type="button" class="btn btn-sm btn-outline" @click="createOpen=false">Close</button></div>
        <div class="modal-body form-grid">
          <div class="form-group full"><label>Name</label><input class="form-control" name="name" required></div>
          <div class="form-group"><label>Category</label>
            <select class="form-control" name="category_id" required>
              <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Brand</label><input class="form-control" name="brand" value="SK Mobility"></div>
          <div class="form-group"><label>Base Price</label><input class="form-control" type="number" step="0.01" name="base_price" required></div>
          <div class="form-group full"><label>Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" @click="createOpen=false">Cancel</button>
          <button class="btn btn-primary" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
