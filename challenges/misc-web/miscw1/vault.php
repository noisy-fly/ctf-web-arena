<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/init.php';

// 注意:本响应 intentionally 不携带 X-Frame-Options / CSP frame-ancestors —— 点击劫持漏洞
if (isset($_GET['open'])) { $_SESSION['vault_open'] = 1; }
$opened = !empty($_SESSION['vault_open']);

page_head('杂项 Web 1/2 · easy', '星云保险箱', '★☆☆☆☆', '', [], []);
?>
<div class="card" style="text-align:center">
<?php if ($opened): ?>
  <h2>保险箱已开启</h2>
  <div class="msg ok" id="vault-flag">内部封条：flag{miscw1_428b3dfd}</div>
<?php else: ?>
  <h2>保险箱已上锁</h2>
  <p class="muted">点击下方巨大按钮解锁（这题按钮故意做得这么大——为了让透明 iframe 更容易盖住它）</p>
  <a href="vault.php?open=1" style="display:block;margin:30px 0;padding:60px;font-size:26px;background:#2563eb;color:#fff;border-radius:14px;text-decoration:none;font-weight:700">
      🔓 点此解锁保险箱
  </a>
<?php endif; ?>
<p class="muted"><a href="index.php">返回活动中心</a></p>
</div>
<?php page_foot('miscw1');
