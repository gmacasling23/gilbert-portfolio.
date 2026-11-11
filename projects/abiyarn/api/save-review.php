<?php
require_once __DIR__ . '/../config.php';
session_start();

function reviewRedirect(string $result, int $orderId): never {
    header('Location: ../my-orders.php?review=' . rawurlencode($result) . '#order-' . $orderId);
    exit;
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../index.html');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$orderId = (int)($_POST['order_id'] ?? 0);
if (empty($_SESSION['review_csrf']) || !hash_equals($_SESSION['review_csrf'], (string)($_POST['csrf'] ?? ''))) {
    reviewRedirect('error', $orderId);
}

$rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
$reviewText = trim((string)($_POST['review_text'] ?? ''));
if (!$orderId || $rating === false || $rating < 1 || $rating > 5 || $reviewText === '' || mb_strlen($reviewText) > 1000) {
    reviewRedirect('error', $orderId);
}

try {
    $pdo = db();
    $orderStmt = $pdo->prepare(
        'SELECT o.id, o.status
           FROM orders o
           JOIN users u ON u.id = :user_id AND u.email = o.email
          WHERE o.id = :order_id
          LIMIT 1'
    );
    $orderStmt->execute(['user_id' => (int)$_SESSION['user_id'], 'order_id' => $orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order || !in_array($order['status'], ['completed', 'cancelled'], true)) {
        reviewRedirect('error', $orderId);
    }

    $insert = $pdo->prepare(
        'INSERT INTO reviews (order_id, user_id, rating, review_text)
         VALUES (:order_id, :user_id, :rating, :review_text)'
    );
    $insert->execute([
        'order_id' => $orderId,
        'user_id' => (int)$_SESSION['user_id'],
        'rating' => $rating,
        'review_text' => $reviewText,
    ]);
    reviewRedirect('saved', $orderId);
} catch (PDOException $e) {
    if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        reviewRedirect('exists', $orderId);
    }
    error_log('AbiYarn save review: ' . $e->getMessage());
    reviewRedirect('error', $orderId);
} catch (Throwable $e) {
    error_log('AbiYarn save review: ' . $e->getMessage());
    reviewRedirect('error', $orderId);
}
