<?php
require __DIR__ . '/config.php';
require __DIR__ . '/common.php';

$resultA = '';
$resultB = '';

// 门 1：switch 松散比较 —— case 0 是开发遗留的"调试等级"，直接发令牌
if (isset($_GET['code'])) {
    switch ($_GET['code']) {
        case 0:
            $resultA = ok_box('命中调试等级 0（开发遗留后门）：<br><code>' . h(get_flag()) . '</code>');
            break;
        default:
            $resultA = bad_box('邀请码无效。');
    }
}

// 门 2：in_array 未带第三参数 —— 名单里有历史遗留的 0 号
if (isset($_GET['id'])) {
    if (in_array($_GET['id'], $STAFF_IDS)) {
        $resultB = ok_box('员工编号命中（in_array 放行）：<br><code>' . h(get_flag()) . '</code>');
    } else {
        $resultB = bad_box('你不在员工名单里。');
    }
}

$body = <<<FORM
<div style="margin-bottom:18px;">
  <b>门 1 · 贵宾邀请码（switch 校验，等级 0 是调试码）</b>
  $resultA
  <form method="get" class="labform">
    <input type="text" name="code" placeholder="输入邀请码">
    <button type="submit">验证</button>
  </form>
</div>
<div>
  <b>门 2 · 员工编号（in_array 校验，名单含 0 号保留位）</b>
  $resultB
  <form method="get" class="labform">
    <input type="text" name="id" placeholder="输入员工编号">
    <button type="submit">验证</button>
  </form>
</div>
<p style="color:#8b949e;font-size:13px;">两扇门任一打开即得 flag。注意：校验都是<b>松散</b>比较，而 GET 参数永远是字符串——字符串和整数 0 比较时会怎样？</p>
FORM;

$src = <<<'PHPSRC'
<?php
// 门 1：switch 的 case 比较用的是 ==（松散）
switch ($_GET['code']) {
    case 0:            // 开发遗留：等级 0 是调试码，直接发令牌
        echo $FLAG;
        break;
}

// 门 2：in_array 默认松散比较（第三参数 strict 未传）
if (in_array($_GET['id'], $STAFF_IDS)) {   // $STAFF_IDS = [0, 1001, 1002, 1003]
    echo $FLAG;
}
PHPSRC;

render_page(array(
    'id' => 'weak3',
    'cat' => 'weak',
    'catname' => 'PHP 弱类型',
    'title' => '白名单的裂缝 —— switch / in_array 松散比较',
    'difficulty' => 'easy',
    'desc' => '系统有两扇门：门 1 用 <code>switch</code> 校验贵宾等级（<code>case 0</code> 是开发遗留的调试码，直接发管理员令牌）；门 2 用 <code>in_array()</code> 核对员工编号（名单里有个历史遗留的 0 号保留位）。GET 参数传进来永远是<b>字符串</b>，而这两处校验用的都是<b>松散比较</b>：字符串与整数比较前，非数字字符串会被转成 0（PHP 7.x 行为）。',
    'knowledge' => array('switch case 松散匹配', 'in_array 缺第三参数', '"任意串" == 0（PHP<8）', 'in_array($x,$arr,true) 修复'),
    'hints' => array(
        'GET 参数永远是字符串。"abc" 和整数 0 用 == 比较时，"abc" 会被转成什么？',
        'PHP<8 中任何非数字（且非纯数字前缀）字符串与整数比较都按 0 处理："abc" == 0 为 true。',
        '门 1：?code=abc（任意非数字串）命中 case 0。门 2：?id=abc 匹配名单里的 0。',
    ),
    'body' => $body,
    'src' => $src,
    'prev' => array('/weak2', 'strcmp 也靠不住'),
    'next' => array('/weak4', '变量从天而降'),
));
