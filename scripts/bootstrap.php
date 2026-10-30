<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once ROOT_PATH.'/config/database.php';
require_once APP_PATH.'/Core/Helpers.php';
spl_autoload_register(function(string $class):void {
    if(str_starts_with($class,'App\\')) {
        $file=APP_PATH.'/'.str_replace('\\','/',substr($class,4)).'.php';
        if(is_file($file)) require_once $file;
    }
});
