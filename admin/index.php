<?php
require_once __DIR__.'/common.php';
header('Location: '.(!empty($_SESSION['admin_id']) ? 'dashboard.php' : 'login.php'), true, 302);
exit;
