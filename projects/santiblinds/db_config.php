<?php
    

// ============================================================
//  Santi Blinds — Database Configuration
//  Place this file ONE level above your web root (public_html)
//  for security, OR keep it here and restrict access in .htaccess
// ============================================================

define('DB_HOST', 'sql111.infinityfree.com');
define('DB_NAME', 'if0_43396285_santiblinds');
define('DB_USER', 'if0_43396285');
define('DB_PASS', 'gilbert123z');
define('DB_CHARSET', 'utf8mb4');
define('MAIL_FROM',         'your-sending-gmail@gmail.com');   // the Gmail that sends the notification
define('MAIL_TO',           'where-you-receive-it@gmail.com'); // where enquiries are delivered
define('MAIL_APP_PASSWORD', 'xxxx xxxx xxxx xxxx');           // 16-char Google App Password

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}