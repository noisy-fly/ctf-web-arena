<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

// 密钥是 64 位高熵随机串,爆破无望——本题的重点不在密钥
const SECRET = 'vT8xQ2wZ5nR7bK3mY9cP1dJ6hF4sL0gUeA2oIi8uUyTtRreWwq';

$token = $_COOKIE['token'] ?? '';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = (string)($_POST['u'] ?? '');
    $p = (string)($_POST['p'] ?? '');
    if ($u === 'guest' && $p === 'guest123') {
        $token = jwt_make_hs256(['user' => 'guest', 'role' => 'user', 'iat' => time(), 'exp' => time() + 600], SECRET);
        setcookie('token', $token, ['path' => '', 'httponly' => false]);
        header('Location: index.php');
        exit;
    }
    $msg = '账号或密码错误（演示账号 guest / guest123）';
}

// 去年运维群里截图泄露的"管理员 token"(已过期,密钥未泄露,理论上无害)
$leaked = jwt_make_hs256(['user' => 'admin', 'role' => 'admin', 'iat' => 1700000000, 'exp' => 1704038400], SECRET);

page_head('JWT 4/4 · medium', '会员站 4 —— 过期的令牌，还作数吗', '★★☆☆☆',
 '本站 JWT 用 HS256 + 64 位高熵随机密钥，算法锁定、验签"看起来"也在做。但安全团队去年在运维群里截图泄露过一个<b>管理员 token</b>——
  它早已<b>过期</b>（exp=2024-01-01），按理说废纸一张。flag 在 <a href="admin.php">admin.php</a>。
  演示账号 <code>guest / guest123</code>。',
 ['<b>exp 声明</b>：JWT 的 payload 里 <code>exp</code>（expiration）表示过期时间戳，<b>它本身也只是 payload 的一部分，改它同样只需要 Base64</b>——前提是签名不被校验。',
  '<b>未校验签名</b>：只 <code>json_decode</code> 就采信 payload、最多检查一下 exp 的"验签"，等于把身份凭证变成了自报家门。真实事故里常见于"以为库会自动验"或重构时丢掉验签调用。',
  '修复思路：验签是一切的前提（先验签、再看 exp/iss/aud），过期则签发新的；敏感接口不要长期信任 token，配合短期 exp + refresh token。'],
 ['把页面上"泄露的管理员 token"复制出来解码看看：role=admin，但 exp 是 2024 年的时间戳，直接用会提示已过期。',
  '想想：谁在"执行"过期检查？服务端。它检查的这个 exp 字段，真的防篡改吗——签名被校验了吗？',
  '把泄露 token 的 payload 里 exp 改成 9999999999，重新 Base64URL 编码替换该段（header 和签名字段原样不动），访问 admin.php。']);
?>
<div class="card"><h2>会员站 4</h2>
<?php if ($msg): ?><div class="msg err"><?php echo h($msg); ?></div><?php endif; ?>
<?php if (!$token): ?>
  <form method="post" style="display:flex;gap:8px;align-items:center">
    <input name="u" value="guest" placeholder="账号">
    <input name="p" type="password" value="guest123" placeholder="密码">
    <button class="btn" type="submit">登录</button>
  </form>
<?php else: ?>
  <div class="msg ok">已登录（JWT / HS256 / 密钥 64 位高熵）</div>
  <p>你的 token（10 分钟有效期）：</p>
  <pre><?php echo h($token); ?></pre>
  <div class="msg err">泄露存档（运维群截图，已过期两年）：</div>
  <pre><?php echo h($leaked); ?></pre>
  <p><a class="btn" href="admin.php">进入 admin.php（管理区）</a> &nbsp; <a class="muted" href="index.php?logout=1">退出</a></p>
<?php endif; ?>
</div>
<?php
if (isset($_GET['logout'])) { setcookie('token', '', ['expires' => time() - 3600]); }
page_foot('jwt4');
