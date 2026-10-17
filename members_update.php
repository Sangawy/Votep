<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config.php';
    require_once 'lineage_core.php';
    $conn = getDB();

    $id = (int)$_POST['id'];
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;
    $member_code = trim($_POST['member_code'] ?? '');
    
    // 4-Level Names - ensure single core names without duplicate chains
    $raw_first = trim($_POST['first_name'] ?? '');
    $raw_father = trim($_POST['father_name'] ?? '');
    $raw_grand = trim($_POST['grandfather_name'] ?? '');
    $raw_great_grand = trim($_POST['great_grandfather_name'] ?? '');

    $first_name = extractFirstName($raw_first);
    if (empty($first_name)) $first_name = $raw_first;

    $father_name = extractFirstName($raw_father);
    if (empty($father_name)) $father_name = $raw_father;

    $grandfather_name = extractFirstName($raw_grand);
    if (empty($grandfather_name)) $grandfather_name = $raw_grand;

    $great_grandfather_name = extractFirstName($raw_great_grand);
    if (empty($great_grandfather_name)) $great_grandfather_name = $raw_great_grand;

    // If linked to a parent in the tree, align ancestor names directly from parent hierarchy
    if ($parent_id > 0) {
        $p_stmt = $conn->prepare("
            SELECT m.full_name as p1_full,
                   p2.full_name as p2_full,
                   p3.full_name as p3_full
            FROM members m
            LEFT JOIN members p2 ON m.parent_id = p2.id
            LEFT JOIN members p3 ON p2.parent_id = p3.id
            WHERE m.id = ?
        ");
        $p_stmt->bind_param("i", $parent_id);
        $p_stmt->execute();
        if ($p_row = $p_stmt->get_result()->fetch_assoc()) {
            if (!empty($p_row['p1_full'])) $father_name = extractFirstName($p_row['p1_full']);
            if (!empty($p_row['p2_full'])) $grandfather_name = extractFirstName($p_row['p2_full']);
            if (!empty($p_row['p3_full'])) $great_grandfather_name = extractFirstName($p_row['p3_full']);
        }
        $p_stmt->close();
    }

    // Preserve existing title (e.g. 'السيد ملا', 'السید', 'شێخ', 'السيد')
    $title = "السيد";
    $prev_stmt = $conn->prepare("SELECT full_name, parent_id FROM members WHERE id = ?");
    $prev_stmt->bind_param("i", $id);
    $prev_stmt->execute();
    $prev_row = $prev_stmt->get_result()->fetch_assoc();
    $prev_stmt->close();

    if (!empty($prev_row['full_name'])) {
        if (preg_match('/^(السيد\s+ملا|السید\s+مەلا|السيد\s+شێخ|السید\s+شیخ|الشیخ|الشيخ|السيد|السید|سید|سەید|ملا|مەلا|شێخ|شیخ)/u', $prev_row['full_name'], $tm)) {
            $title = $tm[1];
        }
    }

    // Check if member is part of lineage tree (has parent or has children in the tree)
    $has_children = false;
    $c_check = $conn->query("SELECT id FROM members WHERE parent_id = $id LIMIT 1");
    if ($c_check && $c_check->num_rows > 0) {
        $has_children = true;
    }

    if ($parent_id > 0 || $has_children) {
        // Lineage tree node: full_name is strictly the person's node title and first name
        $full_name = trim($title . ' ' . $first_name);
    } else {
        // Standalone member: full_name can be the 4 generations
        $full_name = implode(' ', array_filter([$first_name, $father_name, $grandfather_name, $great_grandfather_name]));
    }

    $nickname = trim($_POST['nickname'] ?? '');
    $member_rank = trim($_POST['member_rank']);
    $member_post = trim($_POST['member_post']);

    $family_id = (int)($_POST['family_id'] ?? 0);
    if ($family_id <= 0 && !empty($_POST['family_name_input'])) {
        $fname = trim($_POST['family_name_input']);
        $fstmt = $conn->prepare("SELECT id FROM families WHERE family_name = ?");
        $fstmt->bind_param("s", $fname);
        $fstmt->execute();
        $fres = $fstmt->get_result();
        if ($row = $fres->fetch_assoc()) {
            $family_id = (int)$row['id'];
        }
        $fstmt->close();
    }

    if ($family_id <= 0) {
        $_SESSION['msg'] = "مەرجی سەرەکی دەستکاریکردن دیاریکردنی بنەماڵەیە! ناتوانیت بنەماڵە بە بەتاڵی جێبهێڵیت.";
        $_SESSION['msg_type'] = "error";
        header("Location: members_edit.php?id=" . $id);
        exit;
    }
    
    $membership_date = fixDate($_POST['membership_date'] ?? null);
    $profession = trim($_POST['profession']);
    $education = trim($_POST['education']);
    $address = trim($_POST['address']);
    $birth_place = trim($_POST['birth_place']);
    $birth_date = fixDate($_POST['birth_date'] ?? null);
    $languages = trim($_POST['languages']);
    $blood_group = $_POST['blood_group'];
    $phone = trim($_POST['phone']);
    $id_card_number = trim($_POST['id_card_number'] ?? '');
    $security_code = trim($_POST['security_code'] ?? '');

    // Updated Normalization: Keep spaces, only normalize characters
    function normalizeForSearch($str) {
        $map = ['ێ'=>'ی','ي'=>'ی','ى'=>'ی','ك'=>'ک','ة'=>'ه','ە'=>'ه','ڕ'=>'ر','ڵ'=>'ل','ۆ'=>'و','أ'=>'ا','إ'=>'ا','آ'=>'ا'];
        $titles = ['السيد', 'السید', 'سید', 'ملا', 'مەلا', 'شێخ', 'شیخ'];
        $str = str_replace($titles, '', $str);
        return str_replace(array_keys($map), array_values($map), $str);
    }
    $normalized_name = normalizeForSearch($full_name);

    $stmt = $conn->prepare("UPDATE members SET 
        is_official_member=1, member_code=?, membership_date=?, profession=?, full_name=?, normalized_name=?, nickname=?, 
        member_rank=?, member_post=?, family_id=?, education=?, address=?, birth_place=?, 
        birth_date=?, languages=?, blood_group=?, phone=?, id_card_number=?, security_code=?, 
        profile_image=?, parent_id=?, 
        first_name=?, father_name=?, grandfather_name=?, great_grandfather_name=? 
        WHERE id=?");
    
    $imgstmt = $conn->prepare("SELECT profile_image FROM members WHERE id = ?");
    $imgstmt->bind_param("i", $id);
    $imgstmt->execute();
    $curr_img = $imgstmt->get_result()->fetch_assoc()['profile_image'] ?? "assets/image/user.png";
    $image_path = $curr_img;

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "uploads/members/";
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $new_name = "member_" . time() . "_" . uniqid() . "." . $ext;
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $new_name)) {
            if (!empty($curr_img) && file_exists($curr_img) && strpos($curr_img, 'assets/') === false) @unlink($curr_img);
            $image_path = $upload_dir . $new_name;
        }
    }

    $stmt->bind_param("ssssssssissssssssssissssi", 
        $member_code, $membership_date, $profession, $full_name, $normalized_name, $nickname, 
        $member_rank, $member_post, $family_id, $education, $address, $birth_place, 
        $birth_date, $languages, $blood_group, $phone, $id_card_number, $security_code, 
        $image_path, $parent_id, 
        $first_name, $father_name, $grandfather_name, $great_grandfather_name, $id
    );
    
    if ($stmt->execute()) {
        // Keep hierarchy name fields in sync
        updateMemberNameFields($conn, $id);
        $c_res = $conn->query("SELECT id FROM members WHERE parent_id = $id");
        if ($c_res) {
            while ($c_row = $c_res->fetch_assoc()) {
                updateMemberNameFields($conn, (int)$c_row['id']);
            }
        }

        // Handle Documents
        if (!empty($_POST['delete_docs'])) {
            foreach ($_POST['delete_docs'] as $doc_id) {
                $doc_id = (int)$doc_id;
                $fstmt = $conn->prepare("SELECT doc_path FROM member_documents WHERE id = ? AND member_id = ?");
                $fstmt->bind_param("ii", $doc_id, $id);
                $fstmt->execute();
                if ($d = $fstmt->get_result()->fetch_assoc()) {
                    if (file_exists($d['doc_path'])) @unlink($d['doc_path']);
                    $conn->query("DELETE FROM member_documents WHERE id = $doc_id");
                }
            }
        }

        if (!empty($_FILES['docs_files'])) {
            $doc_upload_dir = "uploads/member_docs/";
            if (!is_dir($doc_upload_dir)) @mkdir($doc_upload_dir, 0777, true);
            foreach ($_FILES['docs_files']['name'] as $idx => $name) {
                if ($_FILES['docs_files']['error'][$idx] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['docs_files']['tmp_name'][$idx];
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $doc_type = $_POST['docs_types'][$idx] ?? 'ناسنامە';
                    $doc_path = $doc_upload_dir . "doc_" . $id . "_" . time() . "_" . $idx . "." . $ext;
                    if (move_uploaded_file($tmp_name, $doc_path)) {
                        $doc_stmt = $conn->prepare("INSERT INTO member_documents (member_id, doc_type, doc_path) VALUES (?, ?, ?)");
                        $doc_stmt->bind_param("iss", $id, $doc_type, $doc_path);
                        $doc_stmt->execute();
                    }
                }
            }
        }

        $_SESSION['msg'] = "زانیارییەکان بە سەرکەوتوویی نوێکرانەوە.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "هەڵەیەک ڕوویدا: " . $conn->error;
        $_SESSION['msg_type'] = "error";
    }
    
    header("Location: members_list.php?updated_id=" . $id);
    exit;
}
?>
