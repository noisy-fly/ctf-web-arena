<?php
declare(strict_types=1);
/* ------------------------------------------------------------------
 * sql7 二次注入（注册安全入库 → 修改密码时信任库内数据直接拼接）
 * 教学靶场 · 仅用于授权学习环境
 * ------------------------------------------------------------------ */

$META = json_decode(file_get_contents(__DIR__ . '/meta.json'), true);

function db(): SQLite3 {
    $f = __DIR__ . '/data.sqlite3';
    $db = new SQLite3($f);
    $db->enableExceptions(true);
    $db->busyTimeout(5000);
    if (!$db->querySingle("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='users'")) {
        $db->exec(file_get_contents(__DIR__ . '/init.sql'));
    }
    return $db;
}
$db = db();
session_start();

$msg = '';
$err = '';
$shown_sql = '';   // 教学展示：修改密码时执行的 SQL

/* ---------- 功能：注册（参数化，安全） ---------- */
if (isset($_POST['action']) && $_POST['action'] === 'register') {
    $u = (string)($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    $dup = false;
    if ($u === '' || $p === '') {
        $err = '用户名/密码不能为空';
    } else {
        $st = $db->prepare('SELECT count(*) FROM users WHERE username = :u');
        $st->bindValue(':u', $u, SQLITE3_TEXT);
        $dup = ((int)$st->execute()->fetchArray(SQLITE3_NUM)[0]) > 0;
        if ($dup) $err = '用户名已存在';
    }
    if ($err === '' && !$dup) {
        // 一阶注入在这里被参数化挡住了：' 之类字符只是普通数据，可以入库
        $st = $db->prepare('INSERT INTO users(username, password, note) VALUES (:u, :p, :n)');
        $st->bindValue(':u', $u, SQLITE3_TEXT);
        $st->bindValue(':p', password_hash($p, PASSWORD_DEFAULT), SQLITE3_TEXT);
        $st->bindValue(':n', '', SQLITE3_TEXT);
        $st->execute();
        $msg = '注册成功，请登录：' . htmlspecialchars($u, ENT_QUOTES);
    }
}

/* ---------- 功能：登录（参数化，安全） ---------- */
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $u = (string)($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    $st = $db->prepare('SELECT id, password FROM users WHERE username = :u');
    $st->bindValue(':u', $u, SQLITE3_TEXT);
    $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
    if ($r && password_verify($p, $r['password'])) {
        $_SESSION['uid'] = (int)$r['id'];
        $_SESSION['username'] = $u;
        $msg = '登录成功';
    } else {
        $err = '用户名或密码错误';
    }
}

/* ---------- 功能：修改密码（漏洞点：username 来自 session，被直接拼接） ---------- */
if (isset($_POST['action']) && $_POST['action'] === 'changepw' && isset($_SESSION['uid'])) {
    $cur = $_SESSION['username'];                 // 注册时安全入库的数据，被当成了"可信数据"
    $np  = password_hash((string)($_POST['newpw'] ?? ''), PASSWORD_DEFAULT);
    $sql = "UPDATE users SET password = '$np' WHERE username = '$cur'";   // ← 二次注入点
    $shown_sql = $sql;
    try { $db->exec($sql); $msg = '密码修改成功'; }
    catch (Throwable $e) { $err = '修改失败：' . $e->getMessage(); }
}

/* ---------- 功能：退出 ---------- */
if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }

