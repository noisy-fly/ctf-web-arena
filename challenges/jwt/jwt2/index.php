<?php
declare(strict_types=1);
require __DIR__ . '/common.php';

// 服务端签名密钥——弱口令,就在大家常用的口令字典里(见 wordlist.txt)
const SECRET = 'moonlight';

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

page_head('JWT 2/4 · medium', '会员站 2 —— 签名很强，密钥很弱', '★★☆☆☆',
 '本站同样用 JWT（HS256）做登录态，而且这次服务端<b>只认 HS256、验签逻辑完全正确</b>——none 算法那招失效了。
  但 HS256 是<b>对称签名</b>：签名的密钥一旦太弱，就能被字典爆破。爆破出密钥 = 你也可以签发任意 token。
  演示账号 <code>guest / guest123</code>，flag 在 <a href="admin.php">admin.php</a>（role=admin 才能看）。',
 ['<b>HS256 对称签名</b>：同一个密钥既用于签名也用于验签。密钥强度 = 整个体系的安全强度。',
  '<b>离线爆破</b>：拿到一个合法 token 后，攻击者可以用字典逐个计算 <code>HMAC-SHA256(header.payload, 猜测密钥)</code> 并与签名比对——全程不需要与服务端交互，无速率限制。',
  '修复思路：密钥使用 ≥32 字节高熵随机值（密码生成器生成，而非人类好记的词）；有条件用 RS256/EdDSA 非对称算法，私钥不出现在验签服务上。'],
 ['token 拿到后，你需要回答一个问题：HMAC 的 key 是什么？先抓一份常见口令字典。<a href="wordlist.txt">本题附带的字典 wordlist.txt</a>（服务端密钥就在其中）。',
  '写脚本对字典里每个词计算 HMAC-SHA256(header.payload, word)，Base64URL 后与 token 第三段比对（也可用 hashcat -m 16500 / jwt-tool -C）。',
  '爆破出密钥后，自签一个 payload 为 {"user":"admin","role":"admin"} 的 HS256 token，替换 Cookie 访问 admin.php。']);
?>
<div class="card"><h2>会员站 2</h2>
<?php if ($msg): ?><div class="msg err"><?php echo h($msg); ?></div><?php endif; ?>
<?php if (!$token): ?>
  <form method="post" style="display:flex;gap:8px;align-items:center">
    <input name="u" value="guest" placeholder="账号">
    <input name="p" type="password" value="guest123" placeholder="密码">
    <button class="btn" type="submit">登录</button>
  </form>
<?php else: ?>
  <div class="msg ok">已登录（JWT / HS256）</div>
  <p>你的 token：</p>
  <pre><?php echo h($token); ?></pre>
  <p class="muted">解题素材：<a href="wordlist.txt">wordlist.txt</a>（常用口令字典，服务端密钥就在里面）</p>
  <p><a class="btn" href="admin.php">进入 admin.php（管理区）</a> &nbsp; <a class="muted" href="index.php?logout=1">退出</a></p>
<?php endif; ?>
</div>
<?php
if (isset($_GET['logout'])) { setcookie('token', '', ['expires' => time() - 3600]); }
page_foot('jwt2');
