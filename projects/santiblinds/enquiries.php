<?php
// ── CORS — must be the very first output, no whitespace before <?php ──
$allowed = 'https://santiblinds.site';
// Always send the header unconditionally so the browser never blocks the response
header('Access-Control-Allow-Origin: ' . $allowed);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: false');

// Preflight request — return early
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ============================================================
//  Santi Blinds — Enquiry Form Handler
//  File: enquiries.php
//  Called by contact.html via fetch() (AJAX)
// ============================================================

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once 'db_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ── 1. Collect & sanitise inputs ────────────────────────────

function clean(string $value): string {
    return trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
}

$fname     = clean($_POST['fname']     ?? '');
$lname     = clean($_POST['lname']     ?? '');
$email     = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$phoneInput = clean($_POST['phone']    ?? '');
$phone     = $phoneInput === '' ? '' : (normalizePhilippineMobileNumber($phoneInput) ?? '');
$city      = clean($_POST['city']      ?? '');
$interest  = clean($_POST['interest']  ?? '');
$preferred = clean($_POST['preferred'] ?? '');
$rooms     = clean($_POST['rooms']     ?? '');
$budget    = clean($_POST['budget']    ?? '');
$message   = clean($_POST['message']   ?? '');
$consent   = isset($_POST['consent'])  ? 1 : 0;

// ── 2. Validate required fields ─────────────────────────────

$errors = [];

if (empty($fname))  $errors[] = 'First name is required.';
if (empty($lname))  $errors[] = 'Last name is required.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if ($phoneInput !== '' && $phone === '') {
    $errors[] = 'Enter a valid Philippine mobile number, such as +63 917 123 4567.';
}
if ($consent !== 1) $errors[] = 'You must agree to be contacted.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// ── 3. Insert into database ──────────────────────────────────

try {
    $pdo = getDB();

    $sql = "
        INSERT INTO enquiries
            (fname, lname, email, phone, city, interest, preferred, rooms, budget, message, consent)
        VALUES
            (:fname, :lname, :email, :phone, :city, :interest, :preferred, :rooms, :budget, :message, :consent)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':fname'     => $fname,
        ':lname'     => $lname,
        ':email'     => $email,
        ':phone'     => $phone     ?: null,
        ':city'      => $city      ?: null,
        ':interest'  => $interest  ?: null,
        ':preferred' => $preferred ?: null,
        ':rooms'     => $rooms     ?: null,
        ':budget'    => $budget    ?: null,
        ':message'   => $message   ?: null,
        ':consent'   => $consent,
    ]);

} catch (\Throwable $e) {   // \Throwable: PDOException is NOT PHPMailer's Exception class
    http_response_code(500);
    error_log('Enquiry DB insert failed: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Sorry, something went wrong on our end. Please try again later.',
    ]);
    exit;
}

// ── 4. Send Gmail SMTP notification ─────────────────────────
// Skipped (not failed) until real Gmail details are put in db_config.php,
// so the form still works on a fresh XAMPP install.

$mailConfigured = MAIL_APP_PASSWORD !== 'xxxx xxxx xxxx xxxx'
               && strpos(MAIL_FROM, 'your-sending') === false
               && strpos(MAIL_TO, 'where-you-receive') === false;
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

