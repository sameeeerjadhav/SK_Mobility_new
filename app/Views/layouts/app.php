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
$loadCharts = !empty($loadCharts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'SK Mobility') ?> — SK Mobility</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>?v=<?= @filemtime(BASE_PATH . '/public/assets/css/app.css') ?: 3 ?>">
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
  <?php if ($loadCharts): ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <?php endif; ?>
</head>
<body>
<div class="app-shell" x-data="{ sidebarOpen: false, searchQ: '', searchResults: [], searchOpen: false, unread: <?= (int)$unread ?> }">
  <div class="sidebar-overlay" :class="{ show: sidebarOpen }" @click="sidebarOpen=false"></div>
  <?php \App\Core\View::partial('partials/sidebar', compact('u', 'role', 'current')); ?>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn" type="button" @click="sidebarOpen = !sidebarOpen" id="menuBtn" style="display:none;" aria-label="Menu">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
      </button>
      <div class="search-box" @click.outside="searchOpen=false">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg>
        <input type="search" placeholder="Search sell orders, dealers, vehicles, leads…"
               x-model="searchQ"
               @input.debounce.350ms="
                 if (searchQ.length < 2) { searchResults=[]; searchOpen=false; return; }
                 fetch('<?= url('search') ?>?q=' + encodeURIComponent(searchQ))
                   .then(r => r.json()).then(d => { searchResults = d.results || []; searchOpen = true; });
               ">
        <div class="search-results" x-show="searchOpen" x-cloak>
          <template x-for="item in searchResults" :key="item.type + item.title + item.url">
            <a class="search-result-item" :href="item.url">
              <div class="search-result-type" x-text="item.type"></div>
              <div class="search-result-title" x-text="item.title"></div>
              <div class="muted" style="font-size:0.75rem;" x-text="item.meta"></div>
            </a>
          </template>
          <div x-show="searchResults.length===0" class="muted" style="padding:1rem;">No results</div>
        </div>
      </div>
      <div class="topbar-actions">
        <a class="icon-btn" href="<?= url('notifications') ?>" title="Notifications" aria-label="Notifications">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span class="badge-dot" x-show="unread > 0" x-text="unread" x-cloak></span>
        </a>
        <a class="user-pill" href="<?= url('profile') ?>">
          <span class="avatar"><?= e(strtoupper(substr($u['first_name'] ?? 'U', 0, 1) . substr($u['last_name'] ?? '', 0, 1))) ?></span>
          <span class="user-name"><?= e(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?></span>
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
  const menuBtn = document.getElementById('menuBtn');
  const syncMenu = () => {
    if (window.matchMedia('(max-width: 960px)').matches) {
      menuBtn.style.display = 'inline-flex';
    } else {
      menuBtn.style.display = 'none';
    }
  };
  syncMenu();
  window.addEventListener('resize', syncMenu);

  // Keep sidebar scroll position across page navigations
  (function () {
    const nav = document.getElementById('sidebarNav');
    if (!nav) return;
    const key = 'sk_sidebar_scroll';
    try {
      const saved = sessionStorage.getItem(key);
      if (saved !== null) {
        nav.scrollTop = parseInt(saved, 10) || 0;
      }
    } catch (e) {}

    const persist = () => {
      try { sessionStorage.setItem(key, String(nav.scrollTop)); } catch (e) {}
    };
    nav.addEventListener('scroll', persist, { passive: true });
    document.querySelectorAll('#sidebarNav a.nav-link').forEach((link) => {
      link.addEventListener('click', persist);
    });
  })();

  setInterval(() => {
    fetch('<?= url('notifications/unread-count') ?>')
      .then(r => r.json())
      .then(d => {
        const root = document.querySelector('.app-shell');
        if (root && root._x_dataStack) {
          root._x_dataStack[0].unread = d.count || 0;
        }
      }).catch(() => {});
  }, 60000);

  window.formatAadharValue = function (value) {
    const digits = String(value || '').replace(/\D/g, '').slice(0, 12);
    if (!digits) return '';
    return digits.match(/.{1,4}/g).join(' ');
  };

  const bindAadharInput = (input) => {
    input.addEventListener('input', () => {
      const formatted = window.formatAadharValue(input.value);
      if (input.value !== formatted) input.value = formatted;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    input.value = window.formatAadharValue(input.value);
  };

  document.querySelectorAll('.aadhar-input').forEach(bindAadharInput);
  document.addEventListener('focusin', (e) => {
    if (e.target.matches('.aadhar-input:not([data-aadhar-bound])')) {
      e.target.dataset.aadharBound = '1';
      bindAadharInput(e.target);
    }
  });

  window.formatContactValue = function (value) {
    const digits = String(value || '').replace(/\D/g, '').slice(0, 10);
    if (!digits) return '';
    if (digits.length <= 5) return digits;
    return digits.slice(0, 5) + ' ' + digits.slice(5);
  };

  const bindContactInput = (input) => {
    input.addEventListener('input', () => {
      const formatted = window.formatContactValue(input.value);
      if (input.value !== formatted) input.value = formatted;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    input.value = window.formatContactValue(input.value);
  };

  document.querySelectorAll('.contact-input').forEach(bindContactInput);
  document.addEventListener('focusin', (e) => {
    if (e.target.matches('.contact-input:not([data-contact-bound])')) {
      e.target.dataset.contactBound = '1';
      bindContactInput(e.target);
    }
  });
</script>
</body>
</html>
