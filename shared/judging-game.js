import {$,setupParty,perform,lobbyContent,roomControls,playerName} from './party-ui.js';
import {el,button,scores} from './game-tools.js';
export function startJudgingGame(game){
 let latest,client,key='',selected=[],offset=0;
 const adult=game==='humanity';if(adult){document.body.classList.add('humanity');document.querySelector('.game-brand').classList.add('humanity-brand');}
 function clock(){const node=$('card-timer');if(!node||!latest?.deadline)return;const left=Math.max(0,Math.ceil(latest.deadline-(Date.now()/1000+offset)));node.textContent=`${Math.floor(left/60)}:${String(left%60).padStart(2,'0')}`;const play=$('play-cards');if(play)play.disabled=selected.length!==latest.prompt.pick||left===0;}
 function paint(){for(const card of document.querySelectorAll('.play-card')){const order=selected.indexOf(card.dataset.card);card.classList.toggle('selected',order>=0);card.setAttribute('aria-pressed',String(order>=0));card.querySelector('small').textContent=order>=0?`Selected ${order+1}`:'Choose card';}const submit=$('play-cards');if(submit){submit.textContent=`Lock ${latest.prompt.pick===1?'card':'cards in this order'}`;submit.disabled=selected.length!==latest.prompt.pick;}clock();}
 function render(room,c){latest=room;client=c;offset=room.serverTime-Date.now()/1000;roomControls(room,c);const nextKey=[room.code,room.phase,room.round,room.judge,room.host,JSON.stringify(room.yourPlay),room.submitted.join(','),room.hand.map(v=>v.id).join(','),room.players.map(v=>`${v.id}:${v.online}`).join(',')].join(':');if(key===nextKey){clock();if(room.phase==='lobby'&&$('start-game'))$('start-game').disabled=room.players.filter(p=>p.online).length<4;return;}
 const roundChanged=!key.startsWith(`${room.code}:${room.phase}:${room.round}:`);if(roundChanged)selected=[];key=nextKey;const stage=$('game-stage');stage.replaceChildren();const host=room.you===room.host,judge=room.you===room.judge,participant=room.participants.includes(room.you);
 if(room.phase==='lobby'){lobbyContent(room,game);$('start-game')?.addEventListener('click',()=>perform(c,'start',adult?{target:Number($('target-score').value)}:{}));return;}
 if(room.phase==='finished'){stage.append(el('span','Game complete','eyebrow'),el('h2','That deserves an encore.'),scores(room));if(host)stage.append(button('Deal a new game',()=>perform(c,'again',adult?{target:room.targetScore}:{})));return;}
 const status=el('div','','card-status');status.append(el('span',`${playerName(room,room.judge)} is ${adult?'the Card Czar':'the judge'}`));if(['playing','judging'].includes(room.phase)){const timer=el('span','','card-timer');timer.id='card-timer';timer.setAttribute('aria-label','Time remaining');status.append(timer);}stage.append(status);
 const prompt=el('div','','card-prompt');prompt.append(el('span',adult?`Black prompt · Pick ${room.prompt.pick}`:'Green adjective','card-type'),el('h2',room.prompt.text));stage.append(prompt);
 if(room.phase==='playing'){
  stage.append(el('p',`${room.submitted.length} / ${room.participants.length-1} plays locked. ${room.targetScore?`First to ${room.targetScore} points.`:'Free play.'}`,'card-note'));
  if(!participant)stage.append(el('p','You’ll receive a hand next round. Watch the comparisons.','stage-copy'));
  else if(judge)stage.append(el('h2','Your only job: choose a favorite.'),el('p','Wait for the anonymous plays. Keep the table talking.','stage-copy'));
  else if(room.yourPlay){stage.append(el('p','Your play is locked.','card-note'),el('div',room.yourPlay.map((v,i)=>room.yourPlay.length>1?`${i+1}. ${v}`:v).join(' / '),'locked-play'));}
  else{
   const heading=el('div','','hand-heading');heading.append(el('h3','Your private hand'),el('span',room.prompt.pick===2?'Choose two cards in reading order.':'Choose the judge’s favorite.','card-note'));stage.append(heading);const grid=el('div','','card-grid');
   for(const card of room.hand){const node=button('',()=>{const index=selected.indexOf(card.id);if(index>=0)selected.splice(index,1);else if(selected.length<room.prompt.pick)selected.push(card.id);else selected=[card.id];paint();},'play-card');node.dataset.card=card.id;node.append(el('span',card.text),el('small','Choose card'));grid.append(node);}stage.append(grid);const actions=el('div','','card-actions'),submit=button('Lock card',()=>perform(c,'play',{cards:[...selected]}));submit.id='play-cards';actions.append(submit,el('p','Submissions stay anonymous until the judge decides.'));stage.append(actions);paint();
  }
  if(host){const missing=room.participants.filter(id=>id!==room.judge&&!room.submitted.includes(id));if(missing.length&&missing.every(id=>!room.players.find(p=>p.id===id)?.online))stage.append(button('Close submissions for absent players',()=>perform(c,'finish_plays'),'text-button'));}
 }
 if(['judging','reveal'].includes(room.phase)){
  if(room.phase==='judging')stage.append(el('h2',judge?'Choose the best play.':'The judge is deciding.'));
  else stage.append(el('h2',room.result.skipped?'Round skipped':`${playerName(room,room.result.winner)} takes the point.`));
  if(room.result?.skipped)stage.append(el('p',room.result.reason,'stage-copy'));
  const choices=el('div','','judge-options');for(const entry of room.choices||[]){const card=el('div','','judged-play');if(room.result?.choice===entry.id)card.classList.add('winner');entry.texts.forEach((text,index)=>card.append(el('p',entry.texts.length>1?`${index+1}. ${text}`:text)));if(room.phase==='judging'&&judge)card.append(button('Choose this play',()=>perform(c,'choose',{choice:entry.id})));if(room.phase==='reveal'&&entry.player)card.append(el('small',playerName(room,entry.player)));choices.append(card);}stage.append(choices);
  if(room.phase==='judging'&&host&&!judge&&!room.players.find(p=>p.id===room.judge)?.online)stage.append(button('Take over for absent judge',()=>perform(c,'take_judge'),'text-button'));
  if(room.phase==='reveal'){stage.append(scores(room));if(host){const actions=el('div','','card-actions');actions.append(button(room.result.gameOver?'See final scores':'Next judge',()=>perform(c,'next')));if(adult&&!room.result.gameOver)actions.append(button('End game here',()=>perform(c,'finish'),'text-button'));stage.append(actions);}else stage.append(el('p','Waiting for the host to continue.','stage-copy'));}
 }clock();
 }
 setupParty(game,render);setInterval(clock,250);
}
