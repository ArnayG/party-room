import {inviteUrl,inviteMatrix,qrSvg} from './invite-qr.js?v=20261008-qr-mobile-2';
export function setupInvites(document,location,navigator,report){
 const el=(tag,cls='',text='')=>{const n=document.createElement(tag);n.className=cls;n.textContent=text;return n;};
 const byId=id=>document.getElementById(id),sidebar=document.querySelector('.party-sidebar'),mobile=matchMedia('(max-width: 760px)');
 const drawer=el('details','party-drawer');drawer.open=true;const summary=el('summary','party-toggle'),summaryText=el('span','','Party & invites'),code=el('span','party-mini-code');summary.append(summaryText,code);const content=el('div','party-content');drawer.append(summary,content);
 const panel=el('div','invite-panel'),text=el('div','invite-code');text.append(sidebar.querySelector('.code-label'),byId('room-code'));const qr=el('button','invite-qr');qr.type='button';qr.setAttribute('aria-label','Enlarge the invite QR code');const image=el('span','qr-image'),caption=el('span','qr-caption','Scan to join');qr.append(image,caption);panel.append(text,qr);
 const actions=el('div','invite-actions'),share=el('button','share-button','Share invite');share.type='button';share.hidden=!navigator.share;actions.append(byId('copy-code'),share);
 const members=el('details','party-members');members.open=!mobile.matches;const memberSummary=el('summary');memberSummary.append(byId('player-count'));members.append(memberSummary,byId('players'));
 content.append(panel,actions,byId('share-link'),members,byId('leave-party'));sidebar.replaceChildren(drawer);
 const dialog=el('dialog','qr-dialog');dialog.setAttribute('aria-labelledby','qr-title');const close=el('button','dialog-close','×');close.type='button';close.setAttribute('aria-label','Close invite QR');const title=el('h2','','Scan to join the party.');title.id='qr-title';const large=el('div','qr-large'),room=el('p','qr-room-code'),hint=el('p','','Open your phone camera and point it at the code.'),link=el('a','qr-invite-link','Open invite link');dialog.append(close,title,large,room,hint,link);document.body.append(dialog);
 close.addEventListener('click',()=>dialog.close());dialog.addEventListener('click',event=>{if(event.target===dialog){const b=dialog.getBoundingClientRect();if(event.clientX<b.left||event.clientX>b.right||event.clientY<b.top||event.clientY>b.bottom)dialog.close();}});
 let previousUrl='',previousRoom='',previousLobby=null,current='';
 qr.addEventListener('click',()=>dialog.showModal());share.addEventListener('click',async()=>{try{await navigator.share({title:'Join our Party Room',text:`Party code: ${previousRoom}`,url:current});}catch(error){if(error.name!=='AbortError')report('Could not open sharing. Use Copy invite link.');}});
 mobile.addEventListener('change',()=>{drawer.open=!mobile.matches||previousLobby;members.open=!mobile.matches;});
 return {update(room){const url=inviteUrl(location.href,room.code),lobby=room.phase==='lobby';if(previousRoom!==room.code||previousLobby!==lobby){drawer.open=!mobile.matches||lobby;previousLobby=lobby;previousRoom=room.code;}code.textContent=room.code;current=url;if(previousUrl!==url){previousUrl=url;byId('share-link').value=url;link.href=url; // No player/session credentials are placed in the QR.
 try{const svg=qrSvg(inviteMatrix(url));image.innerHTML=svg;large.innerHTML=svg;qr.disabled=false;}catch{image.textContent='Use invite link';large.textContent='This address is too long for a QR code.';qr.disabled=true;}dialog.querySelector('.qr-room-code').textContent=room.code;}
 return url;},close(){dialog.close();previousRoom='';previousLobby=null;}};
}
