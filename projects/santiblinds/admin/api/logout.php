<?php
require_once __DIR__ . '/config.php';
if (!empty($_SESSION['owner_id'])) recordAudit('Admin logout', 'Signed out of the admin panel.');
$_SESSION = [];
session_destroy();
echo json_encode(['success' => true]);
