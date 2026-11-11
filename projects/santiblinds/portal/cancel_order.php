<?php
// POST { order_id } -> cancel one of the logged-in customer's active orders.
require_once __DIR__ . '/config.php';
requireMethod('POST');
$customerId = requireCustomer();
$body = requestBody();
$orderId = filter_var($body['order_id'] ?? null, FILTER_VALIDATE_INT);
if ($orderId === false || $orderId === null || $orderId < 1) {
    respond(['success' => false, 'message' => 'A valid order id is required.'], 422);
}

$pdo = getDB();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'SELECT status FROM orders
         WHERE order_id = :order_id AND customer_id = :customer_id
         FOR UPDATE'
    );
    $stmt->execute([':order_id' => $orderId, ':customer_id' => $customerId]);
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$statuses) {
        $pdo->rollBack();
        respond(['success' => false, 'message' => 'Order not found.'], 404);
    }
    if (in_array(false, array_map(static fn($status) => in_array($status, ['Pending', 'Processing'], true), $statuses), true)) {
        $pdo->rollBack();
        respond(['success' => false, 'message' => 'Only pending or processing orders can be cancelled.'], 409);
    }

    $update = $pdo->prepare(
        "UPDATE orders SET status = 'Cancelled'
         WHERE order_id = :order_id AND customer_id = :customer_id"
    );
    $update->execute([':order_id' => $orderId, ':customer_id' => $customerId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

respond(['success' => true]);
