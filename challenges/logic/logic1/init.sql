CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    username TEXT UNIQUE,
    password TEXT,
    addr TEXT
);
INSERT OR IGNORE INTO users(id, username, password, addr) VALUES
 (1, 'alice', 'alice123', '北京市海淀区中关村大街 1 号'),
 (2, 'bob',   'bob@2024!', '上海市浦东新区世纪大道 100 号'),
 (3, 'carol', 'carol456',  '广州市天河区体育西路 8 号');

CREATE TABLE IF NOT EXISTS orders (
    id INTEGER PRIMARY KEY,
    user_id INTEGER,
    item TEXT,
    amount REAL,
    status TEXT,
    note TEXT
);
INSERT OR IGNORE INTO orders(id, user_id, item, amount, status, note) VALUES
 (1001, 1, '无线机械键盘', 329.00, '已发货', '放快递柜，谢谢'),
 (1002, 2, '限量版手办', 1299.00, '已签收', '本人不在时交给公司前台，代收暗号：flag{logic1_75890e75}'),
 (1003, 1, '显示器支架', 199.00, '已发货', ''),
 (1004, 3, '蓝牙耳机', 499.00, '已发货', '周六上午送达'),
 (1005, 2, '游戏点卡 x1', 88.00, '已完成', '');
