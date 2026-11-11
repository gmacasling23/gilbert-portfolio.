<?php
// Requires a customer login.
//   GET                 -> this customer's 10 most recent quotations
//   GET  ?id=12         -> one quotation with its items
//   POST { items: [{product_id,material_id,color_id,width_cm,height_cm,quantity}] }
//                       -> prices the items on the server, saves a quotation, returns it
require_once __DIR__ . '/config.php';
$customerId = requireCustomer();
$pdo        = getDB();
$method     = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        $q = loadQuotation($pdo, (int)$_GET['id'], $customerId);
        if (!$q) respond(['success' => false, 'message' => 'Quotation not found.'], 404);
        respond(['success' => true, 'quotation' => $q]);
    }

    $stmt = $pdo->prepare(
        "SELECT q.quotation_id, q.total_amount, q.status, q.valid_until, q.created_at,
                (SELECT COUNT(*) FROM quotation_item qi WHERE qi.quotation_id = q.quotation_id) AS item_count
         FROM quotation q WHERE q.customer_id = :c ORDER BY q.quotation_id DESC LIMIT 10"
    );
    $stmt->execute([':c' => $customerId]);
    respond(['success' => true, 'quotations' => array_map(fn($r) => [
        'quotation_id' => (int)$r['quotation_id'],
        'reference'    => quotationRef((int)$r['quotation_id']),
        'status'       => quotationStatus($r),
        'total_amount' => (float)$r['total_amount'],
        'item_count'   => (int)$r['item_count'],
        'valid_until'  => $r['valid_until'],
        'created_at'   => $r['created_at'],
    ], $stmt->fetchAll())]);
}

if ($method === 'POST') {
    $b = requestBody();
    $v = validateItems($pdo, $b['items'] ?? []);
    if ($v['errors']) respond(['success' => false, 'errors' => $v['errors']], 422);

    if (recentCount('quote_times') >= 10) {
        respond(['success' => false, 'message' => 'Too many quotations in a short time. Please wait a few minutes.'], 429);
    }

    $now        = time();
    $validUntil = date('Y-m-d H:i:s', strtotime('+' . QUOTE_VALID_DAYS . ' days', $now));

    $pdo->beginTransaction();
    try {
        $stockErrors = lockAvailableInventory($pdo, $v['rows']);
        if ($stockErrors) {
            $pdo->rollBack();
            respond(['success' => false, 'errors' => $stockErrors], 409);
        }

        $pdo->prepare("INSERT INTO quotation (customer_id, total_amount, status, valid_until, created_at)
                       VALUES (:c, :t, 'Active', :v, :n)")
            ->execute([':c' => $customerId, ':t' => $v['total'], ':v' => $validUntil, ':n' => date('Y-m-d H:i:s', $now)]);
        $quotationId = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare(
            "INSERT INTO quotation_item (quotation_id, product_id, material_id, color_id,
                                         width_cm, height_cm, quantity, unit_price, total_amount)
             VALUES (:q, :p, :m, :cl, :w, :h, :qty, :u, :t)"
        );
        foreach ($v['rows'] as $r) {
            $ins->execute([':q' => $quotationId, ':p' => $r['product_id'], ':m' => $r['material_id'], ':cl' => $r['color_id'],
                           ':w' => $r['width_cm'], ':h' => $r['height_cm'], ':qty' => $r['quantity'],
                           ':u' => $r['unit_price'], ':t' => $r['total_amount']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    recordHit('quote_times');
    respond(['success' => true, 'quotation' => loadQuotation($pdo, $quotationId, $customerId)]);
}

respond(['success' => false, 'message' => 'Method not allowed.'], 405);
