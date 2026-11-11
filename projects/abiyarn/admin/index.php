<?php
/* ============================================
   AbiYarn Admin Dashboard
   /admin/index.php
   ============================================ */

require_once __DIR__ . '/../config.php';
session_start();

/* ── Login / Logout ──────────────────────────── */
$error = '';

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $authenticated = false;

    // Primary login: authenticate against the users table and require the admin role.
    try {
        $pdoLogin = db();
        $sql = "
            SELECT u.id, u.username, u.password_hash, r.role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.username = :username
              AND r.role_name IN ('admin', 'owner')
            LIMIT 1
        ";
        $stmt = $pdoLogin->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $authenticated = true;
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['admin_user_id'] = (int)$user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role_name']; // 'admin' or 'owner'
        }
    } catch (Throwable $e) {
        // Fall back to the credentials in config.php during local setup.
    }

    // Backward-compatible login using ADMIN_USER / ADMIN_PASS in config.php.
    if (!$authenticated && hash_equals((string)ADMIN_USER, $username) && hash_equals((string)ADMIN_PASS, $password)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_role'] = 'admin'; // legacy config.php login is never an owner
        $authenticated = true;
    }

    if ($authenticated) {
        header('Location: index.php');
        exit;
    }

    $error = 'Incorrect username or password, or this account does not have the admin role.';
}

// Trust a verified storefront session only after rechecking the role in the database.
// Never authorize access from a client-supplied role or a stale session label.
if (empty($_SESSION['admin']) && !empty($_SESSION['user_id'])) {
    try {
        $authDb = db();
        $authStmt = $authDb->prepare('SELECT u.id, u.username, r.role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id LIMIT 1');
        $authStmt->execute(['id' => (int)$_SESSION['user_id']]);
        $authUser = $authStmt->fetch(PDO::FETCH_ASSOC);
        if ($authUser && in_array($authUser['role_name'], ['admin', 'owner'], true)) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['admin_user_id'] = (int)$authUser['id'];
            $_SESSION['admin_username'] = (string)$authUser['username'];
            $_SESSION['admin_role'] = (string)$authUser['role_name'];
        }
    } catch (Throwable $e) {
        error_log('AbiYarn admin session verification failed: ' . $e->getMessage());
    }
}
$loggedIn = !empty($_SESSION['admin']);
$isOwner  = $loggedIn && (($_SESSION['admin_role'] ?? '') === 'owner');
$msg      = trim((string)($_GET['msg'] ?? ''));
$msgIsError = ($_GET['err'] ?? '') !== '';