/* ---------- 当前用户信息 ---------- */
$me = null;
if (isset($_SESSION['uid'])) {
    $st = $db->prepare('SELECT username, note FROM users WHERE id = :i');
    $st->bindValue(':i', $_SESSION['uid'], SQLITE3_INTEGER);
    $me = $st->execute()->fetchArray(SQLITE3_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($META['title']) ?> · <?= htmlspecialchars($META['id']) ?></title>
<style>
:root{--bg:#0d1117;--card:#161b22;--bd:#30363d;--fg:#c9d1d9;--dim:#8b949e;--acc:#58a6ff;--ok:#3fb950;--warn:#d29922;--err:#f85149;--mono:ui-monospace,SFMono-Regular,Consolas,monospace}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--fg);font:15px/1.7 "Segoe UI","Microsoft YaHei",sans-serif;padding:0 0 60px}
.wrap{max-width:900px;margin:0 auto;padding:0 20px}
header{border-bottom:1px solid var(--bd);background:linear-gradient(180deg,#161b22,#0d1117);padding:28px 0 20px;margin-bottom:24px}
h1{font-size:24px;margin:8px 0 4px}
.badge{display:inline-block;font-size:12px;padding:2px 10px;border:1px solid var(--bd);border-radius:12px;color:var(--acc);margin-right:6px}
.badge.diff-easy{color:var(--ok)} .badge.diff-medium{color:var(--warn)} .badge.diff-hard{color:var(--err)}
.sub{color:var(--dim);font-size:13px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:8px;padding:18px 20px;margin-bottom:18px}
.card h2{font-size:16px;color:var(--acc);margin-bottom:10px;border-left:3px solid var(--acc);padding-left:8px}
.card p{margin-bottom:8px}
code,.mono{font-family:var(--mono);background:#0b0f14;border:1px solid var(--bd);border-radius:4px;padding:1px 6px;font-size:13px;color:#79c0ff}
pre{background:#0b0f14;border:1px solid var(--bd);border-radius:6px;padding:12px;overflow:auto;font-family:var(--mono);font-size:13px;margin:8px 0;color:#a5d6ff}
details{border:1px solid var(--bd);border-radius:6px;padding:10px 14px;margin:8px 0;background:#10151c}
summary{cursor:pointer;color:var(--warn);font-size:14px;user-select:none}
details[open] summary{border-bottom:1px dashed var(--bd);padding-bottom:6px;margin-bottom:8px}
.hint-lv{color:var(--dim);font-size:12px}
input[type=text],input[type=password]{background:#0b0f14;border:1px solid var(--bd);color:var(--fg);border-radius:6px;padding:8px 12px;font-family:var(--mono);font-size:14px}
button{background:#21262d;border:1px solid var(--bd);color:var(--fg);border-radius:6px;padding:8px 18px;cursor:pointer;font-size:14px}
button:hover{border-color:var(--acc);color:var(--acc)}
#flagResult{margin-top:10px;font-size:14px;min-height:22px}
.ok{color:var(--ok)} .bad{color:var(--err)}
footer{border-top:1px solid var(--bd);margin-top:30px;padding-top:16px;color:var(--dim);font-size:13px;display:flex;justify-content:space-between}
footer a{color:var(--dim);text-decoration:none} footer a:hover{color:var(--acc)}
.form-row{margin:8px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.note-box{background:#2d1e0f;border:1px solid #6b4a1c;border-radius:6px;padding:10px 14px;color:#e3b341;margin-top:8px}
.msg{color:var(--ok)} .err{color:var(--err)}
</style>
</head>
<body>
<header><div class="wrap">
  <div><span class="badge"><?= htmlspecialchars($META['category']) ?></span><span class="badge diff-<?= htmlspecialchars($META['difficulty']) ?>">难度: <?= htmlspecialchars($META['difficulty']) ?></span></div>
  <h1><?= htmlspecialchars($META['title']) ?> <span style="color:var(--dim);font-size:14px;font-weight:400"><?= htmlspecialchars($META['id']) ?></span></h1>
  <div class="sub">CTF Web 全题型教学式靶场 · 本题仅用于授权安全学习</div>
</div></header>

<div class="wrap">

<div class="card">
  <h2>题目描述</h2>
  <p>“深蓝笔记”是一个内部笔记本站点。管理员账号 <code>admin</code> 的个人主页上贴着一张内部备忘（flag 就在那里）。</p>
  <p>站点开放注册。开发同学吸取了教训：<b>注册、登录都用了参数化查询</b>，任何特殊字符都只是普通数据。</p>
  <p>但改密码功能是新来的实习生写的——他从 session 里取出"已经过安全验证"的用户名，直接拼进了 UPDATE 语句。数据在入库时是安全的，不代表它<b>再一次被使用</b>时还安全。</p>
</div>

<div class="card">
  <h2>知识点充电站</h2>
  <p><b>二次注入（Second-order Injection）</b>：恶意 payload 先以"数据"身份被<b>安全地存储</b>（参数化/转义后入库），随后应用在另一处逻辑中把这条数据从库里读出来，<b>当作可信值直接拼接</b>进新的 SQL —— 存储时的防御在第二次使用时失效了。</p>
  <pre>第一步（入库，防住了）：INSERT INTO users(username,...) VALUES(?, ...)   -- admin'-- 只是数据
第二步（使用，没防住）：UPDATE users SET password='...' WHERE username='admin'--'
                        -- ↑ 注入在"数据被再次拼接"时爆发</pre>
  <p>防御要点：<b>只要参与 SQL 拼接，任何来源的数据（包括自己数据库里的）都必须参数化</b>。"这个值来自我自己的库"不构成信任理由。</p>
</div>

<div class="card">
  <h2>账号中心</h2>

  <?php if ($msg): ?><p class="msg"><?= $msg ?></p><?php endif; ?>
  <?php if ($err): ?><p class="err"><?= $err ?></p><?php endif; ?>

  <?php if (!$me): ?>
    <form method="POST" class="form-row">
      <input type="hidden" name="action" value="login">
      <input type="text" name="username" placeholder="用户名" required>
      <input type="password" name="password" placeholder="密码" required>
      <button>登录</button>
    </form>
    <form method="POST" class="form-row">
      <input type="hidden" name="action" value="register">
      <input type="text" name="username" placeholder="想注册的用户名（允许特殊字符）" required>
      <input type="password" name="password" placeholder="密码" required>
      <button>注册</button>
    </form>
  <?php else: ?>
    <p>当前登录：<code><?= htmlspecialchars($me['username']) ?></code>　<a href="?logout=1" style="color:var(--dim)">退出</a></p>

    <form method="POST" class="form-row">
      <input type="hidden" name="action" value="changepw">
      <input type="password" name="newpw" placeholder="新密码" required>
      <button>修改密码</button>
    </form>
    <?php if ($shown_sql): ?>
      <p class="sub">后端执行的 SQL：<code class="mono"><?= htmlspecialchars($shown_sql) ?></code></p>
    <?php endif; ?>

    <div class="note-box">
      <b>内部备忘</b>：<?= $me['note'] !== '' ? htmlspecialchars((string)$me['note']) : '（普通用户没有备忘。听说 admin 的主页上贴着好东西…）' ?>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <h2>渐进提示（先自己想，再一层层打开）</h2>
  <?php foreach ($META['hints'] as $i => $h): ?>
  <details><summary>提示 <?= $i + 1 ?> <span class="hint-lv">（第 <?= $i + 1 ?>/<?= count($META['hints']) ?> 层）</span></summary>
    <p><?= nl2br(htmlspecialchars($h)) ?></p>
  </details>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>提交 Flag</h2>
  <div class="form-row">
    <input type="text" id="flagInput" placeholder="flag{sql7_xxxxxxxx}">
    <button onclick="submitFlag()">提交</button>
  </div>
  <div id="flagResult"></div>
</div>

<footer class="wrap">
  <span><?php if ($META['prev']): ?><a href="/<?= htmlspecialchars($META['prev']) ?>">← 上一题 <?= htmlspecialchars($META['prev']) ?></a><?php endif; ?></span>
  <span>难度递进 · 教学式靶场</span>
  <span><?php if ($META['next']): ?><a href="/<?= htmlspecialchars($META['next']) ?>">下一题 <?= htmlspecialchars($META['next']) ?> →</a><?php endif; ?></span>
</footer>
</div>

<script>
async function submitFlag(){
  const box = document.getElementById('flagResult');
  const flag = document.getElementById('flagInput').value.trim();
  if(!flag) { box.textContent = '请输入 flag'; box.className='bad'; return; }
  box.textContent = '校验中...'; box.className='';
  try{
    const r = await fetch('/api/verify', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: 'challenge_id=<?= urlencode($META['id']) ?>&flag=' + encodeURIComponent(flag)
    });
    const t = await r.text();
    let msg = t; try{ const j = JSON.parse(t); msg = j.message || j.msg || (j.correct!==undefined ? (j.correct?'正确 ✓':'错误 ✗') : t); }catch(e){}
    box.textContent = msg; box.className = /正确|correct|true|✓/.test(String(msg)) ? 'ok' : 'bad';
  }catch(e){
    box.textContent = '无法连接校验接口 /api/verify（本地预览属正常，联调后可用）';
    box.className = 'bad';
  }
}
</script>
</body>
</html>
