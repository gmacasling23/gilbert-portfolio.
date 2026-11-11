<?php
session_start();
require_once __DIR__ . '/../db.php';

/* ── Admin Guard ── */
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: login.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['username'] ?? 'Admin');

/* ═══════════════════════════════════════
   PRICE HELPER  (matches dashboard)
═══════════════════════════════════════ */
function estimatePrice(string $style): int {
    $s   = strtolower(trim($style));
    $map = [
        'full render' => 150, 'full body'  => 120, 'fullbody'  => 120,
        'full'        => 120, 'shaded'     => 100, 'half body' => 70,
        'halfbody'    => 70,  'flat color' => 60,  'flat'      => 60,
        'bust'        => 50,  'chibi'      => 45,  'sketch'    => 30,
    ];
    foreach ($map as $k => $v) {
        if (strpos($s, $k) !== false) return $v;
    }
    return 50;
}

/* ═══════════════════════════════════════
   FILTERS + PAGINATION
═══════════════════════════════════════ */
$search  = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = ["u.is_admin = 0"];
$params = [];
$types  = '';

if ($search !== '') {
    $like     = "%{$search}%";
    $where[]  = "(u.username LIKE ? OR u.email LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

/* ── Total count ── */
$countSQL  = "SELECT COUNT(*) FROM users u $whereSQL";
$stmtCount = $conn->prepare($countSQL);
if ($types) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalRows = (int)$stmtCount->get_result()->fetch_row()[0];
$stmtCount->close();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

/* ── Fetch customers with order stats ── */
$sql = "
    SELECT
        u.id,
        u.username,
        u.email,
        u.created_at,
        COUNT(o.id)                AS total_orders,
        MAX(o.created_at)          AS last_order,
        SUM(CASE WHEN LOWER(o.status) = 'pending'   THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN LOWER(o.status) = 'completed' THEN 1 ELSE 0 END) AS completed_count,
        GROUP_CONCAT(
            IFNULL(JSON_UNQUOTE(JSON_EXTRACT(o.details,'$.commission.style')),'')
            ORDER BY o.created_at DESC
            SEPARATOR '|'
        ) AS styles
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    $whereSQL
    GROUP BY u.id
    ORDER BY total_orders DESC, u.created_at DESC
    LIMIT ? OFFSET ?
";
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes  = $types . 'ii';
$stmt      = $conn->prepare($sql);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── Summary stats ── */
$totalCustomers = 0;
$r = $conn->query("SELECT COUNT(*) FROM users WHERE is_admin = 0");
if ($r) $totalCustomers = (int)$r->fetch_row()[0];

$activeCustomers = 0;
$r = $conn->query("SELECT COUNT(DISTINCT user_id) FROM orders WHERE user_id IS NOT NULL");
if ($r) $activeCustomers = (int)$r->fetch_row()[0];

$newThisMonth = 0;
$r = $conn->query("SELECT COUNT(*) FROM users WHERE is_admin = 0 AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())");
if ($r) $newThisMonth = (int)$r->fetch_row()[0];

/* ── Nav badge: pending orders count ── */
$pendingCount = 0;
$rp = $conn->query("SELECT COUNT(*) FROM orders WHERE LOWER(status) = 'pending'");
if ($rp) $pendingCount = (int)$rp->fetch_row()[0];

$conn->close();

/* ── Estimate total spend for a customer's styles string ── */
function calcSpend(?string $stylesStr): int {
    if (!$stylesStr) return 0;
    $total = 0;
    foreach (explode('|', $stylesStr) as $s) {
        $total += estimatePrice($s);
    }
    return $total;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Customers — Admin</title>
  <link rel="icon" type="image/png" href="../Images/logo/Transparent.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&family=Lato:wght@300;400;700&display=swap">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; }
    body { min-height: 100vh; background: #F9F4FF; font-family: 'Lato', sans-serif; font-size: 14px; color: #3A1A5A; }
    a { text-decoration: none; color: inherit; }

    /* ════════════════════ SIDEBAR ════════════════════ */
    .adm-sidebar {
      width: 240px; min-height: 100vh; background: #3A1A5A;
      display: flex; flex-direction: column; position: fixed;
      left: 0; top: 0; bottom: 0; z-index: 200; overflow-y: auto;
    }
    .adm-sidebar-logo {
      padding: 22px 20px 18px; border-bottom: 1px solid rgba(255,255,255,.08);
      display: flex; align-items: center; gap: 11px;
    }
    .adm-sidebar-logo .brand-icon { width: 38px; height: 38px; border-radius: 8px; background: #b582ff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .adm-sidebar-logo .brand-icon img { width: 26px; height: 26px; border-radius: 4px; object-fit: cover; }
    .brand-name { color: #fff; font-size: 14px; font-weight: 700; line-height: 1.2; font-family: 'Nunito', sans-serif; }
    .brand-role { color: rgba(255,255,255,.45); font-size: 11px; margin-top: 1px; }
    .adm-nav { flex: 1; padding: 14px 12px; }
    .nav-section { padding: 10px 12px 4px; font-size: 10px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; color: rgba(255,255,255,.28); font-family: 'Nunito', sans-serif; }
    .nav-divider { height: 1px; background: rgba(255,255,255,.10); margin: 8px 8px; }
    .adm-nav a {
      display: flex; align-items: center; justify-content: space-between;
      padding: 9px 12px; border-radius: 7px;
      color: rgba(255,255,255,.68); font-size: 13.5px; margin-bottom: 1px; transition: all .15s;
    }
    .adm-nav a:hover { background: rgba(181,130,255,.12); color: #fff; }
    .adm-nav a.active { background: rgba(181,130,255,.20); color: #fff; font-weight: 600; }
    .nav-item-left { display: flex; align-items: center; gap: 10px; }
    .adm-nav a svg { width: 16px; height: 16px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
    .nav-badge { background: #FFADC7; color: #3A1A5A; font-size: 10px; font-weight: 700; min-width: 18px; height: 18px; border-radius: 999px; display: flex; align-items: center; justify-content: center; padding: 0 5px; font-family: 'Nunito', sans-serif; }
    .adm-sidebar-footer { padding: 12px; border-top: 1px solid rgba(255,255,255,.08); }
    .adm-sidebar-footer a { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 7px; color: rgba(255,255,255,.45); font-size: 13px; transition: all .15s; }
    .adm-sidebar-footer a:hover { background: rgba(255,173,199,.12); color: #FFADC7; }
    .adm-sidebar-footer a svg { width: 16px; height: 16px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

    /* ════════════════════ MAIN ════════════════════ */
    .adm-main { margin-left: 240px; padding: 28px 28px; min-height: 100vh; }

    /* Topbar */
    .adm-topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 26px; }
    .adm-topbar h1 { font-size: 22px; font-weight: 800; color: #3A1A5A; font-family: 'Nunito', sans-serif; }
    .adm-topbar p  { font-size: 12px; color: rgba(58,26,90,.55); margin-top: 3px; }
    .adm-admin-chip {
      display: flex; align-items: center; gap: 10px;
      background: #fff; border-radius: 999px; padding: 7px 16px 7px 8px;
      box-shadow: 0 2px 12px rgba(58,26,90,.08); border: 1px solid #ecdeff;
    }
    .adm-admin-chip .avatar { width: 30px; height: 30px; border-radius: 50%; background: #b582ff; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 13px; font-family: 'Nunito', sans-serif; }
    .adm-admin-chip span { font-size: 13px; font-weight: 600; color: #3A1A5A; font-family: 'Nunito', sans-serif; }

    /* Stat cards */
    .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 22px; }
    .stat-card { background: #fff; border-radius: 12px; padding: 20px 20px 18px; border-left: 5px solid #ccc; box-shadow: 0 2px 12px rgba(58,26,90,.08); }
    .stat-card .num { font-size: 36px; font-weight: 800; line-height: 1; margin-bottom: 7px; font-family: 'Nunito', sans-serif; }
    .stat-card .lbl { font-size: 13px; color: rgba(58,26,90,.55); }
    .c-purple { border-color: #b582ff; } .c-purple .num { color: #8b52d4; }
    .c-pink   { border-color: #FFADC7; } .c-pink   .num { color: #c2648a; }
    .c-teal   { border-color: #caadff; } .c-teal   .num { color: #6b3ec0; }

    /* Filter bar */
    .filter-bar {
      display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
      background: #fff; border-radius: 12px; padding: 14px 18px;
      box-shadow: 0 2px 12px rgba(58,26,90,.08); border: 1px solid #ecdeff;
      margin-bottom: 18px;
    }
    .filter-bar input[type="text"] {
      flex: 1; min-width: 220px; padding: 8px 14px; border-radius: 7px;
      border: 1.5px solid #ecdeff; font-size: 13px; color: #3A1A5A;
      font-family: 'Lato',sans-serif; background: #faf5ff; outline: none;
    }
    .filter-bar input[type="text"]::placeholder { color: #c9a8f5; }
    .filter-bar input[type="text"]:focus { border-color: #b582ff; box-shadow: 0 0 0 3px rgba(181,130,255,.12); }
    .filter-bar button { padding: 8px 20px; border-radius: 7px; border: none; cursor: pointer; background: #3A1A5A; color: #fff; font-size: 13px; font-weight: 600; font-family: 'Nunito',sans-serif; transition: background .18s; }
    .filter-bar button:hover { background: #b582ff; }
    .filter-bar .btn-reset { background: #F3EAFF; color: #3A1A5A; border: 1px solid #ecdeff; }
    .filter-bar .btn-reset:hover { background: #ecdeff; }

    /* Table card */
    .adm-table-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(58,26,90,.08); border: 1px solid #ecdeff; overflow: hidden; margin-bottom: 18px; }
    .adm-table-hd { padding: 16px 20px 13px; border-bottom: 1px solid #ecdeff; display: flex; align-items: center; justify-content: space-between; }
    .adm-table-hd h3 { font-size: 14px; font-weight: 700; color: #3A1A5A; font-family: 'Nunito', sans-serif; }
    .adm-table-hd span { font-size: 12px; color: rgba(58,26,90,.55); }

    .adm-table { width: 100%; border-collapse: collapse; }
    .adm-table thead tr { background: #F3EAFF; }
    .adm-table th { padding: 9px 14px; text-align: left; font-size: 10.5px; text-transform: uppercase; letter-spacing: .9px; color: rgba(58,26,90,.55); font-weight: 700; font-family: 'Nunito', sans-serif; }
    .adm-table td { padding: 12px 14px; font-size: 13px; color: #3A1A5A; border-bottom: 1px solid #f3eaff; vertical-align: middle; }
    .adm-table tbody tr:last-child td { border-bottom: none; }
    .adm-table tbody tr:hover { background: #faf5ff; }

    /* Avatar */
    .mini-av { width: 30px; height: 30px; border-radius: 50%; background: #b582ff; display: inline-flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; font-weight: 700; flex-shrink: 0; margin-right: 8px; vertical-align: middle; font-family: 'Nunito', sans-serif; }

    /* Badges */
    .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; font-family: 'Nunito', sans-serif; }
    .badge-active   { background: #e8f5e9; color: #2e7d32; }
    .badge-inactive { background: #F3EAFF; color: #8b52d4; }
    .badge-new      { background: #fff0f5; color: #c2648a; }

    .mono { font-size: 12px; color: rgba(58,26,90,.5); font-family: 'Courier New', monospace; }

    /* View link */
    .btn-view { display: inline-block; padding: 4px 12px; border-radius: 6px; font-size: 11.5px; font-weight: 600; background: #F3EAFF; color: #8b52d4; font-family: 'Nunito', sans-serif; transition: background .15s; }
    .btn-view:hover { background: #ecdeff; }

    /* Empty state */
    .empty-state { text-align: center; padding: 56px 20px; color: rgba(58,26,90,.45); }
    .empty-state svg { width: 40px; height: 40px; stroke: #c9a8f5; fill: none; stroke-width: 1.4; margin-bottom: 12px; stroke-linecap: round; stroke-linejoin: round; }
    .empty-state p { font-size: 14px; }

    /* Pagination */
    .pagination { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 16px 0 4px; }
    .pagination a, .pagination span {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 34px; height: 34px; border-radius: 7px; padding: 0 10px;
      font-size: 13px; font-weight: 600; font-family: 'Nunito', sans-serif; transition: all .15s;
    }
    .pagination a { background: #F3EAFF; color: #3A1A5A; }
    .pagination a:hover { background: #b582ff; color: #fff; }
    .pagination span.current { background: #3A1A5A; color: #fff; }
    .pagination span.dots { background: none; color: rgba(58,26,90,.45); }

    /* Responsive */
    @media (max-width: 900px) {
      .adm-sidebar { width: 60px; }
      .brand-name, .brand-role, .nav-section, .adm-nav a span, .nav-badge, .nav-divider { display: none; }
      .adm-nav a { justify-content: center; }
      .nav-item-left { gap: 0; }
      .adm-main { margin-left: 60px; padding: 20px 14px; }
      .stats-row { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 640px) {
      .adm-sidebar { display: none; }
      .adm-main { margin-left: 0; padding: 16px; }
      .stats-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<!-- ════════════════ SIDEBAR ════════════════ -->
<aside class="adm-sidebar">
  <div class="adm-sidebar-logo">
    <div class="brand-icon">
      <img src="../Images/logo/Transparent.png" alt="Yumi">
    </div>
    <div>
      <div class="brand-name">Yumi the Fleur</div>
      <div class="brand-role">Admin Panel</div>
    </div>
  </div>

  <nav class="adm-nav">
    <div class="nav-section">Overview</div>

    <a href="index.php">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        <span>Dashboard</span>
      </div>
    </a>

    <div class="nav-divider"></div>

    <a href="orders.php">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="12" y2="16"/></svg>
        <span>Orders</span>
      </div>
      <?php if ($pendingCount > 0): ?><span class="nav-badge"><?= $pendingCount ?></span><?php endif; ?>
    </a>

    <a href="customers.php" class="active">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Customers</span>
      </div>
      <?php if ($totalCustomers > 0): ?>
        <span class="nav-badge"><?= $totalCustomers ?></span>
      <?php endif; ?>
    </a>
    <a href="references.php">
      <div class="nav-item-left"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg><span>References</span></div>
    </a>

    <div class="nav-divider"></div>
    <div class="nav-section">Site</div>

    <a href="../gallery.html" target="_blank">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
        <span>Gallery</span>
      </div>
    </a>
    <a href="../commission.html" target="_blank">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        <span>Commission</span>
      </div>
    </a>
    <a href="../index.html" target="_blank">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
        <span>View Site</span>
      </div>
    </a>
  </nav>

  <div class="adm-sidebar-footer">
    <a href="logout.php">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span>Logout</span>
    </a>
  </div>
</aside>

<!-- ════════════════ MAIN ════════════════ -->
<main class="adm-main">

  <!-- Topbar -->
  <div class="adm-topbar">
    <div>
      <h1>Customers</h1>
      <p>All registered users and their order activity.</p>
    </div>
    <div class="adm-admin-chip">
      <div class="avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
      <span><?= $adminName ?></span>
    </div>
  </div>

  <!-- Stat cards -->
  <div class="stats-row">
    <div class="stat-card c-purple">
      <div class="num"><?= number_format($totalCustomers) ?></div>
      <div class="lbl">Total Customers</div>
    </div>
    <div class="stat-card c-pink">
      <div class="num"><?= number_format($activeCustomers) ?></div>
      <div class="lbl">Have Placed Orders</div>
    </div>
    <div class="stat-card c-teal">
      <div class="num"><?= number_format($newThisMonth) ?></div>
      <div class="lbl">New This Month</div>
    </div>
  </div>

  <!-- Filter bar -->
  <form method="get" action="">
    <div class="filter-bar">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by username or email…">
      <button type="submit">Search</button>
      <?php if ($search !== ''): ?>
        <a href="customers.php" class="filter-bar"><button type="button" class="btn-reset">Clear</button></a>
      <?php endif; ?>
    </div>
  </form>

  <!-- Customers table -->
  <div class="adm-table-card">
    <div class="adm-table-hd">
      <h3>Customer List</h3>
      <span>
        <?php if ($search !== ''): ?>
          Results for "<?= htmlspecialchars($search) ?>" &nbsp;·&nbsp;
        <?php endif; ?>
        <?= number_format($totalRows) ?> customer<?= $totalRows !== 1 ? 's' : '' ?>
      </span>
    </div>

    <?php if (empty($customers)): ?>
      <div class="empty-state">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        <p>No customers found.</p>
      </div>
    <?php else: ?>
      <table class="adm-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Email</th>
            <th>Joined</th>
            <th>Orders</th>
            <th>Est. Spent</th>
            <th>Last Order</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $c):
            $spend       = calcSpend($c['styles']);
            $joined      = $c['created_at'] ? date('M j, Y', strtotime($c['created_at'])) : '—';
            $lastOrder   = $c['last_order']  ? date('M j, Y', strtotime($c['last_order']))  : 'Never';
            $orderCount  = (int)$c['total_orders'];
            $initial     = strtoupper(substr($c['username'], 0, 1));

            // Status badge logic
            if ($orderCount === 0) {
                $badgeClass = 'badge-inactive';
                $badgeLabel = 'No Orders';
            } elseif ($c['created_at'] && strtotime($c['created_at']) >= strtotime('-30 days')) {
                $badgeClass = 'badge-new';
                $badgeLabel = 'New';
            } else {
                $badgeClass = 'badge-active';
                $badgeLabel = 'Active';
            }
          ?>
          <tr>
            <td class="mono"><?= (int)$c['id'] ?></td>
            <td>
              <span class="mini-av"><?= htmlspecialchars($initial) ?></span>
              <strong><?= htmlspecialchars($c['username']) ?></strong>
            </td>
            <td class="mono"><?= htmlspecialchars($c['email'] ?: '—') ?></td>
            <td><?= $joined ?></td>
            <td><?= $orderCount > 0 ? number_format($orderCount) : '<span style="color:rgba(58,26,90,.35)">0</span>' ?></td>
            <td><?= $spend > 0 ? '$' . number_format($spend) : '<span style="color:rgba(58,26,90,.35)">—</span>' ?></td>
            <td><?= htmlspecialchars($lastOrder) ?></td>
            <td><span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
            <td>
              <a href="orders.php?q=<?= urlencode($c['username']) ?>" class="btn-view">View Orders</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?q=<?= urlencode($search) ?>&page=<?= $page - 1 ?>">&lsaquo;</a>
          <?php endif; ?>

          <?php for ($p = 1; $p <= $totalPages; $p++):
            if ($p === $page): ?>
              <span class="current"><?= $p ?></span>
            <?php elseif ($p <= 2 || $p >= $totalPages - 1 || abs($p - $page) <= 1): ?>
              <a href="?q=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
            <?php elseif (abs($p - $page) === 2): ?>
              <span class="dots">…</span>
            <?php endif;
          endfor; ?>

          <?php if ($page < $totalPages): ?>
            <a href="?q=<?= urlencode($search) ?>&page=<?= $page + 1 ?>">&rsaquo;</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</main>
</body>
</html>
