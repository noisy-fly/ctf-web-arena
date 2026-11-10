<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/init.php';

// 鉴权：只检查了"是否登录"
if (empty($_SESSION['uid'])) { header('Location: index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);

// 漏洞代码：按订单号查询详情，SQL 里没有任何 user_id 归属限制
// （id 已强转整数，本关不涉及 SQL 注入；缺的是"这单是不是你的"这层校验）
$st = db()->prepare('SELECT o.*, u.username AS owner, u.addr AS owner_addr
                     FROM orders o JOIN users u ON u.id = o.user_id
                     WHERE o.id = ?');
$st->execute([$id]);
$o = $st->fetch();

page_head('逻辑漏洞 1/5 · easy', '订单详情', '★☆☆☆☆', '', [], []);
?>
<div class="card">
<?php if (!$o): ?>
  <div class="msg err">订单 <?php echo $id; ?> 不存在</div>
  <a class="btn" href="index.php">返回会员中心</a>
<?php else: ?>
  <h2>订单 #<?php echo (int)$o['id']; ?> 详情</h2>
  <table>
    <tr><th>归属会员</th><td><?php echo h($o['owner']); ?></td></tr>
    <tr><th>商品</th><td><?php echo h($o['item']); ?></td></tr>
    <tr><th>金额</th><td>&yen;<?php echo h((string)$o['amount']); ?></td></tr>
    <tr><th>状态</th><td><?php echo h($o['status']); ?></td></tr>
    <tr><th>收货地址</th><td><?php echo h($o['owner_addr']); ?></td></tr>
    <tr><th>备注</th><td><?php echo h($o['note']); ?></td></tr>
  </table>
  <p class="muted"><a href="index.php">返回我的订单</a></p>
<?php endif; ?>
</div>
<?php page_foot('logic1'); ?>
