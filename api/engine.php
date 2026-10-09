<?php
require_once __DIR__ . '/game-data.php';
require_once __DIR__.'/new-games.php';
require_once __DIR__.'/disaster.php';
require_once __DIR__.'/puzzle-games.php';
require_once __DIR__.'/deduction-games.php';
function party_require(bool $ok, string $message, int $status=409): void { if (!$ok) throw new RuntimeException($message,$status); }
function party_name($value): string { $text=trim((string)$value); party_require($text!=='' && strlen($text)<=60,'Choose a name of 1–24 characters.',400); if (function_exists('mb_substr')) return mb_substr($text,0,24); return substr($text,0,24); }
function party_player(string $name): array { return ['id'=>bin2hex(random_bytes(6)),'token'=>bin2hex(random_bytes(24)),'name'=>party_name($name),'seen'=>time(),'left'=>false,'score'=>0]; }
function party_active(array $room): array { return array_values(array_filter($room['players'],fn($p)=>!$p['left'] && time()-$p['seen']<45)); }
function party_ids(array $room): array { return array_column(party_active($room),'id'); }
function party_member(array $room,string $id): ?array { foreach($room['players'] as $p) if($p['id']===$id) return $p; return null; }
function party_minimum(string $game): int { return party_puzzle_game($game)?1:(in_array($game,['apples','humanity'],true)?4:(in_array($game,['imposter','hivemind'],true)?3:2)); }
function party_median(array $values): float { sort($values,SORT_NUMERIC);$n=count($values);return $n?($n%2?$values[intdiv($n,2)]:($values[$n/2-1]+$values[$n/2])/2):50; }
function party_points(float $guess,float $target): int { $distance=abs($guess-$target);return $distance<=4?4:($distance<=8?3:($distance<=12?2:0)); }
function party_new_round(array &$room): void {
    $ids=party_ids($room);party_require(count($ids)>=party_minimum($room['game']),'Wait for more players to join or reconnect.');
    $room['round']++;$room['participants']=$ids;$room['clues']=[];$room['votes']=[];$room['guesses']=[];$room['result']=null;
    if(party_deduction_game($room['game'])){party_deduction_round($room);return;}
    if(party_puzzle_game($room['game'])){party_puzzle_round($room);return;}
    if(party_extra_game($room['game'])){party_extra_round($room,$ids);return;}
    if($room['game']==='wavelength'){
        $deck=party_spectrums();$used=$room['usedSpectrums'];$eligible=array_values(array_diff(range(0,count($deck)-1),$used));if(!$eligible){$room['usedSpectrums']=[];$eligible=range(0,count($deck)-1);}
        $index=$eligible[random_int(0,count($eligible)-1)];$room['usedSpectrums'][]=$index;$room['spectrum']=$deck[$index];$room['target']=random_int(120,880)/10;
        $room['giver']=$ids[($room['round']-1)%count($ids)];$room['clue']='';$room['phase']='clue';$room['rerolls']=0;
    }elseif($room['game']==='imposter'){
        $deck=party_words();$category=$room['category']??'Mixed';$options=[];
        foreach($deck as $cat=>$words)if($category==='Mixed'||$category===$cat)foreach($words as $word)$options[]=['word'=>$word,'category'=>$cat];
        $fresh=array_values(array_filter($options,fn($w)=>!in_array($w['word'],$room['usedWords'],true)));if(!$fresh){$room['usedWords']=[];$fresh=$options;}
        $chosen=$fresh[random_int(0,count($fresh)-1)];$room['word']=$chosen['word'];$room['wordCategory']=$chosen['category'];$room['usedWords'][]=$chosen['word'];
        $possible=array_values(array_filter($ids,fn($id)=>$id!==($room['previousImposter']??'')));if(!$possible)$possible=$ids;
        $room['imposter']=$possible[random_int(0,count($possible)-1)];$room['previousImposter']=$room['imposter'];
        shuffle($ids);$room['turnOrder']=$ids;$room['turnIndex']=0;$room['phase']='clues';
    }else{
        $deck=party_scatter_categories();$eligible=array_values(array_diff($deck,$room['usedCategories']??[]));
        if(count($eligible)<12){$room['usedCategories']=array_slice($room['usedCategories']??[],-intdiv(count($deck),2));$eligible=array_values(array_diff($deck,$room['usedCategories']));}
        shuffle($eligible);$room['categories']=array_slice($eligible,0,12);$room['usedCategories']=array_merge($room['usedCategories']??[],$room['categories']);
        $letters=str_split('ABCDEFGHIJKLMNOPRSTW');$fresh=array_values(array_diff($letters,$room['usedLetters']??[]));if(!$fresh){$room['usedLetters']=array_slice($room['usedLetters'],-5);$fresh=array_values(array_diff($letters,$room['usedLetters']));}
        $room['letter']=$fresh[random_int(0,count($fresh)-1)];$room['usedLetters'][]=$room['letter'];$room['deadline']=time()+120;$room['answers']=[];$room['ready']=[];$room['rejected']=[];$room['phase']='writing';
    }
}
function party_imposter_result(array &$room,bool $imposterWins,string $reason): void {
    $room['result']['imposterWins']=$imposterWins;$room['result']['reason']=$reason;
    foreach($room['players'] as &$player)if(in_array($player['id'],$room['participants'],true))$player['score']+=($player['id']===$room['imposter']?($imposterWins?2:0):($imposterWins?0:1));unset($player);
    $room['phase']='reveal';
}
function party_resolve_vote(array &$room): void {
    $counts=array_count_values(array_values($room['votes']));$high=$counts?max($counts):0;$top=array_keys(array_filter($counts,fn($n)=>$n===$high));
    $accused=count($top)===1?$top[0]:null;$room['result']=['accused'=>$accused,'counts'=>$counts,'finalGuess'=>null];
    if($accused===$room['imposter'])$room['phase']='last_guess';else party_imposter_result($room,true,$accused?'The group accused the wrong player.':'A tied vote lets the imposter escape.');
}
function party_apply(array &$room,string $id,string $action,array $input): void {
    $host=$room['host']===$id;$phase=$room['phase'];
    if(isset($input['round']))party_require((int)$input['round']===$room['round'],'This round has moved on. Try again.');
    if($action==='start'||$action==='again'){
        party_require($host,'Only the host can start a game.',403);party_require($phase==='lobby'||$phase==='finished','A game is already running.');
        $category=(string)($input['category']??'Mixed');party_require($category==='Mixed'||array_key_exists($category,party_words()),'Choose a valid word category.',400);
        $room['category']=$category;$room['round']=0;$room['totalScore']=0;$room['history']=[];
        $recent=is_array($input['recent']??null)?array_values(array_filter(array_slice($input['recent'],-1600),fn($v)=>is_string($v)&&strlen($v)<160)):[];
        // Keep the newest three quarters of each deck out of the next session.
        $spectrumDeck=party_spectrums();$spectrumKeys=array_map(fn($v)=>implode(' | ',$v),$spectrumDeck);$room['usedSpectrums']=[];
        foreach(array_slice($recent,-intdiv(count($spectrumDeck)*3,4)) as $key){$index=array_search($key,$spectrumKeys,true);if($index!==false)$room['usedSpectrums'][]=$index;}
        $allWords=party_words();$categoryWords=$category==='Mixed'?array_merge(...array_values($allWords)):$allWords[$category];$room['usedWords']=array_slice(array_values(array_intersect($recent,$categoryWords)),-intdiv(count($categoryWords)*3,4));
        $room['usedCategories']=array_slice(array_values(array_intersect($recent,party_scatter_categories())),-intdiv(count(party_scatter_categories())*3,4));$room['usedLetters']=$room['usedLetters']??[];
        if(party_deduction_game($room['game']))party_deduction_reset($room,$input,$recent);
        if(party_puzzle_game($room['game']))party_puzzle_reset($room,$input,$recent);
        if(party_extra_game($room['game']))party_extra_reset($room,$input,$recent);
        foreach($room['players'] as &$player)$player['score']=0;unset($player);party_new_round($room);return;
    }
    if($action==='reset'){party_require($host,'Only the host can return to the lobby.',403);$room['phase']='lobby';$room['participants']=[];return;}
    if($action==='next'){
        party_require($host,'The host starts the next round.',403);party_require($phase==='reveal','Finish the current round first.');
        if(party_extra_game($room['game'])&&party_extra_next($room))return;
        if($room['maxRounds']>0&&$room['round']>=$room['maxRounds']){$room['phase']='finished';return;}party_new_round($room);return;
    }
    if($action==='leave'){foreach($room['players'] as &$p)if($p['id']===$id)$p['left']=true;unset($p);return;}
    if($action==='skip'){
        party_require($host,'Only the host can skip a round.',403);party_require(!in_array($phase,['lobby','finished','reveal'],true),'There is no active round to skip.');
        $room['phase']='reveal';$room['result']=['skipped'=>true,'points'=>0,'reason'=>'The host skipped this round.'];return;
    }
    party_require(in_array($id,$room['participants'],true)||($host&&in_array($action,['review','merge','take_judge','finish','end_race'],true)),'You’ll join in the next round.',403);
    if(party_deduction_game($room['game'])){party_deduction_action($room,$id,$action,$input);return;}
    if(party_puzzle_game($room['game'])){party_puzzle_action($room,$id,$action,$input);return;}
    if(party_extra_game($room['game'])){party_extra_action($room,$id,$action,$input);return;}
    if($room['game']==='wavelength'){
        if($action==='reroll'){
            party_require($phase==='clue'&&$room['giver']===$id,'Only the clue-giver can change this spectrum.',403);party_require($room['rerolls']<3,'Three spectrum swaps per round.');
            $deck=party_spectrums();$available=array_values(array_diff(range(0,count($deck)-1),$room['usedSpectrums']));if(!$available)$available=range(0,count($deck)-1);
            $index=$available[random_int(0,count($available)-1)];$room['usedSpectrums'][]=$index;$room['spectrum']=$deck[$index];$room['target']=random_int(120,880)/10;$room['rerolls']++;return;
        }
        if($action==='clue'){
            party_require($phase==='clue'&&$room['giver']===$id,'It is not your turn to give the clue.',403);
            $clue=trim((string)($input['clue']??''));party_require($clue!==''&&strlen($clue)<=180,'Give a short clue (up to 90 characters).',400);
            $room['clue']=$clue;$room['phase']='guess';return;
        }
        if($action==='guess'){
            party_require($phase==='guess'&&$room['giver']!==$id,'The clue-giver cannot move a guess.',403);$value=$input['value']??null;
            party_require(is_numeric($value)&&is_finite((float)$value)&&$value>=0&&$value<=100,'Guess a position from 0 to 100.',400);$room['guesses'][$id]=round((float)$value,1);return;
        }
        if($action==='reveal'){
            party_require($host,'The host locks in the group guess.',403);party_require($phase==='guess','The group is not guessing yet.');party_require(count($room['guesses'])>0,'Wait for at least one guess.');
            $guess=party_median(array_values($room['guesses']));$points=party_points($guess,$room['target']);$room['totalScore']+=$points;
            $room['result']=['guess'=>$guess,'target'=>$room['target'],'points'=>$points];$room['history'][]=['round'=>$room['round'],'points'=>$points];$room['phase']='reveal';return;
        }
    }elseif($room['game']==='imposter'){
        if($action==='clue'){
            party_require($phase==='clues'&&($room['turnOrder'][$room['turnIndex']]??null)===$id,'Wait for your turn to give a clue.',403);
            $clue=trim((string)($input['clue']??''));party_require($clue!==''&&strlen($clue)<=80,'Give one word or a short phrase (up to 40 characters).',400);
            party_require(strcasecmp($clue,$room['word'])!==0,'Keep the secret word out of your clue.',400);$room['clues'][$id]=$clue;$room['turnIndex']++;
            if($room['turnIndex']>=count($room['turnOrder']))$room['phase']='vote';return;
        }
        if($action==='vote'){
            party_require($phase==='vote','Voting is not open.');$suspect=(string)($input['suspect']??'');party_require($suspect!==$id&&in_array($suspect,$room['participants'],true),'Vote for another player.',400);
            party_require(!isset($room['votes'][$id]),'Your vote is already locked.');$room['votes'][$id]=$suspect;
            if(count($room['votes'])===count($room['participants']))party_resolve_vote($room);return;
        }
        if($action==='finish_vote'){
            party_require($host&&$phase==='vote','Only the host can finish this vote.',403);
            $remaining=array_diff($room['participants'],array_keys($room['votes']));foreach($remaining as $missing){$p=party_member($room,$missing);party_require(!$p||$p['left']||time()-$p['seen']>=45,'Wait for connected players to vote.');}
            party_require(count($room['votes'])>0,'Wait for at least one vote.');party_resolve_vote($room);return;
        }
        if($action==='final_guess'){
            party_require($phase==='last_guess'&&$room['imposter']===$id,'Only the caught imposter gets a final guess.',403);$guess=trim((string)($input['guess']??''));party_require($guess!==''&&strlen($guess)<=80,'Enter your final guess.',400);
            $normalize=fn($text)=>strtolower(preg_replace('/[^\p{L}\p{N}]/u','',$text));$correct=$normalize($guess)===$normalize($room['word']);$room['result']['finalGuess']=$guess;
            party_imposter_result($room,$correct,$correct?'The imposter guessed the secret word.':'The group caught the imposter.');return;
        }
        if($action==='finish_guess'){
            party_require($host&&$phase==='last_guess','Only the host can finish this guess.',403);$p=party_member($room,$room['imposter']);party_require(!$p||$p['left']||time()-$p['seen']>=45,'Give the imposter time to make their final guess.');party_imposter_result($room,false,'The imposter did not return for a final guess.');return;
        }
    }
    if($room['game']==='scattergories'){
        if($action==='answers'||$action==='submit'){
            party_require($phase==='writing'&&time()<$room['deadline'],'The timer is up. Answers are locked.');party_require(!in_array($id,$room['ready'],true),'Your answer sheet is already locked.');
            $values=$input['answers']??null;party_require(is_array($values),'Send your answer sheet.',400);$answers=[];
            for($i=0;$i<count($room['categories']);$i++){$value=$values[$i]??'';party_require(is_string($value)&&strlen($value)<=240,'Keep each answer under 60 characters.',400);$answers[]=trim($value);}
            $room['answers'][$id]=$answers;if($action==='submit')$room['ready'][]=$id;
            if(count($room['ready'])===count($room['participants']))party_scatter_reveal($room);return;
        }
        if($action==='review'){
            party_require($host&&$phase==='reveal'&&!($room['result']['skipped']??false),'Only the host can review revealed answers.',403);
            $player=(string)($input['player']??'');$index=$input['index']??null;party_require(in_array($player,$room['participants'],true)&&is_int($index)&&$index>=0&&$index<count($room['categories']),'Choose an answer to review.',400);
            $room['rejected'][$player][$index]=!(bool)($input['accepted']??true);party_scatter_reveal($room);return;
        }
    }
    throw new RuntimeException('Unknown game action.',400);
}
function party_view(array $room,string $id): array {
    $public=['code'=>$room['code'],'game'=>$room['game'],'host'=>$room['host'],'you'=>$id,'phase'=>$room['phase'],'round'=>$room['round'],'maxRounds'=>$room['maxRounds'],'version'=>$room['version'],'participants'=>$room['participants'],'result'=>$room['result'],'players'=>[]];
    foreach($room['players'] as $p)if(!$p['left'])$public['players'][]=['id'=>$p['id'],'name'=>$p['name'],'score'=>$p['score'],'online'=>time()-$p['seen']<30];
    if($room['game']==='wavelength'){
        $public['spectrum']=$room['spectrum']??null;$public['giver']=$room['giver']??null;$public['clue']=$room['clue']??'';$public['guesses']=$room['guesses'];$public['groupGuess']=party_median(array_values($room['guesses']));$public['totalScore']=$room['totalScore'];$public['history']=$room['history'];
        if(!in_array($room['phase'],['lobby','finished'],true)&&($id===($room['giver']??null)||$room['phase']==='reveal'))$public['target']=$room['target'];
        if($id===($room['giver']??null))$public['rerolls']=$room['rerolls']??0;
    }elseif($room['game']==='imposter'){
        $public['clues']=$room['clues'];$public['order']=$room['turnOrder']??[];$public['turn']=($room['turnOrder'][$room['turnIndex']]??null);$public['category']=$room['wordCategory']??null;$public['voteCount']=count($room['votes']);$public['voted']=array_keys($room['votes']);$public['yourVote']=$room['votes'][$id]??null;
        if(in_array($id,$room['participants'],true)&&!in_array($room['phase'],['lobby','finished'],true)){
            $public['role']=$id===($room['imposter']??null)?'imposter':'informed';if($public['role']==='informed'||$room['phase']==='reveal')$public['word']=$room['word'];
        }
        if(in_array($room['phase'],['reveal','last_guess'],true))$public['imposter']=$room['imposter'];
        if($room['phase']==='reveal')$public['word']=$room['word'];
    }
    if($room['game']==='scattergories'){
        $public['categories']=$room['categories']??[];$public['letter']=$room['letter']??null;$public['deadline']=$room['deadline']??null;$public['serverTime']=time();$public['ready']=$room['ready']??[];
        $public['yourAnswers']=$room['answers'][$id]??[];
        // All answer sheets are included only after the round is locked.
        if($room['phase']==='reveal')$public['answers']=$room['answers']??[];
    }
    if(party_deduction_game($room['game']))$public=array_merge($public,party_deduction_view($room,$id));
    if(party_puzzle_game($room['game']))$public=array_merge($public,party_puzzle_view($room,$id));
    if(party_extra_game($room['game']))$public=array_merge($public,party_extra_view($room,$id));
    return $public;
}

