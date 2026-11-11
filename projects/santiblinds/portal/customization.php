<?php
require_once __DIR__ . '/config.php';
$auth = requireCustomer();
$pdo = getDB();

$cols = "id, blind_type, room, width_cm, height_cm, quantity, material, color, mount, control, notes,
         unit_price, total_price, status, created_at";

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT $cols FROM customizations WHERE account_id = :a ORDER BY id DESC LIMIT 10");
    $stmt->execute([':a' => $auth['account_id']]);
    respond(['success' => true, 'customizations' => decRows($stmt->fetchAll(), ENC_MAP_CUSTOMIZATION)]);
}

requireMethod('POST');
$b = requestBody();
$v = validateCustomization($b);
if (!$v['ok']) respond(['success' => false, 'errors' => $v['errors']], 422);
$s = $v['spec'];
$sEnc = $s;                                                   // what gets stored: free-text notes encrypted
$sEnc['notes'] = encField($s['notes'], 'customizations.notes');

$id = (int)($b['id'] ?? 0);

if ($id) { // edit an existing design (only until it has been ordered)
    $own = $pdo->prepare("SELECT status FROM customizations WHERE id = :id AND account_id = :a");
    $own->execute([':id' => $id, ':a' => $auth['account_id']]);
    $row = $own->fetch();
    if (!$row) respond(['success' => false, 'message' => 'Design not found.'], 404);
    if ($row['status'] === 'Ordered') respond(['success' => false, 'message' => 'This design has already been ordered.'], 409);

    $pdo->prepare(
        "UPDATE customizations SET blind_type=:blind_type, room=:room, width_cm=:width_cm, height_cm=:height_cm,
                quantity=:quantity, material=:material, color=:color, mount=:mount, control=:control,
                notes=:notes, unit_price=:unit_price, total_price=:total_price, status='Saved'
         WHERE id = :id"
    )->execute($sEnc + ['id' => $id]);
    // Old quotes no longer match the new specification.
    $pdo->prepare("UPDATE quotations SET status='Expired' WHERE customization_id=:id AND status='Issued'")->execute([':id' => $id]);
    logActivity($pdo, $auth['account_id'], 'Customization updated', "Design #{$id}: " . specText($s) . " Estimate ₱" . number_format($s['total_price'], 2));
} else {
    $pdo->prepare(
        "INSERT INTO customizations (account_id, customer_id, blind_type, room, width_cm, height_cm, quantity,
                material, color, mount, control, notes, unit_price, total_price)
         VALUES (:account_id, :customer_id, :blind_type, :room, :width_cm, :height_cm, :quantity,
                :material, :color, :mount, :control, :notes, :unit_price, :total_price)"
    )->execute($sEnc + ['account_id' => $auth['account_id'], 'customer_id' => $auth['customer_id']]);
    $id = (int)$pdo->lastInsertId();
    logActivity($pdo, $auth['account_id'], 'Customization saved', "Design #{$id}: " . specText($s) . " Estimate ₱" . number_format($s['total_price'], 2));
}

$stmt = $pdo->prepare("SELECT $cols FROM customizations WHERE id = :id");
$stmt->execute([':id' => $id]);
respond(['success' => true, 'customization' => decRow($stmt->fetch(), ENC_MAP_CUSTOMIZATION)]);
