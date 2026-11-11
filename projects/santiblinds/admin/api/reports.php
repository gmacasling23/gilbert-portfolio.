<?php
require_once __DIR__ . '/config.php';
requireLogin();
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail(405, 'Method not allowed.');

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$isDate = static function ($value): bool {
    if ($value === '') return true;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return false;
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};
if (!$isDate($from) || !$isDate($to)) fail(422, 'Dates must use YYYY-MM-DD format.');
if ($from !== '' && $to !== '' && $from > $to) fail(422, 'The start date must be on or before the end date.');

$where = [];
$params = [];
$status = (string)($_GET['status'] ?? '');
if ($status !== '') {
    if (!in_array($status, ORDER_STATUSES, true)) fail(422, 'Invalid order status filter.');
    if ($status === 'Completed') {
        $where[] = "o.status IN ('Completed', 'Ready', 'Ready for Install')";
    } else {
        $where[] = 'o.status = :status';
        $params[':status'] = $status;
    }
}
$search = trim((string)($_GET['search'] ?? ''));
if ($search !== '') {
    $where[] = '(c.full_name LIKE :customer OR p.name LIKE :product OR CAST(o.order_id AS CHAR) LIKE :order_id)';
    $like = '%' . $search . '%';
    $params[':customer'] = $like;
    $params[':product'] = $like;
    $params[':order_id'] = $like;
}
if ($from !== '') {
    $where[] = 'o.order_date >= :from_date';
    $params[':from_date'] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'o.order_date < DATE_ADD(:to_date, INTERVAL 1 DAY)';
    $params[':to_date'] = $to;
}

$baseSql = "SELECT o.order_id, c.full_name, c.address,
                   CASE WHEN MAX(o.status) IN ('Ready', 'Ready for Install') THEN 'Completed'
                        ELSE MAX(o.status) END AS status,
                   SUM(o.quantity) AS quantity,
                   SUM(o.total_amount) AS amount, MIN(o.order_date) AS order_date,
                   GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS products,
                   COALESCE((SELECT s.amount_paid FROM sale s WHERE s.order_id = o.order_id LIMIT 1),
                            SUM(o.total_amount)) AS paid_amount
            FROM orders o
            JOIN customer c ON c.customer_id = o.customer_id
            JOIN product p ON p.product_id = o.product_id";
if ($where) $baseSql .= ' WHERE ' . implode(' AND ', $where);
$baseSql .= ' GROUP BY o.order_id, c.full_name, c.address';
$summaryStmt = $pdo->prepare(
    "SELECT COUNT(*) AS orders,
            COALESCE(SUM(status = 'Completed'), 0) AS completed_orders,
            COALESCE(SUM(status = 'Pending'), 0) AS pending_orders,
            COALESCE(SUM(CASE WHEN status = 'Completed' THEN paid_amount ELSE 0 END), 0) AS revenue
     FROM ($baseSql) AS report_rows"
);
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();
$total = (int)$summary['orders'];
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$perPage = filter_var($_GET['per_page'] ?? 25, FILTER_VALIDATE_INT);
if ($page === false || $page < 1 || $perPage === false || !in_array($perPage, [25, 50, 100, 200], true)) {
    fail(422, 'Invalid pagination values.');
}
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$stmt = $pdo->prepare("$baseSql ORDER BY order_date DESC, order_id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

foreach ($rows as &$row) {
    $row['quotation_ref'] = null;
    $row['quantity'] = (int)$row['quantity'];
    $row['amount'] = (float)$row['amount'];
    $row['paid_amount'] = (float)$row['paid_amount'];
}
unset($row);

echo json_encode([
    'success' => true,
    'stats' => [
        'orders' => $total,
        'completed_orders' => (int)$summary['completed_orders'],
        'pending_orders' => (int)$summary['pending_orders'],
        'revenue' => (float)$summary['revenue'],
    ],
    'orders' => $rows,
    'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => $pages],
]);
