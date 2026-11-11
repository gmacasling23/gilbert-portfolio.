<?php
require_once __DIR__ . '/config.php';
requireMethod('POST');

if (!empty($_SESSION['account_id'])) {
    logActivity(getDB(), (int)$_SESSION['account_id'], 'Logout', 'Customer logged out.');
}
// Only drop the customer keys so an admin session in the same browser is untouched.
unset($_SESSION['account_id'], $_SESSION['customer_id'], $_SESSION['customer_name']);
respond(['success' => true]);
