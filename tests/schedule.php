<?php
require __DIR__.'/bootstrap.php';
use Hobotix\Lightbot\{Schedule,ScheduleCache,RuntimeStore};
function snapshot(array $raw): array { return ['data'=>Schedule::normalize($raw,$raw['regionId'],'GPV5.2'),'stale'=>false,'error'=>null]; }
test('half hours and adjacent intervals retain exact boundaries',function(){
 $d=Schedule::normalize(feed(hours:[9=>'first',10=>'no',11=>'second']),'kyiv-region','GPV5.2');
 eq(array_column($d['slots'],'state'),['on','off','on','off','on','off','on']);
 eq($d['slots'][1],['start'=>at('2026-11-10 08:00')->getTimestamp(),'end'=>at('2026-11-10 08:30')->getTimestamp(),'state'=>'off']);
});
test('day totals clip slots and label possible separately',function(){
 $day=Schedule::day(snapshot(feed(hours:[9=>'first',10=>'no',11=>'maybe'])),at('2026-11-10 09:00'));
 eq($day['status'],'published'); eq($day['off_seconds'],5400); eq(count($day['possible']),1);
});
test('fresh OFF forecasts only contiguous actual ON boundary',function(){
 $s=snapshot(feed(hours:[10=>'no',11=>'first'])); has(Schedule::forecast($s,false,at('2026-11-10 09:00')),'10:30');
 has(Schedule::forecast($s,true,at('2026-11-10 08:00')),'09:00');
});
test('possible restoration is not a promised ON time',function(){
 $text=Schedule::forecast(snapshot(feed(hours:[10=>'no',11=>'maybe'])),false,at('2026-11-10 09:00'));
 has($text,'не підтверджено');
});
test('observed OFF during planned ON does not borrow another outage end',function(){
 $text=Schedule::forecast(snapshot(feed(hours:[12=>'no'])),false,at('2026-11-10 09:00'));
 has($text,'вже мало бути'); eq(str_contains($text,'12:00'),false);
});
test('unpublished tomorrow does not promise midnight restoration',function(){
 $text=Schedule::forecast(snapshot(feed(hours:[24=>'no'])),false,at('2026-11-10 23:15')); has($text,'ще невідомий');
});
test('next-day restoration includes its date',function(){
 $raw=feed(hours:[24=>'no']); $raw['fact']['data']+=feed('2026-11-11',[1=>'first'])['fact']['data'];
 has(Schedule::forecast(snapshot($raw),false,at('2026-11-10 23:15')),'11.11');
});
test('spring and autumn DST use elapsed instants',function(){
 foreach(['2026-03-29'=>23,'2026-10-25'=>25] as $date=>$hours){
  $s=snapshot(feed($date,array_fill(1,24,'no'))); eq(Schedule::day($s,at($date.' 09:00'))['off_seconds'],$hours*3600);
 }
});
test('unknown missing hours region and group reject',function(){
 $raw=feed(hours:[5=>'wat']); throws(fn()=>Schedule::normalize($raw,'kyiv-region','GPV5.2'));
 $raw=feed(); unset($raw['fact']['data'][array_key_first($raw['fact']['data'])]['GPV5.2'][5]);
 throws(fn()=>Schedule::normalize($raw,'kyiv-region','GPV5.2'));
 throws(fn()=>Schedule::normalize(feed(),'kyiv','GPV5.2'));
 $d=Schedule::normalize(feed(),'kyiv-region','GPV9.1'); eq($d['slots'],[]);
});
test('unpublished never means all-on',function(){
 $raw=feed(); $raw['fact']['data']=[]; eq(Schedule::day(snapshot($raw),at('2026-11-10 09:00'))['status'],'unpublished');
 eq(Schedule::day(snapshot(feed()),at('2026-11-10 09:00'))['off_seconds'],0);
});
test('cache freshness failure retention and domain separation',function(){
 $raw=feed(); $fail=false; $store=new RuntimeStore(tempdir());
 $cache=new ScheduleCache($store,function($url)use(&$raw,&$fail){if($fail)throw new RuntimeException('network'); return $raw;});
 $cfg=['region'=>'kyiv-region','group'=>'GPV5.2'];
 eq($cache->read($cfg,at('2026-11-10 09:00'))['stale'],true);
 eq($cache->refresh($cfg,at('2026-11-10 09:00'))['stale'],false);
 eq($cache->read($cfg,at('2026-11-10 09:21'))['stale'],true);
 eq($cache->read(['region'=>'kyiv','group'=>'GPV14.1'],at('2026-11-10 09:00'))['data'],null);
 $fail=true; $s=$cache->refresh($cfg,at('2026-11-10 09:05')); eq($s['stale'],true); eq(count($s['data']['slots']),1);
 has(Schedule::forecast($s,false,at('2026-11-10 09:05')),'не підтверджено');
});
test('collector failure and future timestamp suppress apparently fresh plan',function(){
 foreach(['collector','future'] as $mode){$raw=feed(); if($mode==='collector')$raw['lastUpdateStatus']['ok']=false; else $raw['lastUpdated']=at('2026-11-10 09:02')->format(DATE_ATOM);
  $cache=new ScheduleCache(new RuntimeStore(tempdir()),fn($url)=>$raw); eq($cache->refresh(['region'=>'kyiv-region','group'=>'GPV5.2'],at('2026-11-10 09:00'))['stale'],true);
 }
});
test('storage corruption and lock contention fail closed',function(){
 $dir=tempdir(); $store=new RuntimeStore($dir); $store->write('example',['ok'=>true]); eq($store->read('example'),['ok'=>true]);
 file_put_contents($dir.'/example.json','broken'); throws(fn()=>$store->read('example'));
 $lock=$store->lock('busy'); eq($store->lock('busy'),null); flock($lock,LOCK_UN); fclose($lock);
});
