<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
const SECRET = 'x9Kj2LmPq7RtVb4Zw8Cs3Df6Gh1Jk5Nn';

/** 校验 token —— 漏洞版本：完全信任 token 头部声明的算法 */
function verify_token(string $t): ?array {
    $parsed = jwt_parse($t);
    if (!$parsed) { return null; }
    [$h, $p, $sig] = $parsed;
    $alg = (string)($h['alg'] ?? '');
    if ($alg === 'none') {
        // ←←← 漏洞核心：算法由 token 自己说了算，none 就不验签，payload 直接采信
        return $p;
    }
    if ($alg === 'HS256') {
        $h64 = explode('.', $t)[0];
        $p64 = explode('.', $t)[1];
        $expect = jwt_hs256_sig($h64, $p64, SECRET);
        return hash_equals($expect, $sig) ? $p : null;
    }
    return null;
}

$p = verify_token($_COOKIE['token'] ?? '');
$isAdmin = is_array($p) && (($p['role'] ?? '') === 'admin');

page_head('JWT 1/4 · easy', '会员站 · 管理区', '★☆☆☆☆', '', [], []);
?>
<div class="card">
<?php if ($isAdmin): ?>
  <h2>欢迎，管理员 <?php echo h((string)($p['user'] ?? '')); ?>！</h2>
  <div class="msg ok">管理区数据：内部系统初始化口令 flag{jwt1_29c9a437}</div>
<?php elseif (is_array($p)): ?>
  <h2>已登录但权限不足</h2>
  <div class="msg err">你是 <?php echo h((string)($p['user'] ?? '?')); ?>（role=<?php echo h((string)($p['role'] ?? '?')); ?>），管理区仅对 role=admin 开放。</div>
  <p class="muted">你的身份完全由 token 的 payload 决定……而 payload，真的改不了吗？</p>
<?php else: ?>
  <h2>未登录</h2>
  <div class="msg err">请先 <a href="index.php">登录</a>。</div>
<?php endif; ?>
</div>
<?php page_foot('jwt1');
