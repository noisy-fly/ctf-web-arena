<?php
/**
 * nosql2 · POST /nosql2/login —— XPath 注入（PHP 8.2 复刻）
 *
 * 模拟一个用 XML 存用户、用 XPath 做认证的老系统：
 *     //users/user[username='$user' and password='$pass']
 * 用户输入被直接字符串拼接进 XPath 表达式 → 闭合引号 + or 恒真 → 绕过认证。
 *
 * 原题内置自写 XPath 1.0 子集求值器（_original/app.py）；
 * PHP 版改用内置 DOMXPath（libxml）——真实的 XPath 1.0 引擎，行为一致且更贴近生产。
 */

function json_out(array $o, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function child_val($userNode, $tag)
{
    $ls = $userNode->getElementsByTagName($tag);
    if ($ls->length === 0) {
        return '';
    }
    return (string)$ls->item(0)->textContent;
}

libxml_use_internal_errors(true);

$doc = new DOMDocument();
if (!@$doc->load(__DIR__ . '/../users.xml')) {
    json_out(array('ok' => false, 'msg' => '用户数据源加载失败'), 500);
}

// 部署时允许用环境变量 FLAG 覆盖 admin 口令（保持与门户统一注入）
$flagEnv = getenv('FLAG');
if ($flagEnv !== false && $flagEnv !== '') {
    foreach ($doc->getElementsByTagName('user') as $u) {
        if (child_val($u, 'role') === 'admin') {
            $pw = $u->getElementsByTagName('password')->item(0);
            if ($pw !== null) {
                $pw->nodeValue = $flagEnv;
            }
        }
    }
}

$user = (isset($_POST['user']) && is_string($_POST['user'])) ? $_POST['user'] : '';
$pass = (isset($_POST['pass']) && is_string($_POST['pass'])) ? $_POST['pass'] : '';

// 服务端拼接 XPath（漏洞所在）；求值只针对方括号内的条件部分
$cond = "username='" . $user . "' and password='" . $pass . "'";
$expr = '//users/user[' . $cond . ']';

$xp = new DOMXPath($doc);
$nodes = @$xp->query($expr);

if ($nodes === false) {
    // 报错回显拼接后的查询 —— 经典错误信息泄露
    $reason = (substr_count($cond, "'") % 2 === 1) ? '字符串字面量未闭合' : '表达式语法非法';
    json_out(array('ok' => false, 'msg' => 'XPath 解析错误：' . $reason, 'query' => $expr), 400);
}

if ($nodes->length > 0) {
    foreach ($nodes as $u) { // 与原版一致：取第一个命中的用户
        $username = child_val($u, 'username');
        $role = child_val($u, 'role');
        $password = child_val($u, 'password');
        if ($role === 'admin') {
            json_out(array(
                'ok' => true,
                'user' => $username,
                'role' => 'admin',
                'msg' => '以 admin 登录成功，口令即通关令牌',
                'flag' => $password,
            ));
        }
        json_out(array(
            'ok' => true,
            'user' => $username,
            'role' => ($role !== '' ? $role : 'user'),
            'msg' => '以 ' . $username . ' 登录成功（普通用户没有令牌）',
        ));
    }
}
json_out(array('ok' => false, 'msg' => '认证失败：用户不存在或口令错误'), 401);
