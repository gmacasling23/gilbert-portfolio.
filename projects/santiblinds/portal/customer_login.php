<?php
// POST { email, password } -> starts a customer session.
require_once __DIR__ . '/config.php';
requireMethod('POST');
$b        = requestBody();
$email    = strtolower(str($b, 'email', 160));
$password = (string)($b['password'] ?? '');

if ($email === '' || $password === '') {
    respond(['success' => false, 'message' => 'Email and password are required.'], 422);
}
if (recentCount('login_fails') >= 5) {
    respond(['success' => false, 'message' => 'Too many failed attempts. Please wait a few minutes and try again.'], 429);
}

$stmt = getDB()->prepare(
    "SELECT customer_id, full_name, email, phone, address, password_hash
     FROM customer WHERE email = :e AND password_hash IS NOT NULL LIMIT 1"
);
$stmt->execute([':e' => $email]);
$u = $stmt->fetch();

if (!$u || !password_verify($password, $u['password_hash'])) {
    recordHit('login_fails');
    error_log("Customer login failed for: {$email}");
    respond(['success' => false, 'message' => 'Incorrect email or password.'], 401);
}

session_regenerate_id(true);
unset($_SESSION['login_fails']);
$_SESSION['customer_id'] = (int)$u['customer_id'];

respond(['success' => true, 'customer' => [
    'full_name' => $u['full_name'], 'email' => $u['email'], 'phone' => $u['phone'], 'address' => $u['address'],
]]);
