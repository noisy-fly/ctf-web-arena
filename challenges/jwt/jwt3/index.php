<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
require __DIR__ . '/keys.php';

$token = $_COOKIE['token'] ?? '';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = (string)($_POST['u'] ?? '');
    $p = (string)($_POST['p'] ?? '');
    if ($u === 'guest' && $p === 'guest123') {
        $token = jwt_make_rs256(['user' => 'guest', 'role' => 'user', 'iat' => time()], PRIV_PEM);
        setcookie('token', $token, ['path' => '', 'httponly' => false]);
        header('Location: index.php');
        exit;
    }
    $msg = '账号或密码错误（演示账号 guest / guest123）';
}

page_head('JWT 3/4 · hard', '会员站 3 —— 公钥怎么成了密码', '★★★☆☆',
 '吸取教训后，本站升级到 <b>RS256 非对称签名</b>：私钥签名、公钥验签，你看得到公钥也伪造不了签名——理论上如此。
  演示账号 <code>guest / guest123</code>，flag 在 <a href="admin.php">admin.php</a>。公钥公开下载：<a href="pubkey.php">pubkey.php</a>。
  想想：<b>如果服务端"验签用哪个密钥"取决于 token 里声明的算法</b>，会发生什么？',
 ['<b>RS256 vs HS256</b>：RS256 用私钥签名、公钥验签（非对称）；HS256 收发双方共用一个密钥（对称）。',
  '<b>算法混淆（Algorithm Confusion）</b>：老版本 JWT 库按 token 头部的 alg 字段选择验签方式。攻击者把 alg 改成 HS256，并用<b>公开的 RSA 公钥作为 HMAC 密钥</b>签名——服务端"用公钥当 HMAC 密钥验签"恰好通过。公钥本来就是公开的，于是任何人都能签发合法 token。（此为 jsonwebtoken/node-jws 等库的历史真实漏洞模式）',
  '修复思路：服务端硬编码唯一算法与唯一密钥类型；RS256 体系绝不允许 HS256；公钥按 kid 白名单管理。'],
 ['先确认常规手段失效：none 不行、HS256 弱密钥爆破也不行（这次的私钥是 2048 位随机生成的）。',
  '下载公钥（pubkey.php）。服务端验签逻辑是：alg=RS256 就用 RSA 公钥验，alg=HS256 就用……同一个"密钥"做 HMAC。公钥本身当 HMAC 密钥会怎样？',
  '构造 header={"alg":"HS256"}、payload={"user":"admin","role":"admin"}，用公钥的 PEM 全文作为 HMAC-SHA256 的 key 计算签名，替换 Cookie 访问 admin.php。']);
?>
<div class="card"><h2>会员站 3（RS256）</h2>
<?php if ($msg): ?><div class="msg err"><?php echo h($msg); ?></div><?php endif; ?>
<?php if (!$token): ?>
  <form method="post" style="display:flex;gap:8px;align-items:center">
    <input name="u" value="guest" placeholder="账号">
    <input name="p" type="password" value="guest123" placeholder="密码">
    <button class="btn" type="submit">登录</button>
  </form>
<?php else: ?>
  <div class="msg ok">已登录（JWT / RS256，私钥从不出现在验签路径）</div>
  <p>你的 token：</p>
  <pre><?php echo h($token); ?></pre>
  <p class="muted">公钥下载（验签公开材料）：<a href="pubkey.php">pubkey.php</a></p>
  <p><a class="btn" href="admin.php">进入 admin.php（管理区）</a> &nbsp; <a class="muted" href="index.php?logout=1">退出</a></p>
<?php endif; ?>
</div>
<?php
if (isset($_GET['logout'])) { setcookie('token', '', ['expires' => time() - 3600]); }
page_foot('jwt3');
