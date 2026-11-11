<?php
require_once __DIR__ . '/config.php';
requireLogin();
$pdo = getDB();

$limit = isset($_GET['limit']) ? min(500, max(1, (int)$_GET['limit'])) : 200;

$where = []; $params = [];
if (!empty($_GET['action'])) { $where[] = 'a.action = :act'; $params[':act'] = $_GET['action']; }

$sql = "SELECT a.id, a.action, a.details, a.email, a.ip_address, a.created_at, c.fname, c.lname
        FROM customer_activity a
        LEFT JOIN customer_accounts ac ON ac.id = a.account_id
        LEFT JOIN customers c ON c.id = ac.customer_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY a.created_at DESC, a.id DESC LIMIT :limit';

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();

$activity = decRows($stmt->fetchAll(), ENC_MAP_ACTIVITY + ['fname' => 'customers.fname', 'lname' => 'customers.lname']);

echo json_encode(['success' => true, 'activity' => $activity]);
