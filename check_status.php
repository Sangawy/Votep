<?php
require_once 'config.php';

try {
    echo "=== Status distribution for 'مدرسة ديرين الأساسية' ===\n";
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM voters WHERE voting_center_name = ? GROUP BY status");
    $stmt->execute(['مدرسة ديرين الأساسية']);
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($statuses as $status) {
        echo "Status: '" . ($status['status'] ?? 'NULL') . "' - Count: " . $status['count'] . "\n";
    }
    
    echo "\n=== Sample records ===\n";
    $stmt = $pdo->prepare("SELECT full_name, voter_number, status FROM voters WHERE voting_center_name = ? LIMIT 5");
    $stmt->execute(['مدرسة ديرين الأساسية']);
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($samples as $sample) {
        echo "Name: " . $sample['full_name'] . ", Status: '" . ($sample['status'] ?? 'NULL') . "'\n";
    }
    
    echo "\n=== Testing query conditions ===\n";
    
    // Test voted query
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM voters WHERE voting_center_name = ? AND status = 'voted'");
    $stmt->execute(['مدرسة ديرين الأساسية']);
    $voted_count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Voted count: " . $voted_count['count'] . "\n";
    
    // Test not voted query
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM voters WHERE voting_center_name = ? AND (status = 'not_voted' OR status = '' OR status = 'not_arrived')");
    $stmt->execute(['مدرسة ديرين الأساسية']);
    $not_voted_count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Not voted count: " . $not_voted_count['count'] . "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>