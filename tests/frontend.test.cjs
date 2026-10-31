// Test-only dependency: JSDOM_MODULE=/absolute/path/to/jsdom or install jsdom locally.
const {JSDOM}=require(process.env.JSDOM_MODULE || 'jsdom');
const {readFileSync}=require('node:fs');const {execFileSync}=require('node:child_process');const assert=require('node:assert/strict');
const html=execFileSync('php',[__dirname+'/render_crm.php'],{encoding:'utf8',env:{...process.env,APP_ENV:'development',APP_URL:'http://localhost'}});
const dom=new JSDOM(html,{url:'http://localhost/crm?project_id=1',runScripts:'outside-only'});
const {window:w}=dom;w.URLSearchParams=URLSearchParams;const d=w.document;
const dialog=d.getElementById('crmBroadcastDialog');dialog.showModal=()=>{dialog.open=true;};dialog.close=()=>{dialog.open=false;};
const candidates=[{id:10,name:'New A',number:'6281234567890',stage:'new',eligible:true,reason:''},{id:11,name:'Contacted B',number:'6281234567891',stage:'contacted',eligible:true,reason:''},{id:12,name:'Blocked',number:'6281234567892',stage:'new',eligible:false,reason:'Jangan dihubungi'}];
let posted;
w.fetch=async(url,options)=>{
 if(!options?.method) return {ok:true,json:async()=>({success:true,recipients:candidates})};
 posted=new URLSearchParams(options.body);
 return {ok:true,headers:{get:()=> 'application/json'},json:async()=>({success:true,broadcast:{token:'a'.repeat(32),template_id:0,template_name:'Pesan bebas',project_id:1,eligible:1,total:1,invalid:0,duplicates:0,processed:0,accepted:0,failed:0,unknown:0,skipped:0,remaining:1,example:'Halo',mark_contacted:false}})};
};
w.eval(readFileSync(__dirname+'/../public/assets/js/crm-broadcast.js','utf8'));
const settle=()=>new Promise(resolve=>setImmediate(resolve));
(async()=>{
 d.getElementById('crmBroadcastOpen').click();await settle();
 assert.equal(d.querySelectorAll('.broadcast-recipient').length,3);
 const stage=d.querySelector('#broadcastStageFilters input[value=new]');stage.checked=true;stage.dispatchEvent(new w.Event('change'));
 assert.equal(d.querySelectorAll('.broadcast-recipient').length,2);
 d.getElementById('broadcastSelectVisible').click();assert.equal(d.getElementById('broadcastSelectionCount').textContent,'1 dipilih');
 stage.checked=false;stage.dispatchEvent(new w.Event('change'));assert.equal(d.getElementById('broadcastSelectionCount').textContent,'0 dipilih');
 stage.checked=true;stage.dispatchEvent(new w.Event('change'));d.getElementById('broadcastSelectVisible').click();
 d.getElementById('crmBroadcastBody').value='Halo';d.getElementById('crmBroadcastPrepare').click();await settle();
 assert.equal(posted.get('stage_filters_json'),'["new"]');assert.equal(posted.get('recipient_ids_json'),'[10]');assert(stage.disabled);
 // Ensure 1000 import selections use one JSON field instead of exceeding PHP max_input_vars.
 const form=d.createElement('form');form.setAttribute('data-process-form','');form.setAttribute('data-import-selection','');
 form.innerHTML=Array.from({length:1000},(_,i)=>`<input type="checkbox" name="selected[]" value="${i}" checked>`).join('')+'<button type="submit">Impor</button>';
 d.body.append(form);w.matchMedia=()=>({matches:false,addEventListener:()=>{}});
 w.eval(readFileSync(__dirname+'/../public/assets/js/appearance.js','utf8'));
 form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
 assert.equal(JSON.parse(form.querySelector('[name=selected_json]').value).length,1000);
 assert.equal(form.querySelectorAll('[name="selected[]"]').length,0);
 assert.equal(form.getAttribute('aria-busy'),'true');
 w.dispatchEvent(new w.Event('pageshow'));
 assert.equal(form.querySelectorAll('[name="selected[]"]').length,1000);assert.equal(form.querySelector('button').disabled,false);
 console.log('Frontend DOM: 12 assertions passed (not browser rendering)');dom.window.close();
})().catch(error=>{console.error(error);dom.window.close();process.exitCode=1;});
