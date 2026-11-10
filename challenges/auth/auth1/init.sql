CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    username TEXT UNIQUE,
    password TEXT,
    memo TEXT
);
INSERT OR IGNORE INTO users(id, username, password, memo) VALUES
 (1, 'admin',      'admin123', '运维主管(口令就是用户名+123,大家都这么好记)'),
 (2, 'superadmin', 'f7c3bc1d808e04732adf679965ccc34ca7ad1e31', '神秘账户');
