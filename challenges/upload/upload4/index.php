<?php
declare(strict_types=1);
/* ------------------------------------------------------------------
 * upload4 .user.ini 上传 getshell（PHP-FPM 的目录级配置：让 .jpg 被 prepend 成 PHP）
 * nginx + php-fpm 下 .htaccess 不生效，等价考点是 .user.ini（auto_prepend_file）
 * 教学靶场 · 仅用于授权学习环境
 * ------------------------------------------------------------------ */

$META = json_decode(file_get_contents(__DIR__ . '/meta.json'), true);

$ALLOWED = ['jpg', 'jpeg', 'png', 'gif', 'ini'];   // 白名单……却放进了 .ini（.user.ini 能改 PHP 配置！）

$msg = ''; $err = '';
if (isset($_FILES['avatar'])) {
    $orig = basename((string)$_FILES['avatar']['name']);
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if (!in_array($ext, $ALLOWED, true)) {
        $err = '后缀 .' . htmlspecialchars($ext ?: '(无)') . ' 不在白名单（jpg/jpeg/png/gif/ini）';
    } elseif (move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . '/uploads/' . $orig)) {
        $msg = "上传成功：uploads/" . htmlspecialchars($orig);
    } else {
        $err = '上传失败（文件为空或目录不可写？）';
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
  <p>吸取了前三题的教训，这次上传做得很“正规”：<b>服务端后缀白名单</b>，只允许 <code>jpg/jpeg/png/gif</code>。</p>
  <p>但运维为了“每个目录独立调 PHP 参数”，允许上传 <code>.ini</code>——于是白名单里混进了一个可怕的东西：<code>.user.ini</code> 是 <b>PHP 对每个目录生效的配置文件</b>。</p>
  <p>本题环境是 <b>nginx + php-fpm</b>。上传目录里自带一个 <code>uploads/index.php</code> 相册入口页。flag 在本题目录的 <code>flag.php</code> 里（PHP 注释里，直接访问什么都看不到）。</p>
</div>

<div class="card">
  <h2>知识点充电站</h2>
  <p><b>.user.ini 是什么</b>：PHP 的<b>目录级配置文件</b>（CGI/FPM 模式生效）。PHP 每次执行某目录下的脚本前，会先加载该目录的 <code>.user.ini</code>，其中可以设置 <code>PHP_INI_PERDIR</code> 级别的指令——比如 <code>auto_prepend_file</code>（每个请求先自动包含一个文件）。</p>
  <pre># 恶意 .user.ini：让目录下所有 PHP 请求先“包含”我的图片
auto_prepend_file=shell.jpg</pre>
  <p>攻击两步走：</p>
  <pre>1. 上传 .user.ini（内容 auto_prepend_file=shell.jpg）
2. 上传 shell.jpg（内容是 PHP 代码）→ 访问同目录任意 .php → 图片被当 PHP 先执行</pre>
  <p><b>孪生兄弟 .htaccess</b>：Apache 的目录级配置（<code>AllowOverride All</code> 时生效），能直接把 <code>.jpg</code> 交给 PHP 解析（<code>AddType application/x-httpd-php .jpg</code>）或同样玩 auto_prepend_file；nginx 没有等价机制，所以 nginx+php-fpm 栈上的对应考点就是本题的 <code>.user.ini</code>。两者的共同教训：<b>白名单里任何“配置文件”都是致命的</b>。</p>
  <p>getshell 后读 flag 的姿势（flag.php 内容形如 <code>&lt;?php //flag{...} ?&gt;</code>，直接访问输出空白）：</p>
  <pre>shell.jpg 内容：&lt;?php echo file_get_contents(__DIR__.'/../flag.php'); ?&gt;
访问：/upload4/uploads/index.php      # prepend 执行 → 打印 flag.php 源码</pre>
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
