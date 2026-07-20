<?php
/** @var array{page:int,total_pages:int,total:int,from?:int,to?:int,per_page?:int} $pagination */
/** @var array<string, mixed> $filters */
$pagination = $pagination ?? [];
$filters = $filters ?? [];
$page = (int)($pagination['page'] ?? 1);
$totalPages = (int)($pagination['total_pages'] ?? 1);
$total = (int)($pagination['total'] ?? 0);
$from = (int)($pagination['from'] ?? 0);
$to = (int)($pagination['to'] ?? 0);
if ($total <= 0 && $totalPages <= 1) {
    return;
}
?>
<nav class="pagination" aria-label="Pagination">
  <p class="pagination-meta">
    <?php if ($total > 0): ?>
      Showing <?= (int)$from ?>–<?= (int)$to ?> of <?= (int)$total ?>
    <?php else: ?>
      No results
    <?php endif; ?>
  </p>
  <?php if ($totalPages > 1): ?>
    <div class="pagination-links">
      <?php if ($page > 1): ?>
        <a class="btn btn-sm btn-outline" href="<?= e(pagination_qs($filters, $page - 1)) ?>">Prev</a>
      <?php endif; ?>
      <?php
        $window = 2;
        $start = max(1, $page - $window);
        $end = min($totalPages, $page + $window);
        if ($start > 1) {
            echo '<a class="btn btn-sm btn-outline" href="' . e(pagination_qs($filters, 1)) . '">1</a>';
            if ($start > 2) {
                echo '<span class="pagination-ellipsis">…</span>';
            }
        }
        for ($i = $start; $i <= $end; $i++) {
            $cls = $i === $page ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';
            echo '<a class="' . $cls . '" href="' . e(pagination_qs($filters, $i)) . '">' . $i . '</a>';
        }
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                echo '<span class="pagination-ellipsis">…</span>';
            }
            echo '<a class="btn btn-sm btn-outline" href="' . e(pagination_qs($filters, $totalPages)) . '">' . $totalPages . '</a>';
        }
      ?>
      <?php if ($page < $totalPages): ?>
        <a class="btn btn-sm btn-outline" href="<?= e(pagination_qs($filters, $page + 1)) ?>">Next</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</nav>
