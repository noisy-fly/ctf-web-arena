<?php
/**
 * common.php — 教学式靶场页面渲染助手
 * 每个题目目录各自携带一份，保证单个目录即可独立部署/搬移。
 */

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function ok_box($html)
{
    return '<div class="result ok"><b>🎉 校验通过</b><br>' . $html . '</div>';
}

function bad_box($html)
{
    return '<div class="result bad"><b>❌ 未通过</b><br>' . $html . '</div>';
}

function render_page(array $opts)
{
    $o = array_merge(array(
        'id' => '', 'cat' => '', 'catname' => '', 'title' => '',
        'difficulty' => 'easy', 'desc' => '', 'knowledge' => array(),
        'hints' => array(), 'body' => '', 'src' => '',
        'prev' => null, 'next' => null,
    ), $opts);

    $diffs = array(
        'easy'   => array('入门', '#3fb950'),
        'medium' => array('进阶', '#d29922'),
        'hard'   => array('高阶', '#f85149'),
    );
    if (isset($diffs[$o['difficulty']])) {
        $dname = $diffs[$o['difficulty']][0];
        $dcolor = $diffs[$o['difficulty']][1];
    } else {
        $dname = '?';
        $dcolor = '#999';
    }

    $chips = '';
    foreach ($o['knowledge'] as $k) {
        $chips .= '<span class="chip">' . h($k) . '</span>';
    }

    $hints = '';
    $n = 0;
    foreach ($o['hints'] as $hint) {
        $n++;
        $hints .= '<details class="hint"><summary>💡 提示 ' . $n . '</summary><div class="hint-body">' . h($hint) . '</div></details>';
    }

    $srcHtml = '';
    if ($o['src'] !== '') {
        ob_start();
        @highlight_string($o['src']);
        $srcHtml = '<div class="srcbox">' . trim((string)ob_get_clean()) . '</div>';
    }

    $nav = '<div class="nav">';
    $nav .= $o['prev'] ? ('<a href="' . h($o['prev'][0]) . '">← 上一关：' . h($o['prev'][1]) . '</a>') : '<span></span>';
    $nav .= $o['next'] ? ('<a href="' . h($o['next'][0]) . '">下一关：' . h($o['next'][1]) . ' →</a>') : '<span></span>';
    $nav .= '</div>';

    $id = h($o['id']);
    $catname = h($o['catname']);
    $title = h($o['title']);
    $desc = $o['desc'];
    $body = $o['body'];

    echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$id} · {$title}</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { margin:0; font-family:"Segoe UI","Microsoft YaHei",sans-serif; background:#0d1117; color:#e6edf3; line-height:1.65; }
  .wrap { max-width: 900px; margin: 0 auto; padding: 24px 20px 60px; }
  header { border-bottom:1px solid #30363d; padding-bottom:14px; margin-bottom:20px; }
  .cat { color:#58a6ff; font-size:14px; letter-spacing:1px; }
  h1 { font-size:26px; margin:6px 0 10px; }
  .badges { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .badge { font-size:12px; padding:2px 10px; border-radius:10px; border:1px solid currentColor; }
  h2 { font-size:17px; margin:28px 0 10px; color:#58a6ff; border-left:3px solid #58a6ff; padding-left:10px; }
  .desc { color:#c9d1d9; }
  .desc code, .lab code, .hint-body code { background:#0a0d12; border:1px solid #30363d; border-radius:4px; padding:0 5px; color:#79c0ff; }
  .chips { display:flex; flex-wrap:wrap; gap:8px; }
  .chip { background:#161b22; border:1px solid #30363d; border-radius:14px; padding:2px 12px; font-size:13px; color:#79c0ff; }
  .lab { background:#161b22; border:1px solid #30363d; border-radius:8px; padding:16px; }
  .result { border-radius:8px; padding:12px 14px; margin:10px 0; }
  .result.ok { background:#0f2e17; border:1px solid #238636; }
  .result.bad { background:#3d1418; border:1px solid #8e1c2c; }
  .srcbox { background:#0a0d12; border:1px solid #30363d; border-radius:8px; padding:12px 14px; overflow:auto; }
  .srcbox code, .srcbox span { font-family: Consolas, "Courier New", monospace; font-size:13px; }
  details.hint { background:#161b22; border:1px solid #30363d; border-radius:8px; padding:10px 14px; margin:8px 0; }
  details.hint summary { cursor:pointer; color:#d29922; }
  .hint-body { color:#c9d1d9; margin-top:6px; }
  form.flagform { display:flex; gap:10px; margin-top:10px; flex-wrap:wrap; }
  form.flagform input[type=text] { flex:1; min-width:240px; background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:6px; padding:8px 12px; font-family:Consolas,monospace; }
  form.flagform button { background:#238636; }
  form.labform { display:flex; gap:10px; flex-wrap:wrap; margin-top:10px; }
  form.labform input[type=text] { flex:1; min-width:160px; background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:6px; padding:8px 10px; font-family:Consolas,monospace; }
  form.labform button, .lab button { background:#1f6feb; border:none; color:#fff; border-radius:6px; padding:8px 18px; cursor:pointer; font-size:14px; }
  .lab button { margin:6px 6px 6px 0; }
  .lab button.ghost { background:#30363d; }
  .lab button.green { background:#238636; }
  .lab textarea { width:100%; min-height:90px; background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:6px; padding:8px 10px; font-family:Consolas,monospace; font-size:13px; margin:6px 0; }
  .lab input[type=text] { background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:6px; padding:8px 10px; font-family:Consolas,monospace; }
  pre.out { background:#0a0d12; border:1px dashed #30363d; border-radius:6px; padding:10px; overflow:auto; font-size:13px; white-space:pre-wrap; word-break:break-all; }
  .nav { display:flex; justify-content:space-between; margin-top:40px; border-top:1px solid #30363d; padding-top:14px; font-size:14px; }
  .nav a { color:#58a6ff; text-decoration:none; }
</style>
</head>
<body><div class="wrap">
<header>
  <div class="cat">CTF Web 教学靶场 · {$catname} · {$id}</div>
  <h1>{$title}</h1>
  <div class="badges">
    <span class="badge" style="color:{$dcolor}">难度：{$dname}</span>
    <span class="badge" style="color:#79c0ff">{$catname}</span>
  </div>
</header>

<h2>📖 题目描述</h2>
<div class="desc">{$desc}</div>

<h2>🧪 动手实验室</h2>
<div class="lab">{$body}</div>

<h2>🔍 关键源码</h2>
{$srcHtml}

<h2>🎓 知识点</h2>
<div class="chips">{$chips}</div>

<h2>💡 渐进提示（按顺序展开）</h2>
{$hints}

<h2>🚩 提交 Flag</h2>
<form class="flagform" method="post" action="/api/verify">
  <input type="hidden" name="challenge_id" value="{$id}">
  <input type="text" name="flag" placeholder="flag{{$id}_xxxxxxxx" required>
  <button type="submit">提交 Flag</button>
</form>

{$nav}
</div></body></html>
HTML;
}
