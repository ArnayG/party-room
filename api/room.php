<?php
declare(strict_types=1);
require_once __DIR__.'/engine.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('display_errors','0');
function party_reply(array $body,int $status=200): void { http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit; }
if($_SERVER['REQUEST_METHOD']!=='POST')party_reply(['error'=>'Send a JSON POST request.'],405);
$raw=file_get_contents('php://input',false,null,0,16385);if(strlen($raw)>16384)party_reply(['error'=>'Request is too large.'],413);
$input=json_decode($raw,true);if(!is_array($input))party_reply(['error'=>'Invalid JSON request.'],400);
$action=(string)($input['action']??'');
$documentRoot=rtrim($_SERVER['DOCUMENT_ROOT']??dirname(__DIR__),'/');$publicPosition=strpos($documentRoot,'/public_html');
$home=$publicPosition!==false?substr($documentRoot,0,$publicPosition):dirname($documentRoot);
$data=getenv('PARTY_ROOM_DATA_DIR')?:$home.'/party-room-data';
if(!is_dir($data)&&!@mkdir($data,0700,true))party_reply(['error'=>'Hosting cannot create room storage. Check the PHP setup in UPLOAD.txt.'],503);
if(!is_writable($data))party_reply(['error'=>'Hosting cannot write room storage. Check the PHP setup in UPLOAD.txt.'],503);
$handle=null;
try{
    if($action==='create'){
        $game=(string)($input['game']??'');party_require(in_array($game,['wavelength','imposter','scattergories','hivemind','apples','humanity','desktop-disaster','word-circuit','sudoku-race'],true),'Choose a valid game.',400);$player=party_player((string)($input['name']??''));
        $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for($attempt=0;$attempt<15;$attempt++){$code='';for($i=0;$i<5;$i++)$code.=$alphabet[random_int(0,strlen($alphabet)-1)];$file=$data.'/'.$code.'.json';$handle=@fopen($file,'x+');if($handle)break;}
        party_require((bool)$handle,'Rooms are busy. Try again.',503);flock($handle,LOCK_EX);
        $room=['code'=>$code,'game'=>$game,'host'=>$player['id'],'players'=>[$player],'phase'=>'lobby','round'=>0,'maxRounds'=>($game==='wavelength'?8:($game==='imposter'?5:6)),'participants'=>[],'result'=>null,'version'=>1,'updated'=>time(),'created'=>time(),'clues'=>[],'votes'=>[],'guesses'=>[],'usedWords'=>[],'usedSpectrums'=>[],'totalScore'=>0,'history'=>[]];
    }else{
        $code=strtoupper(trim((string)($input['code']??'')));party_require((bool)preg_match('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{5}$/',$code),'Enter a five-character room code.',400);
        $file=$data.'/'.$code.'.json';party_require(is_file($file),'That room does not exist or has expired.',404);$handle=fopen($file,'r+');party_require((bool)$handle,'Room storage is unavailable.',503);flock($handle,LOCK_EX);$room=json_decode(stream_get_contents($handle),true);
        party_require(is_array($room)&&time()-$room['updated']<21600,'That room has expired. Create a new one.',404);
        if($action==='join'){
            party_require(($input['game']??'')===$room['game'],'This code belongs to the other game. Open that game to join.',400);
            party_require(count(array_filter($room['players'],fn($p)=>!$p['left']))<16,'This party is full (16 players).');$player=party_player((string)($input['name']??''));$room['players'][]=$player;$room['version']++;
        }else{
            $token=(string)($input['token']??'');$player=null;foreach($room['players'] as &$entry)if(!$entry['left']&&hash_equals($entry['token'],$token)){$entry['seen']=time();$player=$entry;break;}unset($entry);
            party_require((bool)$player,'Your session has ended. Join the party again.',401);
            if($action!=='state'){party_apply($room,$player['id'],$action,$input);$room['version']++;}
        }
        $host=party_member($room,$room['host']);if(!$host||$host['left']||time()-$host['seen']>90){$available=party_active($room);if($available){$room['host']=$available[0]['id'];$room['version']++;}}
    }
    party_tick($room);
    $room['updated']=time();rewind($handle);ftruncate($handle,0);fwrite($handle,json_encode($room,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));fflush($handle);flock($handle,LOCK_UN);fclose($handle);$handle=null;
    if($action==='create'&&random_int(1,15)===1)foreach(array_slice(glob($data.'/*.json')?:[],0,200) as $old)if(time()-filemtime($old)>21600){$cleanup=@fopen($old,'r+');if($cleanup&&flock($cleanup,LOCK_EX|LOCK_NB)){if(time()-filemtime($old)>21600)@unlink($old);flock($cleanup,LOCK_UN);}if($cleanup)fclose($cleanup);}
    $response=['room'=>party_view($room,$player['id'])];if($action==='create'||$action==='join')$response['session']=['code'=>$room['code'],'game'=>$room['game'],'token'=>$player['token'],'id'=>$player['id']];party_reply($response);
}catch(Throwable $error){
    if($handle){flock($handle,LOCK_UN);fclose($handle);}
    $status=$error instanceof RuntimeException&&$error->getCode()>=400&&$error->getCode()<600?$error->getCode():500;
    party_reply(['error'=>$status===500?'The room service hit a problem. Try again.':$error->getMessage()],$status);
}
