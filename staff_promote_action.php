<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

require_once 'config.php';
$conn = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid ID");

// 1. Get current staff info and rank priority
$stmt = $conn->prepare("SELECT m.rank_id, r.priority, m.full_name FROM staff_members m JOIN staff_ranks r ON m.rank_id = r.id WHERE m.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$staff) die("Staff not found");

$current_priority = $staff['priority'];

// 2. Find the NEXT rank (smallest priority higher than current)
$stmt = $conn->prepare("SELECT id, rank_name, promotion_years FROM staff_ranks WHERE priority > ? ORDER BY priority ASC LIMIT 1");
$stmt->bind_param("i", $current_priority);
$stmt->execute();
$next_rank = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$next_rank) {
    $_SESSION['msg'] = "ئەم کارمەندە لە بەرزترین پلەدایە و پلەی باڵاتری نییە.";
    $_SESSION['msg_type'] = "warning";
    header("Location: staff_promotions.php");
    exit;
}

$next_rank_id = $next_rank['id'];
$next_years = $next_rank['promotion_years'];
$today = date('Y-m-d');
$next_due_date = date('Y-m-d', strtotime("+$next_years years", strtotime($today)));

// 3. Update the staff member
$stmt = $conn->prepare("UPDATE staff_members SET rank_id = ?, last_promotion_date = ?, promotion_due_date = ? WHERE id = ?");
$stmt->bind_param("issi", $next_rank_id, $today, $next_due_date, $id);

if ($stmt->execute()) {
    $_SESSION['msg'] = "پیرۆزە! پلەی (" . $staff['full_name'] . ") بەرزکرایەوە بۆ (" . $next_rank['rank_name'] . ")";
    $_SESSION['msg_type'] = "success";
    
    // Optional: Record this in service history as a promotion
    $msg = "بەرزکردنەوەی پلە بۆ " . $next_rank['rank_name'];
    $stmt_his = $conn->prepare("INSERT INTO staff_service_history (staff_id, unit_name, position, order_number) VALUES (?, ?, ?, ?)");
    $unit_name = "وەزارەتی پێشمەرگە";
    $order = "فەرمانی گشتی / " . date('Y');
    $stmt_his->bind_param("isss", $id, $unit_name, $msg, $order);
    $stmt_his->execute();
    $stmt_his->close();
} else {
    $_SESSION['msg'] = "هەڵەیەک ڕوویدا لە کاتی نوێکردنەوەی پلە.";
    $_SESSION['msg_type'] = "error";
}

header("Location: staff_promotions.php");
exit;
?>
