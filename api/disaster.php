<?php
function party_disaster_options(array $labels,string $correct): array {
    $labels=array_values(array_unique($labels));if(!in_array($correct,$labels,true))$labels[]=$correct;shuffle($labels);$choices=[];$expected='';
    foreach($labels as $label){$id=bin2hex(random_bytes(5));$choices[]=['id'=>$id,'label'=>$label];if($label===$correct)$expected=$id;}
    return ['choices'=>$choices,'expected'=>$expected,'input'=>'select'];
}
function party_disaster_task(array &$room): array {
    $types=array_values(array_diff(['flight','file','email','calendar','shop'],[$room['lastTaskType']]));
    for($attempt=0;$attempt<30;$attempt++){
        $type=$types[random_int(0,count($types)-1)];$pick=fn($list)=>$list[random_int(0,count($list)-1)];$names=['Sam Rivera','Avery Chen','Jordan Brooks','Morgan Patel','Riley Kim','Casey Lewis','Jamie Park','Alex Morgan','Taylor Quinn','Drew Bailey','Parker Ellis','Robin Shah'];$name=$pick($names);
        if($type==='flight'){
            $cities=['Lisbon','Oslo','Tokyo','Berlin','Lima','Paris','Seoul','Rome','Athens','Madrid','Cairo','Dublin','Nairobi','Toronto','Helsinki','Prague','Porto','Vienna','Bangkok','Montreal','Stockholm','Sydney','Tallinn','Reykjavik'];$city=$pick($cities);$date=$pick(['Mon 12','Tue 13','Wed 14','Thu 15','Fri 16','Sat 17']);$time=$pick(['06:40','08:15','10:30','12:55','14:20','16:45','18:10','20:35']);
            $goal="Book $name a flight to $city on $date at $time, Economy.";$app='TripDesk';$destination=array_slice($cities,0,7);$destination[]=$city;
            $s1=['title'=>'Choose the destination','button'=>'Search flights']+party_disaster_options($destination,$city);$routes=["$date · $time · Economy","$date · $time · Business","$date · 23:50 · Economy","Sun 18 · $time · Economy"];
            $s2=['title'=>'Choose the exact departure and fare','button'=>'Select flight']+party_disaster_options($routes,$routes[0]);$s3=['title'=>'Enter the passenger’s full name','input'=>'text','expected'=>$name,'button'=>'Confirm booking'];
        }elseif($type==='file'){
            $project=$pick(['Aurora','Juniper','Orbit','Maple','Nimbus','Cobalt','Mango','Lantern','Velvet','Quartz','Tundra','Coral','Mosaic','Pebble','Comet','Willow']);$folder=$pick(['Projects','Archive','Research','Shared']);$file=$pick(['launch-notes.txt','handover.txt','vault-key.txt','briefing.txt','read-first.txt','daily-log.txt','release.txt','secret-memo.txt']);$code=$pick(['violet','golden','silver','sleepy','brave','spicy','lucky','quiet']).'-'.$pick(['goose','comet','otter','tulip','panda','waffle','orbit','cloud']).'-'.random_int(10,99);
            $goal="Find $folder / $project / $file and submit its access phrase.";$app='File Cabinet';$s1=['title'=>'Open the right folder','button'=>'Open folder']+party_disaster_options(["$folder / $project","$folder / {$project}-old","Downloads / $project","Personal / $project"],"$folder / $project");
            $s2=['title'=>'Open the requested file','button'=>'Read file']+party_disaster_options([$file,'budget.csv','photo.png','meeting-draft.txt','read-me-later.txt'],$file);$s3=['title'=>'Read the memo and enter the access phrase','body'=>"PRIVATE MEMO\nProject: $project\nAccess phrase: $code\nDo not use the archived phrase: sleepy-goose-00.",'input'=>'text','expected'=>$code,'button'=>'Submit access phrase'];
        }elseif($type==='email'){
            $recipient=$pick(['Finance','Design','Support','Operations','Travel','Facilities','Research','Marketing','Editorial','People']);$attachment=$pick(['budget-final.pdf','schedule.pdf','floorplan.png','invoice.pdf','itinerary.pdf','brief.pdf','proposal.pdf','menu.pdf','checklist.pdf','launch-plan.pdf']);$subject=$pick(['Ready for review','Approval needed','Final handover','For your records','Next steps','Please confirm','Updated plan','Good to go','Review by Friday','Quick follow-up']);$app='Mailroom';
            $goal="Email $recipient with $attachment attached and the subject “$subject”.";$s1=['title'=>'Choose the recipient','button'=>'Compose message']+party_disaster_options([$recipient,'All Staff','External Partners','Receipts','No Reply'],$recipient);$s2=['title'=>'Choose the attachment','button'=>'Attach file']+party_disaster_options([$attachment,'draft-not-final.pdf','holiday-photo.png','notes-old.txt','wrong-invoice.pdf'],$attachment);$s3=['title'=>'Enter the exact subject line','input'=>'text','expected'=>$subject,'button'=>'Send email'];
        }elseif($type==='calendar'){
            $day=$pick(['Monday','Tuesday','Wednesday','Thursday','Friday']);$time=$pick(['09:00','10:30','11:15','13:00','14:30','15:45','16:15']);$place=$pick(['Cedar','Maple','Pine','Willow','Birch','Oak','Juniper','Elm']);$app='DayPlan';$goal="Book a meeting on $day at $time in the $place room.";
            $s1=['title'=>'Choose the day','button'=>'View available times']+party_disaster_options(['Monday','Tuesday','Wednesday','Thursday','Friday'],$day);$s2=['title'=>'Choose the start time','button'=>'Choose room']+party_disaster_options(['09:00','10:30','11:15','13:00','14:30','15:45','16:15'],$time);$s3=['title'=>'Choose the meeting room','button'=>'Create meeting']+party_disaster_options(['Cedar','Maple','Pine','Willow','Birch','Oak','Juniper','Elm'],$place);
        }else{
            $product=$pick(['Desk lamp','USB hub','Notebook','Coffee mug','Mouse pad','Water bottle','Plant pot','Keyboard','Cable organizer','Headphones','Pen set','Mini fan','Desk clock','Phone stand','Stapler','Sticky notes']);$quantity=(string)random_int(1,5);$delivery=$pick(['Standard','Express','Pickup']);$app='Tiny Store';$goal="Order $quantity × $product with $delivery delivery.";
            $s1=['title'=>'Choose the product','button'=>'Add to basket']+party_disaster_options([$product,'Extra-long ruler','Glitter keyboard cover','Novelty doorbell','Rubber goose'],$product);$s2=['title'=>'Choose the quantity','button'=>'Checkout']+party_disaster_options(['1','2','3','4','5'],$quantity);$s3=['title'=>'Choose the delivery method','button'=>'Place order']+party_disaster_options(['Standard','Express','Pickup'],$delivery);
        }
        $steps=[['title'=>'Open the right application','button'=>'Open application']+party_disaster_options(['TripDesk','File Cabinet','Mailroom','DayPlan','Tiny Store'],$app),$s1,$s2,$s3];
        if(!in_array($goal,$room['usedNewPrompts'],true))break;
    }
    $room['usedNewPrompts'][]=$goal;$room['lastTaskType']=$type;return ['type'=>$type,'app'=>$app,'text'=>$goal,'steps'=>$steps];
}
function party_disaster_live(array $room): array {return array_values(array_filter($room['traps']??[],fn($t)=>!$t['dismissed']&&$t['expires']>time()));}
function party_disaster_finish(array &$room,bool $success): void {
    if($room['phase']!=='desktop')return;$remaining=max(0,$room['deadline']-time());
    if($success)$room['roundScores'][$room['active']]=10+intdiv($remaining,10);else foreach($room['channels'] as $id=>$channels)$room['roundScores'][$id]+=3;
    foreach($room['players'] as &$player)$player['score']+=$room['roundScores'][$player['id']]??0;unset($player);
    $room['result']=['success'=>$success,'remaining'=>$remaining,'scores'=>$room['roundScores'],'stats'=>$room['trapStats'],'channels'=>$room['channels'],'reason'=>$success?'The task survived the desktop.':'Time ran out. The saboteurs win the round.'];$room['phase']='reveal';
}
function party_disaster_action(array &$room,string $id,string $action,array $input): void {
    party_require($room['phase']==='desktop'&&time()<$room['deadline'],'This desktop round has ended.');$active=$id===$room['active'];
    if($action==='trap'){
        party_require(!$active&&isset($room['channels'][$id]),'Only saboteurs deploy traps.',403);$kind=(string)($input['kind']??'');party_require(in_array($kind,$room['channels'][$id],true),'You do not control that part of the interface.',403);
        party_require(time()>=($room['trapCooldown'][$id]??0),'Let your gadget recharge.');party_require($room['trapStats'][$id]['deployed']<10,'Ten traps per saboteur per round.');party_require(count(party_disaster_live($room))<3,'Three traps are already live. Wait or use a different moment.');
        $deck=party_disaster_traps()[$kind];$index=$input['index']??null;party_require(is_int($index)&&isset($deck[$index]),'Choose a gadget message.',400);
        $room['traps'][]=['id'=>bin2hex(random_bytes(6)),'owner'=>$id,'kind'=>$kind,'text'=>$deck[$index],'expires'=>time()+12,'seen'=>false,'hit'=>false,'dismissed'=>false,'shift'=>random_int(0,1)?1:-1];$room['trapCooldown'][$id]=time()+7;$room['trapStats'][$id]['deployed']++;return;
    }
    party_require($active,'Only the active player can use this desktop.',403);
    if($action==='seen'){
        $ids=is_array($input['traps']??null)?array_slice($input['traps'],0,3):[];
        foreach($room['traps'] as &$trap)if(in_array($trap['id'],$ids,true)&&!$trap['seen']&&!$trap['dismissed']&&$trap['expires']>time()){$trap['seen']=true;$owner=$trap['owner'];$room['trapStats'][$owner]['seen']++;if($room['trapStats'][$owner]['seen']<=4)$room['roundScores'][$owner]++;}unset($trap);return;
    }
    if($action==='dismiss'||$action==='hit'){
        $trapId=(string)($input['trap']??'');$found=false;foreach($room['traps'] as &$trap)if($trap['id']===$trapId){party_require(!$trap['dismissed']&&$trap['expires']>time(),'That trap has expired.');$found=true;$trap['dismissed']=true;
            if($action==='hit'&&!$trap['hit']){$trap['hit']=true;$room['trapStats'][$trap['owner']]['hits']++;$room['roundScores'][$trap['owner']]+=2;$room['deadline']-=4;if($trap['kind']==='error')$room['step']=max(0,$room['step']-1);}
            break;}unset($trap);party_require($found,'That trap does not exist.',400);return;
    }
    if($action==='step'){
        $index=$input['step']??null;party_require(is_int($index)&&$index===$room['step'],'The workflow moved on. Check the current step.');$value=trim((string)($input['value']??''));$step=$room['task']['steps'][$index];
        $correct=$step['input']==='text'?strcasecmp($value,$step['expected'])===0:$value===$step['expected'];party_require($correct,'That does not match the mission. Check the details at the top.',400);$room['step']++;if($room['step']>=count($room['task']['steps']))party_disaster_finish($room,true);return;
    }
    if($action==='back'){$room['step']=max(0,$room['step']-1);return;}
    throw new RuntimeException('Unknown desktop action.',400);
}
function party_disaster_view(array $room,string $id): array {
    $out=['active'=>$room['active']??null,'task'=>null,'step'=>$room['step']??0,'traps'=>[]];
    if(isset($room['task'])){
        $out['task']=['type'=>$room['task']['type'],'app'=>$room['task']['app'],'text'=>$room['task']['text'],'steps'=>count($room['task']['steps'])];
        if(isset($room['task']['steps'][$room['step']])){$step=$room['task']['steps'][$room['step']];unset($step['expected']);$out['task']['current']=$step;}
    }
    if($room['phase']==='desktop')foreach(party_disaster_live($room) as $trap){unset($trap['owner']);$out['traps'][]=$trap;}
    if(isset($room['channels'][$id])){$out['channels']=$room['channels'][$id];$out['gadgetDeck']=array_intersect_key(party_disaster_traps(),array_flip($out['channels']));$out['cooldown']=$room['trapCooldown'][$id]??0;$out['yourStats']=$room['trapStats'][$id];$out['roundPoints']=$room['roundScores'][$id];}
    return $out;
}
