<?php
// ============================================================
// CTF Web Arena · 通用题目路由器（配合 nginx 单条 location 使用）
//   /<题目id>[/<子路径>]  →  challenges/<category>/<id>/ 下的 entry / 文件
// 规则：
//   * .php 文件 chdir 到其所在目录后 require（保持与常规 php-fpm 行为一致）
//   * 其余文件按静态资源直出；meta.json / writeup.md / _original/ / deploy/
//     及「包含 flag 明文但非预期泄露」的文件一律 403（运行时读取不受影响）
//   * 路径穿越由 realpath 前缀校验兜底
// 清单：同目录 _routes.json（由 tools/gen_challenges_json.py 生成）
// ============================================================

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

$ROUTES = json_decode(file_get_contents(__DIR__ . '/_routes.json'), true) ?: [];

function h404($msg = '页面不存在') {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>404 · CTF Web Arena</title><style>'
       . 'body{display:grid;place-items:center;min-height:100vh;margin:0;font-family:"Segoe UI","Microsoft YaHei",sans-serif;background:#0f172a;color:#e2e8f0}'
       . '.c{text-align:center;max-width:480px;padding:32px}h1{font-size:3rem;margin:0}.m{color:#94a3b8}'
       . 'a{color:#38bdf8}</style></head><body><div class="c">'
       . '<div style="font-size:3.4rem">🧭</div><h2>' . $msg . '</h2>'
       . '<p class="m">这个路径下没有题目资源。</p>'
       . '<p><a href="/">← 回关卡地图</a></p></div></body></html>';
    exit;
}

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = preg_split('/[?#]/', $uri, 2)[0];
$path = rawurldecode($path);
$parts = [];
foreach (explode('/', trim($path, '/')) as $seg) {
    if ($seg !== '' && $seg !== '.') {
        $parts[] = $seg;
    }
}
$cid = $parts[0] ?? '';
if (!preg_match('/^[a-z][a-z0-9]*\d+$/', $cid) || !isset($ROUTES[$cid])) {
    h404('未知题目');
}
$r = $ROUTES[$cid];
$base = realpath(__DIR__ . '/' . $r['dir']);
if ($base === false) {
    h404('题目目录缺失');
}
$deny = array_fill_keys($r['deny'] ?? [], true);
// 预期泄露的 VCS 元数据目录（recon3 的 .git 等）：按题白名单豁免硬拒绝
$leakDirs = $r['leak'] ?? [];

$rel = implode('/', array_slice($parts, 1));
if ($rel === '') {
    $rel = $r['entry'] ?: 'index.html';
    if ($rel === '') {
        h404('本题无页面入口（产物型题目请看 writeup 说明）');
    }
}

$file = realpath($base . '/' . $rel);
if ($file === false || (strpos($file, $base . DIRECTORY_SEPARATOR) !== 0 && $file !== $base)) {
    h404();
}
if (is_dir($file)) {
    foreach (['index.html', 'index.php'] as $idx) {
        if (is_file($file . '/' . $idx)) {
            $file = $file . '/' . $idx;
            $rel = rtrim($rel, '/') . '/' . $idx;
            break;
        }
    }
    if (is_dir($file)) {
        h404('目录下没有默认页');
    }
}
$relNorm = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($base) + 1)), '/');

// ---- 静态服务防线：答案与内部文件不可直出（PHP 运行时读取不受影响）----
$isLeakAllowed = false;
foreach ($leakDirs as $ld) {
    if ($ld !== '' && strpos($relNorm . '/', rtrim($ld, '/') . '/') === 0) {
        $isLeakAllowed = true;
        break;
    }
}
$isDenied = !$isLeakAllowed && (
    isset($deny[$relNorm])
    || preg_match('#(^|/)(_original|deploy|\.git|__pycache__|node_modules)/#', $relNorm)
    || preg_match('/^(meta\.json|writeup\.md|_routes\.json|_router\.php)$/i', $relNorm)
    || preg_match('/\.(sqlite3?|db|phar|ini|log|sql)(\.|$)/i', $relNorm)
    || preg_match('/(^|[^a-z0-9])flag([^a-z0-9]|$)/i', basename($relNorm))
);
if (!preg_match('/\.php$/i', $relNorm) && $isDenied) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><title>403</title></head>'
       . '<body style="font-family:sans-serif;background:#0f172a;color:#e2e8f0;display:grid;place-items:center;min-height:100vh">'
       . '<div style="text-align:center"><div style="font-size:3rem">🔒</div>'
       . '<h2>这个文件不能直接读取</h2><p>flag 要靠漏洞拿到，而不是靠猜路径 ：）</p>'
       . '<p><a href="/" style="color:#38bdf8">← 回关卡地图</a></p></div></body></html>';
    exit;
}

if (preg_match('/\.(php[2-7]?|phtml|pht)$/i', $relNorm)) {
    if (isset($deny[$relNorm])) {   // flag.php 之类的纯答案文件禁止执行
        http_response_code(403);
        exit('forbidden');
    }
    chdir(dirname($file));
    // 模拟 php-fpm 的 .user.ini 语义：fpm 只加载「FastCGI 主脚本目录」的 user_ini，
    // 而本题架构的主脚本是 _router.php，子目录里的 .user.ini 永远不会被加载——
    // upload4 的 auto_prepend_file 攻击因此失效。这里按目标脚本所在目录手工还原：
    // 仅支持 auto_prepend_file / auto_append_file，且路径限定在本题目录内。
    $udi = dirname($file) . '/.user.ini';
    if (is_file($udi)) {
        $uini = @parse_ini_file($udi, false, INI_SCANNER_RAW) ?: [];
        foreach (['auto_prepend_file', 'auto_append_file'] as $ukey) {
            if (!empty($uini[$ukey]) && is_string($uini[$ukey]) && $uini[$ukey] !== 'none') {
                $upf = realpath(dirname($file) . '/' . $uini[$ukey]);
                if ($upf && (strpos($upf, $base . DIRECTORY_SEPARATOR) === 0 || $upf === $base)) {
                    require $upf;
                }
            }
        }
    }
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['SCRIPT_NAME'] = '/' . $cid . '/' . $relNorm;
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    require $file;
    exit;
}

// ---- 静态直出 ----
$MIME = [
    'html' => 'text/html; charset=utf-8', 'htm' => 'text/html; charset=utf-8',
    'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
    'json' => 'application/json; charset=utf-8', 'xml' => 'application/xml; charset=utf-8',
    'dtd' => 'application/xml-dtd; charset=utf-8', 'txt' => 'text/plain; charset=utf-8',
    'md' => 'text/plain; charset=utf-8', 'png' => 'image/png', 'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon', 'zip' => 'application/zip', 'gz' => 'application/gzip',
    'tar' => 'application/x-tar', 'pdf' => 'application/pdf',
    'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
    'swp' => 'application/octet-stream', 'bak' => 'application/octet-stream',
    'env' => 'text/plain; charset=utf-8', 'csv' => 'text/csv; charset=utf-8',
];
$ext = strtolower(pathinfo($relNorm, PATHINFO_EXTENSION));
header('Content-Type: ' . ($MIME[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-cache');
$fp = fopen($file, 'rb');
fpassthru($fp);
exit;
