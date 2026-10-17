<?php
require_once 'config.php';
$conn = getDB();

if ($conn) {
    echo "<h2>Updating Database Columns...</h2>";

    $columns = [
        'can_access_library' => "ALTER TABLE users ADD can_access_library TINYINT(1) DEFAULT 1",
        'can_access_docs' => "ALTER TABLE users ADD can_access_docs TINYINT(1) DEFAULT 0",
        'can_access_archive' => "ALTER TABLE users ADD can_access_archive TINYINT(1) DEFAULT 0",
        'can_access_office' => "ALTER TABLE users ADD can_access_office TINYINT(1) DEFAULT 0",
        'can_manage_users' => "ALTER TABLE users ADD can_manage_users TINYINT(1) DEFAULT 0"
    ];

    foreach ($columns as $col => $sql) {
        // Check if column exists
        $check = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
        if ($check && $check->num_rows == 0) {
            if ($conn->query($sql)) {
                echo "✅ Column '$col' added successfully.<br>";
            } else {
                echo "❌ Error adding '$col': " . $conn->error . "<br>";
            }
        } else {
            echo "ℹ️ Column '$col' already exists.<br>";
        }
    }

    // Now update admin permissions
    $updateAdmin = "UPDATE users SET 
                    can_access_library=1, 
                    can_access_docs=1, 
                    can_access_archive=1, 
                    can_access_office=1, 
                    can_manage_users=1 
                    WHERE username IN ('admin', 'sangawy') OR role='owner'";
    
    if ($conn->query($updateAdmin)) {
        echo "<h3 style='color:green;'>Admin permissions updated!</h3>";
    } else {
        echo "❌ Error updating admin permissions: " . $conn->error . "<br>";
    }

    echo "<br><a href='portal.php' style='padding:10px 20px; background:blue; color:white; text-decoration:none; border-radius:5px;'>GO TO PORTAL</a>";
} else {
    echo "Connection failed.";
}
?>
