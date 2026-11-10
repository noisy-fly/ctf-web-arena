<?php
require __DIR__ . '/config.php';
require __DIR__ . '/common.php';

// ---- 初始化会话状态（本应由服务端掌控） ----
$is_login = false;
$username = 'guest';

// ---- 把 GET 参数导入当前作用域（危险！） ----
extract($_GET);

$result = '';
if ($is_login && $username === 'admin') {
    $result = ok_box('欢迎回来，admin！你的会话令牌：<br><code>' . h(get_flag()) . '</code>');
} elseif ($is_login) {
    $result = bad_box('已登录，但你是 ' . h($username) . '，只有 admin 能看令牌。');
} else {
    $result = bad_box('未登录。GET 上的参数会被 extract() 倒进变量里——想想哪些变量值得覆盖。');
}

$body = $result . <<<FORM
<form method="get" class="labform">
  <input type="text" name="username" placeholder="随便填点什么覆盖 username">
  <button type="submit">提交</button>
</form>
<p style="color:#8b949e;font-size:13px;">extract($_GET) 会把每个 GET 参数变成当前作用域的变量，同名即覆盖。<br>
试试直接构造 URL：<code>?is_login=1&amp;username=admin</code></p>
FORM;

$src = <<<'PHPSRC'
<?php
$is_login = false;        // 服务端初始化的"会话状态"
$username = 'guest';

extract($_GET);           // 把所有 GET 参数导入为变量（EXTR_OVERWRITE，覆盖同名！）

if ($is_login && $username === 'admin') {
    echo $FLAG;           // $is_login / $username 都可能已被 GET 覆盖
}
PHPSRC;

render_page(array(
    'id' => 'weak4',
    'cat' => 'weak',
    'catname' => 'PHP 弱类型',
    'title' => '变量从天而降 —— extract 变量覆盖',
    'difficulty' => 'medium',
    'desc' => '老项目里常见的写法：为了"方便"，把 GET 参数用 <code>extract()</code> 直接导入成变量。但变量作用域里还躺着服务端自己的状态变量（<code>$is_login</code>、<code>$username</code>……）。攻击者通过 URL 就能给任何变量赋值。覆盖正确的组合，让页面认为你是已登录的 admin。',
    'knowledge' => array('extract($_GET) 变量覆盖', 'EXTR_OVERWRITE 默认行为', 'parse_str 同族风险', '白名单取参修复'),
    'hints' => array(
        'extract 会覆盖哪些变量？源码里 $is_login 和 $username 是谁初始化的。',
        'GET 参数名就是变量名：?is_login=1 会执行 $is_login = "1"。',
        '构造 ?is_login=1&username=admin —— 两个条件同时满足。',
    ),
    'body' => $body,
    'src' => $src,
    'prev' => array('/weak3', '白名单的裂缝'),
    'next' => array('/weak5', '$$ 双重美元'),
));
