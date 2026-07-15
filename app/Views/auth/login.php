<div class="auth-page">
  <div class="auth-card">
    <div class="auth-brand-mark">SK</div>
    <div class="auth-brand">SK Mobility</div>
    <p class="muted" style="text-align:center;margin:0 0 1.5rem;font-weight:500;">EV Dealership Management Platform</p>

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
        <input class="form-control" type="email" name="email" value="<?= e(old('email')) ?>" required autofocus placeholder="you@company.com">
      </div>
      <div class="form-group">
        <label>Password</label>
        <input class="form-control" type="password" name="password" required placeholder="••••••••">
      </div>
      <button class="btn btn-primary btn-block" type="submit" style="margin-top:0.5rem;padding:0.75rem;">Sign in</button>
    </form>
    <p class="muted" style="text-align:center;margin-top:1.4rem;font-size:0.9rem;font-weight:500;">
      New partner? <a href="<?= url('dealers/register') ?>">Register as Dealer</a>
    </p>
  </div>
</div>
<?php clear_old(); ?>
