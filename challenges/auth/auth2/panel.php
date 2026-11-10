<?php
declare(strict_types=1);
require __DIR__ . '/init.php';
$db = db();

// 身份 = 反查 md5(username) 与 Cookie 相等的用户
$sess = (string)($_COOKIE['sess'] ?? '');
$me = null;
foreach ($db->query('SELECT * FROM users') as $u) {
    if (md5((string)$u['username']) === $sess) { $me = $u; break; }
}

page_head('认证与会话 2/2 · medium', '内网门户 · 控制面板', '★★☆☆☆', '', [], []);
?>
<div class="card">
<?php if (!$me): ?>
  <h2>未知会话</h2>
  <div class="msg err">Cookie: sess=<?php echo h($sess ?: '(空)'); ?> 不对应任何用户。<a href="index.php">去登录</a></div>
<?php elseif ($me['username'] === 'administrator'): ?>
  <h2>管理员控制台</h2>
  <div class="msg ok">欢迎，administrator！核心配置备份口令：flag{auth2_576d21a9}</div>
<?php else: ?>
  <h2>普通用户面板</h2>
  <div class="msg ok">欢迎，<?php echo h((string)$me['username']); ?>。这里什么也没有——管理员的东西在 administrator 会话里。</div>
<?php endif; ?>
</div>
<?php page_foot('auth2');
