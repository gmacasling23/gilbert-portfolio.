<?php
session_start();
header("Content-Type: application/json");

require_once "db.php";

// CHECK LOGIN
if (!isset($_SESSION["user_id"])) {
    echo json_encode([
        "status"  => "error",
        "message" => "User not logged in"
    ]);
    exit;
}

$user_id = (int) $_SESSION["user_id"];

// GET ORDERS with counts by status
$sql = "
    SELECT
        id,
        order_type,
        status,
        created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => $conn->error]);
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$orders   = [];
$active    = 0;
$completed = 0;
$pending   = 0;

while ($row = $result->fetch_assoc()) {
    $orders[] = [
        "id"         => $row["id"],
        "type"       => $row["order_type"],
        "status"     => $row["status"],
        "created_at" => $row["created_at"]
    ];

    switch (strtolower($row["status"])) {
        case "active":    $active++;    break;
        case "completed": $completed++; break;
        case "pending":   $pending++;   break;
    }
}

echo json_encode([
    "status"  => "success",
    "orders"  => $orders,
    "counts"  => [
        "active"    => $active,
        "completed" => $completed,
        "pending"   => $pending
    ]
]);

$stmt->close();
$conn->close();
?>