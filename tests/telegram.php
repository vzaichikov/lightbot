<?php
require __DIR__.'/bootstrap.php';
use Hobotix\Lightbot\TelegramClient;
$message=['rich'=>'<h2>Test</h2>','basic'=>'<b>Test</b>'];
test('rich transport sends structured payload with parsed success',function()use($message){
 $seen=[];$client=new TelegramClient(function($method,$token,$payload)use(&$seen){$seen=[$method,$payload];return ['http'=>200,'body'=>['ok'=>true,'result'=>['message_id'=>42]],'error'=>false];});
 $r=$client->send('fake','-123',$message);eq($r['status'],'sent');eq($seen[0],'sendRichMessage');eq($seen[1]['rich_message'],['html'=>'<h2>Test</h2>']);
});
test('explicit unsupported rich format falls back once',function()use($message){
 $methods=[];$c=new TelegramClient(function($method,$token,$payload)use(&$methods){$methods[]=$method;return $method==='sendRichMessage'?['http'=>400,'body'=>['ok'=>false,'error_code'=>400,'description'=>'Bad Request: rich messages are not supported in this chat'],'error'=>false]:['http'=>200,'body'=>['ok'=>true,'result'=>['message_id'=>43]],'error'=>false];});
 eq($c->send('fake','-123',$message)['status'],'sent');eq($methods,['sendRichMessage','sendMessage']);
});
test('timeout malformed response and server failure never duplicate via fallback',function()use($message){
 foreach([['http'=>0,'body'=>null,'error'=>true],['http'=>200,'body'=>null,'error'=>false],['http'=>502,'body'=>null,'error'=>false]] as $response){$n=0;$c=new TelegramClient(function()use($response,&$n){$n++;return $response;});eq($c->send('fake','-123',$message)['status'],'uncertain');eq($n,1);}
});
test('API rejection preserves retry-after and never leaks description or token',function()use($message){
 $n=0;$c=new TelegramClient(function()use(&$n){$n++;return ['http'=>429,'body'=>['ok'=>false,'error_code'=>429,'description'=>'secret-token','parameters'=>['retry_after'=>600]],'error'=>false];});
 $r=$c->send('fake','-123',$message);eq($r['status'],'rejected');eq($r['retry_after'],600);eq($n,1);eq(str_contains(json_encode($r),'secret-token'),false);
});
test('unauthorized and unrelated bad requests do not fall back',function()use($message){
 foreach([401,403,400] as $code){$n=0;$c=new TelegramClient(function()use($code,&$n){$n++;return ['http'=>$code,'body'=>['ok'=>false,'error_code'=>$code,'description'=>'Bad Request: chat not found'],'error'=>false];});eq($c->send('fake','-123',$message)['status'],'rejected');eq($n,1);}
});
test('DTEK composer buttons survive native rich and basic fallback transports',function(){
 $composer=new Hobotix\Lightbot\MessageComposer();$config=['dtek'=>['region'=>'kyiv-region','group'=>'GPV5.2']];$snapshot=Hobotix\Lightbot\ScheduleCache::unavailable();$now=at('2026-11-10 09:00');
 $messages=[$composer->event('light.hobotix.dev',$config,true,1,0,$snapshot,$now),$composer->digest('light.hobotix.dev',$config,$snapshot,$now),$composer->verification('light.hobotix.dev',$config,$snapshot,$now)];
 foreach($messages as $message){$seen=[];$client=new TelegramClient(function($method,$token,$payload)use(&$seen){$seen[]=$payload;return $method==='sendRichMessage'?['http'=>400,'error'=>false,'body'=>['ok'=>false,'description'=>'rich messages are not supported']]:['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>['message_id'=>3]]];});
  $client->send('fake','-1',$message);foreach($seen as $payload)eq($payload['reply_markup']['inline_keyboard'][0][0]['callback_data']??null,'dtek:refresh');
 }
});
