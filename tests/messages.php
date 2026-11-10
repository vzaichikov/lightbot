<?php
require __DIR__.'/bootstrap.php';
use Hobotix\Lightbot\{MessageComposer,Schedule,Texts};
function cfg():array{return ['label'=>'Hobotix','texts_block'=>'default','dtek'=>['region'=>'kyiv-region','group'=>'GPV5.2','address'=>'Київщина']];}
function snap():array{return ['data'=>Schedule::normalize(feed(hours:[10=>'no',11=>'first']),'kyiv-region','GPV5.2'),'stale'=>false];}
test('events identify state duration and forecast independently of rude copy',function(){
 $m=(new MessageComposer)->event('light.hobotix.dev',cfg(),false,3,15,snap(),at('2026-11-10 09:00'));
 has($m['rich'],'СВІТЛА НЕМАЄ');has($m['rich'],'10:30');has($m['rich'],'5.2');has($m['rich'],'<h2>');
 has($m['basic'],'СВІТЛА НЕМАЄ');eq(str_contains($m['basic'],'<table'),false);
 $on=(new MessageComposer)->event('light.hobotix.dev',cfg(),true,2,30,snap(),at('2026-11-10 08:00'));has($on['rich'],'СВІТЛО Є');has($on['rich'],'09:00');
});
test('digest contains interval table and confirmed total',function(){
 $m=(new MessageComposer)->digest('light.hobotix.dev',cfg(),snap(),at('2026-11-10 09:00'));
 has($m['rich'],'10.11.2026');has($m['rich'],'<table');has($m['rich'],'09:00');has($m['rich'],'10:30');has($m['rich'],'1 год 30 хв');
});
test('all-on differs from unavailable and unpublished',function(){
 $c=new MessageComposer; $s=snap();$s['data']=Schedule::normalize(feed(),'kyiv-region','GPV5.2');
 has($c->digest('light.hobotix.dev',cfg(),$s,at('2026-11-10 09:00'))['rich'],'не заплановано');
 $s['stale']=true;has($c->digest('light.hobotix.dev',cfg(),$s,at('2026-11-10 09:00'))['rich'],'не підтверджено');
 $s['stale']=false;$s['data']['slots']=[];has($c->digest('light.hobotix.dev',cfg(),$s,at('2026-11-10 09:00'))['rich'],'не опублікував');
});
test('external labels cannot inject markup or links',function(){
 $cfg=cfg();$cfg['label']='<script>x</script>';$cfg['dtek']['address']='A & B';
 $m=(new MessageComposer)->digest('bad" onclick="x', $cfg,snap(),at('2026-11-10 09:00'));
 has($m['rich'],'&lt;script&gt;');has($m['rich'],'A &amp; B');eq(str_contains($m['rich'],'<script>'),false);eq(str_contains($m['rich'],'onclick='),false);
});
test('every existing phrase collection has 25 unique useful entries',function(){
 foreach(Texts::BLOCKS as $block){foreach(['light_on_first_line','light_on_second_line','light_off_first_line','light_off_second_line','light_on_duration','light_off_duration'] as $key){eq(count($block[$key]),25);eq(count(array_unique($block[$key])),25);foreach($block[$key] as $phrase){if(str_ends_with($key,'duration')) { $v=sprintf($phrase,7,13);has($v,'7');has($v,'13');}}}}
});
test('verification combines labelled examples and live daily plan with required greeting',function(){
 $m=(new MessageComposer)->verification('light.hobotix.dev',cfg(),snap(),at('2026-11-10 09:00'));
 $greeting='Вітаю! Мене звати Хоботун, я найрозумніший AI-агент в світі. Пишу вам за дорученням свого повелителя Віктора Найвеличнішого.';
 eq(str_starts_with(strip_tags($m['rich']),$greeting),true);has($m['rich'],'НЕ РЕАЛЬНІ ПОДІЇ');has($m['rich'],'СВІТЛО Є');has($m['rich'],'СВІТЛА НЕМАЄ');has($m['rich'],'<table');
 eq(mb_strlen(strip_tags($m['basic']))<4096,true);
});
