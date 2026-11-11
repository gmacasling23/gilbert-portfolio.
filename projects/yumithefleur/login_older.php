<?php
session_start();
header("Content-Type: application/json");

require_once "db.php";

// Only accept POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method"
    ]);
    exit;
}

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

// Basic validation
if (empty($username) || empty($password)) {
    echo json_encode([
        "status" => "error",
        "message" => "Username and password are required"
    ]);
    exit;
}

// Look up user
// Added is_admin in SELECT query
$stmt = $conn->prepare("
    SELECT id, username, password, is_admin 
    FROM users 
    WHERE username = ?
");

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => $conn->error
    ]);
    exit;
}

$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();
$user   = $result->fetch_assoc();

$stmt->close();

// Verify password
if (!$user || !password_verify($password, $user["password"])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid username or password"
    ]);
    exit;
}

// Set session
$_SESSION["user_id"]  = $user["id"];
$_SESSION["username"] = $user["username"];
$_SESSION["is_admin"] = (bool) $user["is_admin"];

// Success response
echo json_encode([
    "status"   => "success",
    "username" => $user["username"],
    "is_admin" => (bool) $user["is_admin"]
]);

$conn->close();
?>