<?php
require_once __DIR__.'/deduction-data.php';
require_once __DIR__.'/rulebreakers-data.php';
function party_deduction_game(string $game): bool {return in_array($game,['rulebreakers','alien-dictionary','question-quest'],true);}
function party_deduction_reset(array &$room,array $input,array $recent): void {
 $difficulty=(string)($input['difficulty']??'classic');party_require(in_array($difficulty,['easy','classic','expert'],true),'Choose a valid difficulty.',400);
 $rounds=$input['rounds']??3;party_require(is_int($rounds)&&in_array($rounds,[1,3,5,10],true),'Choose 1, 3, 5, or 10 rounds.',400);
 $room['difficulty']=$difficulty;$room['maxRounds']=$rounds;$room['usedDeduction']=$recent;$room['ruleWordMemory']=array_values(array_map(fn($v)=>substr($v,10),array_filter($recent,fn($v)=>strpos($v,'rule-word:')===0)));
}
function party_rule_atoms(): array {static $atoms;if($atoms===null){$atoms=[];foreach(party_rule_data()['atoms'] as $a)$atoms[$a['id']]=$a;}return $atoms;}
function party_rule_mask(array $rule): string {
 $atoms=party_rule_atoms();$a=base64_decode($atoms[$rule['a']]['mask']);$op=$rule['op'];if($op==='single')return $a;
 $b=base64_decode($atoms[$rule['b']]['mask']);return $op==='and'?($a&$b):($op==='or'?($a|$b):($op==='xor'?($a^$b):($a&(~$b))));
}
function party_rule_pass(string $mask,int $index): bool {return (ord($mask[intdiv($index,8)])&(1<<($index%8)))!==0;}
function party_rule_text(array $rule): string {
 $a=party_rule_atoms()[$rule['a']]['text'];if($rule['op']==='single')return 'A word passes if it '.$a.'.';$b=party_rule_atoms()[$rule['b']]['text'];
 return ['and'=>'A word passes if it '.$a.' AND '.$b.'. Both conditions must hold.','or'=>'A word passes if it '.$a.' OR '.$b.'. Either or both can hold.','xor'=>'A word passes if it '.$a.' OR '.$b.', but not both.','andnot'=>'A word passes if it '.$a.' AND does not satisfy: '.$b.'.'][$rule['op']];
}
function party_deduction_fresh(array &$room,array $deck): array {
 $used=array_fill_keys($room['usedDeduction'],true);$fresh=array_values(array_filter($deck,fn($p)=>!isset($used[$p['id']??$p['key']])));
 if(!$fresh){$room['usedDeduction']=array_slice($room['usedDeduction'],-intdiv(count($deck),2));$used=array_fill_keys($room['usedDeduction'],true);$fresh=array_values(array_filter($deck,fn($p)=>!isset($used[$p['id']??$p['key']])));}
 $chosen=$fresh[random_int(0,count($fresh)-1)];$room['usedDeduction'][]=$chosen['id']??$chosen['key'];return $chosen;
}
function party_alien_caption(array $meaning): string {
 // Canonical English order: number, adjective, noun; subject, verb, object.
 $parts=[];$plural=false;$lastNumber=null;foreach($meaning as $term){if($term['kind']==='number')$lastNumber=$term['word'];$word=$term['word'];if($term['kind']==='noun'&&$lastNumber!==null&&$lastNumber!=='one'){$irregular=['mouse'=>'mice','snowman'=>'snowmen','goose'=>'geese','sheep'=>'sheep','deer'=>'deer','wolf'=>'wolves','elf'=>'elves','fairy'=>'fairies','butterfly'=>'butterflies'];$word=$irregular[$word]??($word==='fox'?'foxes':($word==='octopus'?'octopuses':$word.'s'));$plural=true;$lastNumber=null;}elseif($term['kind']==='noun'){$plural=false;$lastNumber=null;}
 if($term['kind']==='verb'&&$plural){$word=['chases'=>'chase','watches'=>'watch','teaches'=>'teach','carries'=>'carry','copies'=>'copy','surprises'=>'surprise','dances with'=>'dance with'][$word]??preg_replace('/s(?= |$)/','',$word);}$parts[]=$word;}
 return implode(' ',$parts);
}
function party_alien_translate(array $meaning,array $language): array {
 $groups=[[]];$verb=null;foreach($meaning as $term){if($term['kind']==='verb'){$verb=$term;$groups[]=[];}else $groups[count($groups)-1][]=$term;}
 $phrases=[];foreach($groups as $terms){$by=[];foreach($terms as $term)$by[$term['kind']]=$term;$ordered=[];foreach($language['phraseOrder'] as $kind)if(isset($by[$kind]))$ordered[]=$by[$kind]['alien'];$phrases[]=$ordered;}
 if($verb===null)return $phrases[0];$by=['S'=>$phrases[0],'V'=>[$verb['alien']],'O'=>$phrases[1]];$out=[];foreach(str_split($language['sentenceOrder']) as $part)$out=array_merge($out,$by[$part]);return $out;
}
function party_alien_phrase(array $lexicon,bool $full=true): array {
 $out=[];foreach(['number','adj','noun'] as $kind)if($kind==='noun'||$full||random_int(0,1)){ $pool=$lexicon[$kind];$out[]=$pool[random_int(0,count($pool)-1)];}return $out;
}
function party_alien_meaning(array $lexicon,bool $sentence=true): array {
 $a=party_alien_phrase($lexicon);if(!$sentence)return $a;$verbs=$lexicon['verb'];return array_merge($a,[$verbs[random_int(0,count($verbs)-1)]],party_alien_phrase($lexicon));
}
function party_alien_examples_identify(array $examples,array $lexicon): bool {
 $signatures=[];foreach($lexicon as $terms)foreach($terms as $term){$sig='';foreach($examples as $example)$sig.=in_array($term['alien'],$example['alien'],true)?'1':'0';if(substr_count($sig,'1')<2||isset($signatures[$sig]))return false;$signatures[$sig]=true;}return true;
}
function party_alien_puzzle(string $difficulty): array {
 $sizes=['easy'=>[3,2,2,1],'classic'=>[3,3,2,2],'expert'=>[4,3,3,2]][$difficulty];$vocabulary=party_alien_vocabulary();$syllables=['za','mi','ko','lu','ve','ra','ti','no','pa','su','di','fe','go','ha','ji','ke','lo','ma','ne','po','ri','sa','tu','va','wo','xi','ya','zu','chi','dra','flo','gli','kri','plo','sha','tho','vra','zho','qua','ble'];$lexicon=[];$used=[];$number=0;
 foreach(['noun','adj','verb','number'] as $k=>$kind){$pool=$vocabulary[$kind];shuffle($pool);$lexicon[$kind]=[];foreach(array_slice($pool,0,$sizes[$k]) as [$word,$icon]){do{$alien=$syllables[random_int(0,count($syllables)-1)].$syllables[random_int(0,count($syllables)-1)];}while(isset($used[$alien]));$used[$alien]=true;$lexicon[$kind][]=['kind'=>$kind,'word'=>$word,'icon'=>$icon,'alien'=>$alien];$number++;}}
 $orders=$difficulty==='easy'?['SVO','SOV']:['SVO','SOV','OSV','OVS','VSO','VOS'];$phraseOrders=[['number','adj','noun'],['number','noun','adj'],['adj','noun','number'],['noun','adj','number']];$language=['lexicon'=>$lexicon,'sentenceOrder'=>$orders[random_int(0,count($orders)-1)],'phraseOrder'=>$phraseOrders[random_int(0,count($phraseOrders)-1)]];
 $examples=[];$seen=[];
 // Two contrasting contexts per term guarantee identifiable meanings without
 // relying on random coverage. Keep the reference sheet short on phones.
 $add=function(array $meaning) use (&$examples,$language): void {
  $examples[]=['meaning'=>$meaning,'english'=>party_alien_caption($meaning),'alien'=>party_alien_translate($meaning,$language),'icons'=>array_values(array_filter(array_column($meaning,'icon')))];
 };
 foreach($lexicon['noun'] as $noun)foreach(array_slice($lexicon['adj'],0,2) as $adj)$add([$adj,$noun]);
 foreach(array_slice($lexicon['adj'],2) as $adj)foreach(array_slice($lexicon['noun'],0,2) as $noun)$add([$adj,$noun]);
 foreach($lexicon['number'] as $count){$add([$count,$lexicon['adj'][0],$lexicon['noun'][0]]);$add([$count,$lexicon['adj'][1],$lexicon['noun'][1]]);}
 foreach($lexicon['verb'] as $verb){$add([$lexicon['noun'][0],$verb,$lexicon['noun'][1]]);$add([$lexicon['noun'][1],$verb,$lexicon['noun'][0]]);}
 party_require(party_alien_examples_identify($examples,$lexicon),'Could not generate a language. Try again.',503);
 foreach($examples as $example)$seen[$example['english']]=true;$challenges=[];
 for($i=0;$i<8;$i++){$sentence=$i>=2;for($tries=0;$tries<200;$tries++){if($tries>=24)$sentence=true;$meaning=party_alien_meaning($lexicon,$sentence);$english=party_alien_caption($meaning);if(!isset($seen[$english]))break;}party_require(!isset($seen[$english]),'Could not generate fresh translations. Try again.',503);$seen[$english]=true;$correct=party_alien_translate($meaning,$language);$options=[['english'=>$english,'alien'=>$correct]];$keys=[$english=>true];
 for($tries=0;count($options)<6&&$tries<200;$tries++){$wrong=$tries%3===0?party_alien_meaning($lexicon,$sentence):$meaning;$change=random_int(0,count($wrong)-1);$kind=$wrong[$change]['kind'];$wrong[$change]=$lexicon[$kind][random_int(0,count($lexicon[$kind])-1)];$text=party_alien_caption($wrong);if(isset($keys[$text]))continue;$keys[$text]=true;$options[]=['english'=>$text,'alien'=>party_alien_translate($wrong,$language)];}
 party_require(count($options)===6,'Could not generate translations. Try again.',503);shuffle($options);$correctIndex=0;foreach($options as $index=>$option)if($option['english']===$english)$correctIndex=$index;
 $challenges[]=['direction'=>$i%2===0?'decode':'encode','english'=>$english,'alien'=>$correct,'options'=>$options,'correct'=>$correctIndex];}
 return ['language'=>$language,'examples'=>$examples,'challenges'=>$challenges];
}
function party_quest_roster(int $count,array $fields): array {
 $first=['Ada','Theo','Mina','Jules','Nico','Zara','Finn','Luna','Remy','Iris','Ezra','Nora','Arlo','Milo','Nova','Sage','Rory','Vera','Kai','Cleo','Otis','Lyra','Hugo','Ivy','Leon','Maya','Omar','Pia','Quinn','Rhea','Suki','Toby','Uma','Wren','Yara','Zeke','Alma','Basil','Cora','Dara','Eli','Faye','Gus','Hazel','Ida','Juno','Kira','Lyle','Moss','Nell','Orion','Poppy','Rafi','Sora','Tess','Vio','Willa','Xavi','Yuki','Ari'];
 $last=['Moon','Bramble','Quartz','Finch','Maple','Reed','Vale','Sparks','Clover','Stone','Pepper','Wilder','Bloom','Frost','Marsh','Bright','Cedar','Fox','Wells','Riddle'];$out=[];$seen=[];$names=[];$times=$fields['time']['values'];$colors=$fields['color']['values'];shuffle($times);shuffle($colors);
 for($i=0;$i<$count;$i++){do{$traits=[];foreach($fields as $key=>$field)$traits[$key]=$field['values'][random_int(0,count($field['values'])-1)];$traits['time']=$times[$i%count($times)];$traits['color']=$colors[intdiv($i,count($times))];$signature=implode('|',$traits);}while(isset($seen[$signature]));$seen[$signature]=true;do{$name=$first[random_int(0,count($first)-1)].' '.$last[random_int(0,count($last)-1)];}while(isset($names[$name]));$names[$name]=true;$out[]=['id'=>'s'.$i,'name'=>$name,'traits'=>$traits];}shuffle($out);return $out;
}
function party_deduction_round(array &$room): void {
 $room['phase']='deducing';$room['deductionStarts']=microtime(true)+5;$room['deadline']=$room['deductionStarts']+($room['game']==='alien-dictionary'?360:300);$room['ready']=[];$room['deductionStates']=[];
 if($room['game']==='rulebreakers'){$rule=party_deduction_fresh($room,party_rule_data()['decks'][$room['difficulty']]);$mask=party_rule_mask($rule);$yes=[];$no=[];foreach(party_rule_data()['words'] as $i=>$word){if(party_rule_pass($mask,$i))$yes[]=$word;else $no[]=$word;}$freshYes=array_values(array_diff($yes,$room['ruleWordMemory']));$freshNo=array_values(array_diff($no,$room['ruleWordMemory']));if(count($freshYes)>=3)$yes=$freshYes;if(count($freshNo)>=3)$no=$freshNo;shuffle($yes);shuffle($no);$yes=array_slice($yes,0,3);$no=array_slice($no,0,3);$room['ruleWordMemory']=array_slice(array_merge($room['ruleWordMemory'],$yes,$no),-1200);$room['deductionPuzzle']=['rule'=>$rule,'examples'=>['yes'=>$yes,'no'=>$no]];}
 elseif($room['game']==='alien-dictionary'){$room['deductionPuzzle']=party_alien_puzzle($room['difficulty']);$room['deductionPuzzle']['key']='alien:'.bin2hex(random_bytes(8));}
 else{$story=party_deduction_fresh($room,party_quest_stories());$fields=party_quest_fields();if($room['difficulty']==='easy')foreach($fields as &$field)$field['values']=array_slice($field['values'],0,6);unset($field);$count=['easy'=>16,'classic'=>24,'expert'=>36][$room['difficulty']];$roster=party_quest_roster($count,$fields);$room['deductionPuzzle']=['story'=>$story,'fields'=>$fields,'roster'=>$roster,'secret'=>$roster[random_int(0,count($roster)-1)]['id']];}
 foreach($room['participants'] as $id)$room['deductionStates'][$id]=['solved'=>false,'finishedAt'=>null,'probes'=>[],'guesses'=>[],'wrong'=>0,'answers'=>[],'history'=>[],'spent'=>0,'possible'=>$room['game']==='question-quest'?array_column($room['deductionPuzzle']['roster'],'id'):[]];
}
function party_deduction_reveal(array &$room): void {
 if($room['phase']!=='deducing')return;$scores=[];$standings=[];
 foreach($room['participants'] as $id){$state=$room['deductionStates'][$id];$points=$room['game']==='alien-dictionary'?2*count(array_filter($state['answers'],fn($a)=>$a['correct'])):($state['solved']?($room['game']==='rulebreakers'?max(1,12-max(0,count($state['probes'])-3)-2*$state['wrong']):max(1,20-$state['spent'])):0);$scores[$id]=$points;$standings[]=['player'=>$id,'solved'=>$state['solved'],'points'=>$points,'spent'=>$state['spent'],'probes'=>count($state['probes']),'wrong'=>$state['wrong'],'correct'=>count(array_filter($state['answers'],fn($a)=>$a['correct'])),'finishedAt'=>$state['finishedAt']];}
 usort($standings,fn($a,$b)=>($b['points']<=>$a['points'])?: (($a['finishedAt']??PHP_INT_MAX)<=>($b['finishedAt']??PHP_INT_MAX)));foreach($room['players'] as &$player)$player['score']+=$scores[$player['id']]??0;unset($player);
 $room['result']=['scores'=>$scores,'standings'=>$standings];$room['phase']='reveal';
}
function party_deduction_action(array &$room,string $id,string $action,array $input): void {
 party_require($room['phase']==='deducing','This round has finished.');$now=microtime(true);party_require($now>=$room['deductionStarts'],'Wait for the countdown.');party_require($now<$room['deadline'],'Time is up.');
 if($action==='end_race'){party_require($id===$room['host'],'Only the host can end the round.',403);party_deduction_reveal($room);return;}
 party_require(!in_array($id,$room['ready'],true),'Your round is locked.');$state=&$room['deductionStates'][$id];$puzzle=$room['deductionPuzzle'];
 if($action==='give_up'){$room['ready'][]=$id;$state['finishedAt']=$now;}
 elseif($room['game']==='rulebreakers'){
  if($action==='probe'){$word=strtolower(trim((string)($input['word']??'')));$index=array_search($word,party_rule_data()['words'],true);party_require($index!==false,'Use a word from the game dictionary (3–6 letters).',400);party_require(count($state['probes'])<30,'Thirty tests per round. Try a rule or pass.');party_require(!in_array($word,array_column($state['probes'],'word'),true),'You already tested that word.');party_require(!in_array($word,array_merge($puzzle['examples']['yes'],$puzzle['examples']['no']),true),'That word is already a starting example.');$state['probes'][]=['word'=>$word,'pass'=>party_rule_pass(party_rule_mask($puzzle['rule']),$index)];}
  elseif($action==='rule'){$a=$input['a']??'';$b=$input['b']??null;$op=$input['op']??'';$atoms=party_rule_atoms();party_require(is_string($a)&&isset($atoms[$a])&&in_array($op,['single','and','or','xor','andnot'],true)&&($op==='single'||(is_string($b)&&isset($atoms[$b]))),'Build a valid rule from the conditions.',400);$rule=['a'=>$a,'op'=>$op,'b'=>$op==='single'?null:$b];$actual=party_rule_mask($puzzle['rule']);$candidate=party_rule_mask($rule);$correct=$actual===$candidate;$counter=null;if(!$correct){$state['wrong']++;$different=$actual^$candidate;$indices=[];foreach(party_rule_data()['words'] as $index=>$word)if(party_rule_pass($different,$index))$indices[]=$index;$i=$indices[random_int(0,count($indices)-1)];$counter=['word'=>party_rule_data()['words'][$i],'pass'=>party_rule_pass($actual,$i)];}$state['guesses'][]=['text'=>party_rule_text($rule),'correct'=>$correct,'counter'=>$counter];if($correct||$state['wrong']>=6){$state['solved']=$correct;$state['finishedAt']=$now;$room['ready'][]=$id;}}
  else throw new RuntimeException('Unknown Rulebreakers action.',400);
 }elseif($room['game']==='alien-dictionary'){
  party_require($action==='translate','Choose a translation.',400);$question=$input['question']??null;$choice=$input['choice']??null;party_require(is_int($question)&&$question>=0&&$question<count($puzzle['challenges'])&&is_int($choice)&&$choice>=0&&$choice<6,'Choose one of the translations.',400);party_require(!isset($state['answers'][$question]),'That translation is already locked.');$correct=$puzzle['challenges'][$question]['correct'];$state['answers'][$question]=['choice'=>$choice,'correct'=>$choice===$correct,'correctIndex'=>$correct];if(count($state['answers'])===8){$state['solved']=count(array_filter($state['answers'],fn($a)=>$a['correct']))===8;$state['finishedAt']=$now;$room['ready'][]=$id;}
 }else{
  $budget=['easy'=>14,'classic'=>12,'expert'=>12][$room['difficulty']];
  if($action==='question'){$field=$input['field']??'';$values=$input['values']??null;party_require(is_string($field)&&isset($puzzle['fields'][$field])&&is_array($values)&&count($values)>=1&&count($values)<=4,'Choose a field and one to four values.',400);foreach($values as $value)party_require(is_string($value)&&in_array($value,$puzzle['fields'][$field]['values'],true),'Choose a listed value.',400);$values=array_values(array_unique($values));sort($values);$cost=count($values)===1?1:2;$signature=$field.'|'.implode('|',$values);party_require(!in_array($signature,array_column($state['history'],'signature'),true),'You already asked that question.');party_require($state['spent']+$cost<=$budget-2,'Save two credits to name your suspect.');$secret=null;foreach($puzzle['roster'] as $suspect)if($suspect['id']===$puzzle['secret'])$secret=$suspect;$yes=in_array($secret['traits'][$field],$values,true);$state['spent']+=$cost;$state['history'][]=['field'=>$field,'values'=>$values,'yes'=>$yes,'cost'=>$cost,'signature'=>$signature];foreach($puzzle['roster'] as $suspect)if(in_array($suspect['id'],$state['possible'],true)&&in_array($suspect['traits'][$field],$values,true)!==$yes)$state['possible']=array_values(array_diff($state['possible'],[$suspect['id']]));}
  elseif($action==='accuse'){$suspect=$input['suspect']??'';party_require(is_string($suspect)&&in_array($suspect,$state['possible'],true),'Choose a remaining suspect.',400);party_require($state['spent']+2<=$budget,'You need two credits to name a suspect.');$state['spent']+=2;$correct=$suspect===$puzzle['secret'];$state['guesses'][]=['suspect'=>$suspect,'correct'=>$correct];if(!$correct){$state['wrong']++;$state['possible']=array_values(array_diff($state['possible'],[$suspect]));}if($correct||$budget-$state['spent']<2){$state['solved']=$correct;$state['finishedAt']=$now;$room['ready'][]=$id;}}
  else throw new RuntimeException('Unknown Question Quest action.',400);
 }
 if(count($room['ready'])===count($room['participants']))party_deduction_reveal($room);
}
function party_deduction_view(array $room,string $id): array {
 $out=['serverTime'=>microtime(true),'difficulty'=>$room['difficulty']??'classic'];if($room['phase']==='lobby')return $out;
 $p=$room['deductionPuzzle'];$out['startsAt']=$room['deductionStarts'];$out['deadline']=$room['deadline'];$out['ready']=$room['ready'];$out['progress']=[];foreach($room['participants'] as $player)$out['progress'][]=['player'=>$player,'done'=>in_array($player,$room['ready'],true)];if(in_array($id,$room['participants'],true))$out['state']=$room['deductionStates'][$id];
 if($room['game']==='rulebreakers'){$out['examples']=$p['examples'];$out['atoms']=array_map(fn($a)=>array_intersect_key($a,array_flip(['id','text'])),array_values(party_rule_atoms()));if(in_array($room['phase'],['reveal','finished'],true)){$out['solution']=['rule'=>$p['rule'],'text'=>party_rule_text($p['rule'])];$out['puzzleKey']=$p['rule']['id'];}}
 elseif($room['game']==='alien-dictionary'){$out['examples']=array_map(fn($e)=>array_intersect_key($e,array_flip(['english','alien','icons'])),$p['examples']);$out['challenges']=array_map(function($c){$direction=$c['direction'];return ['direction'=>$direction,'prompt'=>$direction==='decode'?$c['alien']:$c['english'],'options'=>array_map(fn($o)=>$direction==='decode'?$o['english']:$o['alien'],$c['options'])];},$p['challenges']);$out['puzzleKey']=$p['key'];if(in_array($room['phase'],['reveal','finished'],true))$out['solution']=$p['language'];}
 else{$out['story']=$p['story'];$out['fields']=$p['fields'];$out['roster']=$p['roster'];$out['budget']=['easy'=>14,'classic'=>12,'expert'=>12][$room['difficulty']];$out['puzzleKey']=$p['story']['key'];if(in_array($room['phase'],['reveal','finished'],true))$out['solution']=$p['secret'];}
 return $out;
}
