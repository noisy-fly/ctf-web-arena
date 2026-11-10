<?php
/* ============================================================
 * 教学靶场 · sql3 布尔盲注
 * 漏洞点：数字 id 拼接；无回显、无报错，只剩 成功/失败 两种状态
 * 目标：ascii()+substr() 二分逐字符提取 fl4g
 * ============================================================ */
$con = @mysqli_connect('localhost', 'web', 'webpass_2026', 'sqlilab3');
if (!$con) { http_response_code(500); exit("数据库连接失败"); }
mysqli_set_charset($con, 'utf8mb4');

$id = $_GET['id'] ?? '1';
/* ★ 危险代码 ★ */ $sql = "SELECT username FROM users WHERE id = $id";

$res = @mysqli_query($con, $sql);   // @ 抑制报错：语法错与条件假一样只剩"失败"
$ok = ($res !== false && mysqli_num_rows($res) > 0);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>sql3 · 布尔盲注</title>
<script src="/assets/boot.js"></script>
<link rel="stylesheet" href="/assets/style.css">
<link rel="stylesheet" href="/assets/challenge.css">
<script src="/assets/challenge.js" data-chal="sql3" defer></script>
</head>
<body>
<header class="topbar">
  <div class="logo"><span class="badge">🗄️</span> 存活检测接口 v3.0</div>
  <div class="spacer"></div>
  <a class="tag" href="/">关卡地图</a>
</header>
<div class="chal-wrap">
  <div class="chal-env">
    <h1>🫀 用户存活检测</h1>
    <p class="env-sub">输入 id 检测用户是否存在。界面朴素得只剩两个字了——当页面不再告诉你任何细节，问题就变成了：如何用"是/否"的提问读出一整段文字？</p>

    <div class="env-card">
      <form method="get" action="/sql3">
        <div class="env-row">
          <label style="flex:0 0 auto">用户 id</label>
          <input type="text" name="id" value="<?= htmlspecialchars($id, ENT_QUOTES) ?>" placeholder="1">
          <button class="btn" type="submit">检测</button>
        </div>
      </form>
    </div>

    <div class="env-result">
      <?php if ($ok): ?>
        <p style="font-size:1.05rem">✅ 查询成功：该用户存在</p>
      <?php else: ?>
        <p style="font-size:1.05rem;color:var(--fg-soft)">❌ 查询失败</p>
      <?php endif; ?>
    </div>

    <div class="sql-echo">本次执行的 SQL：<code><?= htmlspecialchars($sql, ENT_QUOTES) ?></code><br>
      <span style="opacity:.7">（想想：让括号里的条件随你的意愿变真变假，页面状态就是它的回音）</span></div>

    <details class="src-details" style="margin-top:16px">
      <summary style="cursor:pointer;color:var(--fg-soft)">🔍 查看本题关键源码</summary>
      <pre>&lt;?php
$id = $_GET['id'] ?? '1';
$sql = "SELECT username FROM users WHERE id = $id";   // ← 数字型拼接
$res = @mysqli_query($con, $sql);                     // ← @ 抑制了报错
$ok = ($res !== false &amp;&amp; mysqli_num_rows($res) &gt; 0);
echo $ok ? "查询成功：该用户存在" : "查询失败";          // ← 只剩布尔状态</pre>
    </details>
  </div>
</div>
</body>
</html>
