<?php
// GET -> is a customer logged in? (200 either way, so the page can decide which step to show)
require_once __DIR__ . '/config.php';

$id = (int)($_SESSION['customer_id'] ?? 0);
if ($id > 0) {
    $s = getDB()->prepare("SELECT full_name, email, phone, address FROM customer WHERE customer_id = :id AND password_hash IS NOT NULL");
    $s->execute([':id' => $id]);
    $c = $s->fetch();
    if ($c) respond(['success' => true, 'logged_in' => true, 'customer' => $c]);
    unset($_SESSION['customer_id']);   // account no longer exists
}
respond(['success' => true, 'logged_in' => false]);
