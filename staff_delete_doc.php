<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

require_once 'config.php';
$conn = getDB();

$id = (int)($_GET['id'] ?? 0);
$staff_id = (int)($_GET['staff_id'] ?? 0);

if ($id > 0) {
    // Get file path first
    $stmt = $conn->prepare("SELECT file_path FROM staff_documents WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($doc) {
        $path = $doc['file_path'];
        // Delete from DB
        $del = $conn->prepare("DELETE FROM staff_documents WHERE id = ?");
        $del->bind_param("i", $id);
        if ($del->execute()) {
            // Delete from disk
            if (file_exists($path)) @unlink($path);
            $_SESSION['msg'] = "بەڵگەنامەکە سڕایەوە.";
            $_SESSION['msg_type'] = "success";
        }
        $del->close();
    }
}

header("Location: staff_profile.php?id=$staff_id");
exit;
?>
