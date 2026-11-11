<?php
require_once __DIR__ . '/config.php';
requireLogin();
$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT p.product_id, p.name, p.blind_type, p.base_price, p.stock_qty,
                c.name AS category, (p.stock_qty <= :lvl) AS low_stock
         FROM product p JOIN category c ON c.category_id = p.category_id
         ORDER BY low_stock DESC, p.name ASC"
    );
    $stmt->execute([':lvl' => LOW_STOCK_LEVEL]);
    echo json_encode(['success' => true, 'low_stock_level' => LOW_STOCK_LEVEL, 'inventory' => $stmt->fetchAll()]);
    exit;
}

if ($method === 'POST') {
    $body = requestBody();
    $id   = (int)($body['id'] ?? 0);
    $quantityToAdd = filter_var($body['quantity_to_add'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $quantityToAdd === false || $quantityToAdd === null || $quantityToAdd < 1) {
        fail(422, 'A valid product id and quantity greater than zero are required.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE product SET stock_qty = stock_qty + :q WHERE product_id = :id");
        $stmt->execute([':q' => $quantityToAdd, ':id' => $id]);
        if (!$stmt->rowCount()) {
            $exists = $pdo->prepare("SELECT 1 FROM product WHERE product_id = :id");
            $exists->execute([':id' => $id]);
            if (!$exists->fetchColumn()) {
                $pdo->rollBack();
                fail(404, 'Product not found.');
            }
        }
        recordAudit('Stock increased', sprintf('Product #%d stock increased by %d.', $id, $quantityToAdd));
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    echo json_encode(['success' => true]);
    exit;
}

fail(405, 'Method not allowed.');
