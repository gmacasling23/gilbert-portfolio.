<?php
require_once __DIR__ . '/config.php';
requireLogin();
$pdo = getDB();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = requestBody();
    $quotationId = (int)($body['quotation_id'] ?? 0);
    $status = (string)($body['status'] ?? '');
    if ($quotationId < 1) fail(422, 'A valid quotation id is required.');
    if (!in_array($status, ['Active', 'Ordered', 'Expired'], true)) fail(422, 'Invalid customization status.');

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

$status = (string)($_GET['status'] ?? '');
if ($status !== '') {
    if (!in_array($status, ['Active', 'Ordered', 'Expired'], true)) fail(422, 'Invalid customization status filter.');
    $where[] = "CASE WHEN q.status = 'Ordered' THEN 'Ordered'
                     WHEN q.valid_until < NOW() THEN 'Expired'
                     ELSE 'Active' END = :status";
    $params[':status'] = $status;
}
$blindType = trim((string)($_GET['blind_type'] ?? ''));
if ($blindType !== '') {
    $where[] = 'p.blind_type = :blind_type';
    $params[':blind_type'] = $blindType;
}
if (!empty($_GET['search'])) {
    $search = trim((string)$_GET['search']);
    $like = '%' . $search . '%';
    $where[] = '(c.full_name LIKE :customer OR c.email LIKE :email OR p.name LIKE :product
                 OR p.blind_type LIKE :blind_type_search OR CAST(q.quotation_id AS CHAR) LIKE :reference)';
    $params[':customer'] = $like;
    $params[':email'] = $like;
    $params[':product'] = $like;
    $params[':blind_type_search'] = $like;
    $params[':reference'] = $like;
}
$itemId = (int)($_GET['id'] ?? 0);
if ($itemId > 0) {
    $where[] = 'qi.quotation_item_id = :item_id';
    $params[':item_id'] = $itemId;
}

$sql = "SELECT qi.quotation_item_id, qi.quotation_id, c.full_name, c.email,
               p.name AS product, p.blind_type, m.name AS material, col.name AS color,
               qi.width_cm, qi.height_cm, qi.quantity, qi.unit_price, qi.total_amount,
               q.created_at, q.valid_until,
               EXISTS (SELECT 1 FROM orders o WHERE o.quotation_id = q.quotation_id) AS has_order,
               CASE WHEN q.status = 'Ordered' THEN 'Ordered'
                    WHEN q.valid_until < NOW() THEN 'Expired'
                    ELSE 'Active' END AS status
        FROM quotation_item qi
        JOIN quotation q ON q.quotation_id = qi.quotation_id
        JOIN customer c ON c.customer_id = q.customer_id
        JOIN product p ON p.product_id = qi.product_id
        JOIN material m ON m.material_id = qi.material_id
        JOIN color col ON col.color_id = qi.color_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
if ($itemId > 0) {
    $sql .= ' ORDER BY q.quotation_id DESC, qi.quotation_item_id';
} else {
    $countSql = "SELECT COUNT(*)
                 FROM quotation_item qi
                 JOIN quotation q ON q.quotation_id = qi.quotation_id
                 JOIN customer c ON c.customer_id = q.customer_id
                 JOIN product p ON p.product_id = qi.product_id
                 JOIN material m ON m.material_id = qi.material_id
                 JOIN color col ON col.color_id = qi.color_id";
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
    $sql .= " ORDER BY q.quotation_id DESC, qi.quotation_item_id LIMIT $perPage OFFSET $offset";
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if ($itemId > 0) {
    if (!$rows) fail(404, 'Customization not found.');
    echo json_encode(['success' => true, 'customization' => $rows[0]]);
    exit;
}

$typeRows = $pdo->query('SELECT DISTINCT blind_type FROM product ORDER BY blind_type')->fetchAll(PDO::FETCH_COLUMN);
echo json_encode(['success' => true, 'types' => $typeRows, 'customizations' => $rows, 'pagination' => [
    'page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => $pages,
]]);
