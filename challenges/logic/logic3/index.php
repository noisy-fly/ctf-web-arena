<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/init.php';
$db = db();

// 生成一次验证码后,整个会话期间都不会再变 —— 图省事的"静态验证码"
function gen_cap(): string {
    $pool = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < 4; $i++) { $s .= $pool[random_int(0, strlen($pool) - 1)]; }
    return $s;
}
if (!isset($_SESSION['cap'])) { $_SESSION['cap'] = gen_cap(); }
if (isset($_GET['refresh'])) { $_SESSION['cap'] = gen_cap(); header('Location: index.php'); exit; } // 只有手动点"换一张"才更新

$msg = '';
$ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim((string)($_POST['u'] ?? ''));
    $p = (string)($_POST['p'] ?? '');
    $c = strtoupper(trim((string)($_POST['cap'] ?? '')));

    if ($c !== $_SESSION['cap']) {
        // 校验失败也不刷新;校验成功同样不销毁 —— 验证码可以被无限复用
        $msg = '验证码不正确';
        $db->prepare('INSERT INTO login_attempts(ts, ok) VALUES(datetime("now","localtime"),0)')->execute();
    } else {
        $st = $db->prepare('SELECT * FROM users WHERE username=? AND password=?');
        $st->execute([$u, $p]);
        $usr = $st->fetch();
        if ($usr) {
            $_SESSION['uid'] = (int)$usr['id'];
            $_SESSION['username'] = $usr['username'];
            $db->prepare('INSERT INTO login_attempts(ts, ok) VALUES(datetime("now","localtime"),1)')->execute();
        } else {
            $msg = '验证码正确，但账号或密码不对'; // ← 这句话本身就是信息泄露
            $db->prepare('INSERT INTO login_attempts(ts, ok) VALUES(datetime("now","localtime"),0)')->execute();
        }
    }
}

page_head('逻辑漏洞 3/5 · medium', '运维值班系统 —— 一次验证码，用一万次', '★★☆☆☆',
 '值班系统登录页带验证码防爆破。内部情报：值班长账号 <code>captain</code>，密码是 <b>4 位数字</b>，而且以 <code>19</code> 开头（他的入职年份）。
  正常来说 100 个组合也不多，但验证码每次都要重新人眼识别的话，爆破就变得很痛苦——除非，验证码其实<b>只需要识别一次</b>。登录 captain 拿到 flag。',
 ['<b>验证码复用（不失效）</b>：验证码校验后没有立即销毁、会话内也不刷新，导致一个验证码可以被无限次提交，防爆破形同虚设。',
  '<b>缺少频率限制</b>：接口没有失败锁定/限速，爆破成本只取决于密码空间。',
  '修复思路：验证码"一次一验"——无论校验成功失败都立即从会话中销毁；配合登录失败计数与限速（如 5 次锁 10 分钟）。'],
 ['先手工登录一次试试：随便输个密码、验证码照着填。注意失败后页面上验证码变了吗？抓包再提交一次同一个验证码还能用吗？',
  '写个脚本：同一 Cookie + 同一验证码，把 1900~1999 逐个当密码提交，观察哪次返回不一样。',
  'captain 的密码就在 1900~1999 之间，成功页有 flag。']);
?>
<div class="card"><h2>运维值班系统 · 登录</h2>
<?php if (!empty($_SESSION['uid'])): ?>
  <?php if ($_SESSION['username'] === 'captain'): ?>
    <div class="msg ok">值班长已登录。今日值班口令（flag）：<code>flag{logic3_f8a30809}</code></div>
  <?php else: ?>
    <div class="msg ok">欢迎，<?php echo h($_SESSION['username']); ?>（普通值班员，flag 在值班长 captain 账号里）。</div>
  <?php endif; ?>
  <p class="muted"><a href="index.php?logout=1">退出</a></p>
<?php else: ?>
  <?php if ($msg): ?><div class="msg err"><?php echo h($msg); ?></div><?php endif; ?>
  <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <input name="u" value="captain" placeholder="账号" required>
    <input name="p" style="width:110px" placeholder="密码" required>
    <span style="font-size:20px;letter-spacing:6px;font-weight:700;font-style:italic;background:#eef2ff;border:2px solid #c7d2fe;border-radius:6px;padding:3px 10px;user-select:none">
      <?php
      // 纯文本验证码(本关重点是"复用",不是OCR)
      foreach (str_split($_SESSION['cap']) as $ch) {
          echo '<span style="display:inline-block;transform:rotate(' . random_int(-14, 14) . 'deg)">' . h($ch) . '</span>';
      }
      ?>
    </span>
    <input name="cap" style="width:90px" placeholder="验证码" required>
    <button class="btn" type="submit">登录</button>
  </form>
  <p class="muted">验证码看不清？<a href="index.php?refresh=1">换一张</a>（换一张才会刷新；提交失败不会换）</p>
<?php endif; ?>
</div>
<?php
if (isset($_GET['logout'])) { session_destroy(); }
page_foot('logic3');
