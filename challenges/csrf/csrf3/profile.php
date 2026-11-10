<?php
declare(strict_types=1);
require __DIR__ . '/handler.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
[$status, $body, ] = csrf3_profile(current_user(), $origin, isset($_SERVER['HTTP_X_PROTECTION']));
if ($origin !== '') {
    // 漏洞:把请求方的 Origin 原样反射回 Access-Control-Allow-Origin,还允许带凭据
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}
http_response_code($status);
header('Content-Type: application/json; charset=utf-8');
echo $body;
