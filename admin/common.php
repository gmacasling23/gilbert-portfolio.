<?php
require_once dirname(__DIR__).'/bootstrap.php';
ini_set('session.use_strict_mode','1');
ini_set('session.cookie_httponly','1');
ini_set('session.cookie_samesite','Lax');
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
$db=db();
function csrf():string {return $_SESSION['csrf']??=bin2hex(random_bytes(32));}
function check_csrf():void {if(!hash_equals(csrf(),(string)($_POST['csrf']??''))) {http_response_code(403);exit('Invalid form token');}}
function require_admin():void {if(empty($_SESSION['admin_id'])) {header('Location: login.php');exit;}}
function layout_start(string $title, bool $login=false):void {
  $safe=h($title);
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$safe.' — Administrator</title><link rel="stylesheet" href="admin.css"></head><body class="'.($login?'login-page':'dashboard-page').'">';
  if($login){
    echo '<main class="login-main"><div class="login-brand"><img src="../assets/logo.png" alt="GM logo"><span>Gilbert Macasling <small>Portfolio administration</small></span></div><div class="login-heading"><span>SECURE ACCESS</span><h1>Administrator sign in</h1><p>Sign in to manage your portfolio and projects.</p></div>';
  } else {
    echo '<header class="admin-header"><a class="admin-brand" href="dashboard.php"><img src="../assets/logo.png" alt=""><span>Administrator</span></a><nav aria-label="Admin navigation"><a href="dashboard.php">Dashboard</a><a href="projects.php">Projects</a><a href="settings.php">Portfolio</a><a href="account.php">Account</a></nav><form method="post" action="logout.php" class="logout-form"><input type="hidden" name="csrf" value="'.h(csrf()).'"><button type="submit" class="logout-button">Log out</button></form></header><main><div class="heading"><span>ADMINISTRATION</span><h1>'.$safe.'</h1></div>';
  }
}
function layout_end():void {echo '</main></body></html>';}
