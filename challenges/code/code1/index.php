<?php
require __DIR__ . '/config.php';
require __DIR__ . '/common.php';

function welcome($p)
{
    return "welcome, $p ! 这里是插件路由 demo。";
}

$result = '';

$f = $_GET['f'] ?? 'welcome';
$p = $_GET['p'] ?? '';

if ($f !== 'welcome') {
    $blacklist = 'system|exec|passthru|shell_exec|popen|proc_open|pcntl_exec|eval';
    if (!preg_match('/' . $blacklist . '/i', $f)) {
        if (is_callable($f)) {
            $out = call_user_func($f, $p);
            $result = '<pre class="out">' . h((string)$out) . '</pre>';
        } else {
            $result = bad_box("函数 " . h($f) . " 不存在。");
        }
    } else {
        $result = bad_box('危险函数已被黑名单拦截：' . h($f));
    }
} else {
    $result = '<pre class="out">' . h(welcome($p)) . '</pre>';
}

$body = <<<FORM
<form method="get" class="labform">
  <input type="text" name="f" placeholder="函数名 f" value="welcome">
  <input type="text" name="p" placeholder="参数 p" value="">
  <button type="submit">调用</button>
</form>
<p style="color:#8b949e;font-size:13px;">黑名单拦住了 system/exec/passthru/shell_exec/popen/proc_open/pcntl_exec/eval。<br>
但 PHP 有上千个函数——你只需要一个能"读文件"的。flag 就藏在 <code>config.php</code> 里。</p>
$result
FORM;

$src = <<<'PHPSRC'
<?php
$f = $_GET['f'] ?? 'welcome';
$p = $_GET['p'] ?? '';

$blacklist = 'system|exec|passthru|shell_exec|popen|proc_open|pcntl_exec|eval';
if (!preg_match('/' . $blacklist . '/i', $f) && is_callable($f)) {
    echo call_user_func($f, $p);      // 函数名来自用户 → 任何可调用函数都能执行
}

function welcome($p) { return "welcome, $p"; }
function get_flag() { /* 在 config.php 里定义 */ }
PHPSRC;

render_page(array(
    'id' => 'code1',
    'cat' => 'code',
    'catname' => '代码执行与认证绕过',
    'title' => '把函数名也交出去 —— 动态函数调用',
    'difficulty' => 'easy',
    'desc' => '"插件路由"把函数名和参数都放在 GET 里：<code>?f=函数名&p=参数</code>。安全组加了危险函数黑名单就上线了。黑名单挡不住 PHP 的上千个内置函数——找一个能读文件的，把同目录下 <code>config.php</code> 的源码读出来，flag 就在里面。',
    'knowledge' => array('动态函数调用 $f($p)', 'call_user_func', '黑名单 vs 白名单', 'file_get_contents 读源码'),
    'hints' => array(
        '黑名单只拦"命令执行"类函数。读文件的函数有哪些？file_get_contents / readfile / show_source / highlight_file……',
        'flag 在 config.php 里，函数把它 include 进来，但源码本身没人加密。',
        '?f=file_get_contents&p=config.php —— 返回的就是原始 PHP 源码。彩蛋：?f=get_flag 直接调用题目自己定义的函数。',
    ),
    'body' => $body,
    'src' => $src,
    'prev' => array('/weak5', '$$ 双重美元'),
    'next' => array('/code2', 'create_function 的旧伤口'),
));
