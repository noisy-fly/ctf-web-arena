<?php
// 教学环境一键重置：删除数据库，下一次访问会按 init.sql 重建
$files = glob(__DIR__ . '/app.db*') ?: [];
foreach ($files as $f) { @unlink($f); }
header('Location: index.php');
