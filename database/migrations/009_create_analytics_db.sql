CREATE DATABASE IF NOT EXISTS analytics_db;
USE analytics_db;

CREATE TABLE IF NOT EXISTS sales_fact (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS appointment_fact (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barber_id INT NOT NULL,
    service_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customer_dimension (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_spent DECIMAL(10, 2) DEFAULT 0.00
);

CREATE TABLE IF NOT EXISTS product_dimension (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    name VARCHAR(255) NOT NULL
);
