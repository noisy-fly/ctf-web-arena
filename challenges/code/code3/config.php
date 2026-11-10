<?php
/**
 * config.php — flag 与盐配置
 * 部署时可通过环境变量 FLAG 注入；未注入时使用内嵌默认值。
 */
function get_flag()
{
    $f = getenv('FLAG');
    return ($f !== false && $f !== '') ? $f : 'flag{code3_c73b8c98}';
}

// 服务端随机盐（不向玩家展示；md5('salt39923604') = 0e373143265133075354348401919903）
$SALT = 'salt39923604';
