CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    username TEXT,
    balance REAL
);
INSERT OR IGNORE INTO users(id, username, balance) VALUES (1, 'you', 100);

CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY,
    name TEXT,
    price REAL,
    stock INTEGER
);
INSERT OR IGNORE INTO products(id, name, price, stock) VALUES
 (1, 'FLAG 兑换券', 1337.0, 10),
 (2, '星云贴纸', 10.0, 99),
 (3, '拿铁咖啡券', 30.0, 99);

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER,
    qty REAL,
    unit_price REAL,
    total REAL,
    ts TEXT
);
