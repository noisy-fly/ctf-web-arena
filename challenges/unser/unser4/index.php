<?php
// ============================================================
// unser4 · phar 反序列化（反序列化第四课 · 终关）
// 场景：主题工坊支持上传「皮肤包」（官方皮肤包就是 phar 格式，
//       后缀 .skin）。导入预览时服务端读取包的 metadata 展示
//       作者信息 —— Phar::loadPhar + getMetadata 一步就完成了
//       一次 unserialize（用户可控的 metadata！）
// 考点： ① phar 文件结构（stub/manifest/metadata）；
//       ② 经典「file_exists/getimagesize 等流函数触发反序列化」
//          在 PHP 8 已失效（元数据改为惰性，仅显式读取时反序列化）
//          —— 本机 8.5 与容器 8.2 双实测；
//       ③ 现代 PHP 下的真实攻击面：应用显式读取用户可控 phar 的
//          metadata（本题场景）；
//       ④ metadata 里埋 POP 链（__destruct→__toString→__get）
// ============================================================
require __DIR__ . '/classes.php';

const UPLOAD_DIR = __DIR__ . '/uploads/';
$action = $_GET['action'] ?? 'home';
$msg = '';
$files = is_dir(UPLOAD_DIR) ? array_diff(scandir(UPLOAD_DIR), ['.', '..', '.gitkeep']) : [];

