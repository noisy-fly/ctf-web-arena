<?php
declare(strict_types=1);
require __DIR__ . '/common.php';
const SECRET = 'vT8xQ2wZ5nR7bK3mY9cP1dJ6hF4sL0gUeA2oIi8uUyTtRreWwq';

/** 校验 token —— 漏洞版本:只解码+查过期,签名从未被校验 */
function verify_token(string $t): ?array {
    $parsed = jwt_parse($t);
    if (!$parsed) { return null; }
    [$h, $p, $sig] = $parsed;
    // 正确写法应先:
    //   $expect = jwt_hs256_sig(explode('.',$t)[0], explode('.',$t)[1], SECRET);
    //   if (!hash_equals($expect, $sig)) return null;
    // 但这里直接跳到了业务校验 ↓
    if (isset($p['exp']) && (int)$p['exp'] < time()) { return null; }   // 只检查了过期
    return $p;                                                          // payload 原样采信
}

$p = verify_token($_COOKIE['token'] ?? '');
$expired = false;
if (!is_array($p)) {
    // 区分"过期"与"无效"仅为教学展示
    $parsed = jwt_parse($_COOKIE['token'] ?? '');
    if ($parsed && isset($parsed[1]['exp']) && (int)$parsed[1]['exp'] < time()) { $expired = true; }
}
$isAdmin = is_array($p) && (($p['role'] ?? '') === 'admin');

page_head('JWT 4/4 · medium', '会员站 4 · 管理区', '★★☆☆☆', '', [], []);
?>
<div class="card">
<?php if ($isAdmin): ?>
  <h2>欢迎，管理员 <?php echo h((string)($p['user'] ?? '')); ?>！</h2>
  <div class="msg ok">管理区数据：灾备恢复口令 flag{jwt4_1b6c1dc6}</div>
<?php elseif (is_array($p)): ?>
  <h2>已登录但权限不足</h2>
  <div class="msg err">你是 <?php echo h((string)($p['user'] ?? '?')); ?>（role=<?php echo h((string)($p['role'] ?? '?')); ?>）。payload 想写什么就写什么——只要 exp 还"没过期"。</div>
<?php elseif ($expired): ?>
  <h2>token 已过期</h2>
  <div class="msg err">exp 校验未通过，请重新登录。……不过，exp 这个字段，真的可靠吗？</div>
<?php else: ?>
  <h2>未登录或 token 无效</h2>
  <div class="msg err">请先 <a href="index.php">登录</a>。</div>
<?php endif; ?>
</div>
<?php page_foot('jwt4');
