<?php
declare(strict_types=1);

// ---------- Base64URL 与 JWT 基础件 ----------
function b64url_encode(string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}
function b64url_decode(string $s): string {
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4;
    return base64_decode($pad ? $s . str_repeat('=', 4 - $pad) : $s);
}
/** @return array{0:array,1:array,2:string}|null [header, payload, signature] */
function jwt_parse(string $t): ?array {
    $parts = explode('.', $t);
    if (count($parts) < 2) { return null; }
    $h = json_decode(b64url_decode($parts[0]), true);
    $p = json_decode(b64url_decode($parts[1]), true);
    if (!is_array($h) || !is_array($p)) { return null; }
    return [$h, $p, $parts[2] ?? ''];
}
function jwt_hs256_sig(string $h64, string $p64, string $key): string {
    return b64url_encode(hash_hmac('sha256', "$h64.$p64", $key, true));
}
function jwt_make_hs256(array $payload, string $key): string {
    $h64 = b64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $p64 = b64url_encode(json_encode($payload));
    return "$h64.$p64." . jwt_hs256_sig($h64, $p64, $key);
}
function jwt_make_rs256(array $payload, string $privPem): string {
    $h64 = b64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $p64 = b64url_encode(json_encode($payload));
    openssl_sign("$h64.$p64", $sig, $privPem, OPENSSL_ALGO_SHA256);
    return "$h64.$p64." . b64url_encode($sig);
}

// ---------- 教学页面公共骨架（与 logic 系列同款样式） ----------
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function page_head(string $tag, string $title, string $stars, string $desc, array $know, array $hints): void {
    $css = <<<'CSS'
<style>
:root{--pri:#7c3aed;--bg:#f5f7fb;--ink:#1f2937;--mut:#6b7280;--ok:#059669;--warn:#d97706;--err:#dc2626}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--ink);line-height:1.65}
.wrap{max-width:880px;margin:0 auto;padding:24px 16px 64px}
header{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;margin-bottom:6px}
.tag{background:var(--pri);color:#fff;border-radius:6px;padding:2px 10px;font-size:13px}
h1{font-size:22px;margin:0}.stars{color:#f59e0b;font-size:14px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px 20px;margin:14px 0}
.card h2{font-size:16px;margin:0 0 8px;border-left:4px solid var(--pri);padding-left:8px}
details{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:8px 14px;margin:8px 0}
summary{cursor:pointer;font-weight:600;color:var(--mut)}
code,pre{font-family:Consolas,Menlo,monospace;font-size:13px;background:#f1f5f9;border-radius:4px}
code{padding:1px 5px}pre{padding:10px 12px;overflow:auto}
table{border-collapse:collapse;width:100%}th,td{border:1px solid #e5e7eb;padding:6px 10px;font-size:14px;text-align:left}th{background:#f8fafc}
.btn{display:inline-block;background:var(--pri);color:#fff;border:0;border-radius:6px;padding:7px 16px;font-size:14px;cursor:pointer;text-decoration:none}
input,select{padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px}
.msg{padding:8px 12px;border-radius:6px;margin:8px 0}.err{background:#fef2f2;color:var(--err)}.ok{background:#ecfdf5;color:var(--ok)}
.flagbox{border:2px dashed var(--warn)}.muted{color:var(--mut);font-size:13px}
</style>
CSS;
    echo '<!doctype html><html lang="zh"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($tag . ' ' . $title) . "</title>{$css}</head><body><div class=\"wrap\">";
    echo '<header><span class="tag">' . h($tag) . '</span><h1>' . h($title) . '</h1><span class="stars">' . $stars . '</span></header>';
    echo '<div class="card"><h2>场景</h2><p>' . $desc . '</p></div>';
    echo '<div class="card"><h2>知识点</h2><ul>';
    foreach ($know as $k) { echo '<li>' . $k . '</li>'; }
    echo '</ul></div>';
    echo '<div class="card"><h2>渐进提示（点开前先自己想想）</h2>';
    $i = 1;
    foreach ($hints as $ht) { echo '<details><summary>提示 ' . $i++ . '</summary><p>' . $ht . '</p></details>'; }
    echo '</div>';
}

function page_foot(string $id): void {
    echo '<div class="card flagbox"><h2>拿到 Flag 了吗？</h2>';
    echo '<form method="post" action="/api/verify" target="_blank">';
    echo '<input type="hidden" name="challenge_id" value="' . h($id) . '">';
    echo '<input name="flag" placeholder="flag{' . h($id) . '_xxxxxxxx}" size="34" required> ';
    echo '<button class="btn" type="submit">提交 Flag</button></form>';
    echo '<p class="muted">提交接口由靶场门户提供（POST /api/verify，字段 challenge_id、flag）。解出后建议阅读 writeup.md 复盘修复方案。</p></div>';
    echo '<p class="muted">CTF Web 教学靶场 · JWT 篇</p></div></body></html>';
}
