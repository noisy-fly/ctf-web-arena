<?php
declare(strict_types=1);
// ============================================================
// crlf2 · index.php 首页：题目描述 + 缓存实时状态 + 知识点/提示
// 漏洞入口在 view.php（?key= 原样拼进 memcached 文本协议），
// 判定在 admin.php（读缓存里的 role），对照在 fixed.php（key 白名单）。
// ============================================================
require __DIR__ . '/init.php';
require __DIR__ . '/memcached.php';

$mem = new MiniMemcached(__DIR__ . '/data');
$ann = $mem->get('announcement');
$role = $mem->get('role');

page_top('难度：★★★★☆ 高阶 | 关卡 ID：<code>crlf2</code> · 运行时：PHP 8.2（迷你 memcached：文本协议引擎 + 文件存储）');
?>
<section class="card"><h2>题目描述</h2>
<p>星云 CDN 的页面内容走 <b>memcached</b> 缓存（内网 <code>127.0.0.1:11211</code>；本站以 <b>PHP 文本协议引擎 + 文件存储</b>模拟——
命令语义与真实 memcached 一致：一行一命令，<code>\r\n</code> 分隔，get/set/delete/flush_all，set 带定长数据块）。
运营后台有个"缓存查询台"：<a href="view.php?key=announcement">view.php?key=announcement</a> ——输入 key，应用把它拼进文本协议发查询。</p>
<p>控制室的 <a href="admin.php">admin.php</a> 按缓存里的 <code>role</code> 值放行（初始 <code>guest</code>）。应用层<b>没有任何写缓存的接口</b>。
但文本协议的每条命令，是用 <code>\r\n</code> 分隔的……</p>
<table>
<tr><th>缓存键</th><th>当前值（服务端实时）</th></tr>
<tr><td><code>announcement</code></td><td><?php echo h($ann ?? '(未命中)'); ?></td></tr>
<tr><td><code>role</code></td><td><code><?php echo h($role ?? ''); ?></code></td></tr>
</table>
<p><a href="admin.php">进入 admin.php 控制室</a> ｜ <a href="reset.php">重置缓存（恢复 role=guest）</a></p>
</section>

<section class="card"><h2>实验区 · 缓存查询台</h2>
<form method="get" action="view.php">
<label>key：<input type="text" name="key" value="announcement" size="40"></label>
<button type="submit">查询</button>
</form>
<p style="color:var(--dim);font-size:13px">查询台会把 key 原样拼进 <code>get &lt;key&gt;\r\n</code> 发给缓存服务（页面会回显发送原文与服务的原始回复）。
修复对照版（key 白名单校验）：<a href="fixed.php?key=announcement">fixed.php?key=…</a></p>
</section>

<section class="card"><h2>知识点讲解</h2>
<p>应用的查询实现（view.php 里逐字节可见）：</p>
<pre>$cmd = 'get ' . $key . "\r\n";     // key 未过滤 → \r\n 可注入第二条命令
$reply = $mem-&gt;execute($cmd);</pre>
<p><b>文本协议注入</b>：memcached / Redis / SMTP / FTP 这类"一行一命令"的协议，用 <code>\r\n</code> 做命令边界。
凡把不可信输入拼进命令的位置，都和 crlf1 的响应头同源——只是"受害者"从 HTTP 头换成了后端协议。
历史上最著名的两个场景：</p>
<ul>
<li><b>memcached/Redis 注入</b>：常经由 SSRF 的 gopher:// 打进内网（B 组 ssrf4 已演过 gopher 打 Redis）；
本题则是应用自身把用户输入拼进命令，更直接。</li>
<li><b>HTTP 响应拆分 → 缓存投毒</b>：crlf1 的近亲，注入的响应体被 CDN 缓存（SPEC 规划的缓存投毒专题）。</li>
</ul>
<p>注入 set 命令的形态（注意 set 的数据块也要用 \r\n 收尾）：</p>
<pre>get x\r\n
set role 0 0 5\r\n
admin\r\n</pre>
<p>查询台对每条命令的回复都会回显（END / STORED …），便于你观察注入是否生效。</p>
</section>

<section class="card"><h2>渐进提示</h2>
<details><summary>提示 1：先玩查询台</summary><p><code>view.php?key=announcement</code> 能看到公告；<code>view.php?key=role</code> 能看到 guest。注意 %0d%0a 会被 URL 解码成命令分隔符。</p></details>
<details><summary>提示 2：目标是什么命令</summary><p>应用没有写接口，但 memcached 有 <code>set</code>。让"查询"裂变成"查询 + 写入"：写 <code>role</code> 为 <code>admin</code>（5 字节）。</p></details>
<details><summary>提示 3：payload</summary><p><code>view.php?key=x%0d%0aset%20role%200%200%205%0d%0aadmin%0d%0a</code><br>回显应出现 <code>STORED</code>；然后访问 <code>admin.php</code>。对照：<code>fixed.php</code> 同 payload 会被 key 白名单拒绝。</p></details>
</section>
<?php page_bottom('crlf2');
