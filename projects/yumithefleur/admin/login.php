<?php
session_start();
require_once __DIR__ . '/../db.php';

/* ── Already logged in as admin ── */
if (isset($_SESSION['user_id']) && !empty($_SESSION['is_admin'])) {
    header('Location: index.php');
    exit;
}

$error = '';

/* ── Handle POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, is_admin FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Invalid username or password.';
        } elseif (empty($user['is_admin'])) {
            $error = 'You do not have admin access.';
        } else {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = true;
            $conn->close();
            header('Location: index.php');
            exit;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login — Yumi the Fleur</title>
  <link rel="icon" type="image/png" href="../Images/logo/Transparent.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
      height: 100%;
      font-family: 'Inter', sans-serif;
      background: #F9F4FF;
      color: #3A1A5A;
    }

    .page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background: linear-gradient(135deg, #3A1A5A 0%, #6b3fa0 50%, #3A1A5A 100%);
    }

    .login-wrap {
      display: flex;
      width: 100%;
      max-width: 900px;
      min-height: 500px;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 24px 64px rgba(0,0,0,.35);
    }

    /* ── Left panel ── */
    .login-left {
      flex: 1;
      background: #3A1A5A;
      padding: 48px 40px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .login-left .logo { display: flex; align-items: center; gap: 12px; }
    .login-left .logo img { width: 44px; height: 44px; border-radius: 10px; object-fit: cover; }
    .login-left .logo-text strong { display: block; color: #fff; font-size: 16px; font-weight: 700; }
    .login-left .logo-text span   { display: block; color: rgba(255,255,255,.45); font-size: 12px; margin-top: 1px; }

    .login-left .tagline { color: rgba(255,255,255,.9); }
    .login-left .tagline h2 { font-size: 26px; font-weight: 700; line-height: 1.3; margin-bottom: 12px; }
    .login-left .tagline p  { font-size: 14px; color: rgba(255,255,255,.5); line-height: 1.7; }

    .login-left .stats-mini { display: flex; gap: 24px; }
    .login-left .stats-mini div strong { display: block; color: #FFADC7; font-size: 22px; font-weight: 800; }
    .login-left .stats-mini div span   { display: block; color: rgba(255,255,255,.45); font-size: 11px; margin-top: 2px; }

    /* ── Right panel (form) ── */
    .login-right {
      width: 400px;
      flex-shrink: 0;
      background: #fff;
      padding: 48px 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    .login-right h1 { font-size: 22px; font-weight: 700; color: #3A1A5A; margin-bottom: 6px; }
    .login-right .sub { font-size: 13px; color: #8b62b5; margin-bottom: 32px; }

    /* ── Error alert ── */
    .alert-error {
      display: flex; align-items: center; gap: 10px;
      background: #fce4ec; border: 1px solid #f48fb1;
      border-radius: 8px; padding: 12px 14px;
      color: #b71c1c; font-size: 13px; font-weight: 500;
      margin-bottom: 20px;
    }
    .alert-error svg { width: 16px; height: 16px; stroke: #b71c1c; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }

    /* ── Form fields ── */
    .field { margin-bottom: 18px; }
    .field label {
      display: block; font-size: 12px; font-weight: 600;
      color: #5b2d8e; margin-bottom: 6px; letter-spacing: .3px;
    }
    .field input {
      width: 100%; padding: 11px 14px;
      border: 1.5px solid #ecdeff; border-radius: 8px;
      font-size: 14px; font-family: 'Inter', sans-serif;
      color: #3A1A5A; background: #F9F4FF;
      outline: none; transition: border-color .18s, box-shadow .18s;
    }
    .field input:focus {
      border-color: #b582ff;
      box-shadow: 0 0 0 3px rgba(181,130,255,.15);
      background: #fff;
    }
    .field input::placeholder { color: #c9a8f5; }

    .field-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
    .field-row label { margin-bottom: 0; }
    .field-row a { font-size: 12px; color: #b582ff; font-weight: 500; }
    .field-row a:hover { text-decoration: underline; }

    .btn-login {
      width: 100%; padding: 12px;
      background: #1a3528; color: #fff;
      border: none; border-radius: 8px;
      font-size: 14px; font-weight: 600;
      font-family: 'Inter', sans-serif;
      cursor: pointer; transition: background .18s;
      margin-top: 6px;
    }
    .btn-login:hover { background: #2d6a4f; }

    .back-link {
      text-align: center; margin-top: 20px;
      font-size: 12.5px; color: #6b8c72;
    }
    .back-link a { color: #2d6a4f; font-weight: 600; }
    .back-link a:hover { text-decoration: underline; }

    /* ── Responsive ── */
    @media (max-width: 700px) {
      .login-left { display: none; }
      .login-right { width: 100%; padding: 36px 28px; }
      .login-wrap { max-width: 420px; border-radius: 12px; }
    }
  </style>
</head>
<body>

<div class="page">
  <div class="login-wrap">

    <!-- Left decorative panel -->
    <div class="login-left">
      <div class="logo">
        <img src="../Images/logo/Transparent.png" alt="Yumi the Fleur">
        <div class="logo-text">
          <strong>Yumi the Fleur</strong>
          <span>Admin Panel</span>
        </div>
      </div>

      <div class="tagline">
        <h2>Manage your<br>commission studio.</h2>
        <p>Track orders, monitor revenue, and manage customers — all in one place.</p>
      </div>

      <div class="stats-mini">
        <div>
          <strong>✦</strong>
          <span>Orders</span>
        </div>
        <div>
          <strong>✦</strong>
          <span>Revenue</span>
        </div>
        <div>
          <strong>✦</strong>
          <span>Customers</span>
        </div>
      </div>
    </div>

    <!-- Right form panel -->
    <div class="login-right">
      <h1>Admin Sign In</h1>
      <p class="sub">Enter your credentials to access the dashboard.</p>

      <?php if ($error !== ''): ?>
      <div class="alert-error">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off" novalidate>
        <div class="field">
          <label for="username">Username</label>
          <input
            type="text"
            id="username"
            name="username"
            placeholder="Enter your username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            required
            autofocus
          >
        </div>

        <div class="field">
          <div class="field-row">
            <label for="password">Password</label>
          </div>
          <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter your password"
            required
          >
        </div>

        <button type="submit" class="btn-login">Sign In to Dashboard</button>
      </form>

      <p class="back-link">
        ← <a href="index.html">Back to main site</a>
      </p>
    </div>

  </div>
</div>

</body>
</html>
