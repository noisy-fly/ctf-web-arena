CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    username TEXT UNIQUE,
    password TEXT,
    role TEXT
);
INSERT OR IGNORE INTO users(id, username, password, role) VALUES
 (1, 'boss',   'B0ss#2024$', 'admin'),
 (2, 'player', 'player123',  'user'),
 (3, 'anna',   'anna123',    'user');

CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ts TEXT,
    actor TEXT,
    action TEXT
);
INSERT OR IGNORE INTO audit_log(ts, actor, action) VALUES
 ('2026-10-20 09:15:02', 'boss', '发放季度奖励'),
 ('2026-10-21 14:33:47', 'boss', '重置 anna 的二次验证');
