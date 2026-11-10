<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$failed = false;
foreach (['schedule', 'messages', 'telegram', 'morning', 'monitoring', 'callbacks'] as $suite) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $suite . '.php'), $code);
    $failed = $failed || $code !== 0;
}
exit($failed ? 1 : 0);
