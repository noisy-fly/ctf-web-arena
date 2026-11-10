-- sql6 宽字节注入 初始化数据（MySQL，GBK 库，可安全重放）
-- 建库语句（若集成时用 docker-entrypoint-initdb.d 自动执行，可去掉首两行的注释）：
-- CREATE DATABASE IF NOT EXISTS sql6db CHARACTER SET gbk COLLATE gbk_chinese_ci;
-- USE sql6db;

CREATE TABLE IF NOT EXISTS users (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64),
    memo     VARCHAR(255)
) CHARACTER SET gbk;
CREATE TABLE IF NOT EXISTS secret_vault (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    flag VARCHAR(128)
) CHARACTER SET gbk;
INSERT INTO users(username, memo) VALUES
 ('admin', '系统管理员'),
 ('zhangwei', '市场部'),
 ('lisan', '客服部');
INSERT INTO secret_vault(flag) VALUES ('flag{sql6_6d78d87f}');
