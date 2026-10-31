<?php
require __DIR__.'/bootstrap.php';
use App\Services\ProspectFile;
function workbook(string $path,bool $formula=false): void {
 $zip=new ZipArchive();$zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);
 $zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
 $zip->addFromString('_rels/.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
 $zip->addFromString('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Kontak" sheetId="1" r:id="rId1"/></sheets></workbook>');
 $zip->addFromString('xl/_rels/workbook.xml.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
 $zip->addFromString('xl/worksheets/sheet1.xml','<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Nama bisnis</t></is></c><c r="B1" t="inlineStr"><is><t>Telepon / WhatsApp</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Toko Excel</t></is></c><c r="B2" t="inlineStr">'.($formula?'<f>1+1</f>':'').'<is><t>081234567890</t></is></c></row></sheetData></worksheet>');$zip->close();
}
$path=tempnam(sys_get_temp_dir(),'xlsx-');
try {
 workbook($path);$data=ProspectFile::read($path,'kontak.xlsx');
 check($data['source']==='xlsx','real XLSX ZIP detected');check($data['rows'][0]===['Toko Excel','081234567890'],'inline strings and leading zero preserved');
 workbook($path,true);expectDenied(fn()=>ProspectFile::read($path,'kontak.xlsx'),'XLSX formulas rejected');
 file_put_contents($path,'not a zip');expectDenied(fn()=>ProspectFile::read($path,'kontak.xlsx'),'disguised XLSX rejected');
}finally{unlink($path);}
echo "Import XLSX: $checks checks passed\n";
