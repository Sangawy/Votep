<?php
require_once 'config.php';
$conn = getDB();

if ($conn) {
    echo "<h2>Resetting Passwords & Permissions...</h2>";

    // 1. Ensure columns exist
    $cols = [
        'can_access_library' => "ALTER TABLE users ADD can_access_library TINYINT(1) DEFAULT 1",
        'can_access_docs' => "ALTER TABLE users ADD can_access_docs TINYINT(1) DEFAULT 0",
        'can_access_archive' => "ALTER TABLE users ADD can_access_archive TINYINT(1) DEFAULT 0",
        'can_access_office' => "ALTER TABLE users ADD can_access_office TINYINT(1) DEFAULT 0",
        'can_manage_users' => "ALTER TABLE users ADD can_manage_users TINYINT(1) DEFAULT 0"
    ];

    foreach ($cols as $col => $sql) {
        $check = $conn->query("SHOW COLUMNS FROM users LIKE '$col'");
        if ($check && $check->num_rows == 0) $conn->query($sql);
    }

    // 2. Reset / Create Admin
    $new_pass = password_hash('123456', PASSWORD_DEFAULT);
    
    // Check if sangawy exists
    $res = $conn->query("SELECT id FROM users WHERE username='sangawy'");
    if ($res->num_rows > 0) {
        $conn->query("UPDATE users SET password='$new_pass', role='owner', can_manage_users=1, can_access_library=1, can_access_docs=1, can_access_archive=1, can_access_office=1 WHERE username='sangawy'");
        echo "✅ User 'sangawy' has been reset to Owner with password: <b>123456</b><br>";
    } else {
        // Create if not exists
        $conn->query("INSERT INTO users (username, password, role, full_name, can_manage_users, can_access_library) VALUES ('admin', '$new_pass', 'owner', 'Admin System', 1, 1)");
        echo "✅ New 'admin' user created with password: <b>123456</b><br>";
    }

    echo "<br><b style='color:red;'>⚠️ تکایە پاش لۆگین، وشەی نهێنی بگۆڕە بۆ پاراستنی سیستم.</b><br>";
    echo "<br><a href='index.php' style='padding:10px 20px; background:maroon; color:white; text-decoration:none; border-radius:5px;'>GO TO LOGIN</a>";
} else {
    echo "Connection failed.";
}
?>
