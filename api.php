<?php
// Read-only public portfolio data. No admin credentials or private settings exposed.
require_once __DIR__.'/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
try {
 $s=site();
 $out=['name'=>$s['name'],'role'=>$s['role'],'about'=>$s['about'],'skills'=>$s['skills'],'email'=>$s['email'],'location'=>$s['location'],'facebook'=>safe_url((string)($s['facebook']??'')),'github'=>safe_url((string)($s['github']??'')),'projects'=>array_map(static fn($p)=>['title'=>$p['title'],'description'=>$p['description'],'stack'=>$p['stack'],'url'=>project_url((string)$p['url']),'image'=>safe_image_url((string)$p['image'])],$s['projects'])];
 echo json_encode($out,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
} catch(Throwable $e){http_response_code(503);echo json_encode(['error'=>'Portfolio data temporarily unavailable']);}
