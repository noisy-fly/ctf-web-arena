<?php
declare(strict_types=1);
// ============================================================
// crlf1 公共库：flag 常量、HTML 转义、页面骨架
// 风格与 F 组其他 PHP 题（cors/redirect/sess）一致：深色主题。
// ============================================================

const CRLF1_FLAG = 'flag{crlf1_f6199eaa}';

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function crlf1_css(): string
{
    return <<<'CSS'
<style>
:root{--bg:#0f172a;--card:#1e293b;--ink:#e2e8f0;--dim:#94a3b8;--acc:#38bdf8;--ok:#4ade80;--warn:#fbbf24;--bad:#f87171}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);line-height:1.75}
.wrap{max-width:920px;margin:0 auto;padding:24px 16px 60px}
.badge{display:inline-block;background:#34d399;color:#022c22;font-weight:700;font-size:12px;padding:2px 10px;border-radius:999px;letter-spacing:1px}
h1{font-size:26px;margin:10px 0 4px}.meta{color:var(--dim);font-size:13px;margin-bottom:6px}
.card{background:var(--card);border:1px solid #334155;border-radius:12px;padding:18px 20px;margin:16px 0}
.card h2{margin:0 0 10px;font-size:17px;color:var(--acc);border-left:3px solid var(--acc);padding-left:8px}
pre,code{font-family:Consolas,Menlo,"Courier New",monospace}
pre{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
code{background:#0b1220;border-radius:4px;padding:1px 6px;font-size:13px}
details{background:#0b1220;border:1px solid #27364d;border-radius:8px;padding:10px 14px;margin:8px 0}
summary{cursor:pointer;color:var(--warn);font-weight:600}
.okbox{background:#052e16;border:1px solid #14532d;color:#bbf7d0;border-radius:8px;padding:14px 16px;margin-top:12px}
.nobox{background:#0b1220;border:1px solid #27364d;color:var(--dim);border-radius:8px;padding:14px 16px;margin-top:12px}
table{border-collapse:collapse;width:100%;font-size:14px}td,th{border:1px solid #334155;padding:6px 10px;text-align:left}th{color:var(--acc)}
a{color:var(--acc)}footer{color:var(--dim);font-size:12px;text-align:center;margin-top:30px}
.flagrow{display:flex;gap:8px;flex-wrap:wrap}#flagResult{margin-top:8px;font-size:14px;color:var(--warn);word-break:break-all}
button{padding:8px 18px;border-radius:8px;border:0;background:var(--acc);color:#04121f;font-weight:700;cursor:pointer}
input{padding:8px 10px;border-radius:8px;border:1px solid #475569;background:#0b1220;color:var(--ink)}
.nav{display:flex;justify-content:space-between;margin-top:22px}.nav a{color:var(--acc);text-decoration:none}
</style>
CSS;
}

function page_top(string $metaLine): void
{
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>crlf1 · 响应头注入 | CTF 教学靶场</title>' . crlf1_css() . '</head><body><div class="wrap">'
        . '<header><span class="badge">CRLF · 第 1 关</span>'
        . '<h1>星语小铺 · 语言偏好设置</h1>'
        . '<div class="meta">' . $metaLine . '</div></header>';
}

function page_bottom(string $id): void
{
    echo '<section class="card"><h2>提交 Flag</h2>'
        . '<form id="flagForm" class="flagrow" onsubmit="return false">'
        . '<input type="text" id="flagInput" placeholder="flag{...}" style="flex:1">'
        . '<button onclick="submitFlag()">提交</button></form><div id="flagResult"></div></section>'
        . '<nav class="nav"><a href="../cors2">← 上一关：cors2</a><a href="../crlf2">下一关：crlf2 · memcached 注入 →</a></nav>'
        . '<footer>CTF Web 教学式靶场 · F 组 · 仅用于安全学习</footer></div>'
        . '<script>' . "\n"
        . 'function submitFlag(){' . "\n"
        . '  var v=document.getElementById(\'flagInput\').value.trim();' . "\n"
        . '  var el=document.getElementById(\'flagResult\');' . "\n"
        . '  if(!v){el.textContent=\'请输入 flag\';return}' . "\n"
        . '  el.textContent=\'提交中...\';' . "\n"
        . '  fetch(\'/api/verify\',{method:\'POST\',headers:{\'Content-Type\':\'application/x-www-form-urlencoded\'},body:\'challenge_id=' . h($id) . '&flag=\'+encodeURIComponent(v)})' . "\n"
        . '   .then(function(r){return r.text().then(function(t){' . "\n"
        . '      var msg=\'HTTP \'+r.status+\': \'+t;' . "\n"
        . '      try{var j=JSON.parse(t);' . "\n"
        . '        if(typeof j.correct===\'boolean\')msg=j.correct?\'✅ 回答正确！\':\'❌ flag 不对，再试试\';' . "\n"
        . '        else if(j.message!==undefined)msg=j.message;' . "\n"
        . '      }catch(e){}' . "\n"
        . '      el.textContent=msg;})})' . "\n"
        . '   .catch(function(e){el.textContent=\'提交失败(\'+e.message+\')：请从靶场门户页提交\'});' . "\n"
        . '}' . "\n"
        . '</script></body></html>';
}
