<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'Method not allowed.');

$body     = requestBody();
$email    = trim($body['email'] ?? '');
$password = (string)($body['password'] ?? '');
if ($email === '' || $password === '') fail(422, 'Email and password are required.');

$stmt = getDB()->prepare("SELECT owner_id, full_name, password_hash FROM owner_manager WHERE email = :e LIMIT 1");
$stmt->execute([':e' => $email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    error_log("Admin login failed for: {$email}");
    fail(401, 'Invalid email or password.');
}

session_regenerate_id(true);
$_SESSION['owner_id']   = (int)$user['owner_id'];
$_SESSION['owner_name'] = $user['full_name'];
recordAudit('Admin login', 'Signed in to the admin panel.');

echo json_encode(['success' => true, 'name' => $user['full_name']]);
