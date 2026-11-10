<?php
declare(strict_types=1);
require __DIR__ . '/handler.php';

/** 模拟受害者浏览器(emma):依次处理攻击页中的
 *  1) 表单(含 enctype=text/plain → 浏览器报文为 name=value\r\n,可拼出 JSON)
 *  2) script src(带 Origin 的跨域读取;真实浏览器等价于 fetch(url, {credentials:'include'}),能否读到取决于 CORS 响应头) */
$id = (int)($_GET['attack'] ?? 0);
$st = db()->prepare('SELECT * FROM attacks WHERE id = ?');
$st->execute([$id]);
$a = $st->fetch();
if (!$a) { http_response_code(404); exit('攻击页不存在'); }

$victim = user_by_name('emma');
$base = base_dir();
$pageUrl = ($base === '' ? '' : $base) . '/view.php?id=' . $id;
$origin = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($base === '' ? '' : $base);
$html = (string)$a['html'];
$log = ["受害者访问: $pageUrl (Origin: $origin)"];

// 1) 表单
if (preg_match_all('#<form\b([^>]*)>(.*?)</form>#is', $html, $fs, PREG_SET_ORDER)) {
    foreach ($fs as $f) {
        $tag = $f[1];
        $inner = $f[2];
        preg_match('#action\s*=\s*(["\'])(.*?)\1#i', $tag, $am);
        preg_match('#method\s*=\s*(["\'])(.*?)\1#i', $tag, $mm);
        preg_match('#enctype\s*=\s*(["\'])(.*?)\1#i', $tag, $em);
        $action = $am[2] ?? '';
        $method = strtoupper($mm[2] ?? 'GET');
        $enctype = strtolower($em[2] ?? 'application/x-www-form-urlencoded');
        $url = resolve_url(htmlspecialchars_decode($action, ENT_QUOTES), $base);
        if ($url === null) { $log[] = '[form] 跳过外链 action'; continue; }
        [$path, $q] = array_pad(explode('?', $url, 2), 2, '');
        if (basename($path) !== 'api.php') { $log[] = '[form] action 非 api.php,略'; continue; }
        $fields = [];
        if (preg_match_all('#<input\b[^>]*>#i', $inner, $ins, PREG_SET_ORDER)) {
            foreach ($ins as $in) {
                if (!preg_match('#name\s*=\s*(["\'])(.*?)\1#i', $in[0], $nm)) { continue; }
                preg_match('#value\s*=\s*(["\'])(.*?)\1#i', $in[0], $vm);
                $fields[] = [htmlspecialchars_decode($nm[2], ENT_QUOTES), htmlspecialchars_decode($vm[2] ?? '', ENT_QUOTES)];
            }
        }
        if ($method !== 'POST') { $log[] = '[form] 非_POST,略'; continue; }
        if ($enctype === 'text/plain') {
            // 浏览器对 text/plain 表单的报文格式:每个字段一行 "name=value\r\n"
            $raw = implode("\r\n", array_map(fn($fv) => $fv[0] . '=' . $fv[1], $fields)) . "\r\n";
        } else {
            $raw = http_build_query(array_combine(array_column($fields, 0), array_column($fields, 1)) ?: []);
        }
        [$sc, $body] = csrf3_api($victim, 'POST', $raw);
        $log[] = "[form POST $url | Content-Type: $enctype] body=" . str_replace("\r\n", '\\r\\n', $raw) . " -> HTTP $sc $body";
    }
} else {
    $log[] = '(没有表单)';
}

// 2) script src —— 跨域读取
$read = null;
if (preg_match_all('#<script\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']#i', $html, $ss, PREG_SET_ORDER)) {
    foreach ($ss as $s) {
        $url = resolve_url(htmlspecialchars_decode($s[1], ENT_QUOTES), $base);
        if ($url === null) { $log[] = '[script] 跳过外链'; continue; }
        if (basename(explode('?', $url)[0]) !== 'profile.php') { $log[] = '[script] src 非 profile.php,略'; continue; }
        [$sc, $body, $cors] = csrf3_profile(user_by_name('emma'), $origin, false);   // 简单请求:无自定义头;重新读库(上一步可能已降防护)
        if ($sc === 200) {
            $log[] = "[script GET $url | Origin: $origin] -> HTTP 200;响应允许跨域读取($cors)";
            $log[] = "[script] 跨域读到响应体: $body";
            $read = $body;
        } else {
            $log[] = "[script GET $url | Origin: $origin] -> HTTP $sc $body";
        }
    }
} else {
    $log[] = '(没有 script src)';
}

page_head('CSRF 3/3 · hard', '模拟受害者浏览器', '★★★☆☆', '', [], []);
?>
<div class="card">
  <h2>emma 的浏览器访问了攻击页 #<?php echo $id; ?></h2>
  <div class="log"><?php echo h(implode("\n", $log)); ?></div>
  <h2 style="margin-top:14px">结果</h2>
  <?php if ($read && strpos($read, 'flag{') !== false): ?>
    <div class="msg ok">JSON CSRF 降防护 + CORS 反射读取成功！emma 的私密笔记已跨域读出。</div>
  <?php else: ?>
    <div class="msg err">还没读到 flag。检查:①表单是否 enctype="text/plain" 且 name/value 拼出了合法 JSON;②是否有 script src 指向 profile.php;③两步顺序(先降防护后读取)。</div>
  <?php endif; ?>
  <p><a class="btn" href="index.php">返回状态面板</a> <a class="muted" href="reset.php">重置环境</a></p>
</div>
<?php page_foot('csrf3');
