<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

require_once 'config.php';
$conn = getDB();

$action = $_REQUEST['action'] ?? '';
$staff_id = (int)($_REQUEST['staff_id'] ?? 0);

if ($action == 'add') {
    $category = $_POST['category'];
    $item_name = trim($_POST['item_name']);
    $serial_number = trim($_POST['serial_number']);
    $physical_condition = trim($_POST['physical_condition']);
    $received_date = $_POST['received_date'];
    $order_number = trim($_POST['order_number']);

    $stmt = $conn->prepare("INSERT INTO staff_assets (staff_id, category, item_name, serial_number, physical_condition, received_date, order_number, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Held')");
    $stmt->bind_param("issssss", $staff_id, $category, $item_name, $serial_number, $physical_condition, $received_date, $order_number);
    
    if ($stmt->execute()) {
        logAudit($conn, $_SESSION['user_id'], 'INSERT', 'staff_assets', $stmt->insert_id, null, $_POST);
        $_SESSION['msg'] = "زممە بە سەرکەوتوویی تۆمار کرا.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "هەڵەیەک ڕوویدا لە کاتی پاشەکەوتکردن.";
        $_SESSION['msg_type'] = "error";
    }
} 
elseif ($action == 'return') {
    $asset_id = (int)($_GET['id'] ?? 0);
    $today = date('Y-m-d');

    $stmt = $conn->prepare("UPDATE staff_assets SET status = 'Returned', return_date = ? WHERE id = ?");
    $stmt->bind_param("si", $today, $asset_id);
    
    // Fetch old asset data
    $res_old = $conn->query("SELECT * FROM staff_assets WHERE id = $asset_id");
    $old_asset = $res_old->fetch_assoc();

    if ($stmt->execute()) {
        logAudit($conn, $_SESSION['user_id'], 'UPDATE (RETURN)', 'staff_assets', $asset_id, $old_asset, ['status' => 'Returned', 'return_date' => $today]);
        $_SESSION['msg'] = "کەلوپەلەکە وەک گەڕاوە (ئەستۆپاکی) نیشان درا.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "هەڵەیەک ڕوویدا.";
        $_SESSION['msg_type'] = "error";
    }
}
elseif ($action == 'edit') {
    $asset_id = (int)$_POST['asset_id'];
    $category = $_POST['category'];
    $item_name = trim($_POST['item_name']);
    $serial_number = trim($_POST['serial_number']);
    $physical_condition = trim($_POST['physical_condition']);
    $received_date = $_POST['received_date'];
    $order_number = trim($_POST['order_number']);
    $status = $_POST['status'];
    $return_date = ($status == 'Returned') ? $_POST['return_date'] : null;

    // Fetch old asset data
    $res_old = $conn->query("SELECT * FROM staff_assets WHERE id = $asset_id");
    $old_asset = $res_old->fetch_assoc();

    $stmt = $conn->prepare("UPDATE staff_assets SET category=?, item_name=?, serial_number=?, physical_condition=?, received_date=?, order_number=?, status=?, return_date=? WHERE id=?");
    $stmt->bind_param("ssssssssi", $category, $item_name, $serial_number, $physical_condition, $received_date, $order_number, $status, $return_date, $asset_id);

    if ($stmt->execute()) {
        logAudit($conn, $_SESSION['user_id'], 'UPDATE', 'staff_assets', $asset_id, $old_asset, $_POST);
        $_SESSION['msg'] = "گۆڕانکارییەکان بە سەرکەوتوویی پاشەکەوت کران.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "هەڵەیەک ڕوویدا لە کاتی نوێکردنەوە.";
        $_SESSION['msg_type'] = "error";
    }
}
elseif ($action == 'delete') {
    $asset_id = (int)($_GET['id'] ?? 0);

    // Fetch old asset data
    $res_old = $conn->query("SELECT * FROM staff_assets WHERE id = $asset_id");
    $old_asset = $res_old->fetch_assoc();

    $stmt = $conn->prepare("DELETE FROM staff_assets WHERE id = ?");
    $stmt->bind_param("i", $asset_id);
    
    if ($stmt->execute()) {
        logAudit($conn, $_SESSION['user_id'], 'DELETE', 'staff_assets', $asset_id, $old_asset, null);
        $_SESSION['msg'] = "زممە بە سەرکەوتوویی سڕایەوە.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "هەڵەیەک ڕوویدا لە کاتی سڕینەوە.";
        $_SESSION['msg_type'] = "error";
    }
}

header("Location: staff_profile.php?id=$staff_id");
exit;
?>
