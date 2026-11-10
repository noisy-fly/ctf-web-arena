<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/init.php';
$db = db();

$lastSql = '';
$err = '';
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = (string)($_POST['u'] ?? '');
    $p = (string)($_POST['p'] ?? '');
    // 漏洞代码:用户输入直接拼接进 SQL(字符串拼接,非参数化查询)
    $sql = "SELECT * FROM users WHERE username='$u' AND password='$p'";
    $lastSql = $sql;
    try {
        $row = $db->query($sql)->fetch();
    } catch (Throwable $e) {
        $row = false;
        $err = 'SQL 执行出错：' . $e->getMessage();
    }
    if ($row) {
        $_SESSION['uid'] = (int)$row['id'];
        $_SESSION['username'] = (string)$row['username'];
    } else {
        $err = $err ?: '登录失败：用户名或密码错误';
    }
}

$me = $_SESSION['username'] ?? null;
page_head('认证与会话 1/2 · easy', '星云后台登录 —— 一把钥匙开所有的锁', '★☆☆☆☆',
 '某后台登录框。系统里有个 <code>admin</code> 账号（运维主管，flag 就在他登录后的面板里）。
  你不知道密码？没关系——<b>弱口令</b>和<b>万能密码</b>总有一款适合它。两条路都能拿到 flag。',
 ['<b>弱口令</b>：人类倾向用易记密码（admin123、123456、公司名+年份）。攻击成本≈零，永远排在打穿系统的第一步。',
  '<b>万能密码（SQL 注入）</b>：登录 SQL 由用户输入拼接而成。提交 <code>admin\' -- </code> 后语句变成 <code>…WHERE username=\'admin\' -- \' AND password=…</code>，密码条件被注释掉，直接以 admin 身份登录。',
  '修复思路：参数化查询（prepare + bind，输入永远不会改变 SQL 结构）；密码加盐哈希存储（password_hash）；登录失败限速；强制强口令策略。',
  '本题会把"本次执行的 SQL"打印在登录框下方，方便你观察注入后的语句形状——真实环境请不要这样回显。'],
 ['先试试弱口令：admin 的密码大概是用户名加点什么？（admin123 / admin@123 / 123456…）。',
  '万能密码：用户名填 <code>admin\' -- </code>（注意 -- 后面有个空格），密码随便填。观察下方打印的 SQL 变成了什么样。',
  "更多姿势：用户名 <code>' OR 1=1 -- </code>（返回表中第一行,正好是 admin）；密码框填 <code>' OR '1'='1</code> 也可以。登录成功后的面板里有 flag。"]);
?>
<div class="card"><h2>星云后台 · 登录</h2>
<?php if ($me): ?>
  <?php if ($me === 'admin' || $me === 'superadmin'): ?>
    <div class="msg ok">欢迎回来，<?php echo h($me); ?>！运维面板初始化口令：flag{auth1_030a88b8}</div>
  <?php else: ?>
    <div class="msg ok">欢迎，<?php echo h($me); ?>（普通账号,flag 在 admin 的登录态里）</div>
  <?php endif; ?>
  <p class="muted"><a href="index.php?logout=1">退出登录</a></p>
<?php else: ?>
  <?php if ($err): ?><div class="msg err"><?php echo h($err); ?></div><?php endif; ?>
  <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input name="u" placeholder="用户名" required>
    <input name="p" type="password" placeholder="密码" required>
    <button class="btn" type="submit">登录</button>
  </form>
<?php endif; ?>
<?php if ($lastSql): ?>
  <p class="muted">本次执行的 SQL（教学回显）：</p>
  <pre><?php echo h($lastSql); ?></pre>
<?php endif; ?>
</div>
<?php page_foot('auth1');
