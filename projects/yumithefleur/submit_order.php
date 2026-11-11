<?php

session_start();

header('Content-Type: application/json');

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request method"
    ]);

    exit;
}

/* =========================
   CHECK LOGIN
========================= */

if (!isset($_SESSION['user_id'])) {

    echo json_encode([
        "success" => false,
        "message" => "User not logged in"
    ]);

    exit;
}

try {

    /* =========================
       USER ID
    ========================= */

    $user_id = $_SESSION['user_id'];

    /* =========================
       BASIC FORM DATA
    ========================= */

    $customerName = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $characterName = trim($_POST['character_name'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $pose = trim($_POST['pose'] ?? '');
    $background = trim($_POST['background'] ?? '');
    $palette = trim($_POST['palette'] ?? '');
    $style = trim($_POST['style'] ?? '');

    $order_type = trim($_POST['order_type'] ?? 'commission');

    $status = "Pending";

    /* =========================
       FILE UPLOADS
    ========================= */

    $uploadedFiles = [];

    if (
        isset($_FILES['references']) &&
        !empty($_FILES['references']['name'][0])
    ) {

        $uploadDir = "uploads/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['references']['tmp_name'] as $key => $tmpName) {

            if ($_FILES['references']['error'][$key] === 0) {

                $originalName = basename(
                    $_FILES['references']['name'][$key]
                );

                $extension = strtolower(
                    pathinfo($originalName, PATHINFO_EXTENSION)
                );

                $newName =
                    uniqid("ref_", true) . "." . $extension;

                $targetPath = $uploadDir . $newName;

                if (move_uploaded_file($tmpName, $targetPath)) {

                    $uploadedFiles[] = $targetPath;
                }
            }
        }
    }

    /* =========================
       JSON DETAILS
    ========================= */

    $details = [

        "customer" => [
            "name" => $customerName,
            "email" => $email
        ],

        "character" => [
            "name" => $characterName,
            "gender" => $gender,
            "description" => $description
        ],

        "commission" => [
            "style" => $style,
            "pose" => $pose,
            "background" => $background,
            "palette" => explode(",", $palette)
        ],

        "references" => $uploadedFiles
    ];

    $detailsJson = json_encode($details);

    /* =========================
       INSERT ORDER
    ========================= */

    $stmt = $conn->prepare("
        INSERT INTO orders (
            user_id,
            order_type,
            status,
            details
        )
        VALUES (?, ?, ?, ?)
    ");

    if (!$stmt) {

        throw new Exception(
            "Prepare failed: " . $conn->error
        );
    }

    $stmt->bind_param(
        "isss",
        $user_id,
        $order_type,
        $status,
        $detailsJson
    );

    if ($stmt->execute()) {

        echo json_encode([
            "success" => true,
            "message" => "Commission submitted successfully"
        ]);

    } else {

        echo json_encode([
            "success" => false,
            "message" => "Database insert failed"
        ]);
    }

    $stmt->close();

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

$conn->close();

?>