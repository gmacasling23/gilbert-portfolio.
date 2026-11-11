<?php
require __DIR__.'/bootstrap.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!$id){http_response_code(404);exit('Not found');}
$db=db();$db->exec('CREATE TABLE IF NOT EXISTS project_archives (project_id INTEGER PRIMARY KEY, filename VARCHAR(255) NOT NULL)');
$stmt=$db->prepare('SELECT a.filename,p.title FROM project_archives a JOIN projects p ON p.id=a.project_id WHERE a.project_id=?');$stmt->execute([$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$row||!preg_match('/^[a-f0-9]{32}\.zip$/',$row['filename'])){http_response_code(404);exit('Not found');}
$file=__DIR__.'/data/project-archives/'.$row['filename'];if(!is_file($file)){http_response_code(404);exit('Not found');}
header('Content-Type: application/zip');header('X-Content-Type-Options: nosniff');header('Content-Disposition: attachment; filename="project-'.$id.'.zip"');header('Content-Length: '.filesize($file));readfile($file);
