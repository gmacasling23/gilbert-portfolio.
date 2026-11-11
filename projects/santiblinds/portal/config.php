<?php
// ============================================================
//  Santi Blinds — public order API bootstrap (ERD-aligned)
//  Included at the top of every portal/*.php endpoint.
//  Location: <site>/portal/  ->  ../db_config.php
//
//  Customers now have accounts (customer.password_hash):
//    Register > Login > Customize > Quotation > Place order.
//  A login is kept in $_SESSION['customer_id'] (separate from the
//  admin's $_SESSION['owner_id']). Contact-form inquiries remain
//  open to guests, identified by email.
// ============================================================
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Manila');

$GLOBALS['IS_LOCAL'] = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

set_exception_handler(function (Throwable $e) {
    error_log('[portal api] ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode([
        'success' => false,
        'message' => !empty($GLOBALS['IS_LOCAL'])
            ? 'Server error: ' . $e->getMessage()
            : 'Server error. Please try again.',
    ]);
    exit;
});

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'https://santiblinds.site'
    || preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Credentials: true');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/catalog_lib.php';

function requestBody(): array {
    $json = json_decode(file_get_contents('php://input'), true);
    return is_array($json) ? $json : $_POST;
}

function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function requireMethod(string $m): void {
    if ($_SERVER['REQUEST_METHOD'] !== $m) respond(['success' => false, 'message' => 'Method not allowed.'], 405);
}

/** The logged-in customer's id, or a 401 response. */
function requireCustomer(): int {
    $id = (int)($_SESSION['customer_id'] ?? 0);
    if ($id <= 0) respond(['success' => false, 'message' => 'Please log in to continue.'], 401);
    return $id;
}

/** Simple per-session throttle: how many hits are recorded under $key in the last $window seconds. */
function recentCount(string $key, int $window = 600): int {
    $now = time();
    $_SESSION[$key] = array_values(array_filter($_SESSION[$key] ?? [], fn($t) => $t > $now - $window));
    return count($_SESSION[$key]);
}
function recordHit(string $key): void {
    $_SESSION[$key][] = time();
}

function str(array $b, string $k, int $max = 255): string {
    return mb_substr(trim((string)($b[$k] ?? '')), 0, $max);
}

/** Validate the customer block (registration and inquiries). Returns [clean, errors]. */
function readCustomer(array $c, bool $needAddress): array {
    $x = [
        'full_name' => str($c, 'full_name', 160),
        'email'     => strtolower(str($c, 'email', 160)),
        'phone'     => str($c, 'phone', 40),
        'address'   => str($c, 'address', 255),
    ];
    $e = [];
    if ($x['full_name'] === '') $e[] = 'Your full name is required.';
    if (!filter_var($x['email'], FILTER_VALIDATE_EMAIL)) $e[] = 'A valid email address is required.';
    if (!preg_match('/^[0-9+()\-\s]{7,20}$/', $x['phone'])) $e[] = 'A valid contact number is required.';
    if ($needAddress && $x['address'] === '') $e[] = 'Address is required.';
    return [$x, $e];
}

/**
 * Re-use the customer with this email (keeps their history together) or create one.
 * Used by guest inquiries. A registered account's details are never overwritten by a
 * guest form — only guest rows (password_hash IS NULL) are refreshed.
 */
function findOrCreateCustomer(PDO $pdo, array $c): int {
    $f = $pdo->prepare("SELECT customer_id FROM customer WHERE email = :e
                        ORDER BY (password_hash IS NOT NULL) DESC, customer_id LIMIT 1");
    $f->execute([':e' => $c['email']]);
    $id = $f->fetchColumn();
    if ($id) {
        $pdo->prepare("UPDATE customer SET full_name = :n, phone = :p, address = COALESCE(NULLIF(:a, ''), address)
                       WHERE customer_id = :id AND password_hash IS NULL")
            ->execute([':n' => $c['full_name'], ':p' => $c['phone'], ':a' => $c['address'], ':id' => $id]);
        return (int)$id;
    }
    $pdo->prepare("INSERT INTO customer (full_name, email, phone, address) VALUES (:n, :e, :p, :a)")
        ->execute([':n' => $c['full_name'], ':e' => $c['email'], ':p' => $c['phone'], ':a' => $c['address'] ?: null]);
    return (int)$pdo->lastInsertId();
}
