<?php
// ============================================================
// cors2 · CORS null Origin 反射 + 后缀匹配
// 漏洞点：
//   ① Origin === 'null' 时反射 ACAO: null（"为了离线打包的 H5 页面"）
//     → 任何沙盒 iframe / file:// / 跨源重定向 页面都是 null 源
//   ② host 以 stars-partner.com 结尾即放行（无点边界）
//     → https://evil-stars-partner.com 也能骗过
// 接口需要合作方 key（?key=STARPARTNER-2024，"泄露在对接文档里"）
// ============================================================
header('Content-Type: application/json; charset=utf-8');
header('Vary: Origin');

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;
if ($origin !== null) {
    if ($origin === 'null') {
        // ① 沙盒 iframe/本地文件/某些重定向页面的 Origin 是 null——开发者放行了它
        header('Access-Control-Allow-Origin: null');
    } else {
        $host = strtolower((string)parse_url($origin, PHP_URL_HOST));
        // ② 后缀匹配且无点边界：evil-stars-partner.com 也能通过
        if ($host !== '' && substr($host, -strlen('stars-partner.com')) === 'stars-partner.com') {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
    }
}

$key = isset($_GET['key']) ? (string)$_GET['key'] : '';
if ($key !== 'STARPARTNER-2024') {
    http_response_code(401);
    echo json_encode(array('error' => 'invalid partner key'));
    exit;
}
echo json_encode(array('symbol' => 'XSTAR', 'quote' => 'flag{cors2_593b25eb}'));
