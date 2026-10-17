<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Auto-fix for status column
    $checkCols = $conn->query("SHOW COLUMNS FROM activities LIKE 'status'");
    if ($checkCols->num_rows == 0) {
        $conn->query("ALTER TABLE activities ADD COLUMN status TINYINT DEFAULT 0 AFTER is_completed");
        $conn->query("UPDATE activities SET status = 1 WHERE is_completed = 1");
    }

    $name = $conn->real_escape_string($_POST['name']);
    $activity_date = $conn->real_escape_string($_POST['activity_date']);
    $location = $conn->real_escape_string($_POST['location']);
    $implemented_by = $conn->real_escape_string($_POST['implemented_by']);
    $participants_count = (int)$_POST['participants_count'];
    $summary = $conn->real_escape_string($_POST['summary'] ?? '');
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    $activity_id = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : null;
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
    
    if ($activity_id) {
        // Update existing activity
        $sql = "UPDATE activities SET 
                name = '$name', 
                activity_date = '$activity_date', 
                location = '$location', 
                implemented_by = '$implemented_by', 
                participants_count = $participants_count,
                summary = '$summary',
                notes = '$notes',
                status = $status,
                is_completed = " . ($status == 1 ? 1 : 0) . "
                WHERE id = $activity_id";
        
        if ($conn->query($sql)) {
            // Handle additional file uploads if any
            if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
                $row = $conn->query("SELECT folder_path FROM activities WHERE id = $activity_id")->fetch_assoc();
                $folder_rel_path = $row['folder_path'];
                
                if (!is_dir($folder_rel_path)) mkdir($folder_rel_path, 0777, true);
                
                foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                        $orig_name = $_FILES['files']['name'][$key];
                        $ext = pathinfo($orig_name, PATHINFO_EXTENSION);
                        $safe_file_name = time() . '_' . rand(100,999) . '.' . $ext;
                        move_uploaded_file($tmp_name, $folder_rel_path . '/' . $safe_file_name);
                    }
                }
            }
            header('Location: archive_list.php?success=edit');
        } else {
            echo "Error: " . $conn->error;
        }
    } else {
        // Create new activity
        $safe_name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
        $folder_rel_path = 'assets/archives/' . time() . '_' . $safe_name;
        
        if (!is_dir($folder_rel_path)) mkdir($folder_rel_path, 0777, true);
        
        if (isset($_FILES['files'])) {
            foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                    $orig_name = $_FILES['files']['name'][$key];
                    $ext = pathinfo($orig_name, PATHINFO_EXTENSION);
                    $safe_file_name = time() . '_' . $key . '.' . $ext;
                    move_uploaded_file($tmp_name, $folder_rel_path . '/' . $safe_file_name);
                }
            }
        }
        
        $sql = "INSERT INTO activities (name, activity_date, location, implemented_by, participants_count, summary, notes, folder_path, status, is_completed) 
                VALUES ('$name', '$activity_date', '$location', '$implemented_by', $participants_count, '$summary', '$notes', '$folder_rel_path', $status, " . ($status == 1 ? 1 : 0) . ")";
        
        if ($conn->query($sql)) {
            header('Location: archive_list.php?success=1');
        } else {
            echo "Error: " . $conn->error;
        }
    }
} else {
    header('Location: archive_new.php');
}
?>
