<?php
session_start();

// Only destroy the session if the user is actually an admin
// This prevents regular users from accidentally hitting this endpoint
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
    // Unset all session variables
    $_SESSION = [];

    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params["path"],   $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destroy the session
    session_destroy();
}

// Always redirect to admin login
header('Location: login.php');
exit;
?>
