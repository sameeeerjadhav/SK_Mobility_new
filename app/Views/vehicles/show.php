<div style="margin-bottom:1rem;"><a href="<?= url('vehicles') ?>">&larr; Vehicles</a></div>

<div class="grid-2">
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
        <button class="btn btn-primary" type="submit">Update</button>
      </form>
      <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/delete') ?>" style="margin-top:0.75rem;" onsubmit="return confirm('Deactivate this vehicle?')">
        <?= csrf_field() ?>
        <button class="btn btn-danger" type="submit">Deactivate</button>
      </form>
    <?php endif; ?>
  </div>

  <div>
    <div class="card">
      <h3 class="card-title">Images</h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:0.5rem;margin-bottom:1rem;">
        <?php foreach ($images as $img): ?>
          <img src="<?= asset($img['image_url']) ?>" style="width:100%;height:90px;object-fit:cover;border-radius:8px;" alt="">
        <?php endforeach; ?>
      </div>
      <?php if ($canManage): ?>
        <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/images') ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="form-group"><input class="form-control" type="file" name="image" accept="image/*" required></div>
          <label style="font-size:0.85rem;"><input type="checkbox" name="is_primary" value="1"> Set as primary</label>
          <div style="margin-top:0.5rem;"><button class="btn btn-primary btn-sm" type="submit">Upload</button></div>
        </form>
      <?php endif; ?>
    </div>

    <div class="card" style="margin-top:1rem;">
      <h3 class="card-title">Variants</h3>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Name</th><th>SKU</th><th>Color</th><th>Price</th><th>Battery</th><th>Range</th></tr></thead>
          <tbody>
          <?php foreach ($variants as $vv): ?>
            <tr>
              <td><?= e($vv['name']) ?></td>
              <td><?= e($vv['sku']) ?></td>
              <td><?= e($vv['color'] ?? '—') ?></td>
              <td><?= money($vv['price']) ?></td>
              <td><?= e($vv['battery_capacity_kwh'] ?? '—') ?> kWh</td>
              <td><?= e($vv['range_km'] ?? '—') ?> km</td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$variants): ?><tr><td colspan="6" class="muted">No variants yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($canManage): ?>
        <form method="post" action="<?= url('vehicles/' . $vehicle['id'] . '/variants') ?>" style="margin-top:1rem;">
          <?= csrf_field() ?>
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
      <?php endif; ?>
    </div>
  </div>
</div>
