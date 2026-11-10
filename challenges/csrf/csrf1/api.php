<?php
declare(strict_types=1);
require __DIR__ . '/handler.php';

$me = current_user();
[$status, $body] = csrf1_api($me, $_SERVER['REQUEST_METHOD'], $_GET);
http_response_code($status);
echo $body;
