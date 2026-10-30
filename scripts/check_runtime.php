<?php
if(PHP_SAPI!=='cli')exit(1);
$required=['pdo_mysql','mbstring','curl','openssl','fileinfo','zip','gd','intl'];
foreach($required as $extension) if(!extension_loaded($extension)) {fwrite(STDERR,'Missing PHP extension: '.$extension."\n");exit(1);}
if(!function_exists('proc_open')) {fwrite(STDERR,"proc_open disabled\n");exit(1);}
echo "PHP runtime OK\n";
