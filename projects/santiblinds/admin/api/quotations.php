<?php
require_once __DIR__ . '/config.php';
requireLogin();
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = requestBody();
    $quotationId = (int)($body['quotation_id'] ?? 0);
    $action = (string)($body['action'] ?? '');
    if ($quotationId < 1) fail(422, 'A valid quotation id is required.');

    if ($action === 'return_quote') {
        $pdo->beginTransaction();
        try {
            $q = $pdo->prepare("SELECT status, valid_until FROM quotation WHERE quotation_id = :id FOR UPDATE");
            $q->execute([':id' => $quotationId]);
            $quote = $q->fetch();
            if (!$quote) { $pdo->rollBack(); fail(404, 'Quotation not found.'); }
            if ($quote['status'] === 'Ordered') { $pdo->rollBack(); fail(409, 'This quotation has already been ordered.'); }
            $items = $body['items'] ?? [];
            if (!is_array($items) || !$items) { $pdo->rollBack(); fail(422, 'Quotation items are required.'); }
            $update = $pdo->prepare("UPDATE quotation_item SET unit_price = :u, total_amount = :t WHERE quotation_item_id = :i AND quotation_id = :q");
            $sum = 0;
            foreach ($items as $item) {
                $itemId = (int)($item['quotation_item_id'] ?? 0);
                $unit = filter_var($item['unit_price'] ?? null, FILTER_VALIDATE_FLOAT);
                if ($itemId < 1 || $unit === false || $unit < 0 || $unit > 10000000) {
                    $pdo->rollBack(); fail(422, 'Each item must have a valid non-negative unit price.');
                }
                $find = $pdo->prepare("SELECT quantity FROM quotation_item WHERE quotation_item_id = :i AND quotation_id = :q");
                $find->execute([':i' => $itemId, ':q' => $quotationId]);
                $qty = $find->fetchColumn();
                if (!$qty) { $pdo->rollBack(); fail(422, 'An item does not belong to this quotation.'); }
                $lineTotal = round((float)$unit * (int)$qty, 2);
                $update->execute([':u' => round((float)$unit, 2), ':t' => $lineTotal, ':i' => $itemId, ':q' => $quotationId]);
                $sum += $lineTotal;
            }
            $validDays = max(1, min(90, (int)($body['valid_days'] ?? 14)));
            $pdo->prepare("UPDATE quotation SET total_amount = :total, status = 'Returned', valid_until = DATE_ADD(NOW(), INTERVAL $validDays DAY) WHERE quotation_id = :id")
                ->execute([':total' => round($sum, 2), ':id' => $quotationId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Quotation returned to customer.']);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    $status = (string)($body['status'] ?? '');
    if (!in_array($status, ['Active', 'Pending', 'Returned', 'Ordered', 'Expired'], true)) fail(422, 'Invalid quotation status.');
    $error = updateQuotationStatus($pdo, $quotationId, $status);
    if ($error !== null) fail($error === 'Quotation not found.' ? 404 : 409, $error);
    echo json_encode(['success' => true]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail(405, 'Method not allowed.');

$where = [];
$params = [];
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
    $where[] = 'q.created_at >= :from_date';
    $params[':from_date'] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'q.created_at < DATE_ADD(:to_date, INTERVAL 1 DAY)';
    $params[':to_date'] = $to;
}
if (!empty($_GET['search'])) {
    $search = trim((string)$_GET['search']);
    $where[] = '(c.full_name LIKE :customer OR c.email LIKE :email OR CAST(q.quotation_id AS CHAR) LIKE :reference';
    $digits = preg_replace('/\D+/', '', $search);
    if ($digits !== '') {
        $where[count($where) - 1] .= ' OR q.quotation_id = :reference_id';
        $params[':reference_id'] = (int)$digits;
    }
    $where[count($where) - 1] .= ')';
    $like = '%' . $search . '%';
    $params[':customer'] = $like;
    $params[':email'] = $like;
    $params[':reference'] = $like;
}
$statusFilter = (string)($_GET['status'] ?? '');
if (in_array($statusFilter, ['Active', 'Ordered', 'Expired'], true)) {
    $where[] = "CASE
        WHEN q.status = 'Ordered' THEN 'Ordered'
        WHEN q.valid_until < NOW() THEN 'Expired'
        WHEN q.status IN ('Pending', 'Returned') THEN q.status
        ELSE 'Active'
    END = :status";
    $params[':status'] = $statusFilter;
}

$sql = "SELECT q.quotation_id, q.total_amount, q.status AS stored_status, q.valid_until, q.created_at,
               c.full_name, c.email,
               (SELECT COUNT(*) FROM quotation_item qi WHERE qi.quotation_id = q.quotation_id) AS item_count,
               (SELECT MIN(o.order_id) FROM orders o WHERE o.quotation_id = q.quotation_id) AS order_id,
               (SELECT MAX(o.status) FROM orders o WHERE o.quotation_id = q.quotation_id) AS order_status,
               CASE
                   WHEN q.status = 'Ordered' THEN 'Ordered'
                   WHEN q.valid_until < NOW() THEN 'Expired'
                   WHEN q.status IN ('Pending', 'Returned') THEN q.status
                   ELSE 'Active'
               END AS display_status
        FROM quotation q JOIN customer c ON c.customer_id = q.customer_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$countSql = "SELECT COUNT(*) FROM quotation q JOIN customer c ON c.customer_id = q.customer_id";
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
$sql .= " ORDER BY q.quotation_id DESC LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
if (!$rows) {
    echo json_encode(['success' => true, 'quotations' => [], 'pagination' => [
        'page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => $pages,
    ]]);
    exit;
}

$ids = array_map('intval', array_column($rows, 'quotation_id'));
$in = implode(',', array_fill(0, count($ids), '?'));
$itemStmt = $pdo->prepare(
    "SELECT qi.quotation_item_id, qi.quotation_id, p.name AS product, p.blind_type, m.name AS material, col.name AS color,
            qi.width_cm, qi.height_cm, qi.quantity, qi.unit_price, qi.total_amount
     FROM quotation_item qi
     JOIN product p ON p.product_id = qi.product_id
     JOIN material m ON m.material_id = qi.material_id
     JOIN color col ON col.color_id = qi.color_id
     WHERE qi.quotation_id IN ($in)
     ORDER BY qi.quotation_item_id"
);
$itemStmt->execute($ids);
$itemsByQuote = [];
foreach ($itemStmt->fetchAll() as $item) {
    $itemsByQuote[(int)$item['quotation_id']][] = $item;
}

$quotations = array_map(static function ($row) use ($itemsByQuote) {
    $id = (int)$row['quotation_id'];
    return [
        'quotation_id' => $id,
        'reference' => sprintf('QT-%05d', $id),
        'customer' => $row['full_name'],
        'email' => $row['email'],
        'status' => $row['display_status'],
        'total_amount' => (float)$row['total_amount'],
        'item_count' => (int)$row['item_count'],
        'valid_until' => $row['valid_until'],
        'created_at' => $row['created_at'],
        'order_id' => $row['order_id'] ? (int)$row['order_id'] : null,
        'order_status' => $row['order_status'],
        'items' => $itemsByQuote[$id] ?? [],
    ];
}, $rows);

echo json_encode(['success' => true, 'quotations' => $quotations, 'pagination' => [
    'page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => $pages,
]]);
