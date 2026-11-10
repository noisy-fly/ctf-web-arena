CREATE TABLE IF NOT EXISTS wallet (
    id INTEGER PRIMARY KEY,
    owner TEXT,
    balance REAL,
    init_balance REAL
);
INSERT OR IGNORE INTO wallet(id, owner, balance, init_balance) VALUES (1, 'you', 50, 50);

CREATE TABLE IF NOT EXISTS withdrawals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    amount REAL,
    ts TEXT
);
