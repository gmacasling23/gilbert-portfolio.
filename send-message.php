<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function reply(int $code, bool $ok, string $message): never {
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reply(405, false, 'Method not allowed.');
}

// Preserve the same session initialization used by the working handler.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$sessionToken = (string)($_SESSION['contact_csrf'] ?? '');
$submittedToken = (string)($_POST['csrf'] ?? '');
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    reply(403, false, 'Your session expired. Refresh the page and try again.');
}

if (!empty($_POST['website'])) {
    reply(200, true, 'Thanks for your message.');
}
if (isset($_SESSION['last_contact_sent']) && time() - (int)$_SESSION['last_contact_sent'] < 60) {
    reply(429, false, 'Please wait one minute before sending another message.');
}

$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($name === '' || strlen($name) > 100 || preg_match('/[\r\n]/', $name) ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 ||
    $subject === '' || strlen($subject) > 160 || preg_match('/[\r\n]/', $subject) ||
    $message === '' || strlen($message) > 5000) {
    reply(422, false, 'Please complete all fields with valid information.');
}

$configPath = __DIR__ . '/mail.config.php';
if (!is_file($configPath)) {
    reply(503, false, 'Email delivery is not configured.');
}

try {
    $cfg = require $configPath;
    if (!is_array($cfg)) {
        throw new RuntimeException('Mail configuration must return an array.');
    }
    $recipient = trim((string)($cfg['recipient'] ?? ''));
    $from = trim((string)($cfg['from'] ?? ''));
    $username = trim((string)($cfg['smtp_username'] ?? ''));
    $password = (string)($cfg['smtp_password'] ?? '');
    $host = trim((string)($cfg['smtp_host'] ?? ''));
    $port = (int)($cfg['smtp_port'] ?? 587);

    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) ||
        !filter_var($from, FILTER_VALIDATE_EMAIL) ||
        !filter_var($username, FILTER_VALIDATE_EMAIL) ||
        trim($password) === '' || $password === 'YOUR_GOOGLE_APP_PASSWORD' ||
        $host === '' || $port < 1 || $port > 65535) {
        reply(503, false, 'Email delivery is not configured.');
    }

    foreach (['Exception', 'PHPMailer', 'SMTP'] as $part) {
        $path = __DIR__ . '/PHPMailer/src/' . $part . '.php';
        if (!is_file($path)) {
            reply(503, false, 'Email library is missing.');
        }
        require_once $path;
    }

    $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = $host;
    $mailer->Port = $port;
    $mailer->SMTPAuth = true;
    $mailer->Username = $username;
    $mailer->Password = $password;
    $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Timeout = 12;
    $mailer->CharSet = 'UTF-8';
    $mailer->setFrom($from, 'OS Theme Portfolio');
    $mailer->addAddress($recipient);
    $mailer->addReplyTo($email, $name);
    $mailer->Subject = '[OS Theme] ' . $subject;
    $mailer->isHTML(false);
    $mailer->Body = "New portfolio contact message\n\nName: $name\nEmail: $email\nSubject: $subject\n\nMessage:\n$message\n";
    $mailer->send();
} catch (\Throwable $error) {
    error_log('OS Theme contact SMTP error: ' . $error->getMessage());
    reply(503, false, 'Email could not be sent. Please try again later.');
}

$_SESSION['last_contact_sent'] = time();
reply(200, true, 'Message sent successfully. Thank you!');
