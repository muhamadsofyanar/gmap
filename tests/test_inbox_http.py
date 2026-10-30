import http.cookiejar, json, os, re, socket, sqlite3, subprocess, tempfile, time, unittest
from pathlib import Path
from urllib.request import Request, build_opener, HTTPCookieProcessor
from urllib.error import HTTPError
ROOT = Path(__file__).resolve().parents[1]
class InboxHTTP(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory()
        cls.db = str(Path(cls.temp.name)/'inbox.sqlite')
        subprocess.run(['php',str(ROOT/'tests/inbox_seed.php'),cls.db],check=True)
        with socket.socket() as s:
            s.bind(('127.0.0.1',0)); cls.port=s.getsockname()[1]
        cls.base=f'http://127.0.0.1:{cls.port}'
        env=dict(os.environ,APP_ENV='development',APP_URL=cls.base,INBOX_TEST_DB=cls.db,STARSENDER_WEBHOOK_TOKEN='a'*64,STARSENDER_DEVICE_ID='123')
        cls.log=open(Path(cls.temp.name)/'http.log','w')
        cls.process=subprocess.Popen(['php','-S',f'127.0.0.1:{cls.port}','-t',str(ROOT/'public'),str(ROOT/'tests/inbox_router.php')],env=env,stdout=cls.log,stderr=cls.log)
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1',cls.port),timeout=.1): break
            except OSError: time.sleep(.03)
    @classmethod
    def tearDownClass(cls):
        cls.process.terminate();cls.process.wait();cls.log.close();cls.temp.cleanup()
    def client(self,id=None):
        c=build_opener(HTTPCookieProcessor(http.cookiejar.CookieJar()))
        if id: c.open(self.base+f'/test-login?id={id}').close()
        return c
    def test_member_scope_and_xss(self):
        html=self.client(2).open(self.base+'/inbox').read().decode()
        self.assertIn('Hello &lt;script&gt;',html);self.assertNotIn('<script>alert(1)</script>',html)
        self.assertIn('Alice lead',html);self.assertIn('Kotak masuk WhatsApp',html)
        other=self.client(3).open(self.base+'/inbox').read().decode()
        self.assertNotIn('Hello &lt;script&gt;',other)
    def test_member_cannot_review_other_prospect(self):
        c=self.client(3);html=c.open(self.base+'/inbox').read().decode()
        token=re.search(r'name="_csrf_token" value="([a-f0-9]+)"',html).group(1)
        req=Request(self.base+'/inbox/review',data=f'message_id=1&_csrf_token={token}'.encode(),headers={'Accept':'application/json'})
        with self.assertRaises(HTTPError) as ctx:c.open(req)
        self.assertEqual(ctx.exception.code,403)
    def test_csrf_required(self):
        req=Request(self.base+'/inbox/review',data=b'message_id=1',headers={'Accept':'application/json'})
        with self.assertRaises(HTTPError) as ctx:self.client(2).open(req)
        self.assertEqual(ctx.exception.code,403)
    def test_review_get_denied(self):
        with self.assertRaises(HTTPError) as ctx:self.client(2).open(self.base+'/inbox/review')
        self.assertEqual(ctx.exception.code,405)
    def test_callback_persistence_and_replay(self):
        c=self.client()
        event={'device_id':'123','message_id':'callback-1','from':'081234567890','message':'Callback test','is_me':False,'is_group':False,'chat_type':'personal','timestamp':int(time.time())}
        req=Request(self.base+'/starsender-webhook.php?token='+'a'*64,data=json.dumps(event).encode(),headers={'Content-Type':'application/json'})
        first=json.load(c.open(req));again=json.load(c.open(req))
        self.assertEqual(first,{'success':True,'duplicate':False});self.assertEqual(again,{'success':True,'duplicate':True})
        html=self.client(2).open(self.base+'/inbox').read().decode();self.assertIn('Callback test',html)
    def test_login_required(self):
        response=self.client().open(self.base+'/inbox')
        self.assertTrue(response.url.endswith('/login'))
if __name__=='__main__':unittest.main()
