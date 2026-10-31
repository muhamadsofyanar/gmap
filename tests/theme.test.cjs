const {readFileSync}=require('node:fs');
const vm=require('node:vm');const assert=require('node:assert/strict');
const code=readFileSync(__dirname+'/../public/assets/js/appearance.js','utf8');
function boot(saved, broken=false){
 const root={dataset:{}};const listeners={};const picker={value:'',addEventListener:(k,f)=>listeners[k]=f};
 const media={matches:true,addEventListener:(k,f)=>listeners.media=f};let storage=saved;
 const document={documentElement:root,querySelector:s=>s==='[data-theme-picker]'?picker:null,querySelectorAll:()=>[],addEventListener:()=>{}};
 const localStorage={getItem:()=>{if(broken)throw Error();return storage;},setItem:(k,v)=>{if(broken)throw Error();storage=v;}};
 vm.runInNewContext(code,{document,localStorage,window:{matchMedia:()=>media,addEventListener:(k,f)=>listeners[k]=f},console});
 return {root,picker,listeners,media,get storage(){return storage;}};
}
let t=boot(null);assert.equal(t.root.dataset.theme,'dark');assert.equal(t.picker.value,'system');
t.media.matches=false;t.listeners.media();assert.equal(t.root.dataset.theme,'light');
t=boot('light');assert.equal(t.root.dataset.theme,'light');t.picker.value='dark';t.listeners.change();assert.equal(t.storage,'dark');
t.media.matches=false;t.listeners.media();assert.equal(t.root.dataset.theme,'dark');
t=boot(null,true);assert.equal(t.root.dataset.theme,'dark');t.picker.value='light';t.listeners.change();assert.equal(t.root.dataset.theme,'light');
t=boot('invalid');assert.equal(t.picker.value,'system');
console.log('Theme: 10 assertions passed');
