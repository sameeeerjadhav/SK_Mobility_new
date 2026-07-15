<div class="auth-page">
  <div class="auth-card">
    <div class="auth-brand">SK Mobility</div>
    <p class="muted" style="text-align:center;margin-top:0;">EV Dealership Management</p>

    <?php if ($msg = flash('success')): ?>
      <div class="alert alert-success"><?= e($msg) ?></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
      <div class="alert alert-error"><?= e($msg) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= url('login') ?>">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>Email</label>
        <input class="form-control" type="email" name="email" value="<?= e(old('email')) ?>" required autofocus>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input class="form-control" type="password" name="password" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>
    <p class="muted" style="text-align:center;margin-top:1.25rem;font-size:0.9rem;">
      <a href="<?= url('dealers/register') ?>">Register as Dealer</a>
    </p>
    <p class="muted" style="text-align:center;font-size:0.75rem;margin-top:1rem;">
      Default admin: admin@skmobility.com / Admin@123
    </p>
  </div>
</div>
<?php clear_old(); ?>
