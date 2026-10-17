<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$user_id = $_SESSION['user_id'];
$user_data = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$current_user = strtolower($user_data['username'] ?? '');
$is_super_admin = ($current_user === 'admin' || $current_user === 'sangawy' || ($user_data['role'] ?? '') === 'owner');

if (!$is_super_admin) {
    die("تەنها ئەدمین دەتوانێت ئەم کردارە ئەنجام بدات.");
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $permanent = isset($_GET['permanent']) && $_GET['permanent'] == '1';
    
    if ($permanent) {
        // Permanent delete: only Owner can do this
        if (($user_data['role'] ?? '') !== 'owner') {
            die("تەنها ئۆنەر دەتوانێت بە هەمیشەیی چالاکی بسڕێتەوە.");
        }

        // Delete folder and files
        $res = $conn->query("SELECT folder_path FROM activities WHERE id = $id");
        if ($row = $res->fetch_assoc()) {
            $folder = $row['folder_path'];
            if (!empty($folder) && is_dir($folder)) {
                $files = glob($folder . '/*');
                foreach($files as $file) { if(is_file($file)) unlink($file); }
                rmdir($folder);
            }
        }

        if ($conn->query("DELETE FROM activities WHERE id = $id")) {
            header('Location: archive_recycle_bin.php?success=permanent_deleted');
        }
    } else {
        // Soft delete: move to recycle bin
        if ($conn->query("UPDATE activities SET is_deleted = 1 WHERE id = $id")) {
            header('Location: archive_list.php?success=deleted');
        }
    }
}
?>
