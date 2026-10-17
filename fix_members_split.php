<?php
require_once 'config.php';
require_once 'lineage_core.php';
$conn = getDB();

$conn->begin_transaction();
try {
    // 1. Fix Member 134 (سربست)
    $stmt1 = $conn->prepare("UPDATE members SET full_name = 'السيد سربست', first_name = 'سربست', normalized_name = ? WHERE id = 134");
    $norm134 = normalizeName('السيد سربست');
    $stmt1->bind_param("s", $norm134);
    $stmt1->execute();
    $stmt1->close();
    updateMemberNameFields($conn, 134);
    echo "✅ Member 134 updated to: السيد سربست\n";

    // 2. Check if هەڵمەت already exists under parent 131
    $checkHalmat = $conn->query("SELECT id FROM members WHERE parent_id = 131 AND (full_name LIKE '%هەلمەت%' OR full_name LIKE '%هەڵمەت%')");
    if ($checkHalmat->num_rows === 0) {
        $normHalmat = normalizeName('السيد هەڵمەت');
        $stmt2 = $conn->prepare("
            INSERT INTO members (parent_id, full_name, first_name, normalized_name, family_id, member_status, is_alive, member_rank, bnamala, village, profile_image, created_at)
            VALUES (131, 'السيد هەڵمەت', 'هەڵمەت', ?, 1, 'active', 1, 'ئەندام', 'بانیمۆرد', 'شاهۆ / بانیمۆرد', 'assets/image/user.png', NOW())
        ");
        $stmt2->bind_param("s", $normHalmat);
        $stmt2->execute();
        $newHalmatId = $conn->insert_id;
        $stmt2->close();
        updateMemberNameFields($conn, $newHalmatId);
        echo "✅ Created separate member for: السيد هەڵمەت (ID: $newHalmatId, Parent: 131)\n";
    } else {
        echo "ℹ️ Member هەڵمەت already exists\n";
    }

    // 3. Fix Member 195 (محمد)
    $norm195 = normalizeName('السيد محمد');
    $stmt3 = $conn->prepare("UPDATE members SET full_name = 'السيد محمد', first_name = 'محمد', normalized_name = ? WHERE id = 195");
    $stmt3->bind_param("s", $norm195);
    $stmt3->execute();
    $stmt3->close();
    updateMemberNameFields($conn, 195);
    echo "✅ Member 195 updated to: السيد محمد (Parent: 194)\n";

    $conn->commit();
    echo "🎉 Transaction committed successfully!\n";
} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
}
