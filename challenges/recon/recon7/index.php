<?php
declare(strict_types=1);
/* ------------------------------------------------------------------
 * recon7 目录扫描：发现后台与备份压缩包（信息收集集大成）
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
  <p>极客书店官网 v1.2.3（<?= htmlspecialchars(APP_VER) ?>）。网站用了新的前端，看起来焕然一新。</p>
  <p>但一台"干净"的服务器在攻击者眼里从来不干净：<b>robots.txt 写了什么？有没有忘记删除的备份压缩包？后台藏在哪？</b></p>
  <p class="sub">信息收集第四课（也是毕业课）：<b>用目录扫描器把网站的"底裤"摸出来。</b></p>
</div>

<div class="card">
  <h2>知识点充电站</h2>
  <p><b>目录扫描（Directory Brute-forcing）</b>：拿一份常见路径字典，对目标逐个请求，用状态码/长度差异发现"没链接出来的页面"。</p>
  <pre>dirsearch -u http://TARGET/recon7/ -e php,zip,tar,gz,bak,txt
gobuster dir -u http://TARGET/recon7/ -w 目录字典.txt -x php,zip
# 手工党：对常见备份名挨个 curl -I 探测</pre>
  <p>高频命中名单（本题环境里真实存在一部分）：</p>
  <pre>/robots.txt        爬虫协议——后台路径经常被"好心"写进去
/www.zip           网站源码打包（历年事故之王：BAOZOUNG 事件）
/backup*.zip .rar .tar.gz .sql  各种备份
/admin /manage*    后台
/test /tmp /debug  临时目录</pre>
  <p>拿到源码包之后：解压 → grep 配置文件（数据库口令、密钥、内部备注）——本题 flag 就藏在备份里的 <code>inc/config.php</code> 注释中。</p>
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
