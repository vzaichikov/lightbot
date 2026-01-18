<?php

require_once(dirname(__FILE__) . '/vendor/autoload.php');

$lightbot = new \Hobotix\LightBot\LightBot();
$lightbot->checkStatus();