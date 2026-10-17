<?php
require_once 'config.php';
$conn = getDB();

// Function to calculate due date with penalty
function calculateDueDate($last_date, $years, $penalty_days = 0) {
    if (!$last_date) return null;
    $base_date = date('Y-m-d', strtotime("+$years years", strtotime($last_date)));
    if ($penalty_days > 0) {
        return date('Y-m-d', strtotime("+$penalty_days days", strtotime($base_date)));
    }
    return $base_date;
}

// 1. Update all members' due dates based on their current rank rules and penalty
$res = $conn->query("SELECT m.id, m.last_promotion_date, m.absence_penalty_days, r.promotion_years 
                    FROM staff_members m 
                    JOIN staff_ranks r ON m.rank_id = r.id");

while ($row = $res->fetch_assoc()) {
    $due_date = calculateDueDate($row['last_promotion_date'], $row['promotion_years'], $row['absence_penalty_days']);
    if ($due_date) {
        $stmt = $conn->prepare("UPDATE staff_members SET promotion_due_date = ? WHERE id = ?");
        $stmt->bind_param("si", $due_date, $row['id']);
        $stmt->execute();
    }
}


echo "All promotion due dates have been recalculated based on the new rules.";
?>
