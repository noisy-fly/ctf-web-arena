<?php
// 内部管理接口（仅允许服务器本机访问）—— ssrf3 版
header('Content-Type: text/plain; charset=utf-8');
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    http_response_code(403);
    echo "[403] 检测到代理头（X-Forwarded-For），管理接口拒绝代理请求。\n";
    exit;
}
$rip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
if (!in_array($rip, array('127.0.0.1', '::1'), true)) {
    http_response_code(403);
    echo "[403] 管理接口仅允许本机访问，你的 IP：{$rip}\n";
    exit;
}
echo "=== 星辰科技 内部管理面板 v3 ===\n";
echo "节点状态: OK  队列: 0  告警: 无\n";
echo "运维通道密钥: flag{ssrf3_c4650bcd}\n";
