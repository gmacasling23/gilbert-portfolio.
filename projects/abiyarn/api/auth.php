<?php
/* ============================================
   AbiYarn – Customer Auth Endpoint
   Called by js/auth.js from index.html (and any
   other page that includes it).

   Actions:
     GET  ?action=me        -> current session user (or null)
     POST {action:"login"}    {username, password}
     POST {action:"register"} {full_name, email, username, password}
     POST {action:"logout"}
   ============================================ */

require_once __DIR__ . '/../config.php';
session_start();
cors();

function currentSessionUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    if (empty($_SESSION['email'])) {
        $stmt = db()->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int)$_SESSION['user_id']]);
        $_SESSION['email'] = (string)$stmt->fetchColumn();
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'email'     => $_SESSION['email'] ?? '',
        'role'      => $_SESSION['role'] ?? 'user',
    ];
}

/* ── GET ?action=me ───────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'me') {
        json(['loggedIn' => !empty($_SESSION['user_id']), 'user' => currentSessionUser()]);
    }
    json(['error' => 'Unknown action'], 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json(['error' => 'Method not allowed'], 405);
}

$input  = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
$action = $input['action'] ?? '';

/* ── Logout ────────────────────────────────────── */
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    json(['success' => true]);
}

/* ── Login ─────────────────────────────────────── */
if ($action === 'login') {
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($username === '' || $password === '') {
        json(['error' => 'Username and password are required.'], 400);
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.password_hash, u.full_name, u.email, r.role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.username = :username
            LIMIT 1
        ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            json(['error' => 'Invalid username or password.'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['role']      = $user['role_name'];
        // Admin dashboard re-verifies this role server-side before granting access.
        if (in_array($user['role_name'], ['admin', 'owner'], true)) {
            $_SESSION['admin'] = true;
            $_SESSION['admin_user_id'] = (int)$user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role_name'];
        } else {
            unset($_SESSION['admin'], $_SESSION['admin_user_id'], $_SESSION['admin_username'], $_SESSION['admin_role']);
        }

        json(['success' => true, 'user' => currentSessionUser()]);
    } catch (Throwable $e) {
        json(['error' => 'Login failed.', 'detail' => $e->getMessage()], 500);
    }
}

/* ── Register ──────────────────────────────────── */
if ($action === 'register') {
    $fullName = trim((string)($input['full_name'] ?? ''));
    $email    = trim((string)($input['email'] ?? ''));
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($fullName === '' || $email === '' || $username === '' || $password === '') {
        json(['error' => 'All fields are required.'], 400);
    }
    if (strlen($password) < 6) {
        json(['error' => 'Password must be at least 6 characters.'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json(['error' => 'Please enter a valid email address.'], 400);
    }

    try {
        $pdo = db();

        $check = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1');
        $check->execute(['u' => $username, 'e' => $email]);
        if ($check->fetch()) {
            json(['error' => 'That username or email is already registered.'], 409);
        }

        $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = 'user' LIMIT 1");
        $roleStmt->execute();
        $roleId = $roleStmt->fetchColumn();
        if (!$roleId) {
            json(['error' => 'Server is not fully set up (missing "user" role). Ensure the main_database.sql schema has been imported.'], 500);
        }

        $pdo->beginTransaction();
        $insert = $pdo->prepare('INSERT INTO users (username, password_hash, full_name, email, role_id) VALUES (:u, :p, :f, :e, :r)');
        $insert->execute([
            'u' => $username,
            'p' => password_hash($password, PASSWORD_DEFAULT),
            'f' => $fullName,
            'e' => $email,
            'r' => $roleId,
        ]);
        $userId = (int)$pdo->lastInsertId();
        $pdo->commit();

        session_regenerate_id(true);
        $_SESSION['user_id']   = $userId;
        $_SESSION['username']  = $username;
        $_SESSION['full_name'] = $fullName;
        $_SESSION['email']     = $email;
        $_SESSION['role']      = 'user';

        json(['success' => true, 'user' => currentSessionUser()]);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        json(['error' => 'Registration failed.', 'detail' => $e->getMessage()], 500);
    }
}

json(['error' => 'Unknown action'], 400);
