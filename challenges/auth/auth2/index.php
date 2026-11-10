<?php
declare(strict_types=1);
require __DIR__ . '/init.php';
$db = db();

$note = '';
// 会话固定演示:允许在登录前预设会话 ID(真实攻击:攻击者诱导受害者带着攻击者的会话 ID 去登录)
if (isset($_GET['fix'])) {
    setcookie('sess', (string)$_GET['fix'], ['path' => '', 'httponly' => false]);
    $note = '你的会话 ID 已被预设为：' . h((string)$_GET['fix']) . '（会话固定演示入口）';
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = (string)($_POST['u'] ?? '');
    $p = (string)($_POST['p'] ?? '');
    $st = $db->prepare('SELECT * FROM users WHERE username = ? AND password = ?');
    $st->execute([$u, $p]);
    $row = $st->fetch();
    if ($row) {
        if (!empty($_COOKIE['sess'])) {
            // 漏洞1(会话固定):登录成功后沿用浏览器带来的旧会话 ID,没有重新生成
            // 正确做法:登录成功必须 session_regenerate_id(true) / 换发新 token
        } else {
            // 漏洞2(可预测会话):token 就是用户名的 MD5——没有秘密成分,人人可算
            // (开发注释泄露:// token = md5(username),上线前来不及换随机版)
            setcookie('sess', md5($row['username']), ['path' => '', 'httponly' => false]);
        }
        header('Location: panel.php');
        exit;
    }
    $err = '用户名或密码错误（演示账号 guest / guest123）';
}

page_head('认证与会话 2/2 · medium', '星云内网门户 —— 你的会话，我说了算', '★★☆☆☆',
 '门户用自研的 Cookie 会话（Cookie 名 <code>sess</code>）。演示账号 <code>guest / guest123</code>，
  而管理员账号 <code>administrator</code> 的密码是一长串随机十六进制——爆破无望。
  但会话机制本身有两个坑：登录时<b>不换发新会话 ID</b>（会话固定），而且会话 token 的生成算法<b>没有秘密成分</b>（可预测）。
  设法以 administrator 的身份打开 <a href="panel.php"><b>panel.php</b></a>。',
 ['<b>会话固定（Session Fixation）</b>：登录成功后必须重新生成会话 ID。否则攻击者可以先把受害者"钉"在一个攻击者已知的会话 ID 上，受害者登录后，攻击者拿着同一个 ID 就是管理员。',
  '<b>可预测会话（Cookie 伪造）</b>：token = f(用户名) 且 f 无秘密（如 md5）。任何人都能算出 administrator 的 token——等于口令形同虚设。',
  '修复思路：token 用密码学随机（≥128 位 CSPRNG）；登录/提权时换发新 ID 并作废旧值；HttpOnly + Secure + SameSite。',
  '本关 <code>index.php?fix=任意值</code> 可以在登录前预设会话 ID，用来复现"会话固定"场景。'],
 ['先用 guest 登录，看 Cookie：sess 的值像不像某种哈希？对谁做的哈希？',
  'administrator 这个用户名是公开的（登录框报错就能试出来存在）。算一下 md5("administrator")，直接把 sess 换成它访问 panel.php。',
  '会话固定路线：先访问 <code>index.php?fix=&lt;md5(administrator)&gt;</code> 预设 Cookie，再用 guest/guest123 正常登录（服务端沿用旧 ID），然后打开 panel.php——两条路殊途同归。']);
?>
<div class="card"><h2>星云内网门户 · 登录</h2>
<?php if ($note): ?><div class="msg ok"><?php echo $note; ?></div><?php endif; ?>
<?php if ($err): ?><div class="msg err"><?php echo h($err); ?></div><?php endif; ?>
<form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
  <input name="u" value="guest" placeholder="用户名" required>
  <input name="p" type="password" value="guest123" placeholder="密码" required>
  <button class="btn" type="submit">登录</button>
</form>
<p class="muted">登录后进入 <a href="panel.php">panel.php</a>（身份完全由 Cookie: sess 决定）</p>
</div>
<?php page_foot('auth2');
