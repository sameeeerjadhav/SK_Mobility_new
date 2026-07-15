<h1><?= e($order['order_number']) ?></h1>
<p class="muted">Order Confirmation · <?= india_date($order['created_at']) ?></p>
<p><strong>Type:</strong> <?= e(ucfirst($order['order_type'])) ?></p>
<p><strong>Party:</strong> <?= e($order['business_name'] ?? $order['customer_name'] ?? '—') ?></p>
<table>
  <thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
  <tbody>
  <?php foreach ($items as $it): ?>
    <tr>
      <td><?= e($it['vehicle_name'] . ' — ' . $it['variant_name']) ?></td>
      <td><?= (int)$it['quantity'] ?></td>
      <td><?= money($it['unit_price']) ?></td>
      <td><?= money($it['total_price']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p style="margin-top:16px;"><strong>Subtotal:</strong> <?= money($order['subtotal']) ?><br>
<strong>Tax:</strong> <?= money($order['tax_amount']) ?><br>
<strong>Grand Total:</strong> <?= money($order['total_amount']) ?></p>
