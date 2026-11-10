<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once(dirname(__FILE__) . '/vendor/autoload.php');

date_default_timezone_set('Europe/Kyiv');
$runtime = new \Hobotix\Lightbot\RuntimeStore(\Hobotix\Lightbot\Config::runtimeDirectory());
$lock = $runtime->lock('monitor');
if ($lock === null) {
    exit(0);
}
$lightbot = new \Hobotix\Lightbot\LightBot();
$lightbot->checkStatus();
