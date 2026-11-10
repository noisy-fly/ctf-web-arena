<?php
require __DIR__ . '/common.php';

// flag 只存在于环境变量 FLAG 中，磁盘上没有 flag 文件
// （Linux 部署时由容器 -e FLAG=... 注入；本地开发内嵌默认值）

// 环境自检：/proc 可用性（Windows 开发机无 /proc，使用本地模拟文件）
$procEnv = '/proc/self/environ';
$procAvailable = is_dir('/proc');
if (!$procAvailable) {
    $procEnv = str_replace('\\', '/', __DIR__) . '/fakeproc/self/environ';
}

$selfCheck = $procAvailable
    ? '环境自检：<code>/proc</code> 可用 —— 进程环境位于 <code>/proc/self/environ</code>'
    : '环境自检：本机无 <code>/proc</code>（开发环境），已启用本地模拟：<code>' . h($procEnv) . '</code>';

$f = $_GET['file'] ?? '';

// 循环过滤 ".."，双写绕不过
while (strpos($f, '..') !== false) {
    $f = str_replace('..', '', $f);
}

$out = '';
if ($f === '') {
    $out = bad_box('请提供要读取的文件（绝对路径）。' );
} elseif (stripos($f, 'flag') !== false) {
    $out = bad_box('检测到敏感关键字 flag —— 拒绝访问。flag 也不在任何文件名/内容能猜到的地方。');
} elseif (!is_file($f)) {
    $out = bad_box('文件不存在（提示：必须是服务器上的绝对路径）。');
} else {
    $out = '<pre class="out">' . h(file_get_contents($f)) . '</pre>';
}

$body = <<<FORM
<p class="selfcheck">$selfCheck</p>
<form method="get" class="labform">
  <input type="text" name="file" placeholder="要读取的文件（绝对路径）" style="min-width:420px;">
  <button type="submit">读取</button>
</form>
<p style="color:#8b949e;font-size:13px;">
过滤：循环删除 <code>..</code>（双写无效）+ 黑名单关键字 <code>flag</code>。<br>
但 flag 根本<b>不在磁盘上</b>——它在进程的<b>环境变量</b>里。每个进程的环境变量在 Linux 上都能通过一个"虚拟文件"读到。</p>
$out
FORM;

$src = <<<'PHPSRC'
<?php
$f = $_GET['file'] ?? '';

while (strpos($f, '..') !== false) {      // 循环过滤，双写绕过无效
    $f = str_replace('..', '', $f);
}
if (stripos($f, 'flag') !== false) {      // 关键字黑名单
    die('禁止访问敏感文件');
}
if (is_file($f)) {
    echo file_get_contents($f);           // 但绝对路径没有拦 —— /proc 也在其中
}

// Linux 上：/proc/self/environ 保存本进程的环境变量（含 FLAG）
PHPSRC;

render_page(array(
    'id' => 'read3',
    'cat' => 'read',
    'catname' => '文件读取 / 目录遍历',
    'title' => 'flag 不在磁盘上 —— /proc/self/environ',
    'difficulty' => 'medium',
    'desc' => '文件读取接口做了两层防护：<code>..</code> 循环过滤（双写无效）+ <code>flag</code> 关键字黑名单。看起来滴水不漏——因为 flag 确实不在任何文件里，它在进程<b>环境变量</b> <code>FLAG</code> 中。Linux 上每个进程的环境变量都镜像在虚拟文件系统 /proc 里。用"文件读取"读出它。',
    'knowledge' => array('/proc 虚拟文件系统', '/proc/self/environ 读环境变量', '绝对路径未校验', '软链接逃逸变体'),
    'hints' => array(
        '/proc/self 是"当前进程自己"。environ、cmdline、fd 都在里面。',
        '/proc/self/environ 的内容是 KEY=VALUE\\0 形式的环境变量 dump。',
        '读取 /proc/self/environ（页面顶部环境自检在非 Linux 机器上给出了模拟路径）。',
    ),
    'body' => $body,
    'src' => $src,
    'prev' => array('/read2', '下载中心'),
    'next' => array('/nosql1', '不是只有 SQL 才能注入'),
));
