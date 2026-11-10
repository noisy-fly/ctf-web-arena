<?php
// ============================================================
// 内部管理接口（仅允许服务器本机访问）
// 判定：REMOTE_ADDR 必须是回环；带了 X-Forwarded-For 头一律拒绝（防伪造）
// ============================================================
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

// —— 以下内容只有"服务器自己"能看到 ——
echo "=== 星辰科技 内部管理面板 ===\n";
echo "服务状态: OK  内存: 62%  在线节点: 3\n";
echo "机密配置: flag{ssrf1_72fc9cc2}\n";
