<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
require __DIR__ . '/keys.php';

/** 校验 token —— 漏洞版本:验签密钥的选择权交给了 token 头部的 alg 字段 */
function verify_token(string $t): ?array {
    $parsed = jwt_parse($t);
    if (!$parsed) { return null; }
    [$h, $p, $sig] = $parsed;
    [$h64, $p64] = explode('.', $t);
    $alg = (string)($h['alg'] ?? '');
    $ok = false;
    if ($alg === 'RS256') {
        $ok = openssl_verify("$h64.$p64", b64url_decode($sig), PUB_PEM, OPENSSL_ALGO_SHA256) === 1;
    } elseif ($alg === 'HS256') {
        // ←←← 漏洞核心:HS256 时直接拿"RSA 公钥"当 HMAC 对称密钥——而公钥是人人可下载的
        $expect = jwt_hs256_sig($h64, $p64, PUB_PEM);
        $ok = hash_equals($expect, $sig);
    }
    return $ok ? $p : null;
}

$p = verify_token($_COOKIE['token'] ?? '');
$isAdmin = is_array($p) && (($p['role'] ?? '') === 'admin');

page_head('JWT 3/4 · hard', '会员站 3 · 管理区', '★★★☆☆', '', [], []);
?>
<div class="card">
<?php if ($isAdmin): ?>
  <h2>欢迎，管理员 <?php echo h((string)($p['user'] ?? '')); ?>！</h2>
  <div class="msg ok">管理区数据：核心网关种子口令 flag{jwt3_3996c814}</div>
<?php elseif (is_array($p)): ?>
  <h2>已登录但权限不足</h2>
  <div class="msg err">你是 <?php echo h((string)($p['user'] ?? '?')); ?>（role=<?php echo h((string)($p['role'] ?? '?')); ?>）。RS256 的签名确实伪不出来——但验签的人真的分得清"哪种算法该用哪种密钥"吗？</div>
<?php else: ?>
  <h2>未登录或 token 无效</h2>
  <div class="msg err">请先 <a href="index.php">登录</a>。</div>
<?php endif; ?>
</div>
<?php page_foot('jwt3');
