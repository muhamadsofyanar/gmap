<?php
// Private test runner only; this does not replace the production front controller.
if(PHP_SAPI!=='cli-server'||getenv('PLATFORM_MYSQL_TEST')!=='1'){http_response_code(404);exit;}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/assets/')&&is_file(__DIR__.'/../public'.$path))return false;
if($path==='/client-starsender-webhook.php'){require __DIR__.'/../public/client-starsender-webhook.php';return;}
$_GET['url']=ltrim($path,'/');require __DIR__.'/../public/index.php';
