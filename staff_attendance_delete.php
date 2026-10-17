<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$id = (int)$_GET['id'];

// Get details before deleting to update penalty
$res = $conn->query("SELECT * FROM staff_attendance WHERE id = $id");
$attendance = $res->fetch_assoc();

if ($attendance) {
    $staff_id = $attendance['staff_id'];
    $duration = $attendance['duration_days'];
    $type = $attendance['leave_type'];

    if ($conn->query("DELETE FROM staff_attendance WHERE id = $id")) {
        // If it was an absence, subtract penalty days
        if ($type === 'Absence') {
            $conn->query("UPDATE staff_members SET absence_penalty_days = GREATEST(0, absence_penalty_days - $duration) WHERE id = $staff_id");
            
            // Recalculate promotion due date
            $res_staff = $conn->query("SELECT m.last_promotion_date, m.absence_penalty_days, r.promotion_years 
                                      FROM staff_members m 
                                      JOIN staff_ranks r ON m.rank_id = r.id 
                                      WHERE m.id = $staff_id");
            $row = $res_staff->fetch_assoc();
            
            if ($row['last_promotion_date']) {
                $years = $row['promotion_years'];
                $penalty = $row['absence_penalty_days'];
                
                $base_date = date('Y-m-d', strtotime("+$years years", strtotime($row['last_promotion_date'])));
                $new_due_date = date('Y-m-d', strtotime("+$penalty days", strtotime($base_date)));
                
                $stmt_upd = $conn->prepare("UPDATE staff_members SET promotion_due_date = ? WHERE id = ?");
                $stmt_upd->bind_param("si", $new_due_date, $staff_id);
                $stmt_upd->execute();
            }
        }
        
        logAction($_SESSION['user_id'], 'Deleted Attendance', "Type: $type, Days: $duration", $staff_id);
        header('Location: staff_attendance.php?deleted=1');
    } else {
        echo "Error: " . $conn->error;
    }
} else {
    header('Location: staff_attendance.php');
}
?>
