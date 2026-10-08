import {setupInvites} from './party-invite.js';
import {PartyClient} from './party-client.js';
import {rememberPrompts,recentPrompts} from './prompt-memory.js';
export const $=id=>document.getElementById(id);
const names={wavelength:'Wavelength',imposter:'Imposter',scattergories:'Scattergories',hivemind:'Hive Mind',apples:'Apples to Apples',humanity:'Cards Against Humanity','desktop-disaster':'Desktop Disaster','word-circuit':'Word Circuit','sudoku-race':'Sudoku Race'};
const minimums={'word-circuit':1,'sudoku-race':1,imposter:3,hivemind:3,apples:4,humanity:4};
const colors=['#c8b7fb','#edb396','#b4c7b5','#a9cadd','#ddafd0','#d7c68e'];
export function playerName(room,id){return room.players.find(p=>p.id===id)?.name||'A player';}
export function notice(message){$('notice').textContent=message;$('notice').hidden=!message;}
export function setupParty(game,render){
  let snapshot='',previousPhase='',previousRound=0;document.body.dataset.game=game;const invites=setupInvites(document,location,navigator,notice);
  const client=new PartyClient(game,room=>{
    if(!room){$('lobby').hidden=false;$('party').hidden=true;snapshot='';previousPhase='';previousRound=0;invites.close();return;}
    rememberPrompts(game,room);$('lobby').hidden=true;$('party').hidden=false;$('room-code').textContent=room.code;
    const url=invites.update(room);if(url!==location.href)history.replaceState(null,'',url);
    const serialized=JSON.stringify(room);if(serialized===snapshot)return;snapshot=serialized;
    $('player-count').textContent=`${room.players.length} / 16 players`;
    $('players').replaceChildren(...room.players.map((p,index)=>{
      const row=document.createElement('div');row.className='player-row';const avatar=document.createElement('span');avatar.className='avatar';avatar.style.background=colors[index%colors.length];avatar.textContent=p.name.charAt(0).toUpperCase();
      const label=document.createElement('span');label.className='player-name';label.textContent=p.name+(p.id===room.you?' (you)':'');const status=document.createElement('small');status.textContent=p.id===room.host?'Host':(!p.online?'Away':room.participants.length&&!room.participants.includes(p.id)?'Next round':'Here');row.append(avatar,label,status);return row;
    }));render(room,client);if(matchMedia('(max-width:760px)').matches&&room.phase!=='lobby'&&((previousPhase==='lobby')||(previousRound&&room.round!==previousRound))){$('game-stage').scrollIntoView({block:'start',behavior:matchMedia('(prefers-reduced-motion:reduce)').matches?'instant':'smooth'});}previousPhase=room.phase;previousRound=room.round;
  },status=>{$('connection').textContent=status;$('connection').classList.toggle('offline',status!=='Live');});
  const enter=async(action,form)=>{notice('');const button=form.querySelector('button');button.disabled=true;try{await client.enter(action,{name:$(action==='create'?'create-name':'join-name').value,code:$('join-code').value.toUpperCase()});}catch(error){notice(error.message);}finally{button.disabled=false;}};
  $('create-form').addEventListener('submit',event=>{event.preventDefault();enter('create',event.currentTarget);});
  $('join-form').addEventListener('submit',event=>{event.preventDefault();enter('join',event.currentTarget);});
  $('join-code').addEventListener('input',event=>{event.target.value=event.target.value.toUpperCase().replace(/[^A-Z2-9]/g,'');});
  $('copy-code').addEventListener('click',async()=>{try{await navigator.clipboard.writeText($('share-link').value);notice('Invite link copied. Send it to your friends.');}catch{$('share-link').focus();$('share-link').select();notice('Copy the selected invite link and share it.');}});
  $('leave-party').addEventListener('click',async()=>{try{await client.leave();notice('You left the party.');}catch(error){notice(error.message);}});
  $('help').addEventListener('click',()=>$('help-dialog').showModal());$('close-help').addEventListener('click',()=>$('help-dialog').close());
  $('share-link').addEventListener('click',event=>event.target.select());
  const code=(new URL(location.href).searchParams.get('room')||'').toUpperCase();if(code)$('join-code').value=code;
  client.restore(code);return client;
}
export async function perform(client,action,payload={}){notice('');try{await client.act(action,{...payload,...(['start','again'].includes(action)?{recent:recentPrompts(client.game)}:{})});}catch(error){notice(error.message);}}
export function lobbyContent(room,game){
  const stage=$('game-stage');const savedChoices=Object.fromEntries([...stage.querySelectorAll('select')].map(node=>[node.id,node.value]));stage.replaceChildren();const icon=document.createElement('span');icon.className='stage-icon';icon.textContent=({wavelength:'◓',imposter:'◈',scattergories:'Aa',hivemind:'⬡',apples:'✿',humanity:'▣','desktop-disaster':'▤','word-circuit':'Aa','sudoku-race':'▦'})[game];
  const h=document.createElement('h2');h.textContent='Get everyone in the room.';const p=document.createElement('p');p.className='stage-copy';const host=room.you===room.host;const minimum=minimums[game]||2;
  p.textContent=host?`Share the five-character code. Start when at least ${minimum} players are here.`:`Waiting for ${playerName(room,room.host)} to start. You’re in.`;
  stage.append(icon,h,p);
  if(game==='imposter'&&host){const label=document.createElement('label');label.className='category-select';label.textContent='Secret word category';const select=document.createElement('select');select.id='category';for(const category of ['Mixed','Food','Places','Objects','Animals','Activities','Entertainment']){const option=document.createElement('option');option.value=category;option.textContent=category;select.append(option);}label.append(select);stage.append(label);}
  if(host&&['hivemind','humanity'].includes(game)){const label=document.createElement('label');label.className='category-select';label.textContent=game==='hivemind'?'Hive size':'Game length';const select=document.createElement('select');select.id=game==='hivemind'?'hive-size':'target-score';for(const [value,text] of game==='hivemind'?[[6,'6 levels · Standard'],[8,'8 levels · Longer game']]:[[7,'First to 7 points'],[5,'First to 5 points'],[10,'First to 10 points'],[15,'First to 15 points'],[0,'Free play · Host ends game']]){const option=document.createElement('option');option.value=value;option.textContent=text;select.append(option);}label.append(select);stage.append(label);}
  if(host&&['word-circuit','sudoku-race'].includes(game)){for(const [id,title,choices] of [['puzzle-difficulty','Difficulty',game==='word-circuit'?[['classic','Classic · 4–5 letters'],['easy','Warm-up · 4 letters'],['expert','Expert · 5–6 letters']]:[['easy','Easy · 42 clues'],['medium','Medium · 34 clues'],['hard','Hard · 28 clues']]],['puzzle-rounds','Rounds',[[3,'3 rounds'],[1,'1 round · Practice'],[5,'5 rounds'],[10,'10 rounds']]]]){const label=document.createElement('label');label.className='category-select';label.textContent=title;const select=document.createElement('select');select.id=id;for(const [value,text] of choices){const option=document.createElement('option');option.value=value;option.textContent=text;select.append(option);}label.append(select);stage.append(label);}}
  for(const [id,value] of Object.entries(savedChoices)){const select=stage.querySelector('#'+id);if(select)select.value=value;}
  if(host){const start=document.createElement('button');start.className='primary';start.textContent=`Start ${names[game]}`;start.id='start-game';start.disabled=room.players.filter(p=>p.online).length<minimum;stage.append(start);}else{const tag=document.createElement('span');tag.className='waiting-tag';tag.textContent='The host starts the game';stage.append(tag);}
}
export function roomControls(room,client){
  $('round-label').textContent=room.phase==='lobby'?'Lobby':room.maxRounds?`Round ${room.round} / ${room.maxRounds}`:`Round ${room.round}`;
  const controls=$('host-controls'),signature=`${room.you===room.host}:${room.phase}`;if(controls.dataset.signature===signature)return;controls.dataset.signature=signature;controls.replaceChildren();if(room.you!==room.host||room.phase==='lobby')return;
  if(!['finished','reveal'].includes(room.phase)){const skip=document.createElement('button');skip.textContent='Skip round';skip.className='text-button';skip.addEventListener('click',()=>perform(client,'skip'));controls.append(skip);}
  const reset=document.createElement('button');reset.textContent='Back to lobby';reset.className='text-button';reset.addEventListener('click',()=>{if(confirm('End this game and return everyone to the lobby?'))perform(client,'reset');});controls.append(reset);
}
