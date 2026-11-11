<?php
$host   = "sql111.infinityfree.com";
$user   = "if0_43396285";
$pass   = "gilbert123z";
$dbname = "if0_43396285_yumi";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die(json_encode([
        "status"  => "error",
        "message" => "Database connection failed: " . $conn->connect_error
    ]));
}
?>