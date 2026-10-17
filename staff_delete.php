<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    // 1. Get old data for audit logging
    $stmt = $conn->prepare("SELECT * FROM staff_members WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $old_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($old_data) {
        // 2. Delete the record (Cascading will handle related tables if foreign keys are set correctly)
        // But let's be safe and manually handle files if needed
        
        $stmt_del = $conn->prepare("DELETE FROM staff_members WHERE id = ?");
        $stmt_del->bind_param("i", $id);
        
        if ($stmt_del->execute()) {
            // Log Audit
            include_once 'includes/audit_logger.php';
            logAudit($conn, $_SESSION['user_id'], 'DELETE', 'staff_members', $id, $old_data, []);
            
            // Optionally: Delete the archive folder if it's within the uploads directory
            $folder = $old_data['archive_folder'];
            if (!empty($folder) && is_dir($folder) && strpos($folder, 'assets/uploads') !== false) {
                // Warning: Recursevely deleting a folder is risky. Let's just leave it or handle it carefully.
                // For now, let's just delete the database record. 
                // If you want to delete files too, you'd use a recursive function.
            }
            
            header("Location: staff_list.php?deleted=1");
            exit;
        } else {
            die("Error deleting record: " . $stmt_del->error);
        }
    }
}

header("Location: staff_list.php");
exit;
?>
