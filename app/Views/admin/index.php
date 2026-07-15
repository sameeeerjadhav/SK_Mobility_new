<div x-data="{ userOpen: false, roleEdit: null, editUser: null }">
  <h1 class="page-title">Admin Panel</h1>
  <p class="page-sub">Users, roles & audit logs</p>
  <div class="tabs">
    <a class="tab <?= $tab==='users'?'active':'' ?>" href="<?= url('admin?tab=users') ?>">Users</a>
    <a class="tab <?= $tab==='roles'?'active':'' ?>" href="<?= url('admin?tab=roles') ?>">Roles & Permissions</a>
    <a class="tab <?= $tab==='audit'?'active':'' ?>" href="<?= url('admin?tab=audit') ?>">Audit Logs</a>
    <a class="tab <?= $tab==='settings'?'active':'' ?>" href="<?= url('admin?tab=settings') ?>">Settings</a>
  </div>

  <?php if ($tab === 'roles'): ?>
    <div class="vehicle-grid">
      <?php foreach ($roles as $r): ?>
        <div class="card">
          <h3 style="margin:0 0 0.35rem;"><?= e($r['name']) ?></h3>
          <p class="muted" style="margin:0 0 0.75rem;"><?= e($r['slug']) ?> · <?= count($rolePerms[(int)$r['id']] ?? []) ?> permissions</p>
          <button class="btn btn-outline btn-sm" type="button" @click="roleEdit=<?= (int)$r['id'] ?>">Edit Permissions</button>
          <form method="post" action="<?= url('admin/roles/'.$r['id'].'/permissions') ?>" x-show="roleEdit===<?= (int)$r['id'] ?>" style="margin-top:1rem;">
            <?= csrf_field() ?>
            <?php
              $grouped = [];
              foreach ($permissions as $p) { $grouped[$p['module']][] = $p; }
              $assigned = $rolePerms[(int)$r['id']] ?? [];
            ?>
            <?php foreach ($grouped as $mod => $perms): ?>
              <div style="margin-bottom:0.75rem;">
                <strong style="font-size:0.8rem;text-transform:uppercase;color:#64748b;"><?= e($mod) ?></strong>
                <?php foreach ($perms as $p): ?>
                  <label style="display:block;font-size:0.85rem;margin:0.25rem 0;">
                    <input type="checkbox" name="permissions[]" value="<?= (int)$p['id'] ?>" <?= in_array((int)$p['id'], $assigned, true)?'checked':'' ?>>
                    <?= e($p['name']) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
            <button class="btn btn-primary btn-sm" type="submit">Save</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>

  <?php elseif ($tab === 'audit'): ?>
    <form method="get" class="card" style="margin-bottom:1rem;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:end;">
      <input type="hidden" name="tab" value="audit">
      <div class="form-group" style="margin:0;"><label>Module</label><input class="form-control" name="module" value="<?= e($filterModule) ?>"></div>
      <div class="form-group" style="margin:0;"><label>Action</label>
        <select class="form-control" name="action"><option value="">All</option>
          <?php foreach (['create','update','delete','view','login','logout'] as $a): ?>
            <option value="<?= $a ?>" <?= $filterAction===$a?'selected':'' ?>><?= ucfirst($a) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-outline" type="submit">Filter</button>
    </form>
    <div class="card"><div class="table-wrap"><table class="data">
      <thead><tr><th>User</th><th>Action</th><th>Module</th><th>Entity</th><th>Time</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><?= e(trim(($l['first_name']??'').' '.($l['last_name']??'')) ?: 'System') ?></td>
          <td><span class="chip chip-<?= $l['action']==='delete'?'danger':($l['action']==='create'?'success':'info') ?>"><?= e($l['action']) ?></span></td>
          <td><?= e($l['module']) ?></td>
          <td><?= e(($l['entity_type']??'').' #'.($l['entity_id']??'')) ?></td>
          <td><?= india_datetime($l['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div></div>

  <?php elseif ($tab === 'settings'): ?>
    <div class="card">
      <?php foreach ($settings as $s): ?>
        <form method="post" action="<?= url('admin/settings/'.$s['setting_key']) ?>" style="display:flex;gap:0.75rem;align-items:end;margin-bottom:0.75rem;">
          <?= csrf_field() ?>
          <div class="form-group" style="flex:1;margin:0;">
            <label><?= e($s['setting_key']) ?></label>
            <input class="form-control" name="setting_value" value="<?= e($s['setting_value']) ?>">
          </div>
          <button class="btn btn-primary btn-sm" type="submit">Save</button>
        </form>
      <?php endforeach; ?>
    </div>

  <?php else: ?>
    <div class="toolbar"><div></div><button class="btn btn-primary" type="button" @click="userOpen=true">+ Create User</button></div>
    <div class="card"><div class="table-wrap"><table class="data">
      <thead><tr><th></th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><span class="avatar"><?= e(strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1))) ?></span></td>
          <td><?= e($u['first_name'].' '.$u['last_name']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><span class="chip chip-primary"><?= e($u['role_name']) ?></span></td>
          <td><?= status_chip($u['is_active'] ? 'active' : 'inactive') ?></td>
          <td><?= india_datetime($u['last_login_at']) ?></td>
          <td style="white-space:nowrap;">
            <button class="btn btn-sm btn-outline" type="button" @click='editUser = <?= json_encode([
              "id" => (int)$u["id"],
              "first_name" => $u["first_name"],
              "last_name" => $u["last_name"],
              "phone" => $u["phone"],
              "role_slug" => $u["role_slug"],
              "is_active" => (int)$u["is_active"],
            ], JSON_HEX_APOS | JSON_HEX_TAG) ?>'>Edit</button>
            <form method="post" action="<?= url('admin/users/'.$u['id'].'/toggle') ?>" style="display:inline;">
              <?= csrf_field() ?><button class="btn btn-sm btn-outline" type="submit"><?= $u['is_active']?'Deactivate':'Activate' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div></div>

    <div class="modal-backdrop" :class="{open:userOpen}" @click.self="userOpen=false">
      <div class="modal"><form method="post" action="<?= url('admin/users') ?>">
        <?= csrf_field() ?>
        <div class="modal-header"><h3 class="modal-title">Create User</h3></div>
        <div class="modal-body form-grid">
          <div class="form-group"><label>First name</label><input class="form-control" name="first_name" required></div>
          <div class="form-group"><label>Last name</label><input class="form-control" name="last_name" required></div>
          <div class="form-group"><label>Email</label><input class="form-control" type="email" name="email" required></div>
          <div class="form-group"><label>Phone</label><input class="form-control" name="phone"></div>
          <div class="form-group"><label>Role</label>
            <select class="form-control" name="role_slug"><?php foreach ($roles as $r): ?><option value="<?= e($r['slug']) ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select>
          </div>
          <div class="form-group"><label>Password (optional)</label><input class="form-control" name="password" placeholder="Auto-generated if blank"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline" @click="userOpen=false">Cancel</button><button class="btn btn-primary" type="submit">Create</button></div>
      </form></div>
    </div>

    <div class="modal-backdrop" :class="{open:!!editUser}" @click.self="editUser=null" x-show="editUser" x-cloak>
      <div class="modal" x-show="editUser">
        <form method="post" :action="'<?= url('admin/users') ?>/'+editUser?.id">
          <?= csrf_field() ?>
          <div class="modal-header"><h3 class="modal-title">Edit User</h3></div>
          <div class="modal-body form-grid">
            <div class="form-group"><label>First name</label><input class="form-control" name="first_name" :value="editUser?.first_name" required></div>
            <div class="form-group"><label>Last name</label><input class="form-control" name="last_name" :value="editUser?.last_name" required></div>
            <div class="form-group"><label>Phone</label><input class="form-control" name="phone" :value="editUser?.phone"></div>
            <div class="form-group"><label>New password</label><input class="form-control" type="text" name="password" placeholder="Leave blank to keep current" minlength="6" autocomplete="off"></div>
            <div class="form-group"><label>Role</label>
              <select class="form-control" name="role_slug" :value="editUser?.role_slug">
                <?php foreach ($roles as $r): ?><option value="<?= e($r['slug']) ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label>Active</label>
              <select class="form-control" name="is_active" :value="String(editUser?.is_active)">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
              </select>
            </div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-outline" @click="editUser=null">Cancel</button><button class="btn btn-primary" type="submit">Update</button></div>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>
