<?php
declare(strict_types=1);
// ============================================================
// crlf1 · index.php 主页（身份判定页）
// 判定（与原 Python 版同思路，防手改 Cookie 走捷径）：只有"服务端真正
// 通过 Set-Cookie 下发过"的 name=value 才算数。PHP 版把这份"下发记录"
// 保存在会话里（setlang.php 解析模拟报文中的 Set-Cookie 行写入）——
// 浏览器只保存服务端下发过的 Cookie，手改请求里的 Cookie 头没有用。
// ============================================================
session_start();
require __DIR__ . '/init.php';

$issued = (isset($_SESSION['issued']) && is_array($_SESSION['issued'])) ? $_SESSION['issued'] : [];
$isAdmin = in_array('role=admin', $issued, true);
$reqCookie = (isset($_SERVER['HTTP_COOKIE']) && is_string($_SERVER['HTTP_COOKIE'])) ? $_SERVER['HTTP_COOKIE'] : '';

page_top('难度：★★☆☆☆ 基础 | 关卡 ID：<code>crlf1</code> · 运行时：PHP 8.2（模拟报文渲染 + 真实对照端点）');
?>
<section class="card"><h2>题目描述</h2>
<p>星语小铺的"偏好设置"会把语言值写进 Cookie 再跳回首页：<a href="setlang.php?lang=zh-CN">setlang.php?lang=zh-CN</a>。</p>
<p>仓库管理页就在首页里——只有当<b>服务端真正下发过</b> <code>role=admin</code> Cookie 时才会亮出管理员口令。
正常流程里没有任何入口能设置 role。但是……那个 <code>lang</code>，服务端是<b>原样</b>拼进 Set-Cookie 响应头的。</p>
<p>HTTP 头与头之间靠 <code>\r\n</code> 分隔（CRLF）。如果用户输入能带进 CRLF，一个"值"就能裂变成"任意多的头"。</p>
<p style="color:var(--dim);font-size:13px">PHP 的 <code>header()</code>/<code>setcookie()</code> 自 5.1 起拒绝含 <code>\r\n</code> 的头值（框架层补救），应用内已无法产生真实响应头注入；
因此 <a href="setlang.php?lang=zh-CN">setlang.php</a> 把"<b>假如没有防护，你将收到的原始 HTTP 响应报文</b>"逐字节渲染出来（响应行+头+体，注入 <code>%0d%0a</code> 后报文里能看到新行/新头），
另附 <a href="setlang.php?lang=zh-CN&amp;real=1">setlang.php?real=1</a> 真实带防护的对照响应（真实 302）。</p>
<?php if ($isAdmin): ?>
<div class="okbox">👑 管理员身份确认（服务端确实下发过 role=admin）。仓库管理员口令：<b><?php echo CRLF1_FLAG; ?></b></div>
<?php else: ?>
<div class="nobox">👤 当前身份：访客（role=guest）。管理员页面只认服务端<b>真正下发过</b>的 role=admin——手改 Cookie 无效。</div>
<?php endif; ?>
<h2 style="margin-top:18px">实验区</h2>
<form method="get" action="setlang.php">
<label>语言偏好：<input type="text" name="lang" value="zh-CN" size="30"></label>
<button type="submit">保存偏好（观察响应报文）</button>
</form>
<p style="color:var(--dim);font-size:13px">curl：<code>curl -s -b jar.txt -c jar.txt "http://HOST/crlf1/setlang.php?lang=guest%0d%0aSet-Cookie:%20role=admin"</code>
（模拟报文裂变出第二个头，并记入会话的"服务端下发记录"；再 <code>curl -s -b jar.txt http://HOST/crlf1/</code> 回首页）</p>
</section>

<section class="card"><h2>当前会话的"服务端下发记录"（模拟 Cookie Jar）</h2>
<?php if ($issued): ?>
<table>
<tr><th>#</th><th>本站响应"下发过"的 Cookie</th><th>说明</th></tr>
<?php foreach ($issued as $i => $c): ?>
<tr>
<td><?php echo (int)$i + 1; ?></td>
<td><code><?php echo h((string)$c); ?></code></td>
<td><?php echo $c === 'role=admin'
    ? '<span style="color:#f87171">← 注入出来的"第二个头"！服务器"亲手"下发了它</span>'
    : '模拟报文中的 Set-Cookie 行'; ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<p style="color:var(--dim)">还没有任何记录——先去 <a href="setlang.php?lang=zh-CN">setlang.php</a> 玩一圈。</p>
<?php endif; ?>
<p style="color:var(--dim);font-size:13px">浏览器只保存"服务端响应真正下发过"的 Cookie。你本次请求带来的 Cookie 头是：
<code><?php echo h($reqCookie !== '' ? $reqCookie : '(无)'); ?></code>——判定<b>不看</b>这里，手改它没用。</p>
</section>

<section class="card"><h2>知识点讲解</h2>
<p>本关服务端拼响应头的方式（伪码，setlang.php 里逐字节还原）：</p>
<pre>$raw  = "HTTP/1.1 302 Found\r\n";
$raw .= "Set-Cookie: lang=" . $lang . "\r\n";    // lang 未过滤，\r\n 可混入
$raw .= "Location: /crlf1/\r\n";
$raw .= "\r\n";</pre>
<p>当 <code>lang=guest\r\nSet-Cookie: role=admin</code> 时，客户端实际收到：</p>
<pre><?php echo h("HTTP/1.1 302 Found\r\nSet-Cookie: lang=guest\r\nSet-Cookie: role=admin        ← 注入出来的\"第二个头\"\r\nLocation: /crlf1/\r\n"); ?></pre>
<p><b>CRLF 注入</b>（HTTP 响应头注入/响应拆分）的根因：把不可信输入拼进"按 CRLF 分隔"的协议文本（HTTP 头、memcached 文本协议、日志头行……）。
危害包括：注入 Set-Cookie 冒充身份、伪造 Location、注入 CSP/X-Frame-Options 破坏防护、缓存投毒（下一关）。</p>
<p><b>为什么现在少见又仍要学</b>：PHP <code>header()</code>、Python/Werkzeug、Node 的 <code>setHeader</code> 都已拒绝含 <code>\r\n</code> 的头值——应用层被框架堵死；
但自研网关、Nginx/Lua 拼上游、代理链，以及<b>头值之外的拼装场景</b>（重定向 Location 拼接、日志注入、缓存键拼接、邮件头）里，CRLF 依旧真实存在。
本关用"模拟报文"还原的，正是框架之外的那类位置。</p>
</section>

<section class="card"><h2>渐进提示</h2>
<details><summary>提示 1：观察正常响应报文</summary>
<p><code>setlang.php?lang=zh-CN</code>——渲染出来的报文里 Set-Cookie 的值就是你的输入。试试输入里能不能塞进"另起一行"。</p></details>
<details><summary>提示 2：CRLF 怎么进 URL</summary><p>URL 里 <code>%0d%0a</code> 会被服务端 URL 解码成 <code>\r\n</code>（PHP 解析 <code>$_GET</code> 时已自动解码）。空格用 <code>%20</code>（或 +）。</p></details>
<details><summary>提示 3：payload</summary><p><code>setlang.php?lang=guest%0d%0aSet-Cookie:%20role=admin</code>——模拟报文裂变出第二个 Set-Cookie，并记入"服务端下发记录"，然后回到本页——管理员口令出现。对照修复版：<code>setlang.php?lang=…&amp;real=1</code>（真实响应，CRLF 已过滤）。</p></details>
</section>
<?php page_bottom('crlf1');
