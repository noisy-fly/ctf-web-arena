<?php
declare(strict_types=1);
/* ------------------------------------------------------------------
 * sql8 ORDER BY 注入（排序字段用户可控 → CASE WHEN 布尔盲注）
 * 教学靶场 · 仅用于授权学习环境
 * ------------------------------------------------------------------ */

$META = json_decode(file_get_contents(__DIR__ . '/meta.json'), true);

function db(): SQLite3 {
    $f = __DIR__ . '/data.sqlite3';
    $db = new SQLite3($f);
    $db->enableExceptions(true);
    $db->busyTimeout(5000);
    if (!$db->querySingle("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='goods'")) {
        $db->exec(file_get_contents(__DIR__ . '/init.sql'));
    }
    return $db;
}
$db = db();

/* ---------- 题目逻辑：商品列表排序 ---------- */
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'id';
// TODO(实习生): 记得做 $sort 白名单校验 —— 上线前一定改！（flag 就藏在这没写完的检查后面）
$sql = "SELECT id, title, price FROM goods ORDER BY " . $sort;   // 漏洞：排序表达式直接拼接

$rows = [];
$err = '';
try {
    $q = $db->query($sql);
    while ($r = $q->fetchArray(SQLITE3_ASSOC)) $rows[] = $r;
} catch (Throwable $e) {
    $err = $e->getMessage();
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
table{border-collapse:collapse;width:100%;margin:10px 0}
th,td{border:1px solid var(--bd);padding:6px 12px;text-align:left;font-size:14px}
th{color:var(--dim);background:#10151c}
th a{color:var(--acc);text-decoration:none}
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
.form-row{margin:8px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.sqlerr{color:var(--err);font-size:13px}
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
  <p>“极客书店”的书目列表支持按列排序：点击表头，或直接传 <code>?sort=title</code> / <code>?sort=price</code>。</p>
  <p>代码里有一行实习生的 TODO：<b>“记得做 $sort 白名单校验”</b>——但它一直没来得及写。</p>
  <p>flag 在数据库表 <code>flag_secret</code> 的 <code>flag</code> 列里（内部资料泄露了表结构）。SELECT 的列是写死的，union 也没戏——这次从 <code>ORDER BY</code> 里把信息“排”出来。</p>
</div>

<div class="card">
  <h2>知识点充电站</h2>
  <p><b>ORDER BY / LIMIT 注入</b>：注入点出现在排序/分页子句里。它的特殊性：</p>
  <pre>1. ORDER BY 的参数无法用占位符绑定 —— 绑定后会被当成"常量"而非列名/表达式
   （所以开发只能拼接 → 注入高发区）
2. ORDER BY 之后不能接 UNION（SELECT 的输出列已经定死）
3. 但 ORDER BY 后面可以放【任意表达式】—— 包括 CASE WHEN、标量子查询！</pre>
  <p>于是可以用<b>排序结果的差异</b>当布尔信道：</p>
  <pre>ORDER BY CASE WHEN (条件) THEN id ELSE title END
-- 条件真 → 按 id 排（首行是 A 书）；条件假 → 按 title 排（首行是 B 书）
-- 观察"第一行是谁" = 读出 1bit 信息</pre>
  <p>MySQL 同场景还有更快姿势：<code>ORDER BY updatexml(1,concat(0x7e,(SELECT ...)),1)</code> 报错出数据、<code>ORDER BY 1 PROCEDURE ANALYSE()</code> 等；SQLite 没有报错函数，排序盲注就是标准解。</p>
</div>

<div class="card">
  <h2>书目列表</h2>
  <table>
    <tr><th><a href="?sort=id">id ↕</a></th><th><a href="?sort=title">title ↕</a></th><th><a href="?sort=price">price ↕</a></th><th><a href="?sort=id%20DESC">自定义…</a></th></tr>
  </table>
  <form method="GET" class="form-row">
    <label>排序表达式：</label>
    <input type="text" name="sort" value="<?= htmlspecialchars($sort) ?>" style="width:420px">
    <button type="submit">排序</button>
  </form>
  <p class="sub">后端执行的 SQL：<code class="mono"><?= htmlspecialchars($sql) ?></code></p>
  <?php if ($err): ?><p class="sqlerr">SQL 错误：<?= htmlspecialchars($err) ?></p><?php endif; ?>
  <table><tr><th>id</th><th>title</th><th>price</th></tr>
    <?php foreach ($rows as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= htmlspecialchars((string)$r['title']) ?></td><td><?= htmlspecialchars((string)$r['price']) ?></td></tr><?php endforeach; ?>
  </table>
  <p class="sub">※ 注意观察首行：按 id 排序时首行是《Web安全入门指南》；按 title 排序时首行是《CTF从零到一》。</p>
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
    <input type="text" id="flagInput" placeholder="flag{sql8_xxxxxxxx}">
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
