<?php
declare(strict_types=1);
require __DIR__ . '/handler.php';

$me = current_user();
[$status, $body] = csrf2_api($me, $_SERVER['REQUEST_METHOD'], $_GET, $_POST, $_SERVER['HTTP_REFERER'] ?? '');
http_response_code($status);
echo $body;
