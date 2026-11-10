<?php
require_once __DIR__ . '/../vendor/autoload.php';
date_default_timezone_set('Europe/Kyiv');
$passed = 0;
function test(string $name, Closure $fn): void {
    global $passed;
    try { $fn(); ++$passed; echo "PASS $name\n"; }
    catch (Throwable $e) { fwrite(STDERR, "FAIL $name: {$e->getMessage()}\n"); exit(1); }
}
function eq(mixed $actual, mixed $expected): void {
    if ($actual !== $expected) throw new RuntimeException(var_export($actual,true).' != '.var_export($expected,true));
}
function has(string $text, string $part): void { if (!str_contains($text,$part)) throw new RuntimeException("Missing $part in $text"); }
function throws(Closure $fn): void { try { $fn(); } catch (Exception $e) { return; } throw new RuntimeException('Expected exception'); }
function at(string $time): DateTimeImmutable { return new DateTimeImmutable($time,new DateTimeZone('Europe/Kyiv')); }
function feed(string $date='2026-11-10',array $hours=[],string $region='kyiv-region',string $group='GPV5.2'): array {
    $zones=[]; for($h=1;$h<=24;$h++) $zones[$h]=[$h,sprintf('%02d:00',$h-1),sprintf('%02d:00',$h)];
    return ['regionId'=>$region,'lastUpdated'=>at($date.' 09:00')->format(DATE_ATOM),'lastUpdateStatus'=>['ok'=>true],
        'preset'=>['time_zone'=>$zones],'fact'=>['update'=>at($date.' 08:55')->format('d.m.Y H:i'),
        'data'=>[at($date.' 00:00')->getTimestamp()=>[$group=>array_replace(array_fill(1,24,'yes'),$hours)]]]];
}
function tempdir(): string { $p=sys_get_temp_dir().'/lightbot-test-'.bin2hex(random_bytes(8)); mkdir($p,0700); register_shutdown_function(function()use($p){ foreach(glob($p.'/*')?:[] as $f) if(is_file($f)) unlink($f); rmdir($p); }); return $p; }
