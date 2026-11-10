<?php
declare(strict_types=1);
// ============================================================
// crlf2 · admin.php —— 控制室（判定页）
// 读缓存里的 role：值为 admin 时放行（缓存是服务端全局状态，
// 改浏览器里的任何东西都没有用——必须真的污染缓存）。
// ============================================================
require __DIR__ . '/init.php';
require __DIR__ . '/memcached.php';

$mem = new MiniMemcached(__DIR__ . '/data');
$role = $mem->get('role');

page_top('难度：★★★★☆ 高阶 | 关卡 ID：<code>crlf2</code> · 运行时：PHP 8.2 · admin.php（控制室）');

echo '<section class="card"><h2>控制室</h2>';
if ($role === 'admin') {
    echo '<div class="okbox">✅ role=admin（缓存污染成功）。控制室口令：<b>' . CRLF2_FLAG . '</b></div>';
} else {
    echo '<div class="nobox">⛔ role=' . h($role ?? '(空)') . '，需要 admin。缓存是服务端状态——改浏览器里的东西没用。</div>';
}
echo '<p style="color:var(--dim);font-size:13px"><a href="index.php">返回首页</a> ｜ <a href="view.php?key=role">去查询台看看 role</a> ｜ <a href="reset.php">重置缓存（恢复 role=guest）</a></p>';
echo '</section>';

page_bottom('crlf2');
