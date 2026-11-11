<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['account_id'])) {
    respond(['success' => true, 'authenticated' => false]);
}
$stmt = getDB()->prepare(
    "SELECT c.fname, c.lname, c.email, c.phone, c.location
     FROM customer_accounts a JOIN customers c ON c.id = a.customer_id WHERE a.id = :id"
);
$stmt->execute([':id' => (int)$_SESSION['account_id']]);
$me = $stmt->fetch();
if (!$me) { // account was deleted by an admin
    unset($_SESSION['account_id'], $_SESSION['customer_id'], $_SESSION['customer_name']);
    respond(['success' => true, 'authenticated' => false]);
}
respond(['success' => true, 'authenticated' => true, 'user' => decRow($me, ENC_MAP_CUSTOMER)]);
