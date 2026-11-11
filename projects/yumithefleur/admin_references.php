<?php
session_start();
require_once 'db.php';

/* ── Admin Guard ── */
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: admin_login.php');
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
  <link rel="icon" type="image/png" href="Images/logo/Transparent.png">
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

<?php
$adminName = htmlspecialchars($_SESSION['username'] ?? 'Admin');

$pendingCount = 0;
$totalCustomers = 0;

$rp = $conn->query("SELECT COUNT(*) FROM orders WHERE LOWER(status)='pending'");
if ($rp) $pendingCount = (int)$rp->fetch_row()[0];

$rc = $conn->query("SELECT COUNT(*) FROM users WHERE is_admin = 0");
if ($rc) $totalCustomers = (int)$rc->fetch_row()[0];
?>

<!-- Sidebar -->
<aside class="adm-sidebar">
  <div class="adm-sidebar-logo">
    <div class="brand-icon">
      <img src="Images/logo/Transparent.png" alt="Yumi">
    </div>
    <div>
      <div class="brand-name">Yumi the Fleur</div>
      <div class="brand-role">Admin Panel</div>
    </div>
  </div>

  <nav class="adm-nav">

    <a href="admin_dashboard.php">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24">
          <rect x="3" y="3" width="7" height="7" rx="1"/>
          <rect x="14" y="3" width="7" height="7" rx="1"/>
          <rect x="3" y="14" width="7" height="7" rx="1"/>
          <rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
        <span>Dashboard</span>
      </div>
    </a>

    <div class="nav-divider"></div>

    <a href="admin_orders.php">
      <div class="nav-item-left">
        <span>Orders</span>
      </div>
      <?php if ($pendingCount > 0): ?>
        <span class="nav-badge"><?= $pendingCount ?></span>
      <?php endif; ?>
    </a>

    <a href="admin_customers.php">
      <div class="nav-item-left">
        <span>Customers</span>
      </div>
      <?php if ($totalCustomers > 0): ?>
        <span class="nav-badge"><?= $totalCustomers ?></span>
      <?php endif; ?>
    </a>

    <a href="admin_references.php" class="active">
      <div class="nav-item-left">
        <svg viewBox="0 0 24 24">
          <rect x="3" y="3" width="18" height="18" rx="2"/>
          <circle cx="8.5" cy="8.5" r="1.5"/>
          <polyline points="21,15 16,10 5,21"/>
        </svg>
        <span>References</span>
      </div>
    </a>

    <div class="nav-divider"></div>

    <a href="gallery.html" target="_blank">
      <div class="nav-item-left">
        <span>Gallery</span>
      </div>
    </a>

    <a href="commission.html" target="_blank">
      <div class="nav-item-left">
        <span>Commission</span>
      </div>
    </a>

    <a href="index.html" target="_blank">
      <div class="nav-item-left">
        <span>View Site</span>
      </div>
    </a>

  </nav>

  <div class="adm-sidebar-footer">
    <a href="admin_logout.php">
      <span>Logout</span>
    </a>
  </div>
</aside>

<!-- Main -->
<main class="adm-main">

  <div class="adm-topbar">
    <div>
      <h1>References</h1>
      <p>View uploaded client reference images</p>
    </div>

    <div class="adm-admin-chip">
      <div class="avatar">
        <?= strtoupper(substr($adminName,0,1)); ?>
      </div>
      <span><?= $adminName; ?></span>
    </div>
  </div>

<?php while($row = $result->fetch_assoc()): ?>

<?php
$details = json_decode($row['details'], true);

$customerName =
    $details['customer']['name']
    ?? $row['username'];

$references =
    $details['references']
    ?? [];
?>

<div class="adm-table-card">

  <div class="adm-table-hd">
      <h3>Order #<?= $row['id']; ?></h3>
      <span><?= date('M j, Y', strtotime($row['created_at'])); ?></span>
  </div>

  <div style="padding:20px;">

      <p style="margin-bottom:10px;">
          <strong>Client:</strong>
          <?= htmlspecialchars($customerName); ?>
      </p>

      <?php if(!empty($references)): ?>

      <div style="
        display:flex;
        flex-wrap:wrap;
        gap:12px;
      ">

          <?php foreach($references as $image): ?>

          <a href="<?= htmlspecialchars($image); ?>" target="_blank">

              <img
                  src="<?= htmlspecialchars($image); ?>"
                  alt="Reference"
                  style="
                    width:180px;
                    height:180px;
                    object-fit:cover;
                    border-radius:12px;
                    border:2px solid #ecdeff;
                  "
              >

          </a>

          <?php endforeach; ?>

      </div>

      <?php else: ?>

      <p style="color:#777;">
          No reference images uploaded.
      </p>

      <?php endif; ?>

  </div>

</div>

<br>

<?php endwhile; ?>

</main>

</body>
</html>
