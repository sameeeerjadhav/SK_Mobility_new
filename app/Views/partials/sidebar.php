<?php
/** @var array $u */
/** @var string $role */
/** @var string $current */

$icons = [
    'Dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>',
    'Dealers' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'Vehicles' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 17h14v-5l-2-5H7L5 12v5z"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/></svg>',
    'Sell Orders' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/></svg>',
    'Payments' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>',
    'Inventory' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',
    'Purchase Orders' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
    'Leads' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
    'Services' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
    'Spare Parts' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    'Billing' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>',
    'Tax Invoices' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>',
    'HR' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'Partners' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>',
    'Expenses' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
    'Finance' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 16l4-8 4 4 4-8"/></svg>',
    'Reports' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>',
    'Admin Panel' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>',
    'Notifications' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
    'Profile' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
];

$nav = [];
$nav[] = ['section' => 'Overview', 'items' => [
    ['label' => 'Dashboard', 'href' => '/dashboard'],
]];

if ($role === 'super_admin') {
    $nav[] = ['section' => 'Operations', 'items' => [
        ['label' => 'Dealers', 'href' => '/dealers', 'perm' => 'manage_dealers'],
        ['label' => 'Vehicles', 'href' => '/vehicles', 'perm' => 'view_vehicles'],
        ['label' => 'Payments', 'href' => '/payments', 'perm' => 'view_payments'],
        ['label' => 'Inventory', 'href' => '/inventory', 'perm' => 'view_inventory'],
    ]];
    $nav[] = ['section' => 'Orders', 'items' => [
        ['label' => 'Sell Orders', 'href' => '/orders', 'perm' => 'view_orders'],
        ['label' => 'Purchase Orders', 'href' => '/purchase-orders'],
    ]];
    $nav[] = ['section' => 'Field Ops', 'items' => [
        ['label' => 'Leads', 'href' => '/leads', 'perm' => 'view_leads'],
        ['label' => 'Services', 'href' => '/services', 'perm' => 'view_services'],
        ['label' => 'Spare Parts', 'href' => '/spare-parts', 'perm' => 'view_spare_parts'],
    ]];
    $nav[] = ['section' => 'Finance', 'items' => [
        ['label' => 'Tax Invoices', 'href' => '/billing', 'perm' => 'view_billing'],
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
        ['label' => 'Payments', 'href' => '/payments', 'perm' => 'view_payments'],
        ['label' => 'Leads', 'href' => '/leads', 'perm' => 'view_leads'],
        ['label' => 'Services', 'href' => '/services', 'perm' => 'view_services'],
    ]];
    $nav[] = ['section' => 'Orders', 'items' => [
        ['label' => 'Sell Orders', 'href' => '/orders', 'perm' => 'view_orders'],
    ]];
} elseif ($role === 'service') {
    $nav[] = ['section' => 'Field Ops', 'items' => [
        ['label' => 'Services', 'href' => '/services', 'perm' => 'view_services'],
        ['label' => 'Spare Parts', 'href' => '/spare-parts', 'perm' => 'view_spare_parts'],
    ]];
} elseif ($role === 'accountant') {
    $nav[] = ['section' => 'Finance', 'items' => [
        ['label' => 'Payments', 'href' => '/payments', 'perm' => 'view_payments'],
        ['label' => 'Tax Invoices', 'href' => '/billing', 'perm' => 'view_billing'],
        ['label' => 'Reports', 'href' => '/reports', 'perm' => 'view_reports'],
    ]];
}

$nav[] = ['section' => 'User', 'items' => [
    ['label' => 'Notifications', 'href' => '/notifications'],
    ['label' => 'Profile', 'href' => '/profile'],
]];
?>
<aside class="sidebar" :class="{ open: sidebarOpen }" id="appSidebar">
  <div class="sidebar-brand">
    <div class="brand-mark">SK</div>
    <div class="brand-text">SK Mobility<span>EV Dealership ERP</span></div>
  </div>
  <nav class="sidebar-nav" id="sidebarNav">
    <?php foreach ($nav as $group): ?>
      <div class="nav-section"><?= e($group['section']) ?></div>
      <?php foreach ($group['items'] as $item):
        if (isset($item['perm']) && !can($item['perm'])) continue;
      ?>
        <a class="nav-link <?= nav_active($item['href'], $current) ? 'active' : '' ?>"
           href="<?= url(ltrim($item['href'], '/')) ?>"
           @click="sidebarOpen=false">
          <?= $icons[$item['label']] ?? '' ?>
          <?= e($item['label']) ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <a class="nav-link logout" href="<?= url('logout') ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Logout
    </a>
  </nav>
</aside>
