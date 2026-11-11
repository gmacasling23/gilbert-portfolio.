<?php
session_start();
require_once __DIR__ . '/../db.php';

/* ── Admin Guard ── */
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['username'] ?? 'Admin');
$today     = date('l, F j, Y');

/* ═══════════════════════════════════════
   PRICE HELPER  (estimates per commission style)
═══════════════════════════════════════ */
function estimatePrice(string $style): int {
    $s = strtolower(trim($style));

    // Match most-specific style keys first (longest/most descriptive first)
    // Prices in Philippine Peso (₱) matching commission.html
    $map = [
        'whole body detailed anime chibi' => 350,
        'whole-body-chibi'                => 350,
        'chibi with additional'           => 1000, // "50 PHP / 30 USD" — using midpoint ₱1000 as estimate; varies
        'chibi-additional'                => 1000,
        'chibi additional'                => 1000,
        'chibi eye banner'                => 50,
        'eye-banner'                      => 50,
        'eye banner'                      => 50,
        'icon-chibi'                      => 50,
        'icon chibi'                      => 50,
        'bowls chibi'                     => 200,  // same tier as standard Chibi (200 PHP / 8 USD)
        'bowls-chibi'                     => 200,
        'detailed background'             => 1250, // midpoint of 500–2000 PHP range
        'background'                      => 1250,
        'anime'                           => 300,  // midpoint of 200–400 PHP range
        'chibi'                           => 200,  // base chibi: 200 PHP / 10 USD
    ];

    foreach ($map as $k => $v) {
        if (strpos($s, $k) !== false) return $v;
    }
    return 200; // default fallback (base chibi price)
}

/* ═══════════════════════════════════════
   1. SUMMARY STATS
═══════════════════════════════════════ */
$totalOrders     = 0;
$totalUsers      = 0;
$pendingOrders   = 0;
$completedOrders = 0;
$totalRevenue    = 0;
$todayOrders     = 0;

$r = $conn->query("SELECT COUNT(*) FROM orders");
if ($r) $totalOrders = (int)$r->fetch_row()[0];

$r = $conn->query("SELECT COUNT(*) FROM users WHERE is_admin = 0");
if ($r) $totalUsers = (int)$r->fetch_row()[0];

$r = $conn->query("SELECT COUNT(*) FROM orders WHERE LOWER(status) = 'pending'");
if ($r) $pendingOrders = (int)$r->fetch_row()[0];

$r = $conn->query("SELECT COUNT(*) FROM orders WHERE LOWER(status) = 'completed'");
if ($r) $completedOrders = (int)$r->fetch_row()[0];

$r = $conn->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
if ($r) $todayOrders = (int)$r->fetch_row()[0];

/* ─ Total revenue from all orders ─ */
$r = $conn->query("SELECT LOWER(IFNULL(JSON_UNQUOTE(JSON_EXTRACT(details,'$.commission.style')),'')) AS style FROM orders");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $totalRevenue += estimatePrice($row['style']);
    }
}

/* ═══════════════════════════════════════
   2. STATUS DISTRIBUTION  (doughnut)
═══════════════════════════════════════ */
$statusLabels = [];
$statusCounts = [];
$r = $conn->query("SELECT status, COUNT(*) cnt FROM orders GROUP BY status ORDER BY cnt DESC");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $statusLabels[] = $row['status'] ?: 'Unknown';
        $statusCounts[] = (int)$row['cnt'];
    }
}

/* ═══════════════════════════════════════
   3. MONTHLY ORDERS + REVENUE  (line chart, last 6 months)
═══════════════════════════════════════ */
$monthData = [];
for ($i = 5; $i >= 0; $i--) {
    $label            = date('M Y', strtotime("-{$i} months"));
    $monthData[$label] = ['orders' => 0, 'revenue' => 0];
}

$r = $conn->query("
    SELECT
        DATE_FORMAT(created_at,'%b %Y') mo,
        LOWER(IFNULL(JSON_UNQUOTE(JSON_EXTRACT(details,'$.commission.style')),'')) style
    FROM orders
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    ORDER BY created_at ASC
");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        if (isset($monthData[$row['mo']])) {
            $monthData[$row['mo']]['orders']++;
            $monthData[$row['mo']]['revenue'] += estimatePrice($row['style']);
        }
    }
}
$monthLabels   = array_keys($monthData);
$monthOrders   = array_column(array_values($monthData), 'orders');
$monthRevenues = array_column(array_values($monthData), 'revenue');

