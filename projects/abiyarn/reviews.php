<?php
require_once __DIR__ . '/config.php';

function reviewsEscape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function reviewPageUrl(int $page): string {
    $query = $_GET;
    $query['page'] = $page;
    return '?' . http_build_query($query);
}

$rating = (int)($_GET['rating'] ?? 0);
if (!in_array($rating, [0, 1, 2, 3, 4, 5], true)) $rating = 0;
$search = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$where = [];
$params = [];
if ($rating > 0) {
    $where[] = 'r.rating = :rating';
    $params['rating'] = $rating;
}
if ($search !== '') {
    $where[] = '(r.review_text LIKE :review_text OR o.order_num LIKE :order_num OR o.name LIKE :customer_name)';
    $like = '%' . addcslashes($search, '\\%_') . '%';
    $params['review_text'] = $params['order_num'] = $params['customer_name'] = $like;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$reviews = [];
$loadError = '';
$reviewCount = 0;
$averageRating = 0.0;

try {
    $pdo = db();
    $stats = $pdo->query('SELECT COUNT(*) AS total, COALESCE(AVG(rating), 0) AS average FROM reviews')->fetch(PDO::FETCH_ASSOC);
    $reviewCount = (int)$stats['total'];
    $averageRating = (float)$stats['average'];

    $countStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM reviews r JOIN orders o ON o.id = r.order_id' . $whereSql
    );
    $countStmt->execute($params);
    $filteredCount = (int)$countStmt->fetchColumn();
    $pages = max(1, (int)ceil($filteredCount / $perPage));
    $page = min($page, $pages);

    $listStmt = $pdo->prepare(
        'SELECT r.rating, r.review_text, r.created_at, o.name
           FROM reviews r
           JOIN orders o ON o.id = r.order_id' . $whereSql . '
          ORDER BY r.created_at DESC, r.id DESC
          LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
    );
    $listStmt->execute($params);
    $reviews = $listStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('AbiYarn reviews page: ' . $e->getMessage());
    $loadError = 'Reviews are temporarily unavailable. Please try again shortly.';
    $filteredCount = 0;
    $pages = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Reviews - AbiYarn</title>
  <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/header.css">
  <link rel="stylesheet" href="css/auth.css">
  <link rel="stylesheet" href="css/modals.css">
  <link rel="stylesheet" href="css/responsive.css">
  <link rel="stylesheet" href="css/reviews.css">
</head>
<body class="reviews-page auth-logged-out">
  <header class="header">
    <div class="container">
      <div class="header-content">
        <div class="logo"><a href="index.html"><img src="assets/logoone.png" alt="AbiYarn Logo" class="logo-img"></a></div>
        <nav class="nav" aria-label="Main navigation">
          <a href="index.html">Home</a>
          <a href="about.html">About</a>
          <a href="menu.html">Menu</a>
          <a href="reviews.php" class="active">Reviews</a>
          <a href="inquiry.html">Order Inquiry</a>
          <a href="contact.html">Contact</a>
        </nav>
        <div class="header-right">
          <div class="account-area" id="accountArea">
            <button class="account-btn" id="accountBtn" aria-label="Account">👤 <span id="accountLabel" class="account-label-text">Login</span></button>
            <div class="account-dropdown" id="accountDropdown">
              <div class="account-dropdown-name" id="accountName"></div>
              <a href="admin/index.php" id="accountAdminLink" class="account-dropdown-link" style="display:none">🛠️ Admin Dashboard</a>
              <button class="account-dropdown-link" id="logoutBtn">↩️ Log out</button>
            </div>
          </div>
          <button class="hamburger" id="hamburgerBtn" aria-label="Open menu"><span></span><span></span><span></span></button>
        </div>
      </div>
    </div>
  </header>

  <div class="mobile-nav" id="mobileNav">
    <div class="mobile-nav-content">
      <div class="mobile-nav-header">
        <img src="assets/logoone.png" alt="AbiYarn" class="mobile-logo">
        <button class="mobile-close" id="mobileClose" aria-label="Close menu">×</button>
      </div>
      <div class="mobile-nav-links">
        <a href="index.html">Home</a>
        <a href="about.html">About</a>
        <a href="menu.html">Menu</a>
        <a href="reviews.php" class="active">Reviews</a>
        <a href="inquiry.html">Order Inquiry</a>
        <a href="contact.html">Contact</a>
      </div>
    </div>
  </div>

  <main class="reviews-main">
    <section class="reviews-heading">
      <div>
        <p class="reviews-eyebrow">Made by hand, loved for years</p>
        <h1>Customer reviews</h1>
        <p>Notes from customers who have ordered from AbiYarn.</p>
      </div>
      <div class="rating-summary">
        <strong><?= number_format($averageRating, 1) ?></strong>
        <span class="review-stars" aria-label="Average rating out of five">★★★★★</span>
        <small><?= number_format($reviewCount) ?> reviews</small>
      </div>
    </section>

    <section class="review-browser" aria-label="Browse customer reviews">
      <form class="review-filter" method="get">
        <label class="review-search-label">
          <span>Search reviews</span>
          <input type="search" name="q" value="<?= reviewsEscape($search) ?>" placeholder="Review text or order number">
        </label>
        <label>
          <span>Rating</span>
          <select name="rating">
            <option value="0" <?= $rating === 0 ? 'selected' : '' ?>>All ratings</option>
            <?php for ($stars = 5; $stars >= 1; $stars--): ?>
              <option value="<?= $stars ?>" <?= $rating === $stars ? 'selected' : '' ?>><?= $stars ?> star<?= $stars === 1 ? '' : 's' ?></option>
            <?php endfor ?>
          </select>
        </label>
        <button type="submit" class="review-filter-button">Filter reviews</button>
        <?php if ($search !== '' || $rating !== 0): ?><a class="review-clear" href="reviews.php">Clear</a><?php endif ?>
      </form>

      <?php if ($loadError !== ''): ?>
        <div class="review-empty" role="alert"><?= reviewsEscape($loadError) ?></div>
      <?php elseif (!$reviews): ?>
        <div class="review-empty">
          <h2>No reviews match these filters</h2>
          <p>Try another rating or search term.</p>
        </div>
      <?php else: ?>
        <p class="review-result-count">Showing <?= count($reviews) ?> of <?= number_format($filteredCount) ?> matching reviews</p>
        <div class="review-list">
          <?php foreach ($reviews as $review):
            $firstName = explode(' ', trim((string)$review['name']))[0] ?: 'Customer';
            $stars = (int)$review['rating'];
          ?>
            <article class="review-entry">
              <div class="review-entry-top">
                <div class="review-stars" aria-label="<?= $stars ?> out of 5 stars"><?= str_repeat('★', $stars) ?><span><?= str_repeat('☆', 5 - $stars) ?></span></div>
                <time datetime="<?= reviewsEscape((string)$review['created_at']) ?>"><?= date('M j, Y', strtotime((string)$review['created_at'])) ?></time>
              </div>
              <blockquote><?= nl2br(reviewsEscape((string)$review['review_text'])) ?></blockquote>
              <p class="review-customer">Verified order · <?= reviewsEscape($firstName) ?></p>
            </article>
          <?php endforeach ?>
        </div>
        <?php if ($pages > 1): ?>
          <nav class="review-pagination" aria-label="Review pages">
            <?php if ($page > 1): ?><a href="<?= reviewsEscape(reviewPageUrl($page - 1)) ?>">← Previous</a><?php endif ?>
            <span>Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a href="<?= reviewsEscape(reviewPageUrl($page + 1)) ?>">Next →</a><?php endif ?>
          </nav>
        <?php endif ?>
      <?php endif ?>
    </section>

    <footer class="reviews-footer">
      <span>Have an order ready to review?</span>
      <a href="my-orders.php">Open My Orders →</a>
    </footer>
  </main>
  <div class="modal" id="authModal">
    <div class="modal-overlay" id="authOverlay"></div>
    <div class="modal-content auth-modal-content">
      <div class="modal-header">
        <div class="auth-tabs">
          <button type="button" class="auth-tab active" id="authTabLogin">Login</button>
          <button type="button" class="auth-tab" id="authTabRegister">Create Account</button>
        </div>
        <button class="close-btn" id="closeAuthBtn" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <div id="authError" class="auth-error" style="display:none"></div>
        <form id="loginForm" class="auth-form">
          <div class="field"><label>Username</label><input type="text" name="username" required autocomplete="username"></div>
          <div class="field"><label>Password</label><input type="password" name="password" required autocomplete="current-password"></div>
          <button type="submit" class="btn btn-primary btn-full">Login</button>
        </form>
        <form id="registerForm" class="auth-form" style="display:none">
          <div class="field"><label>Full Name</label><input type="text" name="full_name" required autocomplete="name"></div>
          <div class="field"><label>Email</label><input type="email" name="email" required autocomplete="email"></div>
          <div class="field"><label>Username</label><input type="text" name="username" required autocomplete="username"></div>
          <div class="field"><label>Password</label><input type="password" name="password" required minlength="6" autocomplete="new-password"></div>
          <button type="submit" class="btn btn-primary btn-full">Create Account</button>
        </form>
      </div>
    </div>
  </div>
  <script src="js/main.js"></script>
  <script src="js/auth.js"></script>
</body>
</html>