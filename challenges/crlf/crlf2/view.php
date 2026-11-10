<?php
declare(strict_types=1);
// ============================================================
// crlf2 · view.php —— 缓存查询台（漏洞版）
// 漏洞：key 原样拼进 "get <key>\r\n" 发给（模拟）memcached，未过滤 \r\n
//       → 可注入第二条命令（set role …）污染缓存 → admin.php 放行
// 页面回显：发送的原始命令流 + 服务的原始回复（\r\n 逐字节可见）。
// ============================================================
require __DIR__ . '/init.php';
require __DIR__ . '/memcached.php';

$key = $_GET['key'] ?? '';
if (!is_string($key)) {
    $key = '';
}
$key = str_replace("\0", '', $key);

// ===== 漏洞写法：key 原样拼接，\r\n 可注入第二条命令 =====
$cmd = 'get ' . $key . "\r\n";

$mem = new MiniMemcached(__DIR__ . '/data');
$reply = $mem->execute($cmd);

$roleNow = $mem->get('role');
$stored = str_contains($reply, 'STORED');

page_top('难度：★★★★☆ 高阶 | 关卡 ID：<code>crlf2</code> · 运行时：PHP 8.2 · view.php（缓存查询台 · 漏洞版）');
?>
<section class="card"><h2>查询结果</h2>
<div class="result">
<div class="t">发送给 127.0.0.1:11211（模拟）的内容（\r\n 逐字节可见）</div>
<pre><?php echo h(crlf2_escape($cmd)); ?></pre>
<div class="t">所见即所得（把 \r\n 当成真实换行）</div>
<pre><?php echo h($cmd); ?></pre>
<div class="t">memcached 原始回复（\r\n 逐字节可见）</div>
<pre><?php echo h(crlf2_escape($reply !== '' ? $reply : '(无回复)')); ?></pre>
<div class="t">所见即所得</div>
<pre><?php echo h($reply !== '' ? $reply : '(无回复)'); ?></pre>
</div>
<?php if ($stored): ?>
<div class="okbox">检测到 <code>STORED</code>——注入的 <code>set</code> 命令已执行，缓存被写入！
当前 <code>role = <?php echo h((string)$roleNow); ?></code>。去 <a href="admin.php">admin.php 控制室</a> 看看。</div>
<?php endif; ?>
<p style="color:var(--dim);font-size:13px">当前缓存状态：role=<code><?php echo h((string)$roleNow); ?></code>
｜ <a href="index.php">返回首页</a> ｜ <a href="reset.php">重置缓存</a> ｜ 修复对照版：<a href="fixed.php?key=announcement">fixed.php</a></p>
</section>

<section class="card"><h2>发生了什么</h2>
<p>应用把 <code>key</code> 原样拼进 <code>get &lt;key&gt;\r\n</code>。当 key 里带 <code>\r\n</code> 时（URL 里写 <code>%0d%0a</code>，PHP 解析 $_GET 时已解码成真实回车换行），
"一次查询"就裂变成了"命令序列"——上面的回显里能看到每条命令各自的回复（END / STORED …）。</p>
<p>注入写命令的语法：<code>set &lt;key&gt; &lt;flags&gt; &lt;exptime&gt; &lt;bytes&gt;\r\n&lt;定长数据块&gt;\r\n</code>，把 <code>role</code> 写成 <code>admin</code>（5 字节）即污染缓存。</p>
</section>
<?php page_bottom('crlf2');
