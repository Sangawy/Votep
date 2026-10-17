<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $leave_type = $_POST['leave_type'];
    $status = $_POST['status'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $order_number = $_POST['order_number'];
    $reason = $_POST['reason'];
    $notes = $_POST['notes'];

    // Calculate duration
    $d1 = new DateTime($start_date);
    $d2 = new DateTime($end_date);
    $duration_days = $d1->diff($d2)->days + 1;

    $query = "UPDATE staff_attendance SET 
              leave_type = ?, 
              status = ?, 
              start_date = ?, 
              end_date = ?, 
              duration_days = ?, 
              order_number = ?, 
              reason = ?, 
              notes = ? 
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssisssi", $leave_type, $status, $start_date, $end_date, $duration_days, $order_number, $reason, $notes, $id);

    if ($stmt->execute()) {
        header('Location: staff_attendance.php?success=1');
    } else {
        die("Error updating record: " . $stmt->error);
    }
}
?>
