<?php
declare(strict_types=1);
// ============================================================
// graphql2 · login.php —— 会员登录（实验账号 bob / bob123）
// 成功：会话记下会员 id 并 302 回 index.php；失败：401。
// ============================================================
session_start();
require __DIR__ . '/members.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: index.php');
    exit;
}

$user = (isset($_POST['user']) && is_string($_POST['user'])) ? $_POST['user'] : '';
$pass = (isset($_POST['pass']) && is_string($_POST['pass'])) ? $_POST['pass'] : '';

foreach (gql2_members() as $m) {
    if (hash_equals((string)$m['username'], $user) && hash_equals((string)$m['password'], $pass)) {
        session_regenerate_id(true);
        $_SESSION['gql2_mid'] = (string)$m['id'];
        header('Location: index.php');
        exit;
    }
}

http_response_code(401);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>graphql2 · 登录失败</title>
<style>
body{margin:0;font-family:"Segoe UI",system-ui,sans-serif;background:#0f172a;color:#e2e8f0;line-height:1.75}
.box{max-width:560px;margin:60px auto;padding:24px;background:#1e293b;border:1px solid #334155;border-radius:12px}
a{color:#38bdf8}
</style></head>
<body><div class="box">
<h2 style="color:#f87171;margin-top:0">登录失败</h2>
<p>用户名或口令不对。实验账号：<code>bob / bob123</code>。</p>
<p><a href="index.php">返回会员中心</a></p>
</div></body></html>
