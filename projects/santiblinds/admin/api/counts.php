<?php
require_once __DIR__ . '/config.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail(405, 'Method not allowed.');

$counts = getDB()->query(
    "SELECT
        (SELECT COUNT(DISTINCT order_id) FROM orders WHERE is_archived = 0) AS orders,
        (SELECT COUNT(*) FROM quotation) AS quotations,
        (SELECT COUNT(*) FROM quotation_item) AS customizations,
        (SELECT COUNT(*) FROM customer) AS customers,
        (SELECT COUNT(*) FROM admin_audit_log) AS audit"
)->fetch();

echo json_encode(['success' => true, 'counts' => array_map('intval', $counts)]);
