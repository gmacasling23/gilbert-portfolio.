<?php
require_once __DIR__ . '/config.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed.');

$body = requestBody();
$id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
$archived = $body['archived'] ?? null;
if ($id === false || $id < 1) fail(422, 'A valid order id is required.');
if (!in_array($archived, [0, 1, false, true], true)) fail(422, 'Archive status must be true or false.');

$archived = (int)$archived;
$pdo = getDB();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT is_archived FROM orders WHERE order_id = :id FOR UPDATE');
    $stmt->execute([':id' => $id]);
    $lines = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$lines) {
        $pdo->rollBack();
        fail(404, 'Order not found.');
    }

    if (array_filter($lines, static fn($value) => (int)$value !== $archived)) {
        $update = $pdo->prepare('UPDATE orders SET is_archived = :archived WHERE order_id = :id');
        $update->execute([':archived' => $archived, ':id' => $id]);
        recordAudit(
            $archived ? 'Order archived' : 'Order unarchived',
            sprintf('Order #%d was %s.', $id, $archived ? 'archived' : 'unarchived')
        );
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

echo json_encode(['success' => true, 'id' => $id, 'is_archived' => $archived]);
