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
   HANDLE STATUS UPDATE (POST)
═══════════════════════════════════════ */
$updateMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['new_status'])) {
    $allowed = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
    $newStatus = $_POST['new_status'];
    $orderId   = (int)$_POST['order_id'];

    if (in_array($newStatus, $allowed, true) && $orderId > 0) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $orderId);
        $stmt->execute();
        $stmt->close();
        $updateMsg = 'success';
    }
}

/* ═══════════════════════════════════════
   FILTERS
═══════════════════════════════════════ */
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

$where  = [];
$params = [];
$types  = '';

if ($filterStatus !== '') {
    $where[]  = "o.status = ?";
    $params[] = $filterStatus;
    $types   .= 's';
}
if ($search !== '') {
    $like     = "%{$search}%";
    $where[]  = "(u.username LIKE ? OR JSON_UNQUOTE(JSON_EXTRACT(o.details,'$.customer.name')) LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ── Count for pagination ── */
$countSQL  = "SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.user_id = u.id $whereSQL";
$stmtCount = $conn->prepare($countSQL);
if ($types) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalRows = (int)$stmtCount->get_result()->fetch_row()[0];
$stmtCount->close();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

/* ── Fetch orders ── */
$sql  = "
    SELECT
        o.id,
        IFNULL(u.username,'Guest') uname,
        IFNULL(JSON_UNQUOTE(JSON_EXTRACT(o.details,'$.customer.name')),'—') cname,
        IFNULL(JSON_UNQUOTE(JSON_EXTRACT(o.details,'$.customer.email')),'—') cemail,
        IFNULL(JSON_UNQUOTE(JSON_EXTRACT(o.details,'$.commission.style')),'—') style,
        IFNULL(JSON_UNQUOTE(JSON_EXTRACT(o.details,'$.character.name')),'—') charname,
        o.order_type, o.status, o.created_at
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    $whereSQL
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
";
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes  = $types . 'ii';
$stmt      = $conn->prepare($sql);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── Nav badge counts ── */
$pendingCount   = 0;
$totalCustomers = 0;
$rp = $conn->query("SELECT COUNT(*) FROM orders WHERE LOWER(status) = 'pending'");
if ($rp) $pendingCount = (int)$rp->fetch_row()[0];
$rc = $conn->query("SELECT COUNT(*) FROM users WHERE is_admin = 0");
if ($rc) $totalCustomers = (int)$rc->fetch_row()[0];

$conn->close();

/* ── Status options ── */
$statusOptions = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Orders — Admin</title>
  <link rel="icon" type="image/png" href="../Images/logo/Transparent.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&family=Lato:wght@300;400;700&display=swap">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; }
    body { min-height: 100vh; background: #F9F4FF; font-family: 'Lato', sans-serif; font-size: 14px; color: #3A1A5A; }
    a { text-decoration: none; color: inherit; }

    /* Sidebar */
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
    .adm-sidebar-footer a {
      display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 7px;
      color: rgba(255,255,255,.45); font-size: 13px; transition: all .15s;
    }
    .adm-sidebar-footer a:hover { background: rgba(255,173,199,.12); color: #FFADC7; }
    .adm-sidebar-footer a svg { width: 16px; height: 16px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

    /* Main */
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
    .adm-admin-chip .avatar {
      width: 30px; height: 30px; border-radius: 50%; background: #b582ff;
      display: flex; align-items: center; justify-content: center;
      color: #fff; font-weight: 700; font-size: 13px; flex-shrink: 0;
      font-family: 'Nunito', sans-serif;
    }
    .adm-admin-chip span { font-size: 13px; font-weight: 600; color: #3A1A5A; font-family: 'Nunito', sans-serif; }

    /* Filter bar */
    .filter-bar {
      display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
      background: #fff; border-radius: 12px; padding: 14px 18px;
      box-shadow: 0 2px 12px rgba(58,26,90,.08); border: 1px solid #ecdeff;
      margin-bottom: 18px;
    }
    .filter-bar input[type="text"] {
      flex: 1; min-width: 200px; padding: 8px 14px; border-radius: 7px;
      border: 1.5px solid #ecdeff; font-size: 13px; color: #3A1A5A;
      font-family: 'Lato',sans-serif; background: #faf5ff; outline: none;
    }
    .filter-bar input[type="text"]:focus { border-color: #b582ff; box-shadow: 0 0 0 3px rgba(181,130,255,.12); }
    .filter-bar select {
      padding: 8px 14px; border-radius: 7px;
      border: 1.5px solid #ecdeff; font-size: 13px;
      color: #3A1A5A; font-family: 'Lato',sans-serif; background: #faf5ff; outline: none;
    }
    .filter-bar select:focus { border-color: #b582ff; }
    .filter-bar button {
      padding: 8px 20px; border-radius: 7px; border: none; cursor: pointer;
      background: #3A1A5A; color: #fff; font-size: 13px;
      font-weight: 600; font-family: 'Nunito',sans-serif; transition: background .18s;
    }
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
    .adm-table td { padding: 11px 14px; font-size: 13px; color: #3A1A5A; border-bottom: 1px solid #ecdeff; vertical-align: middle; }
    .adm-table tbody tr:last-child td { border-bottom: none; }
    .adm-table tbody tr:hover { background: #faf5ff; }
    .mono { font-family: 'Courier New',monospace; font-size: 12px; color: rgba(58,26,90,.55); }

    .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; font-family: 'Nunito', sans-serif; }
    .badge-pending   { background: #fff0f5; color: #c2648a; }
    .badge-progress  { background: #F3EAFF; color: #8b52d4; }
    .badge-completed { background: #e8f5e9; color: #2e7d32; }
    .badge-cancelled { background: #fce4ec; color: #b71c1c; }
    .badge-default   { background: #F3EAFF; color: #b582ff; }

    /* Status select inline */
    .status-select {
      padding: 4px 8px; border-radius: 6px; border: 1.5px solid #ecdeff;
      font-size: 12px; font-family: 'Lato',sans-serif; font-weight: 600;
      color: #3A1A5A; background: #faf5ff; cursor: pointer; outline: none;
    }
    .status-select:focus { border-color: #b582ff; }

    .btn-save {
      padding: 4px 12px; border-radius: 6px; border: none; cursor: pointer;
      background: #3A1A5A; color: #fff; font-size: 11.5px;
      font-weight: 700; font-family: 'Nunito',sans-serif; transition: background .18s; margin-left: 6px;
    }
    .btn-save:hover { background: #b582ff; }

    /* Pagination */
    .pagination { display: flex; align-items: center; gap: 6px; justify-content: center; padding: 16px 0; }
    .pagination a, .pagination span {
      display: inline-flex; align-items: center; justify-content: center;
      width: 34px; height: 34px; border-radius: 7px; font-size: 13px; font-weight: 600;
      font-family: 'Nunito',sans-serif; text-decoration: none;
      border: 1px solid #ecdeff; color: #3A1A5A; background: #fff;
      transition: all .18s;
    }
    .pagination a:hover { background: #b582ff; color: #fff; border-color: #b582ff; }
    .pagination .current { background: #3A1A5A; color: #fff; border-color: #3A1A5A; }
    .pagination .disabled { opacity: .35; pointer-events: none; }

    /* Toast */
    .toast {
      position: fixed; bottom: 24px; right: 24px; z-index: 9999;
      background: #fff; border-radius: 10px; padding: 12px 20px;
      box-shadow: 0 8px 32px rgba(58,26,90,.15); border: 1px solid #ecdeff;
      font-size: 13px; font-weight: 600; font-family: 'Nunito',sans-serif;
      color: #2e7d32; display: flex; align-items: center; gap: 8px;
      animation: slideUp .3s ease;
    }
    @keyframes slideUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

    /* Responsive */
    @media (max-width: 900px) {
      .adm-sidebar { width: 60px; }
      .brand-role { display: none; }
      .brand-name { display: none; }
      .adm-nav a span, .adm-sidebar-footer a span { display: none; }
      .nav-item-left { gap: 0; }
      .adm-nav a, .adm-sidebar-footer a { justify-content: center; }
      .adm-main { margin-left: 60px; padding: 20px 14px; }
    }
    @media (max-width: 600px) {
      .adm-sidebar { display: none; }
      .adm-main { margin-left: 0; padding: 16px; }
    }
  </style>
</head>
<body>

<!-- Sidebar -->
<aside class="adm-sidebar">
  <div class="adm-sidebar-logo">
    <div class="brand-icon"><img src="../Images/logo/Transparent.png" alt="Yumi"></div>
    <div>
      <div class="brand-name">Yumi the Fleur</div>
      <div class="brand-role">Admin Panel</div>
    </div>
  </div>
  <nav class="adm-nav">
    <a href="index.php">
      <div class="nav-item-left"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg><span>Dashboard</span></div>
    </a>
    <div class="nav-divider"></div>
    <a href="orders.php" class="active">
      <div class="nav-item-left"><svg viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="12" y2="16"/></svg><span>Orders</span></div>
      <?php if ($pendingCount > 0): ?><span class="nav-badge"><?= $pendingCount ?></span><?php endif; ?>
    </a>
    <a href="customers.php">
      <div class="nav-item-left"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Customers</span></div>
      <?php if ($totalCustomers > 0): ?><span class="nav-badge"><?= $totalCustomers ?></span><?php endif; ?>
    </a>
    <a href="references.php">
      <div class="nav-item-left"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg><span>References</span></div>
    </a>
    <div class="nav-divider"></div>
    <a href="../gallery.html" target="_blank">
      <div class="nav-item-left"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg><span>Gallery</span></div>
    </a>
    <a href="../commission.html" target="_blank">
      <div class="nav-item-left"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg><span>Commission</span></div>
    </a>
    <a href="../index.html" target="_blank">
      <div class="nav-item-left"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg><span>View Site</span></div>
    </a>
  </nav>
  <div class="adm-sidebar-footer">
    <a href="logout.php">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16,17 21,12 16,7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      <span>Logout</span>
    </a>
  </div>
</aside>

<!-- Main -->
<main class="adm-main">

  <div class="adm-topbar">
    <div>
      <h1>Orders</h1>
      <p>Manage and update commission orders</p>
    </div>
    <div class="adm-admin-chip">
      <div class="avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
      <span><?= $adminName ?></span>
    </div>
  </div>

  <!-- Filter bar -->
  <form method="GET" class="filter-bar">
    <input type="text" name="q" placeholder="Search by customer name or username…" value="<?= htmlspecialchars($search) ?>">
    <select name="status">
      <option value="">All Statuses</option>
      <?php foreach ($statusOptions as $s): ?>
        <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Search</button>
    <?php if ($filterStatus || $search): ?>
      <a href="orders.php" class="btn-reset" style="padding:8px 20px;border-radius:7px;border:1px solid #ecdeff;background:#F3EAFF;color:#3A1A5A;font-size:13px;font-weight:600;font-family:'Nunito',sans-serif;cursor:pointer;display:inline-block;">Clear</a>
    <?php endif; ?>
    <span style="margin-left:auto;font-size:12px;color:rgba(58,26,90,.55);"><?= number_format($totalRows) ?> order<?= $totalRows !== 1 ? 's' : '' ?> found</span>
  </form>

  <!-- Orders table -->
  <div class="adm-table-card">
    <div class="adm-table-hd">
      <h3>All Orders</h3>
      <span>Page <?= $page ?> of <?= $totalPages ?></span>
    </div>
    <table class="adm-table">
      <thead>
        <tr>
          <th>#ID</th>
          <th>Customer</th>
          <th>Character</th>
          <th>Style</th>
          <th>Status</th>
          <th>Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr><td colspan="7" style="text-align:center;color:rgba(58,26,90,.55);padding:28px;">No orders found.</td></tr>
        <?php else: foreach ($orders as $o): ?>
        <tr>
          <td class="mono">#<?= (int)$o['id'] ?></td>
          <td>
            <strong><?= htmlspecialchars($o['cname']) ?></strong><br>
            <span style="font-size:11px;color:rgba(58,26,90,.55);"><?= htmlspecialchars($o['cemail']) ?></span><br>
            <span style="font-size:11px;color:rgba(58,26,90,.55);">@<?= htmlspecialchars($o['uname']) ?></span>
          </td>
          <td><?= htmlspecialchars($o['charname']) ?></td>
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
          <td>
            <form method="POST" style="display:flex;align-items:center;gap:4px;">
              <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
              <select name="new_status" class="status-select" onchange="this.closest('form').querySelector('.btn-save').style.display='inline-block'">
                <?php foreach ($statusOptions as $s): ?>
                  <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn-save" style="display:none;">Save</button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php
      $qBase = http_build_query(['q' => $search, 'status' => $filterStatus]);
      $prev = $page > 1 ? $page - 1 : null;
      $next = $page < $totalPages ? $page + 1 : null;
    ?>
    <a href="?<?= $qBase ?>&page=<?= $prev ?? $page ?>" class="<?= $prev ? '' : 'disabled' ?>">&#8249;</a>
    <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
      <a href="?<?= $qBase ?>&page=<?= $p ?>" class="<?= $p === $page ? 'current' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
    <a href="?<?= $qBase ?>&page=<?= $next ?? $page ?>" class="<?= $next ? '' : 'disabled' ?>">&#8250;</a>
  </div>
  <?php endif; ?>

</main>

<?php if ($updateMsg === 'success'): ?>
<div class="toast" id="toast">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>
  Order status updated!
</div>
<script>setTimeout(()=>{ const t=document.getElementById('toast'); if(t) t.remove(); }, 3000);</script>
<?php endif; ?>

</body>
</html>
