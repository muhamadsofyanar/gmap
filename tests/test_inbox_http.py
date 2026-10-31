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
        seedenv=dict(os.environ,APP_ENV='development',APP_URL='http://localhost',XSENDER_ENCRYPTION_KEY='AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=')
        subprocess.run(['php',str(ROOT/'tests/inbox_seed.php'),cls.db],check=True,env=seedenv)
        with socket.socket() as s:
            s.bind(('127.0.0.1',0)); cls.port=s.getsockname()[1]
        cls.base=f'http://127.0.0.1:{cls.port}'
        env=dict(seedenv,APP_ENV='development',APP_URL=cls.base,INBOX_TEST_DB=cls.db,STARSENDER_WEBHOOK_TOKEN='a'*64,STARSENDER_DEVICE_ID='123')
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
    def test_refresh_returns_scoped_fragments(self):
        c=self.client(2)
        data=json.load(c.open(self.base+'/inbox?lead=10&refresh=1'))
        self.assertEqual(data['context_key'],'lead:10')
        self.assertIn('Hello &lt;script&gt;',data['messages'])
        self.assertNotIn('<script>alert(1)</script>',data['messages'])
        self.assertIn('revision',data)
        with self.assertRaises(HTTPError) as ctx:self.client(3).open(self.base+'/inbox?lead=10&refresh=1')
        self.assertEqual(ctx.exception.code,404)
    def test_reconcile_rejects_missing_csrf_and_member(self):
        for uid in [1,2]:
            c=self.client(uid)
            with self.assertRaises(HTTPError) as ctx:c.open(Request(self.base+'/inbox/reconcile',data=b'message_id=1',headers={'Accept':'application/json'}))
            self.assertEqual(ctx.exception.code,403)
        c=self.client(2);html=c.open(self.base+'/inbox?lead=10').read().decode()
        csrf=re.search(r'name="_csrf_token" value="([a-f0-9]+)"',html).group(1)
        with self.assertRaises(HTTPError) as ctx:c.open(Request(self.base+'/inbox/reconcile',data=f'message_id=1&_csrf_token={csrf}'.encode(),headers={'Accept':'application/json'}))
        self.assertEqual(ctx.exception.code,403)
    def test_broadcast_history_is_admin_scoped(self):
        c=self.client(1);html=c.open(self.base+'/crm/broadcastHistory?project_id=1').read().decode()
        self.assertIn('Riwayat',html)
        self.assertNotIn('Terjadi Kesalahan Sistem',html)
        with self.assertRaises(HTTPError) as ctx:self.client(2).open(self.base+'/crm/broadcastHistory?project_id=1')
        self.assertEqual(ctx.exception.code,403)
        with self.assertRaises(HTTPError) as ctx:c.open(self.base+'/crm/broadcastHistory?project_id=2')
        self.assertEqual(ctx.exception.code,404)
    def test_member_scope_and_xss(self):
        html=self.client(2).open(self.base+'/inbox?lead=10').read().decode()
        self.assertIn('Hello &lt;script&gt;',html);self.assertNotIn('<script>alert(1)</script>',html)
        self.assertIn('Alice lead',html);self.assertIn('data-conversation-thread',html)
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
    def test_observed_uuid_device_without_timestamp(self):
        c=self.client()
        event={'device_id':'11111111-2222-4333-8444-555555555555','message_id':'observed-premium-1','from':'081234567890','message':'Observed webhook format','is_me':False,'is_group':False,'chat_type':'personal','push_name':'Contact','file':''}
        req=Request(self.base+'/starsender-webhook.php?token='+'a'*64,data=json.dumps(event).encode(),headers={'Content-Type':'application/json'})
        result=json.load(c.open(req));self.assertEqual(result,{'success':True,'duplicate':False})
        self.assertIn('Observed webhook format',self.client(2).open(self.base+'/inbox').read().decode())
        with sqlite3.connect(self.db) as db:
            device=db.execute("SELECT device_id FROM whatsapp_inbox WHERE message_id='observed-premium-1'").fetchone()[0]
            self.assertTrue(device.startswith('sha256:'))
            self.assertNotIn('11111111-2222-4333-8444-555555555555',device)
    def test_gateway_lookup_failure_is_temporary(self):
        event={'device_id':'11111111-2222-4333-8444-555555555555','message_id':'lookup-down','from':'081234567890','message':'Retry after gateway storage restored','is_me':False,'is_group':False,'chat_type':'personal'}
        with sqlite3.connect(self.db) as db:db.execute('ALTER TABLE whatsapp_gateway RENAME TO unavailable_gateway')
        try:
            req=Request(self.base+'/starsender-webhook.php?token='+'a'*64,data=json.dumps(event).encode(),headers={'Content-Type':'application/json'})
            with self.assertRaises(HTTPError) as ctx:self.client().open(req)
            self.assertEqual(ctx.exception.code,503)
        finally:
            with sqlite3.connect(self.db) as db:db.execute('ALTER TABLE unavailable_gateway RENAME TO whatsapp_gateway')
    def test_unknown_uuid_is_rejected(self):
        event={'device_id':'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee','message_id':'foreign-device','from':'081234567890','message':'Must not be saved','is_me':False,'is_group':False,'chat_type':'personal'}
        req=Request(self.base+'/starsender-webhook.php?token='+'a'*64,data=json.dumps(event).encode(),headers={'Content-Type':'application/json'})
        with self.assertRaises(HTTPError) as ctx:self.client().open(req)
        self.assertEqual(ctx.exception.code,422)
        with sqlite3.connect(self.db) as db:
            self.assertEqual(db.execute("SELECT COUNT(*) FROM whatsapp_inbox WHERE message_id='foreign-device'").fetchone()[0],0)
    def test_shared_crm_shell_and_conversation(self):
        html=self.client(2).open(self.base+'/inbox').read().decode()
        self.assertIn('workspace-nav',html)
        self.assertIn('data-conversation-list',html)
        self.assertIn('data-conversation-thread',html)
        self.assertNotIn('Device sha256:',html)
        self.assertIn('/team/detail/10',html)
    def test_free_message_send_and_replay(self):
        c=self.client(1);html=c.open(self.base+'/inbox?lead=10').read().decode()
        token=re.search(r'name="_csrf_token" value="([a-f0-9]+)"',html).group(1)
        values=f'id=10&template_id=0&message=Pesan+bebas+tanpa+template&request_token={"e"*32}&_csrf_token={token}'
        req=Request(self.base+'/crm/sendWhatsApp',data=values.encode(),headers={'Accept':'application/json'})
        self.assertEqual(json.load(c.open(req))['delivery_status'],'accepted')
        self.assertEqual(json.load(c.open(req))['delivery_status'],'accepted')
        with sqlite3.connect(self.db) as db:
            self.assertEqual(db.execute("SELECT COUNT(*) FROM whatsapp_deliveries WHERE request_token=?",('e'*32,)).fetchone()[0],1)
            self.assertEqual(db.execute("SELECT message FROM whatsapp_deliveries WHERE request_token=?",('e'*32,)).fetchone()[0],'Pesan bebas tanpa template')
        changed=Request(self.base+'/crm/sendWhatsApp',data=values.replace('Pesan+bebas+tanpa+template','Changed').encode(),headers={'Accept':'application/json'})
        with self.assertRaises(HTTPError) as ctx:c.open(changed)
        self.assertEqual(ctx.exception.code,422)
    def test_selected_broadcast_http(self):
        c=self.client(1);html=c.open(self.base+'/inbox?lead=10').read().decode()
        token=re.search(r'name="_csrf_token" value="([a-f0-9]+)"',html).group(1)
        items=json.load(c.open(self.base+'/crm/broadcastRecipients?project_id=1'))['recipients']
        self.assertTrue(any(item['id']==10 for item in items))
        values=f'project_id=1&template_id=0&body=Halo&token={"f"*32}&recipient_ids_json=%5B10%5D&selection_count=1&_csrf_token={token}'
        result=json.load(c.open(Request(self.base+'/crm/prepareBroadcast',data=values.encode(),headers={'Accept':'application/json'})))
        self.assertEqual(result['broadcast']['eligible'],1)
        with sqlite3.connect(self.db) as db:
            batch=json.loads(db.execute('SELECT recipients FROM whatsapp_broadcasts WHERE token=?',('f'*32,)).fetchone()[0])
            self.assertEqual([r['id'] for r in batch['recipients']],[10])
    def test_pipeline_stage_validation(self):
        from urllib.parse import urlencode
        c=self.client(1);html=c.open(self.base+'/inbox?lead=10').read().decode()
        csrf=re.search(r'name="_csrf_token" value="([a-f0-9]+)"',html).group(1)
        items=json.load(c.open(self.base+'/crm/broadcastRecipients?project_id=1'))['recipients']
        self.assertTrue(all('stage' in item for item in items))
        values={'project_id':1,'template_id':0,'body':'Stage test','token':'6'*32,'recipient_ids_json':'[10]','selection_count':1,'_csrf_token':csrf}
        for filters in ['["invalid"]','["contacted"]','{}','null','bad']:
            values['stage_filters_json']=filters
            # Empty objects must be rejected, rather than interpreted as all stages.
            with self.assertRaises(HTTPError) as ctx:c.open(Request(self.base+'/crm/prepareBroadcast',data=urlencode(values).encode(),headers={'Accept':'application/json'}))
            self.assertEqual(ctx.exception.code,422)
        values['stage_filters_json']='["new"]'
        result=json.load(c.open(Request(self.base+'/crm/prepareBroadcast',data=urlencode(values).encode(),headers={'Accept':'application/json'})))
        self.assertEqual(result['broadcast']['eligible'],1)
    def test_project_import_upload_map_commit(self):
        from urllib.parse import urlencode, urlparse, parse_qs
        from urllib.request import HTTPRedirectHandler
        c=self.client(1);html=c.open(self.base+'/intake?project_id=1').read().decode()
        csrf=re.search(r'name="_csrf_token" value="([a-f0-9]+)"',html).group(1)
        boundary='TestImportBoundary'
        parts=[]
        for key,value in {'project_id':'1','project_name':'','_csrf_token':csrf}.items():
            parts.append('--'+boundary+'\r\nContent-Disposition: form-data; name="'+key+'"\r\n\r\n'+value+'\r\n')
        parts.append('--'+boundary+'\r\nContent-Disposition: form-data; name="file"; filename="test.csv"\r\nContent-Type: text/csv\r\n\r\nNama bisnis,Telepon / WhatsApp\nUpload Contact,081234567899\nUpload Duplicate,081234567899\nInvalid Contact,nope\n\r\n--'+boundary+'--\r\n')
        response=c.open(Request(self.base+'/intake/upload',data=''.join(parts).encode(),headers={'Content-Type':'multipart/form-data; boundary='+boundary}))
        self.assertIn('/intake/mapping?',response.url)
        token=parse_qs(urlparse(response.url).query)['token'][0]
        data=urlencode({'token':token,'mapping[shop_name]':'0','mapping[phone]':'1','_csrf_token':csrf})
        review=c.open(Request(self.base+'/intake/preview',data=data.encode())).read().decode()
        self.assertIn('1 baris siap diimpor',review)
        data=urlencode({'token':token,'selected_json':'["0","1","2"]','_csrf_token':csrf})
        response=c.open(Request(self.base+'/intake/commit',data=data.encode()))
        self.assertIn('/projects/viewProject/1',response.url)
        response.close()
        with sqlite3.connect(self.db) as db:
            saved=db.execute("SELECT phone,in_crm,project_id FROM project_leads WHERE shop_name='Upload Contact'").fetchall()
            self.assertEqual(saved,[('6281234567899',0,1)])
            self.assertEqual(db.execute("SELECT COUNT(*) FROM project_leads WHERE shop_name IN ('Upload Duplicate','Invalid Contact')").fetchone()[0],0)
    def test_project_import_entrypoints(self):
        c=self.client(1)
        html=c.open(self.base+'/intake?project_id=1').read().decode()
        self.assertIn('id="intake-import"',html)
        self.assertIn('enctype="multipart/form-data"',html)
        self.assertIn('value="1" selected',html)
        example=c.open(self.base+'/intake/example').read().decode('utf-8-sig')
        self.assertIn('Nama bisnis',example)
        with self.assertRaises(HTTPError) as ctx:self.client(2).open(self.base+'/intake/example')
        self.assertEqual(ctx.exception.code,403)
    def test_import_review_report_escapes_formulas(self):
        c=self.client(1);batch_token='5'*32
        payload={'headers':['Nama bisnis','Telepon / WhatsApp'], 'rows':[['=SUM(1,2)','081234567892'],['Invalid','bad']]}
        with sqlite3.connect(self.db) as db:
            db.execute("INSERT INTO crm_intake_batches(token,project_id,payload,mapping,created_at) VALUES(?,?,?,?,datetime('now','+7 hours'))",(batch_token,1,json.dumps(payload),json.dumps({'shop_name':0,'phone':1})))
        result=c.open(self.base+'/intake/report?token='+batch_token)
        self.assertIn('no-store',result.headers.get('Cache-Control'))
        report=result.read().decode('utf-8-sig')
        self.assertIn("'=SUM",report);self.assertIn('Dilewati',report)
    def test_large_selection_json_not_truncated(self):
        c=self.client(1);html=c.open(self.base+'/inbox?lead=10').read().decode()
        csrf=re.search(r'name="_csrf_token" value="([a-f0-9]+)"',html).group(1)
        from urllib.parse import urlencode
        with sqlite3.connect(self.db) as db:db.execute("UPDATE project_leads SET phone='081234567891' WHERE id=11")
        ids=[10]*1500+[11]
        values=urlencode({'project_id':1,'template_id':0,'body':'Large selection','token':'8'*32,'recipient_ids_json':json.dumps(ids),'selection_count':len(ids),'_csrf_token':csrf})
        req=Request(self.base+'/crm/prepareBroadcast',data=values.encode(),headers={'Accept':'application/json'})
        result=json.load(c.open(req))
        self.assertEqual(result['broadcast']['eligible'],2)
    def test_broadcast_requires_explicit_selection(self):
        c=self.client(1);html=c.open(self.base+'/inbox?lead=10').read().decode()
        csrf=re.search(r'name="_csrf_token" value="([a-f0-9]+)"',html).group(1)
        for selection in ['', '&recipient_ids[]=13', '&recipient_ids[][]=10']:
            values=f'project_id=1&template_id=0&body=Halo&token={"9"*32}&_csrf_token={csrf}'+selection
            with self.assertRaises(HTTPError) as ctx:c.open(Request(self.base+'/crm/prepareBroadcast',data=values.encode(),headers={'Accept':'application/json'}))
            self.assertEqual(ctx.exception.code,422)
    def test_member_cannot_send(self):
        req=Request(self.base+'/crm/sendWhatsApp',data=b'id=10&message=Forbidden',headers={'Accept':'application/json'})
        with self.assertRaises(HTTPError) as ctx:self.client(2).open(req)
        self.assertEqual(ctx.exception.code,403)
        with self.assertRaises(HTTPError) as ctx:self.client(2).open(self.base+'/crm/broadcastRecipients?project_id=1')
        self.assertEqual(ctx.exception.code,403)
    def test_z_blocked_contact_cannot_receive_free_text(self):
        c=self.client(1);html=c.open(self.base+'/inbox?lead=10').read().decode()
        csrf=re.search(r'name="_csrf_token" value="([a-f0-9]+)"',html).group(1)
        with sqlite3.connect(self.db) as db:db.execute("INSERT INTO crm_contact_blocks VALUES('phone:6281234567890','stop')")
        try:
            req=Request(self.base+'/crm/sendWhatsApp',data=f'id=10&template_id=0&message=Blocked&request_token={"7"*32}&_csrf_token={csrf}'.encode(),headers={'Accept':'application/json'})
            with self.assertRaises(HTTPError) as ctx:c.open(req)
            self.assertEqual(ctx.exception.code,422)
            with sqlite3.connect(self.db) as db:self.assertEqual(db.execute('SELECT COUNT(*) FROM whatsapp_deliveries WHERE request_token=?',('7'*32,)).fetchone()[0],0)
        finally:
            with sqlite3.connect(self.db) as db:db.execute("DELETE FROM crm_contact_blocks WHERE contact_key='phone:6281234567890'")
    def test_array_query_is_rejected(self):
        for query in ['lead[]=10','q[]=bad','page[]=1']:
            with self.assertRaises(HTTPError) as ctx:self.client(1).open(self.base+'/inbox?'+query)
            self.assertEqual(ctx.exception.code,422)
    def test_creation_csrf_required(self):
        req=Request(self.base+'/inbox/createProspect',data=b'message_id=1&project_id=1&name=Blocked',headers={'Accept':'application/json'})
        with self.assertRaises(HTTPError) as ctx:self.client(1).open(req)
        self.assertEqual(ctx.exception.code,403)
    def test_admin_crm_urls(self):
        html=self.client(1).open(self.base+'/inbox?lead=10').read().decode()
        self.assertIn('data-message-composer',html)
        self.assertIn('name="message"',html)
        self.assertIn('message-composer.js?v=1.4.0',html)
        self.assertIn('/crm/detail?id=10#prospectWhatsApp',html)
        self.assertIn('/crm/detail?id=10',html)
        self.assertNotIn('/crm/detail/10',html)
    def test_create_prospect_from_inbox(self):
        event={'device_id':'123','message_id':'create-http','from':'089999999999','message':'A new contact','is_me':False,'is_group':False,'chat_type':'personal'}
        req=Request(self.base+'/starsender-webhook.php?token='+'a'*64,data=json.dumps(event).encode(),headers={'Content-Type':'application/json'})
        self.client().open(req).close()
        with sqlite3.connect(self.db) as db:anchor=db.execute("SELECT id FROM whatsapp_inbox WHERE message_id='create-http'").fetchone()[0]
        c=self.client(1);html=c.open(self.base+f'/inbox?conversation={anchor}').read().decode()
        self.assertIn('Buat prospek',html)
        token=re.search(r'name="_csrf_token" value="([a-f0-9]+)"',html).group(1)
        req=Request(self.base+'/inbox/createProspect',data=f'message_id={anchor}&project_id=1&name=HTTP+new+prospect&member_id=2&_csrf_token={token}'.encode())
        response=c.open(req);self.assertIn('lead=',response.url);self.assertIn('HTTP new prospect',response.read().decode())
        c.open(req).close()
        with sqlite3.connect(self.db) as db:self.assertEqual(db.execute("SELECT COUNT(*) FROM project_leads WHERE phone='6289999999999'").fetchone()[0],1)
    def test_member_create_denied(self):
        c=self.client(2);html=c.open(self.base+'/inbox').read().decode()
        token=re.search(r'name="_csrf_token" value="([a-f0-9]+)"',html).group(1)
        req=Request(self.base+'/inbox/createProspect',data=f'message_id=1&project_id=1&name=Forbidden&member_id=&_csrf_token={token}'.encode(),headers={'Accept':'application/json'})
        with self.assertRaises(HTTPError) as ctx:c.open(req)
        self.assertEqual(ctx.exception.code,403)
    def test_login_required(self):
        response=self.client().open(self.base+'/inbox')
        self.assertTrue(response.url.endswith('/login'))
if __name__=='__main__':unittest.main()
