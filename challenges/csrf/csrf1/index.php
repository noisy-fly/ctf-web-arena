<?php
declare(strict_types=1);
require __DIR__ . '/init.php';
$me = current_user();
$db = db();

// 保存攻击页
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['html'])) {
    $db->prepare('INSERT INTO attacks(html, ts) VALUES(?, datetime("now","localtime"))')->execute([(string)$_POST['html']]);
    $id = (int)$db->lastInsertId();
    header('Location: view.php?id=' . $id);
    exit;
}

$cathy = user_by_name('cathy');
$attacks = $db->query('SELECT id, ts FROM attacks ORDER BY id DESC LIMIT 8')->fetchAll();

page_head('CSRF 1/3 · easy', '星云周刊订阅站 —— 让别人"自愿"订阅', '★☆☆☆☆',
 '你是攻击者 <code>hacker</code>。星云周刊的订阅接口是 <code>api.php?do=subscribe</code>——<b>GET 请求、无 token、无 Referer 校验、无 SameSite</b>。
  会员 <b>cathy</b> 此刻正登录着（内置"模拟受害者浏览器"，她只会机械地加载你构造的网页）。
  你的订阅不算数——<b>让 cathy 在不知情中完成订阅</b>，本关 flag 就会出现。<span class="muted">（真实世界里，这就是改密/转账/关注接口的裸奔形态）</span>',
 ['<b>CSRF（跨站请求伪造）</b>：受害者已登录 A 站，访问了攻击者的 B 页面；浏览器会自动带上 A 站 Cookie 发起 B 页面里构造的请求——"cookie 能证明请求来自用户的浏览器，不能证明用户知情"。',
  '<b>GET 做状态变更</b>：把订阅/删除等写操作放在 GET 上，一个 <code>&lt;img src&gt;</code> 就能触发，连表单都不需要。',
  '修复思路：写操作只用 POST + CSRF token（一次性、绑定会话）+ <code>SameSite=Lax/Strict</code> Cookie + 校验 Origin/Referer。',
  '<b>本关模拟器说明</b>：sim.php 会以 cathy 的 Cookie 逐个请求攻击页里的 <code>img/iframe src</code> 与表单（相当于真实浏览器自动发起的跨站请求；真实页面通常再加 JS 自动提交，模拟器无需执行 JS）。'],
 ['先自己订阅一次试试（GET api.php?do=subscribe&email=xxx）——注意"你订阅了不算数"，目标是 cathy。',
  '攻击页只需要一行：一个 img 标签，src 指向订阅接口，email 参数随便填。保存后让模拟受害者访问它。',
  '<code>&lt;img src="api.php?do=subscribe&amp;email=pwn@evil.example"&gt;</code> —— 保存 → 运行模拟受害者 → cathy 的订阅状态变化 → flag。']);
?>
<div class="card"><h2>状态面板</h2>
<table>
  <tr><th>成员</th><th>订阅状态</th></tr>
  <tr><td>你（hacker）</td><td><?php echo $me['subscribed'] ? '已订阅' : '未订阅'; ?></td></tr>
  <tr><td><b>cathy（目标）</b></td><td><?php echo $cathy['subscribed'] ? '<b style="color:#059669">已订阅</b>' : '未订阅'; ?></td></tr>
</table>
<?php if ($cathy['subscribed']): ?>
  <div class="msg ok">cathy 已被"自愿"订阅！市场部发放渗透测试奖励：flag{csrf1_40854ad6}</div>
<?php else: ?>
  <div class="msg err">任务：让 cathy 订阅（她的订阅状态 = 你的 flag）</div>
<?php endif; ?>
</div>

<div class="card"><h2>构造你的攻击页（受害者将访问它）</h2>
<form method="post">
  <textarea name="html" rows="6" style="width:100%" placeholder="在此粘贴 HTML（img 标签 / 表单…），受害者浏览器会自动发起其中指向本站的请求" required></textarea><br>
  <button class="btn" type="submit">保存攻击页</button>
</form>
<?php if ($attacks): ?>
  <h2 style="margin-top:14px">已保存的攻击页</h2>
  <table>
    <tr><th>#</th><th>时间</th><th>操作</th></tr>
    <?php foreach ($attacks as $a): ?>
      <tr>
        <td><?php echo $a['id']; ?></td><td><?php echo h((string)$a['ts']); ?></td>
        <td><a href="view.php?id=<?php echo $a['id']; ?>">查看</a> |
            <a href="sim.php?attack=<?php echo $a['id']; ?>">运行模拟受害者</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
</div>
<?php page_foot('csrf1'); ?>
