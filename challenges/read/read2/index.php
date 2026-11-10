<?php
require __DIR__ . '/config.php';
require __DIR__ . '/common.php';

// 下载中心：按用户提供的路径提供文件下载
$file = $_GET['file'] ?? '';

if ($file !== '') {
    if (is_file($file)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        echo file_get_contents($file);
        exit;
    }
    http_response_code(404);
    echo '文件不存在: ' . htmlspecialchars(basename($file));
    exit;
}

// 没有参数时显示下载中心首页
$files = glob(__DIR__ . '/files/*');
$list = '';
foreach ($files as $f) {
    $list .= '<li><a href="?file=' . urlencode(str_replace('\\', '/', $f)) . '">' . h(basename($f)) . '</a>（公开资料）</li>';
}

$body = <<<FORM
<p>以下是公开资料，点击即下载（注意观察 URL 里 <code>file=</code> 的值）：</p>
<ul>$list</ul>
<p style="color:#8b949e;font-size:13px;">下载接口对 <code>file</code> 参数<b>没有任何限制</b>——绝对路径、相对路径都行。<br>
站点目录里还有什么文件？PHP 源码下载下来是"原始文本"。</p>
FORM;

$src = <<<'PHPSRC'
<?php
// download.php 下载中心
$file = $_GET['file'] ?? '';

if (is_file($file)) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    echo file_get_contents($file);   // 任意路径直接读 → 任意文件下载
    exit;
}
PHPSRC;

render_page(array(
    'id' => 'read2',
    'cat' => 'read',
    'catname' => '文件读取 / 目录遍历',
    'title' => '下载中心 —— 任意文件下载',
    'difficulty' => 'easy',
    'desc' => '资料站的"下载中心"直接拿 <code>?file=</code> 参数当文件路径，没有任何目录限制。点击公开资料看清参数格式，然后让接口下载一个它本不该给的文件：同目录下的 <code>config.php</code>（PHP 文件被当作下载内容时，返回的是未解析的原始源码）。',
    'knowledge' => array('任意文件下载', '下载接口读源码', 'basename/realpath 防护', '/etc/passwd 验证'),
    'hints' => array(
        '公开文件的 file 参数是绝对路径——说明接口接受任意路径。',
        '下载 PHP 文件不会被执行：file_get_contents 拿到的是源码文本。',
        '?file=config.php（或点击公开文件后把路径末段改成 config.php）。',
    ),
    'body' => $body,
    'src' => $src,
    'prev' => array('/read1', '目录里散步'),
    'next' => array('/read3', 'flag 不在磁盘上'),
));
