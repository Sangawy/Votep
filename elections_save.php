<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

require_once 'config.php';
$conn = getDB();

$action = $_POST['action'] ?? '';

if ($action === 'add_candidate') {
    $election_id = (int)$_POST['election_id'];
    $member_id = (int)$_POST['member_id'];
    $position_id = (int)$_POST['position_id'];

    if (!$election_id || !$member_id || !$position_id) {
        $_SESSION['msg'] = "تکایە هەموو زانیارییەکان پڕبکەرەوە.";
        $_SESSION['msg_type'] = "danger";
        header("Location: elections_candidates.php");
        exit;
    }

    // Check if member is already a candidate in this election
    $stmt = $conn->prepare("SELECT id FROM candidates WHERE election_id = ? AND member_id = ?");
    $stmt->bind_param("ii", $election_id, $member_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['msg'] = "ئەم ئەندامە پێشتر وەک بەربژێر تۆمارکراوە.";
        $_SESSION['msg_type'] = "warning";
    } else {
        $stmt = $conn->prepare("INSERT INTO candidates (election_id, position_id, member_id) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $election_id, $position_id, $member_id);
        if ($stmt->execute()) {
            $_SESSION['msg'] = "بەربژێر بە سەرکەوتوویی تۆمارکرا.";
            $_SESSION['msg_type'] = "success";
        } else {
            $_SESSION['msg'] = "هەڵە لە تۆمارکردن: " . $conn->error;
            $_SESSION['msg_type'] = "danger";
        }
    }
    header("Location: elections_candidates.php");
    exit;
}

if ($action === 'remove_candidate') {
    $candidate_id = (int)$_POST['candidate_id'];
    
    $stmt = $conn->prepare("DELETE FROM candidates WHERE id = ?");
    $stmt->bind_param("i", $candidate_id);
    if ($stmt->execute()) {
        $_SESSION['msg'] = "بەربژێر سڕایەوە.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "هەڵە لە سڕینەوە.";
        $_SESSION['msg_type'] = "danger";
    }
    header("Location: elections_candidates.php");
    exit;
}

header("Location: elections_dashboard.php");
?>
