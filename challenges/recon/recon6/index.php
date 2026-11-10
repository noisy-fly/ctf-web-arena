<?php
declare(strict_types=1);
/* ------------------------------------------------------------------
 * recon6 vim 交换文件泄露（.swp：SSH 断线留下的源码草稿）
 * 教学靶场 · 仅用于授权学习环境
 * ------------------------------------------------------------------ */
$META = json_decode(file_get_contents(__DIR__ . '/meta.json'), true);
require __DIR__ . '/inc/config.php';   // 线上配置（已清理敏感信息）
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
input[type=text]{background:#0b0f14;border:1px solid var(--bd);color:var(--fg);border-radius:6px;padding:8px 12px;font-family:var(--mono);font-size:14px;width:70%}
button{background:#21262d;border:1px solid var(--bd);color:var(--fg);border-radius:6px;padding:8px 18px;cursor:pointer;font-size:14px}
button:hover{border-color:var(--acc);color:var(--acc)}
#flagResult{margin-top:10px;font-size:14px;min-height:22px}
.ok{color:var(--ok)} .bad{color:var(--err)}
footer{border-top:1px solid var(--bd);margin-top:30px;padding-top:16px;color:var(--dim);font-size:13px;display:flex;justify-content:space-between}
footer a{color:var(--dim);text-decoration:none} footer a:hover{color:var(--acc)}
</style>
</head>
<body>
<header><div class="wrap">
  <div><span class="badge"><?= htmlspecialchars($META['category']) ?></span><span class="badge diff-<?= htmlspecialchars($META['difficulty']) ?>">难度: <?= htmlspecialchars($META['difficulty']) ?></span></div>
  <h1>极客书店官网 <span style="color:var(--dim);font-size:14px;font-weight:400">recon4 · <?= htmlspecialchars($META['title']) ?></span></h1>
  <div class="sub">CTF Web 全题型教学式靶场 · 本题仅用于授权安全学习</div>
</div></header>

<div class="wrap">

<div class="card">
  <h2>站点公告</h2>
  <p>极客书店官网 v0.9（<?= htmlspecialchars(APP_VER) ?>）· 昨晚 dev-chen 紧急修了一个首页 bug。</p>
  <p>他的习惯是用 vim 在服务器上直接改代码：“快速迭代”。昨晚改到一半 SSH 掉线了——vim 猝不及防断开时会留下什么？</p>
  <p class="sub">信息收集第三课：<b>编辑器与开发过程的"中间产物"，也是资产。</b></p>
</div>

<div class="card">
  <h2>知识点充电站</h2>
  <p><b>vim 交换文件（swap）</b>：编辑文件时 vim 会在同目录生成 <code>.文件名.swp</code>（如 <code>.index.php.swp</code>），周期性写入未保存的编辑内容。异常退出（SSH 断线、kill -9、崩溃）时它<b>残留在磁盘上</b>，其中包含：</p>
  <pre>b0VIM 版本头          vim 9.1
原文件路径           /var/www/geekshop/index.php
用户名 / 主机名 / pid dev-chen @ web-prod-01
【编辑中的明文内容】  ← 未保存的草稿，可能包含还没发布的代码！</pre>
  <p>利用姿势：</p>
  <pre>curl http://TARGET/recon6/.index.php.swp -o d.swp
strings d.swp                 # 直接看到源码草稿
vim -r d.swp                  # 或用 vim 恢复完整文件</pre>
  <p>同一家族：vim 的 <code>.swo/.swn</code>（多实例）、<code>file~</code> 备份、Emacs <code>file~</code>/<code>#file#</code> 锁文件、VSCode/JetBrains 的本地历史——编辑器中间产物=半个源码库。</p>
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
  <div style="margin:8px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="text" id="flagInput" placeholder="flag{recon4_xxxxxxxx}">
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
