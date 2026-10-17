<?php
require_once 'config.php';
$conn = getDB();

$sql = "CREATE TABLE IF NOT EXISTS staff_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT,
    category ENUM('Weapon', 'Vehicle', 'Property', 'Tech', 'Other') DEFAULT 'Other',
    item_name VARCHAR(255) NOT NULL,
    serial_number VARCHAR(100),
    physical_condition VARCHAR(100),
    received_date DATE,
    order_number VARCHAR(100),
    status ENUM('Held', 'Returned') DEFAULT 'Held',
    return_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff_members(id) ON DELETE CASCADE
)";

if ($conn->query($sql)) {
    echo "Table 'staff_assets' created successfully.";
} else {
    echo "Error creating table: " . $conn->error;
}
?>
