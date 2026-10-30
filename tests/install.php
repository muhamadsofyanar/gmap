<?php
require __DIR__.'/bootstrap.php';
use App\Services\Installer;
check(class_exists(Installer::class),'Installer must validate production setup before boot');
expectDenied(fn()=> Installer::validateEnvironment(['APP_URL'=>'http://example.test','DB_PASS'=>'secret','XSENDER_ENCRYPTION_KEY'=>base64_encode(random_bytes(32))]),'production requires https');
expectDenied(fn()=> Installer::validateEnvironment(['APP_URL'=>'https://example.test','DB_PASS'=>'','XSENDER_ENCRYPTION_KEY'=>base64_encode(random_bytes(32))]),'database password required');
expectDenied(fn()=> Installer::validateEnvironment(['APP_URL'=>'https://example.test','DB_PASS'=>'secret','XSENDER_ENCRYPTION_KEY'=>'weak']),'encryption key validated');
Installer::validateEnvironment(['APP_URL'=>'https://crm.example.test','DB_PASS'=>'LongDBPassword','XSENDER_ENCRYPTION_KEY'=>base64_encode(random_bytes(32))]);
$db=fixture();$db->exec('DELETE FROM users');
$setup=['ADMIN_USERNAME'=>'owner','ADMIN_EMAIL'=>'owner@example.test','ADMIN_NAME'=>'Owner','ADMIN_PASSWORD'=>'LongPassword123!'];
Installer::bootstrapAdmin($db,$setup);
check((int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn()===1,'bootstrap admin created');
$hash=$db->query("SELECT password FROM users WHERE username='owner'")->fetchColumn();
Installer::bootstrapAdmin($db,array_replace($setup,['ADMIN_PASSWORD'=>'Replacement123!']));
check($db->query("SELECT password FROM users WHERE username='owner'")->fetchColumn()===$hash,'redeploy does not reset password');
check((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===1,'redeploy does not create users');
echo "PASS: $checks installation checks\n";
