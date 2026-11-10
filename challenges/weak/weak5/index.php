<?php
require __DIR__ . '/config.php';
require __DIR__ . '/common.php';

$auth = $_POST['token'] ?? '';

// 把 GET 参数注册成变量（可变变量 $$）
foreach ($_GET as $k => $v) {
    $$k = $v;
}

$result = '';
if ($auth === $SECRET_TOKEN) {
    $result = ok_box('令牌校验通过（严格 === 也没用）：：<br><code>' . h(get_flag()) . '</code>');
} else {
    $result = bad_box('令牌不匹配。$auth 来自 POST token，$SECRET_TOKEN 在服务端——但它们真的只受服务端控制吗？');
}

$body = $result . <<<FORM
<form method="post" class="labform">
  <input type="text" name="token" placeholder="API Token（POST）">
  <button type="submit">校验</button>
</form>
<p style="color:#8b949e;font-size:13px;">页面同时会把 <b>GET</b> 参数变成变量（\$\$k）。<br>
试试：<code>POST /weak5/?auth=pwn&SECRET_TOKEN=pwn</code>，token 随便填。</p>
FORM;

$src = <<<'PHPSRC'
<?php
$auth = $_POST['token'] ?? '';

foreach ($_GET as $k => $v) {
    $$k = $v;        // 可变变量：GET 的键名成为变量名 → 任何变量都可能被覆盖
}

if ($auth === $SECRET_TOKEN) {   // === 严格比较，但比较的"两侧"都可被覆盖
    echo $FLAG;
}
PHPSRC;

render_page(array(
    'id' => 'weak5',
    'cat' => 'weak',
    'catname' => 'PHP 弱类型',
    'title' => '$$ 双重美元 —— 可变变量覆盖',
    'difficulty' => 'medium',
    'desc' => 'API 网关用 <code>===</code> 严格比较你提交的 token 与服务端密钥——看起来无懈可击。但为了"动态注册参数"，代码把 GET 的每个键值对都通过可变变量 <code>$$k = $v</code> 写进了当前作用域。比较的两侧如果都不再是服务端的值，严格比较也就失去了意义。',
    'knowledge' => array('可变变量 $$', 'foreach($_GET) 变量注入', '覆盖比较两侧绕过 ===', '变量白名单修复'),
    'hints' => array(
        '$$k 表示"名字为 $k 的变量"。GET ?foo=1 → $foo=1。那么 $auth、$SECRET_TOKEN 能不能也这样赋值？',
        'GET 参数名可以是大写：?SECRET_TOKEN=pwn → $SECRET_TOKEN = "pwn"。',
        '把两侧都覆盖成同一个值：POST token 随便，URL 加 ?auth=pwn&SECRET_TOKEN=pwn → "pwn" === "pwn"。',
    ),
    'body' => $body,
    'src' => $src,
    'prev' => array('/weak4', '变量从天而降'),
    'next' => array('/code1', '把函数名也交出去'),
));
