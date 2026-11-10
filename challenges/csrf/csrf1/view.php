<?php
declare(strict_types=1);
require __DIR__ . '/init.php';
current_user();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM attacks WHERE id = ?');
$st->execute([$id]);
$a = $st->fetch();
if (!$a) { http_response_code(404); exit('攻击页不存在'); }

page_head('CSRF 1/3 · easy', '攻击页预览 #' . $id, '★☆☆☆☆', '', [], []);
?>
<div class="card">
  <h2>攻击页 #<?php echo $id; ?>（渲染效果 = 受害者看到/加载的内容）</h2>
  <?php if (isset($_GET['src'])): ?>
    <pre><?php echo h((string)$a['html']); ?></pre>
  <?php else: ?>
    <div class="demo"><?php echo $a['html']; /* 故意原样渲染:这就是攻击者的页面 */ ?></div>
  <?php endif; ?>
  <p>
    <a class="btn" href="sim.php?attack=<?php echo $id; ?>">▶ 运行模拟受害者（cathy 访问本页）</a>
    <a class="muted" href="view.php?id=<?php echo $id; ?>&amp;src=1">查看源码</a> |
    <a class="muted" href="index.php">返回</a>
  </p>
</div>
<?php page_foot('csrf1');
