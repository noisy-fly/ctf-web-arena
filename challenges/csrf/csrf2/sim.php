<?php
declare(strict_types=1);
require __DIR__ . '/handler.php';

/** 模拟受害者浏览器:dave 访问攻击页,页内表单以 dave 身份提交,Referer = 攻击页 URL */
$name = (string)($_GET['name'] ?? '');
$st = db()->prepare('SELECT * FROM attacks WHERE name = ?');
$st->execute([$name]);
$a = $st->fetch();
if (!$a) { http_response_code(404); exit('攻击页不存在'); }

$victim = user_by_name('dave');
$base = base_dir();
$pageUrl = ($base === '' ? '' : $base) . '/view.php?name=' . $name;
$referer = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $pageUrl;

$html = (string)$a['html'];
$log = ["受害者访问: $referer"];

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
        if (preg_match_all('#<input\b[^>]*>|<select\b[^>]*>.*?</select>|<textarea\b[^>]*>[^<]*</textarea>#is', $inner, $ins, PREG_SET_ORDER)) {
            foreach ($ins as $in) {
                if (!preg_match('#name\s*=\s*(["\'])(.*?)\1#i', $in[0], $nm)) { continue; }
                preg_match('#value\s*=\s*(["\'])(.*?)\1#i', $in[0], $vm);
                $params[$nm[2]] = htmlspecialchars_decode($vm[2] ?? '', ENT_QUOTES);
            }
        }
        [$path, $q] = array_pad(explode('?', $url, 2), 2, '');
        if (basename($path) !== 'api.php') { $log[] = '[form] action 非 api.php,略'; continue; }
        $get = [];
        parse_str($q, $get);
        if ($method === 'GET') {
            $old = [];
            parse_str($q, $old);
            [$sc, $body] = csrf2_api($victim, 'GET', array_merge($old, $params), [], $referer);
            $log[] = '[form GET] ' . $url . ' -> HTTP ' . $sc . ' ' . trim($body);
        } else {
            [$sc, $body] = csrf2_api($victim, 'POST', $get, $params, $referer);
            $log[] = '[form POST] ' . $url . ' (' . http_build_query($params) . ") Referer=$referer -> HTTP $sc " . trim($body);
        }
    }
} else {
    $log[] = '(攻击页中没有表单)';
}

$dave = user_by_name('dave');
$me = current_user();
page_head('CSRF 2/3 · medium', '模拟受害者浏览器', '★★☆☆☆', '', [], []);
?>
<div class="card">
  <h2>dave 的浏览器访问了攻击页 “<?php echo h($name); ?>”</h2>
  <div class="log"><?php echo h(implode("\n", $log)); ?></div>
  <h2 style="margin-top:14px">结果</h2>
  <?php if ($me['vip']): ?>
    <div class="msg ok">VIP 已转让给 hacker —— CSRF + Referer 绕过成功！flag：flag{csrf2_3bb80cfd}</div>
  <?php else: ?>
    <div class="msg err">VIP 还在 dave 手里。想想:接口要求 Referer 包含 csrf2.lab,而 Referer 就是攻击页自己的 URL——名字起对了吗?表单是 POST 吗?to=hacker 带了吗?</div>
  <?php endif; ?>
  <p><a class="btn" href="index.php">返回状态面板</a> <a class="muted" href="reset.php">重置环境</a></p>
</div>
<?php page_foot('csrf2');