/* ── Data (only when logged in) ──────────────── */
if ($loggedIn) {
    $pdo = db();

    /* Filters */
    $oStatus  = (string)($_GET['os'] ?? '');
    $oSearch  = trim((string)($_GET['oq'] ?? ''));
    $oDate    = filterDate((string)($_GET['od'] ?? ''));
    $oPayment = (string)($_GET['op'] ?? '');
    $iStatus  = (string)($_GET['is'] ?? '');
    $iSearch  = trim((string)($_GET['iq'] ?? ''));
    $iDate    = filterDate((string)($_GET['idate'] ?? ''));
    $iType    = trim((string)($_GET['it'] ?? ''));
    $reviewSearch = trim((string)($_GET['rq'] ?? ''));
    $reviewRating = (int)($_GET['rr'] ?? 0);
    if (!in_array($reviewRating, [0, 1, 2, 3, 4, 5], true)) { $reviewRating = 0; }
    $prSearch = trim((string)($_GET['pq'] ?? ''));   // product search
    $prSort   = (string)($_GET['ps'] ?? 'id');       // product sort
    $prCat    = (string)($_GET['pc'] ?? '');         // product category filter
    $prAvail  = (string)($_GET['pa'] ?? '');         // product availability filter
    $aSearch  = trim((string)($_GET['aq'] ?? ''));   // account search
    $tabOptions = ['dashboard','orders','inquiries','reviews','products','analytics','performance'];
    if ($isOwner) { $tabOptions[] = 'administration'; }
    $tab      = in_array($_GET['tab'] ?? '', $tabOptions) ? $_GET['tab'] : 'dashboard';
    $tabTitles = [
        'dashboard'      => 'Dashboard',
        'orders'         => 'Orders',
        'inquiries'      => 'Inquiries',
        'reviews'        => 'Customer Reviews',
        'products'       => 'Product Management',
        'analytics'      => 'Sales Analytics',
        'performance'    => 'Query Performance',
        'administration' => 'Administration',
    ];

    // Read-only warehouse analytics. The main application connection remains abiyarn_db.
    $analyticsError = null;
    $analyticsLocations = $analyticsProducts = $analyticsCustomers = $analyticsStatuses = [];
    // Sidebar navigation is client-side: populate analytics on every authenticated page load.
    // Otherwise visiting Administration first leaves the analytics panel empty until refresh.
    {
        try {
            $analyticsLocations = $pdo->query("SELECT city,postal_code,country,total_orders,completed_orders,completed_revenue FROM abiyarn_dw.vw_location_sales_summary ORDER BY completed_revenue DESC,total_orders DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
            $analyticsProducts = $pdo->query("SELECT product_name,category,units_sold,completed_orders,completed_revenue FROM abiyarn_dw.vw_product_sales_summary ORDER BY units_sold DESC,completed_revenue DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
            $analyticsCustomers = $pdo->query("SELECT full_name,completed_orders,completed_spending FROM abiyarn_dw.vw_customer_spending_summary ORDER BY completed_spending DESC,completed_orders DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
            $analyticsStatuses = $pdo->query("SELECT status_name,total_orders FROM abiyarn_dw.vw_order_status_summary ORDER BY status_key")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('AbiYarn analytics: '.$e->getMessage());
            $analyticsError = 'Sales analytics is unavailable. Check the warehouse import, reporting views, ETL refresh, and read permissions.';
        }
    }

    /* ── Orders & inquiries: validated filters, pagination, grouped stats ──
      Optimisations (see schema2_updated.sql "Query optimisation"):
         • status is validated against the allowed list
         • date filter is a range (created_at >= day AND < next day) so
           idx_status_created / idx_created can be used; DATE(created_at) = ? cannot
         • LIKE wildcards typed by the user (% and _) are escaped
         • only one page (25 rows) is loaded, plus a COUNT(*) for the pager
         • stats come from 4 GROUP BY queries instead of 10 separate ones
         • CSV export streams its own query, so it still exports every match   */
    $perPage         = 25;
    $orderStatuses   = ['pending','processing','completed','cancelled'];
    $inquiryStatuses = ['new','replied','closed'];
    if (!in_array($oStatus, $orderStatuses, true))   { $oStatus = ''; }
    if (!in_array($iStatus, $inquiryStatuses, true)) { $iStatus = ''; }
    $oPage = max(1, (int)($_GET['opg'] ?? 1));
    $iPage = max(1, (int)($_GET['ipg'] ?? 1));

    /* Orders WHERE (also reused by the CSV export) */
    $oWhere  = [];
    $oParams = [];
    if ($oStatus !== '') { $oWhere[] = 'status = :status'; $oParams['status'] = $oStatus; }
    if ($oDate !== '') {
        [$dFrom, $dTo] = dayRange($oDate);
        $oWhere[] = 'created_at >= :d_from AND created_at < :d_to';
        $oParams['d_from'] = $dFrom;
        $oParams['d_to']   = $dTo;
    }
    $paymentValues = [
      'gcash' => ['GCash'],
      'cod'   => ['Cash on Delivery'],
      'card'  => ['Card', 'Credit / Debit Card'],
    ];
    if (isset($paymentValues[$oPayment])) {
      $paymentParams = [];
      foreach ($paymentValues[$oPayment] as $index => $paymentValue) {
        $key = 'payment' . $index;
        $paymentParams[] = ':' . $key;
        $oParams[$key] = $paymentValue;
      }
      $oWhere[] = 'payment IN (' . implode(', ', $paymentParams) . ')';
    } else {
      $oPayment = '';
    }
    if ($oSearch !== '') {
        $like = '%' . likeEscape($oSearch) . '%';
        $oWhere[] = '(name LIKE :q OR email LIKE :q2 OR order_num LIKE :q3)';
        $oParams['q'] = $oParams['q2'] = $oParams['q3'] = $like;
    }
    $oWhereSql = $oWhere ? ' WHERE ' . implode(' AND ', $oWhere) : '';

    /* Inquiries WHERE (also reused by the CSV export) */
    $iWhere  = [];
    $iParams = [];
    if ($iStatus !== '') { $iWhere[] = 'status = :status'; $iParams['status'] = $iStatus; }
    if ($iDate !== '') {
        [$dFrom, $dTo] = dayRange($iDate);
        $iWhere[] = 'created_at >= :d_from AND created_at < :d_to';
        $iParams['d_from'] = $dFrom;
        $iParams['d_to']   = $dTo;
    }
    if ($iType !== '') { $iWhere[] = 'order_type = :order_type'; $iParams['order_type'] = $iType; }
    if ($iSearch !== '') {
        $like = '%' . likeEscape($iSearch) . '%';
        $iWhere[] = '(name LIKE :q OR email LIKE :q2 OR order_type LIKE :q3)';
        $iParams['q'] = $iParams['q2'] = $iParams['q3'] = $like;
    }
    $iWhereSql = $iWhere ? ' WHERE ' . implode(' AND ', $iWhere) : '';

    /* Safe defaults so the page still renders if a query fails */
    $dbError = '';
    $orders = $inquiries = $inquiryTypes = $payRows = $stRows = $typeRows = [];
    $reviews = [];
    $reviewTotal = 0;
    $reviewAverage = 0.0;
    $oTotal = $iTotal = 0;
    $oPages = $iPages = 1;
    $totalOrders = $pendingOrders = $totalInq = $newInq = 0;
    $totalRev = 0.0;
    $avgOrder = 0;

    try {
        /* Orders: count + one page */
        $st = $pdo->prepare('SELECT COUNT(*) FROM orders' . $oWhereSql);
        $st->execute($oParams);
        $oTotal = (int)$st->fetchColumn();
        $oPages = max(1, (int)ceil($oTotal / $perPage));
        $oPage  = min($oPage, $oPages);

        $st = $pdo->prepare(
                'SELECT id, order_num, name, email, phone, address, city, postal, country,
                  payment, gcash_ref, total, status, estimated_delivery, items, notes, created_at
               FROM orders' . $oWhereSql . '
              ORDER BY created_at DESC, id DESC
              LIMIT ' . $perPage . ' OFFSET ' . (($oPage - 1) * $perPage)
        );
        $st->execute($oParams);
        $orders = $st->fetchAll();

        /* Inquiries: count + one page */
        $st = $pdo->prepare('SELECT COUNT(*) FROM inquiries' . $iWhereSql);
        $st->execute($iParams);
        $iTotal = (int)$st->fetchColumn();
        $iPages = max(1, (int)ceil($iTotal / $perPage));
        $iPage  = min($iPage, $iPages);

        $st = $pdo->prepare(
            'SELECT id, name, email, phone, order_type, quantity, colors, needed_by,
                    details, status, created_at
               FROM inquiries' . $iWhereSql . '
              ORDER BY created_at DESC, id DESC
              LIMIT ' . $perPage . ' OFFSET ' . (($iPage - 1) * $perPage)
        );
        $st->execute($iParams);
        $inquiries = $st->fetchAll();

        /* Stats + analytics: one GROUP BY each (served by the new indexes) */
        $stRows = $pdo->query('SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS rev FROM orders GROUP BY status')->fetchAll();
        foreach ($stRows as $row) {
            $totalOrders += (int)$row['cnt'];
            $totalRev    += (float)$row['rev'];
            if ($row['status'] === 'pending') { $pendingOrders = (int)$row['cnt']; }
        }
        $avgOrder = $totalOrders ? round($totalRev / $totalOrders, 2) : 0;
        $payRows  = $pdo->query('SELECT payment, COUNT(*) AS cnt FROM orders GROUP BY payment')->fetchAll();

        foreach ($pdo->query('SELECT status, COUNT(*) AS cnt FROM inquiries GROUP BY status')->fetchAll() as $row) {
            $totalInq += (int)$row['cnt'];
            if ($row['status'] === 'new') { $newInq = (int)$row['cnt']; }
        }
        $typeRows     = $pdo->query('SELECT order_type, COUNT(*) AS cnt FROM inquiries GROUP BY order_type ORDER BY cnt DESC')->fetchAll();
        $inquiryTypes = array_values(array_filter(array_column($typeRows, 'order_type'), fn($t) => $t !== null && $t !== ''));
        sort($inquiryTypes, SORT_STRING);

        $reviewStats = $pdo->query('SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS average FROM reviews')->fetch(PDO::FETCH_ASSOC);
        $reviewTotal = (int)$reviewStats['total'];
        $reviewAverage = (float)$reviewStats['average'];
        $reviewWhere = [];
        $reviewParams = [];
        if ($reviewRating > 0) {
          $reviewWhere[] = 'r.rating = :rating';
          $reviewParams['rating'] = $reviewRating;
        }
        if ($reviewSearch !== '') {
          $reviewWhere[] = '(r.review_text LIKE :review_text OR o.order_num LIKE :order_num OR o.name LIKE :customer_name)';
          $reviewLike = '%' . likeEscape($reviewSearch) . '%';
          $reviewParams['review_text'] = $reviewParams['order_num'] = $reviewParams['customer_name'] = $reviewLike;
        }
        $reviewWhereSql = $reviewWhere ? ' WHERE ' . implode(' AND ', $reviewWhere) : '';
        $reviewStmt = $pdo->prepare(
          'SELECT r.rating, r.review_text, r.created_at, o.order_num, o.name, o.email
             FROM reviews r
             JOIN orders o ON o.id = r.order_id' . $reviewWhereSql . '
            ORDER BY r.created_at DESC, r.id DESC
            LIMIT 200'
        );
        $reviewStmt->execute($reviewParams);
        $reviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('AbiYarn dashboard: ' . $e->getMessage());
        $dbError = 'Could not load orders and inquiries right now. Please refresh, and make sure schema2_updated.sql has been run.';
    }

    /* ── Products (admin + owner) ─────────────── */
    $productCategories = ['bouquets','flowers','bags','accessories'];
    $productSorts = [
        'id'          => ['Default (ID)',                    'id ASC'],
        'price_asc'   => ['Price: low to high',              'price ASC, id ASC'],
        'price_desc'  => ['Price: high to low',              'price DESC, id ASC'],
        'avail_first' => ['Availability: available first',   "(status = 'available') DESC, id ASC"],
        'out_first'   => ['Availability: out of stock first',"(status = 'out_of_stock') DESC, id ASC"],
        'category'    => ['Category: A to Z',                'category ASC, name ASC'],
    ];
    if (!isset($productSorts[$prSort])) { $prSort = 'id'; }

    $pWhere  = [];
    $pParams = [];
    if ($prSearch !== '') {
        $pWhere[] = '(name LIKE :q OR description LIKE :q2)';
        $pParams['q']  = "%$prSearch%";
        $pParams['q2'] = "%$prSearch%";
    }
    if (in_array($prCat, $productCategories, true)) {
        $pWhere[] = 'category = :cat';
        $pParams['cat'] = $prCat;
    }
    if (in_array($prAvail, ['available','out_of_stock'], true)) {
        $pWhere[] = 'status = :avail';
        $pParams['avail'] = $prAvail;
    }
    $pSql  = 'SELECT * FROM products'
           . ($pWhere ? ' WHERE ' . implode(' AND ', $pWhere) : '')
           . ' ORDER BY ' . $productSorts[$prSort][1];
    $pStmt = $pdo->prepare($pSql);
    $pStmt->execute($pParams);
    $allProducts   = $pStmt->fetchAll();
    $totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();

    /* ── Administration (owner only) ───────────────
       Optimisations, same approach as Orders / Inquiries:
         • the role-change POST is handled BEFORE any account list is queried
         • owners/admins and regular users are two separate paged queries
           (WHERE role_id IN (...) LIMIT 25) instead of loading every account
           and splitting them in PHP. InnoDB secondary indexes carry the primary
           key, so idx_users_role already serves "role filter + ORDER BY id"
         • role names come from the tiny roles table already in memory (no JOIN)
         • one COUNT(*) per pager, only the columns the table shows are selected
         • LIKE wildcards typed by the user (% and _) are escaped
         • role_id is validated and the UPDATE only touches a row that changes */
    $allRoles = $staffUsers = $plainUsers = [];
    $sTotal = $uTotal = 0;
    $sPages = $uPages = 1;
    $sPage  = max(1, (int)($_GET['spg'] ?? 1));
    $uPage  = max(1, (int)($_GET['upg'] ?? 1));
    $adminError = '';

    if ($isOwner) {
        try {
            $allRoles  = $pdo->query('SELECT id, role_name, description FROM roles ORDER BY id ASC')->fetchAll();
            $roleNames = [];
            $staffRoleIds = $plainRoleIds = [];
            foreach ($allRoles as $role) {
                $rid = (int)$role['id'];
                $roleNames[$rid] = $role['role_name'];
                if (in_array($role['role_name'], ['owner', 'admin'], true)) { $staffRoleIds[] = $rid; }
                else { $plainRoleIds[] = $rid; }
            }
            $ownerRoleId = array_search('owner', $roleNames, true);

            /* Owner: promote / demote a user's role */
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
                $userId = (int)($_POST['user_id'] ?? 0);
                $roleId = (int)($_POST['role_id'] ?? 0);
                $flash  = '';
                $flashIsError = true;

                if ($userId === (int)($_SESSION['admin_user_id'] ?? 0)) {
                    $flash = 'You cannot change your own role.';
                } elseif ($userId < 1 || !isset($roleNames[$roleId])) {
                    $flash = 'Invalid account or role.';
                } else {
                    try {
                        $st = $pdo->prepare('UPDATE users SET role_id = :r WHERE id = :u AND role_id <> :r2');
                        $st->execute(['r' => $roleId, 'r2' => $roleId, 'u' => $userId]);
                        $flash = $st->rowCount() ? 'Account role updated.' : 'No change: that account already has this role.';
                        $flashIsError = false;
                    } catch (Throwable $e) {
                        error_log('AbiYarn update role: ' . $e->getMessage());
                        $flash = 'Could not update the role. Please try again.';
                    }
                }

                header('Location: index.php?' . http_build_query([
                    'tab' => 'administration',
                    'aq'  => trim((string)($_POST['aq'] ?? '')),
                    'spg' => max(1, (int)($_POST['spg'] ?? 1)),
                    'upg' => max(1, (int)($_POST['upg'] ?? 1)),
                    'msg' => $flash,
                    'err' => $flashIsError ? 1 : '',
                ]));
                exit;
            }

            /* Load owner Administration lists on every page load because sidebar navigation is client-side. */
            [$staffUsers, $sTotal, $sPages, $sPage] = fetchAccounts($pdo, $staffRoleIds, $ownerRoleId === false ? null : (int)$ownerRoleId, $roleNames, $aSearch, $sPage, $perPage);
            [$plainUsers, $uTotal, $uPages, $uPage] = fetchAccounts($pdo, $plainRoleIds, null, $roleNames, $aSearch, $uPage, $perPage);
        } catch (Throwable $e) {
            error_log('AbiYarn administration: ' . $e->getMessage());
            $adminError = 'Could not load accounts right now. Please refresh and try again.';
        }
    }

    /* Admin + Owner: edit a product's name / price / category / description */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
        $pid   = (int)$_POST['product_id'];
        $pName = trim((string)($_POST['name'] ?? ''));
        $pCat  = trim((string)($_POST['category'] ?? ''));
        $pPrice= (float)($_POST['price'] ?? 0);
        $pDesc = trim((string)($_POST['description'] ?? ''));
        $pStock  = max(0, (int)($_POST['stock'] ?? 0));
        $pStatus = (($_POST['stock_status'] ?? '') === 'out_of_stock') ? 'out_of_stock' : 'available';
        if ($pStock === 0) { $pStatus = 'out_of_stock'; } // nothing left = out of stock

        if ($pid && $pName !== '' && $pPrice >= 0) {
            try {
                $stmt = $pdo->prepare('UPDATE products SET name=:n, category=:c, price=:p, description=:d, stock=:s, status=:st WHERE id=:id');
                $stmt->execute(['n' => $pName, 'c' => $pCat, 'p' => $pPrice, 'd' => $pDesc, 's' => $pStock, 'st' => $pStatus, 'id' => $pid]);
                header('Location: index.php?tab=products&msg=' . urlencode('Product updated.'));
            } catch (Throwable $e) {
                error_log('AbiYarn update product: ' . $e->getMessage());
                header('Location: index.php?tab=products&err=1&msg=' . urlencode('Product could not be saved. Please try again.'));
            }
        } else {
            header('Location: index.php?tab=products&msg=' . urlencode('Product not updated — check name and price.'));
        }
        exit;
    }

    /* Admin + Owner: Query Performance (MariaDB 10.4: EXPLAIN / ANALYZE)
       MariaDB has no "EXPLAIN ANALYZE" (that is MySQL 8.0.18+). Its equivalent is
       ANALYZE FORMAT=JSON <select>, which runs the query and reports measured times
       and rows. Only the fixed read-only SELECT presets below can be chosen, because
       ANALYZE really executes the statement, so free-form SQL is never accepted. */
    $explainPresets = [
        'orders_by_status'     => 'Orders by status (newest first)',
        'orders_by_email'      => 'Orders by customer email',
        'orders_by_payment'    => 'Revenue by payment method',
        'inquiries_by_status'  => 'Inquiries by status (newest first)',
        'products_by_category' => 'Products by category',
        'products_in_stock'    => 'Products in stock',
        'reviews_by_rating'    => 'Reviews by rating',
        'users_roles_join'     => 'Users with roles (Administration tab)',
    ];
    $explainSql = [
        'orders_by_status'     => "SELECT id, order_num, name, total, status, created_at FROM orders WHERE status = 'pending' ORDER BY created_at DESC LIMIT 20",
        'orders_by_email'      => "SELECT id, order_num, status, total FROM orders WHERE email = 'customer@example.com'",
        'orders_by_payment'    => "SELECT payment, COUNT(*) AS orders, SUM(total) AS revenue FROM orders GROUP BY payment",
        'inquiries_by_status'  => "SELECT id, name, order_type, status, created_at FROM inquiries WHERE status = 'new' ORDER BY created_at DESC LIMIT 20",
        'products_by_category' => "SELECT id, name, price, stock FROM products WHERE category = 'bouquets' ORDER BY price",
        'products_in_stock'    => "SELECT id, name, category, stock FROM products WHERE status = 'available' AND stock > 0",
        'reviews_by_rating'    => "SELECT id, order_id, rating, created_at FROM reviews WHERE rating = 5 ORDER BY created_at DESC LIMIT 20",
        'users_roles_join'     => "SELECT u.id, u.username, u.full_name, u.email, r.role_name FROM users u INNER JOIN roles r ON u.role_id = r.id ORDER BY (r.role_name = 'owner') DESC, u.id ASC",
    ];
    $exKey  = (string)($_GET['xq'] ?? '');
    $exMode = (string)($_GET['xm'] ?? '');
    $exSql  = '';
    $exRows = [];
    $exPlanText = '';
    $exErr  = '';
    $exSummary = [];
    if ($tab === 'performance' && $exKey !== '') {
        if (!isset($explainPresets[$exKey]) || !in_array($exMode, ['explain', 'analyze'], true)) {
            $exErr = 'Choose a query, then pick EXPLAIN or ANALYZE.';
        } else {
            $exSql = $explainSql[$exKey];
            $emulate = $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
            try {
                // Send EXPLAIN / ANALYZE as a plain text query (not a server-side prepared statement).
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
                if ($exMode === 'analyze') {
                    $st  = $pdo->query('ANALYZE FORMAT=JSON ' . $exSql);
                    $row = $st->fetch(PDO::FETCH_NUM);          // single column holding the JSON plan
                    $st->closeCursor();
                    $plan = json_decode((string)($row[0] ?? ''), true);
                    if (!is_array($plan)) { throw new RuntimeException('ANALYZE returned no JSON plan.'); }
                    $exPlanText = json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $exRows     = [$plan];
                    $exSummary  = explainSummary('analyze', $exRows);
                } else {
                    $st = $pdo->query('EXPLAIN ' . $exSql);
                    $exRows = $st->fetchAll(PDO::FETCH_ASSOC);
                    $st->closeCursor();
                    if ($exRows) { $exSummary = explainSummary('explain', $exRows); }
                }
            } catch (Throwable $e) {
                error_log('AbiYarn explain: ' . $e->getMessage());
                $exErr = 'Could not run the plan. Please try again; the technical detail is in the PHP error log.';
            } finally {
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulate);
            }
        }
    }

    /* CSV export — runs its own query so it exports every match, not just the current page */
    if (isset($_GET['export'])) {
        $which = $_GET['export'];
        if ($which === 'orders') {
            try {
                $st = $pdo->prepare('SELECT order_num, name, email, phone, address, city, postal, country, payment, gcash_ref, total, status, estimated_delivery, items, created_at
                                       FROM orders' . $oWhereSql . ' ORDER BY created_at DESC, id DESC');
                $st->execute($oParams);
            } catch (Throwable $e) {
                error_log('AbiYarn export orders: ' . $e->getMessage());
                http_response_code(500);
                exit('Could not export orders. Please try again.');
            }
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="abiyarn-orders-' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order #','Name','Email','Phone','Address','City','Postal','Country','Payment','GCash Ref','Total','Status','Estimated delivery','Items','Date']);
            while ($o = $st->fetch()) {
                $itemStr = orderItemsText((string)($o['items'] ?? ''), '; ', ' x');
                fputcsv($out, [$o['order_num'],$o['name'],$o['email'],$o['phone'],$o['address'],$o['city'],$o['postal'],$o['country'],$o['payment'],$o['gcash_ref'],$o['total'],$o['status'],$o['estimated_delivery'],$itemStr,$o['created_at']]);
            }
            fclose($out); exit;
        }
        if ($which === 'inquiries') {
            try {
                $st = $pdo->prepare('SELECT name, email, phone, order_type, quantity, colors, needed_by, details, status, created_at
                                       FROM inquiries' . $iWhereSql . ' ORDER BY created_at DESC, id DESC');
                $st->execute($iParams);
            } catch (Throwable $e) {
                error_log('AbiYarn export inquiries: ' . $e->getMessage());
                http_response_code(500);
                exit('Could not export inquiries. Please try again.');
            }
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="abiyarn-inquiries-' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name','Email','Phone','Type','Quantity','Colors','Needed By','Details','Status','Date']);
            while ($q = $st->fetch()) {
                fputcsv($out, [$q['name'],$q['email'],$q['phone'],$q['order_type'],$q['quantity'],$q['colors'],$q['needed_by'],$q['details'],$q['status'],$q['created_at']]);
            }
            fclose($out); exit;
        }
    }

    /* Inline status update */
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $type   = (string)($_POST['type'] ?? '');
        $id     = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $flash  = '';
        $flashIsError = false;

        try {
            if ($type === 'order' && $id > 0 && in_array($status, $orderStatuses, true)) {
              $flash = setOrderStatus($pdo, $id, $status);
            } elseif ($type === 'inquiry' && $id > 0 && in_array($status, $inquiryStatuses, true)) {
                $pdo->prepare('UPDATE inquiries SET status=:s WHERE id=:id')->execute(['s' => $status, 'id' => $id]);
                $flash = 'Inquiry status updated.';
            } else {
                $flash = 'Invalid status update.';
                $flashIsError = true;
            }
        } catch (DomainException $e) {
          $flash = $e->getMessage();
          $flashIsError = true;
        } catch (Throwable $e) {
            error_log('AbiYarn status update: ' . $e->getMessage());
            $flash = 'Could not update the status. Please try again.';
            $flashIsError = true;
        }

        header('Location: index.php?' . http_build_query([
          'tab' => $tab,
          'os' => $oStatus,
          'oq' => $oSearch,
          'od' => $oDate,
          'op' => $oPayment,
          'opg' => $oPage,
          'is' => $iStatus,
          'iq' => $iSearch,
          'idate' => $iDate,
          'it' => $iType,
          'ipg' => $iPage,
          'msg' => $flash,
          'err' => $flashIsError ? 1 : '',
        ]));
        exit;
    }
}

/* ── Helpers ─────────────────────────────────── */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/* Escape % and _ so a search for "50%" matches the text, not "anything". */
function likeEscape(string $s): string { return addcslashes($s, '\\%_'); }

/* One page of accounts for a set of role ids, plus the total for the pager.
   Returns [rows, total, pages, clampedPage]. $roleIds come from the roles table (ints). */
function fetchAccounts(PDO $pdo, array $roleIds, ?int $ownerRoleId, array $roleNames, string $search, int $page, int $perPage): array {
  if (!$roleIds) return [[], 0, 1, 1];
  $where  = 'role_id IN (' . implode(',', array_map('intval', $roleIds)) . ')';
  $params = [];
  if ($search !== '') {
    $like   = '%' . likeEscape($search) . '%';
    $where .= ' AND (username LIKE :q OR full_name LIKE :q2 OR email LIKE :q3)';
    $params = ['q' => $like, 'q2' => $like, 'q3' => $like];
  }
  $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE ' . $where);
  $st->execute($params);
  $total = (int)$st->fetchColumn();
  $pages = max(1, (int)ceil($total / $perPage));
  $page  = min($page, $pages);

  $order = $ownerRoleId !== null ? '(role_id = ' . (int)$ownerRoleId . ') DESC, id ASC' : 'id ASC';
  $st = $pdo->prepare(
    'SELECT id, username, full_name, email, role_id FROM users WHERE ' . $where .
    ' ORDER BY ' . $order . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
  );
  $st->execute($params);
  $rows = $st->fetchAll();
  foreach ($rows as &$r) { $r['role_name'] = $roleNames[(int)$r['role_id']] ?? ''; }
  unset($r);
  return [$rows, $total, $pages, $page];
}

/* [start of day, start of next day] — lets MySQL use the created_at indexes. */
function dayRange(string $ymd): array {
  $d = new DateTimeImmutable($ymd);
  return [$d->format('Y-m-d 00:00:00'), $d->modify('+1 day')->format('Y-m-d 00:00:00')];
}

/* "Showing 1–25 of 140" + Prev / 1 2 … 9 / Next, keeping every active filter. */
function pagerHtml(int $page, int $pages, int $total, int $perPage, string $param, string $tab): string {
  if ($total < 1) return '';
  $from = ($page - 1) * $perPage + 1;
  $to   = min($total, $page * $perPage);
  $url  = function (int $p) use ($param, $tab): string {
    $q = $_GET;
    unset($q['export'], $q['msg'], $q['err']);
    $q['tab']   = $tab;
    $q[$param]  = $p;
    return '?' . h(http_build_query($q));
  };
  $out = '<div class="pager"><span>Showing ' . number_format($from) . '–' . number_format($to) . ' of ' . number_format($total) . '</span>';
  if ($pages > 1) {
    $out .= '<div class="pager-links">';
    $out .= $page > 1 ? '<a class="pg" href="' . $url($page - 1) . '">‹ Prev</a>' : '<span class="pg disabled">‹ Prev</span>';
    $last = 0;
    for ($p = 1; $p <= $pages; $p++) {
      if ($p === 1 || $p === $pages || abs($p - $page) <= 2) {
        if ($last && $p - $last > 1) $out .= '<span class="pg disabled">…</span>';
        $out .= $p === $page
          ? '<span class="pg current">' . $p . '</span>'
          : '<a class="pg" href="' . $url($p) . '">' . $p . '</a>';
        $last = $p;
      }
    }
    $out .= $page < $pages ? '<a class="pg" href="' . $url($page + 1) . '">Next ›</a>' : '<span class="pg disabled">Next ›</span>';
    $out .= '</div>';
  }
  return $out . '</div>';
}

function filterDate(string $value): string {
  if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) return '';
  return checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1]) ? $value : '';
}

