<?php
require_once __DIR__.'/puzzle-data.php';
function party_puzzle_game(string $game): bool {return in_array($game,['word-circuit','sudoku-race'],true);}
function party_circuit_dictionary(): array {static $words;if($words===null)$words=array_fill_keys(file(__DIR__.'/word-circuit-words.txt',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES),true);return $words;}
function party_circuit_neighbors(string $word): array {
    $dictionary=party_circuit_dictionary();$out=[];
    for($i=0;$i<strlen($word);$i++)for($c=97;$c<=122;$c++){if(ord($word[$i])===$c)continue;$next=$word;$next[$i]=chr($c);if(isset($dictionary[$next]))$out[]=$next;}
    return $out;
}
function party_circuit_route(string $start,string $end,array $avoid=[]): array {
    $parents=[$start=>null];$blocked=array_fill_keys($avoid,true);$queue=[$start];
    for($i=0;$i<count($queue);$i++){
        $word=$queue[$i];if($word===$end){$route=[];while($word!==null){$route[]=$word;$word=$parents[$word];}return array_reverse($route);}
        foreach(party_circuit_neighbors($word) as $next)if(!array_key_exists($next,$parents)&&!isset($blocked[$next])){$parents[$next]=$word;$queue[]=$next;}
    }
    return [];
}
function party_puzzle_reset(array &$room,array $input,array $recent): void {
    $circuit=$room['game']==='word-circuit';$difficulty=(string)($input['difficulty']??($circuit?'classic':'easy'));
    party_require(in_array($difficulty,$circuit?['easy','classic','expert']:['easy','medium','hard'],true),'Choose a valid difficulty.',400);
    $rounds=$input['rounds']??3;party_require(is_int($rounds)&&in_array($rounds,[1,3,5,10],true),'Choose 1, 3, 5, or 10 rounds.',400);
    $room['difficulty']=$difficulty;$room['maxRounds']=$rounds;$room['usedPuzzles']=$recent;
}
function party_sudoku_transform(array $puzzle): array {
    $digits=range(1,9);shuffle($digits);$bands=range(0,2);shuffle($bands);$stacks=range(0,2);shuffle($stacks);$rows=[];$cols=[];
    foreach($bands as $b){$inside=range(0,2);shuffle($inside);foreach($inside as $r)$rows[]=$b*3+$r;}
    foreach($stacks as $b){$inside=range(0,2);shuffle($inside);foreach($inside as $c)$cols[]=$b*3+$c;}
    $transpose=random_int(0,1);$out=$puzzle;
    foreach(['givens','solution'] as $key){$out[$key]='';for($r=0;$r<9;$r++)for($c=0;$c<9;$c++){$old=$puzzle[$key][$transpose?$cols[$c]*9+$rows[$r]:$rows[$r]*9+$cols[$c]];$out[$key].=$old==='0'?'0':(string)$digits[(int)$old-1];}}
    return $out;
}
function party_puzzle_round(array &$room): void {
    $circuit=$room['game']==='word-circuit';$deck=party_puzzle_data()[$circuit?'circuit':'sudoku'][$room['difficulty']];
    $key=fn($p)=>$circuit?'circuit:'.implode('|',[$p['start'],$p['end']]):'sudoku:'.$p['id'];
    $fresh=array_values(array_filter($deck,fn($p)=>!in_array($key($p),$room['usedPuzzles'],true)));
    if(!$fresh){$room['usedPuzzles']=array_slice($room['usedPuzzles'],-intdiv(count($deck),2));$fresh=array_values(array_filter($deck,fn($p)=>!in_array($key($p),$room['usedPuzzles'],true)));}
    $chosen=$fresh[random_int(0,count($fresh)-1)];$room['usedPuzzles'][]=$key($chosen);$room['puzzle']=$circuit?$chosen:party_sudoku_transform($chosen);
    $room['raceStarts']=microtime(true)+5;$seconds=$circuit?180:(['easy'=>600,'medium'=>900,'hard'=>1200][$room['difficulty']]);$room['deadline']=$room['raceStarts']+$seconds;
    $room['phase']='race';$room['ready']=[];$room['paths']=[];$room['boards']=[];$room['bestPaths']=[];$room['raceHints']=[];$room['hintCounts']=[];$room['finishes']=[];
    foreach($room['participants'] as $id){$room['paths'][$id]=[$chosen['start']??''];$room['boards'][$id]=$room['puzzle']['givens']??'';$room['hintCounts'][$id]=0;}
}
function party_puzzle_reveal(array &$room): void {
    if($room['phase']!=='race')return;$circuit=$room['game']==='word-circuit';$results=[];$scores=array_fill_keys($room['participants'],0);
    if($circuit){foreach($room['participants'] as $id){$path=$room['bestPaths'][$id]??null;$moves=$path?count($path)-1:null;$hints=$room['hintCounts'][$id];$points=$path?max(1,12-2*max(0,$moves-$room['puzzle']['optimal'])-2*$hints):0;$scores[$id]=$points;$results[]=['player'=>$id,'solved'=>(bool)$path,'path'=>$path??$room['paths'][$id],'moves'=>$moves,'hints'=>$hints,'points'=>$points];}usort($results,fn($a,$b)=>$b['points']<=>$a['points']);}
    else {foreach($room['participants'] as $id){$finish=$room['finishes'][$id]??null;$hints=$room['hintCounts'][$id];$results[]=['player'=>$id,'solved'=>$finish!==null,'time'=>$finish,'adjustedTime'=>$finish===null?null:$finish+30*$hints,'hints'=>$hints,'filled'=>81-substr_count($room['boards'][$id],'0'),'points'=>0];}usort($results,fn($a,$b)=>($a['adjustedTime']??PHP_INT_MAX)<=>($b['adjustedTime']??PHP_INT_MAX));$place=0;foreach($results as &$row)if($row['solved']){$row['points']=10+max(0,6-2*$place++);$scores[$row['player']]=$row['points'];}unset($row);}
    foreach($room['players'] as &$p)$p['score']+=$scores[$p['id']]??0;unset($p);
    $room['result']=['standings'=>$results,'solution'=>$room['puzzle']['solution'],'optimal'=>$room['puzzle']['optimal']??null,'scores'=>$scores,'reason'=>'The round is complete.'];$room['phase']='reveal';
}
function party_puzzle_board(array $room,string $board): void {
    party_require((bool)preg_match('/^[0-9]{81}$/D',$board),'Send all 81 cells using digits 0–9.',400);
    for($i=0;$i<81;$i++)party_require($room['puzzle']['givens'][$i]==='0'||$room['puzzle']['givens'][$i]===$board[$i],'The starting clues cannot be changed.',400);
}
function party_puzzle_action(array &$room,string $id,string $action,array $input): void {
    party_require($room['phase']==='race','This round has finished.');$now=microtime(true);
    party_require($now>=$room['raceStarts'],'The race starts after the countdown.');
    party_require($now<$room['deadline'],'Time is up.');
    if($action==='end_race'){party_require($room['host']===$id,'Only the host can end the race.',403);party_puzzle_reveal($room);return;}
    party_require(!in_array($id,$room['ready'],true),'Your round is locked.');$circuit=$room['game']==='word-circuit';
    if($action==='give_up'||($circuit&&$action==='lock')){$room['ready'][]=$id;}
    elseif($circuit){
        $path=$room['paths'][$id];$current=end($path);
        if($action==='word'){
            $word=strtolower(trim((string)($input['word']??'')));party_require((bool)preg_match('/^[a-z]+$/D',$word)&&strlen($word)===strlen($current),'Use exactly '.strlen($current).' letters, with no spaces or punctuation.',400);
            party_require(isset(party_circuit_dictionary()[$word]),'“'.$word.'” is not in this game’s dictionary. Try another word.',400);
            $changes=0;for($i=0;$i<strlen($word);$i++)if($word[$i]!==$current[$i])$changes++;
            party_require($changes===1,'Change exactly one letter in the same position. No rearranging letters.',400);
            party_require(!in_array($word,$path,true),'That word is already in your route. Undo to return to it.',400);party_require(count($path)<80,'Your route is full. Undo or restart.');
            $path[]=$word;$room['paths'][$id]=$path;$room['raceHints'][$id]=null;
            if($word===$room['puzzle']['end']&&(!isset($room['bestPaths'][$id])||count($path)<count($room['bestPaths'][$id])))$room['bestPaths'][$id]=$path;
        }elseif($action==='undo'){
            party_require(count($path)>1,'You are already at the starting word.');array_pop($path);$room['paths'][$id]=$path;$room['raceHints'][$id]=null;
        }elseif($action==='restart_path'){$room['paths'][$id]=[$room['puzzle']['start']];$room['raceHints'][$id]=null;}
        elseif($action==='hint'){
            party_require($current!==$room['puzzle']['end'],'You reached the goal. Lock your route or try a shorter one.');
            if(($room['raceHints'][$id]['from']??null)===$current)return;
            party_require($room['hintCounts'][$id]<3,'Three hints per round.');$route=party_circuit_route($current,$room['puzzle']['end'],array_slice($path,0,-1));
            party_require(count($route)>1,'This route is blocked by earlier words. Undo a step or restart.');$room['hintCounts'][$id]++;$room['raceHints'][$id]=['from'=>$current,'word'=>$route[1]];
        }else throw new RuntimeException('Unknown Word Circuit action.',400);
    }else{
        if(in_array($action,['board','finish','hint'],true)){
            $board=$input['board']??null;party_require(is_string($board),'Send your board.',400);party_puzzle_board($room,$board);$room['boards'][$id]=$board;
            if($action==='finish'){party_require($board===$room['puzzle']['solution'],'The board has empty cells or incorrect digits. Check it and try again.',400);$room['finishes'][$id]=round($now-$room['raceStarts'],3);$room['ready'][]=$id;}
            elseif($action==='hint'){
                $index=$input['index']??null;party_require(is_int($index)&&$index>=0&&$index<81,'Select a cell first.',400);party_require($room['puzzle']['givens'][$index]==='0','Choose a cell without a starting clue.',400);
                party_require($board[$index]!==$room['puzzle']['solution'][$index],'This cell already has the correct digit.');party_require($room['hintCounts'][$id]<3,'Three hints per round.');
                $room['hintCounts'][$id]++;$room['boards'][$id][$index]=$room['puzzle']['solution'][$index];
            }
        }else throw new RuntimeException('Unknown Sudoku action.',400);
    }
    if(count($room['ready'])===count($room['participants']))party_puzzle_reveal($room);
}
function party_puzzle_view(array $room,string $id): array {
    $out=['serverTime'=>microtime(true),'difficulty'=>$room['difficulty']??null];if($room['phase']==='lobby')return $out;
    $circuit=$room['game']==='word-circuit';$out['startsAt']=$room['raceStarts'];$out['deadline']=$room['deadline'];$out['ready']=$room['ready'];$out['puzzleKey']=$circuit?'circuit:'.$room['puzzle']['start'].'|'.$room['puzzle']['end']:'sudoku:'.$room['puzzle']['id'];
    $out['puzzle']=$circuit?array_intersect_key($room['puzzle'],array_flip(['start','end','optimal'])):['givens'=>$room['puzzle']['givens']];$out['progress']=[];
    foreach($room['participants'] as $p)$out['progress'][]=['player'=>$p,'done'=>in_array($p,$room['ready'],true),'solved'=>$circuit?isset($room['bestPaths'][$p]):isset($room['finishes'][$p]),'filled'=>$circuit?null:81-substr_count($room['boards'][$p],'0')];
    if(in_array($id,$room['participants'],true)){$out['hints']=$room['hintCounts'][$id];if($circuit){$out['path']=$room['paths'][$id];$out['bestPath']=$room['bestPaths'][$id]??null;$out['hint']=$room['raceHints'][$id]??null;}else{$out['board']=$room['boards'][$id];$out['finishTime']=$room['finishes'][$id]??null;}}
    return $out;
}
