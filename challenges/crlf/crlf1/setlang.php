<?php
declare(strict_types=1);
// ============================================================
// crlf1 · setlang.php —— 漏洞核心页（PHP 交互式模拟）
// PHP 的 header()/setcookie() 会拒绝含 CR/LF 的头值（框架层对历史漏洞的
// 补救），真实环境里应用层 CRLF 已无法经由 header() 发生。本页把"假如
// 服务端无防护地手工拼响应"时你将收到的【原始 HTTP 响应报文】逐字节渲染
// 出来（响应行+头+体）：lang 原样拼进 Set-Cookie 行，注入 %0d%0a 后报文
// "折行"出现伪造头（如 Set-Cookie: role=admin）。
// ?real=1 走真实 PHP 响应（带防护对照）：合法值 → 真实 302 + 真实
// Set-Cookie；含换行 → 真实 400 拒绝。
// （与 E 组 miscw2 setname.php 同一方案，风格照齐）
// ============================================================
session_start();
require __DIR__ . '/init.php';

$_SESSION['visited'] = true;

$rawLang = $_GET['lang'] ?? '';
if (!is_string($rawLang)) {
    $rawLang = '';
}
$lang = str_replace("\0", '', mb_substr($rawLang, 0, 200));
// 说明：PHP 解析 $_GET 时已完成 URL 解码——lang=guest%0d%0aSet-Cookie:%20role=admin
// 到这里 $lang 就是 "guest\r\nSet-Cookie: role=admin"，%0d%0a 已是真实的回车换行字节。

// ---------- 真实对照模式：?real=1 走真实 PHP 响应（带防护） ----------
$realMode = (($_GET['real'] ?? '') === '1');
if ($realMode) {
    if (!str_contains($lang, "\r") && !str_contains($lang, "\n")) {
        setcookie('lang', $lang);                     // 真实 Set-Cookie（setcookie 对值自动 urlencode，且绝不接受换行）
        $base = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        header('Location: ' . $base . '/index.php?saved=1');   // 真实 302；header() 拒绝含 \r\n 的头值
        exit;
    }
    http_response_code(400);
    header('Content-Type: text/html; charset=utf-8');
    page_top('难度：★★☆☆☆ 基础 | 关卡 ID：<code>crlf1</code> · 运行时：PHP 8.2 · real=1 真实对照（带防护）');
    echo '<section class="card"><h2>真实对照 · 已拒绝</h2>'
        . '<div class="nobox">已拒绝：lang 含换行符（CRLF 已过滤）——这是一条<b>真实的</b> <code>400 Bad Request</code> 响应。</div>'
        . '<p>就算不做校验，PHP 的 <code>header()</code>/<code>setcookie()</code> 也会拒绝含 CR/LF 的头值——那是框架的补救；输入校验才是第一道防线。</p>'
        . '<p><a href="index.php">返回首页</a> ｜ <a href="setlang.php?lang=zh-CN">返回模拟页</a></p></section>';
    page_bottom('crlf1');
    exit;
}

// ---------- 漏洞模拟模式：手工拼接"无防护"的原始响应 ----------
// ===== 漏洞写法（模拟）：用户输入未过滤，直接拼进响应头 =====
$raw  = "HTTP/1.1 302 Found\r\n";
$raw .= "Set-Cookie: lang=" . $lang . "\r\n";
$raw .= "Location: /crlf1/\r\n";
$raw .= "Content-Length: 0\r\n";
$raw .= "Connection: close\r\n";
$raw .= "\r\n";

// 模拟"浏览器 Cookie jar 只收服务端下发过的 Cookie"：
// 解析这份模拟报文里真正出现的 Set-Cookie 行，记入本会话的"服务端下发记录"
// （index.php 的管理员判定只认这份记录——手改请求里的 Cookie 头没有用）。
$pairs = [];
foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
    if (is_string($line) && preg_match('/^Set-Cookie:[ \t]*([^=;\s]+)[ \t]*=[ \t]*([^;\s]*)/i', $line, $m)) {
        $pairs[] = $m[1] . '=' . $m[2];
    }
}
if (!isset($_SESSION['issued']) || !is_array($_SESSION['issued'])) {
    $_SESSION['issued'] = [];
}
foreach ($pairs as $p) {
    if (!in_array($p, $_SESSION['issued'], true) && count($_SESSION['issued']) < 20) {
        $_SESSION['issued'][] = $p;
    }
}

// 给本页这条"真实响应"加一条真实头，证明真实头永远单行（curl -i 可见）
header('X-Real-Header: generated-by-PHP-header()-always-single-line');

$escaped = str_replace(["\r", "\n"], ['\r', '\n'], $raw);

page_top('难度：★★☆☆☆ 基础 | 关卡 ID：<code>crlf1</code> · 运行时：PHP 8.2 · setlang.php（漏洞模拟）');
?>
<div class="card"><h2>假如没有防护：你将收到的原始响应报文（逐字节）</h2>
<p style="color:var(--dim);font-size:13px">由 <code>"Set-Cookie: lang=" . $lang . "\r\n"</code> 直接拼接而成（$lang 已是 URL 解码后的原文）：</p>
<pre><?php echo h($escaped); ?></pre>
<p style="color:var(--dim);font-size:13px">所见即所得视图（把 \r\n 当成真实换行显示）：</p>
<pre><?php echo h($raw); ?></pre>
</div>

<div class="card"><h2>模拟 Cookie Jar（浏览器只保存服务端下发过的 Cookie）</h2>
<?php if ($pairs): ?>
<table>
<tr><th>#</th><th>报文中的 Set-Cookie</th><th>含义</th></tr>
<?php foreach ($pairs as $i => $p): ?>
<tr>
<td><?php echo (int)$i + 1; ?></td>
<td><code><?php echo h($p); ?></code></td>
<td><?php echo $i === 0 ? '正常下发' : '<span style="color:#f87171">← 注入！服务器"亲手"下发了它</span>'; ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<p style="color:var(--dim)">lang 为空——报文里没有 Set-Cookie 行。</p>
<?php endif; ?>
<p style="color:var(--dim);font-size:13px">以上已记入你会话的"服务端下发记录"。<a href="index.php">回到首页</a> 查看身份判定——
若注入出 <code>role=admin</code>，管理员口令即出现。</p>
</div>

<div class="card"><h2>真实对照（?real=1 是一条真实 PHP 响应）</h2>
<p>本页的<b>真实</b>响应头经由 <code>header()</code> 发出（本页带了一条真实头 <code>X-Real-Header</code>，<code>curl -i</code> 本页即可见），
PHP 会拒绝含 CR/LF 的头值——真实世界里"应用内 CRLF"已被框架堵死，漏洞存活于自研网关/代理/协议拼接处。对比命令：</p>
<pre>curl -i "http://HOST/crlf1/setlang.php?lang=zh-CN&amp;real=1"          <span style="color:var(--dim)"># 真实 302 + 干净的 Set-Cookie</span>
curl -i "http://HOST/crlf1/setlang.php?lang=guest%0d%0aSet-Cookie:%20role=admin&amp;real=1"  <span style="color:var(--dim)"># 400：已拒绝（CRLF 已过滤）</span></pre>
<p><a href="index.php">返回首页</a></p>
</div>
<?php page_bottom('crlf1');