function orderItemsText(string $raw, string $separator = ', ', string $quantitySeparator = ' ×'): string {
  $items = json_decode($raw, true);
  if (!is_array($items)) return '';

  $formatted = [];
  foreach ($items as $item) {
    if (!is_array($item)) continue;
    $name = trim((string)($item['name'] ?? $item['product_name'] ?? ''));
    if ($name === '') continue;

    $quantity = $item['qty'] ?? $item['quantity'] ?? null;
    $formatted[] = $name . ($quantity !== null && $quantity !== '' ? $quantitySeparator . (int)$quantity : '');
  }
  return implode($separator, $formatted);
}

function accountsTable(array $rows, array $roles, string $emptyMsg): string {
    ob_start();
    if (!$rows): ?>
      <div class="empty"><div class="empty-icon">👥</div><?= h($emptyMsg) ?></div>
    <?php else: ?>
      <table class="acct-table">
        <colgroup>
          <col style="width:70px"><col style="width:18%"><col style="width:22%"><col><col style="width:110px"><col style="width:210px">
        </colgroup>
        <thead><tr>
          <th>ID</th><th>Username</th><th>Full name</th><th>Email</th><th>Role</th><th>Change role</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $u): ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><strong><?= h($u['username']) ?></strong></td>
            <td><?= h((string)$u['full_name']) ?></td>
            <td><?= h((string)$u['email']) ?></td>
            <td><span class="role-pill <?= $u['role_name']==='owner'?'role-owner':'' ?>"><?= h($u['role_name']) ?></span></td>
            <td>
              <?php if ((int)$u['id'] === (int)($_SESSION['admin_user_id'] ?? 0)): ?>
                <span style="font-size:12px;color:var(--muted)">This is you</span>
              <?php else: ?>
              <form method="POST" class="edit-form">
                <input type="hidden" name="update_role" value="1">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <input type="hidden" name="aq" value="<?= h((string)($_GET['aq'] ?? '')) ?>">
                <input type="hidden" name="spg" value="<?= max(1, (int)($_GET['spg'] ?? 1)) ?>">
                <input type="hidden" name="upg" value="<?= max(1, (int)($_GET['upg'] ?? 1)) ?>">
                <select name="role_id">
                  <?php foreach ($roles as $role): ?>
                    <option value="<?= (int)$role['id'] ?>" <?= $role['id']==$u['role_id']?'selected':'' ?>><?= ucfirst(h($role['role_name'])) ?></option>
                  <?php endforeach ?>
                </select>
                <button type="submit" class="btn btn-sm btn-green">Update</button>
              </form>
              <?php endif ?>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    <?php endif;
    return ob_get_clean();
}

/* Collect every table node ("table_name") from an ANALYZE FORMAT=JSON plan, in plan order. */
function analyzeTables($node, array &$out): void {
  if (!is_array($node)) return;
  if (isset($node['table_name']) && is_string($node['table_name'])) { $out[] = $node; }
  foreach ($node as $child) { if (is_array($child)) analyzeTables($child, $out); }
}

/* Headline numbers for the Query Performance tab, read from the plan output.
   MariaDB: $rows is [decoded ANALYZE FORMAT=JSON plan] for 'analyze', or the EXPLAIN rows.
   Returns a list of [label, value, sub-text]. */
