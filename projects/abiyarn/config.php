<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
/* ============================================
   AbiYarn – Database Configuration
   ============================================
   Fill in your hosting details here.
   This file is included by all API endpoints.
   ============================================ */
define('DB_HOST', 'sql111.infinityfree.com');
define('DB_NAME', 'if0_43396285_abiyarn');
define('DB_USER', 'if0_43396285');
define('DB_PASS', 'gilbert123z');
define('DB_CHARSET', 'utf8mb4');



/* CORS — set to your actual domain in production */
define('ALLOWED_ORIGIN', '*');

/* ── PDO helper ─────────────────────────────── */
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $dsn = 'mysql:host=' . DB_HOST
         . ';dbname=' . DB_NAME
         . ';charset=' . DB_CHARSET;

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function setOrderStatus(PDO $pdo, int $orderId, string $newStatus): string {
    $allowed = ['pending', 'processing', 'completed', 'cancelled'];
    if ($orderId < 1 || !in_array($newStatus, $allowed, true)) {
        throw new DomainException('Invalid order status.', 422);
    }

    $pdo->beginTransaction();
    try {
        $orderStmt = $pdo->prepare('SELECT status, items FROM orders WHERE id = :id FOR UPDATE');
        $orderStmt->execute(['id' => $orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new DomainException('Order not found.', 404);
        }

        $oldStatus = (string)$order['status'];
        if ($oldStatus === $newStatus) {
            $pdo->commit();
            return 'Status unchanged.';
        }

        if ($newStatus === 'cancelled' || $oldStatus === 'cancelled') {
            $items = json_decode((string)$order['items'], true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($items)) {
                throw new DomainException('The order items could not be read.');
            }

            $quantities = [];
            foreach ($items as $item) {
                $productId = (int)($item['id'] ?? 0);
                $qty = (int)($item['qty'] ?? $item['quantity'] ?? 0);
                if ($productId < 1 || $qty < 1) {
                    throw new DomainException('The order contains invalid items.');
                }
                $quantities[$productId] = ($quantities[$productId] ?? 0) + $qty;
            }
            ksort($quantities, SORT_NUMERIC);

            $productStmt = $pdo->prepare('SELECT stock, status FROM products WHERE id = :id FOR UPDATE');
            $stockStmt = $pdo->prepare(
                'UPDATE products SET stock = :stock, status = :status
                 WHERE id = :id AND stock = :old_stock AND status = :old_status'
            );

            foreach ($quantities as $productId => $qty) {
                $productStmt->execute(['id' => $productId]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);

                if ($newStatus !== 'cancelled' && !$product) {
                    throw new DomainException("Product #$productId is no longer available.", 409);
                }
                if (!$product) continue;

                $stock = (int)$product['stock'];
                if ($newStatus !== 'cancelled' && ($product['status'] !== 'available' || $stock < $qty)) {
                    throw new DomainException("Not enough stock to reopen this order for product #$productId.", 409);
                }

                $remaining = $newStatus === 'cancelled' ? $stock + $qty : $stock - $qty;
                $stockStmt->execute([
                    'stock' => $remaining,
                    'status' => $remaining === 0 ? 'out_of_stock' : 'available',
                    'id' => $productId,
                    'old_stock' => $stock,
                    'old_status' => $product['status'],
                ]);
                if ($stockStmt->rowCount() !== 1) {
                    throw new DomainException('Product stock changed. Please retry the status update.', 409);
                }
            }
        }

        $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id')
            ->execute(['status' => $newStatus, 'id' => $orderId]);
        $pdo->commit();

        if ($newStatus === 'cancelled') return 'Order cancelled and available items returned to stock.';
        if ($oldStatus === 'cancelled') return 'Order reopened and items deducted from stock.';
        return 'Order status updated.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/* ── CORS headers ───────────────────────────── */
function cors(): void {
    header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

/* ── JSON response helper ───────────────────── */
function json(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
