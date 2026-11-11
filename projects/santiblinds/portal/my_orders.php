<?php
// Requires a customer login. Only ever returns the logged-in customer's own orders.
//   GET            -> every order this customer has placed (newest first), with its items
//   GET ?id=12     -> one order with its items and tracking steps
// Orders are stored one row per item; rows sharing an order_id are one order.
require_once __DIR__ . '/config.php';
requireMethod('GET');
$customerId = requireCustomer();
$pdo        = getDB();

const TRACK_STEPS = ['Pending', 'Processing', 'Completed'];

/** Collapse the legacy "Ready" statuses into Completed, same as the admin panel does. */
function orderStatus(string $s): string {
    return in_array($s, ['Ready', 'Ready for Install'], true) ? 'Completed' : $s;
}

/** Tracking steps for the timeline: done / current / upcoming (or a cancelled flag). */
function trackingFor(string $status): array {
    if ($status === 'Cancelled') {
        return ['cancelled' => true, 'steps' => []];
    }
    $at = array_search($status, TRACK_STEPS, true);
    if ($at === false) $at = 0;
    $steps = [];
    foreach (TRACK_STEPS as $i => $label) {
        $steps[] = [
            'label' => $label,
            'state' => $i < $at ? 'done' : ($i === $at ? ($status === 'Completed' ? 'done' : 'current') : 'upcoming'),
        ];
    }
    return ['cancelled' => false, 'steps' => $steps];
}

$where  = 'o.customer_id = :c';
$params = [':c' => $customerId];
$single = !empty($_GET['id']);
if ($single) {
    $where .= ' AND o.order_id = :id';
    $params[':id'] = (int)$_GET['id'];
}

$stmt = $pdo->prepare(
    "SELECT o.order_id, o.line_id, o.quotation_id, o.status, o.order_date,
            o.width_cm, o.height_cm, o.quantity, o.unit_price, o.total_amount,
            p.name AS product, p.blind_type, m.name AS material, col.name AS color
     FROM orders o
     JOIN product  p   ON p.product_id   = o.product_id
     JOIN material m   ON m.material_id  = o.material_id
     JOIN color    col ON col.color_id   = o.color_id
     WHERE $where
     ORDER BY o.order_date DESC, o.order_id DESC, o.line_id"
);
$stmt->execute($params);

$orders = [];
foreach ($stmt->fetchAll() as $r) {
    $id = (int)$r['order_id'];
    if (!isset($orders[$id])) {
        $orders[$id] = [
            'order_id'     => $id,
            'reference'    => sprintf('SB-%05d', $id),
            'status'       => orderStatus((string)$r['status']),
            'order_date'   => $r['order_date'],
            'quotation'    => $r['quotation_id'] ? quotationRef((int)$r['quotation_id']) : null,
            'total_amount' => 0.0,
            'item_count'   => 0,
            'items'        => [],
        ];
    }
    $orders[$id]['total_amount'] += (float)$r['total_amount'];
    $orders[$id]['item_count']   += (int)$r['quantity'];
    $orders[$id]['items'][] = [
        'product'      => $r['product'],
        'blind_type'   => $r['blind_type'],
        'material'     => $r['material'],
        'color'        => $r['color'],
        'width_cm'     => (float)$r['width_cm'],
        'height_cm'    => (float)$r['height_cm'],
        'quantity'     => (int)$r['quantity'],
        'unit_price'   => (float)$r['unit_price'],
        'total_amount' => (float)$r['total_amount'],
    ];
}

$orders = array_values($orders);
foreach ($orders as &$o) {
    $o['tracking'] = trackingFor($o['status']);
}
unset($o);

if ($single) {
    if (!$orders) respond(['success' => false, 'message' => 'Order not found.'], 404);
    respond(['success' => true, 'order' => $orders[0]]);
}
respond(['success' => true, 'orders' => $orders]);
