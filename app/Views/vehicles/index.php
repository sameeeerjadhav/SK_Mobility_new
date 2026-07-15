<div x-data="{ createOpen: false }">
  <div class="vh-list">
    <div class="vh-list-head">
      <div>
        <h1 class="page-title" style="margin:0;">Vehicles</h1>
        <p class="page-sub" style="margin:0.25rem 0 0;">Catalog models &amp; variants</p>
      </div>
      <?php if ($canManage): ?>
        <button class="btn btn-primary btn-sm" type="button" @click="createOpen=true">+ Create</button>
      <?php endif; ?>
    </div>

    <form method="get" class="vh-list-bar">
      <select class="form-control" name="category_id" aria-label="Category">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (string)$categoryId === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input class="form-control" name="search" value="<?= e($search) ?>" placeholder="Search name or brand">
      <button class="btn btn-outline btn-sm" type="submit">Filter</button>
    </form>

    <div class="vehicle-grid">
      <?php foreach ($vehicles as $v): ?>
        <a class="vh-card" href="<?= url('vehicles/' . $v['id']) ?>">
          <div class="vh-card-media">
            <?php if (!empty($v['image_url'])): ?>
              <img src="<?= asset($v['image_url']) ?>" alt="<?= e($v['name']) ?>" loading="lazy">
            <?php else: ?>
              <div class="vh-card-empty">No photo</div>
            <?php endif; ?>
          </div>
          <div class="vh-card-body">
            <div class="vh-card-cat"><?= e($v['category_name']) ?></div>
            <h3><?= e($v['name']) ?></h3>
            <div class="vh-card-meta">
              <span class="vh-card-price"><?= money($v['min_price'] ?? $v['base_price']) ?></span>
              <span class="vh-card-vars"><?= (int)($v['variant_count'] ?? 0) ?> variant<?= (int)($v['variant_count'] ?? 0) === 1 ? '' : 's' ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if (!$vehicles): ?>
      <p class="muted" style="margin:0.5rem 0 0;">No vehicles found.</p>
    <?php endif; ?>
  </div>

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

<style>
.vh-list{max-width:1100px}
.vh-list-head{display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;margin-bottom:.85rem}
.vh-list-bar{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem}
.vh-list-bar .form-control{padding:.45rem .7rem;border-radius:10px;font-size:.875rem;min-width:0}
.vh-list-bar select.form-control{width:180px;flex:0 0 auto}
.vh-list-bar input.form-control{flex:1;min-width:160px;max-width:320px}
@media(max-width:640px){
  .vh-list-bar select.form-control,.vh-list-bar input.form-control{width:100%;max-width:none;flex:1 1 100%}
}
</style>
