<?php
$imagesByVariant = $imagesByVariant ?? [];
$coverImage = $coverImage ?? null;
$minPrice = $minPrice ?? (float)$vehicle['base_price'];
$variantCount = $variantCount ?? count($variants);
$editOpen = isset($_GET['edit']);
?>
<style>
.vh{max-width:1120px}
.vh-back{display:inline-block;font-size:.84rem;font-weight:600;color:#64748b;margin:0 0 .85rem}
.vh-hero{display:grid;grid-template-columns:minmax(240px,.95fr) minmax(0,1.15fr);gap:1rem;margin-bottom:1rem;align-items:stretch}
.vh-cover{background:#fff;border:1px solid var(--border);border-radius:16px;overflow:hidden;min-height:220px;position:relative}
.vh-cover img{width:100%;height:100%;min-height:220px;object-fit:cover;display:block}
.vh-cover-empty{min-height:220px;display:grid;place-items:center;background:linear-gradient(145deg,#ccfbf1,#f0fdfa);color:#0f766e;font-weight:700}
.vh-identity{background:#fff;border:1px solid var(--border);border-radius:16px;padding:1.15rem 1.25rem;display:flex;flex-direction:column;justify-content:space-between;gap:1rem}
.vh-identity h1{margin:0;font-size:1.45rem;font-weight:800;letter-spacing:-.03em;line-height:1.2}
.vh-brand{margin:.35rem 0 0;color:#64748b;font-size:.9rem;font-weight:600}
.vh-chips{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.75rem}
.vh-chip{display:inline-flex;align-items:center;padding:.28rem .65rem;border-radius:999px;background:#f0fdfa;color:#0f766e;font-size:.75rem;font-weight:700}
.vh-price-row{display:flex;justify-content:space-between;align-items:end;gap:1rem;flex-wrap:wrap;padding-top:.85rem;border-top:1px solid var(--border)}
.vh-price-label{font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#64748b}
.vh-price{font-size:1.35rem;font-weight:800;color:#0f766e;letter-spacing:-.02em}
.vh-actions{display:flex;gap:.4rem;flex-wrap:wrap}
.vh-tabs{display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:.85rem;padding:.35rem;background:#fff;border:1px solid var(--border);border-radius:12px;width:fit-content;max-width:100%}
.vh-tab{border:0;background:transparent;padding:.45rem .9rem;border-radius:9px;font-weight:700;font-size:.84rem;color:#64748b;cursor:pointer;font-family:inherit}
.vh-tab.active{background:#0d9488;color:#fff}
.vh-panel{background:#fff;border:1px solid var(--border);border-radius:16px;padding:1.1rem 1.2rem}
.vh-panel h3{margin:0 0 .85rem;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:800}
.vh-desc{margin:0;line-height:1.55;color:#334155;font-size:.95rem}
.vh-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:.65rem;margin-top:1rem}
.vh-stat{background:#f8fffd;border:1px solid #e6f4f1;border-radius:12px;padding:.75rem}
.vh-stat span{display:block;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
.vh-stat strong{display:block;margin-top:.2rem;font-size:1.05rem;font-weight:800}
.vh-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.55rem}
.vh-thumb{position:relative;border-radius:12px;overflow:hidden;border:1px solid var(--border);aspect-ratio:1;background:#f8fafc}
.vh-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.vh-thumb .badge{position:absolute;left:6px;top:6px}
.vh-thumb .del{position:absolute;right:6px;bottom:6px;padding:.1rem .4rem!important;font-size:.7rem!important}
.vh-var{border:1px solid var(--border);border-radius:14px;padding:.9rem;margin-bottom:.75rem}
.vh-var:last-child{margin-bottom:0}
.vh-var-top{display:flex;justify-content:space-between;gap:.75rem;flex-wrap:wrap;align-items:flex-start}
.vh-var-name{font-weight:800;font-size:1rem}
.vh-var-meta{color:#64748b;font-size:.82rem;margin-top:.2rem;font-weight:500}
.vh-empty{color:#64748b;font-size:.88rem;margin:0}
.vh-filters{display:flex;gap:.75rem;flex-wrap:wrap;align-items:end;background:#fff;border:1px solid var(--border);border-radius:14px;padding:.85rem 1rem;margin-bottom:1rem}
@media(max-width:900px){.vh-hero{grid-template-columns:1fr}.vh-stats{grid-template-columns:1fr}.vh-cover img,.vh-cover-empty{min-height:180px}}
</style>

<div class="vh" x-data="{ tab: '<?= $editOpen ? 'edit' : 'overview' ?>', editVariant: null }">
  <a class="vh-back" href="<?= url('vehicles') ?>">&larr; Vehicles</a>

  <div class="vh-hero">
    <div class="vh-cover">
      <?php if ($coverImage): ?>
        <img src="<?= asset($coverImage) ?>" alt="<?= e($vehicle['name']) ?>">
      <?php else: ?>
        <div class="vh-cover-empty">Add a photo in Media</div>
      <?php endif; ?>
    </div>
    <div class="vh-identity">
      <div>
        <h1><?= e($vehicle['name']) ?></h1>
        <p class="vh-brand"><?= e($vehicle['brand']) ?></p>
        <div class="vh-chips">
          <span class="vh-chip"><?= e($vehicle['category_name']) ?></span>
          <span class="vh-chip"><?= (int)$variantCount ?> variant<?= (int)$variantCount === 1 ? '' : 's' ?></span>
          <?php if ((int)$vehicle['is_active']): ?><span class="vh-chip">Active</span><?php endif; ?>
        </div>
      </div>
      <div class="vh-price-row">
        <div>
          <div class="vh-price-label">From</div>
          <div class="vh-price"><?= money($minPrice) ?></div>
        </div>
        <div class="vh-actions">
          <button class="btn btn-sm btn-outline" type="button" @click="tab='overview'">Overview</button>
          <button class="btn btn-sm btn-outline" type="button" @click="tab='variants'">Variants</button>
          <button class="btn btn-sm btn-outline" type="button" @click="tab='media'">Media</button>
          <?php if ($canManage): ?>
            <button class="btn btn-sm btn-primary" type="button" @click="tab='edit'">Edit</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="vh-tabs">
    <button type="button" class="vh-tab" :class="{ active: tab==='overview' }" @click="tab='overview'">Overview</button>
    <button type="button" class="vh-tab" :class="{ active: tab==='variants' }" @click="tab='variants'">Variants (<?= (int)$variantCount ?>)</button>
    <button type="button" class="vh-tab" :class="{ active: tab==='media' }" @click="tab='media'">Media</button>
    <?php if ($canManage): ?>
      <button type="button" class="vh-tab" :class="{ active: tab==='edit' }" @click="tab='edit'">Edit model</button>
    <?php endif; ?>
  </div>

  <div class="vh-panel" x-show="tab==='overview'" x-cloak>
    <h3>About this model</h3>
    <?php if (!empty($vehicle['description'])): ?>
      <p class="vh-desc"><?= nl2br(e($vehicle['description'])) ?></p>
    <?php else: ?>
      <p class="vh-empty">No description yet.<?= $canManage ? ' Add one in Edit model.' : '' ?></p>
    <?php endif; ?>
    <div class="vh-stats">
      <div class="vh-stat"><span>Base price</span><strong><?= money($vehicle['base_price']) ?></strong></div>
      <div class="vh-stat"><span>Starting at</span><strong><?= money($minPrice) ?></strong></div>
      <div class="vh-stat"><span>Variants</span><strong><?= (int)$variantCount ?></strong></div>
    </div>
  </div>

  <div class="vh-panel" x-show="tab==='media'" x-cloak>
    <h3>Vehicle gallery</h3>
    <p class="muted" style="margin:-0.45rem 0 .85rem;font-size:.82rem;">These photos appear on the catalog card. First upload is used as the cover automatically.</p>
    <div class="vh-gallery">
      <?php foreach ($images as $img): ?>
        <div class="vh-thumb">
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
    <?php if (!$images): ?><p class="vh-empty" style="margin-top:.5rem;">No vehicle photos yet.</p><?php endif; ?>
    <?php if ($canManage): ?>
      <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/images') ?>" enctype="multipart/form-data" style="margin-top:1rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
        <?= csrf_field() ?>
        <input class="form-control" type="file" name="image" accept="image/*" required style="max-width:260px;">
        <label style="font-size:.82rem;font-weight:700;"><input type="checkbox" name="is_primary" value="1" checked> Use as cover</label>
        <button class="btn btn-sm btn-primary" type="submit">Upload photo</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="vh-panel" x-show="tab==='variants'" x-cloak>
    <h3>Variants</h3>
    <?php foreach ($variants as $vv): ?>
      <?php $vImgs = $imagesByVariant[(int)$vv['id']] ?? []; ?>
      <div class="vh-var">
        <div class="vh-var-top">
          <div>
            <div class="vh-var-name">
              <?= e($vv['name']) ?>
              <?php if (!(int)$vv['is_active']): ?><span class="chip chip-secondary">Inactive</span><?php endif; ?>
            </div>
            <div class="vh-var-meta">
              <?= e($vv['sku']) ?>
              <?= $vv['color'] ? ' · ' . e($vv['color']) : '' ?>
              · <?= money($vv['price']) ?>
              · <?= e($vv['battery_capacity_kwh'] ?? '—') ?> kWh
              · <?= e($vv['range_km'] ?? '—') ?> km
            </div>
          </div>
          <?php if ($canManage): ?>
          <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
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

        <div class="vh-gallery" style="margin-top:.7rem;">
          <?php foreach ($vImgs as $img): ?>
            <div class="vh-thumb">
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
        <?php if (!$vImgs): ?><p class="vh-empty" style="margin-top:.55rem;">No images for this variant.</p><?php endif; ?>

        <?php if ($canManage): ?>
          <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/images') ?>" enctype="multipart/form-data" style="margin-top:.65rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;">
            <?= csrf_field() ?>
            <input type="hidden" name="variant_id" value="<?= (int)$vv['id'] ?>">
            <input class="form-control" type="file" name="image" accept="image/*" required style="max-width:220px;padding:.35rem .5rem;">
            <label style="font-size:.8rem;font-weight:700;"><input type="checkbox" name="is_primary" value="1"> Primary</label>
            <button class="btn btn-sm btn-primary" type="submit">Add image</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$variants): ?><p class="vh-empty">No variants yet.</p><?php endif; ?>

    <?php if ($canManage): ?>
      <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/variants') ?>" style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);">
        <?= csrf_field() ?>
        <h3 style="margin-bottom:.65rem;">Add variant</h3>
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

  <?php if ($canManage): ?>
  <div class="vh-panel" x-show="tab==='edit'" x-cloak>
    <h3>Edit model details</h3>
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
        <div class="form-group full"><label>Description</label><textarea class="form-control" name="description" rows="4"><?= e($vehicle['description'] ?? '') ?></textarea></div>
      </div>
      <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.35rem;">
        <button class="btn btn-primary" type="submit">Save changes</button>
      </div>
    </form>
    <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/delete') ?>" style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--border);" onsubmit="return confirm('Permanently delete this vehicle, variants, and images?')">
      <?= csrf_field() ?>
      <p class="muted" style="margin:0 0 .5rem;font-size:.82rem;">This removes the model from the catalog. Orders that already used it are kept.</p>
      <button class="btn btn-danger" type="submit">Delete vehicle</button>
    </form>
  </div>
  <?php endif; ?>
</div>
