<?php
// POST { customer: {full_name,email,phone,address}, product_id, message }
// Saves the inquiry (customer + inquiry tables) and emails the owner via Gmail SMTP.
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;

requireMethod('POST');
$b   = requestBody();
$pdo = getDB();

[$cust, $errors] = readCustomer(is_array($b['customer'] ?? null) ? $b['customer'] : [], false);
$pid = (int)($b['product_id'] ?? 0);
$msg = str($b, 'message', 2000);

$p = $pdo->prepare("SELECT name FROM product WHERE product_id = :id");
$p->execute([':id' => $pid]);
$productName = $p->fetchColumn();
if ($productName === false) $errors[] = 'Please choose the product you are asking about.';
if ($msg === '') $errors[] = 'Please enter your message.';
if ($errors) respond(['success' => false, 'errors' => $errors], 422);

$now = time();
$_SESSION['inquiry_times'] = array_values(array_filter($_SESSION['inquiry_times'] ?? [], fn($t) => $t > $now - 600));
if (count($_SESSION['inquiry_times']) >= 5) {
    respond(['success' => false, 'message' => 'Too many messages in a short time. Please wait a few minutes.'], 429);
}

$pdo->beginTransaction();
try {
    $customerId = findOrCreateCustomer($pdo, $cust);
    $pdo->prepare("INSERT INTO inquiry (customer_id, product_id, message, status) VALUES (:c, :p, :m, 'New')")
        ->execute([':c' => $customerId, ':p' => $pid, ':m' => $msg]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}
$_SESSION['inquiry_times'][] = $now;

// ── Email notification (never blocks or fails the inquiry; the row is already saved) ──
function notifyOwner(array $c, string $product, string $message): void {
    $configured = MAIL_APP_PASSWORD !== 'xxxx xxxx xxxx xxxx'
               && strpos(MAIL_FROM, 'your-sending') === false
               && strpos(MAIL_TO, 'where-you-receive') === false;
    if (!$configured) { error_log('Inquiry email skipped: MAIL_* settings in db_config.php are still placeholders.'); return; }

    $dir = __DIR__ . '/../PHPMailer/src/';
    if (!is_file($dir . 'PHPMailer.php')) { error_log('Inquiry email skipped: PHPMailer folder not found at ' . $dir); return; }
    require_once $dir . 'Exception.php';
    require_once $dir . 'PHPMailer.php';
    require_once $dir . 'SMTP.php';

    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_FROM;
        $mail->Password   = MAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 15;
        if (!empty($GLOBALS['IS_LOCAL'])) { // XAMPP often lacks a CA bundle
            $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        }
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM, 'Santi Blinds Website');
        $mail->addAddress(MAIL_TO, 'Santi Blinds Admin');
        $mail->addReplyTo($c['email'], $c['full_name']);
        $mail->Subject = 'New Enquiry — ' . $c['full_name'] . ($c['address'] ? " ({$c['address']})" : '');

        $rows = [['Name', $c['full_name']], ['Email', $c['email']], ['Phone', $c['phone']],
                 ['City', $c['address'] ?: '—'], ['Interested in', $product]];
        $tr = '';
        foreach ($rows as [$k, $v]) {
            $tr .= "<tr><td style='padding:8px 14px;font-weight:600;background:#faf9f7;border-bottom:1px solid #ece9e3'>{$h($k)}</td>"
                 . "<td style='padding:8px 14px;border-bottom:1px solid #ece9e3'>{$h($v)}</td></tr>";
        }
        $mail->isHTML(true);
        $mail->Body = "<div style='font-family:Arial,sans-serif;max-width:600px'>"
            . "<h2 style='margin:0 0 12px'>New enquiry — SANTI <span style='color:#c9a96e'>BLINDS</span></h2>"
            . "<table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #ece9e3;border-collapse:collapse;font-size:14px'>{$tr}</table>"
            . "<div style='margin-top:16px;padding:14px 16px;background:#faf9f7;border-left:4px solid #c9a96e'>" . nl2br($h($message)) . "</div>"
            . "<p style='color:#999;font-size:12px'>Submitted " . date('F j, Y \a\t g:i A') . ". Hit Reply to answer the customer directly.</p></div>";
        $mail->AltBody = "New enquiry from {$c['full_name']} <{$c['email']}>\nPhone: {$c['phone']}\nCity: " . ($c['address'] ?: '—')
                       . "\nInterested in: {$product}\n\n{$message}";
        $mail->send();
    } catch (Throwable $e) {
        error_log('Inquiry email failed: ' . $e->getMessage());
    }
}
notifyOwner($cust, (string)$productName, $msg);

respond(['success' => true, 'message' => "Thank you, " . explode(' ', $cust['full_name'])[0] . "! We'll be in touch within one business day."]);