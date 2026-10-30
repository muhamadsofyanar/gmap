<?php
require __DIR__.'/bootstrap.php';
use App\Core\Database;
use App\Services\Installer;
try {
    if(APP_ENV==='production') Installer::validateEnvironment(getenv());
    $db=Database::getInstance();
    if ((int)$db->query("SELECT GET_LOCK('gmap_team_install',30)")->fetchColumn()!==1) throw new RuntimeException('Instalasi lain sedang berjalan.');
    try {
        Installer::schema($db,ROOT_PATH.'/database.sql');
        Installer::bootstrapAdmin($db,getenv());
    } finally { $db->query("SELECT RELEASE_LOCK('gmap_team_install')"); }
    echo "Schema dan akun awal siap. Akun yang sudah ada tidak direset.\n";
} catch(Throwable $e) {fwrite(STDERR,'Instalasi gagal: '.$e->getMessage()."\n");exit(1);}
