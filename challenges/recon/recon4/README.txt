极客书店 官网项目
==================

部署方式（2014 年运维交接文档）：
  1. svn checkout https://192.168.10.8/svn/geekshop/trunk /var/www/geekshop
  2. 无需编译，直接使用
  3. 注意：不要删除 .svn 目录，否则下次 svn up 会很慢

目录结构：
  index.php        首页
  inc/config.php   配置
  README.txt       本文件

联系我们：dev@geekshop.local
