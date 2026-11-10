CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    subscribed INTEGER DEFAULT 0,
    token TEXT UNIQUE
);
INSERT OR IGNORE INTO users(username, subscribed, token) VALUES
 ('cathy', 0, 'cathy-victim-token-8k2m4x7q');

CREATE TABLE IF NOT EXISTS attacks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    html TEXT,
    ts TEXT
);
