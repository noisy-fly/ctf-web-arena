<?php
// ============================================================
// unser4 · 头像工坊的三个类（index.php 与 gen_phar.php 共用）
// POP 链（与 unser2 不同的触发器）：__destruct → __toString → __get → sink
// ============================================================

// 入口类：请求结束时把未读公告拼进日志
class Notice {
    public $msg = '暂无公告';

    public function __destruct() {
        echo '[Notice] ' . $this->msg . "\n";   // 字符串拼接 → $msg 为对象时触发 __toString
    }
}

// 中转类：小部件渲染（template 属性其实并不存在）
class Widget {
    public $plugin;

    public function __toString() {
        return (string)$this->plugin->template;  // 访问不存在属性 → 触发 __get
    }
}

// 终点类：插件钩子（属性决定读哪个文件——又是"可控白名单"）
class Plugin {
    public $hook = [];

    public function __get($name) {
        if (isset($this->hook[$name])) {
            $f = $this->hook[$name];
            if ($f !== '' && $f[0] !== '/') {
                $f = __DIR__ . '/' . $f;   // 相对路径一律相对本文件目录，与进程 cwd 无关
            }
            return file_get_contents($f);   // sink
        }
        return '';
    }
}
