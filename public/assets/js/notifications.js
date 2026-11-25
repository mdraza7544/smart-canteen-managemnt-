// Poll only the authenticated account's unread canteen notifications.
(function () {
    'use strict';
    var userId=document.body.dataset.userId;
    if(!userId) return;
    async function refresh(){
        try {
            var result=await smeicAjax('api/check-notifications.php');
            var data=result.data&&result.data.data;
            if(!data) return;
            var badge=document.querySelector('[data-notif-badge]');
            if(badge){badge.textContent=data.unread;badge.classList.toggle('hidden',data.unread<1);}
            if(data.latest&&data.unread>0&&(!window.__lastNotifId||Number(data.latest.id)>window.__lastNotifId)){
                window.__lastNotifId=Number(data.latest.id); toast(data.latest.title+' — '+data.latest.message,'info',5000);
            }
        } catch(e) { /* Keep the page usable if polling is unavailable. */ }
    }
    refresh(); window.setInterval(refresh,10000);
})();
