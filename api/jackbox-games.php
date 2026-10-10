<?php
require_once __DIR__.'/jackbox-data.php';
function party_jb_game(string $game): bool {return in_array($game,['quiz-after-dark','punchline','fact-or-fiction','doodle-bluff','last-laugh-trivia','thread-battle'],true);}
function party_jb_fresh(array &$room,array $deck): array {
 $used=$room['jbUsed']??[];$fresh=array_values(array_filter($deck,fn($v)=>!in_array($v['key'],$used,true)));
 if(!$fresh){$room['jbUsed']=array_slice($used,-intdiv(count($deck),2));$fresh=array_values(array_filter($deck,fn($v)=>!in_array($v['key'],$room['jbUsed'],true)));}
 $chosen=$fresh[random_int(0,count($fresh)-1)];$room['jbUsed'][]=$chosen['key'];return $chosen;
}
function party_jb_reset(array &$room,array $input,array $recent): void {
 $rounds=(int)($input['rounds']??3);party_require(in_array($rounds,[1,3,5,10],true),'Choose 1, 3, 5, or 10 rounds.',400);
 $room['maxRounds']=$rounds;$room['jbUsed']=array_values(array_unique(array_merge($room['gameMemories'][$room['game']]??[],$recent)));$room['jbScrews']=[];$room['jbLives']=[];$room['jbGhostWins']=[];$room['jbEscape']=[];
 foreach(party_ids($room) as $id){$room['jbLives'][$id]=2;$room['jbEscape'][$id]=0;}
}
function party_jb_round(array &$room): void {
 $room['jbSubmissions']=[];$room['jbVotes']=[];$room['jbBluffs']=[];$room['jbDrawings']=[];$room['jbSlogans']=[];$room['jbDesigns']=[];$room['jbEntries']=[];$room['jbDangerAnswers']=[];$room['jbJammed']=[];$room['jbChallenges']=[];$room['jbStarted']=microtime(true);$room['deadline']=microtime(true)+90;
 $game=$room['game'];$ids=$room['participants'];
 if(in_array($game,['quiz-after-dark','last-laugh-trivia'],true)){
  $all=party_jb_quizzes();$facts=party_jb_facts();$deck=random_int(1,4)<=3?$facts:array_slice($all,count($facts));$q=party_jb_fresh($room,$deck);$texts=array_merge([$q['truth']],$q['fakes']);shuffle($texts);$q['options']=$texts;$q['correct']=array_search($q['truth'],$texts,true);$room['jbQuestion']=$q;$room['phase']='jb-trivia';$room['deadline']=microtime(true)+25;
  if($game==='last-laugh-trivia')foreach($ids as $id){$room['jbLives'][$id]=$room['jbLives'][$id]??2;$room['jbEscape'][$id]=$room['jbEscape'][$id]??0;}
 }elseif($game==='punchline'){
  $room['phase']='jb-writing';$room['jbPairs']=[];
  $count=count($ids)===2?2:count($ids);
  for($i=0;$i<$count;$i++){$p=party_jb_fresh($room,party_jb_comedy());$room['jbPairs'][]=['id'=>'p'.$i,'prompt'=>$p,'authors'=>[$ids[$i%count($ids)],$ids[($i+1)%count($ids)]]];}
 }elseif($game==='fact-or-fiction'){$room['jbQuestion']=party_jb_fresh($room,party_jb_facts());$room['phase']='jb-writing';$room['deadline']=microtime(true)+60;}
 else{
  $room['phase']='jb-drawing';$room['deadline']=microtime(true)+100;$room['jbDrawingPrompts']=[];
  foreach($ids as $id)$room['jbDrawingPrompts'][$id]=party_jb_fresh($room,party_jb_draw_prompts());
 }
}
function party_jb_text($value,int $max=160): string {party_require(is_string($value),'Write an answer.',400);$v=trim($value);party_require($v!==''&&strlen($v)<=$max,'Write a short answer (up to '.intdiv($max,2).' characters).',400);return $v;}
function party_jb_normal(string $v): string {return party_scatter_normalize($v);}
function party_jb_drawing($strokes): array {
 party_require(is_array($strokes)&&count($strokes)>0&&count($strokes)<=100,'Draw something before submitting.',400);$total=0;$out=[];
 foreach($strokes as $stroke){party_require(is_array($stroke)&&isset($stroke['points'])&&is_array($stroke['points'])&&count($stroke['points'])>0,'Invalid drawing.',400);$color=(string)($stroke['color']??'#292039');party_require(in_array($color,['#292039','#c45c69','#467f71','#467fa3','#ffffff'],true),'Choose a drawing color.',400);$width=$stroke['width']??4;party_require(is_int($width)&&$width>=2&&$width<=16,'Invalid brush.',400);$points=[];
 foreach($stroke['points'] as $p){party_require(is_array($p)&&count($p)===2&&is_int($p[0])&&is_int($p[1])&&$p[0]>=0&&$p[0]<=512&&$p[1]>=0&&$p[1]<=384,'Invalid drawing coordinates.',400);$points[]=$p;$total++;}
 party_require($total<=900,'This drawing is too detailed. Undo a stroke and try again.',400);$out[]=['color'=>$color,'width'=>$width,'points'=>$points];}
 return $out;
}
function party_jb_entry(string $id,string $prompt,array $options,?array $drawing=null): array {return ['id'=>$id,'prompt'=>$prompt,'options'=>$options,'drawing'=>$drawing];}
function party_jb_build_votes(array &$room): void {
 $game=$room['game'];$entries=[];
 if($game==='punchline')foreach($room['jbPairs'] as $pair){$options=[];foreach($pair['authors'] as $author){$text=$room['jbSubmissions'][$author][$pair['id']]??null;if($text!==null)$options[]=['text'=>$text,'author'=>(string)$author];}
  if(count($room['participants'])===2)$options[]=['text'=>['I would like to unsubscribe from this experience.','This seems like a problem for future me.','Sorry, my emotional support printer ate the plan.'][random_int(0,2)],'author'=>null];
  if($options)$entries[]=party_jb_entry($pair['id'],$pair['prompt']['text'],$options);
 }
 elseif($game==='fact-or-fiction'){$q=$room['jbQuestion'];$options=[['text'=>$q['truth'],'truth'=>true,'authors'=>[]]];foreach($q['fakes'] as $fake)$options[]=['text'=>$fake,'truth'=>false,'authors'=>[]];
  foreach($room['jbSubmissions'] as $author=>$text){$found=false;foreach($options as &$option)if(party_jb_normal($option['text'])===party_jb_normal($text)){$option['authors'][]=(string)$author;$found=true;break;}unset($option);if(!$found)$options[]=['text'=>$text,'truth'=>false,'authors'=>[(string)$author]];}
  $entries[]=party_jb_entry('fact',$q['question'],$options);
 }
 elseif($game==='doodle-bluff')foreach($room['jbDrawings'] as $owner=>$drawing){$prompt=$room['jbDrawingPrompts'][$owner]['text'];$options=[['text'=>$prompt,'truth'=>true,'authors'=>[]]];
  foreach($room['jbBluffs'][$owner]??[] as $author=>$text){$found=false;foreach($options as &$o)if(party_jb_normal($o['text'])===party_jb_normal($text)){$o['authors'][]=(string)$author;$found=true;break;}unset($o);if(!$found)$options[]=['text'=>$text,'truth'=>false,'authors'=>[(string)$author]];}
  $deck=party_jb_draw_prompts();while(count($options)<4){$fake=$deck[random_int(0,count($deck)-1)]['text'];if(!in_array($fake,array_column($options,'text'),true))$options[]=['text'=>$fake,'truth'=>false,'authors'=>[]];}
  $entry=party_jb_entry('d'.array_search((string)$owner,array_map('strval',array_keys($room['jbDrawings'])),true),'What was the original drawing prompt?',$options,$drawing);$entry['owner']=(string)$owner;$entries[]=$entry;
 }
 else{
  $options=[];foreach($room['jbDesigns'] as $author=>$design)$options[]=['text'=>$room['jbSlogans'][$design['slogan']]['text'],'drawing'=>$room['jbDrawings'][$design['drawing']],'author'=>(string)$author,'artist'=>$design['drawing'],'writer'=>$room['jbSlogans'][$design['slogan']]['author']];
  if(count($room['participants'])===2&&$options){$first=$options[0];$options[]=['text'=>'NOT QUALIFIED. STILL HERE.','drawing'=>$first['drawing'],'author'=>null,'artist'=>null,'writer'=>null];}
  if($options)$entries[]=party_jb_entry('shirts','Which shirt would you actually wear?',$options);
 }
 foreach($entries as &$entry){shuffle($entry['options']);foreach($entry['options'] as $i=>&$option)$option['id']='o'.$i;unset($option);}unset($entry);
 $room['jbEntries']=$entries;$room['phase']='jb-voting';$room['deadline']=microtime(true)+max(35,count($entries)*18);if(!$entries)party_jb_reveal($room);
}
function party_jb_eligible(array $entry,string $id): array {
 if(($entry['owner']??null)===$id)return [];
 return array_values(array_filter($entry['options'],fn($o)=>($o['author']??null)!==$id&&!in_array($id,$o['authors']??[],true)));
}
function party_jb_all_votes(array $room): bool {foreach($room['jbEntries'] as $entry)foreach($room['participants'] as $id)if(party_jb_eligible($entry,$id)&&!isset($room['jbVotes'][$entry['id']][$id]))return false;return true;}
function party_jb_reveal(array &$room): void {
 if($room['phase']==='reveal')return;$scores=array_fill_keys($room['participants'],0);$details=[];
 if(in_array($room['game'],['quiz-after-dark','last-laugh-trivia'],true)){
  $q=$room['jbQuestion'];foreach($room['participants'] as $id){$answer=$room['jbSubmissions'][$id]??null;$correct=$answer!==null&&$answer['choice']===$q['correct'];$points=$correct?100+(int)max(0,100-4*($answer['at']-$room['jbStarted'])):($room['game']==='quiz-after-dark'?-50:0);$scores[$id]=$points;
   if($room['game']==='last-laugh-trivia'){
    $alive=($room['jbLives'][$id]??2)>0;
    if(!$correct&&$alive&&!($room['jbDangerAnswers'][$id]['correct']??false))$room['jbLives'][$id]--;
    if($correct&&!$alive){$room['jbGhostWins'][$id]=($room['jbGhostWins'][$id]??0)+1;if($room['jbGhostWins'][$id]>=2){$room['jbLives'][$id]=1;$room['jbGhostWins'][$id]=0;}}
    $room['jbEscape'][$id]=($room['jbEscape'][$id]??0)+($correct?2:0)+(($room['jbDangerAnswers'][$id]['correct']??false)?1:0);
   }
   $details[$id]=['correct'=>$correct,'choice'=>$answer['choice']??null,'survived'=>$room['jbDangerAnswers'][$id]['correct']??null];
  }
  $room['result']=['scores'=>$scores,'answers'=>$details,'correct'=>$q['correct'],'truth'=>$q['truth'],'explanation'=>$q['explanation'],'escape'=>$room['jbEscape']??[]];
 }else{
  foreach($room['jbEntries'] as $entry){$counts=[];foreach($room['jbVotes'][$entry['id']]??[] as $voter=>$choice){$counts[$choice]=($counts[$choice]??0)+1;foreach($entry['options'] as $option)if($option['id']===$choice){
    if($option['truth']??false){$scores[$voter]+=100;if(isset($entry['owner']))$scores[$entry['owner']]+=25;}
    foreach($option['authors']??[] as $author)$scores[$author]+=50;
    if(isset($option['author']))$scores[$option['author']]+=100;
    foreach(['artist','writer'] as $credit)if(isset($option[$credit]))$scores[$option[$credit]]+=25;
   }}
   if($room['game']==='punchline'&&$counts){$high=max($counts);$winners=array_keys(array_filter($counts,fn($v)=>$v===$high));foreach($entry['options'] as $o)if(in_array($o['id'],$winners,true)&&isset($o['author']))$scores[$o['author']]+=count($winners)===1?50:25;}
   $details[$entry['id']]=$counts;
  }
  $room['result']=['scores'=>$scores,'votes'=>$details,'entries'=>$room['jbEntries']];if($room['game']==='fact-or-fiction')$room['result']['explanation']=$room['jbQuestion']['explanation'];
 }
 foreach($room['players'] as &$p)$p['score']+=$scores[$p['id']]??0;unset($p);$room['phase']='reveal';
}
function party_jb_advance(array &$room): void {
 $game=$room['game'];$phase=$room['phase'];
 if($phase==='jb-trivia'&&$game==='last-laugh-trivia'){
  $room['jbChallenges']=[];foreach($room['participants'] as $id)if(($room['jbLives'][$id]??0)>0&&($room['jbSubmissions'][$id]['choice']??-1)!==$room['jbQuestion']['correct']){
   $type=['math','memory','order'][random_int(0,2)];
   if($type==='memory'){$code=(string)random_int(1000,9999);$options=[$code];while(count($options)<4){$n=(string)random_int(1000,9999);if(!in_array($n,$options,true))$options[]=$n;}shuffle($options);$challenge=['text'=>'Memorize this code. It disappears in four seconds.','preview'=>$code,'hideAt'=>microtime(true)+4,'options'=>$options,'correct'=>array_search($code,$options,true)];}
   elseif($type==='math'){$a=random_int(12,40);$b=random_int(2,9);$correct=$a+$b;$options=[$correct,$correct+1,$correct-1,$correct+$b];shuffle($options);$challenge=['text'=>"Unlock the door: $a + $b = ?",'options'=>array_map('strval',$options),'correct'=>array_search($correct,$options,true)];}
   else{$nums=[];while(count($nums)<4){$n=random_int(10,99);if(!in_array($n,$nums,true))$nums[]=$n;}$challenge=['text'=>'Pick the smallest number before the trap shuts.','options'=>array_map('strval',$nums),'correct'=>array_search(min($nums),$nums,true)];}
   $room['jbChallenges'][$id]=$challenge;
  }
  if($room['jbChallenges']){$room['phase']='jb-danger';$room['deadline']=microtime(true)+18;return;}
 }
 if(in_array($phase,['jb-trivia','jb-danger','jb-voting'],true)){party_jb_reveal($room);return;}
 if($phase==='jb-drawing'){
  if(!$room['jbDrawings']){party_jb_reveal($room);return;}
  if($game==='thread-battle'){$room['phase']='jb-slogans';$room['deadline']=microtime(true)+60;return;}
  $room['phase']='jb-bluffing';$room['deadline']=microtime(true)+max(50,count($room['jbDrawings'])*15);return;
 }
 if($phase==='jb-slogans'){
  if(!$room['jbSlogans']){$room['jbSlogans']['house']=['text'=>'ALL VIBES. NO PLAN.','author'=>null];}
  $room['phase']='jb-designing';$room['deadline']=microtime(true)+60;return;
 }
 party_jb_build_votes($room);
}
function party_jb_all_submitted(array $room): bool {
 foreach($room['participants'] as $id){switch($room['phase']){
  case 'jb-trivia':if(!isset($room['jbSubmissions'][$id]))return false;break;
  case 'jb-danger':if(isset($room['jbChallenges'][$id])&&!isset($room['jbDangerAnswers'][$id]))return false;break;
  case 'jb-drawing':if(!isset($room['jbDrawings'][$id]))return false;break;
  case 'jb-writing':if($room['game']==='punchline'){foreach($room['jbPairs'] as $p)if(in_array($id,$p['authors'],true)&&!isset($room['jbSubmissions'][$id][$p['id']]))return false;}elseif(!isset($room['jbSubmissions'][$id]))return false;break;
  case 'jb-slogans':if(!in_array($id,array_column($room['jbSlogans'],'author'),true))return false;break;
  case 'jb-designing':if(!isset($room['jbDesigns'][$id]))return false;break;
  case 'jb-bluffing':foreach($room['jbDrawings'] as $owner=>$d)if((string)$owner!==$id&&!isset($room['jbBluffs'][$owner][$id]))return false;break;
  default:return false;
 }}return true;
}
function party_jb_action(array &$room,string $id,string $action,array $input): void {
 $phase=$room['phase'];party_require(str_starts_with($phase,'jb-'),'This round has finished.');party_require(microtime(true)<$room['deadline'],'The timer has ended.');
 if($action==='finish_stage'){party_require($id===$room['host'],'Only the host can close a stage.',403);party_jb_advance($room);return;}
 if($action==='screw'){
  party_require($room['game']==='quiz-after-dark'&&$phase==='jb-trivia','Screws are only available during this quiz.');party_require(!isset($room['jbScrews'][$id]),'Your screw has already been used.');$target=(string)($input['target']??'');party_require($target!==$id&&in_array($target,$room['participants'],true)&&!isset($room['jbSubmissions'][$target])&&!isset($room['jbJammed'][$target]),'Choose an unanswered opponent.',400);$room['jbScrews'][$id]=true;$room['jbJammed'][$target]=microtime(true)+8;return;
 }
 if($action==='answer'){
  party_require($phase==='jb-trivia','Trivia answers are closed.');party_require(!isset($room['jbSubmissions'][$id]),'Your answer is locked.');party_require(microtime(true)<($room['jbJammed'][$id]??PHP_INT_MAX),'Your screw timer ended.');$choice=$input['choice']??null;party_require(is_int($choice)&&$choice>=0&&$choice<4,'Choose an answer.',400);$room['jbSubmissions'][$id]=['choice'=>$choice,'at'=>microtime(true)];
 }elseif($action==='danger'){
  party_require($phase==='jb-danger'&&isset($room['jbChallenges'][$id]),'This trap is not yours.');party_require(!isset($room['jbDangerAnswers'][$id]),'Your trap answer is locked.');$c=$room['jbChallenges'][$id];party_require(!isset($c['hideAt'])||microtime(true)>=$c['hideAt'],'Wait for the code to disappear.');$choice=$input['choice']??null;party_require(is_int($choice)&&$choice>=0&&$choice<4,'Choose an answer.',400);$room['jbDangerAnswers'][$id]=['choice'=>$choice,'correct'=>$choice===$c['correct']];
 }elseif($action==='write'){
  party_require($phase==='jb-writing','Writing is closed.');
  if($room['game']==='punchline'){$values=$input['answers']??null;party_require(is_array($values),'Send your responses.',400);$out=[];foreach($room['jbPairs'] as $pair)if(in_array($id,$pair['authors'],true))$out[$pair['id']]=party_jb_text($values[$pair['id']]??null,240);party_require(!isset($room['jbSubmissions'][$id]),'Your responses are locked.');$room['jbSubmissions'][$id]=$out;}
  else{$text=party_jb_text($input['text']??null);party_require(party_jb_normal($text)!==party_jb_normal($room['jbQuestion']['truth']),'That is the real answer. Invent a believable lie instead.',400);party_require(!isset($room['jbSubmissions'][$id]),'Your bluff is locked.');$room['jbSubmissions'][$id]=$text;}
 }elseif($action==='draw'){
  party_require($phase==='jb-drawing','Drawing is closed.');party_require(!isset($room['jbDrawings'][$id]),'Your drawing is locked.');$room['jbDrawings'][$id]=party_jb_drawing($input['strokes']??null);
 }elseif($action==='bluff'){
  party_require($phase==='jb-bluffing','Labels are closed.');$alias=(string)($input['picture']??'');$owners=array_map('strval',array_keys($room['jbDrawings']));$picture=preg_match('/^d([0-9]+)$/',$alias,$matches)?($owners[(int)$matches[1]]??''):'';party_require($picture!==$id&&isset($room['jbDrawings'][$picture]),'Choose another player’s drawing.',400);$text=party_jb_text($input['text']??null);party_require(party_jb_normal($text)!==party_jb_normal($room['jbDrawingPrompts'][$picture]['text']),'You found the original prompt. Try a fake label instead.',400);party_require(!isset($room['jbBluffs'][$picture][$id]),'That label is locked.');$room['jbBluffs'][$picture][$id]=$text;
 }elseif($action==='slogans'){
  party_require($phase==='jb-slogans','Slogans are closed.');party_require(!in_array($id,array_column($room['jbSlogans'],'author'),true),'Your slogans are locked.');$texts=$input['texts']??null;party_require(is_array($texts)&&count($texts)===2,'Write two slogans.',400);foreach($texts as $i=>$text)$room['jbSlogans'][$id.':'.$i]=['text'=>party_jb_text($text),'author'=>$id];
 }elseif($action==='design'){
  party_require($phase==='jb-designing','Designs are closed.');$d=(string)($input['drawing']??'');$s=(string)($input['slogan']??'');$drawing=preg_match('/^a([0-9]+)$/',$d,$m)?(array_map('strval',array_keys($room['jbDrawings']))[(int)$m[1]]??''):'';$slogan=preg_match('/^s([0-9]+)$/',$s,$m)?(array_keys($room['jbSlogans'])[(int)$m[1]]??''):'';party_require(isset($room['jbDrawings'][$drawing],$room['jbSlogans'][$slogan]),'Choose an artwork and slogan.',400);party_require(!isset($room['jbDesigns'][$id]),'Your shirt is locked.');$room['jbDesigns'][$id]=['drawing'=>$drawing,'slogan'=>$slogan];
 }elseif($action==='vote'){
  party_require($phase==='jb-voting','Voting is closed.');$entryId=(string)($input['entry']??'');$entry=null;foreach($room['jbEntries'] as $e)if($e['id']===$entryId)$entry=$e;party_require((bool)$entry,'Choose a matchup.',400);party_require(!isset($room['jbVotes'][$entryId][$id]),'That vote is locked.');$choice=(string)($input['choice']??'');party_require(in_array($choice,array_column(party_jb_eligible($entry,$id),'id'),true),'Vote for another player’s answer.',400);$room['jbVotes'][$entryId][$id]=$choice;if(party_jb_all_votes($room))party_jb_reveal($room);return;
 }else{party_require(false,'Unknown game action.',400);}
 if(party_jb_all_submitted($room))party_jb_advance($room);
}
function party_jb_tick(array &$room): void {
 if(str_starts_with($room['phase'],'jb-')&&microtime(true)>=$room['deadline']){party_jb_advance($room);$room['version']++;}
}
function party_jb_view(array $room,string $id): array {
 $p=['deadline'=>$room['deadline']??null,'serverTime'=>microtime(true),'jbReady'=>[],'jbUsedKeys'=>($room['phase']==='reveal'?($room['jbUsed']??[]):[])];$phase=$room['phase'];if(in_array($phase,['lobby','finished'],true)){unset($p['jbUsedKeys']);$p['escape']=$room['jbEscape']??[];$p['lives']=$room['jbLives']??[];return $p;}
 if(in_array($room['game'],['quiz-after-dark','last-laugh-trivia'],true)){
  $q=$room['jbQuestion'];$p['question']=['text'=>str_replace('___','…',$q['question']),'options'=>$q['options']];$p['yourAnswer']=$room['jbSubmissions'][$id]['choice']??null;$p['jbReady']=array_map('strval',array_keys($room['jbSubmissions']));$p['screwUsed']=isset($room['jbScrews'][$id]);$p['personalDeadline']=$room['jbJammed'][$id]??null;$p['lives']=$room['jbLives'];$p['escape']=$room['jbEscape'];
  if($phase==='jb-danger'&&isset($room['jbChallenges'][$id])){$c=$room['jbChallenges'][$id];unset($c['correct']);if(isset($c['hideAt'])&&microtime(true)>=$c['hideAt'])unset($c['preview']);$p['challenge']=$c;$p['yourDanger']=$room['jbDangerAnswers'][$id]['choice']??null;}
 }elseif($room['game']==='punchline'){$p['prompts']=array_values(array_map(fn($e)=>['id'=>$e['id'],'text'=>$e['prompt']['text']],array_filter($room['jbPairs'],fn($e)=>in_array($id,$e['authors'],true))));$p['yourWriting']=$room['jbSubmissions'][$id]??null;$p['jbReady']=array_map('strval',array_keys($room['jbSubmissions']));}
 elseif($room['game']==='fact-or-fiction'){$p['question']=['text'=>$room['jbQuestion']['question']];$p['yourWriting']=$room['jbSubmissions'][$id]??null;$p['jbReady']=array_map('strval',array_keys($room['jbSubmissions']));}
 else{
  if($phase==='jb-drawing'){$p['drawingPrompt']=$room['jbDrawingPrompts'][$id]['text']??null;$p['yourDrawing']=$room['jbDrawings'][$id]??null;$p['jbReady']=array_map('strval',array_keys($room['jbDrawings']));}
  elseif($phase==='jb-bluffing'){$p['pictures']=[];foreach($room['jbDrawings'] as $owner=>$d)if((string)$owner!==$id)$p['pictures'][]=['id'=>'d'.array_search((string)$owner,array_map('strval',array_keys($room['jbDrawings'])),true),'strokes'=>$d,'yourBluff'=>$room['jbBluffs'][$owner][$id]??null];}
  elseif(in_array($phase,['jb-slogans','jb-designing'],true)){$p['yourSlogans']=array_values(array_column(array_filter($room['jbSlogans'],fn($s)=>$s['author']===$id),'text'));if($phase==='jb-designing'){$p['artworks']=[];foreach($room['jbDrawings'] as $owner=>$d)$p['artworks'][]=['id'=>'a'.array_search((string)$owner,array_map('strval',array_keys($room['jbDrawings'])),true),'strokes'=>$d];$p['slogans']=[];foreach($room['jbSlogans'] as $key=>$s)$p['slogans'][]=['id'=>'s'.array_search($key,array_keys($room['jbSlogans']),true),'text'=>$s['text']];$p['yourDesign']=$room['jbDesigns'][$id]??null;}}
 }
 if($phase==='jb-voting'){
  $p['entries']=[];foreach($room['jbEntries'] as $entry){$public=['id'=>$entry['id'],'prompt'=>$entry['prompt'],'drawing'=>$entry['drawing'],'eligible'=>array_column(party_jb_eligible($entry,$id),'id'),'yourVote'=>$room['jbVotes'][$entry['id']][$id]??null,'options'=>[]];foreach($entry['options'] as $o)$public['options'][]=['id'=>$o['id'],'text'=>$o['text'],'drawing'=>$o['drawing']??null];$p['entries'][]=$public;}
 }
 return $p;
}
