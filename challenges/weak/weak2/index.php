<?php
require __DIR__ . '/config.php';
require __DIR__ . '/common.php';

$resultA = '';
$resultB = '';

// 实验 A：用 strcmp 比对口令
if (isset($_GET['password'])) {
    if (@strcmp($_GET['password'], $REAL_PASSWORD) == 0) {
        $resultA = ok_box('strcmp 认为口令一致！<br><code>' . h(get_flag()) . '</code>');
    } else {
        $resultA = bad_box('strcmp 返回了非 0 —— 口令不一致（或类型正常）。');
    }
}

// 实验 B：用 md5 比对两段"密文"
if (isset($_GET['x'], $_GET['y'])) {
    if ($_GET['x'] !== $_GET['y'] && @md5($_GET['x']) == @md5($_GET['y'])) {
        $resultB = ok_box('两个不同的值，md5 却"相等"！<br><code>' . h(get_flag()) . '</code>');
    } else {
        $resultB = bad_box('md5 校验未通过（x 与 y 必须不同）。');
    }
}

$body = <<<FORM
<div style="margin-bottom:18px;">
  <b>实验 A · strcmp 口令校验</b>
  $resultA
  <form method="get" class="labform">
    <input type="text" name="password" placeholder="输入口令（真实口令在服务端，你不知道）">
    <button type="submit">提交 A</button>
  </form>
</div>
<div>
  <b>实验 B · md5 一致性校验</b>
  $resultB
  <form method="get" class="labform">
    <input type="text" name="x" placeholder="值 x">
    <input type="text" name="y" placeholder="值 y（需与 x 不同）">
    <button type="submit">提交 B</button>
  </form>
</div>
<p style="color:#8b949e;font-size:13px;">任一实验通过即可获得 flag。提示：这两个函数对"数组参数"的反应和字符串不一样。</p>
FORM;

$src = <<<'PHPSRC'
<?php
// 实验 A：strcmp 的返回值与 0 松散比较
if (isset($_GET['password'])) {
    if (@strcmp($_GET['password'], $REAL_PASSWORD) == 0) {
        echo $FLAG;                    // 相等时 strcmp 返回 0；不相等返回非 0？
    }
}

// 实验 B：两个不同值的 md5 松散比较
if (isset($_GET['x'], $_GET['y'])) {
    if ($_GET['x'] !== $_GET['y']
        && @md5($_GET['x']) == @md5($_GET['y'])) {
        echo $FLAG;                    // md5() 遇到数组会返回什么？
    }
}
PHPSRC;

render_page(array(
    'id' => 'weak2',
    'cat' => 'weak',
    'catname' => 'PHP 弱类型',
    'title' => '函数遇到数组时 —— strcmp / md5 返回 NULL',
    'difficulty' => 'easy',
    'desc' => '同一套登录代码的两个安全检查：实验 A 用 <code>strcmp()</code> 比对口令，实验 B 要求两个<b>不同</b>值的 <code>md5()</code> 松散相等。这两个函数在收到<b>数组</b>参数时并不抛错（PHP 7），而是发出一条警告并返回 <code>NULL</code>——而 <code>NULL == 0</code>、<code>NULL == NULL</code>。GET 参数天生支持数组语法（<code>?password[]=1</code>）。让任一实验放行。',
    'knowledge' => array('strcmp(array) → NULL（PHP<8）', 'md5(array) → NULL', 'NULL == 0 松散比较', 'GET 数组参数语法 []'),
    'hints' => array(
        'GET/POST 参数可以传数组：?password[]=1 会让 $_GET["password"] 变成 Array。',
        'PHP7 中 strcmp()/md5() 收到数组只报 warning 并返回 NULL；@ 只是页面里用来抑制警告显示。',
        '实验 A：?password[]=anything → strcmp 返回 NULL，NULL == 0 成立。实验 B：?x[]=1&y[]=2 → md5 各返回 NULL，NULL==NULL 成立。',
    ),
    'body' => $body,
    'src' => $src,
    'prev' => array('/weak1', '同一把锁的两把钥匙'),
    'next' => array('/weak3', '白名单的裂缝'),
));
