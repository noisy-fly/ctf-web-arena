<?php
declare(strict_types=1);
const COOKIE_NAME = 'csrf2_sess';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }
    $f = __DIR__ . '/app.db';
    $fresh = !file_exists($f);
    $pdo = new PDO('sqlite:' . $f, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout=5000');
    $pdo->exec('PRAGMA journal_mode=WAL');
    if ($fresh) {
        foreach (array_filter(array_map('trim', explode(';', (string)file_get_contents(__DIR__ . '/init.sql')))) as $st) {
            if ($st !== '') { $pdo->exec($st); }
        }
    }
    return $pdo;
}

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** 当前访问者(攻击者账号 hacker):无 cookie 则发放新 token 并登记 */
function current_user(): array {
    $db = db();
    $tok = $_COOKIE[COOKIE_NAME] ?? '';
    if ($tok !== '') {
        $st = $db->prepare('SELECT * FROM users WHERE token = ?');
        $st->execute([$tok]);
        $u = $st->fetch();
        if ($u) { return $u; }
    }
    // 教学环境共用 hacker 账号:每个浏览器各自持有最新 token
    $tok = 'atk_' . bin2hex(random_bytes(16));
    $db->prepare("UPDATE users SET token = ? WHERE username = 'hacker'")->execute([$tok]);
    if ($db->query("SELECT COUNT(*) c FROM users WHERE username = 'hacker'")->fetch()['c'] == 0) {
        $db->prepare("INSERT INTO users(username, vip, token) VALUES('hacker', 0, ?)")->execute([$tok]);
    }
    setcookie(COOKIE_NAME, $tok, ['path' => '', 'httponly' => false, 'samesite' => 'Lax']);
    $st = $db->prepare('SELECT * FROM users WHERE token = ?');
    $st->execute([$tok]);
    return $st->fetch();
}

function user_by_name(string $name): ?array {
    $st = db()->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$name]);
    $r = $st->fetch();
    return $r ?: null;
}

/** 相对 URL -> 站内绝对路径;外链返回 null */
function resolve_url(string $rel, string $baseDir): ?string {
    if (preg_match('#^https?://#i', $rel) || preg_match('#^(javascript|data):#i', $rel)) { return null; }
    if ($rel === '') { return $baseDir . '/index.php'; }
    if ($rel[0] === '/') { return $baseDir . $rel; }
    return $baseDir . '/' . ltrim($rel, './');
}

function base_dir(): string {
    $d = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    return rtrim($d, '/');
}

// ---------- 教学页面骨架 ----------
function page_head(string $tag, string $title, string $stars, string $desc, array $know, array $hints): void {
    $css = <<<'CSS'
<style>
:root{--pri:#db2777;--bg:#fdf7fa;--ink:#1f2937;--mut:#6b7280;--ok:#059669;--warn:#d97706;--err:#dc2626}
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI","Microsoft YaHei",sans-serif;background:var(--bg);color:var(--ink);line-height:1.65}
.wrap{max-width:900px;margin:0 auto;padding:24px 16px 64px}
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
textarea,input{padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;font-family:Consolas,monospace}
.msg{padding:8px 12px;border-radius:6px;margin:8px 0}.err{background:#fef2f2;color:var(--err)}.ok{background:#ecfdf5;color:var(--ok)}
.flagbox{border:2px dashed var(--warn)}.muted{color:var(--mut);font-size:13px}
.demo{border:2px solid #fca5a5;border-radius:8px;background:#fff;padding:10px;min-height:40px}
.log{background:#0f172a;color:#a5f3fc;border-radius:8px;padding:12px;font-size:13px;white-space:pre-wrap}
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
    echo '<p class="muted">CTF Web 教学靶场 · CSRF/CORS 篇</p></div></body></html>';
}
