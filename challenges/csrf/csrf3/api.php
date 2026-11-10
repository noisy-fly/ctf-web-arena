<?php
declare(strict_types=1);
require __DIR__ . '/handler.php';

$me = current_user();
[$status, $body] = csrf3_api($me, $_SERVER['REQUEST_METHOD'], (string)file_get_contents('php://input'));
http_response_code($status);
header('Content-Type: text/plain; charset=utf-8');
echo $body;
