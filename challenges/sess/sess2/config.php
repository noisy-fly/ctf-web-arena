<?php
// 星门 SSO 配置（真实文件，含密钥——正常情况下绝不可下载）
define('SSO_SECRET', 'StarGate@2024#Salt');
define('SESS2_FLAG', 'flag{sess2_63671a77}');

// 用户库（演示）
function sso_users() {
    return array(
        'player' => array('pass' => 'Player@123', 'role' => 'user'),
        'admin'  => array('pass' => '???', 'role' => 'admin'),   // 管理员口令无人知晓
    );
}

// 弱签名：自制 MAC —— md5(字段|字段|字段|SECRET)
function sso_sign($user, $role, $exp) {
    return md5($user . '|' . $role . '|' . $exp . '|' . SSO_SECRET);
}

function b64url_encode($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function b64url_decode($s) { return base64_decode(strtr($s, '-_', '+/')); }

// 签发登录凭证 Cookie： base64url(json) . "." . 签名
function sso_issue($user, $role) {
    $exp = time() + 3600;
    $payload = json_encode(array('user' => $user, 'role' => $role, 'exp' => $exp));
    return b64url_encode($payload) . '.' . sso_sign($user, $role, $exp);
}

// 校验凭证：结构、有效期、签名
function sso_verify($cookie, &$out) {
    $parts = explode('.', (string)$cookie);
    if (count($parts) !== 2) { return '格式错误（应为 payload.signature）'; }
    $json = b64url_decode($parts[0]);
    $d = json_decode($json, true);
    if (!is_array($d) || !isset($d['user'], $d['role'], $d['exp'])) { return 'payload 结构错误'; }
    if (!is_numeric($d['exp']) || $d['exp'] < time()) { return '凭证已过期'; }
    if (hash_equals(sso_sign($d['user'], $d['role'], $d['exp']), $parts[1]) !== true) {
        return '签名不匹配';
    }
    $out = $d;
    return null;
}
