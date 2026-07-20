<div class="auth-page">
  <div class="auth-card" style="max-width:560px;">
    <div class="auth-brand-mark">SK</div>
    <div class="auth-brand">Dealer Registration</div>
    <p class="muted" style="text-align:center;margin:0 0 1.25rem;font-weight:500;">Apply to join the SK Mobility dealer network</p>
    <?php if ($msg = flash('error')): ?><div class="alert alert-error"><?= e($msg) ?></div><?php endif; ?>
    <form method="post" action="<?= url('dealers/register') ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group full">
          <label>Business Name *</label>
          <input class="form-control" name="business_name" value="<?= e(old('business_name')) ?>" required>
        </div>
        <div class="form-group">
          <label>Contact Person *</label>
          <input class="form-control" name="contact_person" value="<?= e(old('contact_person')) ?>" required>
        </div>
        <div class="form-group">
          <label>Phone *</label>
          <input class="form-control contact-input" name="phone" type="tel" maxlength="11" inputmode="numeric" placeholder="98765 43210" value="<?= e(format_phone(old('phone'))) ?>" required>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input class="form-control" type="email" name="email" value="<?= e(old('email')) ?>" required>
        </div>
        <div class="form-group">
          <label>GST Number</label>
          <input class="form-control" name="gst_number" value="<?= e(old('gst_number')) ?>">
        </div>
        <div class="form-group">
          <label>PAN</label>
          <input class="form-control" name="pan_number" value="<?= e(old('pan_number')) ?>">
        </div>
        <div class="form-group full">
          <label>Address Line 1</label>
          <input class="form-control" name="address_line1" value="<?= e(old('address_line1')) ?>">
        </div>
        <div class="form-group full">
          <label>Address Line 2</label>
          <input class="form-control" name="address_line2" value="<?= e(old('address_line2')) ?>">
        </div>
        <div class="form-group">
          <label>City</label>
          <input class="form-control" name="city" value="<?= e(old('city')) ?>">
        </div>
        <div class="form-group">
          <label>State</label>
          <input class="form-control" name="state" value="<?= e(old('state')) ?>">
        </div>
        <div class="form-group">
          <label>Pincode</label>
          <input class="form-control" name="pincode" value="<?= e(old('pincode')) ?>">
        </div>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Submit Application</button>
    </form>
    <p class="muted" style="text-align:center;margin-top:1rem;"><a href="<?= url('login') ?>">Back to Login</a></p>
  </div>
</div>
<?php clear_old(); ?>
