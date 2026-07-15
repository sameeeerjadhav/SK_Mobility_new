<?php
$imagesByVariant = $imagesByVariant ?? [];
?>
<div style="margin-bottom:1rem;"><a href="<?= url('vehicles') ?>">&larr; Vehicles</a></div>

<div class="grid-2" x-data="{ editVariant: null }">
  <div class="card">
    <h1 class="page-title" style="margin-top:0;"><?= e($vehicle['name']) ?></h1>
    <p class="muted"><?= e($vehicle['brand']) ?> · <?= e($vehicle['category_name']) ?></p>
    <p><?= nl2br(e($vehicle['description'] ?? '')) ?></p>
    <p><strong>Base price:</strong> <?= money($vehicle['base_price']) ?></p>

    <?php if ($canManage): ?>
      <form method="post" action="<?= url('vehicles/' . $vehicle['id']) ?>" style="margin-top:1rem;">
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="form-group full"><label>Name</label><input class="form-control" name="name" value="<?= e($vehicle['name']) ?>" required></div>
          <div class="form-group"><label>Category</label>
            <select class="form-control" name="category_id">
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$vehicle['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Brand</label><input class="form-control" name="brand" value="<?= e($vehicle['brand']) ?>"></div>
          <div class="form-group"><label>Base Price</label><input class="form-control" type="number" step="0.01" name="base_price" value="<?= e($vehicle['base_price']) ?>"></div>
          <div class="form-group full"><label>Description</label><textarea class="form-control" name="description" rows="3"><?= e($vehicle['description'] ?? '') ?></textarea></div>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.25rem;">
          <button class="btn btn-primary" type="submit">Update vehicle</button>
        </div>
      </form>
      <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/delete') ?>" style="margin-top:0.75rem;" onsubmit="return confirm('Permanently delete this vehicle and all its variants? This cannot be undone.')">
        <?= csrf_field() ?>
        <button class="btn btn-danger" type="submit">Delete vehicle</button>
      </form>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
      <h3 class="card-title">Vehicle images</h3>
      <p class="muted" style="margin:-0.4rem 0 0.75rem;font-size:0.8rem;">General photos for this model (not tied to a color/variant).</p>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:0.5rem;margin-bottom:1rem;">
        <?php foreach ($images as $img): ?>
          <div style="position:relative;">
            <img src="<?= asset($img['image_url']) ?>" style="width:100%;height:90px;object-fit:cover;border-radius:8px;border:1px solid var(--border);" alt="">
            <?php if (!empty($img['is_primary'])): ?><span class="chip chip-primary" style="position:absolute;left:4px;top:4px;font-size:0.65rem;">Primary</span><?php endif; ?>
            <?php if ($canManage): ?>
              <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/images/' . $img['id'] . '/delete') ?>" onsubmit="return confirm('Delete this image?')" style="position:absolute;right:4px;bottom:4px;">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-danger" type="submit" style="padding:0.15rem 0.4rem;font-size:0.7rem;">×</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (!$images): ?><p class="muted" style="grid-column:1/-1;margin:0;">No vehicle images yet.</p><?php endif; ?>
      </div>
      <?php if ($canManage): ?>
        <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/images') ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="form-group"><input class="form-control" type="file" name="image" accept="image/*" required></div>
          <label style="font-size:0.85rem;"><input type="checkbox" name="is_primary" value="1"> Set as primary</label>
          <div style="margin-top:0.5rem;"><button class="btn btn-primary btn-sm" type="submit">Upload vehicle image</button></div>
        </form>
      <?php endif; ?>
    </div>

    <div class="card" style="margin-top:1rem;">
      <h3 class="card-title">Variants</h3>
      <div style="display:flex;flex-direction:column;gap:0.85rem;">
        <?php foreach ($variants as $vv): ?>
          <?php $vImgs = $imagesByVariant[(int)$vv['id']] ?? []; ?>
          <div style="border:1px solid var(--border);border-radius:12px;padding:0.75rem;">
            <div style="display:flex;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;align-items:start;">
              <div>
                <div style="font-weight:800;">
                  <?= e($vv['name']) ?>
                  <?php if (!(int)$vv['is_active']): ?><span class="chip chip-secondary">Inactive</span><?php endif; ?>
                </div>
                <div class="muted" style="font-size:0.82rem;margin-top:0.2rem;">
                  <?= e($vv['sku']) ?>
                  <?= $vv['color'] ? ' · ' . e($vv['color']) : '' ?>
                  · <?= money($vv['price']) ?>
                  · <?= e($vv['battery_capacity_kwh'] ?? '—') ?> kWh
                  · <?= e($vv['range_km'] ?? '—') ?> km
                </div>
              </div>
              <?php if ($canManage): ?>
              <div style="display:flex;gap:0.35rem;flex-wrap:wrap;">
                <button class="btn btn-sm btn-outline" type="button"
                  @click='editVariant = <?= json_encode([
                      'id' => (int)$vv['id'],
                      'name' => $vv['name'],
                      'sku' => $vv['sku'],
                      'color' => $vv['color'] ?? '',
                      'price' => $vv['price'],
                      'battery_capacity_kwh' => $vv['battery_capacity_kwh'] ?? '',
                      'range_km' => $vv['range_km'] ?? '',
                      'is_active' => (int)$vv['is_active'],
                  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>'>Edit</button>
                <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/variants/' . $vv['id'] . '/delete') ?>" onsubmit="return confirm('Delete this variant and its images?')">
                  <?= csrf_field() ?>
                  <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                </form>
              </div>
              <?php endif; ?>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(88px,1fr));gap:0.45rem;margin-top:0.7rem;">
              <?php foreach ($vImgs as $img): ?>
                <div style="position:relative;">
                  <img src="<?= asset($img['image_url']) ?>" style="width:100%;height:78px;object-fit:cover;border-radius:8px;border:1px solid var(--border);" alt="">
                  <?php if (!empty($img['is_primary'])): ?><span class="chip chip-primary" style="position:absolute;left:3px;top:3px;font-size:0.62rem;">Primary</span><?php endif; ?>
                  <?php if ($canManage): ?>
                    <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/images/' . $img['id'] . '/delete') ?>" onsubmit="return confirm('Delete this image?')" style="position:absolute;right:3px;bottom:3px;">
                      <?= csrf_field() ?>
                      <button class="btn btn-sm btn-danger" type="submit" style="padding:0.12rem 0.35rem;font-size:0.68rem;">×</button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
              <?php if (!$vImgs): ?><p class="muted" style="grid-column:1/-1;margin:0;font-size:0.8rem;">No images for this variant.</p><?php endif; ?>
            </div>

            <?php if ($canManage): ?>
              <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/images') ?>" enctype="multipart/form-data" style="margin-top:0.65rem;display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                <?= csrf_field() ?>
                <input type="hidden" name="variant_id" value="<?= (int)$vv['id'] ?>">
                <input class="form-control" type="file" name="image" accept="image/*" required style="max-width:220px;padding:0.35rem 0.5rem;">
                <label style="font-size:0.8rem;font-weight:600;"><input type="checkbox" name="is_primary" value="1"> Primary</label>
                <button class="btn btn-sm btn-primary" type="submit">Add image</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (!$variants): ?><p class="muted">No variants yet.</p><?php endif; ?>
      </div>

      <?php if ($canManage): ?>
        <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/variants') ?>" style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);">
          <?= csrf_field() ?>
          <h4 style="margin:0 0 0.5rem;font-size:0.85rem;">Add variant</h4>
          <div class="form-grid">
            <div class="form-group"><label>Variant name</label><input class="form-control" name="name" required></div>
            <div class="form-group"><label>SKU</label><input class="form-control" name="sku" placeholder="Auto if blank"></div>
            <div class="form-group"><label>Color</label><input class="form-control" name="color"></div>
            <div class="form-group"><label>Price</label><input class="form-control" type="number" step="0.01" name="price" required></div>
            <div class="form-group"><label>Battery kWh</label><input class="form-control" type="number" step="0.01" name="battery_capacity_kwh"></div>
            <div class="form-group"><label>Range km</label><input class="form-control" type="number" name="range_km"></div>
          </div>
          <button class="btn btn-primary" type="submit">Add Variant</button>
        </form>

        <div class="modal-backdrop" :class="{ open: !!editVariant }" @click.self="editVariant=null" x-show="editVariant" x-cloak>
          <div class="modal" x-show="editVariant">
            <form method="post" :action="'<?= url('vehicles/' . $vehicle['id'] . '/variants') ?>/' + editVariant?.id">
              <?= csrf_field() ?>
              <div class="modal-header">
                <h3 class="modal-title">Edit variant</h3>
                <button type="button" class="btn btn-sm btn-outline" @click="editVariant=null">Close</button>
              </div>
              <div class="modal-body form-grid">
                <div class="form-group"><label>Name</label><input class="form-control" name="name" :value="editVariant?.name" required></div>
                <div class="form-group"><label>SKU</label><input class="form-control" name="sku" :value="editVariant?.sku"></div>
                <div class="form-group"><label>Color</label><input class="form-control" name="color" :value="editVariant?.color"></div>
                <div class="form-group"><label>Price</label><input class="form-control" type="number" step="0.01" name="price" :value="editVariant?.price" required></div>
                <div class="form-group"><label>Battery kWh</label><input class="form-control" type="number" step="0.01" name="battery_capacity_kwh" :value="editVariant?.battery_capacity_kwh"></div>
                <div class="form-group"><label>Range km</label><input class="form-control" type="number" name="range_km" :value="editVariant?.range_km"></div>
                <div class="form-group"><label>Active</label>
                  <select class="form-control" name="is_active" :value="String(editVariant?.is_active)">
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                  </select>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline" @click="editVariant=null">Cancel</button>
                <button class="btn btn-primary" type="submit">Save variant</button>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
