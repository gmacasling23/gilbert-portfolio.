<?php
declare(strict_types=1);
function db(): PDO {
 static $pdo = null;
 if ($pdo instanceof PDO) return $pdo;
 $cfg = require __DIR__.'/database.php';
 if (is_file(__DIR__.'/database.local.php')) {
  $local = require __DIR__.'/database.local.php';
  $cfg = array_replace_recursive($cfg, $local);
 }
 $driver = $cfg['driver'] ?? 'sqlite';
 if ($driver === 'mysql') {
  $m = $cfg['mysql'];
  $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $m['host'], (int)$m['port'], $m['database'], $m['charset']);
  $pdo = new PDO($dsn, $m['username'], $m['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
  $pdo->exec('CREATE TABLE IF NOT EXISTS admins (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, username VARCHAR(100) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  $pdo->exec('CREATE TABLE IF NOT EXISTS projects (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150) NOT NULL, description TEXT NOT NULL, stack TEXT NOT NULL, url TEXT NOT NULL, image TEXT NOT NULL, sort_order INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  $pdo->exec('CREATE TABLE IF NOT EXISTS settings (`key` VARCHAR(100) NOT NULL PRIMARY KEY, value LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 } elseif ($driver === 'sqlite') {
  $dir=__DIR__.'/data'; if (!is_dir($dir)) mkdir($dir,0700,true);
  $pdo = new PDO('sqlite:'.$dir.'/portfolio.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
  $pdo->exec('CREATE TABLE IF NOT EXISTS admins (id INTEGER PRIMARY KEY, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL)');
  $pdo->exec('CREATE TABLE IF NOT EXISTS projects (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, description TEXT NOT NULL DEFAULT "", stack TEXT NOT NULL DEFAULT "", url TEXT NOT NULL DEFAULT "", image TEXT NOT NULL DEFAULT "", sort_order INTEGER NOT NULL DEFAULT 0)');
  $pdo->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
 } else { throw new RuntimeException('Unsupported database driver'); }
 $pdo->exec('CREATE TABLE IF NOT EXISTS project_archives (project_id INTEGER PRIMARY KEY, filename VARCHAR(255) NOT NULL)');
 if (!(int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn()) {
  $stmt=$pdo->prepare('INSERT INTO admins(username,password_hash) VALUES(?,?)');
  $stmt->execute(['gilbert',password_hash('gilbert123',PASSWORD_DEFAULT)]);
 }
 $stmt=$pdo->prepare('SELECT COUNT(*) FROM settings WHERE `key`=?');
 $stmt->execute(['projects_seeded']);
 if (!(int)$stmt->fetchColumn()) {
  $base=require __DIR__.'/config.php';
  if (!(int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn()) {
   $insert=$pdo->prepare('INSERT INTO projects(title,description,stack,url,image,sort_order) VALUES(?,?,?,?,?,?)');
   foreach ($base['projects'] as $i=>$p) $insert->execute([$p['title'],$p['description'],$p['stack'],$p['url']==='#'?'':$p['url'],'',$i]);
  }
  $pdo->prepare('INSERT INTO settings(`key`,value) VALUES(?,?)')->execute(['projects_seeded','1']);
 }
 return $pdo;
}
function site(): array {
 $base=require __DIR__.'/config.php'; $pdo=db();
 foreach ($pdo->query('SELECT `key`,value FROM settings') as $row) $base[$row['key']]=$row['key']==='skills'?(array)json_decode($row['value'],true):$row['value'];
 $base['projects']=$pdo->query('SELECT p.*, CASE WHEN a.project_id IS NULL THEN 0 ELSE 1 END AS has_archive FROM projects p LEFT JOIN project_archives a ON a.project_id=p.id ORDER BY p.sort_order,p.id')->fetchAll(PDO::FETCH_ASSOC);
 return $base;
}
function h($value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function safe_url(string $url):string{return preg_match('~^https?://~i',trim($url))?trim($url):'';}

/** Allow trusted remote image URLs or existing images inside public project folders. */
function safe_image_url(string $value): string {
 $value=trim(str_replace('\\','/',$value));
 if (preg_match('~^https?://~i',$value)) return safe_url($value);
 if (!preg_match('~^projects/[a-zA-Z0-9_-]+/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp|gif)$~iD',$value)) return '';
 $root=realpath(__DIR__.'/projects'); $file=realpath(__DIR__.'/'.$value);
 return ($root && $file && is_file($file) && str_starts_with($file,$root.DIRECTORY_SEPARATOR)) ? $value : '';
}
/** Local projects are deliberately confined to the public projects directory. */
function local_project_url(string $value): string {
 $value=trim(str_replace('\\','/',$value));
 if (!preg_match('~^projects/([a-zA-Z0-9_-]+)/(index\.(?:php|html)|[a-zA-Z0-9_-]+\.(?:php|html))$~D', $value)) return '';
 $root=realpath(__DIR__.'/projects');
 $file=realpath(__DIR__.'/'.$value);
 if (!$root || !$file || !is_file($file) || !str_starts_with($file,$root.DIRECTORY_SEPARATOR)) return '';
 return $value;
}
function project_url(string $value): string { return local_project_url($value) ?: safe_url($value); }
