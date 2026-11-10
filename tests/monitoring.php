<?php
require __DIR__.'/bootstrap.php';
use Hobotix\Lightbot\{LightBot,StatusStore,Config,TelegramClient,ScheduleCache,RuntimeStore};
// Real monitoring decision flow, replacing only persistence and outbound HTTP.
final class MemoryStatus implements StatusStore {
 public array $rows=[];public array $events=[];
 public function fetchStatus(string $domain):?array{return $this->rows[$domain]??null;}
 public function insertInitialStatus(string $domain,int $status,DateTimeImmutable $now):void{$this->rows[$domain]=['status'=>$status,'pending_status'=>null,'last_change_at'=>$now->format('Y-m-d H:i:s')];$this->events[]=[$domain,$status];}
 public function updateChecked(string $domain,?int $pendingStatus,DateTimeImmutable $now):void{$this->rows[$domain]['pending_status']=$pendingStatus;}
 public function confirmStatusChange(string $domain,int $status,DateTimeImmutable $now):void{$this->insertInitialStatus($domain,$status,$now);}
}
test('silent initialization debounce bounce and failed delivery preserve both domains',function(){
 $cfg=['domains'=>[]];foreach(['one.example','two.example'] as $domain)$cfg['domains'][$domain]=['ip'=>'unused','port'=>1,'timeout'=>1,'tg_token'=>'fake','tg_chat_id'=>'-1','texts_block'=>'default','dtek'=>['region'=>'kyiv-region','group'=>'GPV5.2']];
 $prop=new ReflectionProperty(Config::class,'config');$prop->setValue(null,$cfg);
 $store=new MemoryStatus;$calls=0;$client=new TelegramClient(function()use(&$calls){$calls++;throw new RuntimeException('network');});
 $cache=new ScheduleCache(new RuntimeStore(tempdir()),fn()=>throw new RuntimeException('event must not fetch'));
 $bot=new LightBot($store,$client,$cache);
 ob_start();
 putenv('LIGHTBOT_SIMULATE_STATUS=1');$bot->checkStatus();eq($calls,0);eq(count($store->events),2);
 putenv('LIGHTBOT_SIMULATE_STATUS=0');$bot->checkStatus();eq($calls,0);eq($store->rows['one.example']['pending_status'],0);
 putenv('LIGHTBOT_SIMULATE_STATUS=1');$bot->checkStatus();eq($store->rows['one.example']['pending_status'],null);
 putenv('LIGHTBOT_SIMULATE_STATUS=0');$bot->checkStatus();$bot->checkStatus();eq($calls,2);eq(count($store->events),4);eq($store->rows['two.example']['status'],0);
 $bot->checkStatus();eq($calls,2);
 putenv('LIGHTBOT_SIMULATE_STATUS=1');$bot->checkStatus();$bot->checkStatus();eq($calls,4);eq($store->rows['two.example']['status'],1);
 ob_end_clean();putenv('LIGHTBOT_SIMULATE_STATUS');$prop->setValue(null,null);
});
