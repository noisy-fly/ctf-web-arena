<?php
foreach (glob(__DIR__ . '/app.db*') as $f) { @unlink($f); }
header('Location: index.php');
