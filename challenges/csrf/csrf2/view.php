<?php
declare(strict_types=1);
require __DIR__ . '/init.php';
current_user();

$name = (string)($_GET['name'] ?? '');
$st = db()->prepare('SELECT * FROM attacks WHERE name = ?');
$st->execute([$name]);
$a = $st->fetch();
if (!$a) { http_response_code(404); exit('攻击页不存在'); }

page_head('CSRF 2/3 · medium', '攻击页预览：' . $name, '★★☆☆☆', '', [], []);
?>
<div class="card">
  <h2>攻击页 “<?php echo h($name); ?>”（URL 即受害者访问的地址）</h2>
  <?php if (isset($_GET['src'])): ?>
    <pre><?php echo h((string)$a['html']); ?></pre>
  <?php else: ?>
    <div class="demo"><?php echo $a['html']; ?></div>
  <?php endif; ?>
  <p>
    <a class="btn" href="sim.php?name=<?php echo urlencode($name); ?>">▶ 运行模拟受害者（dave 访问本页）</a>
    <a class="muted" href="view.php?name=<?php echo urlencode($name); ?>&amp;src=1">查看源码</a> |
    <a class="muted" href="index.php">返回</a>
  </p>
</div>
<?php page_foot('csrf2');
