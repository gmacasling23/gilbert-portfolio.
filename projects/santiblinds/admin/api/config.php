<?php
// ============================================================
//  Santi Blinds — Admin API bootstrap (ERD-aligned)
//  Included at the top of every admin/api/*.php endpoint.
// ============================================================
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Manila');

$GLOBALS['IS_LOCAL'] = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

// Stock at or below this number counts as "low stock" (the ERD has no reorder level).
const LOW_STOCK_LEVEL = 5;
const ORDER_STATUSES  = ['Pending', 'Processing', 'Completed', 'Cancelled'];

set_exception_handler(function (Throwable $e) {
    error_log('[admin api] ' . get_class($e) . ': ' . $e->getMessage()
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

require_once __DIR__ . '/../../db_config.php';

function requireLogin(): void {
    if (empty($_SESSION['owner_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
        exit;
    }
}

function recordAudit(string $action, string $details = ''): void {
    $stmt = getDB()->prepare(
        "INSERT INTO admin_audit_log (owner_id, admin_name, action, details)
         VALUES (:owner_id, :admin_name, :action, :details)"
    );
    $stmt->execute([
        ':owner_id' => $_SESSION['owner_id'] ?? null,
        ':admin_name' => $_SESSION['owner_name'] ?? 'Admin',
        ':action' => $action,
        ':details' => $details,
    ]);
}

function requestBody(): array {
    $json = json_decode(file_get_contents('php://input'), true);
    return is_array($json) ? $json : $_POST;
}

function fail(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function updateQuotationStatus(PDO $pdo, int $quotationId, string $status): ?string {
    $pdo->beginTransaction();
    try {
        $quoteStmt = $pdo->prepare('SELECT status, valid_until FROM quotation WHERE quotation_id = :id FOR UPDATE');
        $quoteStmt->execute([':id' => $quotationId]);
        $quote = $quoteStmt->fetch();
        if (!$quote) {
            $pdo->rollBack();
            return 'Quotation not found.';
        }

        if ($status === 'Active') {
            $pdo->prepare(
                "UPDATE quotation
                 SET status = 'Active',
                     valid_until = CASE WHEN valid_until < NOW()
                                        THEN DATE_ADD(NOW(), INTERVAL 7 DAY)
                                        ELSE valid_until END
                 WHERE quotation_id = :id"
            )->execute([':id' => $quotationId]);
        } elseif ($status === 'Expired') {
            $pdo->prepare(
                "UPDATE quotation SET status = 'Active', valid_until = DATE_SUB(NOW(), INTERVAL 1 SECOND)
                 WHERE quotation_id = :id"
            )->execute([':id' => $quotationId]);
        } else {
            $pdo->prepare("UPDATE quotation SET status = 'Ordered' WHERE quotation_id = :id")
                ->execute([':id' => $quotationId]);
        }

        recordAudit(
            'Quotation updated',
            sprintf('Quotation #%d status set to %s.', $quotationId, $status)
        );
        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
