<?php
// sess1 · 模拟管理员登录
// 漏洞版：登录态写入【当前传入的 sid】，不更换 → 会话固定成立
// 修复版（?fixed=1）：生成全新随机 sid，数据迁移，旧 sid 作废
require_once __DIR__ . '/session_lib.php';

$sid = isset($_POST['sid']) ? $_POST['sid'] : (isset($_GET['sid']) ? $_GET['sid'] : '');
if (!sid_valid($sid)) { header('Location: index.php'); exit; }

$fixed = isset($_GET['fixed']);
if ($fixed) {
    // 修复：登录（权限变化）必须换号——session_regenerate_id(true) 的语义
    $newSid = bin2hex(random_bytes(16));
    sess_save($newSid, array('user' => 'admin', 'role' => 'admin', 'born' => date('c')));
    sess_drop($sid);                        // 旧 sid（可能被攻击者预置）立即作废
    setcookie('SESSID', $newSid, array('path' => '/', 'httponly' => true));
    header('Location: index.php?sid=' . $newSid . '&msg=fixed');
    exit;
}

// 漏洞版：直接把 admin 登录态挂到"进站时"的 sid 上
sess_save($sid, array('user' => 'admin', 'role' => 'admin', 'born' => date('c')));
setcookie('SESSID', $sid, array('path' => '/', 'httponly' => false));
header('Location: index.php?sid=' . $sid . '&msg=logedin');
