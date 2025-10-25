<?php
require_once 'config.php';

try {
    echo "=== All Voting Centers in Database ===\n";
    $stmt = $pdo->query("SELECT voting_center_name, COUNT(*) as total_voters, SUM(CASE WHEN status = 'voted' THEN 1 ELSE 0 END) as voted_count FROM voters GROUP BY voting_center_name ORDER BY voting_center_name");
    $centers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($centers as $center) {
        echo "Center: '" . $center['voting_center_name'] . "' - Total: " . $center['total_voters'] . ", Voted: " . $center['voted_count'] . "\n";
    }
    
    echo "\n=== Testing Sample Center Names ===\n";
    $test_centers = ['Sample Center 1', 'مدرسة ديرين الأساسية', 'ديرين الأساسية'];
    
    foreach ($test_centers as $test_center) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM voters WHERE voting_center_name = ?");
        $stmt->execute([$test_center]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Test '" . $test_center . "': " . $result['count'] . " voters\n";
    }
    
    echo "\n=== Checking for unit column ===\n";
    $stmt = $pdo->query("DESCRIBE voters");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $has_unit = false;
    foreach ($columns as $column) {
        if ($column['Field'] == 'unit') {
            $has_unit = true;
            break;
        }
    }
    
    echo "Unit column exists: " . ($has_unit ? "YES" : "NO") . "\n";
    
    if ($has_unit) {
        echo "\n=== Sample unit data ===\n";
        $stmt = $pdo->query("SELECT full_name, voter_number, unit FROM voters LIMIT 5");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($samples as $sample) {
            echo "Name: " . $sample['full_name'] . ", Voter#: " . $sample['voter_number'] . ", Unit: " . ($sample['unit'] ?? 'NULL') . "\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>