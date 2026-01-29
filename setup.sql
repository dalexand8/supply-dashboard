-- SQL to create tables (run this in MySQL to set up the database)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0
);
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL
);
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    category_id INT NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    location VARCHAR(50) NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    suggested TINYINT(1) DEFAULT 0,
    user_id INT NOT NULL,
    quantity INT DEFAULT 1 NOT NULL,
    status ENUM('Pending', 'Acknowledged', 'Ordered', 'Fulfilled', 'Backordered', 'Unavailable') DEFAULT 'Pending' NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category_id INT NULL,
    user_id INT NOT NULL,
    approved TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(50),
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
-- Insert initial categories
INSERT IGNORE INTO categories (name) VALUES
('Cleaning Supplies'), ('Beverages'), ('Snacks'), ('Office Essentials');
-- Insert initial items with categories
INSERT IGNORE INTO items (name, category_id) VALUES
('Clorox Wipes', 1), ('Paper Towels', 1), ('Toilet Paper', 1), ('Power Mop Pads', 1),
('Coffee', 2), ('Coffee Creamer', 2), ('Water', 2),
('Chips', 3);
