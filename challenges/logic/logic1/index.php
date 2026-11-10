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
    $st = $db->prepare('SELECT id, username FROM users WHERE username = ? AND password = ?');
    $st->execute([$u, $p]);
    $usr = $st->fetch();
    if ($usr) {
        $_SESSION['uid'] = (int)$usr['id'];
        $_SESSION['username'] = $usr['username'];
        header('Location: index.php');
        exit;
    }
    $msg = '用户名或密码错误';
}

$uid = (int)($_SESSION['uid'] ?? 0);

page_head('逻辑漏洞 1/5 · easy', '天天快递查询系统 —— 谁动了我的订单？', '★☆☆☆☆',
 '天天快递新上线了「会员订单查询」。alice 是注册会员（账号 <code>alice</code> / 密码 <code>alice123</code>），她怀疑自己的信息被别人看到了。<b>系统宣称：每个会员只能查看自己的订单。</b>真的吗？找到本关 flag（藏在别人的订单里）。',
 ['<b>水平越权（IDOR，Insecure Direct Object Reference）</b>：接口用用户可控的 ID 直接定位资源，却只校验了"是否登录"，没有校验"这个资源是否属于当前用户"。',
  '<b>枚举对象标识</b>：订单号是连续整数（1001、1002…），改一个数字就能遍历他人数据。',
  '修复思路：查询条件必须带上归属，如 <code>WHERE id = ? AND user_id = 当前登录用户</code>，后端强制执行，绝不依赖前端隐藏入口。'],
 ['登录后你只能看到"我的订单"列表，但点进某条订单时，仔细看浏览器地址栏。',
  '订单详情页是 <code>order.php?id=1001</code>，把这个数字改成相邻的订单号会怎样？',
  '用 alice 的身份访问 <code>order.php?id=1002</code>，那是另一个会员的订单，备注里有代收暗号。']);
?>
<div class="card"><h2>天天快递 · 会员中心</h2>
<?php if (!$uid): ?>
  <?php if ($msg): ?><div class="msg err"><?php echo h($msg); ?></div><?php endif; ?>
  <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <input name="u" placeholder="用户名（试 alice）" required>
    <input name="p" type="password" placeholder="密码（试 alice123）" required>
    <button class="btn" type="submit">登录</button>
  </form>
  <p class="muted">演示账号：alice / alice123</p>
<?php else: ?>
  <p>欢迎，<b><?php echo h($_SESSION['username']); ?></b>（uid=<?php echo $uid; ?>）
     <a class="muted" href="index.php?logout=1">退出</a></p>
  <table>
    <tr><th>订单号</th><th>商品</th><th>金额</th><th>状态</th><th></th></tr>
    <?php
    $st = $db->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id');
    $st->execute([$uid]);
    foreach ($st->fetchAll() as $o) {
        echo '<tr><td>' . $o['id'] . '</td><td>' . h($o['item']) . '</td><td>' . $o['amount'] . '</td><td>' . h($o['status']) . '</td>';
        echo '<td><a href="order.php?id=' . (int)$o['id'] . '">查看详情</a></td></tr>';
    }
    ?>
  </table>
  <p class="muted">※ 出于隐私保护，你只能查看自己的订单（列表只展示属于你的记录）。</p>
<?php endif; ?>
</div>
<?php page_foot('logic1'); ?>
