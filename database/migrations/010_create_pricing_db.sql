CREATE DATABASE IF NOT EXISTS pricing_db;
USE pricing_db;

CREATE TABLE IF NOT EXISTS service_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL
);

CREATE TABLE IF NOT EXISTS product_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL
);

CREATE TABLE IF NOT EXISTS discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_type VARCHAR(50) NOT NULL,
    target_id INT NOT NULL,
    discount_value DECIMAL(10, 2) NOT NULL,
    start_date TIMESTAMP,
    end_date TIMESTAMP
);

CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    discount DECIMAL(10, 2) NOT NULL,
    expiry_date TIMESTAMP
);

CREATE TABLE IF NOT EXISTS commission_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    percentage DECIMAL(5, 2) NOT NULL
);
