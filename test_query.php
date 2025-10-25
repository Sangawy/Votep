<?php
require_once 'config.php';

try {
    echo "=== Testing updated query ===\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM voters WHERE voting_center_name = ? AND (status = 'not_voted' OR status = '' OR status = 'not_arrived' OR status IS NULL)");
    $stmt->execute(['مدرسة ديرين الأساسية']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Not voted count: " . $result['count'] . "\n";
    
    echo "\n=== Sample not voted records ===\n";
    $stmt = $pdo->prepare("SELECT full_name, voter_number, status, unit FROM voters WHERE voting_center_name = ? AND (status = 'not_voted' OR status = '' OR status = 'not_arrived' OR status IS NULL) LIMIT 3");
    $stmt->execute(['مدرسة ديرين الأساسية']);
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($samples as $sample) {
        echo "Name: " . $sample['full_name'] . ", Status: '" . ($sample['status'] ?? 'NULL') . "', Unit: '" . ($sample['unit'] ?? 'NULL') . "'\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>