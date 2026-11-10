<?php
// 线上配置（第 9 次安全整改后）：敏感信息已全部改为占位符
declare(strict_types=1);
define('APP_VER', 'v0.7-beta');
define('DB_HOST', 'db-internal');
define('DB_USER', getenv('SHOP_DB_USER') ?: '(from env)');
define('DB_PASS', getenv('SHOP_DB_PASS') ?: '(from env)');
