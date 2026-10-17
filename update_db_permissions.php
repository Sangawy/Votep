<?php
require_once 'config.php';
$conn = getDB();

if ($conn) {
    $queries = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS can_access_library TINYINT(1) DEFAULT 1",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS can_access_docs TINYINT(1) DEFAULT 0",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS can_access_archive TINYINT(1) DEFAULT 0",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS can_access_office TINYINT(1) DEFAULT 0",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS can_manage_users TINYINT(1) DEFAULT 0"
    ];

    foreach ($queries as $q) {
        if ($conn->query($q)) {
            echo "Query success: $q <br>";
        } else {
            echo "Query failed: " . $conn->error . "<br>";
        }
    }
    
    // Set admin/owner to have all permissions
    $conn->query("UPDATE users SET can_access_library=1, can_access_docs=1, can_access_archive=1, can_access_office=1, can_manage_users=1 WHERE role='owner' OR username='admin'");
    
    echo "Database updated successfully.";
} else {
    echo "Connection failed.";
}
?>
