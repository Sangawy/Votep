<?php
require_once 'config.php';

try {
    // Read the SQL file
    $sql = file_get_contents('observers.sql');
    
    // Split by semicolons to get individual statements
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
            echo "✓ تەواو: " . substr($statement, 0, 50) . "...\n";
        }
    }
    
    echo "\n✅ خشتەی observers بە سەرکەوتوویی دروستکرا!\n";
    
    // Test the table
    $stmt = $pdo->query("SELECT COUNT(*) FROM observers");
    $count = $stmt->fetchColumn();
    echo "📊 ژمارەی چاودێرەکان: $count\n";
    
} catch(PDOException $e) {
    echo "❌ هەڵە: " . $e->getMessage() . "\n";
}
?>