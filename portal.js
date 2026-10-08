import {games} from './games.js';
const $=id=>document.getElementById(id);
const readyGames=games.filter(game=>game.available);
function tag(text,className=''){const el=document.createElement('span');el.className=className;el.textContent=text;return el;}
function gameCard(game){
  const card=document.createElement('article');card.className='game-card';card.style.setProperty('--game-color',game.color||'#c8b7fb');
  const cover=document.createElement('a');cover.className='game-cover';cover.href=game.path;cover.setAttribute('aria-label',`Play ${game.title}`);
  const image=document.createElement('img');image.src=game.artwork;image.alt='';image.width=900;image.height=650;cover.append(image);
  const overlay=document.createElement('div');overlay.className='cover-overlay';overlay.append(tag('Play now'),tag('↗'));cover.append(overlay);card.append(cover);
  const details=document.createElement('div');details.className='game-info';details.append(tag(game.category,'game-category'));
  const title=document.createElement('h3');title.textContent=game.title;details.append(title);
  const subtitle=document.createElement('p');subtitle.className='game-subtitle';subtitle.textContent=game.subtitle;details.append(subtitle);
  const description=document.createElement('p');description.className='game-description';description.textContent=game.description;details.append(description);
  const features=document.createElement('div');features.className='game-features';for(const feature of game.features||[])features.append(tag(feature));details.append(features);
  const metadata=document.createElement('div');metadata.className='game-meta';metadata.append(tag(game.players),tag(game.duration));details.append(metadata);
  const play=document.createElement('a');play.className='game-play';play.href=game.path;play.append(tag(`Play ${game.title}`),tag('↗'));details.append(play);card.append(details);return card;
}
function renderGames(query=''){
  const needle=query.trim().toLocaleLowerCase();const matches=readyGames.filter(game=>[game.title,game.category,game.description,...game.features||[]].join(' ').toLocaleLowerCase().includes(needle));
  const collection=$('game-collection');collection.replaceChildren(...matches.map(gameCard));collection.classList.toggle('multiple-games',readyGames.length>1);
  if(!needle){const upcoming=document.createElement('div');upcoming.className='upcoming-game';upcoming.append(tag('✳','upcoming-symbol'));const heading=document.createElement('h3');heading.textContent='The next round is on its way.';upcoming.append(heading);const copy=document.createElement('p');copy.textContent='More games, more reasons to get everyone together.';upcoming.append(copy);upcoming.append(tag('More to come','upcoming-label'));collection.append(upcoming);}
  $('collection-empty').hidden=matches.length>0;
}
$('game-count').textContent=`${readyGames.length} ${readyGames.length===1?'game':'games'} ready to play`;
$('search-field').hidden=readyGames.length<4;
$('search').addEventListener('input',event=>renderGames(event.target.value));
$('clear-search').addEventListener('click',()=>{$('search').value='';renderGames();$('search').focus();});
$('how-to').addEventListener('click',()=>$('how-dialog').showModal());
$('close-dialog').addEventListener('click',()=>$('how-dialog').close());
$('lets-play').addEventListener('click',()=>{$('how-dialog').close();$('games').scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});});
renderGames();
