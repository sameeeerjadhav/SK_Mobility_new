<style>
.od{max-width:1080px}
.od a.od-back{display:inline-block;font-size:.84rem;font-weight:600;color:#64748b;margin:0 0 .7rem}
.od-bar{display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;margin-bottom:.85rem}
.od-bar h1{margin:0;font-size:1.15rem;font-weight:800;letter-spacing:-.03em;line-height:1.25}
.od-bar .meta{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;margin-top:.3rem;font-size:.8rem;color:#64748b;font-weight:500}
.od-bar .actions{display:flex;gap:.4rem;flex-shrink:0}
.od-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(220px,.8fr);gap:.75rem;align-items:start}
.od .card{padding:.85rem 1rem;margin:0;box-shadow:none}
.od .card:hover{box-shadow:none;transform:none}
.od .card+.card,.od .card{margin-top:0}
.od-col,.od-aside{display:flex;flex-direction:column;gap:.75rem}
.od .card-title{margin:0 0 .55rem;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;font-weight:800}
.od-rows{display:grid;gap:.4rem;margin:0}
.od-row{display:grid;grid-template-columns:6.5rem minmax(0,1fr);gap:.5rem;font-size:.875rem;line-height:1.35}
.od-row .k{color:#64748b;font-weight:600;font-size:.8rem}
.od-row .v{font-weight:600;color:#0f172a}
.od-money{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-top:.75rem;padding-top:.7rem;border-top:1px solid #e2e8f0}
.od-money>div{display:flex;flex-direction:column;gap:.1rem}
.od-money .k{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
.od-money .v{font-size:.92rem;font-weight:800}
.od-money .total .v{color:#0f766e;font-size:1rem}
.od table.data th{padding:.4rem .55rem;background:transparent}
.od table.data td{padding:.45rem .55rem;font-size:.84rem}
.od-hist{list-style:none;margin:0;padding:0}
.od-hist li{padding:.5rem 0;border-bottom:1px solid #e2e8f0}
.od-hist li:first-child{padding-top:0}
.od-hist li:last-child{border-bottom:0;padding-bottom:0}
.od-hist .top{display:flex;justify-content:space-between;align-items:center;gap:.4rem;flex-wrap:wrap}
.od-hist .who{font-size:.75rem;color:#64748b;margin-top:.15rem}
.od-hist .note{font-size:.82rem;margin-top:.2rem;font-weight:500}
.od .form-group{margin-bottom:.55rem}
.od .form-control{padding:.42rem .65rem;border-radius:9px;font-size:.875rem}
.od textarea.form-control{min-height:52px}
@media(max-width:960px){.od-grid{grid-template-columns:1fr}.od-money{grid-template-columns:1fr}.od-row{grid-template-columns:5.5rem 1fr}}
</style>

<div class="od">
  <a class="od-back" href="<?= url('orders') ?>">&larr; Orders</a>

  <div class="od-bar">
    <div>
      <h1><?= e($order['order_number']) ?></h1>
      <div class="meta">
        <?= status_chip($order['status']) ?>
        <span><?= e(ucfirst($order['order_type'])) ?> order</span>
        <span>·</span>
        <span><?= india_datetime($order['created_at'] ?? null) ?></span>
      </div>
    </div>
    <div class="actions">
      <a class="btn btn-sm btn-outline" href="<?= url('orders/' . $order['id'] . '/print') ?>" target="_blank">Print</a>
      <?php if ($bill): ?>
        <a class="btn btn-sm btn-primary" href="<?= url('billing/' . $bill['id'] . '/pdf') ?>" target="_blank">Bill</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="od-grid">
    <div class="od-col">
      <div class="card">
        <h3 class="card-title">Details</h3>
        <div class="od-rows">
          <?php if ($order['order_type'] === 'dealer'): ?>
            <div class="od-row"><span class="k">Dealer</span><span class="v"><?= e($order['business_name'] ?? '—') ?> <span class="muted"><?= e($order['dealer_code'] ?? '') ?></span></span></div>
            <div class="od-row"><span class="k">Delivery</span><span class="v"><?= e($order['delivery_address'] ?: '—') ?></span></div>
          <?php else: ?>
            <div class="od-row"><span class="k">Customer</span><span class="v"><?= e($order['customer_name']) ?> · <?= e($order['customer_phone']) ?></span></div>
            <div class="od-row"><span class="k">Address</span><span class="v"><?= e($order['customer_address'] ?: '—') ?></span></div>
            <div class="od-row"><span class="k">Chassis</span><span class="v"><?= e($order['chassis_no'] ?: '—') ?> / <?= e($order['motor_no'] ?: '—') ?></span></div>
            <div class="od-row"><span class="k">Subsidies</span><span class="v">PM <?= money($order['pm_drive_incentive']) ?> · State <?= money($order['state_subsidy']) ?></span></div>
          <?php endif; ?>
        </div>
        <div class="od-money">
          <div><span class="k">Subtotal</span><span class="v"><?= money($order['subtotal']) ?></span></div>
          <div><span class="k">GST 28%</span><span class="v"><?= money($order['tax_amount']) ?></span></div>
          <div class="total"><span class="k">Total</span><span class="v"><?= money($order['total_amount']) ?></span></div>
        </div>
      </div>

      <div class="card">
        <h3 class="card-title">Items</h3>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Vehicle</th><th>Variant</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td><?= e($it['vehicle_name']) ?></td>
                <td><?= e($it['variant_name']) ?><?= $it['color'] ? ' (' . e($it['color']) . ')' : '' ?></td>
                <td><?= (int)$it['quantity'] ?></td>
                <td><?= money($it['unit_price']) ?></td>
                <td><?= money($it['total_price']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="od-aside">
      <div class="card">
        <h3 class="card-title">Status history</h3>
        <ul class="od-hist">
          <?php foreach ($history as $h): ?>
            <li>
              <div class="top">
                <?= status_chip($h['status']) ?>
                <span class="muted" style="font-size:.78rem;"><?= india_datetime($h['created_at']) ?></span>
              </div>
              <div class="who"><?= e(trim(($h['first_name'] ?? '') . ' ' . ($h['last_name'] ?? ''))) ?></div>
              <?php if ($h['notes']): ?><div class="note"><?= e($h['notes']) ?></div><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <?php if ($canManage): ?>
      <div class="card">
        <h3 class="card-title">Update status</h3>
        <form method="post" action="<?= url('orders/' . $order['id'] . '/status') ?>">
          <?= csrf_field() ?>
          <div class="form-group">
            <label>Status</label>
            <select class="form-control" name="status">
              <?php foreach (['pending','approved','processing','shipped','delivered','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Notes</label>
            <textarea class="form-control" name="notes" rows="2" placeholder="Optional note"></textarea>
          </div>
          <button class="btn btn-sm btn-primary" type="submit">Update</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
