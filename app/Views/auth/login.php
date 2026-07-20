<div class="login-screen">
  <div class="login-atmosphere" aria-hidden="true">
    <span class="login-orb login-orb-a"></span>
    <span class="login-orb login-orb-b"></span>
    <span class="login-grid"></span>
    <span class="login-road"></span>
  </div>

  <div class="login-shell">
    <section class="login-hero">
      <p class="login-kicker">EV Dealership ERP</p>
      <h1 class="login-brand">SK Mobility</h1>
      <p class="login-tagline">Sell, stock, and settle every scooter from one calm workspace.</p>
      <ul class="login-points">
        <li>Orders &amp; tax invoices</li>
        <li>Inventory &amp; purchase</li>
        <li>Finance ledger</li>
      </ul>
    </section>

    <section class="login-panel">
      <div class="login-panel-inner">
        <h2 class="login-panel-title">Sign in</h2>
        <p class="login-panel-sub">Use your staff or dealer account.</p>

        <?php if ($msg = flash('success')): ?>
          <div class="alert alert-success"><?= e($msg) ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
          <div class="alert alert-error"><?= e($msg) ?></div>
        <?php endif; ?>

        <form class="login-form" method="post" action="<?= url('login') ?>">
          <?= csrf_field() ?>
          <div class="form-group">
            <label for="login-email">Email</label>
            <input id="login-email" class="form-control" type="email" name="email" value="<?= e(old('email')) ?>" required autofocus placeholder="you@company.com" autocomplete="username">
          </div>
          <div class="form-group">
            <label for="login-password">Password</label>
            <input id="login-password" class="form-control" type="password" name="password" required placeholder="Enter your password" autocomplete="current-password">
          </div>
          <button class="btn btn-primary btn-block login-submit" type="submit">Continue</button>
        </form>

        <p class="login-footer">
          New partner?
          <a href="<?= url('dealers/register') ?>">Register as dealer</a>
        </p>
      </div>
    </section>
  </div>
</div>
<?php clear_old(); ?>
