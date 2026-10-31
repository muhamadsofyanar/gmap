<?php
require __DIR__.'/bootstrap.php';
use App\Services\ProspectFile;
$file=tempnam(sys_get_temp_dir(),'import-');
try {
 file_put_contents($file,"\xEF\xBB\xBFNama bisnis;Telepon / WhatsApp;Alamat\nToko A;081234567890;Jl. A\nToko B;6281234567891;Jl. B\n");
 $data=ProspectFile::read($file,'kontak.csv');
 check($data['headers'][0]==='Nama bisnis','BOM and semicolon delimiter');
 check($data['rows'][0][1]==='081234567890','leading zero preserved');
 check(count($data['rows'])===2,'two source rows read');
 expectDenied(fn()=>ProspectFile::read($file,'kontak.xls'),'legacy XLS rejected');
 file_put_contents($file,"Nama bisnis\n".str_repeat("Toko\n",1001));
 expectDenied(fn()=>ProspectFile::read($file,'kontak.csv'),'1001 rows rejected');
 file_put_contents($file,"Nama bisnis\n".str_repeat("Toko\n",1000));
 check(count(ProspectFile::read($file,'kontak.csv')['rows'])===1000,'1000 rows accepted');
 file_put_contents($file,'');
 expectDenied(fn()=>ProspectFile::read($file,'kontak.csv'),'empty file rejected');
} finally { unlink($file); }
echo "Import file: $checks checks passed\n";
