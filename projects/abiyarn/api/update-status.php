<?php
/* ============================================
   POST /api/update-status.php
   Updates status of an order or inquiry.
   Called from the admin dashboard.

    Order status changes use a PDO transaction so stock and order status
    remain consistent on MariaDB versions without JSON_TABLE support.
   ============================================ */

require_once __DIR__ . '/../config.php';
session_start();

/* Must be logged in. The dashboard (admin/index.php) sets $_SESSION['admin'];
   the old owner.php login set $_SESSION['owner_id'] — both are accepted. */
if (empty($_SESSION['admin']) && empty($_SESSION['owner_id'])) {
    json(['error' => 'Unauthorised'], 401);
}

cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json(['error' => 'Method not allowed'], 405);
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    json(['error' => 'Invalid JSON payload'], 400);
}

$type   = $data['type']   ?? '';   // 'order' or 'inquiry'
$id     = (int)($data['id'] ?? 0);
$status = trim((string)($data['status'] ?? ''));

if (!$id || !in_array($type, ['order','inquiry'], true)) {
    json(['error' => 'Invalid parameters'], 422);
}

$orderStatuses   = ['pending','processing','completed','cancelled'];
$inquiryStatuses = ['new','replied','closed'];

if ($type === 'order' && !in_array($status, $orderStatuses, true)) {
    json(['error' => 'Invalid order status'], 422);
}
if ($type === 'inquiry' && !in_array($status, $inquiryStatuses, true)) {
    json(['error' => 'Invalid inquiry status'], 422);
}

try {
    $pdo = db();

    if ($type === 'order') {
        json(['success' => true, 'message' => setOrderStatus($pdo, $id, $status)]);
    }

    $pdo->prepare('UPDATE inquiries SET status = :status WHERE id = :id')
        ->execute(['status' => $status, 'id' => $id]);
    json(['success' => true]);

} catch (DomainException $e) {
    json(['error' => $e->getMessage()], $e->getCode() ?: 422);
} catch (Throwable $e) {
    error_log('AbiYarn update-status: ' . $e->getMessage());
    json(['error' => 'Database error'], 500);
}
