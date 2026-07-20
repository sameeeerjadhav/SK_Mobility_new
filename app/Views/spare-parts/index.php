<div x-data="{ createOpen: false, usageOpen: false, editOpen: false, editPart: null }">
  <div class="toolbar">
    <div>
      <h1 class="page-title">Spare Parts</h1>
      <p class="page-sub">Parts catalogue & usage</p>
    </div>
    <?php if ($canManage): ?>
      <div style="display:flex;gap:0.5rem;">
        <button class="btn btn-outline" type="button" @click="usageOpen=true">Record Usage</button>
        <button class="btn btn-primary" type="button" @click="createOpen=true">+ Create Part</button>
      </div>
    <?php endif; ?>
  </div>

  <div class="tabs">
    <a class="tab <?= $tab === 'list' ? 'active' : '' ?>" href="<?= url('spare-parts?tab=list') ?>">Parts List</a>
    <a class="tab <?= $tab === 'stock' ? 'active' : '' ?>" href="<?= url('spare-parts?tab=stock') ?>">Stock View</a>
  </div>

  <form method="get" class="card" style="margin-bottom:1rem;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="form-group" style="margin:0;min-width:160px;"><label>Category</label>
      <select class="form-control" name="category_id">
        <option value="">All</option>
        <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (string)$categoryId === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;"><label>Search</label><input class="form-control" name="search" value="<?= e($search) ?>"></div>
    <button class="btn btn-outline" type="submit">Filter</button>
  </form>

  <div class="card">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Part</th><th>Number</th><th>Category</th><th>Price</th><th>Stock</th><th>Min</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($parts as $p): ?>
          <tr>
            <td><?= e($p['name']) ?></td>
            <td><?= e($p['part_number']) ?></td>
            <td><?= e($p['category_name']) ?></td>
            <td><?= money($p['unit_price']) ?></td>
            <td><?= (int)$p['quantity_in_stock'] ?><?php if ((int)$p['quantity_in_stock'] <= (int)$p['min_stock_level']): ?> <span class="chip chip-warning">Low</span><?php endif; ?></td>
            <td><?= (int)$p['min_stock_level'] ?></td>
            <?php if ($canManage): ?>
            <td style="white-space:nowrap;">
              <button class="btn btn-sm btn-outline" type="button"
                @click='editPart = <?= json_encode($p, JSON_HEX_APOS | JSON_HEX_TAG) ?>; editOpen=true'>Edit</button>
              <form method="post" action="<?= url('spare-parts/' . $p['id'] . '/delete') ?>" style="display:inline;" onsubmit="return confirm('Delete part?')">
                <?= csrf_field() ?><button class="btn btn-sm btn-danger" type="submit">Delete</button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        <?php if (!$parts): ?><tr><td colspan="7" class="muted">No spare parts.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php \App\Core\View::partial('partials/pagination', ['pagination' => $pagination ?? [], 'filters' => $filters ?? []]); ?>
  </div>

  <?php if ($canManage): ?>
  <div class="modal-backdrop" :class="{ open: createOpen }" @click.self="createOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('spare-parts') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Create Part</h3></div>
        <div class="modal-body form-grid">
          <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
          <div class="form-group"><label>Part number</label><input class="form-control" name="part_number" required></div>
          <div class="form-group"><label>Category</label>
            <select class="form-control" name="category_id"><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
          </div>
          <div class="form-group"><label>Unit price</label><input class="form-control" type="number" step="0.01" name="unit_price" required></div>
          <div class="form-group"><label>Stock qty</label><input class="form-control" type="number" name="quantity_in_stock" value="0"></div>
          <div class="form-group"><label>Min level</label><input class="form-control" type="number" name="min_stock_level" value="5"></div>
          <div class="form-group full"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="createOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{ open: editOpen }" @click.self="editOpen=false" x-show="editOpen">
    <div class="modal" x-show="editPart">
      <form method="post" :action="'<?= url('spare-parts') ?>/'+editPart?.id">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Edit Part</h3></div>
        <div class="modal-body form-grid" x-show="editPart">
          <div class="form-group"><label>Name</label><input class="form-control" name="name" :value="editPart?.name" required></div>
          <div class="form-group"><label>Part number</label><input class="form-control" name="part_number" :value="editPart?.part_number" required></div>
          <div class="form-group"><label>Category</label>
            <select class="form-control" name="category_id" :value="editPart?.category_id">
              <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Unit price</label><input class="form-control" type="number" step="0.01" name="unit_price" :value="editPart?.unit_price"></div>
          <div class="form-group"><label>Stock</label><input class="form-control" type="number" name="quantity_in_stock" :value="editPart?.quantity_in_stock"></div>
          <div class="form-group"><label>Min level</label><input class="form-control" type="number" name="min_stock_level" :value="editPart?.min_stock_level"></div>
          <div class="form-group full"><label>Description</label><textarea class="form-control" name="description" x-text="editPart?.description" :value="editPart?.description"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="editOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Update</button></div>
      </form>
    </div>
  </div>

  <div class="modal-backdrop" :class="{ open: usageOpen }" @click.self="usageOpen=false">
    <div class="modal">
      <form method="post" action="<?= url('spare-parts/usage') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Record Usage</h3></div>
        <div class="modal-body">
          <div class="form-group"><label>Part</label>
            <select class="form-control" name="spare_part_id" required>
              <?php foreach ($parts as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name'] . ' (stock ' . $p['quantity_in_stock'] . ')') ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Job card</label>
            <select class="form-control" name="job_card_id">
              <option value="">—</option>
              <?php foreach ($jobCards as $jc): ?><option value="<?= (int)$jc['id'] ?>"><?= e($jc['job_card_number'] . ' / ' . $jc['request_number']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Quantity used</label><input class="form-control" type="number" min="1" name="quantity_used" value="1" required></div>
          <div class="form-group"><label>Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="usageOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Save</button></div>
      </form>
    </div>
  </div>
  <?php endif; ?>
</div>
