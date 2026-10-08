<?php
require_once __DIR__.'/new-game-data.php';
function party_extra_game(string $game): bool {return in_array($game,['hivemind','apples','humanity','desktop-disaster'],true);}
function party_extra_reset(array &$room,array $input,array $recent): void {
    $room['usedNewPrompts']=$recent;$room['hands']=[];$room['cardDeck']=[];$room['levels']=[];$room['roundScores']=[];$room['gameOver']=false;$room['winners']=[];
    if($room['game']==='hivemind'){$levels=(int)($input['levels']??6);party_require(in_array($levels,[6,8],true),'Choose a six- or eight-level hive.',400);$room['hiveSize']=$levels;$room['maxRounds']=0;}
    if(in_array($room['game'],['apples','humanity'],true)){
        $count=count(party_ids($room));$room['targetScore']=$room['game']==='apples'?max(4,12-max(4,$count)):(int)($input['target']??7);
        party_require(in_array($room['targetScore'],[0,4,5,6,7,8,10,15],true),'Choose a valid score target.',400);$room['maxRounds']=0;
        $cards=$room['game']==='apples'?party_apple_cards():party_humanity_cards();$fresh=[];$old=[];foreach($cards as $i=>$text){if(in_array('card:'.$text,$recent,true))$old[]=$i;else $fresh[]=$i;}shuffle($fresh);shuffle($old);$room['cardDeck']=array_merge($fresh,$old);
    }
    if($room['game']==='desktop-disaster'){$room['maxRounds']=count(party_ids($room))*2;$room['lastTaskType']=null;}
}
function party_extra_prompt(array &$room,array $deck): array {
    $fresh=array_values(array_filter($deck,fn($p)=>!in_array($p['text'],$room['usedNewPrompts'],true)));
    if(!$fresh){$room['usedNewPrompts']=array_slice($room['usedNewPrompts'],-intdiv(count($deck),2));$fresh=array_values(array_filter($deck,fn($p)=>!in_array($p['text'],$room['usedNewPrompts'],true)));}
    $chosen=$fresh[random_int(0,count($fresh)-1)];$room['usedNewPrompts'][]=$chosen['text'];return $chosen;
}
function party_deal(array &$room,string $id,int $size): void {
    $cards=$room['game']==='apples'?party_apple_cards():party_humanity_cards();$room['hands'][$id]=$room['hands'][$id]??[];
    while(count($room['hands'][$id])<$size){
        if(!$room['cardDeck']){$held=[];foreach($room['hands'] as $hand)foreach($hand as $card)$held[]=$card['index'];$room['cardDeck']=array_values(array_diff(range(0,count($cards)-1),$held));shuffle($room['cardDeck']);}
        $index=array_shift($room['cardDeck']);$room['hands'][$id][]=['id'=>bin2hex(random_bytes(6)),'text'=>$cards[$index],'index'=>$index];
    }
}
function party_extra_round(array &$room,array $ids): void {
    $game=$room['game'];$room['ready']=[];$room['deadline']=null;
    if($game==='hivemind'){
        $occupied=array_intersect_key($room['levels'],array_flip($ids));$entryLevel=$occupied?min($occupied):0;foreach($ids as $id)if(!isset($room['levels'][$id]))$room['levels'][$id]=$entryLevel;
        $room['active']=$ids[($room['round']-1)%count($ids)];$room['questionOptions']=[];for($i=0;$i<3;$i++)$room['questionOptions'][]=party_extra_prompt($room,party_hive_prompts());
        $room['die']=[['dots'=>1,'star'=>false],['dots'=>1,'star'=>false],['dots'=>2,'star'=>false],['dots'=>2,'star'=>false],['dots'=>3,'star'=>false],['dots'=>1,'star'=>true]][random_int(0,5)];
        if(count($ids)===3&&$room['die']['dots']===3)$room['die']['dots']=2;
        $room['answers']=[];$room['aliases']=[];$room['prompt']=null;$room['phase']='question';
    }elseif($game==='apples'||$game==='humanity'){
        foreach($ids as $id)party_deal($room,$id,$game==='apples'?7:10);
        $room['judge']=$ids[($room['round']-1)%count($ids)];$room['submissions']=[];$room['choiceOrder']=[];
        $deck=$game==='apples'?array_map(fn($s)=>['text'=>$s,'pick'=>1],party_apple_adjectives()):party_humanity_prompts();
        $room['prompt']=party_extra_prompt($room,$deck);$room['phase']='playing';$room['deadline']=time()+120;
    }else{
        $room['active']=$ids[($room['round']-1)%count($ids)];$room['task']=party_disaster_task($room);$room['step']=0;$room['traps']=[];$room['channels']=[];$room['trapStats']=[];$room['trapCooldown']=[];$room['roundScores']=array_fill_keys($ids,0);
        $saboteurs=array_values(array_diff($ids,[$room['active']]));$types=['popup','notification','error','buttons'];shuffle($types);foreach($saboteurs as $i=>$id){$room['channels'][$id]=[];$room['trapStats'][$id]=['deployed'=>0,'seen'=>0,'hits'=>0];}
        for($i=0;$i<max(count($saboteurs),4);$i++)$room['channels'][$saboteurs[$i%count($saboteurs)]][]=$types[$i%4];
        $room['phase']='desktop';$room['deadline']=time()+90;
    }
}
function party_hive_key(string $value): string {
    $text=party_scatter_normalize($value);
    if(strlen($text)>3){if(preg_match('/ies$/',$text))$text=substr($text,0,-3).'y';elseif(preg_match('/(ches|shes|xes|zes|sses)$/',$text))$text=substr($text,0,-2);elseif(substr($text,-1)==='s'&&substr($text,-2)!=='ss')$text=substr($text,0,-1);}
    return $text;
}
function party_hive_group(array $room,string $key): string {for($i=0;$i<20&&isset($room['aliases'][$key]);$i++)$key=$room['aliases'][$key];return $key;}
function party_hive_reveal(array &$room): void {
    $groups=[];$scores=[];$perPlayer=[];
    foreach($room['participants'] as $id){$scores[$id]=0;$seen=[];foreach($room['answers'][$id]??[] as $answer){if(trim($answer)==='')continue;$scores[$id]++;$key=party_hive_group($room,party_hive_key($answer));$groups[$key]['answers'][]=['player'=>$id,'text'=>$answer];if(!isset($seen[$key])){$groups[$key]['players'][]=$id;$seen[$key]=true;}}$perPlayer[$id]=array_keys($seen);}
    foreach($perPlayer as $id=>$keys)foreach($keys as $key)$scores[$id]+=count($groups[$key]['players'])-1;
    foreach($room['players'] as &$player)if(isset($scores[$player['id']]))$player['score']=$scores[$player['id']];unset($player);
    $totals=array_values(array_unique(array_values($scores)));sort($totals);$low=array_slice($totals,0,$room['die']['dots']);$high=max($scores);$levels=$room['levels'];$movement=[];
    foreach($scores as $id=>$score){$move=(in_array($score,$low,true)?1:0)-($room['die']['star']&&$score===$high?1:0);$levels[$id]=max(0,$levels[$id]+$move);$movement[$id]=$levels[$id]-$room['levels'][$id];}
    $room['result']=['scores'=>$scores,'groups'=>array_values($groups),'levels'=>$levels,'movement'=>$movement];$room['phase']='reveal';
}
function party_cards_judge(array &$room): void {
    $room['choiceOrder']=array_keys($room['submissions']);shuffle($room['choiceOrder']);
    if(!$room['choiceOrder']){$room['phase']='reveal';$room['result']=['skipped'=>true,'reason'=>'No cards were submitted before time ran out.'];}
    else{$room['phase']='judging';$room['deadline']=time()+120;}
}
function party_extra_next(array &$room): bool {
    if($room['game']==='hivemind'&&!($room['result']['skipped']??false)){
        $room['levels']=$room['result']['levels'];$exited=array_filter($room['participants'],fn($id)=>$room['levels'][$id]>=$room['hiveSize']);
        if($exited){$room['winners']=array_values(array_filter($room['participants'],fn($id)=>$room['levels'][$id]<$room['hiveSize']));if(!$room['winners'])$room['winners']=$room['participants'];$room['phase']='finished';return true;}
    }
    if(in_array($room['game'],['apples','humanity'],true)&&$room['gameOver']){$room['phase']='finished';return true;}
    return false;
}
function party_extra_action(array &$room,string $id,string $action,array $input): void {
    $game=$room['game'];$host=$id===$room['host'];$phase=$room['phase'];
    if($action==='finish') {party_require($host&&$phase==='reveal'&&$game==='humanity','The host can end the card game between rounds.',403);$room['phase']='finished';return;}
    if($game==='hivemind'){
        if($action==='question'){
            party_require($phase==='question'&&$id===$room['active'],'Only the active player chooses the question.',403);
            if(isset($input['custom'])){$text=trim((string)$input['custom']);$count=(int)($input['count']??3);party_require($text!==''&&strlen($text)<=240&&$count>=2&&$count<=5,'Write a question asking for two to five answers.',400);$room['prompt']=['text'=>$text,'count'=>$count];}
            else{$index=$input['index']??null;party_require(is_int($index)&&isset($room['questionOptions'][$index]),'Choose one of the three questions.',400);$room['prompt']=$room['questionOptions'][$index];}
            $room['deadline']=time()+120;$room['phase']='answering';return;
        }
        if($action==='answer'||$action==='submit'){
            party_require($phase==='answering'&&time()<$room['deadline'],'Answers are locked.');party_require(!in_array($id,$room['ready'],true),'Your answers are already submitted.');
            $answers=$input['answers']??null;party_require(is_array($answers),'Send your answers.',400);$out=[];for($i=0;$i<$room['prompt']['count'];$i++){$text=$answers[$i]??'';party_require(is_string($text)&&strlen($text)<=180,'Keep each answer short.',400);$out[]=trim($text);}$room['answers'][$id]=$out;
            if($action==='submit')$room['ready'][]=$id;if(count($room['ready'])===count($room['participants']))party_hive_reveal($room);return;
        }
        if($action==='merge'){
            party_require($host&&$phase==='reveal'&&!($room['result']['skipped']??false),'The host resolves matching answers after reveal.',403);
            $from=party_hive_key((string)($input['from']??''));$to=party_hive_key((string)($input['to']??''));$known=[];foreach($room['answers'] as $answers)foreach($answers as $answer)$known[]=party_hive_key($answer);
            party_require($from!==''&&in_array($from,$known,true)&&in_array($to,$known,true),'Choose answers from this round.',400);
            if($from===$to)unset($room['aliases'][$from]);else{$target=party_hive_group($room,$to);party_require($target!==$from,'Those answers already belong to the same group.');$room['aliases'][$from]=$target;}
            party_hive_reveal($room);return;
        }
    }
    if($game==='apples'||$game==='humanity'){
        if($action==='play'){
            party_require($phase==='playing'&&time()<$room['deadline'],'Card submissions are locked.');party_require($id!==$room['judge'],'The judge does not submit cards.',403);party_require(!isset($room['submissions'][$id]),'Your cards are already locked.');
            $cards=$input['cards']??null;party_require(is_array($cards)&&count($cards)===$room['prompt']['pick']&&count(array_unique($cards))===count($cards),'Choose the required number of different cards.',400);
            $texts=[];foreach($cards as $cardId){$matches=array_values(array_filter($room['hands'][$id],fn($c)=>$c['id']===$cardId));party_require(count($matches)===1,'Choose cards from your own hand.',403);$texts[]=$matches[0]['text'];}
            $room['hands'][$id]=array_values(array_filter($room['hands'][$id],fn($c)=>!in_array($c['id'],$cards,true)));$room['submissions'][$id]=['id'=>bin2hex(random_bytes(8)),'texts'=>$texts];
            if(count($room['submissions'])===count($room['participants'])-1)party_cards_judge($room);return;
        }
        if($action==='choose'){
            party_require($phase==='judging'&&$id===$room['judge'],'Only the judge picks the winner.',403);$choice=(string)($input['choice']??'');$winner=null;foreach($room['submissions'] as $player=>$entry)if($entry['id']===$choice)$winner=$player;party_require($winner!==null&&$winner!==$id,'Choose a submitted card.',400);
            foreach($room['players'] as &$player)if($player['id']===$winner){$player['score']++;if($room['targetScore']&&$player['score']>=$room['targetScore'])$room['gameOver']=true;}unset($player);
            $room['result']=['winner'=>$winner,'choice'=>$choice,'texts'=>$room['submissions'][$winner]['texts'],'gameOver'=>$room['gameOver']];$room['phase']='reveal';return;
        }
        if($action==='finish_plays'){
            party_require($host&&$phase==='playing','The host may close submissions for disconnected players.',403);foreach(array_diff($room['participants'],array_merge([$room['judge']],array_keys($room['submissions']))) as $missing){$p=party_member($room,$missing);party_require(!$p||$p['left']||time()-$p['seen']>=45,'Wait for connected players to submit.');}party_cards_judge($room);return;
        }
        if($action==='take_judge'){
            party_require($host&&$phase==='judging','Only the host can replace an absent judge.',403);$judge=party_member($room,$room['judge']);party_require(!$judge||$judge['left']||time()-$judge['seen']>=45,'The current judge is still connected.');$room['judge']=$id;unset($room['submissions'][$id]);party_cards_judge($room);return;
        }
    }
    if($game==='desktop-disaster'){party_disaster_action($room,$id,$action,$input);return;}
    throw new RuntimeException('Unknown game action.',400);
}
function party_extra_view(array $room,string $id): array {
    $out=['serverTime'=>time(),'deadline'=>$room['deadline']??null,'ready'=>$room['ready']??[]];$game=$room['game'];
    if($game==='hivemind'){
        $out+=['active'=>$room['active']??null,'prompt'=>$room['prompt']??null,'die'=>$room['die']??null,'levels'=>$room['levels']??[],'hiveSize'=>$room['hiveSize']??6,'winners'=>$room['winners']??[],'yourAnswers'=>$room['answers'][$id]??[]];
        if($room['phase']==='question'&&$id===($room['active']??null))$out['questionOptions']=$room['questionOptions'];
    }elseif($game==='apples'||$game==='humanity'){
        $out+=['judge'=>$room['judge']??null,'prompt'=>$room['prompt']??null,'targetScore'=>$room['targetScore']??0,'submitted'=>array_keys($room['submissions']??[]),'yourPlay'=>$room['submissions'][$id]['texts']??null,'hand'=>[]];
        if(in_array($id,$room['participants'],true)&&!in_array($room['phase'],['lobby','finished'],true))$out['hand']=array_map(fn($c)=>['id'=>$c['id'],'text'=>$c['text']],$room['hands'][$id]??[]);
        if(in_array($room['phase'],['judging','reveal'],true)){$out['choices']=[];foreach($room['choiceOrder']??[] as $player){$entry=$room['submissions'][$player];if($room['phase']==='reveal')$entry['player']=$player;$out['choices'][]=$entry;}}
    }else $out+=party_disaster_view($room,$id);
    return $out;
}
function party_extra_tick(array &$room): void {
    if(!party_extra_game($room['game']))return;$phase=$room['phase'];$expired=isset($room['deadline'])&&$room['deadline']!==null&&time()>=$room['deadline'];if(!$expired)return;
    if($room['game']==='hivemind'&&$phase==='answering')party_hive_reveal($room);
    elseif(in_array($room['game'],['apples','humanity'],true)&&$phase==='playing')party_cards_judge($room);
    elseif(in_array($room['game'],['apples','humanity'],true)&&$phase==='judging'){$room['phase']='reveal';$room['result']=['skipped'=>true,'reason'=>'The judge ran out of time. No point awarded.'];}
    elseif($room['game']==='desktop-disaster'&&$phase==='desktop')party_disaster_finish($room,false);
    else return;$room['version']++;
}
