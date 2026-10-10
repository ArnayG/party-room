<?php
function party_games(): array { return ['party','barcraft','wavelength','imposter','scattergories','hivemind','apples','humanity','desktop-disaster','word-circuit','sudoku-race','rulebreakers','alien-dictionary','question-quest','quiz-after-dark','punchline','fact-or-fiction','doodle-bluff','last-laugh-trivia','thread-battle']; }
function party_initial(string $code,string $game,string $host,array $players): array {
 return ['code'=>$code,'game'=>$game,'host'=>$host,'players'=>$players,'phase'=>'lobby','round'=>0,'maxRounds'=>($game==='wavelength'?8:($game==='imposter'?5:6)),'participants'=>[],'result'=>null,'version'=>1,'gameEpoch'=>0,'updated'=>time(),'created'=>time(),'clues'=>[],'votes'=>[],'guesses'=>[],'usedWords'=>[],'usedSpectrums'=>[],'totalScore'=>0,'history'=>[]];
}
function party_select_game(array &$room,string $id,string $game): void {
 party_require($id===$room['host'],'Only the host can choose the next game.',403);
 party_require(in_array($game,party_games(),true),'Choose a valid game.',400);
 $old=$room;$players=$room['players'];foreach($players as &$p)$p['score']=0;unset($p);
 $room=party_initial($old['code'],$game,$old['host'],$players);
 $room['created']=$old['created'];$room['version']=$old['version'];$room['gameEpoch']=($old['gameEpoch']??0)+1;
 $room['gameMemories']=$old['gameMemories']??[];
 if(isset($old['jbUsed']))$room['gameMemories'][$old['game']]=$old['jbUsed'];
}
