<?php
// ============================================================
// varcover2 · parse_str + $$ 可变变量
// 漏洞链：parse_str($input,$vars) 解析用户输入 → foreach $$k=$v
//         把键导入为真实变量（模拟 PHP5 时代 parse_str($q) 单参数行为）
// 通关条件：$$name === 'admin'
// 黑名单：input 字符串里不允许出现 level2（挡住 name=level2 的直球解）
// 解法：?input=name=x&x=admin  （先造一个值=admin 的变量，再让 name 指向它）
// 进阶变体：双重 URL 编码绕过更严的黑名单（writeup 有演示）
// ============================================================
define('FLAG', 'flag{varcover2_52098fb7}');

// 权限等级：level1=访客（公开），level2=admin（内部变量，值恰好是字符串 'admin'）
$level1 = 'guest';
$level2 = 'admin';

$input = isset($_GET['input']) ? (string)$_GET['input'] : '';
$blocked = '';
$msg = '';
$opened = false;

if ($input !== '') {
    if (preg_match('/level2|GLOBALS|_GET|_POST|_COOKIE|_SERVER/i', $input)) {
        $blocked = '输入被安全策略拦截：检测到受保护标识（level2 / 超全局变量）';
    } else {
        // 老代码"兼容层"：把 input 当 query string 解析后导入为真实变量
        $vars = array();
        parse_str($input, $vars);
        foreach ($vars as $k => $v) {
            if (in_array($k, array('vars', 'k', 'v', 'input', 'FLAG', 'flag'), true)) continue;
            $$k = $v;   // ← $$ 可变变量：$k 的"值"被当成变量名
        }
        // 门禁判定：$name 指向的变量（$$name）必须等于 'admin'
        if (isset($name) && is_string($name) && $name !== '' && isset($$name) && $$name === 'admin') {
            $opened = true;
        } else {
            $msg = '门禁未通过：$name 未设置，或 $$name 的值不是 admin。当前 $name = ' .
                   (isset($name) && is_string($name) ? var_export($name, true) : '(null)');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>varcover2 · parse_str + $$ | CTF 教学靶场</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:920px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#a3e635;color:#1a2e05;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
input[type=text]{width:70%;min-width:260px;padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer}
button:hover{filter:brightness(1.15)}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
table{border-collapse:collapse;width:100%;font-size:14px}td,th{border:1px solid #334155;padding:6px 10px;text-align:left}th{color:var(--acc)}
.okbox{background:#052e16;border:1px solid #14532d;color:#bbf7d0;border-radius:8px;padding:14px 16px;margin-top:12px}
.nobox{background:#0b1220;border:1px solid #27364d;color:var(--dim);border-radius:8px;padding:14px 16px;margin-top:12px}
.errbox{background:#3b1519;border:1px solid #7f1d1d;color:#fca5a5;border-radius:8px;padding:14px 16px;margin-top:12px}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">变量覆盖 · 第 2 关</span>
<h1>星舰门禁 · 调试终端</h1>
<div class="meta">难度：★★★☆☆ 进阶 | 关卡 ID：<code>varcover2</code> · 运行时：PHP</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>星舰主门禁按<b>权限等级</b>放行：<code>$level1 = 'guest'</code>（访客），<code>$level2 = 'admin'</code>（管理员，内部变量，值恰好就是字符串 <code>admin</code>）。</p>
<p>为了兼容老设备，门禁带了一个<b>调试终端</b>：提交一段 query-string 格式的 <code>input</code>，系统会把它解析后<b>导入为真实变量</b>，然后执行判定：</p>
<pre>if ($$name === 'admin')  开门</pre>
<p>安全策略：input 里不允许出现 <code>level2</code>（也不许碰超全局变量）。<b>让门禁开</b>，flag 就在门后。</p>
</section>

<section class="card">
<h2>实验区 · 调试终端</h2>
<form method="get" action="">
<label>input：<input type="text" name="input" placeholder="例如 a=1&b=2" value="<?= htmlspecialchars($input, ENT_QUOTES) ?>"></label>
<button type="submit">导入并判定</button>
</form>
<?php if ($blocked): ?>
<div class="errbox">🚫 <?= htmlspecialchars($blocked, ENT_QUOTES) ?></div>
<?php elseif ($opened): ?>
<div class="okbox">🔓 门禁开启（$$name === 'admin'）。门后铭牌：<b><?= FLAG ?></b></div>
<?php elseif ($msg): ?>
<div class="nobox"><?= htmlspecialchars($msg, ENT_QUOTES) ?></div>
<?php endif; ?>
<table style="margin-top:14px">
<tr><th>变量</th><th>当前值</th><th>说明</th></tr>
<tr><td><code>$level1</code></td><td><code>'guest'</code></td><td>访客等级（公开）</td></tr>
<tr><td><code>$level2</code></td><td><code>'admin'</code></td><td>内部权限变量——黑名单不许你直接引用它</td></tr>
<tr><td><code>$name</code></td><td><code><?= isset($name) && is_string($name) ? htmlspecialchars(var_export($name, true)) : '(null)' ?></code></td><td>判定用指针：<code>$$name</code> 会取"名为 name 值"的那个变量</td></tr>
</table>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关核心代码：</p>
<pre>&lt;?php
$level1 = 'guest';  $level2 = 'admin';          // 权限变量
$vars = array();  parse_str($input, $vars);      // 解析用户 input
foreach ($vars as $k =&gt; $v) { $$k = $v; }       // ← $$ 把键名变成真实变量（导入）
if ($$name === 'admin') { 开门; }                // ← $$name：按 $name 的值取变量</pre>
<p><b>可变变量 <code>$$</code></b>：变量名本身来自另一个变量的值。<code>$name='level2'</code> 时 <code>$$name</code> 就是 <code>$level2</code>。一旦"变量名"可被用户输入决定（导入循环里的 <code>$$k</code>），用户就能创建/覆盖任意普通变量。</p>
<p>本关黑名单挡住了 <code>name=level2</code> 这条直路——但 <code>$$name</code> 的判定只要求"<code>$name</code> 指向的某个变量的<b>值</b>是 <code>admin</code>"。哪个变量、从哪来，一概不问：你可以<b>自己造一个</b>。</p>
<p><code>parse_str()</code> 的背景：PHP5 时代 <code>parse_str($q)</code> 单参数会直接把解析结果导入为全局/当前变量——无数老系统因此被变量覆盖打穿（覆盖 <code>$CONFIG</code>、<code>$is_admin</code>、文件路径……）。PHP 8 已移除单参数用法，但"解析 + $$ 导入"的自研兼容层会原样复刻这个漏洞。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：先读懂判定条件</summary><p><code>$$name === 'admin'</code> 不要求 name 等于 level2，只要求"<b>名字叫 name 值的那个变量</b>"的值是 'admin'。它可以是任何名字的变量。</p></details>
<details><summary>提示 2：变量是哪来的</summary><p>导入循环会把 input 里的每个键都变成真实变量。也就是说，你既能决定 <code>$name</code> 的值，也能<b>凭空创建一个值为 'admin' 的变量</b>。</p></details>
<details><summary>提示 3：payload</summary><p><code>?input=name=x&x=admin</code>：导入后 <code>$name='x'</code>、<code>$x='admin'</code>，于是 <code>$$name = $x = 'admin'</code> → 开门。<br>（若黑名单更严、连 admin 都拦，还可以用双重 URL 编码让 parse_str 解码后才出现敏感词——见 writeup 变体。）</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../varcover1">← 上一关：varcover1</a><a href="../xxe1">下一关：xxe1 · XXE 有回显 →</a></nav>
<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=varcover2&flag='+encodeURIComponent(v)})
   .then(function(r){return r.text().then(function(t){
      var msg='HTTP '+r.status+': '+t;
      try{var j=JSON.parse(t);
        if(typeof j.correct==='boolean')msg=j.correct?'✅ 回答正确！':'❌ flag 不对，再试试';
        else if(j.message!==undefined)msg=j.message;
      }catch(e){}
      el.textContent=msg;})})
   .catch(function(e){el.textContent='提交失败('+e.message+')：请从靶场门户页提交'});
}
</script>
</body>
</html>
