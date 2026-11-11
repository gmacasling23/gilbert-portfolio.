<?php
require_once __DIR__ . '/config.php';
requireMethod('POST');

$b = requestBody();
$email = strtolower(str($b, 'email', 160));
$password = (string)($b['password'] ?? '');
if ($email === '' || $password === '') {
    respond(['success' => false, 'message' => 'Email and password are required.'], 422);
}

$pdo = getDB();
$bidx = blindIndex($email);   // e-mails are stored encrypted; look them up by keyed hash

// Throttle: 5 failed attempts per email in 15 minutes.
$t = $pdo->prepare(
    "SELECT COUNT(*) FROM customer_activity
     WHERE email_bidx = :e AND action = 'Login failed' AND created_at > (NOW() - INTERVAL 15 MINUTE)"
);
$t->execute([':e' => $bidx]);
if ((int)$t->fetchColumn() >= 5) {
    respond(['success' => false, 'message' => 'Too many failed attempts. Please wait 15 minutes and try again.'], 429);
}

$stmt = $pdo->prepare(
    "SELECT a.id, a.customer_id, a.password_hash, c.fname, c.lname
     FROM customer_accounts a JOIN customers c ON c.id = a.customer_id
     WHERE a.email_bidx = :e LIMIT 1"
);
$stmt->execute([':e' => $bidx]);
$u = $stmt->fetch();
if ($u) $u = decRow($u, ENC_MAP_CUSTOMER);   // decrypts fname / lname

$valid = $u && password_verify($password, $u['password_hash']);

if (!$valid) {
    logActivity($pdo, $u ? (int)$u['id'] : null, 'Login failed', 'Incorrect email or password.', $email);
    respond(['success' => false, 'message' => 'Invalid email or password.'], 401);
}

session_regenerate_id(true);
$_SESSION['account_id']    = (int)$u['id'];
$_SESSION['customer_id']   = (int)$u['customer_id'];
$_SESSION['customer_name'] = $u['fname'];

$pdo->prepare("UPDATE customer_accounts SET last_login_at = NOW() WHERE id = :id")->execute([':id' => $u['id']]);
logActivity($pdo, (int)$u['id'], 'Login', "{$u['fname']} {$u['lname']} logged in.", $email);

respond(['success' => true, 'name' => $u['fname'] . ' ' . $u['lname']]);
