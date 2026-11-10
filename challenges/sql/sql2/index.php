<?php
/* ============================================================
 * 教学靶场 · sql2 报错注入
 * 漏洞点：username 拼进单引号字符串；查询失败时 mysqli_error() 原样回显
 * 目标：updatexml 报错把 fl4g 表内容夹带出来
 * ============================================================ */
$con = @mysqli_connect('localhost', 'web', 'webpass_2026', 'sqlilab2');
// PHP8 起 mysqli 默认抛异常；教学需要 PHP7 语义（查询失败返回 false + mysqli_error 回显）
mysqli_report(MYSQLI_REPORT_OFF);
if (!$con) { http_response_code(500); exit("数据库连接失败"); }
mysqli_set_charset($con, 'utf8mb4');

$username = $_GET['username'] ?? 'admin';
/* ★ 危险代码 ★ */ $sql = "SELECT id FROM users WHERE username = '$username'";

$res = mysqli_query($con, $sql);
if ($res === false) {
    $error = mysqli_error($con);   // 报错信息直接回显 → 报错注入通道
    $exists = null;
} else {
    $error = null;
    $exists = mysqli_num_rows($res) > 0;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>sql2 · 报错注入</title>
<script src="/assets/boot.js"></script>
<link rel="stylesheet" href="/assets/style.css">
<link rel="stylesheet" href="/assets/challenge.css">
<script src="/assets/challenge.js" data-chal="sql2" defer></script>
</head>
<body>
<header class="topbar">
  <div class="logo"><span class="badge">🗄️</span> 用户探测接口 v2.0</div>
  <div class="spacer"></div>
  <a class="tag" href="/">关卡地图</a>
</header>
<div class="chal-wrap">
  <div class="chal-env">
    <h1>🔎 用户名探测</h1>
    <p class="env-sub">输入用户名检测是否已注册。这次不会把数据查出来给你看了——但出错的时候，工程师好像忘了关掉调试信息…</p>

    <div class="env-card">
      <form method="get" action="/sql2">
        <div class="env-row">
          <label style="flex:0 0 auto">用户名</label>
          <input type="text" name="username" value="<?= htmlspecialchars($username, ENT_QUOTES) ?>" placeholder="admin">
          <button class="btn" type="submit">检测</button>
        </div>
      </form>
    </div>

    <div class="env-result">
      <?php if ($error !== null): ?>
        <p class="env-error">SQL 查询错误：<?= htmlspecialchars($error, ENT_QUOTES) ?></p>
      <?php elseif ($exists): ?>
        <p>✅ 用户存在</p>
      <?php else: ?>
        <p>❌ 用户不存在</p>
      <?php endif; ?>
    </div>

    <div class="sql-echo">本次执行的 SQL：<code><?= htmlspecialchars($sql, ENT_QUOTES) ?></code></div>

    <details class="src-details" style="margin-top:16px">
      <summary style="cursor:pointer;color:var(--fg-soft)">🔍 查看本题关键源码</summary>
      <pre>&lt;?php
$username = $_GET['username'] ?? 'admin';
$sql = "SELECT id FROM users WHERE username = '$username'";  // ← 单引号拼接
$res = mysqli_query($con, $sql);
if ($res === false) {
    echo "SQL 查询错误：" . mysqli_error($con);               // ← 报错原样回显
}</pre>
    </details>
  </div>
</div>
</body>
</html>
