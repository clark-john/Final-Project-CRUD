<?php
$conn = mysqli_connect("localhost", "root", "", "laundry_shop");
if (!$conn) { die("Connection failed: " . mysqli_connect_error()); }
mysqli_set_charset($conn, "utf8mb4");

// Public price list, maintained by the admin.
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS service_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL UNIQUE,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    unit VARCHAR(30) NOT NULL DEFAULT 'kg'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "INSERT IGNORE INTO service_prices (service_name, price, unit) VALUES
    ('Wash and Fold', 50.00, 'kg'), ('Wash Only', 35.00, 'kg')");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    max_weight DECIMAL(6,2) NOT NULL DEFAULT 8.00,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "INSERT IGNORE INTO promotions (name, description, price, max_weight) VALUES
    ('Regular Promo', '38 mins wash · 1 wash / 2 rinse · 40 mins drying · Free fold, detergent & fabcon', 180.00, 8.00),
    ('Premium Promo', '48 mins wash · 1 wash / 3 rinse · 50 mins drying · Free fold, detergent & fabcon', 200.00, 8.00)");

$serviceLabelColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'service_label'");
if ($serviceLabelColumn && mysqli_num_rows($serviceLabelColumn) === 0) {
    mysqli_query($conn, "ALTER TABLE orders ADD COLUMN service_label VARCHAR(100) NULL DEFAULT NULL");
}
?>
