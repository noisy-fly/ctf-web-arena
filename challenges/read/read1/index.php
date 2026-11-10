<?php
require __DIR__ . '/common.php';

// “静态页面加载器”：只允许加载 pages/ 目录下的页面
$page = $_GET['page'] ?? 'welcome';

// 安全过滤：去掉目录穿越符号（只过滤一次）
$page = str_replace('../', '', $page);

$path = __DIR__ . '/pages/' . $page;

if (is_string($page) && $page !== '' && is_file($path)) {
    $out = '<pre class="out">' . h(file_get_contents($path)) . '</pre>';
} else {
    $out = bad_box('页面不存在。可用页面：welcome、about');
}

$body = <<<FORM
<form method="get" class="labform">
  <input type="text" name="page" placeholder="页面名，如 welcome" value="welcome">
  <button type="submit">加载</button>
</form>
<p style="color:#8b949e;font-size:13px;">听说敏感文件放在站点目录的<b>上一级</b>的 <code>secret_zone/</code> 里（和 pages/ 平级）。</p>
$out
FORM;

$src = <<<'PHPSRC'
<?php
// 静态页面加载器
$page = $_GET['page'] ?? 'welcome';

$page = str_replace('../', '', $page);   // 过滤目录穿越（但只过滤了一次！）

$path = __DIR__ . '/pages/' . $page;
if (is_file($path)) {
    echo file_get_contents($path);
}
PHPSRC;

render_page(array(
    'id' => 'read1',
    'cat' => 'read',
    'catname' => '文件读取 / 目录遍历',
    'title' => '目录里散步 —— 过滤一次的 ../',
    'difficulty' => 'easy',
    'desc' => '静态站点的页面加载器允许 <code>?page=xxx</code> 读取 <code>pages/</code> 目录下的文件。开发知道有人会用 <code>../</code> 逃出目录，于是把 <code>../</code> 替换为空——但只替换了<b>一次</b>。敏感文件在站点目录的 <code>secret_zone/</code>（与 pages/ 平级，即 pages/ 的上一级）。读出它。',
    'knowledge' => array('目录遍历 ../', 'str_replace 单次替换缺陷', '双写绕过 ....//', 'realpath 判根修复'),
    'hints' => array(
        'str_replace 不是循环过滤：替换完成后不会再扫一遍结果。',
        '提交 ....// 会被替换成什么？（中间的 ../ 被吃掉，剩下的字符重新拼出 ../）',
        '?page=....//secret_zone/flag.txt —— 双写绕过，读到 pages/../secret_zone/flag.txt。',
    ),
    'body' => $body,
    'src' => $src,
    'prev' => array('/code3', '登录口的类型缺陷'),
    'next' => array('/read2', '下载中心'),
));
