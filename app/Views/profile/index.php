<?php $u = user(); ?>
<h1 class="page-title">My Profile</h1>
<p class="page-sub">Manage your account details</p>

<div class="grid-2-eq">
  <div class="card">
    <h3 class="card-title">Personal info</h3>
    <form method="post" action="<?= url('profile') ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group"><label>First name</label><input class="form-control" name="first_name" value="<?= e($u['first_name']) ?>" required></div>
        <div class="form-group"><label>Last name</label><input class="form-control" name="last_name" value="<?= e($u['last_name']) ?>" required></div>
        <div class="form-group full"><label>Email</label><input class="form-control" value="<?= e($u['email']) ?>" disabled></div>
        <div class="form-group full"><label>Phone</label><input class="form-control contact-input" name="phone" type="tel" maxlength="11" inputmode="numeric" placeholder="98765 43210" value="<?= e(format_phone($u['phone'] ?? '')) ?>"></div>
        <div class="form-group full"><label>Role</label><input class="form-control" value="<?= e($u['role_name'] ?? $u['role_slug']) ?>" disabled></div>
      </div>
      <button class="btn btn-primary" type="submit">Save changes</button>
    </form>
  </div>

  <div>
    <div class="card">
      <h3 class="card-title">Change password</h3>
      <form method="post" action="<?= url('profile/password') ?>">
        <?= csrf_field() ?>
        <div class="form-group"><label>Current password</label><input class="form-control" type="password" name="current_password" required></div>
        <div class="form-group"><label>New password</label><input class="form-control" type="password" name="new_password" required></div>
        <div class="form-group"><label>Confirm new password</label><input class="form-control" type="password" name="confirm_password" required></div>
        <button class="btn btn-primary" type="submit">Update password</button>
      </form>
    </div>

    <?php if (!empty($dealer)): ?>
    <div class="card" style="margin-top:1rem;">
      <h3 class="card-title">Dealer info</h3>
      <p><strong><?= e($dealer['business_name']) ?></strong></p>
      <p>Code: <?= e($dealer['dealer_code'] ?? '—') ?></p>
      <p>Status: <?= status_chip($dealer['status']) ?></p>
      <p>Orders: <?= (int)$dealer['total_orders'] ?> · Revenue: <?= money($dealer['total_revenue']) ?></p>
    </div>
    <?php endif; ?>
  </div>
</div>
