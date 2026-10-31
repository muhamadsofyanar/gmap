"""Runs only inside tests/run_platform_mysql.py's disposable database environment."""
import http.cookiejar, json, os, re, socket, subprocess, tempfile, time, unittest
from pathlib import Path
from urllib.request import Request, build_opener, HTTPCookieProcessor
from urllib.parse import urlencode
from urllib.error import HTTPError
ROOT=Path(__file__).resolve().parents[1]
class PlatformHTTP(unittest.TestCase):
 @classmethod
 def setUpClass(cls):
  if os.environ.get('PLATFORM_MYSQL_TEST')!='1':raise unittest.SkipTest('Use run_platform_mysql.py')
  with socket.socket() as s:s.bind(('127.0.0.1',0));cls.port=s.getsockname()[1]
  cls.base='http://127.0.0.1:'+str(cls.port);cls.tmp=tempfile.TemporaryDirectory();cls.log=open(Path(cls.tmp.name)/'http.log','w')
  env=dict(os.environ,APP_URL=cls.base,APP_ENV='development')
  cls.proc=subprocess.Popen(['php','-S','127.0.0.1:'+str(cls.port),'-t',str(ROOT/'public'),str(ROOT/'tests/platform_router.php')],env=env,stdout=cls.log,stderr=cls.log)
  for _ in range(100):
   try:
    with socket.create_connection(('127.0.0.1',cls.port),timeout=.1):break
   except OSError:time.sleep(.03)
 @classmethod
 def tearDownClass(cls):cls.proc.terminate();cls.proc.wait();cls.log.close();cls.tmp.cleanup()
 def client(self):return build_opener(HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def page(self,c,path):
  r=c.open(self.base+path);html=r.read().decode();r.close();self.assertNotIn('Terjadi Kesalahan Sistem',html);return html
 def csrf(self,html):return re.search(r'name="_csrf_token" value="([a-f0-9]+)"',html).group(1)
 def login(self,name,password):
  c=self.client();token=self.csrf(self.page(c,'/login'));r=c.open(Request(self.base+'/login',data=urlencode({'identifier':name,'password':password,'_csrf_token':token}).encode()));html=r.read().decode();r.close();self.assertNotIn('Terjadi Kesalahan Sistem',html);return c
 def test_01_root_settings_mask_and_clients(self):
  c=self.login('originaladmin','Original!Owner_2026');html=self.page(c,'/account/settings');self.assertIn('Kunci tersimpan',html);self.assertNotIn('qa-not-real',html)
  registry=self.page(c,'/account');self.assertIn('alpha@example.test',registry);self.assertIn('beta@example.test',registry)
 def test_02_owner_database_scope_and_privileges(self):
  c=self.login('alpha','Changed!ClientAlpha_2026');html=self.page(c,'/projects');self.assertIn('Alpha private',html);self.assertNotIn('Beta private',html)
  for path in ['/account/settings']:
   with self.assertRaises(HTTPError) as ctx:c.open(self.base+path)
   self.assertEqual(ctx.exception.code,403)
  csrf=self.csrf(self.page(c,'/account'))
  with self.assertRaises(HTTPError) as ctx:c.open(Request(self.base+'/account/saveSettings',data=urlencode({'registration_enabled':'1','_csrf_token':csrf}).encode()))
  self.assertEqual(ctx.exception.code,403)
 def test_03_member_cannot_invite_or_view_projects(self):
  c=self.login('member','Member!Password_2026')
  for path in ['/projects','/account','/account/settings']:
   with self.assertRaises(HTTPError) as ctx:c.open(self.base+path)
   self.assertEqual(ctx.exception.code,403)
 def test_04_public_forms_and_csrf(self):
  c=self.client()
  for path in ['/account/register','/account/forgot','/account/resend']:
   html=self.page(c,path);self.assertIn('<form',html)
  with self.assertRaises(HTTPError) as ctx:c.open(Request(self.base+'/account/register',data=b'name=Test',headers={'Accept':'application/json'}))
  self.assertEqual(ctx.exception.code,403)
  html=self.page(c,'/account/forgot');csrf=self.csrf(html)
  result=c.open(Request(self.base+'/account/forgot',data=urlencode({'identifier':'definitely-unknown@example.test','_csrf_token':csrf}).encode())).read().decode()
  self.assertIn('Jika akun memenuhi syarat',result)
 def test_05_callback_cannot_choose_unsigned_workspace(self):
  c=self.client()
  for token in ['x'*64,'a'*64]:
   with self.assertRaises(HTTPError) as ctx:c.open(Request(self.base+'/client-starsender-webhook.php?tenant_id=2&token='+token,data=b'{}',headers={'Content-Type':'application/json'}))
   self.assertEqual(ctx.exception.code,403)
 def test_055_valid_callback_only_enters_its_workspace(self):
  fixture=json.loads(Path(os.environ['PLATFORM_QA_CONTEXT_FILE']).read_text());token=fixture['callback_token'];c=self.client()
  event={'device_id':'123','message_id':'tenant-callback-qa','from':'081234567890','message':'Workspace callback secret QA','is_me':False,'is_group':False,'chat_type':'personal','timestamp':int(time.time())}
  req=Request(self.base+'/client-starsender-webhook.php?token='+token,data=json.dumps(event).encode(),headers={'Content-Type':'application/json'})
  first=json.load(c.open(req));again=json.load(c.open(req));self.assertEqual(first,{'success':True,'duplicate':False});self.assertEqual(again,{'success':True,'duplicate':True})
  owner=self.login('alpha','Changed!ClientAlpha_2026');self.assertIn('Workspace callback secret QA',self.page(owner,'/inbox'))
  root=self.login('originaladmin','Original!Owner_2026');self.assertNotIn('Workspace callback secret QA',self.page(root,'/inbox'))
 def test_06_reset_get_is_readonly_and_session_revoked(self):
  c=self.login('alpha','Changed!ClientAlpha_2026');token='7'*64
  html=self.page(c,'/account/reset?token='+token);self.assertIn('Buat kata sandi baru',html);self.assertIn('name="token"',html)
  html=self.page(c,'/account/reset?token='+token);self.assertIn('name="token"',html)
  csrf=self.csrf(html);response=c.open(Request(self.base+'/account/reset',data=urlencode({'token':token,'password':'Final!AlphaPassword_2026','password_confirmation':'Final!AlphaPassword_2026','_csrf_token':csrf}).encode()));response.read();response.close()
  html=self.page(c,'/projects');self.assertIn('name="identifier"',html)
  reused=self.page(self.client(),'/account/reset?token='+token);self.assertIn('telah digunakan',reused)
if __name__=='__main__':unittest.main()
