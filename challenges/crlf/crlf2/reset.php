<?php
declare(strict_types=1);
// ============================================================
// crlf2 · reset.php —— 重置缓存为初始状态（方便重复练习）
// ============================================================
require __DIR__ . '/init.php';
require __DIR__ . '/memcached.php';

$mem = new MiniMemcached(__DIR__ . '/data');
$mem->reset();

page_top('难度：★★★★☆ 高阶 | 关卡 ID：<code>crlf2</code> · 运行时：PHP 8.2 · reset.php');

echo '<section class="card"><h2>缓存已重置</h2>'
    . '<div class="okbox">role=guest 已恢复（announcement/motto 一并还原）。</div>'
    . '<p><a href="index.php">返回首页</a> ｜ <a href="view.php?key=role">验证 role 已恢复 guest</a></p></section>';

page_bottom('crlf2');