function party_scatter_normalize(string $value): string {
    $lower=function_exists('mb_strtolower')?mb_strtolower(trim($value)):strtolower(trim($value));
    $lower=preg_replace('/^(the|an|a)\s+/u','',$lower);
    return preg_replace('/[^\p{L}\p{N}]/u','',$lower);
}
function party_scatter_reveal(array &$room): void {
    $previous=$room['result']['scores']??[];$scores=array_fill_keys($room['participants'],0);$details=[];
    foreach($room['categories'] as $index=>$category){
        $counts=[];$keys=[];
        foreach($room['participants'] as $id){$key=party_scatter_normalize($room['answers'][$id][$index]??'');$keys[$id]=$key;if($key!=='')$counts[$key]=($counts[$key]??0)+1;}
        foreach($room['participants'] as $id){$key=$keys[$id];$status=$key===''?'blank':(strtoupper(substr($key,0,1))!==$room['letter']?'letter':(($counts[$key]??0)>1?'duplicate':'unique'));
            if($room['rejected'][$id][$index]??false)$status='rejected';$points=$status==='unique'?1:0;$scores[$id]+=$points;$details[$id][$index]=['points'=>$points,'status'=>$status];}
    }
    foreach($room['players'] as &$player)if(isset($scores[$player['id']]))$player['score']+=$scores[$player['id']]-($previous[$player['id']]??0);unset($player);
    $room['result']=['scores'=>$scores,'details'=>$details];$room['phase']='reveal';
}
function party_tick(array &$room): void {
    party_extra_tick($room);
    if(party_deduction_game($room['game'])&&$room['phase']==='deducing'&&microtime(true)>=$room['deadline']){party_deduction_reveal($room);$room['version']++;}
    if(party_puzzle_game($room['game'])&&$room['phase']==='race'&&microtime(true)>=$room['deadline']){party_puzzle_reveal($room);$room['version']++;}
    if($room['game']==='scattergories'&&$room['phase']==='writing'&&time()>=$room['deadline']){party_scatter_reveal($room);$room['version']++;}
}
