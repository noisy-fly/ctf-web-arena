<?php
declare(strict_types=1);
require __DIR__ . '/handler.php';

/** 模拟受害者浏览器:以受害者身份依次发起攻击页中指向本站的请求(等价于浏览器自动带上 Cookie 的跨站请求)。
 *  img/iframe src => 自动 GET;表单 => 提交其声明值(真实攻击中由 JS 自动提交)。 */
$id = (int)($_GET['attack'] ?? 0);
$st = db()->prepare('SELECT * FROM attacks WHERE id = ?');
$st->execute([$id]);
$a = $st->fetch();
if (!$a) { http_response_code(404); exit('攻击页不存在'); }

$victim = user_by_name('cathy');
$base = base_dir();
$html = (string)$a['html'];
$log = [];

// 1) img / iframe 的 src
if (preg_match_all('#<(img|iframe)\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']#i', $html, $ms, PREG_SET_ORDER)) {
    foreach ($ms as $m) {
        $url = resolve_url(htmlspecialchars_decode($m[2], ENT_QUOTES), $base);
        if ($url === null) { $log[] = "[{$m[1]}] 跳过外链: {$m[2]}"; continue; }
        [$path, $q] = array_pad(explode('?', $url, 2), 2, '');
        if (basename($path) === 'api.php') {
            $params = [];
            parse_str($q, $params);
            [$sc, $body] = csrf1_api($victim, 'GET', $params);   // 受害者 Cookie 语义
            $log[] = "[{$m[1]}] GET $url -> HTTP $sc " . trim(strip_tags($body));
        } else {
            $log[] = "[{$m[1]}] GET $url -> (非接口,略)";
        }
    }
}

// 2) 表单
if (preg_match_all('#<form\b([^>]*)>(.*?)</form>#is', $html, $fs, PREG_SET_ORDER)) {
    foreach ($fs as $f) {
        $tag = $f[1];
        $inner = $f[2];
        preg_match('#action\s*=\s*(["\'])(.*?)\1#i', $tag, $am);
        preg_match('#method\s*=\s*(["\'])(.*?)\1#i', $tag, $mm);
        $action = $am[2] ?? '';
        $method = strtoupper($mm[2] ?? 'GET');
        $url = resolve_url(htmlspecialchars_decode($action, ENT_QUOTES), $base);
        if ($url === null) { $log[] = '[form] 跳过外链 action'; continue; }
        $params = [];
        if (preg_match_all('#<input\b[^>]*>#i', $inner, $ins, PREG_SET_ORDER)) {
            foreach ($ins as $in) {
                if (!preg_match('#name\s*=\s*(["\'])(.*?)\1#i', $in[0], $nm)) { continue; }
                preg_match('#value\s*=\s*(["\'])(.*?)\1#i', $in[0], $vm);
                $params[$nm[2]] = htmlspecialchars_decode($vm[2] ?? '', ENT_QUOTES);
            }
        }
        [$path, $q] = array_pad(explode('?', $url, 2), 2, '');
        if (basename($path) !== 'api.php') { $log[] = "[form] action 非 api.php,略"; continue; }
        if ($method === 'GET') {
            $old = [];
            parse_str($q, $old);
            $final = $path . '?' . http_build_query(array_merge($old, $params));
            [$sc, $body] = csrf1_api($victim, 'GET', array_merge($old, $params));
            $log[] = "[form] GET $final -> HTTP $sc " . trim(strip_tags($body));
        } else {
            [$sc, $body] = csrf1_api($victim, 'POST', []);
            $log[] = "[form] POST $url (" . http_build_query($params) . ") -> HTTP $sc " . trim(strip_tags($body));
        }
    }
}

$cathy = user_by_name('cathy');
page_head('CSRF 1/3 · easy', '模拟受害者浏览器', '★☆☆☆☆', '', [], []);
?>
<div class="card">
  <h2>cathy 的浏览器访问了攻击页 #<?php echo $id; ?></h2>
  <div class="log"><?php echo h(implode("\n", $log) ?: '(攻击页中没有发现指向本站接口的 img/iframe/表单请求)'); ?></div>
  <h2 style="margin-top:14px">结果</h2>
  <?php if ($cathy['subscribed']): ?>
    <div class="msg ok">cathy 的订阅状态已变为 <b>已订阅</b> —— CSRF 成功！flag：flag{csrf1_40854ad6}</div>
  <?php else: ?>
    <div class="msg err">cathy 仍未订阅。检查攻击页里的请求是否真的指向了订阅接口（api.php?do=subscribe…）。</div>
  <?php endif; ?>
  <p><a class="btn" href="index.php">返回状态面板</a> <a class="muted" href="reset.php">重置环境</a></p>
</div>
<?php page_foot('csrf1');
