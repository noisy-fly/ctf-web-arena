<?php
// fixed_quote.php —— cors2 的"已修复"对照接口
// 修复：精确 Origin 白名单（全等比较）；永远不反射 null
header('Content-Type: application/json; charset=utf-8');
header('Vary: Origin');

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;
$allow  = array('https://app.stars-partner.com', 'https://panel.stars-partner.com');
if ($origin !== null && in_array(strtolower($origin), $allow, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

$key = isset($_GET['key']) ? (string)$_GET['key'] : '';
if ($key !== 'STARPARTNER-2024') {
    http_response_code(401);
    echo json_encode(array('error' => 'invalid partner key'));
    exit;
}
echo json_encode(array('symbol' => 'XSTAR', 'quote' => '（合作方接口：数据正常返回）'));
