<?php
declare(strict_types=1);
// ============================================================
// crlf2 · fixed.php —— 修复对照版查询台
// 修复：key 白名单校验 [A-Za-z0-9_.-]{1,64}（URL 解码之后校验），
// 携带空格/控制字符/换行的 key 一律 400 拒绝——命令结构再也进不来。
// ============================================================
require __DIR__ . '/init.php';
require __DIR__ . '/memcached.php';

$key = $_GET['key'] ?? '';
if (!is_string($key)) {
    $key = '';
}

if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D', $key)) {
    http_response_code(400);
    page_top('难度：★★★★☆ 高阶 | 关卡 ID：<code>crlf2</code> · 运行时：PHP 8.2 · fixed.php（修复版）');
    echo '<section class="card"><h2>已拒绝（修复版）</h2>'
        . '<div class="errbox">key 含非法字符（白名单 [A-Za-z0-9_.-]，长度 1-64）：<code>' . h($key) . '</code></div>'
        . '<p>key 无法再携带空格与换行——也就无法在文本协议里"另起一条命令"。</p>'
        . '<p><a href="index.php">返回首页</a> ｜ <a href="view.php?key=announcement">对比漏洞版 view.php</a></p></section>';
    page_bottom('crlf2');
    exit;
}

$mem = new MiniMemcached(__DIR__ . '/data');
$raw = $mem->execute('get ' . $key . "\r\n");

page_top('难度：★★★★☆ 高阶 | 关卡 ID：<code>crlf2</code> · 运行时：PHP 8.2 · fixed.php（修复版）');
?>
<section class="card"><h2>修复版查询结果</h2>
<div class="result">
<div class="t">发送的内容（key 已通过白名单校验）</div>
<pre><?php echo h(crlf2_escape('get ' . $key . "\r\n")); ?></pre>
<div class="t">memcached 原始回复</div>
<pre><?php echo h(crlf2_escape($raw)); ?></pre>
</div>
<p><a href="index.php">返回首页</a> ｜ <a href="view.php?key=announcement">对比漏洞版 view.php</a></p>
</section>
<?php page_bottom('crlf2');
