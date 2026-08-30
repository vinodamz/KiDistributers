-- ============================================================================
-- Ki Distributers — MySQL schema (utf8mb4 / InnoDB).
-- Same stack as Little Graduates: PHP 8.1+ / MySQL / PIN login / modules.
-- Apply once on a fresh database. Re-applying is destructive (DROP first).
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

DROP TABLE IF EXISTS notification_preferences;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS auth_tokens;
DROP TABLE IF EXISTS task_items;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS deliveries;
DROP TABLE IF EXISTS order_lines;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS stock_moves;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS app_settings;

CREATE TABLE app_settings (
    setting_key   VARCHAR(60) NOT NULL,
    setting_value TEXT        NULL,
    updated_at    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO app_settings (setting_key, setting_value) VALUES
    ('app_name',           'Ki Distributers'),
    ('app_short_name',     'KD'),
    ('email_from_name',    'Ki Distributers'),
    ('email_from_address', 'no-reply@example.com');

CREATE TABLE users (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(120) NOT NULL,
    pin_hash   VARCHAR(255) NOT NULL,
    role       ENUM('admin','sales','warehouse','accounts') NOT NULL DEFAULT 'sales',
    modules    SET('products','customers','orders','deliveries','stock','invoices','expenses','tasks')
               NOT NULL DEFAULT 'orders,deliveries,customers',
    active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_users_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE auth_tokens (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NOT NULL,
    selector       CHAR(24) NOT NULL,
    validator_hash CHAR(64) NOT NULL,
    expires_at     DATETIME NOT NULL,
    last_used_at   DATETIME NULL,
    user_agent     VARCHAR(190) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_selector (selector),
    KEY idx_user (user_id),
    CONSTRAINT fk_auth_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    category     ENUM('orders','stock','invoices','tasks','system') NOT NULL DEFAULT 'system',
    event_type   VARCHAR(40)  NOT NULL,
    title        VARCHAR(200) NOT NULL,
    body         TEXT         NULL,
    link         VARCHAR(255) NULL,
    read_at      DATETIME      NULL,
    email_status ENUM('pending','sent','skipped','failed') NOT NULL DEFAULT 'skipped',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_read (user_id, read_at),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notification_preferences (
    user_id          INT UNSIGNED NOT NULL,
    email_enabled    TINYINT(1) NOT NULL DEFAULT 0,
    orders_enabled    TINYINT(1) NOT NULL DEFAULT 1,
    stock_enabled     TINYINT(1) NOT NULL DEFAULT 1,
    invoices_enabled  TINYINT(1) NOT NULL DEFAULT 1,
    tasks_enabled      TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_np_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sku           VARCHAR(40) NOT NULL,
    name          VARCHAR(160) NOT NULL,
    category      VARCHAR(80) NOT NULL DEFAULT 'General',
    unit          VARCHAR(20) NOT NULL DEFAULT 'pcs',
    pack_size     VARCHAR(40) NULL,
    mrp           DECIMAL(12,2) NOT NULL DEFAULT 0,
    trade_price   DECIMAL(12,2) NOT NULL DEFAULT 0,
    gst_pct       DECIMAL(5,2) NOT NULL DEFAULT 0,
    qty_on_hand   DECIMAL(12,3) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(12,3) NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    notes         TEXT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sku (sku),
    KEY idx_prod_cat (category),
    KEY idx_prod_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_moves (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id  INT UNSIGNED NOT NULL,
    qty_delta   DECIMAL(12,3) NOT NULL,
    reason      ENUM('purchase','sale','adjustment','return','opening') NOT NULL DEFAULT 'adjustment',
    ref_type    VARCHAR(30) NULL,
    ref_id      INT UNSIGNED NULL,
    note        VARCHAR(255) NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sm_prod (product_id, created_at),
    CONSTRAINT fk_sm_prod FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_sm_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customers (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(160) NOT NULL,
    type          ENUM('retailer','wholesaler','modern_trade','other') NOT NULL DEFAULT 'retailer',
    phone         VARCHAR(20) NULL,
    gstin         VARCHAR(20) NULL,
    area          VARCHAR(80) NULL,
    route         VARCHAR(80) NULL,
    address       VARCHAR(255) NULL,
    credit_limit  DECIMAL(12,2) NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    notes         TEXT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cust_area (area),
    KEY idx_cust_route (route),
    KEY idx_cust_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id  INT UNSIGNED NOT NULL,
    taken_by     INT UNSIGNED NULL,
    order_date   DATE NOT NULL,
    status       ENUM('draft','confirmed','packed','delivered','cancelled') NOT NULL DEFAULT 'draft',
    notes        TEXT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ord_date (order_date),
    KEY idx_ord_status (status),
    KEY idx_ord_cust (customer_id),
    CONSTRAINT fk_ord_cust FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_ord_user FOREIGN KEY (taken_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_lines (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id    INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NOT NULL,
    qty         DECIMAL(12,3) NOT NULL,
    unit_price  DECIMAL(12,2) NOT NULL,
    gst_pct     DECIMAL(5,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_ol_ord (order_id),
    CONSTRAINT fk_ol_ord FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_ol_prod FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE deliveries (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id       INT UNSIGNED NOT NULL,
    assigned_to   INT UNSIGNED NULL,
    scheduled_date DATE NOT NULL,
    delivered_at  DATETIME NULL,
    status        ENUM('pending','out','done','failed') NOT NULL DEFAULT 'pending',
    notes         VARCHAR(255) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_del_date (scheduled_date, status),
    CONSTRAINT fk_del_ord FOREIGN KEY (order_id) REFERENCES orders(id),
    CONSTRAINT fk_del_user FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoices (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_no   VARCHAR(40) NOT NULL,
    order_id     INT UNSIGNED NULL,
    customer_id  INT UNSIGNED NOT NULL,
    invoice_date DATE NOT NULL,
    amount       DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax          DECIMAL(12,2) NOT NULL DEFAULT 0,
    status       ENUM('open','partial','paid','cancelled') NOT NULL DEFAULT 'open',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inv_no (invoice_no),
    KEY idx_inv_cust (customer_id),
    KEY idx_inv_status (status),
    CONSTRAINT fk_inv_ord FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_inv_cust FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id  INT UNSIGNED NOT NULL,
    paid_on     DATE NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    mode        ENUM('cash','upi','neft','cheque','other') NOT NULL DEFAULT 'cash',
    note        VARCHAR(160) NULL,
    received_by INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pay_inv (invoice_id),
    CONSTRAINT fk_pay_inv FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_pay_user FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE expenses (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    expense_date DATE NOT NULL,
    category     VARCHAR(80) NOT NULL DEFAULT 'Travel',
    amount       DECIMAL(12,2) NOT NULL,
    merchant     VARCHAR(160) NULL,
    notes        TEXT NULL,
    status       ENUM('draft','submitted','approved','rejected','paid') NOT NULL DEFAULT 'submitted',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_exp_user (user_id, expense_date),
    CONSTRAINT fk_exp_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE task_items (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title       VARCHAR(200) NOT NULL,
    due_date    DATE NULL,
    status       ENUM('todo','in_progress','done') NOT NULL DEFAULT 'todo',
    assigned_to INT UNSIGNED NULL,
    notes       TEXT NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_task_due (due_date, status),
    CONSTRAINT fk_task_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_task_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
