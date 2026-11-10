<?php
// redirect1 漏洞点：url 完全可控 → 开放重定向
// "分享奖励"逻辑把 share_token（=flag）拼在任意目标 URL 后面
$TOKEN = 'flag{redirect1_f555b687}';

$url = isset($_GET['url']) ? $_GET['url'] : '';
if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    exit('参数错误：url 必须是 http(s):// 开头的完整 URL');
}
$sep = (strpos($url, '?') === false) ? '?' : '&';
header('Location: ' . $url . $sep . 'share_token=' . $TOKEN);
exit;
