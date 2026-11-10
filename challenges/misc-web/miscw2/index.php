<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/init.php';

$_SESSION['visited'] = true;
$issued = (isset($_SESSION['issued']) && is_array($_SESSION['issued'])) ? $_SESSION['issued'] : [];
$saved = (isset($_GET['saved']) && !is_array($_GET['saved']) && $_GET['saved'] === '1');

page_head('杂项 Web 2/2 · medium', '星云留言板 —— 会拐弯的响应头', '★★☆☆☆',
 '留言板的“记住我的称呼”功能调用了 <a href="setname.php?name=Bob">setname.php?name=…</a>：服务端把<b>你提交的 name 参数原样拼进响应头</b> <code>Set-Cookie: visitor=…</code> 后返回 302。' .
 'PHP 的 <code>header()</code>/<code>setcookie()</code> 会拒绝含 <code>\r\n</code> 的头值（这是框架对历史漏洞的“补救”），所以本关把“<b>假如没有防护，你将收到的原始 HTTP 响应报文</b>”<b>逐字节渲染</b>出来（响应行+头+体）——' .
 '注入 <code>%0d%0a</code> 后，渲染报文里就会“折行”出现伪造头（如 <code>Set-Cookie: role=admin</code>）。' .
 '管理入口 <a href="admin.php">admin.php</a> 只认可<b>由本站响应真正下发过</b>的 <code>role=admin</code> Cookie（浏览器只保存服务端下发的 Cookie——自己用开发者工具手改的可不算）。' .
 '想办法让“服务器亲手”给你发一份管理员 Cookie。附：<a href="setname.php?name=Bob&amp;real=1">setname.php?real=1</a> 是带白名单防护的<b>真实</b>响应对照。',
 ['<b>CRLF 注入 / HTTP 响应拆分</b>：HTTP 头以 <code>\r\n</code> 分隔。用户输入未过滤就进入响应头时，<code>%0d%0a</code> 可以“折行”注入任意头（这里是第二条 Set-Cookie），甚至注入完整响应体。',
  '<b>现实危害</b>：Set-Cookie 注入 → 会话固定/Cookie tossing（子域覆盖主域 Cookie）；配合代理缓存 → 缓存投毒；老版本还能拆分出第二个完整响应。',
  '<b>为什么现在少见</b>：现代框架（PHP header()、Werkzeug、Node）在 API 层拒绝含换行的头值。但“自研网关/手写响应/旧式 CGI”依旧会踩坑——所以输入校验永远是第一道防线。本关 real=1 提供真实防护对照。',
  '修复：头值只允许白名单字符（如 <code>^[A-Za-z0-9._-]{1,32}$</code>），或对输入做 URL 编码后再放入 Set-Cookie。'],
 ['先访问 <code>setname.php?name=Bob</code> 观察模拟报文：name 出现在哪一行？再用 <code>curl -i</code> 对比本页的真实响应头。',
  'URL 编码的回车换行是 <code>%0d%0a</code>。在 name 后面接上 <code>%0d%0aSet-Cookie:%20role=admin</code>，再看模拟报文多出了哪一行。',
  '注入成功后（同一会话，curl 记得 <code>-b/-c</code> 保存 Cookie）访问 <code>admin.php</code> —— 服务端记录过它“下发过 role=admin”，管理入口即对你开放。']);
?>
<div class="card"><h2>留言板 · 记住我的称呼</h2>
<?php if ($saved): ?><div class="msg ok">称呼已保存（这是 setname.php?real=1 那条真实 302 跳回来的）。</div><?php endif; ?>
<form method="get" action="setname.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
<input name="name" value="Bob" style="width:200px" placeholder="称呼">
<button class="btn" type="submit">设置称呼（观察响应）</button></form>
<p class="muted">表单以 GET 提交到 setname.php?name=…；也可以直接改 URL 里的 name 参数玩。</p>
<table>
  <tr><th>页面</th><th>说明</th></tr>
  <tr><td><a href="setname.php?name=Bob">setname.php（漏洞模拟）</a></td><td>把 name 逐字节拼进“无防护的原始响应报文”并渲染 —— 漏洞核心</td></tr>
  <tr><td><a href="setname.php?name=Bob&amp;real=1">setname.php?real=1（真实对照）</a></td><td>带白名单防护的真实 PHP 响应（真实 302 / 真实 Set-Cookie / 400 拒绝）</td></tr>
  <tr><td><a href="admin.php">admin.php（管理入口）</a></td><td>只认“本站响应真正下发过”的 role=admin</td></tr>
</table>
<h2 style="margin-top:16px">当前会话的 Cookie Jar（服务端视角）</h2>
<?php if ($issued): ?>
<div class="msg ok">本站响应已向你下发过：
<?php foreach ($issued as $c) { echo '<code>' . h((string)$c) . '</code>&nbsp;'; } ?></div>
<?php else: ?>
<div class="msg err">还没有任何记录——先去 setname.php 玩一圈。</div>
<?php endif; ?>
<p class="muted">提醒：浏览器只保存“服务端响应真正下发过”的 Cookie；自己手改请求里的 role=admin 骗不过 admin.php。</p>
</div>
<?php page_foot('miscw2');
