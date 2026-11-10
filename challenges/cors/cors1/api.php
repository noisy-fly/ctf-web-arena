<?php
// cors1 漏洞点：反射任意 Origin + Allow-Credentials（null 被拒绝，见 cors2）
session_start();
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;
if ($origin !== null && $origin !== 'null') {
    header('Access-Control-Allow-Origin: ' . $origin);   // ← 谁来反射谁
    header('Access-Control-Allow-Credentials: true');    // ← 允许带 Cookie 读
    header('Vary: Origin');
}
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['sid'])) {
    echo json_encode(array('error' => '未登录：请先到 /cors1/?action=claim 领取身份（会话 Cookie）'));
    exit;
}
echo json_encode(array(
    'user' => 'user-' . $_SESSION['sid'],
    'private_note' => 'flag{cors1_b32ab893}',
));