function explainSummary(string $mode, array $rows): array {
    if ($mode === 'analyze') {
        $plan   = $rows[0];
        $tables = [];
        analyzeTables($plan, $tables);

        $ms = $plan['query_block']['r_total_time_ms'] ?? null;
        $msText = is_numeric($ms) ? rtrim(rtrim(number_format((float)$ms, 3, '.', ''), '0'), '.') . ' ms' : '—';

        $read = 0; $haveRead = false;
        foreach ($tables as $t) {
            if (isset($t['r_rows']) && is_numeric($t['r_rows'])) {
                $read += (int)round((float)$t['r_rows'] * (float)($t['r_loops'] ?? 1));
                $haveRead = true;
            }
        }

        $first = $tables[0] ?? null;
        if ($first === null)                                   { $access = '—'; }
        elseif (($first['access_type'] ?? '') === 'ALL')       { $access = 'Full table scan'; }
        elseif (!empty($first['key']))                         { $access = 'Index: ' . $first['key']; }
        else                                                   { $access = 'No index'; }

        $json = json_encode($plan);
        return [
            ['Mode',       'ANALYZE',                        'The query was executed'],
            ['Total time', $msText,                          'Measured by the server'],
            ['Rows read',  $haveRead ? number_format($read) : '—', 'Measured, not estimated'],
            ['Access',     $access,                          strpos((string)$json, '"filesort"') !== false ? 'Uses filesort' : 'No extra sort'],
        ];
    }
    $row = null;
    foreach ($rows as $r) { if (($r['table'] ?? null) !== null) { $row = $r; break; } }
    $type  = (string)($row['type'] ?? '');
    $key   = $row['key'] ?? null;
    $extra = (string)($row['Extra'] ?? '');
    if ($row === null)        { $access = '—'; }
    elseif ($type === 'ALL')  { $access = 'Full table scan'; }
    elseif ($key)             { $access = 'Index: ' . $key; }
    else                      { $access = 'No index'; }
    return [
        ['Mode',           'EXPLAIN',                                      'Plan only, the query was not run'],
        ['Access',         $access,                                        $type !== '' ? 'type: ' . $type : ''],
        ['Estimated rows', $row ? number_format((int)$row['rows']) : '—',  'From table statistics'],
        ['Sorting',        stripos($extra, 'filesort') !== false ? 'Uses filesort' : 'No extra sort', $extra],
    ];
}

function statusPill(string $s): string {
    $map = [
        'pending'    => '#854F0B;background:#FAEEDA',
        'processing' => '#185FA5;background:#E6F1FB',
        'completed'  => '#3B6D11;background:#EAF3DE',
        'cancelled'  => '#A32D2D;background:#FCEBEB',
        'new'        => '#534AB7;background:#EEEDFE',
        'replied'    => '#3B6D11;background:#EAF3DE',
        'closed'     => '#5F5E5A;background:#F1EFE8',
    ];
    $style = $map[$s] ?? '#5F5E5A;background:#F1EFE8';
    return "<span style='color:$style;font-size:11px;padding:2px 8px;border-radius:20px;font-weight:500;white-space:nowrap'>$s</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>AbiYarn <?= $isOwner ? 'Owner' : 'Admin' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --page:#f7f6ef;--surface:#faf9f4;--white:#fff;--border:#dddcd5;
  --text:#242923;--muted:#73736d;--blue:#182c4b;--blue-soft:#e7edf8;
  --green:#3f6847;--green-soft:#e8f0e8;--yellow-soft:#f5ecd8;--red-soft:#f7e3e1;
  --font:Arial,sans-serif;--mono:"DM Mono",monospace
}
html{scroll-behavior:smooth}
body{font-family:var(--font);background:var(--page);color:var(--text);font-size:14px;min-height:100vh}

