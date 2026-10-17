<?php
require_once 'config.php';
$conn = getDB();

if (!$conn) {
    die("Database connection failed");
}

$queries = [
    "CREATE TABLE IF NOT EXISTS documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doc_number VARCHAR(100) NOT NULL,
        doc_date DATE NOT NULL,
        subject VARCHAR(255) NOT NULL,
        type ENUM('Inbound', 'Outbound', 'Secret', 'Internal') NOT NULL,
        sender_receiver VARCHAR(150) NOT NULL,
        file_path VARCHAR(255) NULL,
        status ENUM('Pending Review', 'Seen/Reviewed') DEFAULT 'Pending Review',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    "CREATE TABLE IF NOT EXISTS document_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parent_id INT NOT NULL,
        child_id INT NOT NULL,
        relation_type VARCHAR(100) NULL,
        FOREIGN KEY (parent_id) REFERENCES documents(id) ON DELETE CASCADE,
        FOREIGN KEY (child_id) REFERENCES documents(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($queries as $query) {
    if ($conn->query($query)) {
        echo "Query executed successfully.\n";
    } else {
        echo "Error executing query: " . $conn->error . "\n";
    }
}

echo "Done.";
?>
