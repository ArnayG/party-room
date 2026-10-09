// Shared modal behavior, including dialogs added later by the invitation panel.
export function setupComfort(doc){
 const sync=()=>doc.body.classList.toggle('dialog-open',[...doc.querySelectorAll('dialog')].some(dialog=>dialog.open));
 for(const dialog of doc.querySelectorAll('dialog')){
  if(dialog.dataset.comfort)continue;dialog.dataset.comfort='true';
  const open=dialog.showModal.bind(dialog),close=dialog.close.bind(dialog);
  dialog.showModal=(...args)=>{open(...args);dialog.scrollTop=0;sync();};
  dialog.close=(...args)=>{close(...args);sync();};
  dialog.addEventListener('close',sync);
  dialog.addEventListener('click',event=>{if(event.target!==dialog)return;const box=dialog.getBoundingClientRect();if(event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom)dialog.close();});
 }
}
