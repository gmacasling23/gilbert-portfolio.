<?php
require_once __DIR__ . '/config.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail(405, 'Method not allowed.');

$pdo = getDB();
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

$where = [];
$params = [];
if ($from !== '') {
    $where[] = 'created_at >= :from_date';
    $params[':from_date'] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'created_at < DATE_ADD(:to_date, INTERVAL 1 DAY)';
    $params[':to_date'] = $to;
}
$action = trim((string)($_GET['action'] ?? ''));
if ($action !== '') {
    $where[] = 'action = :action';
    $params[':action'] = $action;
}
$search = trim((string)($_GET['search'] ?? ''));
if ($search !== '') {
    $where[] = '(admin_name LIKE :admin OR action LIKE :search_action OR details LIKE :details)';
    $like = '%' . $search . '%';
    $params[':admin'] = $like;
    $params[':search_action'] = $like;
    $params[':details'] = $like;
}

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$perPage = filter_var($_GET['per_page'] ?? 25, FILTER_VALIDATE_INT);
if ($page === false || $page < 1 || $perPage === false || !in_array($perPage, [25, 50, 100, 200], true)) {
    fail(422, 'Invalid pagination values.');
}
$countSql = 'SELECT COUNT(*) FROM admin_audit_log';
if ($where) $countSql .= ' WHERE ' . implode(' AND ', $where);
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$sql = 'SELECT audit_id, admin_name, action, details, created_at FROM admin_audit_log';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= " ORDER BY audit_id DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$actions = $pdo->query('SELECT DISTINCT action FROM admin_audit_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
echo json_encode(['success' => true, 'logs' => $stmt->fetchAll(), 'actions' => $actions, 'pagination' => [
    'page' => $page, 'per_page' => $perPage, 'total' => $total, 'pages' => $pages,
]]);
