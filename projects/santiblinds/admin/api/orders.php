<?php
// ============================================================
//  Santi Blinds — Admin API: orders
//  GET  ?search=&status=&from=&to=&payment_method=&archived= -> filtered order list
//  GET  ?id=12             -> one order with its items + sale
//  POST {id,status,payment_method} -> update status; a Completed
//        order is recorded in `sale`, any other status removes it.
//  Location: admin/api/orders.php
// ============================================================
require_once __DIR__ . '/config.php';
requireLogin();
$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Older database installations may not have the optional order archive column.
$hasArchiveColumn = (bool)$pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'is_archived'")->fetchColumn();
$archiveSelect = $hasArchiveColumn ? 'MAX(o.is_archived) AS is_archived' : '0 AS is_archived';

const PAYMENT_METHODS = ['Cash', 'GCash', 'Bank Transfer'];

if ($method === 'GET') {

    // ---- one order ----
    if (!empty($_GET['id'])) {
        $id = (int)$_GET['id'];

        $h = $pdo->prepare(
            "SELECT o.order_id, c.full_name, c.phone, c.email, c.address,
                    CASE WHEN MAX(o.status) IN ('Ready', 'Ready for Install') THEN 'Completed'
                         ELSE MAX(o.status) END AS status,
                    MIN(o.order_date) AS order_date,
                    SUM(o.total_amount) AS amount, MAX(o.quotation_id) AS quotation_id,
                    $archiveSelect
             FROM orders o JOIN customer c ON c.customer_id = o.customer_id
             WHERE o.order_id = :id
             GROUP BY o.order_id, c.full_name, c.phone, c.email, c.address"
        );
        $h->execute([':id' => $id]);
        $order = $h->fetch();
        if (!$order) fail(404, 'Order not found.');
        $order['quotation_ref'] = $order['quotation_id'] ? sprintf('QT-%05d', (int)$order['quotation_id']) : null;
        unset($order['quotation_id']);

        $i = $pdo->prepare(
            "SELECT p.name AS product, p.blind_type, m.name AS material,
                    col.name AS color, col.hex_code,
                    o.width_cm, o.height_cm, o.quantity, o.unit_price, o.total_amount
             FROM orders o
             JOIN product  p   ON p.product_id   = o.product_id
             JOIN material m   ON m.material_id  = o.material_id
             JOIN color    col ON col.color_id   = o.color_id
             WHERE o.order_id = :id
             ORDER BY o.product_id, o.material_id, o.color_id"
        );
        $i->execute([':id' => $id]);

        $s = $pdo->prepare("SELECT payment_method, amount_paid, sale_date FROM sale WHERE order_id = :id");
        $s->execute([':id' => $id]);

        echo json_encode([
            'success' => true,
            'order'   => $order,
            'items'   => $i->fetchAll(),
            'sale'    => $s->fetch() ?: null,
        ]);
        exit;
    }

    // ---- list ----
    $where = []; $params = [];
    $archiveFilter = (string)($_GET['archived'] ?? '0');
    if (!in_array($archiveFilter, ['0', '1', 'all'], true)) fail(422, 'Invalid archive filter.');
    if ($archiveFilter !== 'all') {
        if ($hasArchiveColumn) {
            $where[] = 'o.is_archived = :is_archived';
            $params[':is_archived'] = (int)$archiveFilter;
        } elseif ($archiveFilter === '1') {
            // No archive column means all existing orders are active.
            $where[] = '1 = 0';
        }
    }
    $statusFilter = (string)($_GET['status'] ?? '');
    if ($statusFilter !== '') {
        if (!in_array($statusFilter, ORDER_STATUSES, true)) fail(422, 'Invalid order status filter.');
        if ($statusFilter === 'Completed') {
            $where[] = "o.status IN ('Completed', 'Ready', 'Ready for Install')";
        } else {
            $where[] = 'o.status = :st';
            $params[':st'] = $statusFilter;
        }
    }
    $from = trim((string)($_GET['from'] ?? ''));
    $to = trim((string)($_GET['to'] ?? ''));
    $isDate = static function (string $value): bool {
        if ($value === '') return true;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return false;
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    };
    if (!$isDate($from) || !$isDate($to)) fail(422, 'Dates must use YYYY-MM-DD format.');
    if ($from !== '' && $to !== '' && $from > $to) fail(422, 'The start date must be on or before the end date.');
    if ($from !== '') {
        $where[] = 'o.order_date >= :from_date';
        $params[':from_date'] = $from . ' 00:00:00';
    }
    if ($to !== '') {
        $where[] = 'o.order_date < DATE_ADD(:to_date, INTERVAL 1 DAY)';
        $params[':to_date'] = $to;
    }
    $paymentFilter = (string)($_GET['payment_method'] ?? '');
    if ($paymentFilter !== '') {
        if (!in_array($paymentFilter, PAYMENT_METHODS, true)) fail(422, 'Invalid payment method filter.');
        $where[] = 's.payment_method = :payment_method';
        $params[':payment_method'] = $paymentFilter;
    }
    if (!empty($_GET['search'])) {
        $where[] = '(c.full_name LIKE :s1 OR p.name LIKE :s2 OR CAST(o.order_id AS CHAR) LIKE :s3)';
        $like = '%' . trim((string)$_GET['search']) . '%';
        $params[':s1'] = $like; $params[':s2'] = $like; $params[':s3'] = $like;
    }
    $sql = "SELECT o.order_id, c.full_name,
                   CASE WHEN MAX(o.status) IN ('Ready', 'Ready for Install') THEN 'Completed'
                        ELSE MAX(o.status) END AS status,
                   SUM(o.quantity) AS quantity, SUM(o.total_amount) AS amount,
                   MIN(o.order_date) AS order_date, MAX(o.quotation_id) AS quotation_id,
                   GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS products,
                   MAX(s.payment_method) AS payment_method,
                   $archiveSelect
            FROM orders o
            JOIN customer c ON c.customer_id = o.customer_id
            JOIN product  p ON p.product_id  = o.product_id
            LEFT JOIN sale s ON s.order_id = o.order_id";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= " GROUP BY o.order_id, c.full_name ORDER BY order_date DESC, o.order_id DESC";

    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
    $perPage = filter_var($_GET['per_page'] ?? 25, FILTER_VALIDATE_INT);
    if ($page === false || $page < 1 || $perPage === false || !in_array($perPage, [25, 50, 100, 200], true)) {
        fail(422, 'Invalid pagination values.');
    }
    $countSql = "SELECT COUNT(DISTINCT o.order_id)
                 FROM orders o
                 JOIN customer c ON c.customer_id = o.customer_id
                 JOIN product p ON p.product_id = o.product_id
                 LEFT JOIN sale s ON s.order_id = o.order_id";
    if ($where) $countSql .= ' WHERE ' . implode(' AND ', $where);
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;
    $sql .= " LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = array_map(function ($r) {
        // NULL quotation_id = a direct order placed from the design.
        $r['quotation_ref'] = $r['quotation_id'] ? sprintf('QT-%05d', (int)$r['quotation_id']) : null;
        unset($r['quotation_id']);
        return $r;
    }, $stmt->fetchAll());
    echo json_encode(['success' => true, 'orders' => $rows, 'pagination' => [
        'page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => $pages,
    ]]);
    exit;
}

