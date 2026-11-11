<?php

session_start();

header("Content-Type: application/json");

/* DATABASE */
require_once "db.php";

/* ONLY ALLOW POST */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    echo json_encode([
        "status" => "error",
        "message" => "Invalid request"
    ]);

    exit;
}

/* GET FORM DATA */
$username = trim($_POST["username"] ?? "");
$password = trim($_POST["password"] ?? "");

/* VALIDATE */
if (empty($username) || empty($password)) {

    echo json_encode([
        "status" => "error",
        "message" => "All fields are required"
    ]);

    exit;
}

/* FIND USER */
$sql = "
SELECT
    id,
    username,
    password
FROM users
WHERE username = ?
LIMIT 1
";

$stmt = $conn->prepare($sql);

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

/* CHECK USER EXISTS */
if ($result->num_rows === 0) {

    echo json_encode([
        "status" => "error",
        "message" => "User not found"
    ]);

    exit;
}

$user = $result->fetch_assoc();

/* VERIFY PASSWORD */

/* IF USING HASHED PASSWORDS */
if (!password_verify($password, $user["password"])) {

    echo json_encode([
        "status" => "error",
        "message" => "Wrong password"
    ]);

    exit;
}

/*
IF YOUR PASSWORDS ARE NOT HASHED,
USE THIS INSTEAD:

if ($password !== $user["password"]) {

    echo json_encode([
        "status" => "error",
        "message" => "Wrong password"
    ]);

    exit;
}
*/

/* SAVE SESSION */
$_SESSION["user_id"] = $user["id"];
$_SESSION["username"] = $user["username"];

/* SUCCESS */
echo json_encode([
    "status" => "success",
    "username" => $user["username"]
]);

$stmt->close();
$conn->close();

?>