try {
    if (!$mailConfigured) {
        throw new Exception('Email notification skipped: MAIL_* settings in db_config.php are still placeholders.');
    }

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_FROM;
    $mail->Password   = MAIL_APP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->Timeout    = 15;   // default is 300s, which hangs the form if Gmail is unreachable

    // XAMPP on Windows often has no CA bundle configured, which makes the Gmail
    // TLS handshake fail. Relax verification ONLY when running on localhost.
    if ($isLocal) {
        $mail->SMTPOptions = ['ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]];
    }

    $mail->setFrom(MAIL_FROM, 'Santi Blinds Website');
    $mail->addAddress(MAIL_TO, 'Santi Blinds Admin');
    $mail->addReplyTo($email, "{$fname} {$lname}");

    $mail->Subject = "New Enquiry — {$fname} {$lname}" . ($city ? " ({$city})" : '');

    $mail->AltBody = implode("\n", [
        "NEW ENQUIRY — SANTI BLINDS",
        str_repeat("─", 40),
        "Name:      {$fname} {$lname}",
        "Email:     {$email}",
        "Phone:     " . ($phone     ?: '—'),
        "City:      " . ($city      ?: '—'),
        "Interest:  " . ($interest  ?: '—'),
        "Schedule:  " . ($preferred ?: '—'),
        "Windows:   " . ($rooms     ?: '—'),
        "Budget:    " . ($budget    ?: '—'),
        "",
        "Message:",
        ($message ?: '(no message)'),
        "",
        "Submitted: " . date('F j, Y  g:i A'),
    ]);

    $rows = [
        ['Name',     "{$fname} {$lname}"],
        ['Email',    "<a href='mailto:{$email}' style='color:#8a6d3b;'>{$email}</a>"],
        ['Phone',    $phone     ?: '—'],
        ['City',     $city      ?: '—'],
        ['Interest', $interest  ?: '—'],
        ['Schedule', $preferred ?: '—'],
        ['Windows',  $rooms     ?: '—'],
        ['Budget',   $budget    ?: '—'],
    ];

    $tableRows = '';
    foreach ($rows as [$label, $value]) {
        $tableRows .= "
            <tr>
              <td style='padding:10px 16px;font-weight:600;color:#555;background:#faf9f7;
                         width:130px;border-bottom:1px solid #ece9e3;font-family:Arial,sans-serif;font-size:13px;'>
                {$label}
              </td>
              <td style='padding:10px 16px;color:#222;border-bottom:1px solid #ece9e3;
                         font-family:Arial,sans-serif;font-size:13px;'>
                {$value}
              </td>
            </tr>";
    }

    $msgBlock = $message
        ? "<div style='margin:24px 0 0;padding:18px 20px;background:#faf9f7;
                        border-left:4px solid #c9a96e;border-radius:4px;'>
              <p style='margin:0 0 6px;font-weight:600;color:#555;font-size:12px;
                        text-transform:uppercase;letter-spacing:.08em;font-family:Arial,sans-serif;'>Message</p>
              <p style='margin:0;color:#333;font-size:14px;line-height:1.7;font-family:Arial,sans-serif;'>"
              . nl2br($message) . "</p>
           </div>"
        : '';

    $submitted = date('F j, Y \a\t g:i A');

    $mail->isHTML(true);
    $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0ede8;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0ede8;padding:32px 16px;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0"
             style="max-width:600px;width:100%;background:#fff;border-radius:8px;
                    overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);">
        <tr>
          <td style="background:#1a1a1a;padding:28px 32px;text-align:center;">
            <p style="margin:0;font-size:22px;font-weight:700;letter-spacing:.15em;color:#fff;font-family:Arial,sans-serif;">
              SANTI <span style="color:#c9a96e;">BLINDS</span>
            </p>
            <p style="margin:6px 0 0;font-size:12px;color:#aaa;letter-spacing:.1em;text-transform:uppercase;font-family:Arial,sans-serif;">
              New Enquiry Received
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding:28px 32px 8px;">
            <p style="margin:0;font-size:15px;color:#333;line-height:1.6;">
              You have a new enquiry from <strong>{$fname} {$lname}</strong>.
              Hit <strong>Reply</strong> to respond directly to them.
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding:16px 32px;">
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="border:1px solid #ece9e3;border-radius:6px;overflow:hidden;border-collapse:collapse;">
              {$tableRows}
            </table>
            {$msgBlock}
          </td>
        </tr>
        <tr>
          <td style="padding:20px 32px 28px;border-top:1px solid #ece9e3;">
            <p style="margin:0;font-size:12px;color:#999;font-family:Arial,sans-serif;">
              Submitted on {$submitted} · Santi Blinds Website
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $mail->send();

} catch (\Throwable $e) {
    error_log('Enquiry email failed: ' . $e->getMessage());
}

// ── 5. Return success ────────────────────────────────────────

echo json_encode([
    'success' => true,
    'message' => "Thank you, {$fname}! We'll be in touch within one business day.",
]);