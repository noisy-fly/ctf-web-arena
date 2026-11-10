<?php
// ============================================================
// rce1 · 无过滤命令注入
// 漏洞点：$_GET['ip'] 未做任何过滤，直接拼接到 shell 命令中
// 教学目标：认识 OS 命令注入与 shell 连接符（; && || |）
// ============================================================
$output = null;
$ip_param = isset($_GET['ip']) ? $_GET['ip'] : '';
if ($ip_param !== '') {
    $ip = $ip_param;                        // ← 没有任何过滤
    ob_start();
    system('ping -c 2 ' . $ip);             // ← 漏洞：直接字符串拼接
    $output = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>rce1 · 无过滤命令注入 | CTF 教学靶场</title>
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
<span class="badge">RCE · 命令注入 · 第 1 关</span>
<h1>Ping 诊断工具 —— 无过滤命令注入</h1>
<div class="meta">难度：★☆☆☆☆ 入门 · 关卡 ID：<code>rce1</code> · 运行时：PHP</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>运维小组上线了一个「网络诊断小工具」，输入一个 IP 地址，服务器会帮你 <code>ping</code> 两次并返回结果。</p>
<p>听说这工具上线第一天就被打出 flag 了。目标：读取本目录下 <code>flag.php</code> 文件的内容拿到 flag。</p>
<p class="hint-note">💡 注意：直接用浏览器访问 <code>flag.php</code> 是<b>空的</b>——flag 藏在 PHP 注释里，只有把它当"文本"读出来才能看见。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>目标 IP：<input type="text" name="ip" placeholder="例如 127.0.0.1" value="<?= htmlspecialchars($ip_param, ENT_QUOTES) ?>"></label>
<button type="submit">开始 Ping</button>
</form>
<?php if ($output !== null): ?>
<div class="result"><div class="t">ping -c 2 <?= htmlspecialchars($ip_param, ENT_QUOTES) ?> 的输出</div>
<pre><?= htmlspecialchars($output, ENT_QUOTES) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p><b>命令注入（OS Command Injection）</b>：应用程序把用户输入拼接到系统命令中执行，且未做充分过滤，攻击者可以"追加"或"改写"原本的命令。</p>
<p>本关代码（已给出，黑白盒结合学习）：</p>
<pre>&lt;?php
$ip = $_GET['ip'];              // 没有任何过滤
system('ping -c 2 ' . $ip);     // 直接拼进 shell 命令
?&gt;</pre>
<p>如果传入 <code>?ip=127.0.0.1</code>，shell 收到的命令是 <code>ping -c 2 127.0.0.1</code>。那如果传入 <code>?ip=127.0.0.1; whoami</code> 呢？shell 会把它当成<b>两条命令</b>依次执行。常用连接符：</p>
<table>
<tr><th>符号</th><th>含义</th><th>示例</th></tr>
<tr><td><code>;</code></td><td>顺序执行两条命令（不管前一条成败）</td><td><code>ping -c 1 x; id</code></td></tr>
<tr><td><code>&amp;&amp;</code></td><td>前一条成功才执行后一条</td><td><code>ping -c 1 x &amp;&amp; id</code></td></tr>
<tr><td><code>||</code></td><td>前一条失败才执行后一条</td><td><code>ping -c 1 x || id</code></td></tr>
<tr><td><code>|</code></td><td>前一条的输出作为后一条的输入</td><td><code>ping -c 1 x | wc -l</code></td></tr>
<tr><td>反引号 / <code>$()</code></td><td>命令替换，输出嵌入字符串</td><td><code>echo $(id)</code></td></tr>
</table>
<p>⚠️ 传输细节：<code>&amp;</code>、<code>|</code> 在 URL 里建议写编码形式（<code>%26%26</code>、<code>%7C</code>），避免被浏览器/代理误解。</p>
</section>

<section class="card">
<h2>渐进提示（点开一条想一条，别一口气全看）</h2>
<details><summary>提示 1：输入框的内容去哪了？</summary><p>看"知识点讲解"里的源码：你的输入被<b>原样拼接</b>在 <code>ping -c 2 </code> 后面交给 shell。试着让 shell "以为你只输了个 IP"，然后再补一条自己的命令。</p></details>
<details><summary>提示 2：先证明能执行第二条命令</summary><p>试试 <code>?ip=127.0.0.1;id</code> 或 <code>?ip=127.0.0.1%26%26id</code>，看输出里是否多了一行 <code>uid=...</code>。能看到就说明命令注入成立。</p></details>
<details><summary>提示 3：flag 在哪、怎么读</summary><p>flag 在<b>当前目录</b>的 <code>flag.php</code> 里。用 <code>cat</code> 读它：<code>?ip=127.0.0.1;cat flag.php</code>（也可以 <code>?ip=127.0.0.1%26%26cat%20flag.php</code>，或 <code>?ip=127.0.0.1|cat flag.php</code>）。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><span></span><a href="../rce2">下一关：rce2 · 过滤了空格 →</a></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=rce1&flag='+encodeURIComponent(v)})
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
