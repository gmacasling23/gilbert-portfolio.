<?php

header('Content-Type: application/json');

include "db.php";

$username = trim($_POST['username']);
$email = trim($_POST['email']);
$password = $_POST['password'];
$confirm = $_POST['confirm'];

if($password !== $confirm){

    echo json_encode([
        "status" => "error",
        "message" => "Passwords do not match."
    ]);

    exit();
}

$check = $conn->prepare(
    "SELECT * FROM users WHERE email=?"
);

$check->bind_param("s", $email);

$check->execute();

$result = $check->get_result();

if($result->num_rows > 0){

    echo json_encode([
        "status" => "error",
        "message" => "Email already exists."
    ]);

    exit();
}

$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);

$stmt = $conn->prepare(
    "INSERT INTO users(username,email,password)
     VALUES(?,?,?)"
);

$stmt->bind_param(
    "sss",
    $username,
    $email,
    $hashedPassword
);

if($stmt->execute()){

    echo json_encode([
        "status" => "success"
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Signup failed."
    ]);
}
?>