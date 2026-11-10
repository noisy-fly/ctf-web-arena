<?php
// redirect2 漏洞点：白名单用 strpos 子串匹配实现，可被 userinfo(@)/query(?)/fragment(#) 拼接绕过
$TOKEN = 'flag{redirect2_4a59116e}';

$url = isset($_GET['url']) ? $_GET['url'] : '';
if ($url === '' || !preg_match('#^https?://#i', $url)) {
    http_response_code(400);
    exit('参数错误：url 必须是 http(s):// 开头的完整 URL');
}
// "修复"：URL 字符串里必须包含官方域名 —— 子串匹配，过于宽松
if (strpos($url, 'stars-express.com') === false) {
    http_response_code(403);
    exit('不允许的跳转目标：URL 中未包含白名单域名 stars-express.com');
}
$sep = (strpos($url, '?') === false) ? '?' : '&';
header('Location: ' . $url . $sep . 'share_token=' . $TOKEN);
exit;
