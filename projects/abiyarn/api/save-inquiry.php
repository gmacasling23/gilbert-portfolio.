<?php
/* ============================================
   AbiYarn – Save Inquiry Endpoint
   Called by js/inquiry.js -> fetch(`${API_BASE}/save-inquiry.php`)
   Place this file at:  <site-root>/api/save-inquiry.php
   (config.php must live one level up, at <site-root>/config.php)

   NOTE: inquiry.js sends keys from_name / from_email (EmailJS template
   naming), but the `inquiries` table columns are name / email — this
   file maps between the two.
   ============================================ */

require_once __DIR__ . '/../config.php';
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    json(['error' => 'Invalid JSON payload'], 400);
}

/* Fields sent by inquiry.js's `templateParams` object */
$required = ['from_name', 'from_email', 'phone', 'order_type', 'details'];
foreach ($required as $field) {
    if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
        json(['error' => "Missing field: $field"], 400);
    }
}

/* Trim + clip to the column sizes so a long value can't cause a DB error */
$clip = static fn($v, int $max): string => mb_substr(trim((string)$v), 0, $max);

$email = $clip($input['from_email'], 200);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json(['error' => 'Please enter a valid email address.'], 422);
}

try {
    $pdo = db();

    $stmt = $pdo->prepare("
        INSERT INTO inquiries
            (name, email, phone, order_type, quantity, colors,
             needed_by, details, status, created_at)
        VALUES
            (:name, :email, :phone, :order_type, :quantity, :colors,
             :needed_by, :details, 'new', NOW())
    ");

    $stmt->execute([
        'name'       => $clip($input['from_name'], 120),
        'email'      => $email,
        'phone'      => $clip($input['phone'], 40),
        'order_type' => $clip($input['order_type'], 100),
        'quantity'   => isset($input['quantity'])  ? $clip($input['quantity'], 60)  : null,
        'colors'     => isset($input['colors'])    ? $clip($input['colors'], 200)   : null,
        'needed_by'  => isset($input['needed_by']) ? $clip($input['needed_by'], 60) : null,
        'details'    => $clip($input['details'], 5000),
    ]);

    json(['success' => true]);

} catch (Throwable $e) {
    // Keep SQL details in the server log, not in the browser response.
    error_log('AbiYarn save-inquiry: ' . $e->getMessage());
    json(['error' => 'Failed to save inquiry. Please try again.'], 500);
}
