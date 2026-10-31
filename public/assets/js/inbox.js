(() => {
    const workspace=document.querySelector('[data-inbox-workspace]');
    document.querySelectorAll('[data-message-scroll]').forEach(panel=>{panel.scrollTop=panel.scrollHeight;});
    if(!workspace)return;
    const toggle=workspace.querySelector('[data-context-toggle]');
    const back=workspace.querySelector('[data-inbox-back]');
    const status=workspace.querySelector('[data-refresh-status]');
    const selection=new URL(window.location.href).searchParams;
    if(selection.has('lead') || selection.has('conversation'))workspace.classList.add('wa-show-thread');
    function contextOpen(open){workspace.classList.toggle('wa-context-open',open);toggle.setAttribute('aria-expanded',String(open));}
    toggle?.addEventListener('click',()=>contextOpen(!workspace.classList.contains('wa-context-open')));
    back?.addEventListener('click',()=>{workspace.classList.remove('wa-show-thread');contextOpen(false);workspace.querySelector('input[name=q]')?.focus();});
    document.addEventListener('keydown',event=>{if(event.key==='Escape')contextOpen(false);});
    const showNew=workspace.querySelector('[data-show-new-messages]');
    let force=false,waitingNew=false;
    let timer=null,pending=false,stopped=false,delay=10000,revision=workspace.dataset.messageRevision||null;
    showNew?.addEventListener('click',()=>{
        const target=new URL(workspace.dataset.refreshUrl,window.location.href);
        if(Number(target.searchParams.get('history_page')||1)>1){target.searchParams.delete('refresh');target.searchParams.set('history_page','1');window.location.assign(target.href);return;}
        force=true;clearTimeout(timer);refresh();
    });
    function schedule(){clearTimeout(timer);if(!stopped)timer=setTimeout(refresh,delay);}
    async function refresh(){
        if(stopped || pending)return;
        if(document.hidden){schedule();return;}
        const editor=workspace.querySelector('[data-message-composer] textarea');
        // Never disturb in-flight sends, text selection, or a submitted review form.
        if(editor?.readOnly || (!force && window.getSelection()?.toString()) || workspace.dataset.reviewing){schedule();return;}
        pending=true;const controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),15000);
        try{
            const response=await fetch(workspace.dataset.refreshUrl,{headers:{Accept:'application/json'},cache:'no-store',signal:controller.signal});
            if(response.status===401 || response.status===403){stopped=true;throw new Error('Akses berubah. Muat ulang halaman untuk masuk kembali.');}
            if(!response.ok)throw new Error('Pembaruan tertunda. Percakapan yang tersimpan tetap terlihat.');
            if(!(response.headers.get('content-type')||'').includes('application/json')){stopped=true;throw new Error('Sesi berakhir. Muat ulang halaman untuk masuk kembali.');}
            const data=await response.json();if(!data.success)throw new Error('Pembaruan belum tersedia.');
            if(data.context_key!==workspace.dataset.contextKey){stopped=true;throw new Error('Kaitan percakapan berubah. Muat ulang untuk melihat konteks terbaru; salin draf Anda terlebih dahulu.');}
            const contacts=workspace.querySelector('[data-contact-fragment]');
            if(contacts && contacts.innerHTML!==data.contacts){const list=contacts.querySelector('.wa-contact-list');const top=list?.scrollTop||0;contacts.innerHTML=data.contacts;const next=contacts.querySelector('.wa-contact-list');if(next)next.scrollTop=top;}
            const panel=workspace.querySelector('[data-message-scroll]');
            if(panel && data.messages && revision!==data.revision){
                const top=panel.scrollTop;const bottom=panel.scrollHeight-panel.clientHeight-top<70;
                if(Number(data.history_page)===1 && (bottom || force)) {
                    const parsed=new DOMParser().parseFromString(data.messages,'text/html').querySelector('[data-message-scroll]');
                    if(parsed){panel.replaceChildren(...parsed.childNodes);panel.scrollTop=panel.scrollHeight;revision=data.revision;waitingNew=false;if(showNew)showNew.hidden=true;force=false;}
                } else {waitingNew=true;if(showNew)showNew.hidden=false;}
            }
            const older=workspace.querySelector('[data-older-messages]');if(older)older.hidden=!data.has_older;
            delay=10000;status.textContent=waitingNew?'Riwayat tetap di posisi baca. Klik Tampilkan pesan terbaru untuk memperbarui.':'Diperbarui '+new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'})+' · otomatis 10 detik';status.dataset.state='success';
        }catch(error){delay=Math.min(delay*2,60000);status.textContent=error.name==='AbortError'?'Koneksi lambat. Pembaruan akan dicoba kembali.':error.message;status.dataset.state='error';}
        finally{clearTimeout(timeout);pending=false;schedule();}
    }
    workspace.addEventListener('submit',event=>{if(event.target.closest('.wa-review'))workspace.dataset.reviewing='1';});
    document.addEventListener('visibilitychange',()=>{if(!document.hidden){clearTimeout(timer);refresh();}});
    window.addEventListener('beforeunload',event=>{if(workspace.querySelector('[data-message-composer] textarea')?.value.trim()){event.preventDefault();event.returnValue='';}});
    window.addEventListener('pagehide',()=>{stopped=true;clearTimeout(timer);});
    window.addEventListener('pageshow',()=>{if(stopped){stopped=false;delete workspace.dataset.reviewing;schedule();}});
    schedule();
})();
