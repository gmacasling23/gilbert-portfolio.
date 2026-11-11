<?php
// admin/api/customers.php
//   GET                 -> customer list (with search)
//   GET ?id=N           -> one customer: profile, orders, inquiries, quotations (with items)
require_once __DIR__ . '/config.php';
requireLogin();
$pdo = getDB();

/**
 * All quotations of one customer, newest first, each with its items and
 * (if it was turned into an order) the order number it became.
 */
function customerQuotations(PDO $pdo, int $customerId): array {
    $q = $pdo->prepare(
        "SELECT quotation_id, total_amount, status, valid_until, created_at
         FROM quotation WHERE customer_id = :c ORDER BY quotation_id DESC"
    );
    $q->execute([':c' => $customerId]);
    $rows = $q->fetchAll();
    if (!$rows) return [];

    $ids = array_map('intval', array_column($rows, 'quotation_id'));
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $items = $pdo->prepare(
        "SELECT qi.quotation_id, p.name AS product, m.name AS material, col.name AS color,
                qi.width_cm, qi.height_cm, qi.quantity, qi.unit_price, qi.total_amount
         FROM quotation_item qi
         JOIN product  p   ON p.product_id  = qi.product_id
         JOIN material m   ON m.material_id = qi.material_id
         JOIN color    col ON col.color_id  = qi.color_id
         WHERE qi.quotation_id IN ($in)
         ORDER BY qi.quotation_item_id"
    );
    $items->execute($ids);
    $itemsByQ = [];
    foreach ($items->fetchAll() as $r) {
        $itemsByQ[(int)$r['quotation_id']][] = [
            'product'      => $r['product'],
            'material'     => $r['material'],
            'color'        => $r['color'],
            'width_cm'     => (float)$r['width_cm'],
            'height_cm'    => (float)$r['height_cm'],
            'quantity'     => (int)$r['quantity'],
            'unit_price'   => (float)$r['unit_price'],
            'total_amount' => (float)$r['total_amount'],
        ];
    }

    // Which order (if any) each quotation became, and that order's current status.
    $ord = $pdo->prepare(
        "SELECT quotation_id, MIN(order_id) AS order_id, MAX(status) AS order_status
         FROM orders WHERE quotation_id IN ($in) GROUP BY quotation_id"
    );
    $ord->execute($ids);
    $orderByQ = [];
    foreach ($ord->fetchAll() as $r) {
        $orderByQ[(int)$r['quotation_id']] = [
            'order_id'     => (int)$r['order_id'],
            'order_status' => $r['order_status'],
        ];
    }

    $now = time();
    return array_map(function ($r) use ($itemsByQ, $orderByQ, $now) {
        $id = (int)$r['quotation_id'];
        // Same rule as the customer side: "Expired" is derived from valid_until.
        if ($r['status'] === 'Ordered')                       $status = 'Ordered';
        elseif (strtotime((string)$r['valid_until']) < $now)  $status = 'Expired';
        else                                                  $status = 'Active';

        $lines = $itemsByQ[$id] ?? [];
        $ordered = $orderByQ[$id] ?? null;
        return [
            'quotation_id' => $id,
            'reference'    => sprintf('QT-%05d', $id),
            'status'       => $status,
            'total_amount' => (float)$r['total_amount'],
            'valid_until'  => $r['valid_until'],
            'created_at'   => $r['created_at'],
            'item_count'   => count($lines),
            'order_id'     => $ordered['order_id'] ?? null,
            'order_status' => $ordered['order_status'] ?? null,  // current status of that order (e.g. Cancelled)
            'items'        => $lines,
        ];
    }, $rows);
}

if (!empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT customer_id, full_name, email, phone, address, created_at FROM customer WHERE customer_id = :id");
    $stmt->execute([':id' => $id]);
    $customer = $stmt->fetch();
    if (!$customer) fail(404, 'Customer not found.');

    $o = $pdo->prepare(
        "SELECT o.order_id,
                CASE WHEN MAX(o.status) IN ('Ready', 'Ready for Install') THEN 'Completed'
                     ELSE MAX(o.status) END AS status,
                SUM(o.total_amount) AS amount,
                MIN(o.order_date) AS order_date, MAX(o.quotation_id) AS quotation_id,
                GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') AS products
         FROM orders o JOIN product p ON p.product_id = o.product_id
         WHERE o.customer_id = :id
         GROUP BY o.order_id ORDER BY order_date DESC"
    );
    $o->execute([':id' => $id]);
    $orders = array_map(function ($r) {
        $r['quotation_ref'] = $r['quotation_id'] ? sprintf('QT-%05d', (int)$r['quotation_id']) : null;
        unset($r['quotation_id']);
        return $r;
    }, $o->fetchAll());

    $q = $pdo->prepare(
        "SELECT i.enquiry_id, p.name AS product, i.message, i.status, i.created_at
         FROM inquiry i JOIN product p ON p.product_id = i.product_id
         WHERE i.customer_id = :id ORDER BY i.created_at DESC"
    );
    $q->execute([':id' => $id]);

    echo json_encode(['success' => true, 'customer' => $customer,
                      'orders' => $orders, 'inquiries' => $q->fetchAll(),
                      'quotations' => customerQuotations($pdo, $id)]);
    exit;
}

$where = []; $params = [];
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
    $where[] = 'c.created_at >= :from_date';
    $params[':from_date'] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'c.created_at < DATE_ADD(:to_date, INTERVAL 1 DAY)';
    $params[':to_date'] = $to;
}
$ordersFilter = (string)($_GET['orders'] ?? '');
if ($ordersFilter === 'yes') {
    $where[] = 'EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = c.customer_id)';
} elseif ($ordersFilter === 'no') {
    $where[] = 'NOT EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = c.customer_id)';
} elseif ($ordersFilter !== '') {
    fail(422, 'Invalid customer order filter.');
}
if (!empty($_GET['search'])) {
    $where[] = "(c.full_name LIKE :s1 OR c.phone LIKE :s2 OR c.email LIKE :s3 OR c.address LIKE :s4)";
    $like = '%' . $_GET['search'] . '%';
    $params[':s1'] = $like; $params[':s2'] = $like; $params[':s3'] = $like; $params[':s4'] = $like;
}
$sql = "SELECT c.customer_id, c.full_name, c.email, c.phone, c.address, c.created_at,
               (SELECT COUNT(DISTINCT o.order_id) FROM orders o WHERE o.customer_id = c.customer_id) AS order_count
        FROM customer c";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$countSql = 'SELECT COUNT(*) FROM customer c';
if ($where) $countSql .= ' WHERE ' . implode(' AND ', $where);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$perPage = filter_var($_GET['per_page'] ?? 25, FILTER_VALIDATE_INT);
if ($page === false || $page < 1 || $perPage === false || !in_array($perPage, [25, 50, 100, 200], true)) {
    fail(422, 'Invalid pagination values.');
}
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$sql .= " ORDER BY c.created_at DESC, c.customer_id DESC LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode(['success' => true, 'customers' => $stmt->fetchAll(), 'pagination' => [
    'page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => $pages,
]]);
