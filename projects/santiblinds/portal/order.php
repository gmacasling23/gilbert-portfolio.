<?php
// POST { quotation_id, address }   -> order from one of the customer's own Active quotations
// POST { items: [...], address }   -> order placed directly from the design (no quotation)
// Either way the prices are checked/computed on the server and stock is reserved in one transaction.
require_once __DIR__ . '/config.php';
requireMethod('POST');
$customerId = requireCustomer();
$b   = requestBody();
$pdo = getDB();

$qid      = (int)($b['quotation_id'] ?? 0);
$useQuote = $qid > 0;
$address  = str($b, 'address', 255);
$errors   = [];
if ($address === '') $errors[] = 'Installation / delivery address is required.';
if ($errors) respond(['success' => false, 'errors' => $errors], 422);

// Basic spam throttle: 5 orders per 10 minutes per browser session.
if (recentCount('order_times') >= 5) {
    respond(['success' => false, 'message' => 'Too many orders in a short time. Please wait a few minutes.'], 429);
}

// Direct order: price the items now, exactly as a quotation would.
$direct = null;
if (!$useQuote) {
    $direct = validateItems($pdo, $b['items'] ?? []);
    if ($direct['errors']) respond(['success' => false, 'errors' => $direct['errors']], 422);
}

$pdo->beginTransaction();
try {
    if ($useQuote) {
        // 0) Lock the quotation so it can only ever be turned into one order.
        $q = loadQuotation($pdo, $qid, $customerId, true);
        if (!$q) { $pdo->rollBack(); respond(['success' => false, 'message' => 'Quotation not found.'], 404); }
        $existingOrder = $pdo->prepare('SELECT 1 FROM orders WHERE quotation_id = :id LIMIT 1');
        $existingOrder->execute([':id' => $qid]);
        if ($existingOrder->fetchColumn()) { $pdo->rollBack(); respond(['success' => false, 'message' => 'An order has already been placed from this quotation.'], 409); }
        if ($q['status'] === 'Ordered') { $pdo->rollBack(); respond(['success' => false, 'message' => 'An order has already been placed from this quotation.'], 409); }
        if ($q['status'] === 'Expired') { $pdo->rollBack(); respond(['success' => false, 'message' => 'This quotation has expired. Please design your blinds again to get a new one.'], 409); }

        $lines       = $q['items'];
        $total       = $q['total_amount'];
        $quotationId = $qid;
        $quoteRef    = $q['reference'];
    } else {
        $lines       = $direct['rows'];
        $total       = $direct['total'];
        $quotationId = null;
        $quoteRef    = null;
    }

    // Keep product-lock acquisition ordered to reduce deadlocks across concurrent orders.
    usort($lines, function ($a, $b) {
        return (int)$a['product_id'] <=> (int)$b['product_id'];
    });

    $stockErrors = lockAvailableInventory($pdo, $lines);
    if ($stockErrors) {
        $pdo->rollBack();
        respond(['success' => false, 'errors' => $stockErrors], 409);
    }

    // 1) Keep the delivery address on the customer record.
    $pdo->prepare("UPDATE customer SET address = :a WHERE customer_id = :c")
        ->execute([':a' => $address, ':c' => $customerId]);

    // 2) Save the order. The database trigger reserves stock for each line.
    $orderId = (int)$pdo->query("SELECT COALESCE(MAX(order_id), 0) + 1 FROM orders FOR UPDATE")->fetchColumn();

    $ins = $pdo->prepare(
        "INSERT INTO orders (order_id, customer_id, quotation_id, product_id, material_id, color_id,
                             width_cm, height_cm, quantity, unit_price, total_amount, status)
         VALUES (:o, :c, :qt, :p, :m, :cl, :w, :h, :q, :u, :t, 'Pending')"
    );
    foreach ($lines as $r) {
        $ins->execute([':o' => $orderId, ':c' => $customerId, ':qt' => $quotationId, ':p' => $r['product_id'],
                       ':m' => $r['material_id'], ':cl' => $r['color_id'],
                       ':w' => $r['width_cm'], ':h' => $r['height_cm'], ':q' => $r['quantity'],
                       ':u' => $r['unit_price'], ':t' => $r['total_amount']]);
    }

    // 3) If it came from a quotation, that quotation is now used up.
    if ($useQuote) {
        $pdo->prepare("UPDATE quotation SET status = 'Ordered' WHERE quotation_id = :id")->execute([':id' => $qid]);
    }

    $receiptStmt = $pdo->prepare(
        "SELECT MIN(o.order_date) AS order_date
         FROM orders o
         WHERE o.order_id = :id AND o.customer_id = :customer_id"
    );
    $receiptStmt->execute([':id' => $orderId, ':customer_id' => $customerId]);
    $orderDate = $receiptStmt->fetchColumn();
    $itemsStmt = $pdo->prepare(
        "SELECT p.name AS product, p.blind_type, m.name AS material, col.name AS color,
                o.width_cm, o.height_cm, o.quantity, o.unit_price, o.total_amount
         FROM orders o
         JOIN product p ON p.product_id = o.product_id
         JOIN material m ON m.material_id = o.material_id
         JOIN color col ON col.color_id = o.color_id
         WHERE o.order_id = :id AND o.customer_id = :customer_id
         ORDER BY o.line_id"
    );
    $itemsStmt->execute([':id' => $orderId, ':customer_id' => $customerId]);
    $receiptItems = $itemsStmt->fetchAll();

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (($e->errorInfo[0] ?? $e->getCode()) === '45000') {
        respond(['success' => false, 'message' => 'Inventory changed while placing your order. Please review stock and try again.'], 409);
    }
    throw $e;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

recordHit('order_times');
respond([
    'success' => true,
    'order'   => ['id' => $orderId, 'reference' => sprintf('SB-%05d', $orderId), 'amount' => $total,
                  'items' => count($lines), 'quotation' => $quoteRef],
    'receipt' => ['order_date' => $orderDate, 'items' => $receiptItems],
    'message' => 'Thank you! Your order has been received.',
]);
