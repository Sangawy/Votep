<?php
require_once 'config.php';
$conn = getDB();

$tables = ['lineage_certificates', 'committee_approvals', 'members'];
foreach (['lineage_certificates', 'committee_approvals'] as $t) {
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    if ($res && $res->num_rows > 0) {
        $c134 = $conn->query("SELECT * FROM $t WHERE member_id = 134")->num_rows;
        $c195 = $conn->query("SELECT * FROM $t WHERE member_id = 195")->num_rows;
        echo "Table $t: member 134 has $c134 rows, member 195 has $c195 rows\n";
    }
}
