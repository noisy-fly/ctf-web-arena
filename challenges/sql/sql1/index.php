<?php
/* ============================================================
 * 教学靶场 · sql1 联合注入
 * 漏洞点：$id 未做任何过滤，直接拼进 WHERE（数字型注入）
 * 目标：利用 UNION SELECT 把 fl4g 表里的 flag 回显出来
 * ============================================================ */
$con = @mysqli_connect('localhost', 'web', 'webpass_2026', 'sqlilab1');
// PHP8 起 mysqli 默认抛异常；教学需要 PHP7 语义（查询失败返回 false + mysqli_error 回显）
mysqli_report(MYSQLI_REPORT_OFF);
if (!$con) { http_response_code(500); exit("数据库连接失败"); }
mysqli_set_charset($con, 'utf8mb4');

$id = $_GET['id'] ?? '1';
/* ★ 危险代码 ★ */ $sql = "SELECT id, username, password FROM users WHERE id = $id";

$res = mysqli_query($con, $sql);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>sql1 · 联合注入</title>
<script src="/assets/boot.js"></script>
<link rel="stylesheet" href="/assets/style.css">
<link rel="stylesheet" href="/assets/challenge.css">
<script src="/assets/challenge.js" data-chal="sql1" defer></script>
</head>
<body>
<header class="topbar">
  <div class="logo"><span class="badge">🗄️</span> 用户查询系统 v1.0</div>
  <div class="spacer"></div>
  <a class="tag" href="/">关卡地图</a>
</header>
<div class="chal-wrap">
  <div class="chal-env">
    <h1>📋 员工信息查询</h1>
    <p class="env-sub">输入员工编号（1~5）查询账号信息。听说 flag 就藏在这台数据库的某个角落…</p>

    <div class="env-card">
      <form method="get" action="/sql1">
        <div class="env-row">
          <label style="flex:0 0 auto">员工编号</label>
          <input type="text" name="id" value="<?= htmlspecialchars($id, ENT_QUOTES) ?>" placeholder="例如 1">
          <button class="btn" type="submit">查询</button>
        </div>
      </form>
    </div>

    <div class="env-result">
      <?php if ($res === false): ?>
        <p class="env-error">查询出错：<?= htmlspecialchars(mysqli_error($con), ENT_QUOTES) ?></p>
      <?php elseif (mysqli_num_rows($res) === 0): ?>
        <p style="color:var(--fg-soft)">没有 id = <?= htmlspecialchars($id, ENT_QUOTES) ?> 的记录。</p>
      <?php else: ?>
        <table>
          <tr><th>id</th><th>username</th><th>password</th></tr>
          <?php while ($row = mysqli_fetch_assoc($res)): ?>
          <tr><td><?= htmlspecialchars((string)$row['id']) ?></td>
              <td><?= htmlspecialchars((string)$row['username']) ?></td>
              <td><?= htmlspecialchars((string)$row['password']) ?></td></tr>
          <?php endwhile; ?>
        </table>
      <?php endif; ?>
    </div>

    <div class="sql-echo">本次执行的 SQL：<code><?= htmlspecialchars($sql, ENT_QUOTES) ?></code></div>

    <details class="src-details" style="margin-top:16px">
      <summary style="cursor:pointer;color:var(--fg-soft)">🔍 查看本题关键源码</summary>
      <pre>&lt;?php
$id = $_GET['id'] ?? '1';
$sql = "SELECT id, username, password FROM users WHERE id = $id";  // ← $id 未过滤直接拼接
$res = mysqli_query($con, $sql);
// 结果原样渲染到表格；出错时还会打印 mysqli_error()</pre>
    </details>
  </div>
</div>
</body>
</html>
