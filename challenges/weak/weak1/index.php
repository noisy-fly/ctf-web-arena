<?php
require __DIR__ . '/config.php';
require __DIR__ . '/common.php';

$result = '';

if (isset($_GET['a'], $_GET['b'])) {
    $a = $_GET['a'];
    $b = $_GET['b'];
    if (is_string($a) && is_string($b)
        && $a != $b && md5($a) == md5($b)) {
        $result = ok_box('两把不同的钥匙打开了同一把锁：<br><code>' . h(get_flag()) . '</code>');
    } else {
        $result = bad_box('校验未通过。要求：<code>a != b</code> 且 <code>md5(a) == md5(b)</code>（注意是两个等号的松散比较）。');
    }
}

$body = $result . <<<FORM
<form method="get" class="labform">
  <input type="text" name="a" placeholder="口令 a，例如 hello">
  <input type="text" name="b" placeholder="口令 b，要求与 a 不同">
  <button type="submit">提交校验</button>
</form>
<p style="color:#8b949e;font-size:13px;">也可以用 curl：<code>curl "http://HOST/weak1/?a=...&amp;b=..."</code></p>
FORM;

$src = <<<'PHPSRC'
<?php
// 双口令校验器：两个管理员的口令必须不同，但 md5 要在 == 下相等
if (isset($_GET['a'], $_GET['b'])) {
    if ($_GET['a'] != $_GET['b']
        && md5($_GET['a']) == md5($_GET['b'])) {
        echo $FLAG;   // 直觉上 md5 不同则必不相等，== 会告诉你为什么"相等"
    }
}
PHPSRC;

render_page(array(
    'id' => 'weak1',
    'cat' => 'weak',
    'catname' => 'PHP 弱类型',
    'title' => '同一把锁的两把钥匙 —— == 弱比较',
    'difficulty' => 'easy',
    'desc' => '安全组为了"双人复核"，网关要求两名管理员各输入一个<b>不同</b>的口令，且两者的 md5 必须在 <code>==</code>（松散比较）下相等，通过后下发一次性令牌。直觉上这不可能——除非你知道 PHP 比较两个"看起来像数字"的字符串时会做什么。找出这样一对口令，拿到 flag。',
    'knowledge' => array('PHP 松散比较 ==', '数字字符串 → 科学计数法转换', '0e 开头的魔术哈希', 'hash_equals() / === 修复'),
    'hints' => array(
        'PHP 手册：当 == 的两边都是"数字字符串"时，会先转成数字再比较。那么 "0e830..." 会被转成什么？',
        '"0e830400..." 是合法的科学计数法（0×10^8304...），值为 0。于是任意两个 "0e+纯数字" 的字符串 == 时都是 0 == 0。',
        '找两个 md5 恰好是 0e+纯数字 的明文（"魔术哈希"）：QNKCDZO 和 240610708。提交 ?a=QNKCDZO&b=240610708。',
    ),
    'body' => $body,
    'src' => $src,
    'prev' => null,
    'next' => array('/weak2', 'strcmp 也靠不住'),
));
