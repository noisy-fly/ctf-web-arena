<?php
// ============================================================
// ssrf3 · SSRF 之 IP 黑名单绕过
// 过滤：对 URL 的 host 做"字面量"黑名单（localhost/127.0.0.1/内网段…）
// 绕过：IP 的十进制/十六进制/八进制/省略写法（2130706433、0x7f000001、0177.0.0.1、127.1）
// ============================================================
$url = isset($_GET['url']) ? trim($_GET['url']) : '';
$body = null; $error = null; $blocked = null;
$blacklist = array('localhost', '127.0.0.1', '::1', '0.0.0.0', '127.0',
                   '192.168.', '10.', '172.16.', '172.17.', '172.18.', '172.19.',
                   '172.2', '172.30.', '172.31.', '169.254.');
if ($url !== '') {
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    $host   = strtolower((string)parse_url($url, PHP_URL_HOST));
    if (!in_array($scheme, array('http', 'https'), true)) {
        $error = '仅支持 http/https 协议';
    } else {
        foreach ($blacklist as $b) {
            if ($host !== '' && strpos($host, $b) !== false) {
                $blocked = "检测到内网/回环地址（{$host}），已拦截";
                break;
            }
        }
        if ($blocked === null) {
            $ctx = stream_context_create(array('http' => array('timeout' => 4, 'ignore_errors' => true)));
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) {
                $error = '请求失败（目标不可达或超时）';
            } else {
                $body = $raw;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ssrf3 · IP 黑名单绕过 | CTF 教学靶场</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:920px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#a855f7;color:#1b0630;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
input[type=text]{width:75%;min-width:260px;padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer}
button:hover{filter:brightness(1.15)}
.result{background:#0b1220;border:1px solid #27364d;border-radius:8px;margin-top:12px}
.result .t{padding:6px 12px;border-bottom:1px solid #27364d;color:var(--dim);font-size:12px}
.result pre{border:0;margin:0}
.errbox{background:#3b1519;border:1px solid #7f1d1d;color:#fca5a5;border-radius:8px;padding:10px 14px;margin-top:12px}
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
<span class="badge">SSRF · 第 3 关</span>
<h1>加了黑名单的预览工具 —— IP 限制绕过</h1>
<div class="meta">难度：★★★☆☆ 进阶 | 关卡 ID：<code>ssrf3</code> · 运行时：PHP</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>ssrf1 被打穿后，安全组给预览工具上了"内网黑名单"：URL 的主机名里出现 <code>localhost</code>、<code>127.0.0.1</code>、<code>10.</code>、<code>192.168.</code> 等字样一律拦截。</p>
<p>管理接口 <code>internal.php</code> 还在原地（仅本机可访问）。目标：绕过黑名单，再次借服务器的手访问它。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>URL：<input type="text" name="url" placeholder="http://…" value="<?= htmlspecialchars($url, ENT_QUOTES) ?>"></label>
<button type="submit">预览</button>
</form>
<?php if ($blocked !== null): ?>
<div class="errbox">🚫 <?= htmlspecialchars($blocked, ENT_QUOTES) ?></div>
<?php elseif ($error !== null): ?>
<div class="errbox">⚠️ <?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php elseif ($body !== null): ?>
<div class="result"><div class="t">服务器请求 <?= htmlspecialchars($url, ENT_QUOTES) ?> 的响应</div>
<pre><?= htmlspecialchars(substr((string)$body, 0, 4000), ENT_QUOTES) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关的过滤（关键缺陷：<b>拿"字符串"当"IP"比</b>）：</p>
<pre>&lt;?php
$host = parse_url($url, PHP_URL_HOST);
foreach (['localhost','127.0.0.1','192.168.','10.', ...] as $b) {
    if (strpos($host, $b) !== false) 拦截;
}
file_get_contents($url);   // 解析 DNS/连接时，操作系统并不这么看
?&gt;</pre>
<p>操作系统解析地址时接受 IP 的<b>多种等价写法</b>，它们和 <code>127.0.0.1</code> 殊途同归，但字面量完全不同：</p>
<table>
<tr><th>写法</th><th>含义</th><th>等价于</th></tr>
<tr><td><code>127.1</code></td><td>省略零段（inet_aton 短格式）</td><td>127.0.0.1</td></tr>
<tr><td><code>2130706433</code></td><td>十进制整数</td><td>127.0.0.1</td></tr>
<tr><td><code>0x7f000001</code></td><td>十六进制</td><td>127.0.0.1</td></tr>
<tr><td><code>0177.0.0.1</code></td><td>八进制段</td><td>127.0.0.1</td></tr>
<tr><td>任意解析到 127.0.0.1 的域名</td><td>如自建 DNS / DNS rebinding</td><td>127.0.0.1</td></tr>
<tr><td>302 跳转</td><td>黑名单只查第一跳，目标重定向到内网</td><td>任意内网</td></tr>
</table>
<p>换算原理：127.0.0.1 的四段字节 127,0,0,1 → 十进制 127×256³+0+0+1 = <b>2130706433</b>；十六进制 0x7f 0x00 0x00 0x01 → <b>0x7f000001</b>。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：先看看黑名单多"字面"</summary><p>分别提交 <code>http://127.0.0.1/</code> 和 <code>http://a.b.com/</code>：前者被拦、后者放行——过滤比对的是<b>字符串</b>。</p></details>
<details><summary>提示 2：换个写法写同一个 IP</summary><p>127.0.0.1 还能写成 <code>127.1</code>、十进制 <code>2130706433</code>、十六进制 <code>0x7f000001</code>、八进制 <code>0177.0.0.1</code>。字面全变了，解析结果没变。</p></details>
<details><summary>提示 3：payload</summary><p><code>?url=http://127.1/ssrf3/internal.php</code><br><code>?url=http://2130706433/ssrf3/internal.php</code><br><code>?url=http://0x7f000001/ssrf3/internal.php</code><br>（独立容器挂载则把 <code>/ssrf3</code> 去掉；非 80 端口记得带端口）</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../ssrf2">← 上一关：ssrf2</a><a href="../ssrf4">下一关：ssrf4 · gopher 打内网 Redis →</a></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=ssrf3&flag='+encodeURIComponent(v)})
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
