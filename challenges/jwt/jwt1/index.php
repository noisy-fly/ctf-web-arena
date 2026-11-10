<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

// 服务端签名密钥(强随机,本关不需要也不应该被爆破出来)
const SECRET = 'x9Kj2LmPq7RtVb4Zw8Cs3Df6Gh1Jk5Nn';

$token = $_COOKIE['token'] ?? '';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = (string)($_POST['u'] ?? '');
    $p = (string)($_POST['p'] ?? '');
    if ($u === 'guest' && $p === 'guest123') {
        $token = jwt_make_hs256(['user' => 'guest', 'role' => 'user', 'iat' => time()], SECRET);
        setcookie('token', $token, ['path' => '', 'httponly' => false]);
        header('Location: index.php');
        exit;
    }
    $msg = '账号或密码错误（演示账号 guest / guest123）';
}

page_head('JWT 1/4 · easy', '会员站 —— 无人签名的通行证', '★☆☆☆☆',
 '本站用 JWT 做登录态：登录后 Cookie 里会放一个 <code>token</code>（格式 <code>header.payload.signature</code>）。
  用演示账号 <code>guest / guest123</code> 登录后，去 <a href="admin.php"><b>admin.php</b></a> 看看——普通用户会被拒。
  设法让服务端<b>承认你是 admin</b>，即可在 admin.php 拿到 flag。',
 ['<b>JWT 结构</b>：<code>Base64URL(header).Base64URL(payload).signature</code>。前两段只是编码，<b>任何人都能解码和伪造</b>；防篡改完全依赖第三段签名。',
  '<b>alg=none 攻击</b>：JWT 规范允许 "none" 算法（无签名）。若服务端校验时<b>信任 token 头里声明的算法</b>，攻击者把 alg 改成 none、payload 改成 admin、签名字段留空，即可伪造任意身份。这是 2015 年爆出的经典漏洞（CVE-2015-9235），很多 JWT 库当年中招。',
  '修复思路：服务端<b>硬编码期望的算法</b>（如只接受 HS256），拒绝 none；密钥用强随机值。'],
 ['先把 Cookie 里的 token 复制出来，分别 Base64 解码前两段，看看 header 和 payload 长什么样。',
  '注意 header 里的 "alg" 字段——如果服务端按 token 自己声明的算法来校验，把它改成 "none" 会发生什么？',
  '手工构造：header=<code>{"alg":"none","typ":"JWT"}</code>，payload=<code>{"user":"admin","role":"admin"}</code>，签名留空，拼成 <code>header.payload.</code>（注意末尾有个点），替换 Cookie 后访问 admin.php。']);
?>
<div class="card"><h2>会员站</h2>
<?php if ($msg): ?><div class="msg err"><?php echo h($msg); ?></div><?php endif; ?>
<?php if (!$token): ?>
  <form method="post" style="display:flex;gap:8px;align-items:center">
    <input name="u" value="guest" placeholder="账号">
    <input name="p" type="password" value="guest123" placeholder="密码">
    <button class="btn" type="submit">登录</button>
  </form>
<?php else: ?>
  <div class="msg ok">已登录（JWT 登录态）</div>
  <p>你的 token（Cookie: token）：</p>
  <pre><?php echo h($token); ?></pre>
  <?php
  [$hd, $pl, $sg] = jwt_parse($token) ?? [[], [], ''];
  echo '<p>header 解码：<code>' . h(json_encode($hd)) . '</code></p>';
  echo '<p>payload 解码：<code>' . h(json_encode($pl)) . '</code></p>';
  ?>
  <p><a class="btn" href="admin.php">进入 admin.php（管理区）</a> &nbsp; <a class="muted" href="index.php?logout=1">退出</a></p>
<?php endif; ?>
</div>
<?php
if (isset($_GET['logout'])) { setcookie('token', '', ['expires' => time() - 3600]); }
page_foot('jwt1');
