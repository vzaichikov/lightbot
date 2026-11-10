<?php
require __DIR__.'/bootstrap.php';
use Hobotix\Lightbot\{MorningDigest,ScheduleCache,RuntimeStore,MessageComposer,TelegramClient};
function domains():array{return ['light.hobotix.dev'=>['label'=>'Hobotix','texts_block'=>'default','tg_token'=>'fake','tg_chat_id'=>'-1','dtek'=>['region'=>'kyiv-region','group'=>'GPV5.2']]];}
function worker(string $dir,Closure $request,?Closure $fetch=null):MorningDigest{
 $store=new RuntimeStore($dir);return new MorningDigest($store,new ScheduleCache($store,$fetch??fn($url)=>feed()),new MessageComposer(),new TelegramClient($request));
}
function success():array{return ['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>['message_id'=>123]]];}
test('09:00 Kyiv gate and once per domain per date',function(){
 $count=0;$fetches=0;$w=worker(tempdir(),function()use(&$count){$count++;return success();},function()use(&$fetches){$fetches++;return feed();});
 eq($w->run(domains(),at('2026-11-10 08:59')),[]);eq($count,0);eq($fetches,0);
 $w->run(domains(),new DateTimeImmutable('2026-11-10T07:00:00Z'));eq($count,1);
 $w->run(domains(),at('2026-11-10 09:05'));eq($count,1);eq($fetches,1);
 $w->run(domains(),at('2026-11-10 12:00'));eq($count,1);
 $w->run(domains(),at('2026-11-11 09:00'));eq($count,2);
});
test('definite rate limit respects retry_after and three attempt bound',function(){
 $count=0;$w=worker(tempdir(),function()use(&$count){$count++;return ['http'=>429,'error'=>false,'body'=>['ok'=>false,'error_code'=>429,'parameters'=>['retry_after'=>600]]];});
 foreach(['09:00','09:05','09:10','09:20','09:30'] as $t)$w->run(domains(),at('2026-11-10 '.$t));eq($count,3);
});
test('ambiguous delivery and interrupted sending state never resend',function(){
 $dir=tempdir();$count=0;$w=worker($dir,function()use(&$count){$count++;return ['http'=>0,'body'=>null,'error'=>true];});
 $w->run(domains(),at('2026-11-10 09:00'));$w->run(domains(),at('2026-11-10 09:05'));eq($count,1);
 $file=glob($dir.'/digest-*.json')[0];$state=json_decode(file_get_contents($file),true);$state['status']='sending';file_put_contents($file,json_encode($state));
 $w->run(domains(),at('2026-11-10 09:10'));eq($count,1);
});
test('corrupt delivery state fails closed',function(){
 $dir=tempdir();$count=0;$w=worker($dir,function()use(&$count){$count++;return success();});
 $w->run(domains(),at('2026-11-10 09:00'));$file=glob($dir.'/digest-*.json')[0];
 foreach(['broken','{}','{"status":"rejected"}'] as $bad){file_put_contents($file,$bad);$w->run(domains(),at('2026-11-10 09:05'));eq($count,1);}
});
test('disabled credentials and independent domains',function(){
 $d=domains();$d['disabled']=$d['light.hobotix.dev'];$d['disabled']['tg_token']='';$d['second']=$d['light.hobotix.dev'];
 $count=0;$w=worker(tempdir(),function()use(&$count){$count++;return success();});$w->run($d,at('2026-11-10 09:00'));eq($count,2);
 $w->run($d,at('2026-11-10 09:05'));eq($count,2);
});
test('fetch failure still produces exactly one honest unavailable digest',function(){
 $payloads=[];$w=worker(tempdir(),function($method,$token,$payload)use(&$payloads){$payloads[]=$payload;return success();},fn()=>throw new RuntimeException('unavailable'));
 $w->run(domains(),at('2026-11-10 09:00'));$w->run(domains(),at('2026-11-10 09:05'));eq(count($payloads),1);has($payloads[0]['rich_message']['html'],'не підтверджено');
});
