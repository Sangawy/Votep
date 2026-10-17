<?php
session_start();
$_SESSION['user_id'] = 1;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lineage_core.php';

// Let's first clear any duplicate members created recently to start fresh
$conn = getDB();
$conn->query("DELETE FROM members WHERE id = 55");

$_POST['action'] = 'dummy';
require_once __DIR__ . '/lineage_engine.php';

// Prepare POST variables exactly like lineage_explorer.php does
$_POST['siblings'] = json_encode(["السید فرمان", "السید سلمان", "السید رازی"]);
$_POST['missingChain'] = json_encode([
    "السيد حسيب",
    "السيد رضا",
    "السيد محمد",
    "السيد محمود",
    "السيد محمد",
    "السيد محمود الكبير في(قريه باني مورد)"
]);
$_POST['bridgeId'] = 50; // السيد حسيب
$_POST['bestChainIndex'] = 0;
$_POST['familyId'] = 1;

// Run the save_and_link logic manually to inspect what calls findMemberContextual
echo "--- Starting save_and_link Simulation ---\n";

$chainParts = json_decode($_POST['missingChain'], true);
$bridgeId = (int)$_POST['bridgeId'];
$bestChainIndex = (int)$_POST['bestChainIndex'];
$familyId = (int)$_POST['familyId'];

$currentTopId = $bridgeId;
for ($i = $bestChainIndex + 1; $i < count($chainParts); $i++) {
    echo "Processing index $i: {$chainParts[$i]}\n";
    
    // Let's call findMemberContextual directly first to see if it finds the existing member
    $existing = findMemberContextual($conn, $chainParts, $i);
    if ($existing) {
        echo "  -> findMemberContextual FOUND member ID: {$existing['id']} (Name: {$existing['full_name']}, Parent ID: {$existing['parent_id']})\n";
    } else {
        echo "  -> findMemberContextual returned NULL!\n";
    }
    
    $ancestorId = getOrCreateContextualMember($conn, $chainParts, $i, 0, $familyId, 'inactive');
    echo "  -> getOrCreateContextualMember returned ID: $ancestorId\n";
    
    $currentTopId = $ancestorId;
}

?>
