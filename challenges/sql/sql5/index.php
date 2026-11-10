<?php
declare(strict_types=1);
/* ------------------------------------------------------------------
 * sql5 时间盲注（SQLite：无回显差异，用 randomblob 制造延时侧信道）
 * 教学靶场 · 仅用于授权学习环境
 * ------------------------------------------------------------------ */

$META = json_decode(file_get_contents(__DIR__ . '/meta.json'), true);

function db(): SQLite3 {
    $f = __DIR__ . '/data.sqlite3';
    $db = new SQLite3($f);
    $db->enableExceptions(true);
    $db->busyTimeout(5000);
    if (!$db->querySingle("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='cards'")) {
        $db->exec(file_get_contents(__DIR__ . '/init.sql'));
    }
    return $db;
}
$db = db();

/* ---------- 题目逻辑：礼品卡查询（无任何结果回显） ---------- */
$card = isset($_GET['card']) ? (string)$_GET['card'] : '1';
$sql  = "SELECT cardno, balance FROM cards WHERE id = " . $card;   // 漏洞：直接拼接

$accepted = false;
try {
    $q = $db->query($sql);
    if ($q) $accepted = true;          // 无论有没有查到行，页面对用户只说同一句话
} catch (Throwable $e) {
    $accepted = false;                 // 出错也只说同一句话：彻底堵死回显
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
input[type=text]{background:#0b0f14;border:1px solid var(--bd);color:var(--fg);border-radius:6px;padding:8px 12px;font-family:var(--mono);font-size:14px;width:70%}
button{background:#21262d;border:1px solid var(--bd);color:var(--fg);border-radius:6px;padding:8px 18px;cursor:pointer;font-size:14px}
button:hover{border-color:var(--acc);color:var(--acc)}
#flagResult{margin-top:10px;font-size:14px;min-height:22px}
.ok{color:var(--ok)} .bad{color:var(--err)}
footer{border-top:1px solid var(--bd);margin-top:30px;padding-top:16px;color:var(--dim);font-size:13px;display:flex;justify-content:space-between}
footer a{color:var(--dim);text-decoration:none} footer a:hover{color:var(--acc)}
.form-row{margin:8px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.notice{background:#10261a;border:1px solid #1f4d2e;border-radius:6px;padding:10px 14px;color:#7ee2a8;margin-top:10px}
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
  <p>“星愿礼品卡”自助查询系统。输入礼品卡编号即可查询余额——但出于“安全考虑”，查询结果<b>不会展示在网页上</b>，而是通过短信发送到持卡人预留手机号。</p>
  <p>所以，无论你输入什么，页面永远只会告诉你同一句话。真的什么都问不出来吗？</p>
  <p class="sub">提示：flag 藏在数据库表 <code>flag_secret</code> 的 <code>flag</code> 列里（内部资料泄露了表结构）。</p>
</div>

<div class="card">
  <h2>知识点充电站</h2>
  <p><b>时间盲注（Time-based Blind SQL Injection）</b>：当页面既不回显数据、也不显示错误、连布尔差异都没有时，可以让数据库在“条件为真”时<b>故意变慢</b>——用响应时间当作 1bit 的信息通道，一个字符一个字符地把数据“问”出来。</p>
  <p>不同数据库的延时原语：</p>
  <pre>MySQL     : IF(cond, SLEEP(3), 0)            /  BENCHMARK(100000000, MD5('x'))
PostgreSQL: SELECT pg_sleep(3)               (堆叠或 CASE 内子查询)
SQLite    : 没有 sleep()！但 randomblob(N) 要生成 N 个随机字节，
            CASE WHEN cond THEN randomblob(60000000) IS NOT NULL ELSE 0 END
            条件为真时耗时约 0.8 秒（N 可按机器调大调小）</pre>
  <p>SQL 的 <code>CASE WHEN</code> 与 <code>AND</code> 都具备<b>惰性（短路）求值</b>：条件为假时根本不会去算延时表达式，所以“假条件”几乎瞬时返回——“快/慢”就是 0 和 1。</p>
</div>

<div class="card">
  <h2>礼品卡查询</h2>
  <form method="GET" class="form-row">
    <label>礼品卡编号：</label>
    <input type="text" name="card" value="<?= htmlspecialchars($card) ?>" style="width:320px">
    <button type="submit">查询</button>
  </form>
  <?php if (isset($_GET['card'])): ?>
  <div class="notice">✔ 查询已受理，余额信息将通过短信发送至持卡人预留手机号，请留意查收。</div>
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
    <input type="text" id="flagInput" placeholder="flag{sql5_xxxxxxxx}">
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
