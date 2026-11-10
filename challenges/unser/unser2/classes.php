<?php
// ============================================================
// unser2 · 门禁系统的三个业务类（index.php 与 gen_payload.php 共用）
// POP 链：DoorLog::__destruct → Badge::__toString → Engine::__call → file_get_contents
// ============================================================

// 入口类：注销时把"最后通行者"写进日志
class DoorLog {
    public $name = '访客';

    public function __destruct() {
        echo '[日志] 门禁关闭，最后通行者：' . $this->name . "\n";
    }
}

// 中转类：工牌信息要"变成字符串"打印
class Badge {
    public $template;

    public function __toString() {
        // 徽章模板引擎：调用 template 的 render() 得到文本
        return (string)$this->template->render();
    }
}

// 终点类：模板引擎支持注册"允许调用的方法"（白名单设计，但白名单可控……）
class Engine {
    public $allow = [];
    public $cmd = '';

    public function __call($name, $args) {
        if (isset($this->allow[$name])) {
            // 白名单内的方法允许执行"模板指令"
            return file_get_contents($this->cmd);
        }
        return "[Engine] 方法 {$name} 不在白名单";
    }
}
