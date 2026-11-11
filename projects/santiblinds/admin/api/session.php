<?php
require_once __DIR__ . '/config.php';
if (empty($_SESSION['owner_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}
echo json_encode(['success' => true, 'name' => $_SESSION['owner_name'] ?? 'Admin']);
