<?php
/**
 * config.php — flag 与口令配置
 * 部署时可通过环境变量 FLAG 注入；未注入时使用内嵌默认值。
 */
function get_flag()
{
    $f = getenv('FLAG');
    return ($f !== false && $f !== '') ? $f : 'flag{weak2_75f665a2}';
}

// 服务端真实口令（不向玩家展示）
$REAL_PASSWORD = 'S3cr3t_Unkn0wn_Pa55w0rd_9f2e';
