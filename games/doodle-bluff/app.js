import {startJackboxGame,jackboxInstructions} from '../../shared/jackbox-ui.js?v=20261009-party-1';
const instructions=document.getElementById('game-instructions');const list=document.createElement('ol');for(const text of jackboxInstructions['doodle-bluff']){const item=document.createElement('li');item.textContent=text;list.append(item);}instructions.append(list);startJackboxGame('doodle-bluff');
