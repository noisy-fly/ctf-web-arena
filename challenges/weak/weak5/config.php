<?php
/**
 * config.php — flag 与密钥配置
 * 部署时可通过环境变量 FLAG 注入；未注入时使用内嵌默认值。
 */
function get_flag()
{
    $f = getenv('FLAG');
    return ($f !== false && $f !== '') ? $f : 'flag{weak5_f2993dd2}';
}

// 服务端随机 API 密钥（不向玩家展示）
$SECRET_TOKEN = 'e9d1f4a7c2b80563f7a1d4e9c0b3a628';
