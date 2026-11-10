<?php
declare(strict_types=1);
// miscw2 核心：把"假如没有防护"的原始 HTTP 响应报文逐字节渲染出来。
// PHP 的 header()/setcookie() 会拒绝含 CR/LF 的头值（框架层的补救），真实环境里 CRLF 注入无法经由
// header() 发生；本页用"模拟报文"还原漏洞，并在 ?real=1 提供"真实带防护"的对照响应。
session_start();
require __DIR__ . '/init.php';

$_SESSION['visited'] = true;

$rawName = $_GET['name'] ?? '';
if (is_array($rawName)) { $rawName = ''; }
$name = str_replace("\0", '', mb_substr($rawName, 0, 200));
// 说明：PHP 解析 $_GET 时已完成 URL 解码——name=Bob%0d%0aSet-Cookie:%20role=admin
// 到这里 $name 就是 "Bob\r\nSet-Cookie: role=admin"，%0d%0a 已是真实的回车换行字节。

// ---------- 真实对照模式：?real=1 走真实 PHP 响应（带防护） ----------
if (($_GET['real'] ?? '') === '1') {
    header('Content-Type: text/plain; charset=utf-8');
    if ($name !== '' && preg_match('/^[A-Za-z0-9._-]{1,32}$/D', $name)) {
        setcookie('visitor', $name);            // setcookie() 对值自动 urlencode，且绝不接受换行
        header('Location: index.php?saved=1');  // 真实 302；header() 拒绝含 \r\n 的头值
        exit;
    }
    http_response_code(400);
    echo "400 Bad Request\n";
    echo "name 为空或含白名单(^[A-Za-z0-9._-]{1,32}$)之外的字符，已拒绝。\n";
    echo "顺带一提：就算不做校验，PHP 的 header()/setcookie() 也会拒绝含 CR/LF 的头值——那是框架的补救；输入校验才是第一道防线。\n";
    exit;
}

// ---------- 漏洞模拟模式：手工拼接"无防护"的原始响应 ----------
// ===== 漏洞写法（模拟）：用户输入未过滤，直接拼进响应头 =====
$raw  = "HTTP/1.1 302 Found\r\n";
$raw .= "Set-Cookie: visitor=" . $name . "\r\n";
$raw .= "Location: /?saved=1\r\n";
$raw .= "Content-Length: 0\r\n";
$raw .= "Connection: close\r\n";
$raw .= "\r\n";

// 模拟"浏览器 Cookie jar 只收服务端下发过的 Cookie"：
// 解析这份模拟报文里真正出现的 Set-Cookie 行，记录本会话被"服务端"下发过的 Cookie
$pairs = [];
foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
    if (preg_match('/^Set-Cookie:[ \t]*([^=;\s]+)[ \t]*=[ \t]*([^;\s]*)/i', $line, $m)) {
        $pairs[] = $m[1] . '=' . $m[2];
    }
}
if (!isset($_SESSION['issued']) || !is_array($_SESSION['issued'])) { $_SESSION['issued'] = []; }
foreach ($pairs as $p) {
    if (!in_array($p, $_SESSION['issued'], true)) { $_SESSION['issued'][] = $p; }
}

// 给本页这条"真实响应"加一条真实头，证明真实头永远单行（curl -i 可见）
header('X-Real-Header: generated-by-PHP-header()-always-single-line');

$escaped = str_replace(["\r", "\n"], ['\r', '\n'], $raw);

page_head('杂项 Web 2/2 · medium', '星云留言板 —— setname.php（漏洞模拟）', '★★☆☆☆',
 '本页把“假如服务端用无防护方式手工拼响应”时你将收到的<b>原始 HTTP 报文</b>逐字节渲染出来：\r\n 以转义形式可见，' .
 'HTTP 头正是靠这两个字节分隔的——注入它们就能“折行”伪造任意头。页面底部附真实带防护的对照响应。', [], []);
?>
<div class="card"><h2>假如没有防护：你将收到的原始响应报文（逐字节）</h2>
<p class="muted">由 <code>"Set-Cookie: visitor=" . $name . "\r\n"</code> 直接拼接而成，\r\n 逐字节可见：</p>
<pre><?php echo h($escaped); ?></pre>
<p class="muted">所见即所得视图（把 \r\n 当成真实换行显示）：</p>
<pre><?php echo h($raw); ?></pre>
</div>

<div class="card"><h2>模拟 Cookie Jar（浏览器只保存服务端下发过的 Cookie）</h2>
<?php if ($pairs): ?>
<ul>
<?php foreach ($pairs as $i => $p): ?>
  <li><code><?php echo h($p); ?></code><?php echo $i === 0
      ? ' <span class="muted">← 正常下发</span>'
      : ' <span style="color:#dc2626">← 注入！服务器“亲手”下发了它</span>'; ?></li>
<?php endforeach; ?>
</ul>
<?php else: ?>
<p>这份模拟报文没有产生任何 Set-Cookie。</p>
<?php endif; ?>
<p class="muted">以上记录已写入你的会话。<a href="admin.php">admin.php</a> 只认这份“服务端下发记录”——手改请求里的 Cookie 头没有用。</p>
</div>

<div class="card"><h2>真实对照（本页就是一条真实 PHP 响应）</h2>
<p>你收到的<b>真实</b>响应里没有第二行 Set-Cookie：真实头经由 <code>header()</code> 发出（本页带了一条真实头 <code>X-Real-Header</code>，用 <code>curl -i</code> 本页即可见），PHP 会拒绝含 CR/LF 的头值。对比命令：</p>
<pre>curl -i "http://HOST/miscw2/setname.php?name=Bob&amp;real=1"        <span class="muted"># 真实 302 + 干净的 Set-Cookie</span>
curl -i "http://HOST/miscw2/setname.php?name=Bob%0d%0aSet-Cookie:%20role=admin&amp;real=1"  <span class="muted"># 400 白名单拒绝</span></pre>
<p>
<a class="btn" href="index.php">返回首页</a>
<a class="btn" href="admin.php">前往 admin.php</a>
</p>
</div>
<?php page_foot('miscw2');
