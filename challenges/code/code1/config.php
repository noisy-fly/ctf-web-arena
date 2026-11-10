<?php
/**
 * config.php — flag 配置
 * 部署时可通过环境变量 FLAG 注入；未注入时使用内嵌默认值。
 */
function get_flag()
{
    $f = getenv('FLAG');
    return ($f !== false && $f !== '') ? $f : 'flag{code1_4f66ef1a}';
}
