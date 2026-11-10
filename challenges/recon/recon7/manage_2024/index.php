<?php
declare(strict_types=1);
// 后台登录页（未接入鉴权，仅占位——考点不在爆破）
?>
<!DOCTYPE html>
<html lang="zh-CN"><head><meta charset="utf-8"><title>管理后台</title>
<style>body{background:#0d1117;color:#c9d1d9;font:15px/1.7 sans-serif;padding:60px;font-family:"Microsoft YaHei"}
.box{max-width:420px;margin:0 auto;border:1px solid #30363d;border-radius:8px;padding:30px;background:#161b22}
input{width:100%;padding:8px;margin:6px 0;background:#0b0f14;border:1px solid #30363d;color:#c9d1d9;border-radius:6px;box-sizing:border-box}
h2{color:#58a6ff}</style></head>
<body><div class="box">
<!-- TODO(运维): 备份文件不要放在 web 根目录！ /backup_20240601.zip 6月1日那份记得删 -->
<h2>极客书店 · 管理后台</h2>
<p>系统维护中，后台仅对内网开放（10.0.0.0/8）。</p>
<form><input placeholder="账号" disabled><input type="password" placeholder="密码" disabled></form>
</div></body></html>
