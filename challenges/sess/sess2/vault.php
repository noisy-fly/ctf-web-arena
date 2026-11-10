<?php
// sess2 · 金库（验签放行）
require_once __DIR__ . '/config.php';

$err = null; $who = null;
if (!isset($_COOKIE['stargate_auth'])) {
    $err = '未携带凭证：请先在 index.php 登录。';
} else {
    $err = sso_verify($_COOKIE['stargate_auth'], $who);
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="utf-8"><title>星门金库</title>
<style>
body{margin:0;font-family:"Segoe UI",system-ui,sans-serif;background:#0f172a;color:#e2e8f0;line-height:1.8;padding:32px 16px}
.w{max-width:760px;margin:0 auto}h1{font-size:22px;color:#fbbf24}
.box{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:18px 22px;margin:14px 0}
.ok{background:#052e16;border-color:#14532d;color:#bbf7d0}.no{color:#94a3b8}.bad{background:#3b1519;border-color:#7f1d1d;color:#fca5a5}
a{color:#38bdf8}code{background:#0b1220;border-radius:4px;padding:1px 6px}
</style></head><body><div class="w">
<h1>🏦 星门金库 vault.php</h1>
<?php if ($err): ?>
<div class="box bad">⛔ <?= h($err) ?></div>
<div class="box no">提示：凭证结构 <code>base64url(json).签名</code>，签名 = md5(user|role|exp|SECRET)。</div>
<?php elseif ($who['role'] === 'admin'): ?>
<div class="box ok">✅ 凭证有效：user=<?= h($who['user']) ?> role=admin（签名校验通过）。<br>
金库口令：<b><?= SESS2_FLAG ?></b></div>
<?php else: ?>
<div class="box no">✅ 凭证有效：user=<?= h($who['user']) ?> role=<?= h($who['role']) ?>。<br>
但金库只对 <b>role=admin</b> 的<b>签名有效</b>凭证开放——普通会员就到这为止。</div>
<?php endif; ?>
<p><a href="index.php">← 返回登录页</a></p>
</div></body></html>
