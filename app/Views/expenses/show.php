<?php
$isAsset = ($expense['record_type'] ?? 'expenditure') === 'asset';
$hasGst = !empty($expense['gst_applicable']);
$recordedBy = trim(($expense['first_name'] ?? '') . ' ' . ($expense['last_name'] ?? ''));
?>
<style>
.ex-view{max-width:900px}
.ex-view-back{display:inline-block;font-size:.84rem;font-weight:600;color:#64748b;margin:0 0 .85rem}
.ex-view-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap}
.ex-view-head h1{margin:0;font-size:1.35rem;font-weight:800;letter-spacing:-.03em}
.ex-view-meta{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;margin-top:.4rem}
.ex-view-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:.85rem;align-items:start}
.ex-view .card{padding:1rem;margin:0}
.ex-view .card:hover{box-shadow:none;transform:none}
.ex-rows{display:grid;gap:.55rem}
.ex-row{display:grid;grid-template-columns:8.5rem minmax(0,1fr);gap:.65rem;font-size:.9rem;line-height:1.4}
.ex-row .k{color:#64748b;font-weight:600;font-size:.82rem}
.ex-row .v{font-weight:600;color:#0f172a}
.ex-money{display:grid;grid-template-columns:repeat(<?= $hasGst ? 4 : 2 ?>,1fr);gap:.55rem;margin-top:.25rem}
.ex-money>div{display:flex;flex-direction:column;gap:.15rem;padding:.65rem .75rem;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc}
.ex-money .k{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
.ex-money .v{font-size:1rem;font-weight:800}
.ex-money .total{background:#f0fdfa;border-color:#99f6e4}
.ex-money .total .v{color:#0f766e;font-size:1.1rem}
.ex-receipt{margin-top:.75rem;padding:.85rem;border:1px dashed #cbd5e1;border-radius:10px;background:#fff}
@media(max-width:800px){.ex-view-grid{grid-template-columns:1fr}.ex-money{grid-template-columns:1fr 1fr}.ex-row{grid-template-columns:6.5rem 1fr}}
</style>

<div class="ex-view">
  <a class="ex-view-back" href="<?= url('expenses') ?>">&larr; Back to expenses</a>

  <div class="card" style="margin-bottom:.85rem;">
    <div class="ex-view-head">
      <div>
        <h1><?= e($expense['name'] ?: 'Expense #' . (int)$expense['id']) ?></h1>
        <div class="ex-view-meta">
          <?php if ($isAsset): ?>
            <span class="chip chip-info">Asset</span>
          <?php else: ?>
            <span class="chip chip-warning">Expenditure</span>
          <?php endif; ?>
          <span class="chip chip-primary"><?= e($expense['category_name']) ?></span>
          <span class="muted"><?= india_date($expense['expense_date']) ?></span>
        </div>
      </div>
      <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
        <a class="btn btn-outline btn-sm" href="<?= url('expenses?edit=' . (int)$expense['id']) ?>">Edit</a>
        <form method="post" action="<?= url('expenses/' . $expense['id'] . '/delete') ?>" onsubmit="return confirm('Delete this record?')">
          <?= csrf_field() ?>
          <button class="btn btn-danger btn-sm" type="submit">Delete</button>
        </form>
      </div>
    </div>
  </div>

  <div class="ex-view-grid">
    <div class="card">
      <h3 class="card-title">Items in this record</h3>
      <?php if (!empty($items) && count($items) > 0): ?>
        <div class="table-wrap" style="margin-bottom:0.75rem;">
          <table class="data">
            <thead>
              <tr>
                <th>#</th>
                <th>Item</th>
                <th style="text-align:right;">Base amount</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $i => $item): ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><strong><?= e($item['name']) ?></strong></td>
                  <td style="text-align:right;"><?= money($item['amount']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <div class="ex-money">
        <div>
          <span class="k">Base total</span>
          <span class="v"><?= money($expense['amount']) ?></span>
        </div>
        <?php if ($hasGst): ?>
          <div>
            <span class="k">CGST 9%</span>
            <span class="v"><?= money($expense['cgst_amount'] ?? 0) ?></span>
          </div>
          <div>
            <span class="k">SGST 9%</span>
            <span class="v"><?= money($expense['sgst_amount'] ?? 0) ?></span>
          </div>
        <?php endif; ?>
        <div class="total">
          <span class="k">Total payable</span>
          <span class="v"><?= money($total) ?></span>
        </div>
      </div>
      <?php if ($hasGst): ?>
        <p class="muted" style="margin:.75rem 0 0;font-size:.82rem;">GST applied: 9% CGST + 9% SGST on combined base of all items.</p>
      <?php else: ?>
        <p class="muted" style="margin:.75rem 0 0;font-size:.82rem;">No GST on this record.</p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3 class="card-title">Payment &amp; audit</h3>
      <div class="ex-rows">
        <div class="ex-row"><span class="k">Payment mode</span><span class="v"><?= e(ucfirst($expense['payment_mode'])) ?></span></div>
        <div class="ex-row"><span class="k">Record type</span><span class="v"><?= $isAsset ? 'Asset' : 'Expenditure' ?></span></div>
        <div class="ex-row"><span class="k">Category</span><span class="v"><?= e($expense['category_name']) ?></span></div>
        <div class="ex-row"><span class="k">Recorded by</span><span class="v"><?= e($recordedBy ?: '—') ?></span></div>
        <div class="ex-row"><span class="k">Created</span><span class="v"><?= india_datetime($expense['created_at'] ?? null) ?></span></div>
        <div class="ex-row"><span class="k">Record ID</span><span class="v">#<?= (int)$expense['id'] ?></span></div>
      </div>
    </div>
  </div>

  <?php if (!empty($expense['description'])): ?>
  <div class="card" style="margin-top:.85rem;">
    <h3 class="card-title">Notes</h3>
    <p style="margin:0;white-space:pre-wrap;line-height:1.55;"><?= e($expense['description']) ?></p>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-top:.85rem;">
    <h3 class="card-title">Receipt</h3>
    <?php if (!empty($expense['receipt_url'])): ?>
      <div class="ex-receipt">
        <?php
          $receiptUrl = asset($expense['receipt_url']);
          $ext = strtolower(pathinfo($expense['receipt_url'], PATHINFO_EXTENSION));
        ?>
        <?php if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)): ?>
          <a href="<?= $receiptUrl ?>" target="_blank" rel="noopener">
            <img src="<?= $receiptUrl ?>" alt="Receipt" style="max-width:100%;max-height:420px;border-radius:8px;display:block;">
          </a>
        <?php else: ?>
          <p style="margin:0 0 .5rem;">PDF or document attached.</p>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline" href="<?= $receiptUrl ?>" target="_blank" rel="noopener" style="margin-top:.5rem;">Open receipt</a>
      </div>
    <?php else: ?>
      <p class="muted" style="margin:0;">No receipt uploaded.</p>
    <?php endif; ?>
  </div>
</div>
