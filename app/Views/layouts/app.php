<?php
$u = user();
$role = $u['role_slug'] ?? '';
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$base = rtrim((string)env('APP_BASE', ''), '/');
if ($base && str_starts_with($current, $base)) {
    $current = substr($current, strlen($base)) ?: '/';
}
$unread = 0;
try {
    $unread = \App\Services\NotificationService::unreadCount();
} catch (\Throwable $e) {}

$nav = [];
$nav[] = ['section' => 'Overview', 'items' => [
    ['label' => 'Dashboard', 'href' => '/dashboard', 'roles' => null],
]];

if ($role === 'super_admin') {
    $nav[] = ['section' => 'Operations', 'items' => [
        ['label' => 'Dealers', 'href' => '/dealers', 'perm' => 'manage_dealers'],
        ['label' => 'Vehicles', 'href' => '/vehicles', 'perm' => 'view_vehicles'],
        ['label' => 'Orders', 'href' => '/orders', 'perm' => 'view_orders'],
        ['label' => 'Payments', 'href' => '/payments', 'perm' => 'view_payments'],
    ]];
    $nav[] = ['section' => 'Field Ops', 'items' => [
        ['label' => 'Leads', 'href' => '#', 'soon' => true],
        ['label' => 'Services', 'href' => '#', 'soon' => true],
        ['label' => 'Spare Parts', 'href' => '#', 'soon' => true],
    ]];
    $nav[] = ['section' => 'Finance', 'items' => [
        ['label' => 'Billing', 'href' => '/billing', 'perm' => 'view_billing'],
        ['label' => 'HR', 'href' => '#', 'soon' => true],
        ['label' => 'Partners', 'href' => '#', 'soon' => true],
        ['label' => 'Expenses', 'href' => '#', 'soon' => true],
        ['label' => 'Finance', 'href' => '#', 'soon' => true],
    ]];
    $nav[] = ['section' => 'System', 'items' => [
        ['label' => 'Reports', 'href' => '#', 'soon' => true],
        ['label' => 'Admin Panel', 'href' => '#', 'soon' => true],
    ]];
} elseif ($role === 'dealer') {
    $nav[] = ['section' => 'Operations', 'items' => [
        ['label' => 'Vehicles', 'href' => '/vehicles', 'perm' => 'view_vehicles'],
        ['label' => 'Orders', 'href' => '/orders', 'perm' => 'view_orders'],
        ['label' => 'Payments', 'href' => '/payments', 'perm' => 'view_payments'],
        ['label' => 'Leads', 'href' => '#', 'soon' => true],
        ['label' => 'Services', 'href' => '#', 'soon' => true],
    ]];
} elseif ($role === 'service') {
    $nav[] = ['section' => 'Field Ops', 'items' => [
        ['label' => 'Services', 'href' => '#', 'soon' => true],
        ['label' => 'Spare Parts', 'href' => '#', 'soon' => true],
    ]];
} elseif ($role === 'accountant') {
    $nav[] = ['section' => 'Finance', 'items' => [
        ['label' => 'Payments', 'href' => '/payments', 'perm' => 'view_payments'],
        ['label' => 'Billing', 'href' => '/billing', 'perm' => 'view_billing'],
        ['label' => 'Reports', 'href' => '#', 'soon' => true],
    ]];
}

$nav[] = ['section' => 'User', 'items' => [
    ['label' => 'Notifications', 'href' => '#', 'soon' => true],
    ['label' => 'Profile', 'href' => '/profile', 'roles' => null],
]];

function nav_active(string $href, string $current): bool {
    if ($href === '#' || $href === '') return false;
    return $current === $href || str_starts_with($current, $href . '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'SK Mobility') ?> — SK Mobility</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-shell" x-data="{ sidebarOpen: false }">
  <aside class="sidebar" :class="{ open: sidebarOpen }">
    <div class="sidebar-brand">SK Mobility</div>
    <nav class="sidebar-nav">
      <?php foreach ($nav as $group): ?>
        <div class="nav-section"><?= e($group['section']) ?></div>
        <?php foreach ($group['items'] as $item):
          if (isset($item['perm']) && !can($item['perm'])) continue;
        ?>
          <a class="nav-link <?= nav_active($item['href'], $current) ? 'active' : '' ?>"
             href="<?= $item['href'] === '#' ? '#' : url(ltrim($item['href'], '/')) ?>"
             <?= !empty($item['soon']) ? 'title="Coming in next phase" onclick="return false;" style="opacity:.55"' : '' ?>>
            <?= e($item['label']) ?><?= !empty($item['soon']) ? ' · soon' : '' ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <a class="nav-link" href="<?= url('logout') ?>" style="margin-top:1rem;color:#dc2626;">Logout</a>
    </nav>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn" type="button" @click="sidebarOpen = !sidebarOpen" style="display:none;" id="menuBtn">☰</button>
      <div class="search-box">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg>
        <input type="search" placeholder="Search orders, dealers, vehicles… (Phase 6)" disabled>
      </div>
      <div class="topbar-actions">
        <button class="icon-btn" type="button" title="Notifications">
          🔔
          <?php if ($unread > 0): ?><span class="badge-dot"><?= (int)$unread ?></span><?php endif; ?>
        </button>
        <a class="user-pill" href="<?= url('profile') ?>">
          <span class="avatar"><?= e(strtoupper(substr($u['first_name'] ?? 'U', 0, 1) . substr($u['last_name'] ?? '', 0, 1))) ?></span>
          <span style="font-size:0.85rem;font-weight:600;padding-right:0.35rem;"><?= e(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?></span>
        </a>
      </div>
    </header>

    <main class="content">
      <?php if ($msg = flash('success')): ?>
        <div class="alert alert-success"><?= e($msg) ?></div>
      <?php endif; ?>
      <?php if ($msg = flash('error')): ?>
        <div class="alert alert-error"><?= e($msg) ?></div>
      <?php endif; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<script>
  if (window.matchMedia('(max-width: 960px)').matches) {
    document.getElementById('menuBtn').style.display = 'inline-flex';
  }
</script>
</body>
</html>
