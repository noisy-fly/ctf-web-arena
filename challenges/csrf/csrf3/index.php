<?php
declare(strict_types=1);
require __DIR__ . '/init.php';
$me = current_user();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['html'])) {
    $db->prepare('INSERT INTO attacks(html, ts) VALUES(?, datetime("now","localtime"))')->execute([(string)$_POST['html']]);
    header('Location: view.php?id=' . (int)$db->lastInsertId());
    exit;
}

$emma = user_by_name('emma');
$attacks = $db->query('SELECT id, ts FROM attacks ORDER BY id DESC LIMIT 8')->fetchAll();

page_head('CSRF 3/3 · hard', '星云空间 —— JSON 也拦不住，CORS 还把门打开', '★★★☆☆',
 '会员 <b>emma</b> 的私密笔记存在 <code>profile.php</code>（flag 就在里面）。这个接口防护不错：
  ①要求 <code>X-Protection</code> 自定义头——跨站"简单请求"（img/表单）根本带不了自定义头；②只返回 JSON，页面读不出。
  但她的<b>隐私设置接口</b> <code>api.php?do=privacy</code> 接受 JSON 正文，<b>不校验 Content-Type</b>；
  而 emma 一旦把保护级别调成 <code>low</code>（移动端调试模式），profile 接口就会<b>反射任意 Origin 的 CORS 头且允许携带凭据</b>。
  任务：让 emma 在不知情中"降防护"，再<b>跨域读到</b>她的 profile 响应。',
 ['<b>JSON CSRF（Content-Type 信任缺失）</b>：服务端只做 <code>json_decode(请求体)</code> 却不校验 Content-Type。HTML 表单的 <code>enctype="text/plain"</code> 同样是"简单请求"（可跨站自动发送），其报文格式 <code>name=value\\r\\n</code> 可以被精心构造的 name/value 拼成合法 JSON。',
  '<b>CORS 反射任意 Origin + Allow-Credentials</b>：等于向所有网站的 JS 开放"带受害者 Cookie 读取响应"的权限。正确做法是 Origin 白名单；<code>*</code> 与凭据根本不能共存（浏览器也会拒）。',
  '<b>自定义头不是 CSRF 防线</b>：跨站请求加自定义头会触发预检（preflight），没有服务器批准就发不出去——它能挡"读"，但挡不住 text/plain 这类"写"。',
  '修复思路：Content-Type 必须严格校验（仅 application/json）+ CSRF token；CORS 用白名单、绝不反射 Origin；敏感数据接口加 SameSite。'],
 ['先用 curl 探一探：GET profile.php（不带/带 X-Protection 头）观察 403 的差别；再 POST api.php?do=privacy 一段 JSON，注意它并不管 Content-Type。',
  '写攻击：enctype=text/plain 的表单，input 的 name 写 <code>{"protection":"low","x":"</code>、value 写 <code>1"}</code>，两者拼出的正文恰是合法 JSON（text/plain 报文格式是 name=value）。',
  '组合拳：表单（降防护）+ <code>&lt;script src="profile.php"&gt;</code>（模拟受害者会带上 Origin 去读；真实浏览器里这就是 fetch credentials:include 的跨域读取）。保存后运行模拟受害者。']);
?>
<div class="card"><h2>状态面板</h2>
<table>
  <tr><th>成员</th><th>保护级别</th><th>私密笔记</th></tr>
  <tr><td>你（hacker）</td><td><?php echo h($me['protection']); ?></td><td class="muted">（无）</td></tr>
  <tr><td><b>emma（目标）</b></td><td><b><?php echo h($emma['protection']); ?></b></td><td class="muted">🔒 仅她本人可读</td></tr>
</table>
<div class="msg err">任务：让 emma 降到 low 防护并跨域读出她的笔记（flag 在模拟受害者的"跨域读取"结果里）</div>
</div>

<div class="card"><h2>构造你的攻击页</h2>
<form method="post">
  <textarea name="html" rows="7" style="width:100%" placeholder="表单(enctype=text/plain 可拼 JSON) + script(src 指向 profile.php)…" required></textarea><br>
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
<?php page_foot('csrf3');
