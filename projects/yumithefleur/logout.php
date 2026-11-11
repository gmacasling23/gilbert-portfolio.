<?php
session_start();
session_unset();
session_destroy();

// Clear the session cookie so the browser doesn't resend it
if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
}

header("Content-Type: application/json");
echo json_encode(["status" => "success"]);
exit;