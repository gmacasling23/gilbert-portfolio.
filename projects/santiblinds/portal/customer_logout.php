<?php
// POST -> ends the customer session only (an admin login in the same browser is left alone).
require_once __DIR__ . '/config.php';
requireMethod('POST');
unset($_SESSION['customer_id']);
session_regenerate_id(true);
respond(['success' => true]);
