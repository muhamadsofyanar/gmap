(() => {
    document.querySelectorAll('[data-message-composer]').forEach(form => {
        const editor=form.querySelector('[name="message"]');
        const button=form.querySelector('button[type="submit"]');
        if(!editor || !button)return;
        const notice=form.querySelector('[data-send-notice]');
        const template=form.querySelector('select[name="template_id"]');
        const originalLabel=button.textContent.trim();let busy=false,completed=false,uncertain=false;
        function newToken(){const bytes=crypto.getRandomValues(new Uint8Array(16));return Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join('');}
        editor.addEventListener('input',()=>{
            if(completed && !uncertain){form.dataset.token=newToken();completed=false;button.disabled=false;button.textContent=originalLabel;}
        });
        form.addEventListener('submit',async event=>{
            event.preventDefault();if(busy || completed || uncertain || button.disabled)return;
            if(!editor.value.trim()){editor.reportValidity();return;}
            const message=editor.value.trim();
            if(!confirm(`Kirim ke ${form.dataset.name} (${form.dataset.phone})?\n\n${message}`))return;
            busy=true;button.disabled=true;button.textContent='Mengirim...';notice.hidden=false;notice.textContent='Menunggu respons gateway...';
            editor.readOnly=true;if(template)template.disabled=true;
            try{
                const body=new URLSearchParams(new FormData(form));body.set('id',form.dataset.lead);if(template)body.set('template_id',template.value);body.set('request_token',form.dataset.token);body.set('message',message);
                const response=await fetch(form.dataset.api,{method:'POST',body,headers:{Accept:'application/json','X-CSRF-Token':form.querySelector('[name="_csrf_token"]').value}});
                const data=await response.json();
                if(!response.ok || !data.success){if(response.status===422 || response.status===403){button.disabled=false;throw Object.assign(new Error(data.message || 'Pesan tidak dapat dikirim.'),{retrySafe:true});}throw new Error(data.message || 'Pengiriman belum dapat dipastikan.');}
                notice.textContent=data.message;completed=true;
                if(data.delivery_status==='accepted'){
                    button.textContent='Terkirim ke gateway';notice.dataset.state='success';
                    const panel=form.closest('.wa-thread-panel,.crm-detail-page')?.querySelector('[data-message-scroll]');
                    if(panel){const item=document.createElement('article');item.className='wa-bubble wa-bubble-out';const label=document.createElement('div');label.className='wa-bubble-label';label.textContent='Keluar · CRM · baru saja';const text=document.createElement('div');text.className='wa-message-text';text.textContent=message;const state=document.createElement('small');state.className='wa-delivery';state.textContent=`Diterima gateway · ${form.dataset.phone}`;item.append(label,text,state);panel.append(item);panel.scrollTop=panel.scrollHeight;}
                    editor.value='';notice.textContent+=' Tulis pesan baru untuk melanjutkan.';
                }else{uncertain=true;button.textContent='Periksa hasil';notice.textContent+=' Periksa riwayat gateway sebelum mengirim ulang.';}
            }catch(error){
                notice.textContent=error.message;notice.dataset.state='error';
                if(!error.retrySafe){uncertain=true;notice.textContent+=' Periksa riwayat gateway sebelum mengirim ulang.';button.textContent='Periksa hasil';}
                else button.textContent=originalLabel;
            }finally{busy=false;editor.readOnly=false;if(template)template.disabled=false;}
        });
    });
})();