if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pack'])) {
    $up = $_FILES['pack'];
    $ext = strtolower(pathinfo($up['name'], PATHINFO_EXTENSION));
    $allowed = ['skin', 'jpg', 'jpeg', 'png', 'gif'];   // skin = 官方皮肤包（phar 格式）
    if ($up['error'] !== UPLOAD_ERR_OK) {
        $msg = '上传失败（code ' . $up['error'] . '）';
    } elseif (!in_array($ext, $allowed, true)) {
        $msg = "只允许皮肤包(.skin)或图片：" . implode('/', $allowed);
    } elseif ($up['size'] > 64 * 1024) {
        $msg = '文件过大（≤64KB）';
    } else {
        if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0777, true);
        $safe = 'u_' . bin2hex(random_bytes(4)) . '_' . basename($up['name']);
        move_uploaded_file($up['tmp_name'], UPLOAD_DIR . $safe);
        $msg = "已保存为 uploads/{$safe}，到「导入预览」里试试";
    }
} elseif ($action === 'preview') {
    $name = basename($_GET['name'] ?? '');          // 只允许文件名，防路径穿越
    $path = UPLOAD_DIR . $name;
    if ($name === '' || !is_file($path)) {
        $msg = '文件不存在（先上传，再从下方文件名点进预览）';
    } elseif (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'skin') {
        $msg = '只有 .skin 皮肤包需要导入预览';
    } else {
        try {
            // ---------------------- 漏洞点 ----------------------
            Phar::loadPhar($path);                       // 任意后缀都能载入 phar 存档
            $meta = (new Phar('phar://' . $path))->getMetadata();   // ← metadata 反序列化在此发生
            $msg = '皮肤包载入成功！元数据：'
                . (is_object($meta) ? ('对象 ' . get_class($meta) . '（详情看页面末尾日志）') : var_export($meta, true));
        } catch (Throwable $e) {
            $msg = '皮肤包解析失败：' . $e->getMessage();
        }
    }
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>unser4 · phar 反序列化 | CTF 教学靶场</title>
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
<span class="badge">反序列化 · 第 4 关（终关）</span>
<h1>主题工坊 —— phar 反序列化</h1>
<div class="meta">难度：★★★★☆ 高阶 · 关卡 ID：<code>unser4</code> · 运行时：PHP 8</div>
</header>

<section class="card">
<h2>题目描述</h2>
<p>主题工坊支持上传「皮肤包」。官方皮肤包其实是 <b>phar</b> 格式（PHP 的打包格式，后缀 .skin 只是业务叫法），导入预览时服务端会读取包的 <b>metadata</b> 展示作者信息。</p>
<p>代码里一个 <code>unserialize()</code> 都没有——但 flag 就在本目录 <code>flag.php</code> 的注释里。想办法把 POP 链塞进皮肤包。</p>
</section>

<section class="card">
<h2>实验区 · ① 上传皮肤包</h2>
<form method="post" action="?action=upload" enctype="multipart/form-data">
<label>文件（.skin 皮肤包 / 图片，≤64KB）：<input type="file" name="pack" style="color:var(--dim)"></label>
<button type="submit">上传</button>
</form>
<?php if ($msg !== '' && $action === 'upload'): ?>
<div class="result"><div class="t">上传结果</div><pre><?= e($msg) ?></pre></div>
<?php endif; ?>
<?php if ($files): ?>
<p style="font-size:13px;color:var(--dim)">uploads/ 下已有：
<?php foreach ($files as $f): ?>
<a style="color:var(--acc)" href="?action=preview&amp;name=<?= e($f) ?>"><?= e($f) ?></a>
<?php endforeach; ?></p>
<?php endif; ?>
</section>

<section class="card">
<h2>实验区 · ② 导入预览（点击上方文件名即可）</h2>
<form method="get" action="">
<input type="hidden" name="action" value="preview">
<label>皮肤包文件名：<input type="text" name="name" placeholder="u_xxxxxxxx_evil.skin" value="<?= e($_GET['name'] ?? '') ?>" style="width:60%"></label>
<button type="submit">预览</button>
</form>
<?php if ($msg !== '' && $action === 'preview'): ?>
<div class="result"><div class="t">预览结果</div><pre><?= e($msg) ?></pre></div>
<?php endif; ?>
<p style="color:var(--dim);font-size:13px">预览会打印元数据类型；皮肤包内部对象产生的日志会追加在<b>页面源码末尾</b>（请求结束时析构）。</p>
</section>

<section class="card">
<h2>知识点讲解</h2>
<p><b>phar 是什么</b>：PHP 的打包格式（类似 jar）。结构：</p>
<pre>┌─ stub：一段 PHP 代码，必须含 __HALT_COMPILER(); —— 前面可加任意伪装内容（如 GIF89a）
├─ manifest：文件清单 + <b>metadata（任意序列化数据！）</b>
└─ 条目内容</pre>
<p><b>老教材的流包装触发法，PHP 8 已经死了</b>（本机 8.5 与容器 8.2 双实测）：旧 writeup 说 <code>file_exists('phar://x.jpg')</code>、<code>getimagesize(...)</code> 等文件函数一碰 phar:// 就会反序列化 metadata——现代 PHP 把元数据反序列化改成了<b>显式/惰性</b>，这些函数只解析 manifest 结构、不再 unserialize metadata。</p>
<table>
<tr><th>操作</th><th>PHP 8.2 / 8.5 实测</th></tr>
<tr><td><code>file_exists / is_dir / getimagesize / file_get_contents('phar://…')</code></td><td>❌ 不反序列化 metadata</td></tr>
<tr><td><code>Phar::loadPhar($任意后缀路径)</code> + <code>(new Phar('phar://'.$path))-&gt;getMetadata()</code></td><td>✅ <b>反序列化发生</b>（本题漏洞点）</td></tr>
</table>
<p>也就是说：现代 PHP 上 phar 漏洞的典型形态是——<b>应用业务上显式读取用户可控 phar 的 metadata</b>（皮肤包/插件包/配置包的作者信息就是最常见业务）。本题漏洞代码：</p>
<pre>Phar::loadPhar($path);                              // 任意后缀都能载入（phar 靠内容识别）
$meta = (new Phar('phar://' . $path))-&gt;getMetadata();  // ← metadata 里是什么类，就反序列化出什么对象</pre>
<p>metadata 里能埋对象，对象身上能挂 POP 链（本题三个类源码全给出，触发器与 unser2 不同，这次用 <code>__get</code>）：</p>
<pre>Notice::__destruct: '...' . $this-&gt;msg   → msg 是 Widget  → __toString
Widget::__toString: $this-&gt;plugin-&gt;template → template 不存在 → __get
Plugin::__get('template'): 查 hook → file_get_contents(hook['template'])   → sink</pre>
<p>phar 的另外两个经典性质仍然成立：<b>①后缀无关</b>（evil.skin/evil.jpg 都行，本题业务就是 .skin）；<b>②stub 可伪装</b>（GIF89a 开头照样是合法 phar）。</p>
</section>

<section class="card">
<h2>渐进提示（点开一条想一条，别一口气全看）</h2>
<details><summary>提示 1：反序列化入口在哪</summary><p>页面源码里没有 unserialize，但「导入预览」调用了 <code>getMetadata()</code>——phar 的 metadata 本身就是<b>序列化数据</b>，读取即反序列化。你要上传一个 metadata 里埋着 Notice 链对象的 .skin 文件。</p></details>
<details><summary>提示 2：怎么生成恶意 phar</summary><p>题目目录附了 <code>gen_phar.php</code>：<code>php -d phar.readonly=0 gen_phar.php</code> 产出 <code>evil.skin</code>（GIF89a 伪装头 + x.txt 条目 + metadata=Notice 链）。注意 <b>生成</b>phar 才需要 readonly=0，<b>使用</b>不需要。上传它。</p></details>
<details><summary>提示 3：触发</summary><p>上传后点 uploads/ 列表里的文件名（或手填 <code>?action=preview&name=u_xxxx_evil.skin</code>）。预览显示"对象 Notice"后，<b>页面源码末尾</b>会多出一行 <code>[Notice] &lt;?php /* flag... */ ?&gt;</code>——链在请求结束时析构，把 flag.php 打了出来。</p></details>
</section>

<section class="card">
<h2>提交 Flag</h2>
<form id="flagForm" class="flagrow" onsubmit="return false">
<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">
<button onclick="submitFlag()">提交</button>
</form>
<div id="flagResult"></div>
</section>

<nav class="nav"><a href="/unser3">← 上一关：unser3 · pickle</a><span></span></nav>
<footer>CTF Web 教学式靶场 · C 组 · 仅用于安全学习</footer>
</div>
<script>
function submitFlag(){
  var v=document.getElementById('flagInput').value.trim();
  var el=document.getElementById('flagResult');
  if(!v){el.textContent='请输入 flag';return}
  el.textContent='提交中...';
  fetch('/api/verify',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'challenge_id=unser4&flag='+encodeURIComponent(v)})
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
