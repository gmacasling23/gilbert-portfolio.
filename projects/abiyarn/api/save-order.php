<?php
/* ============================================
   AbiYarn – Save Order Endpoint
   Called by js/checkout.js -> fetch(`${API_BASE}/save-order.php`)
   Place this file at:  <site-root>/api/save-order.php
   (config.php must live one level up, at <site-root>/config.php)

     Validate stock, calculate prices, deduct inventory and insert the order
     in one PDO transaction. This also works with MariaDB versions that do not
     support JSON_TABLE in stored procedures.
   ============================================ */

require_once __DIR__ . '/../config.php';
session_start();
cors();

if (empty($_SESSION['user_id'])) {
    json(['error' => 'Please log in before placing an order.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    json(['error' => 'Invalid JSON payload'], 400);
}

/* ── Required fields (sent by checkout.js's `payload`) ─────────────── */
$required = ['order_num', 'name', 'email', 'phone', 'address', 'city', 'postal', 'country', 'payment', 'total', 'items'];
foreach ($required as $field) {
    if (!isset($input[$field]) || $input[$field] === '' || $input[$field] === []) {
        json(['error' => "Missing field: $field"], 400);
    }
}

/* ── Sanitise (lengths match the `orders` columns) ─────────────────── */
$clip = static fn($v, int $max): string => mb_substr(trim((string)$v), 0, $max);

$orderNum = strtoupper($clip($input['order_num'], 20));
$email    = $clip($input['email'], 200);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json(['error' => 'Please enter a valid email address.'], 422);
}
if (!is_numeric($input['total'])) {
    json(['error' => 'Invalid order total.'], 422);
}

/* ── Items: only product id + quantity are trusted from the browser ── */
if (!is_array($input['items'])) {
    json(['error' => 'Invalid items.'], 422);
}
$cleanItems = [];
foreach ($input['items'] as $item) {
    $pid = is_array($item) && isset($item['id'])  ? (int)$item['id']  : 0;
    $qty = is_array($item) && isset($item['qty']) ? (int)$item['qty'] : 0;
    if ($pid < 1 || $qty < 1 || $qty > 100) {
        json(['error' => 'Your cart is out of date. Please refresh the page and add your items again.'], 422);
    }
    $cleanItems[] = ['id' => $pid, 'qty' => $qty];
}

/* ── Merge duplicate product rows and lock them in a stable order ──── */
$quantities = [];
foreach ($cleanItems as $item) {
    $quantities[$item['id']] = ($quantities[$item['id']] ?? 0) + $item['qty'];
    if ($quantities[$item['id']] > 100) {
        json(['error' => 'Your cart is out of date. Please refresh the page and add your items again.'], 422);
    }
}
ksort($quantities, SORT_NUMERIC);

/* ── Place the order atomically ─────────────────────────────────────── */
$pdo = null;
try {
    $pdo = db();
    $accountStmt = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
    $accountStmt->execute(['id' => (int)$_SESSION['user_id']]);
    $accountEmail = $accountStmt->fetchColumn();
    if (!$accountEmail || strcasecmp($email, (string)$accountEmail) !== 0) {
        json(['error' => 'Use the email address registered to your account for this order.'], 422);
    }

    $pdo->beginTransaction();

    $findProduct = $pdo->prepare(
        'SELECT id, name, price, stock, status FROM products WHERE id = :id FOR UPDATE'
    );
    $updateStock = $pdo->prepare(
        'UPDATE products SET stock = :stock, status = :status
         WHERE id = :id AND stock = :old_stock AND status = :old_status'
    );

    $total = 0.0;
    $orderItems = [];
    foreach ($quantities as $productId => $qty) {
        $findProduct->execute(['id' => $productId]);
        $product = $findProduct->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            throw new DomainException("Product #$productId is no longer available.", 409);
        }

        $stock = (int)$product['stock'];
        if ($product['status'] !== 'available' || $stock < $qty) {
            $message = $stock === 0 || $product['status'] === 'out_of_stock'
                ? 'Sorry, "' . $product['name'] . '" is out of stock.'
                : 'Only ' . $stock . ' left of "' . $product['name'] . '".';
            throw new DomainException($message, 409);
        }

        $price = (float)$product['price'];
        $total += $price * $qty;
        $orderItems[] = [
            'id' => (int)$product['id'],
            'name' => $product['name'],
            'qty' => $qty,
            'price' => $price,
        ];

        $remaining = $stock - $qty;
        $updateStock->execute([
            'stock' => $remaining,
            'status' => $remaining === 0 ? 'out_of_stock' : 'available',
            'id' => $productId,
            'old_stock' => $stock,
            'old_status' => $product['status'],
        ]);
        if ($updateStock->rowCount() !== 1) {
            throw new DomainException('Product stock changed. Please review your cart and try again.', 409);
        }
    }

    $total = round($total, 2);
    if (abs($total - round((float)$input['total'], 2)) > 0.01) {
        throw new DomainException('Prices changed since you added these items. Please refresh and review your cart.', 409);
    }

    $estimatedDelivery = (new DateTimeImmutable('today'))->modify('+7 days')->format('Y-m-d');
    $insert = $pdo->prepare(
        'INSERT INTO orders
         (order_num, name, email, phone, address, city, postal, country, payment, gcash_ref, total, status, estimated_delivery, items, notes)
         VALUES
         (:order_num, :name, :email, :phone, :address, :city, :postal, :country, :payment, :gcash_ref, :total, :status, :estimated_delivery, :items, :notes)'
    );
    $insert->execute([
        'order_num' => $orderNum,
        'name' => $clip($input['name'], 120),
        'email' => $email,
        'phone' => $clip($input['phone'], 40),
        'address' => $clip($input['address'], 300),
        'city' => $clip($input['city'], 100),
        'postal' => $clip($input['postal'], 20),
        'country' => $clip($input['country'], 80),
        'payment' => $clip($input['payment'], 40),
        'gcash_ref' => isset($input['gcash_ref']) ? $clip($input['gcash_ref'], 80) : null,
        'total' => $total,
        'status' => 'pending',
        'estimated_delivery' => $estimatedDelivery,
        'items' => json_encode($orderItems, JSON_THROW_ON_ERROR),
        'notes' => isset($input['notes']) ? $clip($input['notes'], 1000) : null,
    ]);

    // Normalized order lines for reporting; kept in the same checkout transaction.
    $orderId = (int)$pdo->lastInsertId();
    $insertLine = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, qty, unit_price)
         VALUES (:order_id, :product_id, :qty, :unit_price)'
    );
    foreach ($orderItems as $line) {
        $insertLine->execute([
            'order_id' => $orderId,
            'product_id' => $line['id'],
            'qty' => $line['qty'],
            'unit_price' => $line['price'],
        ]);
    }

    $pdo->commit();
    json(['success' => true, 'order_num' => $orderNum, 'total' => $total]);

} catch (DomainException $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    json(['error' => $e->getMessage()], $e->getCode() ?: 422);

} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    $mysqlCode = (int)($e->errorInfo[1] ?? 0);
    if ($mysqlCode === 1062) {
        json(['error' => 'This order number was already recorded. Please try again.'], 409);
    }
    error_log('AbiYarn save-order: ' . $e->getMessage());
    json(['error' => 'We could not place your order. Please try again.'], 500);

} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    error_log('AbiYarn save-order: ' . $e->getMessage());
    json(['error' => 'We could not place your order. Please try again.'], 500);
}
