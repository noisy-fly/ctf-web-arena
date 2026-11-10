<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/init.php';
$db = db();
$msg = '';

// 本 demo 所有玩家共用一个钱包（教学环境），reset.php 可一键恢复
$db->exec('INSERT OR IGNORE INTO users(id, username, balance) VALUES(1,\'you\',100)');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'buy') {
    $pid  = (int)($_POST['pid'] ?? 0);
    $qty  = (float)($_POST['qty'] ?? 1);     // 数量：客户端可控
    $price= (float)($_POST['price'] ?? 0);   // ←←← 漏洞：单价也由前端提交，后端直接采信用来结算
    $prod = $db->prepare('SELECT * FROM products WHERE id=?');
    $prod->execute([$pid]);
    $prod = $prod->fetch();
    if (!$prod) {
        $msg = '商品不存在';
    } else {
        $bal = (float)$db->query('SELECT balance FROM users WHERE id=1')->fetch()['balance'];
        $total = $price * $qty;
        if ($bal < $total) {                  // 负数 total 恒小于余额 → 负数量反而"充值"
            $msg = '余额不足：当前 ' . $bal . ' 星币，应付 ' . $total;
        } else {
            $db->prepare('UPDATE users SET balance = balance - ? WHERE id=1')->execute([$total]);
            $db->prepare('INSERT INTO orders(product_id, qty, unit_price, total, ts) VALUES(?,?,?,?,datetime("now","localtime"))')
               ->execute([$pid, $qty, $price, $total]);
            $msg = '下单成功！购买 ' . $prod['name'] . ' x ' . $qty . '，实付 ' . $total . ' 星币。';
        }
    }
}

$bal = $db->query('SELECT balance FROM users WHERE id=1')->fetch()['balance'];
$products = $db->query('SELECT * FROM products ORDER BY id')->fetchAll();
$orders = $db->query('SELECT o.*, p.name FROM orders o LEFT JOIN products p ON p.id=o.product_id ORDER BY o.id DESC LIMIT 10')->fetchAll();

page_head('逻辑漏洞 4/5 · medium', '星云积分商城 —— 价格谁说了算？', '★★☆☆☆',
 '你有 <b>100</b> 星币，商城最贵的商品是 <b>FLAG 兑换券（1337 星币）</b>。按规则你根本买不起它。
  但下单请求是浏览器发给服务器的——<b>商品、数量、单价都在请求里</b>。试着让这单"便宜到买得起"，买到 FLAG 兑换券即可在本页下方看到 flag。买崩了可访问 <a href="reset.php">reset.php</a> 重置。',
 ['<b>金额/数量篡改</b>：服务端信任客户端提交的价格/数量/折扣等业务参数。价格必须服务端按 pid 反查，数量必须校验为正整数。',
  '<b>负数与类型陷阱</b>：<code>total = price * qty</code> 不校验符号时，负数量会让"扣款"变成"充值"；浮点金额在生产中应改为整数分 + Decimal。',
  '修复思路：服务端定价（<code>SELECT price FROM products WHERE id=?</code>），数量 <code>if ($qty <= 0 || $qty != (int)$qty) 拒绝</code>，金额用整数运算。'],
 ['打开浏览器开发者工具的 Network 面板，点一次购买，看看请求体里都带了什么字段。',
  '表单里有个隐藏 input 叫 price（右键查看源代码能看到）。改小它，或者改大你的余额——注意数量 qty 填负数会发生什么。',
  'POST 提交 pid=1、qty=1、price=1（甚至 0.01），余额足够后下单，订单列表里 FLAG 兑换券就是 flag。']);
?>
<div class="card"><h2>积分商城</h2>
<div class="msg ok">当前余额：<b><?php echo h((string)$bal); ?></b> 星币</div>
<?php if ($msg): ?><div class="msg err"><?php echo h($msg); ?></div><?php endif; ?>
<table>
  <tr><th>商品</th><th>标价</th><th>购买</th></tr>
  <?php foreach ($products as $p): ?>
  <tr>
    <td><?php echo h($p['name']); ?></td>
    <td><?php echo $p['price']; ?> 星币</td>
    <td>
      <form method="post" style="display:flex;gap:6px;align-items:center">
        <input type="hidden" name="do" value="buy">
        <input type="hidden" name="pid" value="<?php echo $p['id']; ?>">
        <!-- 前端把"页面上的价格"一并提交，后端图省事直接用它结算（漏洞根源） -->
        <input type="hidden" name="price" value="<?php echo $p['price']; ?>">
        <input name="qty" value="1" style="width:56px" title="数量">
        <button class="btn" type="submit">购买</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<h2 style="margin-top:16px">最近订单</h2>
<table>
  <tr><th>#</th><th>商品</th><th>数量</th><th>成交单价</th><th>实付</th><th>时间</th><th>内容</th></tr>
  <?php foreach ($orders as $o): ?>
  <tr>
    <td><?php echo $o['id']; ?></td><td><?php echo h((string)$o['name']); ?></td>
    <td><?php echo h((string)$o['qty']); ?></td><td><?php echo h((string)$o['unit_price']); ?></td>
    <td><?php echo h((string)$o['total']); ?></td><td><?php echo h((string)$o['ts']); ?></td>
    <td><?php echo $o['product_id'] == 1 ? '<code>flag{logic4_033a28a4}</code>' : '<span class="muted">—</span>'; ?></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php page_foot('logic4'); ?>