if ($method === 'POST') {
    $b      = requestBody();
    $id     = (int)($b['id'] ?? 0);
    $status = (string)($b['status'] ?? '');
    $pay    = (string)($b['payment_method'] ?? 'Cash');

    if (!$id) fail(422, 'A valid order id is required.');
    if (!in_array($status, ORDER_STATUSES, true)) fail(422, 'Invalid status.');
    if (!in_array($pay, PAYMENT_METHODS, true)) $pay = 'Cash';

    $pdo->beginTransaction();
    try {
        $t = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) AS amount, COUNT(*) AS n FROM orders WHERE order_id = :id");
        $t->execute([':id' => $id]);
        $row = $t->fetch();
        if ((int)$row['n'] === 0) { $pdo->rollBack(); fail(404, 'Order not found.'); }

        // Inventory triggers reconcile stock for each order line on status changes.
        $pdo->prepare("UPDATE orders SET status = :s WHERE order_id = :id")
            ->execute([':s' => $status, ':id' => $id]);

        if ($status === 'Completed') {
            $pdo->prepare(
                "INSERT INTO sale (order_id, amount_paid, payment_method)
                 VALUES (:id, :amt, :pm)
                 ON DUPLICATE KEY UPDATE amount_paid = VALUES(amount_paid), payment_method = VALUES(payment_method)"
            )->execute([':id' => $id, ':amt' => $row['amount'], ':pm' => $pay]);
        } else {
            $pdo->prepare("DELETE FROM sale WHERE order_id = :id")->execute([':id' => $id]);
        }
        recordAudit('Order updated', sprintf('Order #%d status set to %s.', $id, $status));
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (($e->errorInfo[0] ?? $e->getCode()) === '45000') {
            fail(409, 'Insufficient inventory to reactivate this order.');
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    echo json_encode(['success' => true]);
    exit;
}

fail(405, 'Method not allowed.');
