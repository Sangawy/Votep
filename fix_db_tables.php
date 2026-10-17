<?php
require_once 'config.php';
$conn = getDB();

$sql = "CREATE TABLE IF NOT EXISTS congress_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    election_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (member_id, election_id)
)";

if ($conn->query($sql)) {
    echo "Table 'congress_members' ensured successfully.";
} else {
    echo "Error: " . $conn->error;
}
