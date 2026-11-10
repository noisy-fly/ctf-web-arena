-- CTF Web Arena 题目数据库初始化（容器首启执行一次）
-- 账号：web / webpass_2026（仅最小权限；PHP 题源码中明文出现属教学设计）

CREATE DATABASE IF NOT EXISTS sqlilab1 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE DATABASE IF NOT EXISTS sqlilab2 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE DATABASE IF NOT EXISTS sqlilab3 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE DATABASE IF NOT EXISTS xssboard DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- sql1：联合注入（回显 id/username/password，flag 在 fl4g 表）
USE sqlilab1;
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(64),
  password VARCHAR(128)
);
INSERT INTO users (username, password) VALUES
  ('admin',  'admin123'),
  ('alice',  'alice2026'),
  ('bob',    'bob@123456'),
  ('charlie','charlie!2026'),
  ('dave',   'd4ve_pass');
CREATE TABLE fl4g (id INT PRIMARY KEY, flag VARCHAR(128));
INSERT INTO fl4g VALUES (1, 'flag{sql1_5f3a91c2}');

-- sql2：报错注入（只回显 存在/不存在 + mysqli_error；flag 在 fl4g 表）
USE sqlilab2;
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(64),
  nickname VARCHAR(64)
);
INSERT INTO users (username, nickname) VALUES
  ('admin', '站长'),
  ('test',  '测试号');
CREATE TABLE fl4g (id INT PRIMARY KEY, flag VARCHAR(128));
INSERT INTO fl4g VALUES (1, 'flag{sql2_8d47e0b6}');

-- sql3：布尔盲注（只回显 成功/失败；flag 在 fl4g 表）
USE sqlilab3;
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(64)
);
INSERT INTO users (username) VALUES ('admin'), ('guest');
CREATE TABLE fl4g (id INT PRIMARY KEY, flag VARCHAR(128));
INSERT INTO fl4g VALUES (1, 'flag{sql3_b2c689f1}');

-- xss2：存储型 XSS 留言板（poster 记 ctf_sess 会话，供 Victim Bot 判定归属）
USE xssboard;
CREATE TABLE msgs (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  poster CHAR(64) NOT NULL,
  content TEXT NOT NULL,
  ts DATETIME NOT NULL
);

-- 最小权限：web 账号只读 SQLi 库；留言板可读可写（不能删，防破坏）
CREATE USER IF NOT EXISTS 'web'@'localhost' IDENTIFIED BY 'webpass_2026';
CREATE USER IF NOT EXISTS 'web'@'127.0.0.1' IDENTIFIED BY 'webpass_2026';
GRANT SELECT ON sqlilab1.* TO 'web'@'localhost', 'web'@'127.0.0.1';
GRANT SELECT ON sqlilab2.* TO 'web'@'localhost', 'web'@'127.0.0.1';
GRANT SELECT ON sqlilab3.* TO 'web'@'localhost', 'web'@'127.0.0.1';
GRANT SELECT, INSERT ON xssboard.* TO 'web'@'localhost', 'web'@'127.0.0.1';
-- sql6 宽字节注入：独立库，应用首访自建表（需建表权限）
CREATE DATABASE IF NOT EXISTS sql6db DEFAULT CHARACTER SET gbk COLLATE gbk_chinese_ci;
GRANT ALL PRIVILEGES ON sql6db.* TO 'web'@'localhost', 'web'@'127.0.0.1';
FLUSH PRIVILEGES;
