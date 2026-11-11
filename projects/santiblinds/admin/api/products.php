<?php
require_once __DIR__ . '/config.php';
requireLogin();
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $categories = $pdo->query("SELECT category_id, name FROM category ORDER BY name")->fetchAll();
    $products = $pdo->query(
        "SELECT p.product_id, p.name, p.blind_type, p.base_price, p.stock_qty,
                c.name AS category
         FROM product p JOIN category c ON c.category_id = p.category_id
         ORDER BY p.product_id DESC"
    )->fetchAll();
    echo json_encode(['success' => true, 'categories' => $categories, 'products' => $products]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = requestBody();
    $categoryId = filter_var($body['category_id'] ?? null, FILTER_VALIDATE_INT);
    $name = trim((string)($body['name'] ?? ''));
    $blindType = trim((string)($body['blind_type'] ?? ''));
    $description = trim((string)($body['description'] ?? ''));
    $basePrice = $body['base_price'] ?? null;
    $stockQty = $body['stock_qty'] ?? null;
    $imageUrl = trim((string)($body['image_url'] ?? ''));

    if (!$categoryId || $name === '' || strlen($name) > 120 || $blindType === '' || strlen($blindType) > 60) {
        fail(422, 'Enter a product name, blind type, and valid category.');
    }
    if (strlen($description) > 255 || strlen($imageUrl) > 255) {
        fail(422, 'Description and image path must be 255 characters or fewer.');
    }
    if (!is_numeric($basePrice) || (float)$basePrice <= 0 || (float)$basePrice > 99999999.99) {
        fail(422, 'Base price must be a positive amount within the supported range.');
    }
    if (filter_var($stockQty, FILTER_VALIDATE_INT) === false || (int)$stockQty < 0) {
        fail(422, 'Stock quantity must be a whole number of zero or more.');
    }

    $category = $pdo->prepare("SELECT category_id FROM category WHERE category_id = :id");
    $category->execute([':id' => $categoryId]);
    if (!$category->fetch()) fail(422, 'Choose a valid product category.');

    $duplicate = $pdo->prepare("SELECT product_id FROM product WHERE name = :name LIMIT 1");
    $duplicate->execute([':name' => $name]);
    if ($duplicate->fetch()) fail(409, 'A product with that name already exists.');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO product (category_id, name, blind_type, description, base_price, image_url, stock_qty)
             VALUES (:category_id, :name, :blind_type, :description, :base_price, :image_url, :stock_qty)"
        );
        $stmt->execute([
            ':category_id' => $categoryId,
            ':name' => $name,
            ':blind_type' => $blindType,
            ':description' => $description !== '' ? $description : null,
            ':base_price' => round((float)$basePrice, 2),
            ':image_url' => $imageUrl !== '' ? $imageUrl : null,
            ':stock_qty' => (int)$stockQty,
        ]);
        $id = (int)$pdo->lastInsertId();
        recordAudit('Product added', sprintf('Product #%d "%s" was added.', $id, $name));
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    echo json_encode(['success' => true, 'product_id' => $id]);
    exit;
}

fail(405, 'Method not allowed.');
