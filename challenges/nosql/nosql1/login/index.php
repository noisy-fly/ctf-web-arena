<?php
/**
 * nosql1 · POST /nosql1/login —— MongoDB 风格操作符注入（PHP 8.2 复刻）
 *
 * 模拟一个用 NoSQL（MongoDB 风格查询）做登录认证的站点：
 *     db.users.findOne({ user: <用户输入>, pass: <用户输入> })
 * 若客户端提交 JSON 对象 / 表单数组语法，"密码"就成了一个**条件对象**而非字符串，
 * $ne / $nin / $regex 等操作符即可注入查询逻辑。
 *
 * WAF 对「原始请求体」做黑名单字符串匹配（$ne、$gt）——
 * 可被 JSON unicode 转义（\u0024ne）或黑名单遗漏（$nin/$regex）绕过。
 * 原题（Python/Flask）在 _original/app.php。
 */

function json_out(array $o, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ---------- 简化版 Mongo 匹配器（支持常见操作符，与原 Python 版语义一致） ---------- */
function re_search($pat, $subj)
{
    // 与 Python re.search 一致：入参是"裸模式"（无分隔符）。
    // PCRE 需要分隔符：挑一个模式里没出现的字符当分隔符。
    foreach (array('/', '#', '~', '%') as $d) {
        if (strpos($pat, $d) === false) {
            return @preg_match($d . $pat . $d, $subj) === 1;
        }
    }
    return false;
}

function mongo_match($stored, $cond)
{
    if (is_array($cond)) {
        foreach ($cond as $op => $arg) {
            if ($op === '$ne') {
                if ($stored === $arg) {
                    return false;
                }
            } elseif ($op === '$eq') {
                if ($stored !== $arg) {
                    return false;
                }
            } elseif ($op === '$gt' || $op === '$gte' || $op === '$lt' || $op === '$lte') {
                if (!is_scalar($stored) || !is_scalar($arg)) {
                    return false;
                }
                if (is_numeric($stored) && is_numeric($arg)) {
                    $a = (float)$stored;
                    $b = (float)$arg;
                } else {
                    $a = (string)$stored;
                    $b = (string)$arg;
                }
                if ($op === '$gt' && !($a > $b)) {
                    return false;
                }
                if ($op === '$gte' && !($a >= $b)) {
                    return false;
                }
                if ($op === '$lt' && !($a < $b)) {
                    return false;
                }
                if ($op === '$lte' && !($a <= $b)) {
                    return false;
                }
            } elseif ($op === '$regex') {
                if (!is_scalar($arg)) {
                    return false;
                }
                // 无效正则按"不匹配"处理（原版 re.error 时跳过该用户）
                if (!re_search((string)$arg, (string)$stored)) {
                    return false;
                }
            } elseif ($op === '$in' || $op === '$nin') {
                $list = is_array($arg) ? $arg : array($arg);
                $found = in_array($stored, $list, true);
                if ($op === '$in' && !$found) {
                    return false;
                }
                if ($op === '$nin' && $found) {
                    return false;
                }
            } elseif ($op === '$exists') {
                if (($stored !== null) !== (bool)$arg) {
                    return false;
                }
            } else {
                return false; // 未知操作符：不匹配
            }
        }
        return true;
    }
    return $stored === $cond; // 普通等值匹配
}

function find_user(array $query, array $users)
{
    $userCond = array_key_exists('user', $query) ? $query['user'] : null;
    $passCond = array_key_exists('pass', $query) ? $query['pass'] : null;
    foreach ($users as $name => $rec) {
        if (mongo_match($name, $userCond) && mongo_match($rec['password'], $passCond)) {
            return array($name, $rec);
        }
    }
    return null;
}

/* ---------- 登录接口 ---------- */
$FLAG = getenv('FLAG');
if ($FLAG === false || $FLAG === '') {
    $f = @file_get_contents(__DIR__ . '/../flag.txt');
    $FLAG = ($f === false) ? '' : trim($f);
}
$users = require __DIR__ . '/../users.php';

$raw = file_get_contents('php://input');

// WAF：对「原始请求体」做黑名单匹配
foreach (array('$ne', '$gt') as $bad) {
    if (str_contains($raw, $bad)) {
        json_out(array('ok' => false, 'msg' => 'WAF 拦截：请求体中发现危险操作符 ' . $bad), 403);
    }
}

$ctype = strtolower(isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '');
$query = array();

if (str_contains($ctype, 'json')) {
    $data = null;
    if (trim((string)$raw) !== '') {
        $data = json_decode($raw, true);
    } else {
        $data = array();
    }
    if (!is_array($data) || ($data !== array() && array_is_list($data))) {
        json_out(array('ok' => false, 'msg' => '参数解析失败: body must be a JSON object'), 400);
    }
    $query = array(
        'user' => array_key_exists('user', $data) ? $data['user'] : null,
        'pass' => array_key_exists('pass', $data) ? $data['pass'] : null,
    );
} else {
    // 表单/URL 参数：PHP 原生解析即支持数组语法——
    // pass[$ne]=x 会被直接解析成 $_POST['pass'] = array('$ne' => 'x')，
    // 这正是 PHP/Express 时代操作符注入的经典入口。
    $src = array_merge($_GET, $_POST);
    foreach (array('user', 'pass') as $k) {
        if (array_key_exists($k, $src)) {
            $query[$k] = $src[$k];
        }
    }
}

if (!array_key_exists('user', $query) || !array_key_exists('pass', $query)) {
    json_out(array('ok' => false, 'msg' => '请同时提交 user 与 pass'), 400);
}

$hit = find_user($query, $users);
if ($hit !== null) {
    list($name, $rec) = $hit;
    $resp = array('ok' => true, 'user' => $name, 'role' => $rec['role']);
    if ($rec['role'] === 'admin') {
        $resp['msg'] = '以 admin 身份登录成功，拿到一次性令牌';
        $resp['flag'] = $FLAG;
    } else {
        $resp['msg'] = '以 ' . $name . '（' . $rec['role'] . '）登录成功——但这不是 admin，没有 flag';
    }
    json_out($resp);
}
json_out(array('ok' => false, 'msg' => '用户名或密码错误'), 401);
