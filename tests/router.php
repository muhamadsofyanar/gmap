<?php
// PHP built-in server router for integration tests only. Excluded from Docker image.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/';
if(str_starts_with($path,'/assets/') && is_file(__DIR__.'/../public'.$path))return false;
$_GET['url']=ltrim($path,'/');
require __DIR__.'/../public/index.php';