/* LOGIN */
.login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:30px;background:var(--page)}
.login-card{width:100%;max-width:400px;padding:35px;background:var(--surface);border:1px solid var(--border);border-radius:24px;box-shadow:0 10px 35px rgba(24,44,75,.06)}
.login-logo{text-align:center;margin-bottom:28px}
.login-logo h1{font-size:25px;color:var(--blue);font-weight:700}
.login-logo p{font-size:13px;color:var(--muted);margin-top:7px}
.field{margin-bottom:16px}
.field label{display:block;font-size:12px;color:var(--muted);margin-bottom:7px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.field input{width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:#fff;color:var(--text);font-family:var(--font);font-size:14px}
.field input:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(24,44,75,.08)}
.btn-login{width:100%;padding:13px;margin-top:6px;border:0;border-radius:25px;background:var(--blue);color:#fff;font-family:var(--font);font-size:14px;font-weight:700;cursor:pointer}
.btn-login:hover{opacity:.9}
.login-err{padding:11px 13px;margin-bottom:16px;border:1px solid #e7c3c0;border-radius:10px;background:var(--red-soft);color:#9b3d35;font-size:13px}

/* REFERENCE-STYLE LAYOUT */
.shell{display:flex;min-height:100vh}
.sidebar{width:280px;min-height:100vh;flex-shrink:0;position:sticky;top:0;background:var(--surface);border-right:1px solid var(--border);padding:35px 25px 25px;display:flex;flex-direction:column;gap:8px}
.sidebar-logo{text-align:center;color:var(--blue);font-size:20px;font-weight:700;padding:0;margin:0 0 22px}
.sidebar-logo span{color:#536f91}
.nav-item{min-height:43px;border:0;border-radius:8px;padding:10px 13px;display:flex;align-items:center;gap:10px;width:100%;background:transparent;color:#666862;font-family:var(--font);font-size:13px;font-weight:600;text-decoration:none;cursor:pointer;text-align:left}
.nav-item:hover{background:#efeee8;color:var(--blue)}
.nav-item.active{background:#dedfdc;color:var(--blue)}
.nav-icon{width:20px;text-align:center;font-size:15px}
.sidebar-bottom{margin-top:auto;padding-top:25px;border-top:1px solid var(--border)}
.badge-count{margin-left:auto;padding:3px 8px;border-radius:20px;background:#e5e9f2;color:var(--blue);font-size:10px;font-weight:700}
.badge-pending{background:var(--yellow-soft);color:#7a5a1e}
.btn-logout{color:#9b3d35}
.btn-logout:hover{background:var(--red-soft);color:#8d3029}

.main{flex:1;min-width:0;padding:40px 60px;overflow-x:hidden}
.topbar{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:25px}
.topbar h2{color:var(--blue);font-size:32px;line-height:1.2;font-weight:700;letter-spacing:-.5px}
.topbar-actions{display:flex;gap:8px}
/* Fixed top-right account menu; follows existing AbiYarn palette */
.topbar-actions{align-items:center;flex-wrap:wrap;justify-content:flex-end}
.account-menu{position:relative;z-index:70}
.account-menu-trigger{display:flex;align-items:center;gap:9px;border:1px solid var(--border);border-radius:24px;padding:5px 12px 5px 5px;background:var(--surface);color:var(--blue);font:600 13px var(--font);cursor:pointer;max-width:230px}
.account-avatar{display:grid;place-items:center;width:32px;height:32px;flex:none;border-radius:50%;background:var(--blue);color:white;font-size:13px}
.account-menu-name{max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.account-menu-dropdown{position:absolute;right:0;top:calc(100% + 8px);width:230px;background:var(--surface);border:1px solid var(--border);border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.12);padding:9px}
.account-menu-dropdown[hidden]{display:none}
.account-menu-identity{display:flex;flex-direction:column;gap:4px;padding:11px 12px;border-bottom:1px solid var(--border);overflow-wrap:anywhere}
.account-menu-identity small{color:var(--muted)}
.account-menu-logout{display:block;padding:12px;border-radius:10px;text-decoration:none;color:#9b3d35;font-weight:600}
.account-menu-logout:hover,.account-menu-logout:focus-visible{background:var(--red-soft)}
@media(max-width:520px){.account-menu-name{max-width:75px}.account-menu-trigger{padding-right:8px}}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:40px;padding:8px 16px;border:1px solid var(--border);border-radius:25px;background:var(--surface);color:var(--blue);font-family:var(--font);font-size:13px;font-weight:600;text-decoration:none;cursor:pointer}
.btn:hover{background:#efeee8}
.btn-green{background:var(--blue);border-color:var(--blue);color:#fff}
.btn-green:hover{background:#263f60}

/* STAT CARDS */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px}
.stat-card{min-height:125px;padding:18px;background:var(--surface);border:1px solid var(--border);border-radius:20px}
.stat-label{color:var(--muted);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px}
.stat-val{color:var(--blue);font-size:25px;line-height:1.1;font-weight:700}
.stat-sub{color:var(--muted);font-size:11px;margin-top:7px}


/* SALES ANALYTICS — match the existing dashboard stat cards */
.analytics-note{color:var(--muted);margin:0 0 22px;font-size:13px}
.analytics-section-title{color:var(--blue);font-size:17px;margin:20px 0 12px}
.analytics-status-grid{grid-template-columns:repeat(4,minmax(0,1fr))}
.analytics-highlight-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
.analytics-highlight-name{font-size:20px;line-height:1.25;overflow-wrap:anywhere}
.analytics-tabs{display:flex;flex-wrap:wrap;gap:8px;padding:9px;background:var(--surface);border:1px solid var(--border);border-radius:22px;margin:24px 0 18px}
.analytics-tab{padding:11px 18px;border:0;border-radius:16px;background:transparent;color:var(--blue);font:600 13px var(--font);cursor:pointer}
.analytics-tab:hover{background:#efeee8}
.analytics-tab.active{background:var(--blue);color:white}
.analytics-report{display:none}
.analytics-report.active{display:block}
.analytics-report-heading{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:12px}
.analytics-report-heading h3{font-size:18px;color:var(--blue)}
.analytics-search{min-height:40px;min-width:220px;max-width:100%;padding:8px 14px;border:1px solid var(--border);border-radius:22px;background:var(--surface);font:13px var(--font)}
.analytics-table-wrap{overflow-x:auto;border:1px solid var(--border);border-radius:18px;background:var(--surface)}
.analytics-table{width:100%;min-width:550px;border-collapse:collapse;font-size:13px}
.analytics-table th{background:#f2f1eb;color:var(--blue);text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.04em}
.analytics-table td,.analytics-table th{padding:13px 16px;border-bottom:1px solid var(--border)}
.analytics-table tbody tr:last-child td{border-bottom:0}
.analytics-table tbody tr:hover{background:#f8f7f2}
.analytics-table-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;color:var(--muted);font-size:12px}
.analytics-pagination{display:flex;align-items:center;gap:10px}
.analytics-pagination button:disabled{opacity:.4;cursor:not-allowed}
@media(max-width:900px){.analytics-status-grid,.analytics-highlight-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){.analytics-status-grid,.analytics-highlight-grid{grid-template-columns:1fr}.analytics-tab{flex:1}.analytics-search{width:100%}}

/* Compact sales analytics: fit summaries and the beginning of the table on laptop screens */
#panel-analytics .analytics-section-title{margin:9px 0 7px;font-size:14px}
#panel-analytics .analytics-status-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-bottom:10px}
#panel-analytics .analytics-highlight-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin-bottom:10px}
#panel-analytics .stat-card{min-height:76px;padding:11px 13px;border-radius:14px}
#panel-analytics .stat-label{font-size:10px;line-height:1.25}
#panel-analytics .stat-val{font-size:23px;line-height:1.2;margin:5px 0 3px}
#panel-analytics .stat-val.analytics-highlight-name{font-size:16px;line-height:1.2;overflow-wrap:anywhere}
#panel-analytics .stat-sub{font-size:10px;line-height:1.35}
#panel-analytics .analytics-tabs{margin:11px 0 10px;padding:5px;border-radius:15px;gap:4px}
#panel-analytics .analytics-tab{padding:8px 13px;border-radius:11px}
#panel-analytics .analytics-report-heading{margin-bottom:8px}
#panel-analytics .analytics-report-heading h3{font-size:15px}
#panel-analytics .analytics-search{min-height:33px}
#panel-analytics .analytics-table td,#panel-analytics .analytics-table th{padding:9px 12px}
#panel-analytics .analytics-table-footer{margin-top:8px}
@media(max-width:850px){#panel-analytics .analytics-status-grid{grid-template-columns:repeat(2,minmax(0,1fr))}#panel-analytics .analytics-highlight-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:540px){#panel-analytics .analytics-highlight-grid{grid-template-columns:1fr}}

/* PILL TOOL BAR */
.filter-bar{display:flex;align-items:center;flex-wrap:wrap;gap:8px;width:100%;min-height:70px;padding:10px 20px;margin-bottom:20px;border:1px solid var(--border);border-radius:35px;background:var(--surface)}
.filter-bar form{display:flex!important;align-items:center;flex-wrap:wrap;gap:8px;width:100%}
.filter-bar input,.filter-bar select{min-height:38px;padding:8px 12px;border:1px solid var(--border);border-radius:20px;background:#fff;color:#333832;font-family:var(--font);font-size:13px}
.filter-bar input{min-width:240px;flex:1}
.filter-bar input:focus,.filter-bar select:focus{outline:none;border-color:var(--blue)}

/* CARDS / TABLES */
.panel{display:none}.panel.active{display:block}
.tbl-card,.chart-card,.chart-full{background:var(--surface);border:1px solid var(--border);border-radius:20px;overflow:hidden}
table{width:100%;border-collapse:collapse;font-size:13px}
th{padding:11px 14px;background:#f1f0ea;color:#6c6d67;border-bottom:1px solid var(--border);text-align:left;white-space:nowrap;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
td{padding:12px 14px;border-bottom:1px solid var(--border);vertical-align:top}
tr:last-child td{border-bottom:none}
tbody tr:hover td{background:#f5f4ef}
.expand-row{display:none;background:#f5f4ef}.expand-row.open{display:table-row}
.expand-row td{padding:14px;color:var(--muted);font-size:12px}
.expand-inner{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.expand-field{display:flex;flex-direction:column;gap:3px}
.expand-field strong{color:var(--muted);font-size:10px;text-transform:uppercase;letter-spacing:.04em}
.expand-field span{color:#30342f}
.expand-toggle{padding:0 4px;border:0;background:none;color:var(--blue);font-size:18px;cursor:pointer}
.td-mono{font-family:var(--mono);font-size:12px;color:var(--muted)}
.items-text{font-size:11px;color:var(--muted);margin-top:2px}
.addr-cell{max-width:180px;color:var(--muted);font-size:12px}.addr-cell strong{display:block;color:#30342f;font-size:13px}
.status-form select{padding:5px 8px;border:1px solid var(--border);border-radius:20px;background:#fff;color:#333832;font-family:var(--font);font-size:11px;cursor:pointer}

/* DASHBOARD TOP LAYOUT (left: revenue · right: stacked cards) */
.dash-top{display:grid;grid-template-columns:1.15fr 1fr;gap:15px;margin-bottom:15px}
.dash-stack{display:grid;grid-template-rows:repeat(3,1fr);gap:15px}
.rev-card{display:flex;flex-direction:column;justify-content:center;padding:30px}
.rev-card .stat-val{font-size:46px;letter-spacing:-1px}
.rev-meta{margin-top:30px;padding-top:20px;border-top:1px solid var(--border)}
.rev-avg{color:var(--blue);font-size:24px;font-weight:700}

/* CHARTS */
.chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px}
.chart-card,.chart-full{padding:20px}
.chart-card h3,.chart-full h3{margin-bottom:15px;color:var(--blue);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.chart-wrap{height:220px;position:relative}.chart-full .chart-wrap{height:260px}

/* ADMINISTRATION / PRODUCTS */
.panel-head{padding:18px 20px;border-bottom:1px solid var(--border)}
.panel-head h3{color:var(--blue);font-size:14px;display:flex;align-items:center;gap:6px}
.panel-head p{color:var(--muted);font-size:12px;margin-top:4px}
.msg-banner{padding:11px 14px;margin-bottom:15px;border:1px solid #c9d8c9;border-radius:12px;background:var(--green-soft);color:var(--green);font-size:13px}
.role-pill{display:inline-block;padding:3px 9px;border-radius:20px;background:var(--green-soft);color:var(--green);font-size:11px;text-transform:capitalize;font-weight:700}
.role-pill.role-owner{background:#f3e7d3;color:#8a5a0e}
.edit-form{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.edit-form select,.edit-form input[type=text],.edit-form input[type=number]{padding:7px 9px;border:1px solid var(--border);border-radius:8px;background:#fff;color:#333832;font-family:var(--font);font-size:12px}
.prod-thumb{width:38px;height:38px;object-fit:cover;border-radius:8px;border:1px solid var(--border)}
.btn-sm{min-height:32px;padding:6px 11px;font-size:12px}
.empty{text-align:center;padding:55px 25px;color:var(--muted)}.empty-icon{font-size:34px;margin-bottom:10px}

/* PRODUCT / ACCOUNT TABLES */
.tbl-card.scroll-x{overflow-x:auto}
.pt,.acct-table{table-layout:fixed}
.pt{min-width:1080px}.acct-table{min-width:760px}
.pt th,.pt td,.acct-table th,.acct-table td{vertical-align:middle}
.pt input[type=text],.pt input[type=number],.pt select{width:100%;padding:7px 9px;border:1px solid var(--border);border-radius:8px;background:#fff;color:#333832;font-family:var(--font);font-size:12px}
.pt input:focus,.pt select:focus{outline:none;border-color:var(--blue)}
.pt .col-num{text-align:right}.pt td.col-num input{text-align:right}
.acct-table td{overflow-wrap:anywhere}
.section-gap{margin-top:20px}
.panel-head .count{display:inline-block;margin-left:6px;padding:2px 8px;border-radius:20px;background:#e5e9f2;color:var(--blue);font-size:11px;font-weight:700}
.filter-bar .sort-label{color:var(--muted);font-size:12px;font-weight:700}
.filter-bar select{min-width:150px}
.msg-banner.msg-error{border-color:#f0c4c4;background:#fdeeee;color:#a32d2d}
.pager{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-top:14px;font-size:13px;color:var(--muted)}
.pager-links{display:flex;gap:6px;flex-wrap:wrap}
.pager .pg{min-width:34px;padding:7px 12px;border:1px solid var(--border);border-radius:20px;background:#fff;color:#333832;text-align:center;text-decoration:none;font-size:12px;font-weight:600}
.pager a.pg:hover{border-color:var(--blue);color:var(--blue)}
.pager .pg.current{background:var(--blue);border-color:var(--blue);color:#fff}
.pager .pg.disabled{opacity:.45}

/* QUERY PERFORMANCE */
.perf-val{font-size:18px;line-height:1.3;overflow-wrap:anywhere}
.plan-box{margin:0;padding:16px 20px;overflow-x:auto;white-space:pre;font-family:var(--mono);font-size:12px;line-height:1.7;color:#30342f;background:#fff}

/* RESPONSIVE */
@media (max-width:1200px){.main{padding:30px}.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media (max-width:900px){.sidebar{width:220px;padding:30px 18px 20px}.main{padding:25px}.topbar h2{font-size:27px}.chart-grid,.dash-top{grid-template-columns:1fr}}
@media (max-width:700px){
  .shell{display:block}.sidebar{position:static;width:100%;min-height:auto}.sidebar-bottom{margin-top:20px}
  .main{padding:20px}.topbar{align-items:flex-start}.topbar h2{font-size:24px}
  .stat-grid{grid-template-columns:1fr}.filter-bar{border-radius:20px;padding:12px}
  .filter-bar input{min-width:100%}.tbl-card{overflow-x:auto}table{min-width:760px}.expand-inner{grid-template-columns:1fr}
}
</style>
</head>
<body>

<?php if (!$loggedIn): ?>
<!-- ═══ LOGIN PAGE ════════════════════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <h1>🧶 AbiYarn</h1>
      <p>Admin Dashboard</p>
    </div>
    <?php if ($error): ?>
      <div class="login-err"><?= h($error) ?></div>
    <?php endif ?>
    <form method="POST">
      <input type="hidden" name="admin_login" value="1">
      <div class="field">
        <label>Username</label>
        <input type="text" name="username" autofocus autocomplete="username">
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" autocomplete="current-password">
      </div>
      <button type="submit" class="btn-login">Login →</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ═══ DASHBOARD ════════════════════════════ -->
<div class="shell">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-logo">Abi<span>Yarn</span> <?= $isOwner ? 'Owner' : 'Admin' ?></div>

    <button class="nav-item <?= $tab==='dashboard'?'active':'' ?>" onclick="switchTab('dashboard')">
      <span class="nav-icon">📊</span> Dashboard
    </button>
    <button class="nav-item <?= $tab==='orders'?'active':'' ?>" onclick="switchTab('orders')">
      <span class="nav-icon">🛍</span> Orders
      <?php if ($pendingOrders > 0): ?>
        <span class="badge-count badge-pending"><?= $pendingOrders ?></span>
      <?php endif ?>
    </button>
    <button class="nav-item <?= $tab==='inquiries'?'active':'' ?>" onclick="switchTab('inquiries')">
      <span class="nav-icon">💬</span> Inquiries
      <?php if ($newInq > 0): ?>
        <span class="badge-count"><?= $newInq ?></span>
      <?php endif ?>
    </button>
    <button class="nav-item <?= $tab==='reviews'?'active':'' ?>" onclick="switchTab('reviews')">
      <span class="nav-icon">★</span> Reviews
      <?php if ($reviewTotal > 0): ?><span class="badge-count"><?= $reviewTotal ?></span><?php endif ?>
    </button>
    <button class="nav-item <?= $tab==='products'?'active':'' ?>" onclick="switchTab('products')">
      <span class="nav-icon">🧶</span> Product Management
    </button>
    <button class="nav-item <?= $tab==='analytics'?'active':'' ?>" onclick="switchTab('analytics')">
      <span class="nav-icon">📈</span> Sales Analytics
    </button>
    <button class="nav-item <?= $tab==='performance'?'active':'' ?>" onclick="switchTab('performance')">
      <span class="nav-icon">⚡</span> Query Performance
    </button>
    <?php if ($isOwner): ?>
    <button class="nav-item <?= $tab==='administration'?'active':'' ?>" onclick="switchTab('administration')">
      <span class="nav-icon">👑</span> Administration
    </button>
    <?php endif ?>

    <a class="nav-item" href="../menu.html" target="_blank" rel="noopener">
      <span class="nav-icon">↗</span> Open storefront
    </a>


  </aside>

  <!-- Main -->
  <main class="main">

    <!-- Top bar -->
    <div class="topbar">
      <div class="header-content"><h2 id="pageTitle"><?= h($tabTitles[$tab]) ?></h2><div style="color:var(--muted);font-size:13px;margin-top:8px">AbiYarn <?= $isOwner ? 'owner' : 'administration' ?> dashboard</div></div>
      <div class="topbar-actions">
        <a id="exportBtn" href="?export=<?= $tab==='inquiries' ? 'inquiries' : 'orders' ?>" class="btn btn-green" style="display:<?= in_array($tab,['orders','inquiries']) ? 'inline-flex' : 'none' ?>">⬇ Export CSV</a>
        <div class="account-menu" id="adminAccountMenu">
          <button type="button" class="account-menu-trigger" id="adminAccountTrigger" aria-expanded="false" aria-controls="adminAccountDropdown" aria-haspopup="true">
            <span class="account-avatar" aria-hidden="true"><?= h(strtoupper(substr((string)($_SESSION['admin_username'] ?? 'A'), 0, 1))) ?></span>
            <span class="account-menu-name"><?= h((string)($_SESSION['admin_username'] ?? 'Account')) ?></span>
            <span aria-hidden="true">⌄</span>
          </button>
          <div class="account-menu-dropdown" id="adminAccountDropdown" hidden>
            <div class="account-menu-identity">
              <strong><?= h((string)($_SESSION['admin_username'] ?? 'Account')) ?></strong>
              <small><?= h(ucfirst((string)($_SESSION['admin_role'] ?? 'admin'))) ?> account</small>
            </div>
            <a class="account-menu-logout" href="?logout=1">↩ Log out</a>
          </div>
        </div>
      </div>
    </div>

    <?php if ($dbError): ?><div class="msg-banner msg-error">⚠ <?= h($dbError) ?></div><?php endif ?>

    <!-- ── DASHBOARD PANEL ── -->
    <div id="panel-dashboard" class="panel <?= $tab==='dashboard'?'active':'' ?>">

      <!-- Top: Total Revenue (left) · Total Orders / Pending Orders / Inquiries (right) -->
      <div class="dash-top">
        <div class="stat-card rev-card">
          <div class="stat-label">Total revenue</div>
          <div class="stat-val">₱<?= number_format($totalRev, 2) ?></div>
          <div class="stat-sub">all-time</div>
          <div class="rev-meta">
            <div class="stat-label">Avg order value</div>
            <div class="rev-avg">₱<?= number_format($avgOrder, 2) ?></div>
            <div class="stat-sub">per order</div>
          </div>
        </div>
        <div class="dash-stack">
          <div class="stat-card">
            <div class="stat-label">Total orders</div>
            <div class="stat-val"><?= number_format($totalOrders) ?></div>
            <div class="stat-sub">all-time</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Pending orders</div>
            <div class="stat-val"><?= number_format($pendingOrders) ?></div>
            <div class="stat-sub">awaiting action</div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Inquiries</div>
            <div class="stat-val"><?= number_format($totalInq) ?></div>
            <div class="stat-sub"><?= $newInq ?> new / unread</div>
          </div>
        </div>
      </div>

      <!-- Bottom: charts -->
      <div class="chart-grid">
        <div class="chart-card">
          <h3>Orders by payment method</h3>
          <div class="chart-wrap">
            <canvas id="payChart" role="img" aria-label="Orders by payment method"></canvas>
          </div>
        </div>
        <div class="chart-card">
          <h3>Orders by status</h3>
          <div class="chart-wrap">
            <canvas id="statusChart" role="img" aria-label="Orders by status"></canvas>
          </div>
        </div>
      </div>
      <div class="chart-full">
        <h3>Inquiry types</h3>
        <div class="chart-wrap">
          <canvas id="typeChart" role="img" aria-label="Inquiry types breakdown"></canvas>
        </div>
      </div>
    </div>

    <!-- ── ORDERS PANEL ── -->
    <div id="panel-orders" class="panel <?= $tab==='orders'?'active':'' ?>">
      <?php if ($msg && $tab === 'orders'): ?><div class="msg-banner<?= $msgIsError ? ' msg-error' : '' ?>"><?= $msgIsError ? '⚠' : '✓' ?> <?= h($msg) ?></div><?php endif ?>
      <div class="filter-bar">
        <form method="GET" style="display:contents">
          <input type="hidden" name="tab" value="orders">
          <input type="text" name="oq" value="<?= h($oSearch) ?>" placeholder="Search name, email, order #…">
          <input type="date" name="od" value="<?= h($oDate) ?>" aria-label="Filter orders by date">
          <select name="op" aria-label="Filter orders by payment">
            <option value="">All payment methods</option>
            <option value="gcash" <?= $oPayment==='gcash'?'selected':'' ?>>GCash</option>
            <option value="cod" <?= $oPayment==='cod'?'selected':'' ?>>Cash on Delivery</option>
            <option value="card" <?= $oPayment==='card'?'selected':'' ?>>Credit / Debit Card</option>
          </select>
          <select name="os">
            <option value="">All statuses</option>
            <?php foreach(['pending','processing','completed','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $oStatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach ?>
          </select>
          <button type="submit" class="btn">Filter</button>
          <?php if ($oSearch || $oStatus || $oDate || $oPayment): ?>
            <a href="?tab=orders" class="btn">Clear</a>
          <?php endif ?>
        </form>
      </div>

      <div class="tbl-card">
        <?php if (!$orders): ?>
          <div class="empty">
            <div class="empty-icon">🛍</div>
            No orders yet — they appear here when customers check out.
          </div>
        <?php else: ?>
        <table>
          <thead><tr>
            <th></th>
            <th>Order #</th>
            <th>Customer</th>
            <th>Address</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Status</th>
            <th>Date</th>
          </tr></thead>
          <tbody>
          <?php foreach ($orders as $o):
            $itemStr = orderItemsText((string)($o['items'] ?? ''));
            $rowId = 'row-' . $o['id'];
          ?>
            <tr onclick="toggleRow('<?= $rowId ?>')" style="cursor:pointer">
              <td style="width:32px;text-align:center">
                <button class="expand-toggle" aria-label="Expand order">+</button>
              </td>
              <td class="td-mono"><?= h($o['order_num']) ?></td>
              <td>
                <strong><?= h($o['name']) ?></strong><br>
                <span style="font-size:12px;color:var(--muted)"><?= h($o['email']) ?></span>
                <?php if($o['phone']): ?>
                  <br><span style="font-size:12px;color:var(--muted)"><?= h($o['phone']) ?></span>
                <?php endif ?>
              </td>
              <td class="addr-cell">
                <strong><?= h($o['address']) ?></strong>
                <?= h($o['city']) ?>, <?= h($o['postal']) ?><br>
                <?= h($o['country']) ?>
              </td>
              <td style="font-weight:600">₱<?= number_format($o['total'], 2) ?></td>
              <td style="font-size:12px;color:var(--muted)"><?= h($o['payment']) ?><?= $o['gcash_ref'] ? '<br><span style="font-family:var(--mono);font-size:11px">Ref: '.h($o['gcash_ref']).'</span>' : '' ?></td>
              <td>
                <form method="POST" class="status-form" onclick="event.stopPropagation()">
                  <input type="hidden" name="update_status" value="1">
                  <input type="hidden" name="type" value="order">
                  <input type="hidden" name="id" value="<?= $o['id'] ?>">
                  <select name="status" onchange="this.form.submit()">
                    <?php foreach(['pending','processing','completed','cancelled'] as $s): ?>
                      <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach ?>
                  </select>
                </form>
              </td>
              <td style="font-size:12px;color:var(--muted);white-space:nowrap">
                <?= date('M j, Y', strtotime($o['created_at'])) ?><br>
                <?= date('g:i A', strtotime($o['created_at'])) ?><br>
                <strong>Est. delivery:</strong> <?= $o['estimated_delivery'] ? date('M j, Y', strtotime($o['estimated_delivery'])) : '—' ?>
              </td>
            </tr>
            <tr id="<?= $rowId ?>" class="expand-row">
              <td colspan="8">
                <div class="expand-inner">
                  <div class="expand-field">
                    <strong>Items ordered</strong>
                    <span><?= h($itemStr) ?></span>
                  </div>
                  <?php if ($o['notes']): ?>
                  <div class="expand-field">
                    <strong>Notes</strong>
                    <span><?= h($o['notes']) ?></span>
                  </div>
                  <?php endif ?>
                </div>
              </td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
        <?php endif ?>
      </div>
      <?= pagerHtml($oPage, $oPages, $oTotal, $perPage, 'opg', 'orders') ?>
    </div>

    <!-- ── INQUIRIES PANEL ── -->
    <div id="panel-inquiries" class="panel <?= $tab==='inquiries'?'active':'' ?>">
      <?php if ($msg && $tab === 'inquiries'): ?><div class="msg-banner<?= $msgIsError ? ' msg-error' : '' ?>"><?= $msgIsError ? '⚠' : '✓' ?> <?= h($msg) ?></div><?php endif ?>
      <div class="filter-bar">
        <form method="GET" style="display:contents">
          <input type="hidden" name="tab" value="inquiries">
          <input type="text" name="iq" value="<?= h($iSearch) ?>" placeholder="Search name, email, type…">
          <input type="date" name="idate" value="<?= h($iDate) ?>" aria-label="Filter inquiries by date">
          <select name="it" aria-label="Filter inquiries by type">
            <option value="">All types</option>
            <?php foreach ($inquiryTypes as $type): ?>
              <option value="<?= h((string)$type) ?>" <?= $iType===(string)$type?'selected':'' ?>><?= h((string)$type) ?></option>
            <?php endforeach ?>
          </select>
          <select name="is">
            <option value="">All statuses</option>
            <?php foreach(['new','replied','closed'] as $s): ?>
              <option value="<?= $s ?>" <?= $iStatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach ?>
          </select>
          <button type="submit" class="btn">Filter</button>
          <?php if ($iSearch || $iStatus || $iDate || $iType): ?>
            <a href="?tab=inquiries" class="btn">Clear</a>
          <?php endif ?>
        </form>
      </div>

      <div class="tbl-card">
        <?php if (!$inquiries): ?>
          <div class="empty">
            <div class="empty-icon">💬</div>
            No inquiries yet — they appear here when customers submit the inquiry form.
          </div>
        <?php else: ?>
        <table>
          <thead><tr>
            <th></th>
            <th>Name</th>
            <th>Contact</th>
            <th>Type</th>
            <th>Qty</th>
            <th>Needed by</th>
            <th>Status</th>
            <th>Date</th>
          </tr></thead>
          <tbody>
          <?php foreach ($inquiries as $q):
            $rowId = 'inq-' . $q['id'];
          ?>
            <tr onclick="toggleRow('<?= $rowId ?>')" style="cursor:pointer">
              <td style="width:32px;text-align:center">
                <button class="expand-toggle" aria-label="Expand inquiry">+</button>
              </td>
              <td><strong><?= h($q['name']) ?></strong></td>
              <td style="font-size:12px;color:var(--muted)">
                <?= h($q['email']) ?><br><?= h($q['phone']) ?>
              </td>
              <td><?= h($q['order_type']) ?></td>
              <td style="color:var(--muted)"><?= h($q['quantity'] ?: '—') ?></td>
              <td style="font-size:12px;color:var(--muted)"><?= h($q['needed_by'] ?: '—') ?></td>
              <td>
                <form method="POST" class="status-form" onclick="event.stopPropagation()">
                  <input type="hidden" name="update_status" value="1">
                  <input type="hidden" name="type" value="inquiry">
                  <input type="hidden" name="id" value="<?= $q['id'] ?>">
                  <select name="status" onchange="this.form.submit()">
                    <?php foreach(['new','replied','closed'] as $s): ?>
                      <option value="<?= $s ?>" <?= $q['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach ?>
                  </select>
                </form>
              </td>
              <td style="font-size:12px;color:var(--muted);white-space:nowrap">
                <?= date('M j, Y', strtotime($q['created_at'])) ?><br>
                <?= date('g:i A', strtotime($q['created_at'])) ?>
              </td>
            </tr>
            <tr id="<?= $rowId ?>" class="expand-row">
              <td colspan="8">
                <div class="expand-inner">
                  <div class="expand-field">
                    <strong>Colors / preferences</strong>
                    <span><?= h($q['colors'] ?: '—') ?></span>
                  </div>
                  <div class="expand-field">
                    <strong>Details</strong>
                    <span><?= h($q['details']) ?></span>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
        <?php endif ?>
      </div>
      <?= pagerHtml($iPage, $iPages, $iTotal, $perPage, 'ipg', 'inquiries') ?>
    </div>

    <!-- ── CUSTOMER REVIEWS PANEL ── -->
    <div id="panel-reviews" class="panel <?= $tab==='reviews'?'active':'' ?>">
      <div class="stat-card" style="margin-bottom:16px;max-width:260px">
        <div class="stat-label">Average rating</div>
        <div class="stat-val"><?= number_format($reviewAverage, 1) ?> / 5</div>
        <div class="stat-sub"><?= number_format($reviewTotal) ?> customer reviews</div>
      </div>
      <div class="filter-bar">
        <form method="GET" style="display:contents">
          <input type="hidden" name="tab" value="reviews">
          <input type="search" name="rq" value="<?= h($reviewSearch) ?>" placeholder="Search review, order # or customer…">
          <select name="rr" aria-label="Filter reviews by rating">
            <option value="0" <?= $reviewRating===0?'selected':'' ?>>All ratings</option>
            <?php for ($stars = 5; $stars >= 1; $stars--): ?>
              <option value="<?= $stars ?>" <?= $reviewRating===$stars?'selected':'' ?>><?= $stars ?> star<?= $stars===1?'':'s' ?></option>
            <?php endfor ?>
          </select>
          <button type="submit" class="btn">Filter</button>
          <?php if ($reviewSearch !== '' || $reviewRating > 0): ?>
            <a href="?tab=reviews" class="btn">Clear</a>
          <?php endif ?>
        </form>
      </div>
      <div class="tbl-card">
        <div class="panel-head">
          <h3>Recent reviews <span class="count"><?= count($reviews) ?> shown / <?= number_format($reviewTotal) ?></span></h3>
        </div>
        <?php if (!$reviews): ?>
          <div class="empty"><div class="empty-icon">★</div><?= $reviewTotal ? 'No reviews match these filters.' : 'No customer reviews yet.' ?></div>
        <?php else: ?>
          <table>
            <thead><tr><th>Order #</th><th>Customer</th><th>Rating</th><th>Review</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($reviews as $review): ?>
              <tr>
                <td class="td-mono"><?= h($review['order_num']) ?></td>
                <td><strong><?= h($review['name']) ?></strong><br><span style="font-size:12px;color:var(--muted)"><?= h($review['email']) ?></span></td>
                <td style="white-space:nowrap;color:#b97816"><?= str_repeat('★', (int)$review['rating']) ?><?= str_repeat('☆', 5 - (int)$review['rating']) ?></td>
                <td style="white-space:pre-wrap;min-width:240px"><?= h($review['review_text']) ?></td>
                <td style="font-size:12px;color:var(--muted);white-space:nowrap"><?= date('M j, Y', strtotime($review['created_at'])) ?></td>
              </tr>
            <?php endforeach ?>
            </tbody>
          </table>
        <?php endif ?>
      </div>
    </div>

    <!-- ── PRODUCT MANAGEMENT PANEL (admin + owner) ── -->
    <div id="panel-products" class="panel <?= $tab==='products'?'active':'' ?>">
      <?php if ($msg): ?><div class="msg-banner<?= $msgIsError ? ' msg-error' : '' ?>"><?= $msgIsError ? '⚠' : '✓' ?> <?= h($msg) ?></div><?php endif ?>

      <div class="filter-bar">
        <form method="GET" style="display:contents">
          <input type="hidden" name="tab" value="products">
          <input type="text" name="pq" value="<?= h($prSearch) ?>" placeholder="Search product name or description…">
          <span class="sort-label">Sort by</span>
          <select name="ps" onchange="this.form.submit()" aria-label="Sort products">
            <?php foreach ($productSorts as $key => $def): ?>
              <option value="<?= h($key) ?>" <?= $prSort===$key?'selected':'' ?>><?= h($def[0]) ?></option>
            <?php endforeach ?>
          </select>
          <select name="pc" onchange="this.form.submit()" aria-label="Filter by category">
            <option value="">All categories</option>
            <?php foreach ($productCategories as $c): ?>
              <option value="<?= $c ?>" <?= $prCat===$c?'selected':'' ?>><?= ucfirst($c) ?></option>
            <?php endforeach ?>
          </select>
          <select name="pa" onchange="this.form.submit()" aria-label="Filter by availability">
            <option value="">All availability</option>
            <option value="available" <?= $prAvail==='available'?'selected':'' ?>>Available</option>
            <option value="out_of_stock" <?= $prAvail==='out_of_stock'?'selected':'' ?>>Out of stock</option>
          </select>
          <button type="submit" class="btn">Search</button>
          <?php if ($prSearch !== '' || $prCat !== '' || $prAvail !== '' || $prSort !== 'id'): ?>
            <a href="?tab=products" class="btn">Clear</a>
          <?php endif ?>
        </form>
      </div>

      <div class="tbl-card scroll-x">
        <div class="panel-head">
          <h3>🧶 Product Management — Edit Items <span class="count"><?= count($allProducts) ?> shown</span></h3>
          <p>Update names, prices, categories, stock and descriptions shown on the storefront. Setting stock to 0 marks the item out of stock.</p>
        </div>
        <?php if (!$allProducts): ?>
          <div class="empty">
            <div class="empty-icon">📦</div>
            <?php if ($totalProducts === 0): ?>
              No products found. Run <code>schema2_updated.sql</code> to create and seed the <code>products</code> table.
            <?php else: ?>
              No products match your search or filters.
            <?php endif ?>
          </div>
        <?php else: ?>
        <table class="pt">
          <colgroup>
            <col style="width:64px"><col style="width:52px"><col style="width:190px"><col style="width:130px">
            <col style="width:105px"><col style="width:85px"><col style="width:130px"><col><col style="width:90px">
          </colgroup>
          <thead><tr>
            <th>Image</th>
            <th>ID</th>
            <th>Name</th>
            <th>Category</th>
            <th class="col-num">Price (₱)</th>
            <th class="col-num">Stock</th>
            <th>Status</th>
            <th>Description</th>
            <th>Action</th>
          </tr></thead>
          <tbody>
          <?php foreach ($allProducts as $p): $pid = (int)$p['id']; ?>
            <tr>
              <td>
                <?php if (!empty($p['image'])): ?>
                  <img class="prod-thumb" src="../<?= h($p['image']) ?>" alt="">
                <?php endif ?>
              </td>
              <td><?= $pid ?></td>
              <td><input type="text" name="name" form="pf-<?= $pid ?>" value="<?= h($p['name']) ?>" required></td>
              <td>
                <select name="category" form="pf-<?= $pid ?>">
                  <?php foreach ($productCategories as $c): ?>
                    <option value="<?= $c ?>" <?= $p['category']===$c?'selected':'' ?>><?= ucfirst($c) ?></option>
                  <?php endforeach ?>
                </select>
              </td>
              <td class="col-num"><input type="number" name="price" form="pf-<?= $pid ?>" value="<?= h((string)$p['price']) ?>" step="0.01" min="0" required></td>
              <td class="col-num"><input type="number" name="stock" id="stock-<?= $pid ?>" form="pf-<?= $pid ?>" value="<?= (int)($p['stock'] ?? 0) ?>" step="1" min="0" oninput="syncStock(<?= $pid ?>)" required></td>
              <td>
                <select name="stock_status" id="stat-<?= $pid ?>" form="pf-<?= $pid ?>">
                  <option value="available" <?= ($p['status'] ?? '')==='available'?'selected':'' ?>>Available</option>
                  <option value="out_of_stock" <?= ($p['status'] ?? '')==='out_of_stock'?'selected':'' ?>>Out of stock</option>
                </select>
              </td>
              <td><input type="text" name="description" form="pf-<?= $pid ?>" value="<?= h((string)$p['description']) ?>"></td>
              <td>
                <form method="POST" id="pf-<?= $pid ?>">
                  <input type="hidden" name="update_product" value="1">
                  <input type="hidden" name="product_id" value="<?= $pid ?>">
                  <button type="submit" class="btn btn-sm btn-green">Save</button>
                </form>
              </td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
        <?php endif ?>
      </div>
    </div>

    <!-- ── QUERY PERFORMANCE PANEL (admin + owner) ── -->
    <!-- Sales Analytics: summary cards and navigable reports -->
    <div id="panel-analytics" class="panel <?= $tab==='analytics'?'active':'' ?>">
      <?php if ($analyticsError): ?><div class="msg-banner msg-error"><?= h($analyticsError) ?></div><?php endif ?>

      <h3 class="analytics-section-title">Order Status Overview</h3>
      <div class="stat-grid analytics-status-grid">
        <?php foreach ($analyticsStatuses as $statusRow): ?>
          <div class="stat-card">
            <div class="stat-label"><?= h((string)$statusRow['status_name']) ?> orders</div>
            <div class="stat-val"><?= number_format((int)$statusRow['total_orders']) ?></div>
            <div class="stat-sub">Distinct orders · last ETL refresh</div>
          </div>
        <?php endforeach ?>
        <?php if (!$analyticsStatuses): ?><div class="stat-card"><div class="stat-label">Order status</div><div class="stat-val">—</div><div class="stat-sub">No warehouse data yet</div></div><?php endif ?>
      </div>

      <?php
        $topLocation = $analyticsLocations[0] ?? null;
        $topProduct = $analyticsProducts[0] ?? null;
        $topCustomer = $analyticsCustomers[0] ?? null;
      ?>
      <h3 class="analytics-section-title">Performance Highlights</h3>
      <div class="stat-grid analytics-highlight-grid">
        <div class="stat-card">
          <div class="stat-label">Highest Sales Location</div>
          <div class="stat-val analytics-highlight-name"><?= h($topLocation ? trim((string)$topLocation['city'].', '.(string)$topLocation['country'], ', ') : '—') ?></div>
          <div class="stat-sub"><?= $topLocation ? '₱'.number_format((float)$topLocation['completed_revenue'],2).' · '.number_format((int)$topLocation['completed_orders']).' completed orders' : 'No sales yet' ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Best-Selling Product</div>
          <div class="stat-val analytics-highlight-name"><?= h((string)($topProduct['product_name'] ?? '—')) ?></div>
          <div class="stat-sub"><?= $topProduct ? number_format((int)$topProduct['units_sold']).' units sold' : 'No sales yet' ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Top-Spending Customer</div>
          <div class="stat-val analytics-highlight-name"><?= h((string)($topCustomer['full_name'] ?? '—')) ?></div>
          <div class="stat-sub"><?= $topCustomer ? '₱'.number_format((float)$topCustomer['completed_spending'],2).' · '.number_format((int)$topCustomer['completed_orders']).' completed orders' : 'No completed orders yet' ?></div>
        </div>
      </div>

      <nav class="analytics-tabs" aria-label="Sales analytics reports">
        <button type="button" class="analytics-tab active" data-report="status" onclick="showAnalyticsReport('status')">Order Status</button>
        <button type="button" class="analytics-tab" data-report="location" onclick="showAnalyticsReport('location')">Locations</button>
        <button type="button" class="analytics-tab" data-report="product" onclick="showAnalyticsReport('product')">Products</button>
        <button type="button" class="analytics-tab" data-report="customer" onclick="showAnalyticsReport('customer')">Customers</button>
      </nav>

      <?php
        $analyticsTables = [
          'status' => ['Order Status Summary',['Status','Orders'],$analyticsStatuses,['status_name','total_orders']],
          'location' => ['Sales by Location',['City','Postal','Country','Orders','Completed orders','Completed revenue'],$analyticsLocations,['city','postal_code','country','total_orders','completed_orders','completed_revenue']],
          'product' => ['Best-Selling Crochet Products',['Product','Category','Units sold','Completed orders','Completed revenue'],$analyticsProducts,['product_name','category','units_sold','completed_orders','completed_revenue']],
          'customer' => ['Top-Spending Customers',['Customer','Completed orders','Completed spending'],$analyticsCustomers,['full_name','completed_orders','completed_spending']]
        ];
      ?>
      <?php foreach ($analyticsTables as $reportId => [$heading,$headings,$rows,$keys]): ?>
        <section class="analytics-report <?= $reportId==='status'?'active':'' ?>" id="analytics-report-<?= h($reportId) ?>" data-report="<?= h($reportId) ?>">
          <div class="analytics-report-heading">
            <h3><?= h($heading) ?></h3>
            <input type="search" class="analytics-search" aria-label="Search <?= h($heading) ?>" placeholder="Search this report..." oninput="filterAnalyticsRows('<?= h($reportId) ?>')" />
          </div>
          <div class="analytics-table-wrap">
            <table class="analytics-table">
              <thead><tr><?php foreach ($headings as $headingCell): ?><th><?= h($headingCell) ?></th><?php endforeach ?></tr></thead>
              <tbody>
              <?php foreach ($rows as $record): ?>
                <tr><?php foreach ($keys as $key): ?>
                  <td><?php
                    $value = $record[$key] ?? '';
                    if (in_array($key, ['completed_revenue','completed_spending'], true)) echo '₱'.number_format((float)$value, 2);
                    elseif (in_array($key, ['total_orders','completed_orders','units_sold'], true)) echo number_format((int)$value);
                    else echo h((string)$value);
                  ?></td>
                <?php endforeach ?></tr>
              <?php endforeach ?>
              <?php if (!$rows): ?><tr class="analytics-empty"><td colspan="<?= count($headings) ?>">No warehouse data available yet.</td></tr><?php endif ?>
              </tbody>
            </table>
          </div>
          <div class="analytics-table-footer">
            <span class="analytics-row-count"></span>
            <div class="analytics-pagination">
              <button type="button" class="btn btn-sm" onclick="analyticsPage('<?= h($reportId) ?>',-1)">Previous</button>
              <span class="analytics-page-label"></span>
              <button type="button" class="btn btn-sm" onclick="analyticsPage('<?= h($reportId) ?>',1)">Next</button>
            </div>
          </div>
        </section>
      <?php endforeach ?>
    </div>

    <div id="panel-performance" class="panel <?= $tab==='performance'?'active':'' ?>">
      <?php if ($exErr): ?><div class="msg-banner msg-error">⚠ <?= h($exErr) ?></div><?php endif ?>

      <div class="filter-bar">
        <form method="GET" style="display:contents">
          <input type="hidden" name="tab" value="performance">
          <span class="sort-label">Query</span>
          <select name="xq" aria-label="Query to analyse" style="min-width:280px">
            <?php foreach ($explainPresets as $key => $label): ?>
              <option value="<?= h($key) ?>" <?= $exKey===$key?'selected':'' ?>><?= h($label) ?></option>
            <?php endforeach ?>
          </select>
          <button type="submit" name="xm" value="explain" class="btn">EXPLAIN</button>
          <button type="submit" name="xm" value="analyze" class="btn btn-green">ANALYZE</button>
        </form>
      </div>

      <?php if ($exSummary): ?>
      <div class="stat-grid">
        <?php foreach ($exSummary as [$sLabel, $sValue, $sSub]): ?>
        <div class="stat-card">
          <div class="stat-label"><?= h($sLabel) ?></div>
          <div class="stat-val perf-val"><?= h($sValue) ?></div>
          <div class="stat-sub"><?= h($sSub) ?></div>
        </div>
        <?php endforeach ?>
      </div>

      <div class="tbl-card">
        <div class="panel-head">
          <h3>🔎 Statement</h3>
          <p>The read-only query that was analysed.</p>
        </div>
        <pre class="plan-box"><?= h($exSql) ?></pre>
      </div>

      <div class="tbl-card scroll-x section-gap">
        <div class="panel-head">
          <h3>⚡ <?= $exMode==='analyze' ? 'ANALYZE' : 'EXPLAIN' ?> result</h3>
          <p><?= $exMode==='analyze'
              ? 'The query was run. Each table shows the estimated rows next to the rows and time actually measured (r_rows, r_total_time_ms).'
              : 'How MariaDB plans to run the query. Nothing was executed.' ?></p>
        </div>
        <?php if ($exMode === 'analyze'): ?>
          <pre class="plan-box"><?= h($exPlanText) ?></pre>
        <?php else: ?>
          <table>
            <thead><tr>
              <?php foreach (array_keys($exRows[0]) as $col): ?><th><?= h((string)$col) ?></th><?php endforeach ?>
            </tr></thead>
            <tbody>
              <?php foreach ($exRows as $r): ?>
              <tr><?php foreach ($r as $v): ?><td class="td-mono"><?= $v === null ? 'NULL' : h((string)$v) ?></td><?php endforeach ?></tr>
              <?php endforeach ?>
            </tbody>
          </table>
        <?php endif ?>
      </div>
      <?php elseif (!$exErr): ?>
      <div class="tbl-card">
        <div class="empty">
          <div class="empty-icon">⚡</div>
          Pick a query, then run <strong>EXPLAIN</strong> to see the plan, or <strong>ANALYZE</strong> to also run it and measure real timings.
        </div>
      </div>
      <?php endif ?>
    </div>

    <?php if ($isOwner): ?>
    <!-- ── ADMINISTRATION PANEL (owner-role only) ── -->
    <div id="panel-administration" class="panel <?= $tab==='administration'?'active':'' ?>">
      <?php if ($msg): ?><div class="msg-banner<?= $msgIsError ? ' msg-error' : '' ?>"><?= $msgIsError ? '⚠' : '✓' ?> <?= h($msg) ?></div><?php endif ?>
      <?php if ($adminError): ?><div class="msg-banner msg-error">⚠ <?= h($adminError) ?></div><?php endif ?>

      <div class="filter-bar">
        <form method="GET" style="display:contents">
          <input type="hidden" name="tab" value="administration">
          <input type="text" name="aq" value="<?= h($aSearch) ?>" placeholder="Search username, full name or email…">
          <button type="submit" class="btn">Search</button>
          <?php if ($aSearch !== ''): ?>
            <a href="?tab=administration" class="btn">Clear</a>
          <?php endif ?>
        </form>
      </div>

      <div class="tbl-card scroll-x">
        <div class="panel-head">
          <h3>👑 Owners &amp; Admins <span class="count"><?= number_format($sTotal) ?></span></h3>
          <p>Owner-only. Staff accounts that can log in to this dashboard.</p>
        </div>
        <?= accountsTable($staffUsers, $allRoles, $aSearch !== '' ? 'No owner or admin accounts match your search.' : 'No owner or admin accounts found.') ?>
      </div>
      <?= pagerHtml($sPage, $sPages, $sTotal, $perPage, 'spg', 'administration') ?>

      <div class="tbl-card scroll-x section-gap">
        <div class="panel-head">
          <h3>👥 Users <span class="count"><?= number_format($uTotal) ?></span></h3>
          <p>Regular customer accounts. Promote a user to <strong>admin</strong> or <strong>owner</strong> with Change role.</p>
        </div>
        <?= accountsTable($plainUsers, $allRoles, $aSearch !== '' ? 'No user accounts match your search.' : 'No user accounts found.') ?>
      </div>
      <?= pagerHtml($uPage, $uPages, $uTotal, $perPage, 'upg', 'administration') ?>
    </div>
    <?php endif ?>

  </main>
</div>

<script>
function syncStock(id){
  const qty=document.getElementById('stock-'+id), st=document.getElementById('stat-'+id);
  if(!qty||!st)return;
  st.value=(parseInt(qty.value,10)||0)<=0?'out_of_stock':'available';
}


/* Analytics report navigation, local search and paging (no extra SQL requests) */
const analyticsPages = {};
function showAnalyticsReport(name){
  document.querySelectorAll('.analytics-report').forEach(el=>el.classList.toggle('active',el.dataset.report===name));
  document.querySelectorAll('.analytics-tab').forEach(el=>el.classList.toggle('active',el.dataset.report===name));
  filterAnalyticsRows(name);
}
function filterAnalyticsRows(name){
  const panel=document.getElementById('analytics-report-'+name);
  if(!panel)return;
  analyticsPages[name]=1;
  renderAnalyticsRows(name);
}
function analyticsPage(name,delta){
  analyticsPages[name]=Math.max(1,(analyticsPages[name]||1)+delta);
  renderAnalyticsRows(name);
}
function renderAnalyticsRows(name){
  const panel=document.getElementById('analytics-report-'+name);
  if(!panel)return;
  const term=(panel.querySelector('.analytics-search')?.value||'').toLocaleLowerCase().trim();
  const rows=[...panel.querySelectorAll('tbody tr:not(.analytics-empty)')];
  const matching=rows.filter(row=>row.textContent.toLocaleLowerCase().includes(term));
  const size=10, pages=Math.max(1,Math.ceil(matching.length/size));
  const page=Math.min(analyticsPages[name]||1,pages);
  analyticsPages[name]=page;
  rows.forEach(row=>row.hidden=true);
  matching.slice((page-1)*size,page*size).forEach(row=>row.hidden=false);
  const footer=panel.querySelector('.analytics-table-footer');
  footer.querySelector('.analytics-row-count').textContent=matching.length+' matching records';
  footer.querySelector('.analytics-page-label').textContent='Page '+page+' of '+pages;
  const buttons=footer.querySelectorAll('button');
  buttons[0].disabled=page<=1;
  buttons[1].disabled=page>=pages;
  const empty=panel.querySelector('.analytics-empty');
  if(empty)empty.hidden=!!term;
}
document.addEventListener('DOMContentLoaded',()=>{
  ['status','location','product','customer'].forEach(renderAnalyticsRows);
});

function toggleRow(id){
  const row=document.getElementById(id);
  if(!row)return;
  row.classList.toggle('open');
  const btn=row.previousElementSibling?.querySelector('.expand-toggle');
  if(btn)btn.textContent=row.classList.contains('open')?'−':'+';
}

const PAGE_TITLES={dashboard:'Dashboard',orders:'Orders',inquiries:'Inquiries',reviews:'Customer Reviews',products:'Product Management',analytics:'Sales Analytics',performance:'Query Performance',administration:'Administration'};


// Account menu: keyboard-friendly and closes on outside click / Escape.
(function(){
  const trigger=document.getElementById('adminAccountTrigger');
  const dropdown=document.getElementById('adminAccountDropdown');
  if(!trigger||!dropdown)return;
  function setOpen(open){dropdown.hidden=!open;trigger.setAttribute('aria-expanded',String(open));}
  trigger.addEventListener('click',()=>setOpen(dropdown.hidden));
  document.addEventListener('click',e=>{if(!trigger.contains(e.target)&&!dropdown.contains(e.target))setOpen(false);});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!dropdown.hidden){setOpen(false);trigger.focus();}});
})();

function switchTab(name){
  document.querySelectorAll('.panel').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
  document.getElementById('panel-'+name).classList.add('active');
  document.querySelector('[onclick="switchTab(\''+name+'\')"]').classList.add('active');
  document.getElementById('pageTitle').textContent=PAGE_TITLES[name]||name;
  const exportBtn=document.getElementById('exportBtn');
  if(exportBtn){
    if(name==='orders') exportBtn.href='?export=orders';
    else if(name==='inquiries') exportBtn.href='?export=inquiries';
    exportBtn.style.display=(name==='orders'||name==='inquiries')?'inline-flex':'none';
  }
  if(name==='dashboard')buildCharts();
  history.replaceState(null,'','?tab='+name);
}

<?php if ($tab === 'dashboard'): ?>
window.addEventListener('load', buildCharts);
<?php endif ?>

function buildCharts(){
  const payData  = <?= json_encode(array_column($payRows,  'cnt')) ?>;
  const payLabels= <?= json_encode(array_column($payRows,  'payment')) ?>;
  const stLabels = <?= json_encode(array_column($stRows,   'status')) ?>;
  const stData   = <?= json_encode(array_column($stRows,   'cnt')) ?>;
  const tpLabels = <?= json_encode(array_column($typeRows, 'order_type')) ?>;
  const tpData   = <?= json_encode(array_column($typeRows, 'cnt')) ?>;

  if(window._charts){window._charts.forEach(c=>c.destroy());} window._charts=[];

  window._charts.push(new Chart(document.getElementById('payChart'),{
    type:'doughnut',
    data:{labels:payLabels,datasets:[{data:payData,backgroundColor:['#1D9E75','#378ADD','#D4537E','#BA7517'],borderWidth:0,hoverOffset:4}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:10,padding:10}}}}
  }));
  window._charts.push(new Chart(document.getElementById('statusChart'),{
    type:'bar',
    data:{labels:stLabels,datasets:[{data:stData,backgroundColor:['#FAC775','#85B7EB','#97C459','#F09595'],borderRadius:6,borderWidth:0}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{ticks:{stepSize:1},grid:{color:'rgba(0,0,0,.05)'}},x:{grid:{display:false}}}}
  }));
  window._charts.push(new Chart(document.getElementById('typeChart'),{
    type:'bar',
    data:{labels:tpLabels,datasets:[{data:tpData,backgroundColor:'#AFA9EC',borderRadius:6,borderWidth:0}]},
    options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{ticks:{stepSize:1},grid:{color:'rgba(0,0,0,.05)'}},y:{grid:{display:false}}}}
  }));
}
</script>
<?php endif ?>
</body>
</html>
