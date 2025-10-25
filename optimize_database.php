<?php
// Database Optimization Script
// This script adds indexes and optimizes the voters table for better performance

require_once 'config.php';

try {
    echo "<h2>Database Optimization Script</h2>\n";
    echo "<p>Starting database optimization...</p>\n";
    
    // Add indexes for better performance
    $optimizations = [
        // Index for voter_number search
        "CREATE INDEX IF NOT EXISTS idx_voter_number ON voters(voter_number)",
        
        // Index for voting_center_name search
        "CREATE INDEX IF NOT EXISTS idx_voting_center ON voters(voting_center_name)",
        
        // Composite index for common search patterns
        "CREATE INDEX IF NOT EXISTS idx_voter_center ON voters(voter_number, voting_center_name)",
        
        // Index for status queries
        "CREATE INDEX IF NOT EXISTS idx_status ON voters(status)",
        
        // Index for scanned_at for recent scans
        "CREATE INDEX IF NOT EXISTS idx_scanned_at ON voters(scanned_at)",
        
        // Composite index for statistics queries
        "CREATE INDEX IF NOT EXISTS idx_stats ON voters(voting_center_name, status)",
        
        // Index for full_name search (if needed)
        "CREATE INDEX IF NOT EXISTS idx_full_name ON voters(full_name)",
    ];
    
    foreach ($optimizations as $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color: green;'>✅ " . htmlspecialchars($sql) . "</p>\n";
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠️ " . htmlspecialchars($sql) . " - " . $e->getMessage() . "</p>\n";
        }
    }
    
    // Optimize table
    try {
        $pdo->exec("OPTIMIZE TABLE voters");
        echo "<p style='color: green;'>✅ Table optimized successfully</p>\n";
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠️ Table optimization: " . $e->getMessage() . "</p>\n";
    }
    
    // Show current indexes
    echo "<h3>Current Indexes on voters table:</h3>\n";
    $stmt = $pdo->query("SHOW INDEX FROM voters");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Key Name</th><th>Column Name</th><th>Index Type</th><th>Unique</th></tr>\n";
    
    foreach ($indexes as $index) {
        $unique = $index['Non_unique'] == 0 ? 'Yes' : 'No';
        echo "<tr>";
        echo "<td>" . htmlspecialchars($index['Key_name']) . "</td>";
        echo "<td>" . htmlspecialchars($index['Column_name']) . "</td>";
        echo "<td>" . htmlspecialchars($index['Index_type']) . "</td>";
        echo "<td>" . $unique . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Show table statistics
    echo "<h3>Table Statistics:</h3>\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total_voters FROM voters");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT COUNT(*) as scanned_voters FROM voters WHERE status = 'voted'");
    $scanned = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Total Voters:</strong> " . number_format($total['total_voters']) . "</p>\n";
    echo "<p><strong>Scanned Voters:</strong> " . number_format($scanned['scanned_voters']) . "</p>\n";
    
    echo "<p style='color: green; font-weight: bold;'>✅ Database optimization completed successfully!</p>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database connection error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1000px;
    margin: 20px auto;
    padding: 20px;
    background: #f5f5f5;
}
table {
    background: white;
    margin: 10px 0;
}
th {
    background: #007bff;
    color: white;
    padding: 10px;
}
td {
    padding: 8px;
    border: 1px solid #ddd;
}
</style>