/* ═══════════════════════════════════════
   4. COMMISSION STYLE BREAKDOWN  (bar)
═══════════════════════════════════════ */
$styleLabels = [];
$styleCounts = [];
$r = $conn->query("
    SELECT
        IFNULL(JSON_UNQUOTE(JSON_EXTRACT(details,'$.commission.style')),'Unknown') style,
        COUNT(*) cnt
    FROM orders
    GROUP BY style
    ORDER BY cnt DESC
    LIMIT 7
");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $styleLabels[] = ucwords(strtolower($row['style'] ?: 'Unknown'));
        $styleCounts[] = (int)$row['cnt'];
    }
}

/* ═══════════════════════════════════════
   5. RECENT ORDERS TABLE
═══════════════════════════════════════ */
$recentOrders = [];
$r = $conn->query("
    SELECT
        o.id,
        IFNULL(u.username,'Guest') uname,
        IFNULL(JSON_UNQUOTE(JSON_EXTRACT(o.details,'$.customer.name')),'—') cname,
        IFNULL(JSON_UNQUOTE(JSON_EXTRACT(o.details,'$.commission.style')),'—') style,
        o.status,
        o.created_at
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
    LIMIT 10
");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $recentOrders[] = $row;
    }
}

/* ═══════════════════════════════════════
   6. TOP CUSTOMERS
═══════════════════════════════════════ */
$topCustomers = [];
$r = $conn->query("
    SELECT
        IFNULL(u.username,'Guest') uname,
        COUNT(*) orders,
        GROUP_CONCAT(DISTINCT o.status ORDER BY o.created_at DESC SEPARATOR ', ') statuses
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    GROUP BY o.user_id
    ORDER BY orders DESC
    LIMIT 5
");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $topCustomers[] = $row;
    }
}

/* ═══════════════════════════════════════
   EXTRA STATS
═══════════════════════════════════════ */
$inProgressOrders = 0;
$cancelledOrders  = 0;
$monthOrders_count  = 0;
$monthRevenue_total = 0;

$r = $conn->query("SELECT COUNT(*) FROM orders WHERE LOWER(status) LIKE '%progress%'");
if ($r) $inProgressOrders = (int)$r->fetch_row()[0];

$r = $conn->query("SELECT COUNT(*) FROM orders WHERE LOWER(status) LIKE '%cancel%'");
if ($r) $cancelledOrders = (int)$r->fetch_row()[0];

