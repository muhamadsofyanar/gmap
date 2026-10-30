import json, os, subprocess, urllib.request, urllib.error, socket, time, unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
class Callback(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        with socket.socket() as s:
            s.bind(('127.0.0.1',0)); cls.port=s.getsockname()[1]
        env=dict(os.environ, APP_ENV='development',APP_URL='http://localhost',STARSENDER_WEBHOOK_TOKEN='a'*64,STARSENDER_DEVICE_ID='123')
        cls.log=open('/tmp/gmap-webhook-test.log','w')
        cls.process=subprocess.Popen(['php','-S',f'127.0.0.1:{cls.port}','-t',str(ROOT/'public')],env=env,stdout=cls.log,stderr=cls.log)
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1',cls.port),timeout=.1): break
            except OSError: time.sleep(.03)
    @classmethod
    def tearDownClass(cls): cls.process.terminate(); cls.process.wait(); cls.log.close()
    def call(self, body=b'{}',token='a'*64,method='POST'):
        request=urllib.request.Request(f'http://127.0.0.1:{self.port}/starsender-webhook.php?token={token}',data=body if method=='POST' else None,method=method,headers={'Content-Type':'application/json'})
        try:
            with urllib.request.urlopen(request) as r: return r.status,json.load(r)
        except urllib.error.HTTPError as e: return e.code,json.load(e)
    def test_disabled_receiver(self):
        env=dict(os.environ,STARSENDER_WEBHOOK_TOKEN='',STARSENDER_DEVICE_ID='123')
        code="$_SERVER['REQUEST_METHOD']='POST';register_shutdown_function(function(){fwrite(STDERR,(string)http_response_code());});require $argv[1];"
        result=subprocess.run(['php','-r',code,str(ROOT/'public/starsender-webhook.php')],env=env,capture_output=True,text=True)
        self.assertEqual(result.stderr,'503');self.assertFalse(json.loads(result.stdout)['success'])
    def test_wrong_secret(self): self.assertEqual(self.call(token='wrong')[0],403)
    def test_method(self): self.assertEqual(self.call(method='GET')[0],405)
    def test_malformed(self): self.assertEqual(self.call(b'[')[0],422)
    def test_oversize(self): self.assertEqual(self.call(b'x'*262145)[0],413)
    def test_wrong_device(self): self.assertEqual(self.call(json.dumps({'device_id':'456'}).encode())[0],422)
    def test_group_without_database(self):
        event={'device_id':'123','chat_type':'group','is_group':True}
        self.assertEqual(self.call(json.dumps(event).encode()),(200,{'success':True,'ignored':True}))
if __name__=='__main__': unittest.main()
