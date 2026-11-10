<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/init.php';

// 鉴权：只检查了"是否登录"，没有检查角色 —— 垂直越权漏洞
if (empty($_SESSION['uid'])) { header('Location: index.php'); exit; }
// 正确写法应当是：
// if (empty($_SESSION['uid']) || $_SESSION['role'] !== 'admin') { http_response_code(403); exit('Forbidden'); }

$db = db();
$users = $db->query('SELECT id, username, role FROM users ORDER BY id')->fetchAll();
$logs = $db->query('SELECT * FROM audit_log ORDER BY id DESC LIMIT 10')->fetchAll();

page_head('逻辑漏洞 2/5 · easy', '星云 OA · 管理后台', '★☆☆☆☆', '', [], []);
?>
<div class="card">
  <h2>管理后台（当前登录：<?php echo h($_SESSION['username']); ?> / <?php echo h($_SESSION['role']); ?>）</h2>
  <div class="msg ok">系统公告：管理后台搭建完成。内部庆祝口令：flag{logic2_e5e46f45}</div>
  <h2 style="margin-top:14px">用户列表</h2>
  <table>
    <tr><th>ID</th><th>用户名</th><th>角色</th></tr>
    <?php foreach ($users as $u): ?>
      <tr><td><?php echo $u['id']; ?></td><td><?php echo h($u['username']); ?></td><td><?php echo h($u['role']); ?></td></tr>
    <?php endforeach; ?>
  </table>
  <h2 style="margin-top:14px">操作审计</h2>
  <table>
    <tr><th>时间</th><th>操作人</th><th>操作</th></tr>
    <?php foreach ($logs as $l): ?>
      <tr><td><?php echo h($l['ts']); ?></td><td><?php echo h($l['actor']); ?></td><td><?php echo h($l['action']); ?></td></tr>
    <?php endforeach; ?>
  </table>
  <p class="muted"><a href="index.php">返回工作台</a></p>
</div>
<?php page_foot('logic2'); ?>
