<?php
require_once 'config.php';

try {
    echo "=== Checking all voting centers ===\n\n";
    
    // Get all unique center names with voter counts
    $stmt = $pdo->query("
        SELECT 
            voting_center_name,
            COUNT(*) as total_voters,
            SUM(CASE WHEN status = 'voted' THEN 1 ELSE 0 END) as voted_count,
            SUM(CASE WHEN status IS NULL OR status = '' OR status = 'not_voted' OR status = 'not_arrived' THEN 1 ELSE 0 END) as not_voted_count
        FROM voters 
        GROUP BY voting_center_name 
        ORDER BY total_voters DESC
    ");
    
    $centers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($centers as $center) {
        echo "Center: '{$center['voting_center_name']}'\n";
        echo "  Total voters: {$center['total_voters']}\n";
        echo "  Voted: {$center['voted_count']}\n";
        echo "  Not voted: {$center['not_voted_count']}\n";
        echo "  Length: " . strlen($center['voting_center_name']) . " bytes\n\n";
    }
    
    // Check if there are voters with different status values
    echo "=== Status distribution ===\n";
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM voters 
        GROUP BY status 
        ORDER BY count DESC
    ");
    
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($statuses as $status) {
        $status_value = $status['status'] ?? 'NULL';
        echo "Status: '$status_value' - Count: {$status['count']}\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>