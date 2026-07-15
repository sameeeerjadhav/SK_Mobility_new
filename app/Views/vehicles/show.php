<?php
$imagesByVariant = $imagesByVariant ?? [];
$coverImage = $coverImage ?? null;
$minPrice = $minPrice ?? (float)$vehicle['base_price'];
$variantCount = $variantCount ?? count($variants);
$tabInit = match ($_GET['tab'] ?? '') {
    'variants', 'media', 'edit' => $_GET['tab'],
    default => 'overview',
};
?>
<style>
.vp{max-width:1080px}
.vp-back{display:inline-block;font-size:.82rem;font-weight:600;color:#64748b;margin:0 0 .7rem}
.vp-hero{display:grid;grid-template-columns:280px minmax(0,1fr);gap:1rem;margin-bottom:.85rem}
.vp-cover{border-radius:14px;overflow:hidden;border:1px solid var(--border);background:#fff;aspect-ratio:4/3}
.vp-cover img{width:100%;height:100%;object-fit:cover;display:block}
.vp-cover-empty{height:100%;min-height:180px;display:grid;place-items:center;background:linear-gradient(145deg,#ccfbf1,#f0fdfa);color:#0f766e;font-weight:700;font-size:.9rem}
.vp-info{display:flex;flex-direction:column;justify-content:center;gap:.55rem;min-width:0}
.vp-info h1{margin:0;font-size:1.35rem;font-weight:800;letter-spacing:-.03em;line-height:1.2}
.vp-sub{margin:0;font-size:.88rem;color:#64748b;font-weight:600}
.vp-chips{display:flex;flex-wrap:wrap;gap:.35rem}
.vp-chip{font-size:.72rem;font-weight:700;padding:.22rem .55rem;border-radius:999px;background:#f0fdfa;color:#0f766e}
.vp-price{font-size:1.15rem;font-weight:800;color:#0f766e;letter-spacing:-.02em}
.vp-price span{display:block;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin-bottom:.1rem}
.vp-tabs{display:flex;gap:.25rem;flex-wrap:wrap;margin-bottom:.85rem;border-bottom:1px solid var(--border);padding-bottom:0}
.vp-tab{border:0;background:transparent;padding:.55rem .85rem;font-weight:700;font-size:.84rem;color:#64748b;cursor:pointer;font-family:inherit;border-bottom:2px solid transparent;margin-bottom:-1px}
.vp-tab.active{color:#0f766e;border-bottom-color:#0d9488}
.vp-panel{background:#fff;border:1px solid var(--border);border-radius:14px;padding:1rem 1.1rem}
.vp-panel h3{margin:0 0 .7rem;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:800}
.vp-desc{margin:0;line-height:1.55;color:#334155;font-size:.92rem}
.vp-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.5rem;margin-top:.9rem}
.vp-stat{padding:.65rem .75rem;border-radius:10px;background:#f8fffd;border:1px solid #e6f4f1}
.vp-stat span{display:block;font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
.vp-stat strong{display:block;margin-top:.15rem;font-size:.98rem;font-weight:800}
.vp-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(104px,1fr));gap:.5rem}
.vp-thumb{position:relative;border-radius:10px;overflow:hidden;border:1px solid var(--border);aspect-ratio:1;background:#f8fafc}
.vp-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.vp-thumb .badge{position:absolute;left:5px;top:5px}
.vp-thumb .del{position:absolute;right:5px;bottom:5px;padding:.1rem .35rem!important;font-size:.68rem!important}
.vp-var{border:1px solid var(--border);border-radius:12px;padding:.8rem;margin-bottom:.65rem}
.vp-var:last-of-type{margin-bottom:0}
.vp-var-top{display:flex;justify-content:space-between;gap:.6rem;flex-wrap:wrap;align-items:flex-start}
.vp-var-name{font-weight:800;font-size:.95rem}
.vp-var-meta{color:#64748b;font-size:.8rem;margin-top:.15rem;font-weight:500}
.vp-empty{color:#64748b;font-size:.86rem;margin:0}
.vp-upload{margin-top:.75rem;display:flex;gap:.45rem;flex-wrap:wrap;align-items:center}
.vp-upload .form-control{padding:.35rem .55rem;border-radius:9px;font-size:.84rem;max-width:240px}
@media(max-width:800px){
  .vp-hero{grid-template-columns:1fr}
  .vp-cover{max-width:320px}
  .vp-stats{grid-template-columns:1fr}
}
</style>

<div class="vp" x-data="{ tab: '<?= e($tabInit) ?>', editVariant: null }">
  <a class="vp-back" href="<?= url('vehicles') ?>">&larr; Vehicles</a>

  <div class="vp-hero">
    <div class="vp-cover">
      <?php if ($coverImage): ?>
        <img src="<?= asset($coverImage) ?>" alt="<?= e($vehicle['name']) ?>">
      <?php else: ?>
        <div class="vp-cover-empty">No cover photo</div>
      <?php endif; ?>
    </div>
    <div class="vp-info">
      <div>
        <h1><?= e($vehicle['name']) ?></h1>
        <p class="vp-sub"><?= e($vehicle['brand']) ?> · <?= e($vehicle['category_name']) ?></p>
      </div>
      <div class="vp-chips">
        <span class="vp-chip"><?= (int)$variantCount ?> variant<?= (int)$variantCount === 1 ? '' : 's' ?></span>
        <?php if ((int)$vehicle['is_active']): ?><span class="vp-chip">Active</span><?php endif; ?>
      </div>
      <div class="vp-price"><span>From</span><?= money($minPrice) ?></div>
    </div>
  </div>

  <div class="vp-tabs">
    <button type="button" class="vp-tab" :class="{ active: tab==='overview' }" @click="tab='overview'">Overview</button>
    <button type="button" class="vp-tab" :class="{ active: tab==='variants' }" @click="tab='variants'">Variants</button>
    <button type="button" class="vp-tab" :class="{ active: tab==='media' }" @click="tab='media'">Media</button>
    <?php if ($canManage): ?>
      <button type="button" class="vp-tab" :class="{ active: tab==='edit' }" @click="tab='edit'">Edit</button>
    <?php endif; ?>
  </div>

  <div class="vp-panel" x-show="tab==='overview'" x-cloak>
    <h3>About</h3>
    <?php if (!empty($vehicle['description'])): ?>
      <p class="vp-desc"><?= nl2br(e($vehicle['description'])) ?></p>
    <?php else: ?>
      <p class="vp-empty">No description<?= $canManage ? ' — add one in Edit.' : '.' ?></p>
    <?php endif; ?>
    <div class="vp-stats">
      <div class="vp-stat"><span>Base price</span><strong><?= money($vehicle['base_price']) ?></strong></div>
      <div class="vp-stat"><span>Starting at</span><strong><?= money($minPrice) ?></strong></div>
      <div class="vp-stat"><span>Variants</span><strong><?= (int)$variantCount ?></strong></div>
    </div>
  </div>

  <div class="vp-panel" x-show="tab==='media'" x-cloak>
    <h3>Catalog photos</h3>
    <p class="muted" style="margin:-0.35rem 0 .7rem;font-size:.8rem;">Shown on the Vehicles list card. First photo becomes the cover.</p>
    <div class="vp-gallery">
      <?php foreach ($images as $img): ?>
        <div class="vp-thumb">
          <img src="<?= asset($img['image_url']) ?>" alt="">
          <?php if (!empty($img['is_primary'])): ?><span class="chip chip-primary badge">Cover</span><?php endif; ?>
          <?php if ($canManage): ?>
            <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/images/' . $img['id'] . '/delete') ?>" onsubmit="return confirm('Delete this image?')">
              <?= csrf_field() ?>
              <button class="btn btn-sm btn-danger del" type="submit">×</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!$images): ?><p class="vp-empty" style="margin-top:.4rem;">No photos yet.</p><?php endif; ?>
    <?php if ($canManage): ?>
      <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/images') ?>" enctype="multipart/form-data" class="vp-upload">
        <?= csrf_field() ?>
        <input class="form-control" type="file" name="image" accept="image/*" required>
        <label style="font-size:.8rem;font-weight:700;"><input type="checkbox" name="is_primary" value="1" checked> Cover</label>
        <button class="btn btn-sm btn-primary" type="submit">Upload</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="vp-panel" x-show="tab==='variants'" x-cloak>
    <h3>Variants</h3>
    <?php foreach ($variants as $vv): ?>
      <?php $vImgs = $imagesByVariant[(int)$vv['id']] ?? []; ?>
      <div class="vp-var">
        <div class="vp-var-top">
          <div>
            <div class="vp-var-name">
              <?= e($vv['name']) ?>
              <?php if (!(int)$vv['is_active']): ?><span class="chip chip-secondary">Inactive</span><?php endif; ?>
            </div>
            <div class="vp-var-meta">
              <?= e($vv['sku']) ?>
              <?= $vv['color'] ? ' · ' . e($vv['color']) : '' ?>
              · <?= money($vv['price']) ?>
              · <?= e($vv['battery_capacity_kwh'] ?? '—') ?> kWh
              · <?= e($vv['range_km'] ?? '—') ?> km
            </div>
          </div>
          <?php if ($canManage): ?>
          <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
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
            <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/variants/' . $vv['id'] . '/delete') ?>" onsubmit="return confirm('Delete this variant?')">
              <?= csrf_field() ?>
              <button class="btn btn-sm btn-danger" type="submit">Delete</button>
            </form>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($vImgs): ?>
          <div class="vp-gallery" style="margin-top:.6rem;">
            <?php foreach ($vImgs as $img): ?>
              <div class="vp-thumb">
                <img src="<?= asset($img['image_url']) ?>" alt="">
                <?php if (!empty($img['is_primary'])): ?><span class="chip chip-primary badge">Primary</span><?php endif; ?>
                <?php if ($canManage): ?>
                  <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/images/' . $img['id'] . '/delete') ?>" onsubmit="return confirm('Delete this image?')">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-danger del" type="submit">×</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($canManage): ?>
          <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/images') ?>" enctype="multipart/form-data" class="vp-upload">
            <?= csrf_field() ?>
            <input type="hidden" name="variant_id" value="<?= (int)$vv['id'] ?>">
            <input class="form-control" type="file" name="image" accept="image/*" required>
            <label style="font-size:.78rem;font-weight:700;"><input type="checkbox" name="is_primary" value="1"> Primary</label>
            <button class="btn btn-sm btn-outline" type="submit">Add image</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$variants): ?><p class="vp-empty">No variants yet.</p><?php endif; ?>

    <?php if ($canManage): ?>
      <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/variants') ?>" style="margin-top:.9rem;padding-top:.9rem;border-top:1px solid var(--border);">
        <?= csrf_field() ?>
        <h3>Add variant</h3>
        <div class="form-grid">
          <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
          <div class="form-group"><label>SKU</label><input class="form-control" name="sku" placeholder="Auto if blank"></div>
          <div class="form-group"><label>Color</label><input class="form-control" name="color"></div>
          <div class="form-group"><label>Price</label><input class="form-control" type="number" step="0.01" name="price" required></div>
          <div class="form-group"><label>Battery kWh</label><input class="form-control" type="number" step="0.01" name="battery_capacity_kwh"></div>
          <div class="form-group"><label>Range km</label><input class="form-control" type="number" name="range_km"></div>
        </div>
        <button class="btn btn-sm btn-primary" type="submit">Add variant</button>
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
              <button class="btn btn-primary" type="submit">Save</button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($canManage): ?>
  <div class="vp-panel" x-show="tab==='edit'" x-cloak>
    <h3>Edit model</h3>
    <form method="post" action="<?= url('vehicles/' . $vehicle['id']) ?>">
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
        <div class="form-group"><label>Base price</label><input class="form-control" type="number" step="0.01" name="base_price" value="<?= e($vehicle['base_price']) ?>"></div>
        <div class="form-group full"><label>Description</label><textarea class="form-control" name="description" rows="3"><?= e($vehicle['description'] ?? '') ?></textarea></div>
      </div>
      <button class="btn btn-sm btn-primary" type="submit">Save changes</button>
    </form>
    <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/delete') ?>" style="margin-top:1rem;padding-top:.85rem;border-top:1px solid var(--border);" onsubmit="return confirm('Permanently delete this vehicle?')">
      <?= csrf_field() ?>
      <button class="btn btn-sm btn-danger" type="submit">Delete vehicle</button>
    </form>
  </div>
  <?php endif; ?>
</div>
