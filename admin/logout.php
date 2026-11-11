<?php
require 'common.php';
require_admin();
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Location: dashboard.php');exit;}
check_csrf();
$_SESSION=[];
if(ini_get('session.use_cookies')){$params=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$params['path'],$params['domain'],$params['secure'],$params['httponly']);}
session_destroy();
header('Location: login.php',true,303);
exit;
