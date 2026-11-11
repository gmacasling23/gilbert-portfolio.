<?php
require_once __DIR__ . '/config.php';
requireLogin();
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail(405, 'Method not allowed.');

$stmt = $pdo->query(
    "SELECT p.name AS product, SUM(o.quantity) AS units,
            COALESCE(SUM(o.total_amount), 0) AS revenue
     FROM orders o
     JOIN product p ON p.product_id = o.product_id
     WHERE o.status IN ('Completed', 'Ready', 'Ready for Install')
     GROUP BY p.product_id, p.name
     ORDER BY units DESC, revenue DESC, p.name ASC"
);

echo json_encode([
    'success' => true,
    'products' => array_map(static function ($row) {
        $row['units'] = (int)$row['units'];
        $row['revenue'] = (float)$row['revenue'];
        return $row;
    }, $stmt->fetchAll()),
]);
