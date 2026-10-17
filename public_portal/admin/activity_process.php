<?php
session_start();
if (!isset($_SESSION['public_admin'])) {
    header("Location: login.php");
    exit;
}
require_once '../db_config.php';
$conn = getPublicDB();

$action = $_REQUEST['action'] ?? '';

if ($action === 'save') {
    $id = $_POST['id'] ?? null;
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $date = $_POST['activity_date'];
    $pub = (int)$_POST['is_published'];
    $main_img = $_POST['existing_image'] ?? '';

    if ($id) {
        $stmt = $conn->prepare("UPDATE public_activities SET title = ?, content = ?, activity_date = ?, is_published = ? WHERE id = ?");
        $stmt->bind_param("sssii", $title, $content, $date, $pub, $id);
        $stmt->execute();
        $activity_id = $id;
    } else {
        $stmt = $conn->prepare("INSERT INTO public_activities (title, content, activity_date, is_published, image_path) VALUES (?, ?, ?, ?, '')");
        $stmt->bind_param("sssi", $title, $content, $date, $pub);
        $stmt->execute();
        $activity_id = $conn->insert_id;
    }

    // Handle Multiple Images Upload
    if (isset($_FILES['activity_images']) && !empty($_FILES['activity_images']['name'][0])) {
        $upload_dir = '../uploads/activities/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $files = $_FILES['activity_images'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                $new_name = 'act_' . $activity_id . '_' . time() . '_' . $i . '.' . $ext;
                $dest = $upload_dir . $new_name;
                
                if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                    $db_path = 'uploads/activities/' . $new_name;
                    $stmt_img = $conn->prepare("INSERT INTO public_activity_images (activity_id, image_path) VALUES (?, ?)");
                    $stmt_img->bind_param("is", $activity_id, $db_path);
                    $stmt_img->execute();
                    
                    // Set first image as main if empty
                    if (empty($main_img)) {
                        $main_img = $db_path;
                        $conn->query("UPDATE public_activities SET image_path = '$main_img' WHERE id = $activity_id");
                    }
                }
            }
        }
    }

    header("Location: index.php?msg=success");
    exit;

} elseif ($action === 'delete_img') {
    $img_id = $_GET['img_id'] ?? 0;
    $activity_id = $_GET['id'] ?? 0;
    if ($img_id) {
        $stmt = $conn->prepare("SELECT image_path FROM public_activity_images WHERE id = ?");
        $stmt->bind_param("i", $img_id);
        $stmt->execute();
        $img = $stmt->get_result()->fetch_assoc();
        if ($img && file_exists('../' . $img['image_path'])) {
            @unlink('../' . $img['image_path']);
        }
        $conn->query("DELETE FROM public_activity_images WHERE id = $img_id");
        
        // Update main image if the deleted one was main
        $check_main = $conn->query("SELECT image_path FROM public_activities WHERE id = $activity_id");
        $main = $check_main->fetch_assoc();
        if ($main && $main['image_path'] === $img['image_path']) {
            $next = $conn->query("SELECT image_path FROM public_activity_images WHERE activity_id = $activity_id LIMIT 1");
            $new_main = ($next && $next->num_rows > 0) ? $next->fetch_assoc()['image_path'] : '';
            $conn->query("UPDATE public_activities SET image_path = '$new_main' WHERE id = $activity_id");
        }
    }
    header("Location: activity_edit.php?id=" . $activity_id);
    exit;

} elseif ($action === 'set_cover') {
    $img_id = $_GET['img_id'] ?? 0;
    $activity_id = $_GET['id'] ?? 0;
    if ($img_id && $activity_id) {
        $stmt = $conn->prepare("SELECT image_path FROM public_activity_images WHERE id = ? AND activity_id = ?");
        $stmt->bind_param("ii", $img_id, $activity_id);
        $stmt->execute();
        $img = $stmt->get_result()->fetch_assoc();
        if ($img) {
            $stmt = $conn->prepare("UPDATE public_activities SET image_path = ? WHERE id = ?");
            $stmt->bind_param("si", $img['image_path'], $activity_id);
            $stmt->execute();
        }
    }
    header("Location: activity_edit.php?id=" . $activity_id);
    exit;

} elseif ($action === 'move_up' || $action === 'move_down') {
    $img_id = $_GET['img_id'] ?? 0;
    $activity_id = $_GET['id'] ?? 0;
    if ($img_id && $activity_id) {
        $res = $conn->query("SELECT id FROM public_activity_images WHERE activity_id = $activity_id ORDER BY sort_order ASC, id ASC");
        $images = [];
        while($row = $res->fetch_assoc()) {
            $images[] = $row['id'];
        }
        
        $target_index = array_search($img_id, $images);
        if ($target_index !== false) {
            $swap_index = ($action === 'move_up') ? $target_index - 1 : $target_index + 1;
            if (isset($images[$swap_index])) {
                $temp = $images[$target_index];
                $images[$target_index] = $images[$swap_index];
                $images[$swap_index] = $temp;
                
                foreach ($images as $index => $id) {
                    $conn->query("UPDATE public_activity_images SET sort_order = $index WHERE id = $id");
                }
            }
        }
    }
    header("Location: activity_edit.php?id=" . $activity_id);
    exit;

} elseif ($action === 'delete') {
    $id = $_GET['id'] ?? 0;
    if ($id) {
        // Delete all associated images files
        $imgs = $conn->query("SELECT image_path FROM public_activity_images WHERE activity_id = $id");
        if ($imgs) {
            while($img = $imgs->fetch_assoc()) {
                if (file_exists('../' . $img['image_path'])) @unlink('../' . $img['image_path']);
            }
        }
        $conn->query("DELETE FROM public_activity_images WHERE activity_id = $id");

        // Delete main image if it was different (fallback)
        $stmt = $conn->prepare("SELECT image_path FROM public_activities WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if ($res && $res['image_path'] && file_exists('../' . $res['image_path'])) {
            @unlink('../' . $res['image_path']);
        }

        $stmt = $conn->prepare("DELETE FROM public_activities WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: index.php?msg=deleted");
    exit;
}
?>
