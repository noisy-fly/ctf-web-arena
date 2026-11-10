<?php
declare(strict_types=1);
require __DIR__ . '/init.php';
$me = current_user();
$db = db();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['html'], $_POST['name'])) {
    $name = (string)$_POST['name'];
    if (!preg_match('/^[A-Za-z0-9._-]{1,48}$/', $name)) {
        $msg = '攻击页名称只能包含字母/数字/./_/-（你起的名字会出现在它的 URL 里）';
    } else {
        $db->prepare('INSERT OR REPLACE INTO attacks(name, html, ts) VALUES(?,?,datetime("now","localtime"))')
           ->execute([$name, (string)$_POST['html']]);
        header('Location: view.php?name=' . urlencode($name));
        exit;
    }
}

$dave = user_by_name('dave');
$attacks = $db->query('SELECT name, ts FROM attacks ORDER BY ts DESC LIMIT 8')->fetchAll();

page_head('CSRF 2/3 · medium', '星云俱乐部 VIP 转让 —— Referer 防得住吗', '★★☆☆☆',
 '会员 <b>dave</b> 是 VIP，你不是。俱乐部上次的订阅接口被 CSRF 打穿后，开发"吸取教训"重构了 VIP 转让接口
  <code>api.php?do=transfer_vip</code>：<b>只收 POST</b>，而且<b>校验 Referer 必须包含本站域名 <code>csrf2.lab</code></b>。
  把 dave 的 VIP 转让给 <code>hacker</code>（你自己），本关 flag 就会出现。<span class="muted">（模拟受害者浏览器会以 dave 的身份访问你构造的页面）</span>',
 ['<b>Referer "包含"校验的缺陷</b>：<code>strpos($referer, \'csrf2.lab\')</code> 只要求字符串中出现子串。攻击者可控的 URL（页面名、路径、查询参数）里带上这个子串即可绕过。',
  '<b>真实世界的同类绕过</b>：可信域名的子域名（attacker.csrf2.lab）、开放 URL（跳转链）、第三方托管页的查询参数、以及"Referer 缺失即放行"的逻辑漏洞。',
  '修复思路：Referer/Origin 必须<b>解析出 host 精确匹配</b>可信域（或其父域），缺失一律拒绝；Referer 只是辅助，核心仍是 CSRF token + SameSite。'],
 ['先直接 POST 试试（不带 Referer 或带别的）：会被拒。注意错误信息把校验逻辑说得很清楚。',
  '攻击页保存后会有一个 URL：view.php?name=你起的名字。受害者访问时的 Referer 就是这个 URL——名字里能做文章。',
  '把攻击页命名为包含 <code>csrf2.lab</code> 的字符串（如 a.csrf2.lab），页面里放一个 POST 表单：action 指向转让接口，隐藏域 to=hacker。运行模拟受害者。']);
?>
<div class="card"><h2>状态面板</h2>
<table>
  <tr><th>成员</th><th>VIP</th></tr>
  <tr><td>你（hacker）</td><td><?php echo $me['vip'] ? '<b style="color:#059669">VIP</b>' : '普通会员'; ?></td></tr>
  <tr><td><b>dave（目标）</b></td><td><?php echo $dave['vip'] ? '<b>VIP</b>' : '已失去'; ?></td></tr>
</table>
<?php if ($me['vip']): ?>
  <div class="msg ok">你已是 VIP 会员！俱乐部发放安全奖励：flag{csrf2_3bb80cfd}</div>
<?php else: ?>
  <div class="msg err">任务：让 dave 在不知情中把 VIP 转让给你（to=hacker）</div>
<?php endif; ?>
</div>

<div class="card"><h2>构造你的攻击页</h2>
<?php if ($msg): ?><div class="msg err"><?php echo h($msg); ?></div><?php endif; ?>
<form method="post" style="display:grid;gap:8px">
  <div>攻击页名称（会出现在它的 URL 里）：<input name="name" size="30" value="my-attack-page" required></div>
  <textarea name="html" rows="6" style="width:100%" placeholder="攻击页 HTML(表单会以 dave 的身份提交)" required></textarea>
  <div><button class="btn" type="submit">保存攻击页</button></div>
</form>
<?php if ($attacks): ?>
  <h2 style="margin-top:14px">已保存的攻击页</h2>
  <table>
    <tr><th>名称</th><th>时间</th><th>操作</th></tr>
    <?php foreach ($attacks as $a): ?>
      <tr>
        <td><?php echo h((string)$a['name']); ?></td><td><?php echo h((string)$a['ts']); ?></td>
        <td><a href="view.php?name=<?php echo urlencode((string)$a['name']); ?>">查看</a> |
            <a href="sim.php?name=<?php echo urlencode((string)$a['name']); ?>">运行模拟受害者</a></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
</div>
<?php page_foot('csrf2');
