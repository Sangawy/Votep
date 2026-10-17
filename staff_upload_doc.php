<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

require_once 'config.php';
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['doc_file'])) {
    $staff_id = (int)$_POST['staff_id'];
    $doc_title = trim($_POST['doc_title'] ?: 'بەڵگەنامە');
    
    $stmt = $conn->prepare("SELECT archive_folder FROM staff_members WHERE id = ?");
    $stmt->bind_param("i", $staff_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $staff = $res->fetch_assoc();
    $stmt->close();

    if ($staff) {
        $settings = $conn->query("SELECT archive_base_dir FROM settings WHERE id = 1")->fetch_assoc();
        $archive_base = $settings['archive_base_dir'] ?? 'D:/Archive/';

        // Get detailed info for path calculation
        $info_stmt = $conn->prepare("SELECT m.full_name, r.rank_name, u.unit_name FROM staff_members m JOIN staff_ranks r ON m.rank_id = r.id JOIN staff_units u ON m.unit_id = u.id WHERE m.id = ?");
        $info_stmt->bind_param("i", $staff_id);
        $info_stmt->execute();
        $details = $info_stmt->get_result()->fetch_assoc();
        $info_stmt->close();

        // Calculate Hierarchical Path
        $clean_unit = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $details['unit_name']);
        $clean_rank = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $details['rank_name']);
        $clean_name = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $details['full_name']);
        
        $folder = rtrim($archive_base, '/\\') . DIRECTORY_SEPARATOR . $clean_unit . DIRECTORY_SEPARATOR . $clean_rank . DIRECTORY_SEPARATOR . $clean_name;

        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
            // Update the DB so next time it knows the exact path
            $upd = $conn->prepare("UPDATE staff_members SET archive_folder = ? WHERE id = ?");
            $upd->bind_param("si", $folder, $staff_id);
            $upd->execute();
            $upd->close();
        }

        $file = $_FILES['doc_file'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
            
            if (!in_array($ext, $allowed)) {
                $_SESSION['msg'] = "فایلی لەم شێوەیە ڕێگەپێنەدراوە. تەنها وێنە و PDF.";
                $_SESSION['msg_type'] = "error";
                header("Location: staff_profile.php?id=$staff_id");
                exit;
            }

            // Save as Title_Timestamp.ext
            $clean_title = preg_replace('/[\\/*?:"<>|]/', '', $doc_title);
            $new_name = $clean_title . "_" . time() . "." . $ext;
            $final_path = $folder . DIRECTORY_SEPARATOR . $new_name;
            
            if (move_uploaded_file($file['tmp_name'], $final_path)) {
                // Record in DB
                $stmt_db = $conn->prepare("INSERT INTO staff_documents (staff_id, doc_title, file_path, file_type) VALUES (?, ?, ?, ?)");
                $stmt_db->bind_param("isss", $staff_id, $doc_title, $final_path, $ext);
                $stmt_db->execute();
                $stmt_db->close();

                $_SESSION['msg'] = "بەڵگەنامەکە بە ناوی ($doc_title) پاشەکەوت کرا.";
                $_SESSION['msg_type'] = "success";
            } else {
                $_SESSION['msg'] = "کێشەیەک لە پاشەکەوتکردنی فایلەکەدا هەبوو.";
                $_SESSION['msg_type'] = "error";
            }
        }
    }
    
    header("Location: staff_profile.php?id=$staff_id");
    exit;
}
?>
