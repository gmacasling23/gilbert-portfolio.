<?php
require_once __DIR__ . '/config.php';
requireMethod('POST');

$b = requestBody();
$fname = str($b, 'fname', 80);
$lname = str($b, 'lname', 80);
$email = strtolower(str($b, 'email', 160));
$phone = str($b, 'phone', 40);
$location = str($b, 'location', 160);
$password = (string)($b['password'] ?? '');
$consent  = !empty($b['consent']);

$errors = [];
if ($fname === '') $errors[] = 'First name is required.';
if ($lname === '') $errors[] = 'Last name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if (!preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) $errors[] = 'A valid contact number is required.';
if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
if (strlen($password) > 72) $errors[] = 'Password must be 72 characters or fewer.';
if (!$consent) $errors[] = 'Please agree to the terms to create an account.';
if ($errors) respond(['success' => false, 'errors' => $errors], 422);

$pdo = getDB();
$bidx = blindIndex($email);   // keyed hash used to find / de-duplicate encrypted e-mails

$exists = $pdo->prepare("SELECT id FROM customer_accounts WHERE email_bidx = :b LIMIT 1");
$exists->execute([':b' => $bidx]);
if ($exists->fetch()) {
    respond(['success' => false, 'errors' => ['An account with this email already exists. Please log in instead.'], 'code' => 'exists'], 409);
}

$pdo->beginTransaction();
try {
    // Re-use an existing customer record with the same email (e.g. added by the admin)
    // so their history stays together; otherwise create one. The customer list in the
    // admin panel therefore shows every registered user.
    $find = $pdo->prepare(
        "SELECT c.id FROM customers c
         LEFT JOIN customer_accounts a ON a.customer_id = c.id
         WHERE c.email_bidx = :b AND a.id IS NULL LIMIT 1"
    );
    $find->execute([':b' => $bidx]);
    $customerId = $find->fetchColumn();

    if ($customerId) {
        $pdo->prepare("UPDATE customers SET fname=:f, lname=:l, email=:e, email_bidx=:b, phone=:p, location=:loc WHERE id=:id")
            ->execute([
                ':f' => encField($fname, 'customers.fname'), ':l' => encField($lname, 'customers.lname'),
                ':e' => encField($email, 'customers.email'), ':b' => $bidx,
                ':p' => encField($phone, 'customers.phone'), ':loc' => encField($location ?: null, 'customers.location'),
                ':id' => $customerId,
            ]);
    } else {
        $pdo->prepare("INSERT INTO customers (fname, lname, email, email_bidx, phone, location) VALUES (:f,:l,:e,:b,:p,:loc)")
            ->execute([
                ':f' => encField($fname, 'customers.fname'), ':l' => encField($lname, 'customers.lname'),
                ':e' => encField($email, 'customers.email'), ':b' => $bidx,
                ':p' => encField($phone, 'customers.phone'), ':loc' => encField($location ?: null, 'customers.location'),
            ]);
        $customerId = (int)$pdo->lastInsertId();
    }

    $pdo->prepare("INSERT INTO customer_accounts (customer_id, email, email_bidx, password_hash) VALUES (:c,:e,:b,:h)")
        ->execute([
            ':c' => $customerId, ':e' => encField($email, 'customer_accounts.email'), ':b' => $bidx,
            ':h' => password_hash($password, PASSWORD_DEFAULT),   // passwords: one-way bcrypt hash, never encrypted
        ]);
    $accountId = (int)$pdo->lastInsertId();

    logActivity($pdo, $accountId, 'Register', "New account for {$fname} {$lname} ({$email}).", $email);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

// Deliberately NOT logged in: the customer must now log in.
respond(['success' => true, 'email' => $email, 'message' => 'Account created. Please log in to continue.']);
