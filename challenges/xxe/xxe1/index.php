<?php
// ============================================================
// xxe1 · XXE 有回显读文件
// 漏洞：DOMDocument::loadXML($_POST['xml'], LIBXML_NOENT | LIBXML_DTDLOAD)
//       替换外部实体 + 加载外部 DTD → <!ENTITY x SYSTEM "file://..."> 读任意文件并回显
// flag：deploy/flag_xxe1.txt（部署时复制到容器根 /flag_xxe1.txt 并删除 deploy/）
// ============================================================
// 机密档案路径（部署后为 /flag_xxe1.txt；本机自测时为 deploy/ 下的绝对路径）
$candidates = array('/flag_xxe1.txt', __DIR__ . '/deploy/flag_xxe1.txt', __DIR__ . '/flag_xxe1.txt');
$flagFile = '/flag_xxe1.txt';
foreach ($candidates as $c) { if (is_file($c)) { $flagFile = realpath($c); break; } }

$xml = isset($_POST['xml']) ? (string)$_POST['xml'] : '';
$parsed = null; $errs = array();
if ($xml !== '') {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $ok = @$dom->loadXML($xml, LIBXML_NOENT | LIBXML_DTDLOAD);   // ← 漏洞：NOENT 替换外部实体
    $errs = libxml_get_errors();
    libxml_clear_errors();
    if ($ok) { $parsed = $dom->textContent; }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>xxe1 · XXE 有回显读文件 | CTF 教学靶场</title>
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
.result{background:#0b1220;border:1px solid #27364d;border-radius:8px;margin-top:12px}
.result .t{padding:6px 12px;border-bottom:1px solid #27364d;color:var(--dim);font-size:12px}
.result pre{border:0;margin:0}
.errbox{background:#3b1519;border:1px solid #7f1d1d;color:#fca5a5;border-radius:8px;padding:10px 14px;margin-top:12px;font-size:13px}
.pathbox{background:#052e16;border:1px solid #14532d;color:#bbf7d0;border-radius:8px;padding:10px 14px;margin-top:12px;font-size:14px}
</style>
</head>
<body>
<div class="wrap">
<header>
<span class="badge">XXE · 第 1 关</span>
<h1>星际 HR · 简历导入器</h1>
<div class="meta">难度：★★☆☆☆ 基础 | 关卡 ID：<code>xxe1</code> · 运行时：PHP（DOMDocument + libxml2）</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>星际 HR 系统支持粘贴 XML 格式的简历批量导入。导入器会把解析出的<b>文本内容</b>原样回显给你核对。</p>
<p>另据内部通报：HR 主管把一份"机密档案"放在了服务器上，路径是公开目录之外……也不算太隐蔽（就在页面上告诉你）：</p>
<div class="pathbox">🗂️ 机密档案绝对路径：<code><?= h($flagFile) ?></code>（XML 解析器能读的，你能读到吗？）</div>
<p>期望的简历格式：</p>
<pre>&lt;resume&gt;
  &lt;name&gt;张三&lt;/name&gt;
  &lt;skill&gt;PHP / Go / 唱跳 Rap&lt;/skill&gt;
&lt;/resume&gt;</pre>
</section>

<section class="card">
<h2>实验区</h2>
<form method="post" action="">
<label for="xml">XML 简历内容：</label><br><br>
<textarea id="xml" name="xml" placeholder="把简历 XML 粘贴到这里">&lt;resume&gt;
  &lt;name&gt;张三&lt;/name&gt;
  &lt;skill&gt;PHP&lt;/skill&gt;
&lt;/resume&gt;</textarea><br><br>
<button type="submit">导入解析</button>
</form>
<?php if ($parsed !== null): ?>
<div class="result"><div class="t">解析成功 —— 文本内容（textContent）</div>
<pre><?= h($parsed) ?></pre></div>
<?php elseif ($xml !== ''): ?>
<div class="errbox">解析失败：
<?php foreach ($errs as $e): ?>
<?= h(trim($e->message)) ?> (line <?= $e->line ?>)
<?php endforeach; ?></div>
<?php endif; ?>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p>本关核心代码：</p>
<pre>&lt;?php
$dom = new DOMDocument();
$dom-&gt;loadXML($_POST['xml'], LIBXML_NOENT | LIBXML_DTDLOAD);
echo $dom-&gt;textContent;   // 实体被替换后，文件内容混进了回显</pre>
<p><b>XXE（XML External Entity，外部实体注入）</b>：XML 支持在 DTD 里声明"实体"（可理解为宏）。<b>外部实体</b>的值来自一个 URL——可以是 <code>file://</code> 本地文件、<code>http://</code> 远程地址等：</p>
<pre>&lt;!DOCTYPE resume [
  &lt;!ENTITY xxe SYSTEM "file:///etc/passwd"&gt;   &lt;!-- SYSTEM = 外部实体，读 URL --&gt;
]&gt;
&lt;resume&gt;&lt;name&gt;&amp;xxe;&lt;/name&gt;&lt;/resume&gt;            &lt;!-- 引用处被替换成文件内容 --&gt;</pre>
<p>解析器替换实体（<code>LIBXML_NOENT</code>）并允许加载外部资源（<code>LIBXML_DTDLOAD</code>）时，引用 <code>&amp;xxe;</code> 的位置就会"打印"出文件内容——回显型 XXE 一步读文件。</p>
<p>注意：libxml 是 C 库，不认识 PHP 的流封装——所以 <code>php://filter</code> 在这里<b>不可用</b>（那是 PHP include/file_get_contents 体系的能力），只能老老实实用 <code>file://</code>/<code>http://</code>。读到的内容若含 <code>&lt;</code>/<code>&amp;</code> 等 XML 特殊字符会导致实体不合法而报错（这也正是下一关"盲注"要解决的问题之一）。</p>
</section>

<section class="card">
<h2>渐进提示</h2>
<details><summary>提示 1：实体是个"变量"</summary><p>XML 的 DTD 可以声明实体：<code>&lt;!DOCTYPE resume [&lt;!ENTITY a "hello"&gt;]&gt;</code>，正文里 <code>&amp;a;</code> 会被替换成 hello。实体名你可以随便起，值也可以是 <b>SYSTEM "URL"</b> 形式的外部资源。</p></details>
<details><summary>提示 2：把实体值指向机密档案</summary><p>页面上已经给了机密档案的绝对路径。Windows 路径 <code>C:\x\y</code> 对应的 file URL 是 <code>file:///C:/x/y</code>；Linux 路径 <code>/flag_xxe1.txt</code> 对应 <code>file:///flag_xxe1.txt</code>。</p></details>
<details><summary>提示 3：payload</summary><pre>&lt;!DOCTYPE resume [
  &lt;!ENTITY xxe SYSTEM "file:///&lt;页面给的路径&gt;"&gt;
]&gt;
&lt;resume&gt;&lt;name&gt;&amp;xxe;&lt;/name&gt;&lt;skill&gt; infiltration&lt;/skill&gt;&lt;/resume&gt;</pre><p>导入后，回显里 <code>name</code> 的位置就是档案内容。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="../varcover2">← 上一关：varcover2</a><a href="../xxe2">下一关：xxe2 · XXE 盲注 OOB →</a></nav>
<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=xxe1&flag='+encodeURIComponent(v)})
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