$r = $conn->query("SELECT COUNT(*) FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
if ($r) $monthOrders_count = (int)$r->fetch_row()[0];

// revenue this month
$r = $conn->query("SELECT LOWER(IFNULL(JSON_UNQUOTE(JSON_EXTRACT(details,'$.commission.style')),'')) AS style FROM orders WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
if ($r) { while ($row = $r->fetch_assoc()) $monthRevenue_total += estimatePrice($row['style']); }

$conn->close();

/* ── JSON encode for JS ── */
$jsMonthLabels   = json_encode($monthLabels,   JSON_HEX_TAG);
$jsMonthOrders   = json_encode($monthOrders,   JSON_HEX_TAG);
$jsMonthRevenues = json_encode($monthRevenues, JSON_HEX_TAG);
$jsStatusLabels  = json_encode($statusLabels,  JSON_HEX_TAG);
$jsStatusCounts  = json_encode($statusCounts,  JSON_HEX_TAG);
$jsStyleLabels   = json_encode($styleLabels,   JSON_HEX_TAG);
$jsStyleCounts   = json_encode($styleCounts,   JSON_HEX_TAG);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard — Yumi the Fleur</title>
  <link rel="icon" type="image/png" href="../Images/logo/Transparent.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&family=Lato:wght@300;400;700&display=swap">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; }
    body {
      font-family: 'Lato', sans-serif;
      font-size: 14px;
      background: #F9F4FF;
      color: #3A1A5A;
      min-height: 100vh;
    }
    a { text-decoration: none; color: inherit; }
    ul { list-style: none; }

    /* ════════════════════════════
       SIDEBAR
    ════════════════════════════ */
    .sidebar {
      width: 240px;
      min-height: 100vh;
      background: #3A1A5A;
      display: flex;
      flex-direction: column;
      position: fixed;
      left: 0; top: 0; bottom: 0;
      z-index: 200;
      overflow-y: auto;
    }
    .sidebar-brand {
      padding: 22px 20px 18px;
      border-bottom: 1px solid rgba(255,255,255,.08);
      display: flex; align-items: center; gap: 11px;
    }
    .sidebar-brand .brand-icon {
      width: 38px; height: 38px; border-radius: 8px;
      background: #b582ff;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .sidebar-brand .brand-icon img { width: 26px; height: 26px; border-radius: 4px; object-fit: cover; }
    .sidebar-brand .brand-text strong { display: block; color: #fff; font-size: 14px; font-weight: 700; line-height: 1.2; font-family: 'Nunito', sans-serif; }
    .sidebar-brand .brand-text span   { display: block; color: rgba(255,255,255,.45); font-size: 11px; margin-top: 1px; }

    .sidebar-nav { flex: 1; padding: 14px 12px; }
    .nav-divider {
      height: 1px;
      background: rgba(255,255,255,.10);
      margin: 8px 8px;
    }
    .nav-item {
      display: flex; align-items: center; justify-content: space-between;
      padding: 9px 12px; border-radius: 7px;
      color: rgba(255,255,255,.68); font-size: 13.5px; font-weight: 400;
      margin-bottom: 1px; cursor: pointer; transition: all .15s;
    }
    .nav-item:hover  { background: rgba(181,130,255,.12); color: #fff; }
    .nav-item.active { background: rgba(181,130,255,.20); color: #fff; font-weight: 600; }
    .nav-item-left   { display: flex; align-items: center; gap: 10px; }
    .nav-item svg    { width: 16px; height: 16px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .nav-badge {
      background: #FFADC7; color: #3A1A5A;
      font-size: 10px; font-weight: 700; min-width: 18px; height: 18px;
      border-radius: 999px; display: flex; align-items: center; justify-content: center;
      padding: 0 5px; font-family: 'Nunito', sans-serif;
    }

    .sidebar-footer {
      padding: 12px; border-top: 1px solid rgba(255,255,255,.08);
    }
    .sidebar-footer .nav-item { color: rgba(255,255,255,.45); }
    .sidebar-footer .nav-item:hover { background: rgba(255,173,199,.12); color: #FFADC7; }

    /* ── Nav section labels ── */
    .nav-section {
      padding: 10px 12px 4px;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 1.2px;
      text-transform: uppercase;
      color: rgba(255,255,255,.28);
      font-family: 'Nunito', sans-serif;
    }

    /* ════════════════════════════
       MAIN
    ════════════════════════════ */
    .main { margin-left: 240px; padding: 30px 28px; min-height: 100vh; display: block; }

    .topbar {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 26px;
    }
    .topbar-left h1  { font-size: 22px; font-weight: 800; color: #3A1A5A; font-family: 'Nunito', sans-serif; }
    .topbar-left p   { font-size: 13px; color: rgba(58,26,90,.55); margin-top: 3px; }
    .topbar-user {
      display: flex; align-items: center; gap: 10px;
      background: #fff; border: 1px solid #ecdeff;
      border-radius: 999px; padding: 7px 16px 7px 8px;
      box-shadow: 0 2px 12px rgba(58,26,90,.08);
    }
    .topbar-user .av {
      width: 30px; height: 30px; border-radius: 50%;
      background: #b582ff;
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-size: 13px; font-weight: 700; flex-shrink: 0;
      font-family: 'Nunito', sans-serif;
    }
    .topbar-user span { font-size: 13px; font-weight: 600; color: #3A1A5A; font-family: 'Nunito', sans-serif; }

    /* ════════════════════════════
       STAT CARDS — colored left border
    ════════════════════════════ */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 14px;
      margin-bottom: 14px;
    }
    .stats-grid-3 {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
      margin-bottom: 22px;
    }
    .stat-card {
      background: #fff;
      border-radius: 12px;
      padding: 20px 20px 18px;
      border-left: 5px solid #ccc;
      box-shadow: 0 2px 12px rgba(58,26,90,.08);
      transition: box-shadow .18s, transform .18s;
    }
    .stat-card:hover { box-shadow: 0 8px 24px rgba(58,26,90,.13); transform: translateY(-1px); }
    .stat-card .num  { font-size: 36px; font-weight: 800; line-height: 1; margin-bottom: 7px; font-family: 'Nunito', sans-serif; }
    .stat-card .lbl  { font-size: 13px; color: rgba(58,26,90,.55); font-weight: 400; }

    /* Border + number color variants */
    .c-orange { border-color: #FFADC7; }  .c-orange .num { color: #c2648a; }
    .c-green  { border-color: #b582ff; }  .c-green  .num { color: #8b52d4; }
    .c-teal   { border-color: #caadff; }  .c-teal   .num { color: #6b3ec0; }
    .c-blue   { border-color: #a78bfa; }  .c-blue   .num { color: #5b21b6; }
    .c-red    { border-color: #e63946; }  .c-red    .num { color: #b52d38; }

    /* ════════════════════════════
       CHART CARDS
    ════════════════════════════ */
    .charts-row   { display: grid; grid-template-columns: 2fr 1.1fr; gap: 16px; margin-bottom: 16px; }
    .charts-row-2 { display: grid; grid-template-columns: 1fr 1fr;   gap: 16px; margin-bottom: 16px; }

    .chart-card {
      background: #fff; border-radius: 12px;
      padding: 20px 20px 16px;
      box-shadow: 0 2px 12px rgba(58,26,90,.08);
    }
    .chart-card-hd { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 16px; }
    .chart-card-hd h3 { font-size: 14px; font-weight: 700; color: #3A1A5A; font-family: 'Nunito', sans-serif; }
    .chart-card-hd p  { font-size: 11.5px; color: rgba(58,26,90,.55); margin-top: 2px; }
    .chart-pill {
      font-size: 10.5px; font-weight: 600;
      background: #F3EAFF; color: #b582ff;
      padding: 3px 10px; border-radius: 999px;
      font-family: 'Nunito', sans-serif;
    }
    .chart-wrap { position: relative; }

    /* ════════════════════════════
       TABLE CARDS
    ════════════════════════════ */
    .tbl-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    .tbl-card {
      background: #fff; border-radius: 12px;
      box-shadow: 0 2px 12px rgba(58,26,90,.08);
      overflow: hidden;
    }
    .tbl-card-hd {
      padding: 16px 20px 12px;
      border-bottom: 1px solid #ecdeff;
      display: flex; align-items: center; justify-content: space-between;
    }
    .tbl-card-hd h3   { font-size: 14px; font-weight: 700; color: #3A1A5A; font-family: 'Nunito', sans-serif; }
    .tbl-card-hd a    { font-size: 12.5px; color: #b582ff; font-weight: 600; }
    .tbl-card-hd a:hover { text-decoration: underline; }
    .tbl-card-hd span { font-size: 12px; color: rgba(58,26,90,.55); }

    table { width: 100%; border-collapse: collapse; }
    thead tr { background: #F3EAFF; }
    th {
      padding: 9px 16px; text-align: left;
      font-size: 10.5px; text-transform: uppercase; letter-spacing: .9px;
      color: rgba(58,26,90,.55); font-weight: 700;
      font-family: 'Nunito', sans-serif;
    }
    td { padding: 11px 16px; font-size: 13px; color: #3A1A5A; border-bottom: 1px solid #ecdeff; vertical-align: middle; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover { background: #faf5ff; }
    .mono { font-family: 'Courier New', monospace; font-size: 11.5px; color: rgba(58,26,90,.55); }

    /* ── Badges ── */
    .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; font-family: 'Nunito', sans-serif; }
    .badge-pending   { background: #fff0f5; color: #c2648a; }
    .badge-progress  { background: #F3EAFF; color: #8b52d4; }
    .badge-completed { background: #e8f5e9; color: #2e7d32; }
    .badge-cancelled { background: #fce4ec; color: #b71c1c; }
    .badge-default   { background: #F3EAFF; color: #b582ff; }

    /* ── Avatar ── */
    .mini-av {
      width: 28px; height: 28px; border-radius: 50%;
      background: #b582ff;
      display: inline-flex; align-items: center; justify-content: center;
      color: #fff; font-size: 11px; font-weight: 700; flex-shrink: 0;
      margin-right: 8px; vertical-align: middle;
      font-family: 'Nunito', sans-serif;
    }

    /* ── Responsive ── */
    @media (max-width: 1200px) {
      .stats-grid   { grid-template-columns: repeat(2,1fr); }
      .stats-grid-3 { grid-template-columns: repeat(2,1fr); }
      .charts-row   { grid-template-columns: 1fr; }
    }
    @media (max-width: 900px) {
      .sidebar { width: 60px; }
      .sidebar-brand .brand-text, .nav-section, .nav-item span, .nav-badge, .nav-divider { display: none; }
      .nav-item { justify-content: center; }
      .nav-item-left { gap: 0; }
      .main { margin-left: 60px; padding: 20px 14px; }
      .charts-row-2 { grid-template-columns: 1fr; }
      .tbl-row      { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
      .sidebar { display: none; }
      .main    { margin-left: 0; padding: 16px; }
      .stats-grid, .stats-grid-3 { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<!-- ════════════════════════════
     SIDEBAR
════════════════════════════ -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">
      <img src="../Images/logo/Transparent.png" alt="Yumi">
    </div>
    <div class="brand-text">
      <strong>Yumi the Fleur</strong>
      <span>Admin Panel</span>
    </div>
  </div>

  <nav class="sidebar-nav">

    <div class="nav-section">Overview</div>

    <a href="index.php" class="nav-item active">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        <span>Dashboard</span>
      </div>
    </a>

    <div class="nav-divider"></div>

    <a href="orders.php" class="nav-item">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="12" y2="16"/></svg>
        <span>Orders</span>
      </div>
      <?php if ($pendingOrders > 0): ?>
        <span class="nav-badge"><?= $pendingOrders ?></span>
      <?php endif; ?>
    </a>

    <a href="customers.php" class="nav-item">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Customers</span>
      </div>
      <?php if ($totalUsers > 0): ?>
        <span class="nav-badge"><?= $totalUsers ?></span>
      <?php endif; ?>
    </a>

    <a href="references.php" class="nav-item">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
        <span>References</span>
      </div>
    </a>

    <div class="nav-divider"></div>

    <div class="nav-section">Site</div>

    <a href="../gallery.html" target="_blank" class="nav-item">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
        <span>Gallery</span>
      </div>
    </a>
    <a href="../commission.html" target="_blank" class="nav-item">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        <span>Commission</span>
      </div>
    </a>
    <a href="../index.html" target="_blank" class="nav-item">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
        <span>View Site</span>
      </div>
    </a>

  </nav>

  <div class="sidebar-footer">
    <a href="logout.php" class="nav-item">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span>Logout</span>
      </div>
    </a>
  </div>
</aside>

<!-- ════════════════════════════
     MAIN CONTENT
════════════════════════════ -->
<main class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div class="topbar-left">
      <h1>Dashboard</h1>
      <p>Welcome back, <?= $adminName ?> &nbsp;·&nbsp; <?= $today ?></p>
    </div>
    <div class="topbar-user">
      <div class="av"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
      <span><?= $adminName ?></span>
    </div>
  </div>

  <!-- ── Row 1: 4 stat cards ── -->
  <div class="stats-grid">
    <div class="stat-card c-orange">
      <div class="num"><?= number_format($pendingOrders) ?></div>
      <div class="lbl">Pending Orders</div>
    </div>
    <div class="stat-card c-green">
      <div class="num"><?= number_format($totalOrders) ?></div>
      <div class="lbl">Total Orders</div>
    </div>
    <div class="stat-card c-teal">
      <div class="num"><?= number_format($completedOrders) ?></div>
      <div class="lbl">Completed Orders</div>
    </div>
    <div class="stat-card c-green">
      <div class="num">₱<?= number_format($totalRevenue) ?></div>
      <div class="lbl">Est. Total Revenue</div>
    </div>
  </div>

  <!-- ── Row 2: 4 stat cards ── -->
  <div class="stats-grid">
    <div class="stat-card c-orange">
      <div class="num"><?= number_format($todayOrders) ?></div>
      <div class="lbl">New Orders Today</div>
    </div>
    <div class="stat-card c-blue">
      <div class="num"><?= number_format($inProgressOrders) ?></div>
      <div class="lbl">In Progress</div>
    </div>
    <div class="stat-card c-red">
      <div class="num"><?= number_format($cancelledOrders) ?></div>
      <div class="lbl">Cancelled Orders</div>
    </div>
    <div class="stat-card c-teal">
      <div class="num"><?= number_format($totalUsers) ?></div>
      <div class="lbl">Registered Customers</div>
    </div>
  </div>

  <!-- ── Row 3: 3 stat cards (this month) ── -->
  <div class="stats-grid-3">
    <div class="stat-card c-green">
      <div class="num"><?= number_format($monthOrders_count) ?></div>
      <div class="lbl">Orders This Month</div>
    </div>
    <div class="stat-card c-teal">
      <div class="num">₱<?= number_format($monthRevenue_total) ?></div>
      <div class="lbl">Revenue This Month</div>
    </div>
    <div class="stat-card c-blue">
      <div class="num"><?= $totalOrders > 0 ? '₱'.number_format(round($totalRevenue / $totalOrders)) : '₱0' ?></div>
      <div class="lbl">Avg. Order Value</div>
    </div>
  </div>

  <!-- ── Charts Row 1: Line + Doughnut ── -->
  <div class="charts-row">
    <div class="chart-card">
      <div class="chart-card-hd">
        <div>
          <h3>Orders &amp; Revenue Overview</h3>
          <p>Last 6 months</p>
        </div>
        <span class="chart-pill">6 months</span>
      </div>
      <div class="chart-wrap" style="height:240px;">
        <canvas id="chartLine"></canvas>
      </div>
    </div>
    <div class="chart-card">
      <div class="chart-card-hd">
        <div>
          <h3>Order Status</h3>
          <p>Current distribution</p>
        </div>
      </div>
      <div class="chart-wrap" style="height:240px;">
        <canvas id="chartDoughnut"></canvas>
      </div>
    </div>
  </div>

  <!-- ── Charts Row 2: Style bar + Monthly revenue bar ── -->
  <div class="charts-row-2">
    <div class="chart-card">
      <div class="chart-card-hd">
        <div>
          <h3>Commission Styles</h3>
          <p>Orders per type</p>
        </div>
      </div>
      <div class="chart-wrap" style="height:220px;">
        <canvas id="chartBar"></canvas>
      </div>
    </div>
    <div class="chart-card">
      <div class="chart-card-hd">
        <div>
          <h3>Monthly Revenue</h3>
          <p>Estimated earnings</p>
        </div>
      </div>
      <div class="chart-wrap" style="height:220px;">
        <canvas id="chartRevenue"></canvas>
      </div>
    </div>
  </div>

  <!-- ── Tables ── -->
  <div class="tbl-row">

    <!-- Recent Orders -->
    <div class="tbl-card">
      <div class="tbl-card-hd">
        <h3>Recent Orders</h3>
        <a href="orders.php">View all →</a>
      </div>
      <table>
        <thead>
          <tr>
            <th>#ID</th>
            <th>Customer</th>
            <th>Style</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentOrders)): ?>
          <tr><td colspan="5" style="text-align:center;color:#6b8c72;padding:24px;">No orders yet.</td></tr>
          <?php else: foreach ($recentOrders as $o): ?>
          <tr>
            <td class="mono">#<?= (int)$o['id'] ?></td>
            <td>
              <strong><?= htmlspecialchars($o['cname']) ?></strong><br>
              <span style="font-size:11px;color:#6b8c72;">@<?= htmlspecialchars($o['uname']) ?></span>
            </td>
            <td><?= htmlspecialchars(ucwords(strtolower($o['style']))) ?></td>
            <td>
              <?php
                $st  = strtolower($o['status'] ?? '');
                $cls = match(true) {
                  str_contains($st,'pending')   => 'badge-pending',
                  str_contains($st,'progress')  => 'badge-progress',
                  str_contains($st,'complet')   => 'badge-completed',
                  str_contains($st,'cancel')    => 'badge-cancelled',
                  default                        => 'badge-default',
                };
              ?>
              <span class="badge <?= $cls ?>"><?= htmlspecialchars($o['status']) ?></span>
            </td>
            <td style="white-space:nowrap;font-size:12px;"><?= htmlspecialchars(date('M j, Y', strtotime($o['created_at']))) ?></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Top Customers -->
    <div class="tbl-card">
      <div class="tbl-card-hd">
        <h3>Top Customers</h3>
        <span>by order count</span>
      </div>
      <table>
        <thead>
          <tr>
            <th>Username</th>
            <th>Orders</th>
            <th>Latest Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($topCustomers)): ?>
          <tr><td colspan="3" style="text-align:center;color:#6b8c72;padding:24px;">No data yet.</td></tr>
          <?php else: foreach ($topCustomers as $c): ?>
          <tr>
            <td>
              <span class="mini-av"><?= strtoupper(substr($c['uname'], 0, 1)) ?></span>
              <?= htmlspecialchars($c['uname']) ?>
            </td>
            <td><strong><?= (int)$c['orders'] ?></strong></td>
            <td>
              <?php
                $st2 = strtolower(explode(', ', $c['statuses'])[0]);
                $c2  = match(true) {
                  str_contains($st2,'pending')  => 'badge-pending',
                  str_contains($st2,'progress') => 'badge-progress',
                  str_contains($st2,'complet')  => 'badge-completed',
                  str_contains($st2,'cancel')   => 'badge-cancelled',
                  default                        => 'badge-default',
                };
              ?>
              <span class="badge <?= $c2 ?>"><?= htmlspecialchars(explode(', ', $c['statuses'])[0]) ?></span>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

  </div><!-- /tbl-row -->

</main>

<!-- ════════════════════════════
     CHART.JS
════════════════════════════ -->
<script>
Chart.defaults.font.family = "'Lato', sans-serif";
Chart.defaults.color       = 'rgba(58,26,90,.55)';

const monthLabels   = <?= $jsMonthLabels ?>;
const monthOrders   = <?= $jsMonthOrders ?>;
const monthRevenues = <?= $jsMonthRevenues ?>;
const statusLabels  = <?= $jsStatusLabels ?>;
const statusCounts  = <?= $jsStatusCounts ?>;
const styleLabels   = <?= $jsStyleLabels ?>;
const styleCounts   = <?= $jsStyleCounts ?>;

const GREEN  = '#b582ff';
const TEAL   = '#caadff';
const ORANGE = '#FFADC7';
const BLUE   = '#a78bfa';
const RED    = '#e63946';
const PALETTE = ['#b582ff', '#FFADC7', '#caadff', '#a78bfa', '#FF99B6', '#8b52d4', '#c084fc'];

/* ── 1. Line Chart ── */
(function(){
  const ctx = document.getElementById('chartLine').getContext('2d');
  const gO = ctx.createLinearGradient(0,0,0,260);
  gO.addColorStop(0,'rgba(181,130,255,.28)'); gO.addColorStop(1,'rgba(181,130,255,.02)');
  const gR = ctx.createLinearGradient(0,0,0,260);
  gR.addColorStop(0,'rgba(255,173,199,.28)'); gR.addColorStop(1,'rgba(255,173,199,.02)');

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: monthLabels,
      datasets: [
        { label:'Orders', data:monthOrders, borderColor:GREEN, backgroundColor:gO, tension:.42, fill:true, pointBackgroundColor:GREEN, pointRadius:4, pointHoverRadius:6, borderWidth:2.5, yAxisID:'yO' },
        { label:'Revenue (₱)', data:monthRevenues, borderColor:ORANGE, backgroundColor:gR, tension:.42, fill:true, pointBackgroundColor:ORANGE, pointRadius:4, pointHoverRadius:6, borderWidth:2.5, yAxisID:'yR' }
      ]
    },
    options: {
      responsive:true, maintainAspectRatio:false,
      interaction:{ mode:'index', intersect:false },
      plugins:{
        legend:{ position:'top', labels:{ usePointStyle:true, pointStyleWidth:10, boxHeight:7, font:{size:12} }},
        tooltip:{ backgroundColor:'#fff', titleColor:'#3A1A5A', bodyColor:'#3A1A5A', borderColor:'#ecdeff', borderWidth:1, padding:10,
          callbacks:{ label:c => c.dataset.label==='Revenue (₱)' ? ` ₱${c.parsed.y}` : ` ${c.parsed.y} orders` }}
      },
      scales:{
        x:{ grid:{ color:'rgba(58,26,90,.06)' }},
        yO:{ position:'left', beginAtZero:true, ticks:{stepSize:1,precision:0}, grid:{ color:'rgba(58,26,90,.06)' }},
        yR:{ position:'right', beginAtZero:true, grid:{ drawOnChartArea:false }, ticks:{ callback:v=>'₱'+v }}
      }
    }
  });
})();

/* ── 2. Doughnut ── */
(function(){
  const ctx = document.getElementById('chartDoughnut').getContext('2d');
  const colors = statusLabels.map(l => {
    const m = l.toLowerCase();
    if (m.includes('pending'))  return '#FFADC7';
    if (m.includes('progress')) return '#a78bfa';
    if (m.includes('complet'))  return '#b582ff';
    if (m.includes('cancel'))   return RED;
    return '#caadff';
  });
  new Chart(ctx, {
    type:'doughnut',
    data:{ labels:statusLabels, datasets:[{ data:statusCounts, backgroundColor:colors, borderWidth:2, borderColor:'#fff', hoverOffset:6 }]},
    options:{
      responsive:true, maintainAspectRatio:false, cutout:'65%',
      plugins:{
        legend:{ position:'bottom', labels:{ usePointStyle:true, pointStyleWidth:10, boxHeight:7, font:{size:12}, padding:14 }},
        tooltip:{ backgroundColor:'#fff', titleColor:'#3A1A5A', bodyColor:'#3A1A5A', borderColor:'#ecdeff', borderWidth:1, padding:10 }
      }
    }
  });
})();

/* ── 3. Horizontal Bar — Styles ── */
(function(){
  const ctx = document.getElementById('chartBar').getContext('2d');
  new Chart(ctx, {
    type:'bar',
    data:{ labels:styleLabels, datasets:[{ label:'Orders', data:styleCounts, backgroundColor:styleLabels.map((_,i)=>PALETTE[i%PALETTE.length]+'CC'), borderColor:styleLabels.map((_,i)=>PALETTE[i%PALETTE.length]), borderWidth:1.5, borderRadius:5 }]},
    options:{
      responsive:true, maintainAspectRatio:false, indexAxis:'y',
      plugins:{ legend:{display:false}, tooltip:{ backgroundColor:'#fff', titleColor:'#3A1A5A', bodyColor:'#3A1A5A', borderColor:'#ecdeff', borderWidth:1, padding:10 }},
      scales:{ x:{ beginAtZero:true, ticks:{precision:0}, grid:{ color:'rgba(58,26,90,.06)' }}, y:{ grid:{display:false} }}
    }
  });
})();

/* ── 4. Bar — Monthly Revenue ── */
(function(){
  const ctx = document.getElementById('chartRevenue').getContext('2d');
  const g = ctx.createLinearGradient(0,0,0,230);
  g.addColorStop(0,'rgba(181,130,255,.85)'); g.addColorStop(1,'rgba(202,173,255,.35)');
  new Chart(ctx, {
    type:'bar',
    data:{ labels:monthLabels, datasets:[{ label:'Revenue (₱)', data:monthRevenues, backgroundColor:g, borderColor:GREEN, borderWidth:1.5, borderRadius:7 }]},
    options:{
      responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{display:false}, tooltip:{ backgroundColor:'#fff', titleColor:'#3A1A5A', bodyColor:'#3A1A5A', borderColor:'#ecdeff', borderWidth:1, padding:10, callbacks:{label:c=>` ₱${c.parsed.y}`} }},
      scales:{ x:{ grid:{ color:'rgba(58,26,90,.06)' }}, y:{ beginAtZero:true, grid:{ color:'rgba(58,26,90,.06)' }, ticks:{callback:v=>'₱'+v} }}
    }
  });
})();
</script>

</body>
</html>
