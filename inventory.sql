-- Inventory + POS database (MySQL / WAMP). Import via phpMyAdmin or: mysql -u root < inventory.sql
CREATE DATABASE IF NOT EXISTS inventory_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventory_pos;

DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS products;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  barcode VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'General',
  price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  qty INT NOT NULL DEFAULT 0,               -- signed on purpose: sale.php rolls back if it goes below 0
  low_stock_level INT NOT NULL DEFAULT 10,
  image VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_name (name),
  INDEX idx_category (category)
) ENGINE=InnoDB;                            -- InnoDB is required for transactions

CREATE TABLE sales (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_no VARCHAR(30) NOT NULL UNIQUE,
  subtotal DECIMAL(12,2) NOT NULL,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(12,2) NOT NULL,
  paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  change_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_method VARCHAR(20) NOT NULL DEFAULT 'cash',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE sale_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  product_name VARCHAR(150) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  qty INT NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO products (barcode, name, category, price, cost, qty, low_stock_level) VALUES
('1001', 'Basmati Rice 1kg',      'Grocery',   450.00, 380.00, 120, 20),
('1002', 'Sunflower Oil 1L',      'Grocery',   980.00, 860.00,  60, 15),
('1003', 'Milk Powder 400g',      'Dairy',     890.00, 780.00,  45, 15),
('1004', 'Fresh Milk 1L',         'Dairy',     420.00, 350.00,  12, 15),
('1005', 'White Bread',           'Bakery',    180.00, 140.00,  30, 10),
('1006', 'Eggs (10 pack)',        'Dairy',     520.00, 440.00,   8, 10),
('1007', 'Orange Juice 1L',       'Beverages', 650.00, 540.00,  40, 10),
('1008', 'Cola 1.5L',             'Beverages', 390.00, 310.00,  75, 20),
('1009', 'Mineral Water 500ml',   'Beverages', 100.00,  70.00, 200, 40),
('1010', 'Chocolate Bar',         'Snacks',    250.00, 190.00,  90, 20),
('1011', 'Potato Chips',          'Snacks',    300.00, 230.00,  55, 15),
('1012', 'Tea Leaves 200g',       'Grocery',   620.00, 520.00,   5, 10),
('1013', 'Laundry Soap',          'Household', 210.00, 160.00,  70, 15),
('1014', 'Dishwash Liquid 500ml', 'Household', 480.00, 390.00,  25, 10),
('1015', 'Toothpaste 120g',       'Personal',  360.00, 280.00,  48, 12);
