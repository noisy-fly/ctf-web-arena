<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/init.php';

page_head('杂项 Web 1/2 · easy', '星云活动中心 —— 链接会拐弯，按钮会隐身', '★☆☆☆☆',
 '同一页面演示两种"低技术含量但杀伤力十足"的漏洞：
  ①<b>开放重定向</b>：<a href="goto.php?url=https://www.baidu.com">goto.php?url=…</a> 什么地址都肯跳——钓鱼邮件最爱；
  ②<b>点击劫持（Clickjacking）</b>：<a href="vault.php">vault.php</a> 是"星云保险箱"，它没有 <code>X-Frame-Options</code>/<code>frame-ancestors</code> 防护，可以被任意网页装进透明 iframe——
  受害者以为点的是"领红包"，实际点开的是保险箱。
  打开保险箱（直接点开 <a href="attack.php">attack.php</a> 的伪装按钮试试"被劫持的点击"</a>，或访问 <a href="vault.php?open=1">vault.php?open=1</a>）即可拿到本关 flag。',
 ['<b>开放重定向</b>：跳转目标完全由 url 参数决定且无白名单。危害：钓鱼链接显示可信域名（…/goto.php?url=https://evil）骗过用户与部分邮件网关；配合 OAuth 回调还能偷授权码。',
  '<b>点击劫持</b>：敏感页面可被 iframe 嵌套且无 X-Frame-Options/CSP frame-ancestors。攻击者用透明 iframe 盖住诱导页面，受害者"亲手"点了隐藏页面的按钮——CSRF token、密码、二次验证统统失效，因为这是受害者本人真实点击。',
  '修复思路：重定向白名单（host 精确匹配）+ 中转提示页；敏感页一律 <code>X-Frame-Options: DENY</code>（或 CSP <code>frame-ancestors \'none\'</code>），对比示例见 <a href="fixed.php">fixed.php</a>。'],
 ['用 curl -i 看看 goto.php 的响应：Location 可以是任何网站吗？试试 goto.php?url=https://www.example.com',
  '用 curl -I 对比 vault.php 和 fixed.php 的响应头，谁缺了 X-Frame-Options？',
  '浏览器打开 attack.php：页面上的"领取红包"按钮其实是透明 iframe 里的保险箱解锁键——点一下，再回来看这里；或者直接 GET vault.php?open=1。']);
?>
<div class="card"><h2>功能入口</h2>
<table>
  <tr><th>页面</th><th>说明</th></tr>
  <tr><td><a href="goto.php?url=https://www.example.com">goto.php（跳转服务）</a></td><td>任意 url 参数 302 跳转 —— 漏洞①</td></tr>
  <tr><td><a href="vault.php">vault.php（保险箱）</a></td><td>敏感页，无 XFO，可被 iframe —— 漏洞②</td></tr>
  <tr><td><a href="attack.php">attack.php（点击劫持演示）</a></td><td>伪装红包页 + 透明 iframe 实战</td></tr>
  <tr><td><a href="fixed.php">fixed.php（正确姿势示例）</a></td><td>带 X-Frame-Options: DENY 的对照页</td></tr>
</table>
<?php if (!empty($_SESSION['vault_open'])): ?>
  <div class="msg ok">保险箱已开启！内部封条：flag{miscw1_428b3dfd}</div>
<?php else: ?>
  <div class="msg err">保险箱未开启（开启后 flag 显示在这里）</div>
<?php endif; ?>
</div>
<?php page_foot('miscw1');
