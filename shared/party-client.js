export class PartyClient {
  constructor(game,onState,onConnection){this.game=game;this.onState=onState;this.onConnection=onConnection;this.session=null;this.room=null;this.timer=null;this.busy=false;this.generation=0;this.actions=Promise.resolve();this.endpoint=new URL('../api/room.php',import.meta.url);this.key='party-room.session';this.legacyKey=`party-room.${game}.session`;}
  async request(action,payload={}){
    const generation=this.generation,controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),12000);
    try{
      const response=await fetch(this.endpoint,{method:'POST',headers:{'Content-Type':'application/json'},cache:'no-store',body:JSON.stringify({...this.session,game:this.game,...payload,action}),signal:controller.signal});
      let result;try{result=await response.json();}catch{throw new Error('The party service is unavailable. Check that the full website is uploaded to PHP hosting.');}
      if(generation!==this.generation)return null;
      if(!response.ok){const error=new Error(result.error||'The room could not be updated.');error.status=response.status;throw error;}
      if(result.session){this.session=result.session;try{sessionStorage.setItem(this.key,JSON.stringify(this.session));}catch{}}
      if(action!=='leave'&&result.room&&(!this.room||result.room.code!==this.room.code||result.room.version>=this.room.version)){this.room=result.room;if(this.session&&result.room.game){this.session.game=result.room.game;try{sessionStorage.setItem(this.key,JSON.stringify(this.session));}catch{}}this.onState(this.room);}
      this.onConnection('Live');return result;
    }catch(error){if(error.name==='AbortError')throw new Error('The connection timed out. Try again.');throw error;}finally{clearTimeout(timeout);}
  }
  async enter(action,payload){this.clear();await this.request(action,payload);this.poll();}
  async restore(code){try{const saved=JSON.parse(sessionStorage.getItem(this.key)||sessionStorage.getItem(this.legacyKey)||'null');if(saved&&(!code||code===saved.code)){this.session=saved;await this.request('state');this.poll();return true;}}catch{this.clear();}return false;}
  act(action,payload={}){const generation=this.generation,round=this.room?.round,gameEpoch=this.room?.gameEpoch;const pending=this.actions.then(async()=>{if(generation!==this.generation)return null;this.busy=true;try{return await this.request(action,{round,gameEpoch,...payload});}finally{this.busy=false;}});this.actions=pending.catch(()=>{});return pending;}
  poll(){clearTimeout(this.timer);this.timer=setTimeout(async()=>{if(!this.session)return;const generation=this.generation;try{await this.request('state');if(generation!==this.generation)return;}catch(error){if(generation!==this.generation)return;this.onConnection(error.status===401||error.status===404?error.message:'Reconnecting…');if(error.status===401||error.status===404){this.clear();this.onState(null);return;}}this.poll();},1100);}
  clear(){this.generation++;clearTimeout(this.timer);this.session=null;this.room=null;try{sessionStorage.removeItem(this.key);sessionStorage.removeItem(this.legacyKey);}catch{}}
  async leave(){const credentials={...this.session};this.clear();this.onState(null);await this.request('leave',credentials);}
}
