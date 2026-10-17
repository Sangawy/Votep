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
$is_owner = (($user_data['role'] ?? '') === 'owner');

if (!$is_owner) {
    die("تەنها ئۆنەر دەتوانێت ئەم کردارە ئەنجام بدات.");
}

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($conn->query("UPDATE activities SET is_deleted = 0 WHERE id = $id")) {
        header('Location: archive_recycle_bin.php?success=restored');
    } else {
        echo "هەڵەیەک ڕوویدا لە کاتی گەڕاندنەوە.";
    }
}
?>
