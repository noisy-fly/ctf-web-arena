<?php
/**
 * config.php — flag 配置
 * 部署时可通过环境变量 FLAG 注入；未注入时使用内嵌默认值。
 */
function get_flag()
{
    $f = getenv('FLAG');
    return ($f !== false && $f !== '') ? $f : 'flag{weak3_f6f901e5}';
}

// 员工编号表：0 号是历史遗留的"保留号"，本不该享受权限
$STAFF_IDS = array(0, 1001, 1002, 1003);
