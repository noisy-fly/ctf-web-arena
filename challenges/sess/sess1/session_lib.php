<?php
// sess1 · 自研文件会话的公共库（index.php 与 admin_login.php 共用）
define('SESS1_DATA_DIR', __DIR__ . '/data');
if (!is_dir(SESS1_DATA_DIR)) { @mkdir(SESS1_DATA_DIR, 0777, true); }

function sid_valid($s) { return is_string($s) && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $s); }

function sess_load($sid) {
    $f = SESS1_DATA_DIR . '/sess_' . md5($sid) . '.json';
    if (!is_file($f)) { return null; }
    $d = @json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : null;
}
function sess_save($sid, array $data) {
    file_put_contents(SESS1_DATA_DIR . '/sess_' . md5($sid) . '.json', json_encode($data));
}
function sess_drop($sid) { @unlink(SESS1_DATA_DIR . '/sess_' . md5($sid) . '.json'); }
