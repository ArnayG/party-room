import {setupParty,notice} from './shared/party-ui.js?v=20261009-party-1';
import {gameUrl,followGame} from './shared/party-navigation.js?v=20261009-party-1';
import {setupComfort} from './shared/ui-comfort.js?v=20261009-party-1';
import {games} from './games.js?v=20261009-party-1';
const $=id=>document.getElementById(id);
setupComfort(document);
let style='all',partyRoom=null,partyClient=null,pendingSelection='party',knownEpoch=null;const jackboxIds=['quiz-after-dark','punchline','fact-or-fiction','doodle-bluff','last-laugh-trivia','thread-battle'];
const styleIds={jackbox:jackboxIds,words:['barcraft','scattergories','word-circuit'],logic:['imposter','sudoku-race','rulebreakers','alien-dictionary','question-quest'],party:['wavelength','hivemind','apples','humanity','desktop-disaster']};
const minimums={imposter:3,hivemind:3,apples:4,humanity:4,barcraft:1,'word-circuit':1,'sudoku-race':1};
const readyGames=games.filter(game=>game.available);
function tag(text,className=''){const el=document.createElement('span');el.className=className;el.textContent=text;return el;}
function gameCard(game){
  const card=document.createElement('article');card.className='game-card';card.style.setProperty('--game-color',game.color||'#c8b7fb');
  const cover=document.createElement('a');cover.className='game-cover';cover.href=game.path;cover.setAttribute('aria-label',`Play ${game.title}`);
  const image=document.createElement('img');image.src=game.artwork;image.alt='';image.width=900;image.height=650;image.loading='lazy';image.decoding='async';cover.append(image);
  const overlay=document.createElement('div');overlay.className='cover-overlay';overlay.append(tag('Play now'),tag('↗'));cover.append(overlay);card.append(cover);cover.dataset.game=game.id;
  const details=document.createElement('div');details.className='game-info';details.append(tag(game.category,'game-category'));
  const title=document.createElement('h3');title.textContent=game.title;details.append(title);
  const subtitle=document.createElement('p');subtitle.className='game-subtitle';subtitle.textContent=game.subtitle;details.append(subtitle);
  const description=document.createElement('p');description.className='game-description';description.textContent=game.description;details.append(description);
  const features=document.createElement('div');features.className='game-features';for(const feature of game.features||[])features.append(tag(feature));details.append(features);
  const metadata=document.createElement('div');metadata.className='game-meta';metadata.append(tag(game.players),tag(game.duration));details.append(metadata);
  const play=document.createElement('a');play.className='game-play';play.href=game.path;play.dataset.game=game.id;play.append(tag(`Play ${game.title}`),tag('↗'));details.append(play);card.append(details);return card;
}
function renderGames(query=$('search').value){
  const needle=query.trim().toLocaleLowerCase();const people=Number($('group-size').value);const matches=readyGames.filter(game=>(style==='all'||styleIds[style].includes(game.id))&&(!people||people>=(minimums[game.id]||2))&&[game.title,game.category,game.description,...game.features||[]].join(' ').toLocaleLowerCase().includes(needle));
  const collection=$('game-collection');collection.replaceChildren(...matches.map(gameCard));collection.classList.toggle('multiple-games',readyGames.length>1);
  $('game-count').textContent=`${matches.length} of ${readyGames.length} games${people?` for ${people} ${people===1?'player':'players'}`:' ready to play'}`;
  $('collection-empty').hidden=matches.length>0;
}
$('game-count').textContent=`${readyGames.length} ${readyGames.length===1?'game':'games'} ready to play`;
$('search-field').hidden=readyGames.length<4;
$('search').addEventListener('input',event=>renderGames(event.target.value));
$('clear-search').addEventListener('click',()=>{$('search').value='';$('group-size').value='0';style='all';paintFilters();renderGames();$('search').focus();});
$('how-to').addEventListener('click',()=>$('how-dialog').showModal());
$('close-dialog').addEventListener('click',()=>$('how-dialog').close());
$('lets-play').addEventListener('click',()=>{$('how-dialog').close();$('games').scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});});
renderGames();

function paintFilters(){for(const button of document.querySelectorAll('[data-style]'))button.setAttribute('aria-pressed',String(button.dataset.style===style));}
for(const button of document.querySelectorAll('[data-style]'))button.addEventListener('click',()=>{style=button.dataset.style;paintFilters();renderGames();});
$('group-size').addEventListener('change',()=>renderGames());
$('game-count').setAttribute('role','status');

function renderParty(room,client){
 partyRoom=room;partyClient=client;
 if(!room){knownEpoch=null;$('party-entry').hidden=false;return;}
 $('party-entry').hidden=true;
 if(knownEpoch!==null&&knownEpoch!==room.gameEpoch&&room.game!=='party'){followGame(room.game,room.code);return;}
 knownEpoch=room.gameEpoch;
 const stage=$('game-stage');stage.replaceChildren();const title=document.createElement('h2');title.textContent='Your people. One party.';
 const copy=document.createElement('p');copy.textContent=room.you===room.host?'Choose a game below. Everyone keeps this code and follows your selection.':'You’re in. Browse the games while the host chooses what’s next.';stage.append(title,copy);
 if(room.game!=='party'){const selected=games.find(g=>g.id===room.game);const a=document.createElement('a');a.className='primary';a.href=gameUrl(room.game,room.code).href;a.textContent=`Continue ${selected?.title||room.game}`;stage.append(a);}
 const browse=document.createElement('a');browse.href='#games';browse.className='party-browse';browse.textContent=room.you===room.host?'Choose the next game':'Browse games';stage.append(browse);
 $('round-label').textContent='Party lobby';
}
setupParty('party',renderParty,{follow:false,followOnEnter:true,initialGame:()=>pendingSelection});
$('game-collection').addEventListener('click',async event=>{
 const link=event.target.closest('a[data-game]');if(!link)return;event.preventDefault();const game=link.dataset.game;
 if(!partyRoom){pendingSelection=game;$('party-entry').open=true;$('party-entry').scrollIntoView({block:'start',behavior:'smooth'});document.querySelector('#create-form h2').textContent=`Start a party for ${games.find(g=>g.id===game).title}`;$('create-name').focus();return;}
 if(partyRoom.you!==partyRoom.host){notice('The host chooses the game. Your party stays together.');return;}
 if(partyRoom.game===game){followGame(game,partyRoom.code);return;}
 if(!['lobby','finished','reveal'].includes(partyRoom.phase)&&!confirm('End the current game and switch everyone to this game?'))return;
 try{await partyClient.act('select_game',{selection:game});followGame(game,partyRoom.code);}catch(error){notice(error.message);}
});
