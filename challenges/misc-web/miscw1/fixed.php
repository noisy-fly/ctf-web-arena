<?php
declare(strict_types=1);
// 正确姿势示例:敏感页面一律拒绝被 iframe 嵌套
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'");
require __DIR__ . '/init.php';
page_head('杂项 Web 1/2 · easy', '加了防护的示例页（fixed.php）', '★☆☆☆☆', '', [], []);
?>
<div class="card">
  <h2>本页携带 X-Frame-Options: DENY 与 CSP frame-ancestors 'none'</h2>
  <p>用 <code>curl -I</code> 对比本页与 vault.php 的响应头；再试着用 <code>&lt;iframe src="fixed.php"&gt;</code> 嵌套本页——浏览器会拒绝渲染。</p>
  <pre>&lt;iframe src="fixed.php"&gt;&lt;/iframe&gt;  →  浏览器控制台报错 "Refused to display … in a frame"</pre>
  <p class="muted"><a href="index.php">返回活动中心</a></p>
</div>
<?php page_foot('miscw1');
