<?php
require __DIR__ . '/config.php';
require __DIR__ . '/common.php';

$result = '';
if (isset($_REQUEST['user'], $_REQUEST['pass'])) {
    $user = $_REQUEST['user'];
    $pass = $_REQUEST['pass'];
    if ($user === 'admin' && is_string($pass) && md5($pass) == md5($SALT)) {
        $result = ok_box('登录成功，欢迎 admin！一次性令牌：<br><code>' . h(get_flag()) . '</code>');
    } else {
        $result = bad_box('用户名或密码错误。');
    }
}

$body = $result . <<<FORM
<form method="post" class="labform">
  <input type="text" name="user" placeholder="用户名" value="admin">
  <input type="password" name="pass" placeholder="密码（真实密码 = 某个未知随机盐）">
  <button type="submit">登录</button>
</form>
<p style="color:#8b949e;font-size:13px;">服务端把你的密码 md5 后与 md5(\$SALT) 做<b>松散</b>比较——\$SALT 是随机盐，你不知道。<br>
weak1 的知识在这里还有效吗？</p>
FORM;

$src = <<<'PHPSRC'
<?php
// 登录校验：密码 md5 后与服务端随机盐的 md5 松散比较
$user = $_REQUEST['user'];
$pass = $_REQUEST['pass'];

if ($user === 'admin' && md5($pass) == md5($SALT)) {
    // $SALT 是配置里的随机串（你看不到）
    echo $FLAG;
}
PHPSRC;

render_page(array(
    'id' => 'code3',
    'cat' => 'code',
    'catname' => '代码执行与认证绕过',
    'title' => '登录口的类型缺陷 —— md5($pass) == md5($salt)',
    'difficulty' => 'medium',
    'desc' => '管理后台的密码校验：<code>md5($pass) == md5($SALT)</code>，其中 $SALT 是服务端随机盐，不可知也不可猜。看起来必须爆破——但 weak1 学过：松散比较下，两个 md5 不必真的"相等"。用一类特殊明文让比较成立。',
    'knowledge' => array('认证中的 == 类型缺陷', '0e 魔术哈希通杀', 'hash_equals 修复', 'salt 不能救弱比较'),
    'hints' => array(
        'md5($SALT) 的值你不知道——但你不需要知道。两边都会被 == 转换。',
        '若 md5($pass) 是 0e+纯数字（科学计数法，值为 0），而 md5($SALT) 恰好也是 0e+纯数字呢？页面没告诉你盐，但出题人无法保证它的 md5 不长那样。',
        '试试魔术哈希明文：pass=QNKCDZO 或 240610708 或 s878926199a（任选其一）。',
    ),
    'body' => $body,
    'src' => $src,
    'prev' => array('/code2', 'create_function 的旧伤口'),
    'next' => array('/read1', '目录里散步'),
));
