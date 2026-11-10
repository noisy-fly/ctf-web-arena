<?php
require __DIR__ . '/_compat.php';
require __DIR__ . '/config.php';
require __DIR__ . '/common.php';

$mode = $_GET['m'] ?? 'calc';
$out = '';

if ($mode === 'calc') {
    // 在线计算器：表达式被拼进 create_function 的函数体
    $expr = $_GET['e'] ?? '1+1';
    $fn = @create_function('', 'return ' . $expr . ';');
    if ($fn) {
        $out = '<pre class="out">= ' . h((string)$fn()) . '</pre>';
    } else {
        $out = bad_box('表达式无法编译。');
    }
} elseif ($mode === 'check') {
    // 断言检查器：字符串断言会被当作 PHP 代码求值（PHP7 + zend.assertions=1）
    $x = $_GET['x'] ?? '1==1';
    if (@assert($x)) {
        $out = '<pre class="out">断言成立</pre>';
    } else {
        $out = bad_box('断言不成立（或表达式报错）。');
    }
}

$body = <<<FORM
<div style="margin-bottom:18px;">
  <b>模式 A · 在线计算器（create_function）</b>
  <form method="get" class="labform">
    <input type="hidden" name="m" value="calc">
    <input type="text" name="e" placeholder="PHP 表达式，如 6*7" value="1+1">
    <button type="submit">计算</button>
  </form>
</div>
<div>
  <b>模式 B · 断言检查器（assert）</b>
  <form method="get" class="labform">
    <input type="hidden" name="m" value="check">
    <input type="text" name="x" placeholder="断言表达式，如 1==1" value="1==1">
    <button type="submit">断言</button>
  </form>
</div>
<p style="color:#8b949e;font-size:13px;">两个模式都把你的输入拼进了"会被 eval 的地方"。flag 在 <code>config.php</code>。</p>
$out
FORM;

$src = <<<'PHPSRC'
<?php
// 模式 A：$expr 被拼进 create_function 的函数体（内部等价于 eval）
$fn = create_function('', 'return ' . $expr . ';');
echo $fn();

// 模式 B：assert 收到字符串参数时按 PHP 代码求值（PHP7，需 zend.assertions=1）
assert($x);
PHPSRC;

render_page(array(
    'id' => 'code2',
    'cat' => 'code',
    'catname' => '代码执行与认证绕过',
    'title' => 'create_function 的旧伤口 —— 函数体注入',
    'difficulty' => 'medium',
    'desc' => '在线计算器用 <code>create_function(\'\', \'return \' . $expr . \';\')</code> 动态生成函数——你的表达式被直接拼进函数体，而 create_function 内部就是一次 <code>eval</code>。断言检查器则把输入交给 <code>assert()</code>：PHP 7 里字符串断言同样会被 eval。读出 <code>config.php</code> 拿 flag。',
    'knowledge' => array('create_function 函数体注入', '} 提前闭合逃逸', 'assert 字符串断言', '代码执行的另一类入口'),
    'hints' => array(
        '模式 A 最简单：表达式本身就是函数体里 return 后的代码。哪个函数能读文件？',
        '?m=calc&e=file_get_contents(\'config.php\') —— 直接把源码 return 出来。',
        '进阶逃逸：e=1;}print_r(file_get_contents(\'config.php\'));// 用 } 提前结束函数，注入任意语句。模式 B：?m=check&x=print_r(file_get_contents(\'config.php\'))。',
    ),
    'body' => $body,
    'src' => $src,
    'prev' => array('/code1', '把函数名也交出去'),
    'next' => array('/code3', '登录口的类型缺陷'),
));
