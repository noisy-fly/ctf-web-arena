<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
const SECRET = 'moonlight';

/** 校验 token —— 这次写得"正确":算法白名单 + 严格验签 */
function verify_token(string $t): ?array {
    $parsed = jwt_parse($t);
    if (!$parsed) { return null; }
    [$h, $p, $sig] = $parsed;
    if (($h['alg'] ?? '') !== 'HS256') { return null; }   // 只认 HS256,none 已无效
    [$h64, $p64] = explode('.', $t);
    $expect = jwt_hs256_sig($h64, $p64, SECRET);
    return hash_equals($expect, $sig) ? $p : null;         // 验签逻辑无懈可击
}

$p = verify_token($_COOKIE['token'] ?? '');
$isAdmin = is_array($p) && (($p['role'] ?? '') === 'admin');

page_head('JWT 2/4 · medium', '会员站 2 · 管理区', '★★☆☆☆', '', [], []);
?>
<div class="card">
<?php if ($isAdmin): ?>
  <h2>欢迎，管理员 <?php echo h((string)($p['user'] ?? '')); ?>！</h2>
  <div class="msg ok">管理区数据：数据库运维口令 flag{jwt2_233df5e2}</div>
<?php elseif (is_array($p)): ?>
  <h2>已登录但权限不足</h2>
  <div class="msg err">你是 <?php echo h((string)($p['user'] ?? '?')); ?>（role=<?php echo h((string)($p['role'] ?? '?')); ?>）。验签是对的，但密钥……你确定经得起爆破？</div>
<?php else: ?>
  <h2>未登录或 token 无效</h2>
  <div class="msg err">请先 <a href="index.php">登录</a>。</div>
<?php endif; ?>
</div>
<?php page_foot('jwt2');
