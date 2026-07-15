<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= e($title ?? 'Print') ?></title>
  <style>
    body{font-family:Arial,sans-serif;margin:24px;color:#0f172a;font-size:13px;}
    h1{color:#0d9488;margin:0 0 8px;}
    table{width:100%;border-collapse:collapse;margin-top:16px;}
    th,td{border:1px solid #ddd;padding:8px;text-align:left;}
    th{background:#f0faf8;}
    .muted{color:#64748b;}
    @media print{.no-print{display:none;}}
  </style>
</head>
<body>
  <div class="no-print" style="margin-bottom:12px;">
    <button onclick="window.print()">Print</button>
  </div>
  <?= $content ?>
</body>
</html>
