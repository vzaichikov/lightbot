<?php

require_once(dirname(__FILE__) . '/vendor/autoload.php');

$lightbot = new \Hobotix\Lightbot\LightBot();
$lightbot->checkStatus();
