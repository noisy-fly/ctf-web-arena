<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/init.php';
$db = db();

if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim((string)($_POST['u'] ?? ''));
    $p = (string)($_POST['p'] ?? '');
    $st = $db->prepare('SELECT id, username, role FROM users WHERE username = ? AND password = ?');
    $st->execute([$u, $p]);
    $usr = $st->fetch();
    if ($usr) {
        $_SESSION['uid'] = (int)$usr['id'];
        $_SESSION['username'] = $usr['username'];
        $_SESSION['role'] = $usr['role'];
        header('Location: index.php');
        exit;
    }
    $msg = '用户名或密码错误';
}

page_head('逻辑漏洞 2/5 · easy', '星云办公系统（OA）—— 普通员工的大门', '★☆☆☆☆',
 '星云办公系统（OA）演示站。你是新入职员工 <code>player</code>（密码 <code>player123</code>）。入职培训 PPT 里说：<b>"管理后台 admin.php 仅限管理员 boss 访问，导航里没有入口，所以普通员工进不去。"</b>——"导航里没有" 等于 "权限控制" 吗？想办法进管理后台拿到本关 flag。',
 ['<b>垂直越权（权限提升）</b>：低权限用户直接访问高权限接口/页面。与 logic1 的水平越权相对应：一个横向冒充同事，一个纵向冒充上级。',
  '<b>安全失效的错觉</b>："前端不显示入口 = 没有权限" 是常见错误认知；真正的权限校验必须发生在服务端。',
  '修复思路：每个受保护接口在服务端校验 <code>$_SESSION[\'role\'] === \'admin\'</code>，默认拒绝。'],
 ['登录后看看页面源代码（右键查看源代码），前端注释里常藏着"内部入口"。',
  '导航栏没有"管理后台"按钮，但培训文档提到过文件名：admin.php。直接在地址栏输入它。',
  '用 player 的会话直接访问 admin.php —— 服务端只检查了"是否登录"，没检查"是否是管理员"。']);
?>
<div class="card"><h2>星云 OA · 工作台</h2>
<?php if (empty($_SESSION['uid'])): ?>
  <?php if ($msg): ?><div class="msg err"><?php echo h($msg); ?></div><?php endif; ?>
  <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <input name="u" placeholder="用户名" required>
    <input name="p" type="password" placeholder="密码" required>
    <button class="btn" type="submit">登录</button>
  </form>
  <p class="muted">员工演示账号：player / player123（boss 是管理员，但密码可不好猜）</p>
<?php else: ?>
  <p>欢迎，<b><?php echo h($_SESSION['username']); ?></b>
     <span class="muted">（角色：<?php echo h($_SESSION['role']); ?>）</span>
     <a class="muted" href="index.php?logout=1">退出</a></p>
  <ul>
    <li>我的考勤：本月全勤 ✓</li>
    <li>我的工资条：每月 5 日发放</li>
    <li>内部论坛：<span class="muted">维护中</span></li>
  </ul>
  <!-- 导航暂未对普通员工开放管理入口；管理后台在 admin.php，仅 boss 可用（前端先隐藏入口，后端权限二期再做 —— 外包开发留言） -->
  <p class="muted">提示：功能入口只是"没显示"，不代表"不存在"。</p>
<?php endif; ?>
</div>
<?php page_foot('logic2'); ?>
