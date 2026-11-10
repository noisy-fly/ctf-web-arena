<?php
/* ============================================================
 * 教学靶场 · xss2 存储型 XSS
 * 漏洞点：留言内容未经转义存库、未经转义渲染（存储型）
 * 发布者身份用 ctf_sess cookie 标记（不展示原文，仅用于 Victim 判定归属）
 * ============================================================ */
$con = @mysqli_connect('localhost', 'web', 'webpass_2026', 'xssboard');
if (!$con) { http_response_code(500); exit("数据库连接失败"); }
mysqli_set_charset($con, 'utf8mb4');

$posted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = mb_substr(trim($_POST['content'] ?? ''), 0, 500);
    if ($content !== '') {
        $poster = substr($_COOKIE['ctf_sess'] ?? 'anon', 0, 64);
        $stmt = mysqli_prepare($con, "INSERT INTO msgs(poster, content, ts) VALUES (?,?,NOW())");
        mysqli_stmt_bind_param($stmt, 'ss', $poster, $content);
        mysqli_stmt_execute($stmt);
        $posted = true;
    }
    header('Location: /xss2');   // PRG 防重复提交
    exit;
}

$res = mysqli_query($con, "SELECT id, content, ts FROM msgs ORDER BY id DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>xss2 · 存储型 XSS</title>
<script src="/assets/boot.js"></script>
<link rel="stylesheet" href="/assets/style.css">
<link rel="stylesheet" href="/assets/challenge.css">
<script src="/assets/challenge.js" data-chal="xss2" defer></script>
</head>
<body>
<header class="topbar">
  <div class="logo"><span class="badge">🎭</span> 匿名留言板</div>
  <div class="spacer"></div>
  <a class="tag" href="/">关卡地图</a>
</header>
<div class="chal-wrap">
  <div class="chal-env">
    <h1>💬 全站留言板</h1>
    <p class="env-sub">每个人都能留言，每个人都会看到所有留言——包括那位时不时来逛逛的 Victim 访客。</p>

    <div class="env-card">
      <form method="post" action="/xss2">
        <label>发布新留言（支持任意内容…真的任意吗？）</label>
        <div class="env-row">
          <textarea name="content" rows="2" placeholder="写点什么…" required></textarea>
        </div>
        <div style="margin-top:8px"><button class="btn" type="submit">发布留言</button></div>
      </form>
    </div>

    <div class="env-result">
      <h3>最新留言（<?= mysqli_num_rows($res) ?>）</h3>
      <?php if (mysqli_num_rows($res) === 0): ?>
        <p style="color:var(--fg-soft)">还没有留言，来抢沙发。</p>
      <?php else: ?>
      <div class="msg-list">
        <?php while ($row = mysqli_fetch_assoc($res)): ?>
        <div class="msg-item">
          <div class="meta">#<?= (int)$row['id'] ?> · <?= htmlspecialchars($row['ts']) ?></div>
          <!-- ★ 危险代码 ★：content 未转义直接渲染，对所有访客生效 -->
          <?= $row['content'] ?>
        </div>
        <?php endwhile; ?>
      </div>
      <?php endif; ?>
    </div>

    <details class="src-details" style="margin-top:16px">
      <summary style="cursor:pointer;color:var(--fg-soft)">🔍 查看本题关键源码</summary>
      <pre>&lt;?php
// 存储（这里用了预处理，插入本身没问题）
$stmt = mysqli_prepare($con, "INSERT INTO msgs(poster, content, ts) VALUES (?,?,NOW())");
// 渲染（对每个访客执行——问题在这里）
while ($row = mysqli_fetch_assoc($res)) {
    echo "&lt;div class='msg'&gt;" . $row['content'] . "&lt;/div&gt;";  // ← 存下来的内容未转义直接输出
}</pre>
    </details>

    <p style="color:var(--fg-soft);font-size:.8rem;margin-top:14px">⚠️ 教学靶场：Victim Bot 为浏览器执行的模拟判定；真实场景中存储型 XSS 会在每位访客浏览器执行。</p>
  </div>
</div>
</body>
</html>
