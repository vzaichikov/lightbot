<?php
require __DIR__.'/bootstrap.php';
use Hobotix\Lightbot\{RefreshCallbacks,CallbackPoller,TelegramClient,RuntimeStore,ScheduleCache,MessageComposer};
function cbDomains():array{return [
 'light.hobotix.dev'=>['label'=>'Hobotix','tg_token'=>'fake-a','tg_chat_id'=>'-1','dtek'=>['region'=>'kyiv-region','group'=>'GPV5.2']],
 'light.charmpole.dance'=>['label'=>'Charmpole','tg_token'=>'fake-b','tg_chat_id'=>'-2','dtek'=>['region'=>'kyiv','group'=>'GPV14.1']],
];}
function query(int $chat=-1,string $id='q1'):array{return ['id'=>$id,'from'=>['id'=>12,'is_bot'=>false,'first_name'=>'Test'],
 'message'=>['message_id'=>100,'date'=>1794294000,'chat'=>['id'=>$chat,'type'=>'supergroup']], 'chat_instance'=>'abc','data'=>'dtek:refresh'];}
function cbWorker(string $dir,array &$calls,int &$fetches,?Closure $request=null,?Closure $fetch=null):array{
 $store=new RuntimeStore($dir);
 $client=new TelegramClient(function($m,$t,$p)use(&$calls,$request){$calls[]=[$m,$t,$p];return $request ? $request($m,$t,$p) : ['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>$m==='answerCallbackQuery'?true:['message_id'=>222]]];});
 $cache=new ScheduleCache($store,$fetch??function($url)use(&$fetches){$fetches++;return str_ends_with($url,'/kyiv.json')?feed('2026-11-10',[], 'kyiv','GPV14.1'):feed('2026-11-10',[12=>'no']);});
 return [new RefreshCallbacks($store,$cache,new MessageComposer(),$client),$store,$client];
}
test('callback immediately acknowledges then refreshes and posts only to configured chat',function(){
 eq(class_exists(RefreshCallbacks::class),true);
 $calls=[];$fetches=0;[$handler]=cbWorker(tempdir(),$calls,$fetches);
 eq($handler->handle('fake-a',cbDomains(),query(),at('2026-11-10 09:01')),'sent');eq($fetches,1);
 eq(array_column($calls,0),['answerCallbackQuery','sendRichMessage']);eq($calls[1][1],'fake-a');eq($calls[1][2]['chat_id'],'-1');
 has($calls[1][2]['rich_message']['html'],'11:00');has($calls[1][2]['rich_message']['html'],'09:01');
 eq($calls[1][2]['reply_markup']['inline_keyboard'][0][0]['callback_data'],'dtek:refresh');
});
test('unknown chat wrong bot and arbitrary callback data cannot fetch or send',function(){
 $calls=[];$fetches=0;[$handler]=cbWorker(tempdir(),$calls,$fetches);
 $bad=query();$bad['data']='dtek:refresh:../../other';$inline=query();unset($inline['message']);
 foreach([['fake-a',query(-2)],['fake-b',query(-1)],['fake-a',query(-999)],['fake-a',$bad],['fake-a',$inline]] as [$token,$q])eq($handler->handle($token,cbDomains(),$q,at('2026-11-10 09:01')),'ignored');
 eq($fetches,0);eq(count(array_filter($calls,fn($c)=>$c[0]!=='answerCallbackQuery')),0);
});
test('group cooldown survives restart and does not block other bot group',function(){
 $dir=tempdir();$calls=[];$fetches=0;[$handler]=cbWorker($dir,$calls,$fetches);
 eq($handler->handle('fake-a',cbDomains(),query(),at('2026-11-10 09:01')),'sent');
 [$handler]=cbWorker($dir,$calls,$fetches);
 eq($handler->handle('fake-a',cbDomains(),query(-1,'q2'),at('2026-11-10 09:01:59')),'cooldown');
 eq($handler->handle('fake-b',cbDomains(),query(-2,'q3'),at('2026-11-10 09:01:59')),'sent');
 eq($handler->handle('fake-a',cbDomains(),query(-1,'q4'),at('2026-11-10 09:02')),'sent');eq($fetches,3);
});
test('refresh failure posts unavailable warning instead of claiming fresh schedule',function(){
 $calls=[];$fetches=0;[$handler]=cbWorker(tempdir(),$calls,$fetches,null,fn()=>throw new RuntimeException('private url'));
 eq($handler->handle('fake-a',cbDomains(),query(),at('2026-11-10 09:01')),'sent');
 has($calls[1][2]['rich_message']['html'],'недоступний');eq(str_contains(json_encode($calls),'private url'),false);
});
test('rate limit extends group cooldown and does not automatically retry send',function(){
 $calls=[];$fetches=0;[$handler]=cbWorker(tempdir(),$calls,$fetches,fn($m)=>$m==='answerCallbackQuery'?['http'=>200,'body'=>['ok'=>true,'result'=>true],'error'=>false]:['http'=>429,'body'=>['ok'=>false,'error_code'=>429,'parameters'=>['retry_after'=>600]],'error'=>false]);
 eq($handler->handle('fake-a',cbDomains(),query(),at('2026-11-10 09:01')),'rejected');
 eq($handler->handle('fake-a',cbDomains(),query(-1,'q2'),at('2026-11-10 09:05')),'cooldown');eq($fetches,1);
});
test('corrupt cooldown state cannot cause a send',function(){
 $dir=tempdir();$calls=[];$fetches=0;[$handler]=cbWorker($dir,$calls,$fetches);
 $handler->handle('fake-a',cbDomains(),query(),at('2026-11-10 09:01'));
 $files=glob($dir.'/refresh-*.json');eq(count($files),1);file_put_contents($files[0],'{}');
 eq($handler->handle('fake-a',cbDomains(),query(-1,'q2'),at('2026-11-10 09:05')),'error');eq($fetches,1);
});
test('polling persists offset before dispatch and replay cannot resend after ambiguous delivery',function(){
 $dir=tempdir();$calls=[];$fetches=0;$pollOffsets=[];
 [$handler,$store,$client]=cbWorker($dir,$calls,$fetches,function($m,$t,$p)use(&$pollOffsets){
  if($m==='getUpdates'){$pollOffsets[]=$p;return ['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>[['update_id'=>10,'callback_query'=>query()]]]];}
  if($m==='sendRichMessage')return ['http'=>0,'error'=>true,'body'=>null];
  return ['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>true]];
 });
 $poller=new CallbackPoller($store,$client,$handler);
 eq($poller->poll('fake-a',cbDomains(),at('2026-11-10 09:01'),20),['uncertain']);
 $poller=new CallbackPoller($store,$client,$handler);
 eq($poller->poll('fake-a',cbDomains(),at('2026-11-10 09:05'),20),[]);eq($fetches,1);
 eq($pollOffsets[0]['offset'],0);eq($pollOffsets[1]['offset'],11);eq($pollOffsets[0]['allowed_updates'],['callback_query','message']);eq($pollOffsets[0]['timeout'],20);
});
test('polling skips unrelated updates and cannot advance past a malformed batch',function(){
 $calls=[];$fetches=0;$batch=[['update_id'=>4,'message'=>['text'=>'hello']],['update_id'=>5,'callback_query'=>query()]];
 [$handler,$store,$client]=cbWorker(tempdir(),$calls,$fetches,function($m)use(&$batch){return ['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>$m==='getUpdates'?$batch:($m==='answerCallbackQuery'?true:['message_id'=>4])]];});
 $poller=new CallbackPoller($store,$client,$handler);eq($poller->poll('fake-a',cbDomains(),at('2026-11-10 09:01'),0),['ignored','sent']);
 $batch=[['update_id'=>7,'callback_query'=>query()],['update_id'=>'bad']];throws(fn()=>$poller->poll('fake-a',cbDomains(),at('2026-11-10 09:05'),0));eq($fetches,1);
});
test('source refresh already in progress cannot relabel old cache as an on-demand refresh',function(){
 $dir=tempdir();$calls=[];$fetches=0;[$handler,$store]=cbWorker($dir,$calls,$fetches);
 (new ScheduleCache($store,fn()=>feed('2026-11-10',[12=>'no'])))->refresh(cbDomains()['light.hobotix.dev']['dtek'],at('2026-11-10 09:00'));
 $lock=$store->lock('schedule-kyiv-region-GPV5.2');
 eq($handler->handle('fake-a',cbDomains(),query(),at('2026-11-10 09:01')),'sent');
 has($calls[1][2]['rich_message']['html'],'недоступний');eq($fetches,0);fclose($lock);
});
test('polling rate limit survives worker restarts without additional API requests',function(){
 $calls=[];$fetches=0;[$handler,$store,$client]=cbWorker(tempdir(),$calls,$fetches,fn()=>['http'=>429,'error'=>false,'body'=>['ok'=>false,'error_code'=>429,'parameters'=>['retry_after'=>600]]]);
 throws(fn()=>(new CallbackPoller($store,$client,$handler))->poll('fake-a',cbDomains(),at('2026-11-10 09:00'),0));
 throws(fn()=>(new CallbackPoller($store,$client,$handler))->poll('fake-a',cbDomains(),at('2026-11-10 09:05'),0));eq(count($calls),1);
});
test('malformed cursor fails closed before fetching or sending',function(){
 $dir=tempdir();$calls=[];$fetches=0;[$handler,$store,$client]=cbWorker($dir,$calls,$fetches);
 $store->write(CallbackPoller::key('fake-a'),['offset'=>'bad','next_poll'=>0]);
 throws(fn()=>(new CallbackPoller($store,$client,$handler))->poll('fake-a',cbDomains(),at('2026-11-10 09:00'),0));eq($calls,[]);eq($fetches,0);
});
test('group lock prevents concurrent refresh and duplicate configured routing is rejected',function(){
 $calls=[];$fetches=0;[$handler,$store]=cbWorker(tempdir(),$calls,$fetches);
 $lock=$store->lock('refresh-'.hash('sha256','fake-a:-1'));
 eq($handler->handle('fake-a',cbDomains(),query(),at('2026-11-10 09:00')),'busy');fclose($lock);
 $domains=cbDomains();$domains['duplicate']=$domains['light.hobotix.dev'];
 eq($handler->handle('fake-a',$domains,query(),at('2026-11-10 09:00')),'ignored');eq($fetches,0);
});
test('idle cursor expires beyond retained-update lifetime and accepts a lower new update ID',function(){
 $calls=[];$fetches=0;$offsets=[];
 [$handler,$store,$client]=cbWorker(tempdir(),$calls,$fetches,function($m,$t,$p)use(&$offsets){
  if($m==='getUpdates'){$offsets[]=$p['offset'];return ['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>[['update_id'=>100,'callback_query'=>query()]]]];}
  return ['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>$m==='answerCallbackQuery'?true:['message_id'=>8]]];
 });
 $key=CallbackPoller::key('fake-a');
 $store->write($key,['offset'=>900001,'next_poll'=>0,'last_update_at'=>at('2026-11-07 09:00')->getTimestamp()]);
 eq((new CallbackPoller($store,$client,$handler))->poll('fake-a',cbDomains(),at('2026-11-10 09:00'),0),['sent']);eq($offsets,[0]);eq($fetches,1);
 eq($store->read($key)['offset'],101);
});
test('delayed send rate limit starts after the API response',function(){
 $dir=tempdir();$calls=[];$fetches=0;[$handler,$store]=cbWorker($dir,$calls,$fetches,function($m){
  if($m==='answerCallbackQuery')return ['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>true]];
  usleep(1100000);return ['http'=>429,'error'=>false,'body'=>['ok'=>false,'error_code'=>429,'parameters'=>['retry_after'=>120]]];
 });
 $now=at('2026-11-10 09:00');$handler->handle('fake-a',cbDomains(),query(),$now);
 $state=$store->read('refresh-'.hash('sha256','fake-a:-1'));eq($state['next_allowed']>=$now->getTimestamp()+122,true);
});
test('delayed polling rate limit starts after the API response',function(){
 $calls=[];$fetches=0;[$handler,$store,$client]=cbWorker(tempdir(),$calls,$fetches,function(){
  usleep(1100000);return ['http'=>429,'error'=>false,'body'=>['ok'=>false,'error_code'=>429,'parameters'=>['retry_after'=>120]]];
 });
 $now=at('2026-11-10 09:00');throws(fn()=>(new CallbackPoller($store,$client,$handler))->poll('fake-a',cbDomains(),$now,0));
 eq($store->read(CallbackPoller::key('fake-a'))['next_poll']>=$now->getTimestamp()+122,true);
});
