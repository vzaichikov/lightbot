<?php
require __DIR__.'/bootstrap.php';
use Hobotix\Lightbot\{RefreshCallbacks,CallbackPoller,TelegramClient,RuntimeStore,ScheduleCache,MessageComposer};
function cmdDomains():array{return ['light.hobotix.dev'=>['tg_token'=>'fake','tg_chat_id'=>'-1','dtek'=>['region'=>'kyiv-region','group'=>'GPV5.2']]];}
function commandMessage(string $text='/dtek',int $chat=-1):array{
 $first=explode(' ',$text)[0];return ['message_id'=>42,'date'=>1794294000,'from'=>['id'=>10,'is_bot'=>false,'first_name'=>'Test'],
 'chat'=>['id'=>$chat,'type'=>'supergroup'],'text'=>$text,'entities'=>[['type'=>'bot_command','offset'=>0,'length'=>strlen($first)]]];
}
function commandWorker(array &$calls,int &$fetches,?Closure $request=null):array{
 $store=new RuntimeStore(tempdir());$client=new TelegramClient(function($m,$t,$p)use(&$calls,$request){$calls[]=[$m,$t,$p];return $request ? $request($m,$t,$p):['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>['message_id'=>9]]];});
 $handler=new RefreshCallbacks($store,new ScheduleCache($store,function()use(&$fetches){$fetches++;return feed();}),new MessageComposer(),$client);
 return [$handler,$store,$client];
}
test('plain and targeted dtek commands refresh and post without callback acknowledgement',function(){
 foreach(['/dtek','/dtek@Light_Bot','/dtek@light_bot test'] as $text){$calls=[];$fetches=0;[$h]=commandWorker($calls,$fetches);
  eq(method_exists($h,'handleCommand'),true);
  eq($h->handleCommand('fake',cmdDomains(),commandMessage($text),'light_bot',at('2026-11-10 09:00')),'sent');eq($fetches,1);
  eq(array_column($calls,0),['sendRichMessage']);eq($calls[0][2]['chat_id'],'-1');
 }
});
test('commands reject other bot mentions unknown chats and ordinary text',function(){
 $calls=[];$fetches=0;[$h]=commandWorker($calls,$fetches);
 $cases=[commandMessage('/dtek@other_bot'),commandMessage('/dtek',-2),commandMessage('hello /dtek'),commandMessage('/dtek_extra')];
 $noEntity=commandMessage();unset($noEntity['entities']);$cases[]=$noEntity;
 $bot=commandMessage();$bot['from']['is_bot']=true;$cases[]=$bot;
 foreach($cases as $m)eq($h->handleCommand('fake',cmdDomains(),$m,'light_bot',at('2026-11-10 09:00')),'ignored');
 eq($h->handleCommand('wrong',cmdDomains(),commandMessage(),'light_bot',at('2026-11-10 09:00')),'ignored');eq($fetches,0);eq($calls,[]);
});
test('command and button share the same cooldown without extra group spam',function(){
 $calls=[];$fetches=0;[$h]=commandWorker($calls,$fetches);$now=at('2026-11-10 09:00');
 eq($h->handleCommand('fake',cmdDomains(),commandMessage(),'light_bot',$now),'sent');
 eq($h->handleCommand('fake',cmdDomains(),commandMessage(),'light_bot',$now->modify('+10 seconds')),'cooldown');eq(count($calls),1);
 eq($h->handle('fake',cmdDomains(),['id'=>'q1','message'=>commandMessage(),'data'=>'dtek:refresh'],$now->modify('+20 seconds')),'cooldown');eq($fetches,1);
 eq($h->handleCommand('fake',cmdDomains(),commandMessage(),'light_bot',$now->modify('+60 seconds')),'sent');eq($fetches,2);
});
test('poller dispatches only new command messages and persists their consumption',function(){
 $calls=[];$fetches=0;[$h,$s,$c]=commandWorker($calls,$fetches,function($m){return ['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>$m==='getUpdates' ? [
 ['update_id'=>1,'edited_message'=>commandMessage()],['update_id'=>2,'message'=>commandMessage('/dtek@other_bot')],['update_id'=>3,'message'=>commandMessage('/dtek@light_bot')]]:['message_id'=>8]]];});
 $p=new CallbackPoller($s,$c,$h,'light_bot');eq($p->poll('fake',cmdDomains(),at('2026-11-10 09:00'),0),['ignored','sent']);
 eq($p->poll('fake',cmdDomains(),at('2026-11-10 09:05'),0),[]);eq($fetches,1);
 eq($calls[0][2]['allowed_updates'],['callback_query','message']);
});
test('bot username comes from verified getMe and invalid identity fails closed',function(){
 $client=new TelegramClient(fn()=>['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>['id'=>10,'is_bot'=>true,'username'=>'light_bot']]]);
 eq($client->username('fake'),'light_bot');
 foreach([['http'=>401,'error'=>false,'body'=>['ok'=>false]],['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>['is_bot'=>false,'username'=>'light_bot']]]] as $r) throws(fn()=>(new TelegramClient(fn()=>$r))->username('fake'));
});
test('command registration preserves existing entries and verifies stored group menu',function(){
 $calls=[];$commands=[['command'=>'help','description'=>'Help']];$client=new TelegramClient(function($m,$t,$p)use(&$calls,&$commands){$calls[]=[$m,$p];if($m==='setMyCommands')$commands=$p['commands'];return ['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>$m==='setMyCommands'?true:$commands]];});
 $client->registerDtekCommand('fake','-1');eq(array_column($commands,'command'),['help','dtek']);eq($calls[1][1]['scope'],['type'=>'chat','chat_id'=>'-1']);
 $client->registerDtekCommand('fake','-1');eq(count($commands),2);
});
test('command registration fails closed on read or write errors',function(){
 $calls=[];$c=new TelegramClient(function($m)use(&$calls){$calls[]=$m;return ['http'=>401,'error'=>false,'body'=>['ok'=>false]];});
 throws(fn()=>$c->registerDtekCommand('fake','-1'));eq($calls,['getMyCommands']);
 $c=new TelegramClient(fn($m)=>$m==='getMyCommands'?['http'=>200,'error'=>false,'body'=>['ok'=>true,'result'=>[]]]:['http'=>403,'error'=>false,'body'=>['ok'=>false]]);
 throws(fn()=>$c->registerDtekCommand('fake','-1'));
});
