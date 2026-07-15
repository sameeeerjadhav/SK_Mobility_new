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
        ['label' => 'Inventory', 'href' => '/inventory', 'perm' => 'view_inventory'],
    ]];
    $nav[] = ['section' => 'Field Ops', 'items' => [
        ['label' => 'Leads', 'href' => '/leads', 'perm' => 'view_leads'],
        ['label' => 'Services', 'href' => '/services', 'perm' => 'view_services'],
        ['label' => 'Spare Parts', 'href' => '/spare-parts', 'perm' => 'view_spare_parts'],
    ]];
    $nav[] = ['section' => 'Finance', 'items' => [
        ['label' => 'Billing', 'href' => '/billing', 'perm' => 'view_billing'],
        ['label' => 'HR', 'href' => '/hr'],
        ['label' => 'Partners', 'href' => '/partners'],
        ['label' => 'Expenses', 'href' => '/expenses'],
        ['label' => 'Finance', 'href' => '/finance'],
    ]];
    $nav[] = ['section' => 'System', 'items' => [
        ['label' => 'Reports', 'href' => '/reports', 'perm' => 'view_reports'],
        ['label' => 'Admin Panel', 'href' => '/admin'],
    ]];
} elseif ($role === 'dealer') {
    $nav[] = ['section' => 'Operations', 'items' => [
        ['label' => 'Vehicles', 'href' => '/vehicles', 'perm' => 'view_vehicles'],
        ['label' => 'Orders', 'href' => '/orders', 'perm' => 'view_orders'],
        ['label' => 'Payments', 'href' => '/payments', 'perm' => 'view_payments'],
        ['label' => 'Leads', 'href' => '/leads', 'perm' => 'view_leads'],
        ['label' => 'Services', 'href' => '/services', 'perm' => 'view_services'],
    ]];
} elseif ($role === 'service') {
    $nav[] = ['section' => 'Field Ops', 'items' => [
        ['label' => 'Services', 'href' => '/services', 'perm' => 'view_services'],
        ['label' => 'Spare Parts', 'href' => '/spare-parts', 'perm' => 'view_spare_parts'],
    ]];
} elseif ($role === 'accountant') {
    $nav[] = ['section' => 'Finance', 'items' => [
        ['label' => 'Payments', 'href' => '/payments', 'perm' => 'view_payments'],
        ['label' => 'Billing', 'href' => '/billing', 'perm' => 'view_billing'],
        ['label' => 'Reports', 'href' => '/reports', 'perm' => 'view_reports'],
    ]];
}

$nav[] = ['section' => 'User', 'items' => [
    ['label' => 'Notifications', 'href' => '/notifications'],
    ['label' => 'Profile', 'href' => '/profile'],
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
<div class="app-shell" x-data="{ sidebarOpen: false, searchQ: '', searchResults: [], searchOpen: false, unread: <?= (int)$unread ?> }">
  <aside class="sidebar" :class="{ open: sidebarOpen }">
    <div class="sidebar-brand">SK Mobility</div>
    <nav class="sidebar-nav">
      <?php foreach ($nav as $group): ?>
        <div class="nav-section"><?= e($group['section']) ?></div>
        <?php foreach ($group['items'] as $item):
          if (isset($item['perm']) && !can($item['perm'])) continue;
        ?>
          <a class="nav-link <?= nav_active($item['href'], $current) ? 'active' : '' ?>"
             href="<?= url(ltrim($item['href'], '/')) ?>">
            <?= e($item['label']) ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <a class="nav-link" href="<?= url('logout') ?>" style="margin-top:1rem;color:#dc2626;">Logout</a>
    </nav>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn" type="button" @click="sidebarOpen = !sidebarOpen" style="display:none;" id="menuBtn">☰</button>
      <div class="search-box" @click.outside="searchOpen=false">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg>
        <input type="search" placeholder="Search orders, dealers, vehicles, leads…"
               x-model="searchQ"
               @input.debounce.350ms="
                 if (searchQ.length < 2) { searchResults=[]; searchOpen=false; return; }
                 fetch('<?= url('search') ?>?q=' + encodeURIComponent(searchQ))
                   .then(r => r.json()).then(d => { searchResults = d.results || []; searchOpen = true; });
               ">
        <div x-show="searchOpen" x-cloak
             style="position:absolute;left:0;right:0;top:110%;background:#fff;border:1px solid #f1f5f9;border-radius:12px;box-shadow:0 8px 24px rgba(15,23,42,.08);z-index:50;max-height:320px;overflow:auto;">
          <template x-for="item in searchResults" :key="item.type + item.title + item.url">
            <a :href="item.url" style="display:block;padding:0.7rem 1rem;border-bottom:1px solid #f8fafc;color:inherit;">
              <div style="font-size:0.7rem;font-weight:700;color:#0d9488;text-transform:uppercase;" x-text="item.type"></div>
              <div style="font-weight:600;" x-text="item.title"></div>
              <div class="muted" style="font-size:0.75rem;" x-text="item.meta"></div>
            </a>
          </template>
          <div x-show="searchResults.length===0" class="muted" style="padding:1rem;">No results</div>
        </div>
      </div>
      <div class="topbar-actions">
        <a class="icon-btn" href="<?= url('notifications') ?>" title="Notifications">
          🔔
          <span class="badge-dot" x-show="unread > 0" x-text="unread" x-cloak></span>
        </a>
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
  setInterval(() => {
    fetch('<?= url('notifications/unread-count') ?>')
      .then(r => r.json())
      .then(d => {
        const root = document.querySelector('[x-data]');
        if (root && root._x_dataStack) {
          root._x_dataStack[0].unread = d.count || 0;
        }
      }).catch(() => {});
  }, 60000);
</script>
</body>
</html>
