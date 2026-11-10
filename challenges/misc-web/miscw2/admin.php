<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/init.php';

$issued = (isset($_SESSION['issued']) && is_array($_SESSION['issued'])) ? $_SESSION['issued'] : [];
$visited = !empty($_SESSION['visited']);
$isAdminIssued = in_array('role=admin', $issued, true);

if (!$isAdminIssued) { http_response_code(403); }

page_head('杂项 Web 2/2 · medium', '星云留言板 —— 管理入口', '★★☆☆☆',
 '管理入口只认可<b>由本站响应真正下发过</b>的 <code>role=admin</code> Cookie——判定依据是服务端记录的“下发记录”（解析本站响应报文得到的 Set-Cookie），不是请求里带的 Cookie 头。', [], []);

if ($isAdminIssued) {
    echo '<div class="card"><h2>管理入口</h2>'
       . '<div class="msg ok">检测到本站确实向你下发过 role=admin（CRLF 注入成功）。'
       . '管理员初始化口令：<code>flag{miscw2_e688c64d}</code></div></div>';
} elseif (!$visited) {
    echo '<div class="card"><h2>403</h2><div class="msg err">请先访问 <a href="index.php">index.php</a> 获得会话。</div></div>';
} else {
    echo '<div class="card"><h2>403</h2><div class="msg err">'
       . '你手改的 Cookie 可骗不了人——只认本站响应真正下发过的 role=admin。</div></div>';
}
page_foot('miscw2');
