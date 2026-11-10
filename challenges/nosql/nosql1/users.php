<?php
/**
 * users.php — "用户集合"（文档型存储的教学模拟）
 * 原题用进程内 dict 模拟 MongoDB 集合；PHP 版用返回数组的文件承载同样的"文档"。
 * 键 = 用户名（文档 _id 的角色），值 = 文档（password/role 字段）。
 */
return array(
    'guest' => array('password' => 'guest12345', 'role' => 'guest'),
    'alice' => array('password' => 'aliceW0nder', 'role' => 'user'),
    'bob'   => array('password' => 'bobTheBu1lder', 'role' => 'user'),
    'admin' => array('password' => 'Adm1n_Sup3r$ecret_9f3e', 'role' => 'admin'),
);
