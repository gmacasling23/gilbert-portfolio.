<?php
require_once __DIR__ . '/config.php';
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: index.html');
    exit;
}
if (empty($_SESSION['review_csrf'])) {
  $_SESSION['review_csrf'] = bin2hex(random_bytes(32));
}
$reviewCsrf = $_SESSION['review_csrf'];
$reviewNotice = match ($_GET['review'] ?? '') {
  'saved' => 'Thanks for sharing your review.',
  'exists' => 'A review has already been submitted for that order.',
  'error' => 'Your review could not be submitted. Please try again.',
  default => '',
};

$orders = [];
$loadError = '';
try {
    $pdo = db();
    $userStmt = $pdo->prepare('SELECT full_name, email FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute(['id' => (int)$_SESSION['user_id']]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $_SESSION = [];
        session_destroy();
        header('Location: index.html');
        exit;
    }

    $orderStmt = $pdo->prepare(
        'SELECT o.id, o.order_num, o.total, o.status, o.estimated_delivery, o.items, o.created_at,
          r.rating, r.review_text
           FROM orders o
           LEFT JOIN reviews r ON r.order_id = o.id
          WHERE o.email = :email
          ORDER BY o.created_at DESC, o.id DESC'
    );
    $orderStmt->execute(['email' => $user['email']]);
    $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('AbiYarn customer orders: ' . $e->getMessage());
    $user = $user ?? ['full_name' => $_SESSION['full_name'] ?? 'Customer', 'email' => ''];
    $loadError = 'Your orders could not be loaded right now. Please try again shortly.';
}

function orderPageEscape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$labels = [
    'pending' => 'Received',
    'processing' => 'In progress',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
$steps = ['pending', 'processing', 'completed'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders - AbiYarn</title>
  <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/orders.css">
</head>
<body class="orders-page">
  <header class="orders-header">
    <div class="orders-header-inner">
      <a class="orders-brand" href="index.html">
        <img src="assets/logoone.png" alt="AbiYarn">
        <span>AbiYarn</span>
      </a>
      <a class="store-link" href="menu.html">← Back to storefront</a>
      <a class="store-link" href="reviews.php">Customer reviews</a>
    </div>
  </header>

  <main class="orders-main">
    <div class="orders-heading">
      <div>
        <p class="orders-eyebrow">Customer account</p>
        <h1>My orders</h1>
        <p class="orders-welcome">Hello, <?= orderPageEscape((string)$user['full_name']) ?></p>
      </div>
    </div>

    <?php if ($loadError !== ''): ?>
      <div class="orders-message orders-message--error" role="alert"><?= orderPageEscape($loadError) ?></div>
    <?php elseif ($reviewNotice !== ''): ?>
      <div class="orders-message orders-message--success" role="status"><?= orderPageEscape($reviewNotice) ?></div>
    <?php endif ?>

    <?php if ($loadError === '' && !$orders): ?>
      <section class="orders-empty">
        <span class="empty-mark" aria-hidden="true">□</span>
        <h2>No orders yet</h2>
        <p>Your completed checkouts will appear here.</p>
        <a href="menu.html" class="shop-link">Browse the menu</a>
      </section>
    <?php elseif ($loadError === ''): ?>
      <section class="orders-list" aria-label="Your orders">
        <?php foreach ($orders as $order):
          $status = (string)$order['status'];
          $statusLabel = $labels[$status] ?? ucfirst($status);
          $progress = ['pending' => 0, 'processing' => 50, 'completed' => 100][$status] ?? 0;
          $currentStep = array_search($status, $steps, true);
          $items = json_decode((string)$order['items'], true);
          if (!is_array($items)) $items = [];
        ?>
          <article class="order-card" id="order-<?= (int)$order['id'] ?>">
            <div class="order-card-head">
              <div>
                <span class="order-label">Order</span>
                <h2><?= orderPageEscape((string)$order['order_num']) ?></h2>
              </div>
              <span class="order-status order-status--<?= orderPageEscape($status) ?>"><?= orderPageEscape($statusLabel) ?></span>
            </div>

            <div class="order-meta">
              <span>
                Placed <?= date('M j, Y · g:i A', strtotime((string)$order['created_at'])) ?>
                <?php if ($status !== 'cancelled' && !empty($order['estimated_delivery'])): ?>
                  <br>Estimated delivery <?= date('M j, Y', strtotime((string)$order['estimated_delivery'])) ?>
                <?php endif ?>
              </span>
              <strong>₱<?= number_format((float)$order['total'], 2) ?></strong>
            </div>

            <?php if ($status === 'cancelled'): ?>
              <div class="cancelled-note">This order was cancelled.</div>
            <?php else: ?>
              <div class="order-progress" aria-label="Order status: <?= orderPageEscape($statusLabel) ?>">
                <div class="progress-line"><span style="width:<?= $progress ?>%"></span></div>
                <div class="progress-steps">
                  <?php foreach ($steps as $stepIndex => $step): ?>
                    <div class="progress-step <?= $currentStep !== false && $stepIndex <= $currentStep ? 'is-current' : '' ?>">
                      <span class="step-dot"></span>
                      <span><?= orderPageEscape($labels[$step]) ?></span>
                    </div>
                  <?php endforeach ?>
                </div>
              </div>
            <?php endif ?>

            <?php if (in_array($status, ['completed', 'cancelled'], true) && $order['rating'] !== null): ?>
              <div class="order-review order-review--saved">
                <div class="review-title">Your review <span><?= str_repeat('★', (int)$order['rating']) ?><?= str_repeat('☆', 5 - (int)$order['rating']) ?></span></div>
                <p><?= nl2br(orderPageEscape((string)$order['review_text'])) ?></p>
              </div>
            <?php elseif (in_array($status, ['completed', 'cancelled'], true)): ?>
              <form class="order-review" method="post" action="api/save-review.php">
                <input type="hidden" name="csrf" value="<?= orderPageEscape($reviewCsrf) ?>">
                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                <label for="review-rating-<?= (int)$order['id'] ?>">Review this order</label>
                <div class="review-fields">
                  <select id="review-rating-<?= (int)$order['id'] ?>" name="rating" required aria-label="Rating">
                    <option value="">Choose a rating</option>
                    <option value="5">5 - Excellent</option>
                    <option value="4">4 - Good</option>
                    <option value="3">3 - Okay</option>
                    <option value="2">2 - Poor</option>
                    <option value="1">1 - Bad</option>
                  </select>
                  <textarea name="review_text" rows="3" maxlength="1000" placeholder="Share your experience" required></textarea>
                  <button type="submit" class="review-submit">Submit review</button>
                </div>
              </form>
            <?php endif ?>

            <details class="order-items">
              <summary>Items (<?= count($items) ?>)</summary>
              <ul>
                <?php foreach ($items as $item):
                  $itemName = (string)($item['name'] ?? ('Product #' . (int)($item['id'] ?? 0)));
                  $itemQty = max(0, (int)($item['qty'] ?? $item['quantity'] ?? 0));
                ?>
                  <li><span><?= orderPageEscape($itemName) ?></span><span>× <?= $itemQty ?></span></li>
                <?php endforeach ?>
              </ul>
            </details>
          </article>
        <?php endforeach ?>
      </section>
    <?php endif ?>
  </main>
</body>
</html>