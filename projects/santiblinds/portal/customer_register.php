<?php
// POST { full_name, email, phone, address, password, consent }
// Creates a customer account. If this email already exists as a guest (e.g. from an earlier
// inquiry) that row is upgraded to an account so the history stays together.
// The customer is NOT logged in automatically: the flow is Register > Login.
require_once __DIR__ . '/config.php';
requireMethod('POST');
$b   = requestBody();
$pdo = getDB();

[$cust, $errors] = readCustomer($b, true);
$password = (string)($b['password'] ?? '');
if (strlen($password) < 8)        $errors[] = 'Password must be at least 8 characters.';
elseif (strlen($password) > 72)   $errors[] = 'Password must be 72 characters or fewer.';
if (empty($b['consent']))         $errors[] = 'Please agree to the terms to create an account.';
if ($errors) respond(['success' => false, 'errors' => $errors], 422);

if (recentCount('register_times') >= 5) {
    respond(['success' => false, 'message' => 'Too many attempts. Please wait a few minutes and try again.'], 429);
}
recordHit('register_times');

$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo->beginTransaction();
try {
    $f = $pdo->prepare("SELECT customer_id, password_hash FROM customer WHERE email = :e
                        ORDER BY (password_hash IS NOT NULL) DESC, customer_id LIMIT 1 FOR UPDATE");
    $f->execute([':e' => $cust['email']]);
    $row = $f->fetch();

    if ($row && $row['password_hash'] !== null) {
        $pdo->rollBack();
        respond(['success' => false, 'message' => 'An account with this email already exists. Please log in instead.'], 409);
    }

    if ($row) {   // upgrade the guest row
        $pdo->prepare("UPDATE customer SET full_name = :n, phone = :p, address = :a, password_hash = :h WHERE customer_id = :id")
            ->execute([':n' => $cust['full_name'], ':p' => $cust['phone'], ':a' => $cust['address'], ':h' => $hash, ':id' => $row['customer_id']]);
    } else {
        $pdo->prepare("INSERT INTO customer (full_name, email, phone, address, password_hash) VALUES (:n, :e, :p, :a, :h)")
            ->execute([':n' => $cust['full_name'], ':e' => $cust['email'], ':p' => $cust['phone'], ':a' => $cust['address'], ':h' => $hash]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

respond(['success' => true, 'message' => 'Account created. Please log in to continue.']);
