<?php
declare(strict_types=1);
/* ------------------------------------------------------------------
 * sql9 黑名单过滤绕过（str_ireplace 删除式 WAF + 空格过滤）
 * 绕过手法：内联注释(斜杠星星斜杠)或 %0a 替代空格；双写(selselectect)重组关键字
 * 教学靶场 · 仅用于授权学习环境
 * ------------------------------------------------------------------ */

$META = json_decode(file_get_contents(__DIR__ . '/meta.json'), true);

function db(): SQLite3 {
    $f = __DIR__ . '/data.sqlite3';
    $db = new SQLite3($f);
    $db->enableExceptions(true);
    $db->busyTimeout(5000);
    if (!$db->querySingle("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='items'")) {
        $db->exec(file_get_contents(__DIR__ . '/init.sql'));
    }
    return $db;
}
$db = db();

/* ---------- 简陋 WAF：删除式黑名单（单遍替换，无二次校验） ---------- */
function waf(string $s): string {
    $black = ['select','union','from','where','and','or','order','limit',
              'insert','update','delete','--','#',' '];
    return str_ireplace($black, '', $s);   // 只删一遍，删完直接用 —— 漏洞所在
}

/* ---------- 题目逻辑：商品搜索 ---------- */
$raw  = isset($_GET['q']) ? (string)$_GET['q'] : '';
$clean = waf($raw);
$sql  = "SELECT id, name, info FROM items WHERE name LIKE '%" . $clean . "%'";

$rows = []; $err = '';
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
.wafbox{background:#231414;border:1px solid #5c2b2b;border-radius:6px;padding:8px 12px;margin-top:8px;font-size:13px}
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
  <p>“极客书店”上新了搜索功能。上次被注入之后，开发连夜加了一个 <b>WAF</b>：把输入里的 <code>select / union / from / where / and / or / order / limit / insert / update / delete / -- / # / 空格</code> <b>统统删掉</b>，然后再拼进 SQL。</p>
  <p>flag 在一个神秘表里（表名你得自己找）。WAF 挡得住你吗？</p>
</div>

<div class="card">
  <h2>知识点充电站</h2>
  <p><b>黑名单 + 删除式过滤</b>是最常见的绕过题模型。两个通杀思路：</p>
  <pre>① 空格被删 → 用 /**/ 内联注释当空白分隔符：  sel/**/ect 无效，但 union/**/select 有效
   （SQL 里注释起到 token 分隔作用；SQLite/MySQL 都认；%0a 换行同理）

② 关键字被"删除" → 双写：把词的一部分藏在另一份里
   selselectect  --WAF删掉中间的 select-->  select     （替换只扫一遍，不回头复查）
   uniunionon    --> union    frfromom --> from    whwhereere --> where
   aandnd        --> and      oorr     --> or</pre>
  <p>为什么双写有效：<code>str_ireplace()</code> 从左到右扫描<b>一遍</b>，匹配到的都删掉，但删除后拼出来的新词<b>不会再被扫第二次</b>。</p>
  <p style="color:var(--dim)">根治：黑名单对抗是无底洞，参数化查询 / 预编译才是唯一正解。</p>
</div>

<div class="card">
  <h2>商品搜索</h2>
  <form method="GET" class="form-row">
    <label>关键词：</label>
    <input type="text" name="q" value="<?= htmlspecialchars($raw) ?>" style="width:420px">
    <button type="submit">搜索</button>
  </form>
  <?php if ($raw !== ''): ?>
  <div class="wafbox">
    原始输入：<span class="mono"><?= htmlspecialchars($raw) ?></span><br>
    WAF 之后：<span class="mono"><?= htmlspecialchars($clean) ?></span><br>
    拼接的 SQL：<span class="mono"><?= htmlspecialchars($sql) ?></span>
  </div>
  <?php endif; ?>
  <?php if ($err): ?><p class="sqlerr">SQL 错误：<?= htmlspecialchars($err) ?></p><?php endif; ?>
  <?php if ($rows): ?>
  <table><tr><th>id</th><th>name</th><th>info</th></tr>
    <?php foreach ($rows as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= htmlspecialchars((string)$r['name']) ?></td><td><?= htmlspecialchars((string)$r['info']) ?></td></tr><?php endforeach; ?>
  </table>
  <?php elseif ($raw !== ''): ?><p class="sub">（没有匹配的商品）</p><?php endif; ?>
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
    <input type="text" id="flagInput" placeholder="flag{sql9_xxxxxxxx}">
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
