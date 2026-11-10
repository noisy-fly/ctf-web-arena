<?php
declare(strict_types=1);
/* ------------------------------------------------------------------
 * sql4 堆叠注入（SQLite / SQLite3::exec 多语句）
 * 教学靶场 · 仅用于授权学习环境
 * ------------------------------------------------------------------ */

$META = json_decode(file_get_contents(__DIR__ . '/meta.json'), true);

/* ---------- 数据库初始化（SQLite 文件型，免服务） ---------- */
function db(): SQLite3 {
    $f = __DIR__ . '/data.sqlite3';
    $db = new SQLite3($f);
    $db->enableExceptions(true);   // 出错抛异常：让页面能显示 SQL 报错（教学用）
    $db->busyTimeout(5000);
    $tables = $db->querySingle("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='goods'");
    if (!$tables) {
        // init.sql 使用 IF NOT EXISTS / OR IGNORE，可安全重放
        $db->exec(file_get_contents(__DIR__ . '/init.sql'));
    }
    return $db;
}
$db = db();

/* ---------- 题目逻辑：商品查询 ---------- */
$id   = isset($_GET['id']) ? (string)$_GET['id'] : '1';
$err  = null;
$sql  = "SELECT id, title, price FROM goods WHERE id = " . $id;   // 漏洞：输入直接拼接

try {
    // 漏洞核心：exec() 会把分号分隔的多条 SQL 一次性全部执行（堆叠）
    $db->exec($sql);
} catch (Throwable $e) {
    $err = $e->getMessage();
}

// 回显：仅展示当前 id 对应行（intval 后安全）
$show = [];
$q = $db->query('SELECT id, title, price FROM goods WHERE id = ' . (int)$id);
while ($q && $r = $q->fetchArray(SQLITE3_ASSOC)) $show[] = $r;
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
table{border-collapse:collapse;width:100%;margin:10px 0}
th,td{border:1px solid var(--bd);padding:6px 12px;text-align:left;font-size:14px}
th{color:var(--dim);background:#10151c}
details{border:1px solid var(--bd);border-radius:6px;padding:10px 14px;margin:8px 0;background:#10151c}
summary{cursor:pointer;color:var(--warn);font-size:14px;user-select:none}
details[open] summary{border-bottom:1px dashed var(--bd);padding-bottom:6px;margin-bottom:8px}
.hint-lv{color:var(--dim);font-size:12px}
input[type=text]{background:#0b0f14;border:1px solid var(--bd);color:var(--fg);border-radius:6px;padding:8px 12px;font-family:var(--mono);font-size:14px;width:70%}
button{background:#21262d;border:1px solid var(--bd);color:var(--fg);border-radius:6px;padding:8px 18px;cursor:pointer;font-size:14px}
button:hover{border-color:var(--acc);color:var(--acc)}
#flagResult{margin-top:10px;font-size:14px;min-height:22px}
.ok{color:var(--ok)} .bad{color:var(--err)}
footer{border-top:1px solid var(--bd);margin-top:30px;padding-top:16px;color:var(--dim);font-size:13px;display:flex;justify-content:space-between}
footer a{color:var(--dim);text-decoration:none} footer a:hover{color:var(--acc)}
.sqlerr{color:var(--err);font-size:13px}
.form-row{margin:8px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
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
  <p>“极客书店”的商品查询接口上线了。运营同学说，只要传入商品编号就能查到书名和价格：</p>
  <p class="mono">index.php?id=1</p>
  <p>开发同学把接口直接交给了 <code>SQLite3::exec()</code> 执行，而且把你的输入<b>原样拼接</b>进了 SQL 语句。</p>
  <p>听说 flag 就藏在这个数据库的某张表里，但它永远不会出现在查询结果中 —— 想办法让数据库<b>自己把 flag 搬到你能看见的地方</b>。</p>
</div>

<div class="card">
  <h2>知识点充电站</h2>
  <p><b>堆叠注入（Stacked Queries）</b>：当应用把多条以分号分隔的 SQL 语句一起交给数据库执行时，攻击者可以在原始查询后面“堆叠”上任意第二条语句（如 <code>INSERT</code> / <code>UPDATE</code> / <code>DELETE</code> / <code>DDL</code>）。</p>
  <p>PHP 中常见的可堆叠执行方式：</p>
  <pre>mysqli_multi_query()      // MySQL 经典堆叠入口（本题的“同款”考点）
SQLite3::exec()           // SQLite 底层 sqlite3_exec() 支持分号多语句 ← 本题
PDO->exec() (pdo_sqlite)  // 同样支持多语句</pre>
  <p>对比：<code>mysqli_query()</code> / <code>SQLite3::query()</code> 只执行<b>单条</b>语句，堆叠会被拒绝 —— 所以“能不能堆叠”取决于应用用了哪个执行接口。</p>
  <p style="color:var(--dim)">修复思路：SQL 与用户输入分离（参数化查询 / 预编译），并尽量使用只执行单条语句的接口。</p>
</div>

<div class="card">
  <h2>商品查询</h2>
  <form method="GET" class="form-row">
    <label>商品编号 id：</label>
    <input type="text" name="id" value="<?= htmlspecialchars($id) ?>" style="width:320px">
    <button type="submit">查询</button>
  </form>
  <p class="sub">后端执行的 SQL：<code class="mono"><?= htmlspecialchars($sql) ?></code></p>
  <?php if ($err): ?><p class="sqlerr">SQL 错误：<?= htmlspecialchars($err) ?></p><?php endif; ?>
  <?php if ($show): ?>
  <table><tr><th>id</th><th>title</th><th>price</th></tr>
    <?php foreach ($show as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= htmlspecialchars((string)$r['title']) ?></td><td><?= htmlspecialchars((string)$r['price']) ?></td></tr><?php endforeach; ?>
  </table>
  <?php else: ?><p class="sub">（没有 id 对应的商品）</p><?php endif; ?>
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
    <input type="text" id="flagInput" placeholder="flag{sql4_xxxxxxxx}">
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
