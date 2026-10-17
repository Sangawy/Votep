<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
$conn = getDB();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// -------------------------------------------------------------
// PUBLIC ACTION: Get Family Members for Login Dropdown
// -------------------------------------------------------------
if ($action === 'get_family_members') {
    $family_id = isset($_GET['family_id']) ? (int)$_GET['family_id'] : 0;
    if ($family_id <= 0) {
        echo json_encode(['success' => false, 'members' => []]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT m.id, m.full_name, m.member_code, 
               COALESCE(fat.full_name, m.father_name) as father_name 
        FROM members m 
        LEFT JOIN members fat ON m.parent_id = fat.id 
        WHERE m.family_id = ? 
        ORDER BY m.full_name ASC
    ");
    $stmt->bind_param("i", $family_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $members = [];
    while ($row = $res->fetch_assoc()) {
        $members[] = [
            'id' => $row['id'],
            'name' => $row['full_name'],
            'father_name' => $row['father_name'] ?: '',
            'code' => $row['member_code'] ?: $row['id']
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'members' => $members]);
    exit;
}

// -------------------------------------------------------------
// PROTECTED ACTIONS: Require Member Login
// -------------------------------------------------------------
if (!isset($_SESSION['member_portal_id']) || (int)$_SESSION['member_portal_id'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'پێویستە سەرەتا بچیتە ژوورەوە.']);
    exit;
}

$member_id = (int)$_SESSION['member_portal_id'];

// 1. UPDATE PROFILE INFORMATION
if ($action === 'update_profile') {
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $profession = trim($_POST['profession'] ?? ($_POST['job'] ?? ''));
    $education = trim($_POST['education'] ?? '');
    $languages = trim($_POST['languages'] ?? '');
    $blood_group = trim($_POST['blood_group'] ?? '');
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $birth_place = trim($_POST['birth_place'] ?? '');
    $membership_date = !empty($_POST['membership_date']) ? $_POST['membership_date'] : null;
    $security_code = trim($_POST['security_code'] ?? '');
    $id_card_number = trim($_POST['id_card_number'] ?? '');

    $stmt = $conn->prepare("
        UPDATE members 
        SET phone = ?, address = ?, profession = ?, job = ?, education = ?, education_level = ?, 
            languages = ?, blood_group = ?, birth_date = ?, birth_place = ?, membership_date = ?, 
            security_code = ?, id_card_number = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param(
        "sssssssssssssi", 
        $phone, $address, $profession, $profession, $education, $education, 
        $languages, $blood_group, $birth_date, $birth_place, $membership_date, 
        $security_code, $id_card_number, $member_id
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'سەرجەم زانیارییەکان بە سەرکەوتوویی نوێکرانەوە.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'هەڵەیەک ڕوویدا: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// 2. UPLOAD DOCUMENT OR PROFILE PHOTO
if ($action === 'upload_document') {
    $doc_type = trim($_POST['doc_type'] ?? '');
    if (empty($doc_type)) {
        echo json_encode(['success' => false, 'message' => 'جۆری بەڵگەنامە دیاری نەکراوە.']);
        exit;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'هیچ فایلێک هەڵنەبژێردراوە یاخود هەڵە لە بارکردندا هەیە.']);
        exit;
    }

    $upload_dir = __DIR__ . '/../uploads/member_docs/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'تەنها فایلی وێنە (JPG, PNG, WEBP) یان PDF ڕێگەپێدراوە.']);
        exit;
    }

    $filename = 'm_' . $member_id . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
    $target_path = $upload_dir . $filename;
    $db_path = 'uploads/member_docs/' . $filename;

    if (move_uploaded_file($_FILES['file']['tmp_name'], $target_path)) {
        // If profile photo, update members.profile_image too
        if ($doc_type === 'وێنەی کەسی') {
            $uStmt = $conn->prepare("UPDATE members SET profile_image = ?, updated_at = NOW() WHERE id = ?");
            $uStmt->bind_param("si", $db_path, $member_id);
            $uStmt->execute();
            $uStmt->close();
        }

        // Insert or update in member_documents
        // Check if doc_type already exists for this member
        $chk = $conn->prepare("SELECT id FROM member_documents WHERE member_id = ? AND doc_type = ?");
        $chk->bind_param("is", $member_id, $doc_type);
        $chk->execute();
        $existing = $chk->get_result()->fetch_assoc();
        $chk->close();

        if ($existing) {
            $ins = $conn->prepare("UPDATE member_documents SET doc_path = ?, created_at = NOW() WHERE id = ?");
            $ins->bind_param("si", $db_path, $existing['id']);
        } else {
            $ins = $conn->prepare("INSERT INTO member_documents (member_id, doc_type, doc_path) VALUES (?, ?, ?)");
            $ins->bind_param("iss", $member_id, $doc_type, $db_path);
        }
        $ins->execute();
        $ins->close();

        echo json_encode([
            'success' => true, 
            'message' => 'بەڵگەنامەکە بە سەرکەوتوویی بارکرا.',
            'doc_path' => $db_path,
            'doc_type' => $doc_type
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'نەتوانرا فایلەکە پاشەکەوت بکرێت.']);
    }
    exit;
}

// 3. ADD CHILD REQUEST (PENDING CONFIRMATION)
if ($action === 'add_child_request') {
    $child_name = trim($_POST['child_name'] ?? '');
    $gender = trim($_POST['gender'] ?? 'نێر');
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $notes = trim($_POST['notes'] ?? '');

    if (empty($child_name)) {
        echo json_encode(['success' => false, 'message' => 'تکایە ناوی منداڵ بنووسە.']);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO member_requests (member_id, request_type, child_name, gender, birth_date, notes, status, created_at)
        VALUES (?, 'add_child', ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->bind_param("issss", $member_id, $child_name, $gender, $birth_date, $notes);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'داواکاری زیادکردنی منداڵ بە سەرکەوتوویی نێردرا! پاش وردبینی و پەسەندکردنی لیژنەی ئەنساب دەخرێتە ناو درەختی فەرمی.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'هەڵەیەک ڕوویدا: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// 4. CANCEL PENDING REQUEST
if ($action === 'cancel_request') {
    $req_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $stmt = $conn->prepare("DELETE FROM member_requests WHERE id = ? AND member_id = ? AND status = 'pending'");
    $stmt->bind_param("ii", $req_id, $member_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'داواکارییەکە هەڵوەشێنرایەوە.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'نەتوانرا داواکارییەکە هەڵبوەشێنرێتەوە.']);
    }
    $stmt->close();
    exit;
}

// 5. UPDATE CHILD PROFILE (FATHER EDITING CONFIRMED CHILD)
if ($action === 'update_child_profile') {
    $child_id = isset($_POST['child_id']) ? (int)$_POST['child_id'] : 0;
    if ($child_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ئایدی منداڵ دیاری نەکراوە.']);
        exit;
    }

    // Verify father-child relationship (Security Check)
    $chk = $conn->prepare("SELECT id, full_name FROM members WHERE id = ? AND parent_id = ?");
    $chk->bind_param("ii", $child_id, $member_id);
    $chk->execute();
    $childRow = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$childRow) {
        echo json_encode(['success' => false, 'message' => 'تۆ دەسەڵاتی دەستکاریکردنی ئەم ئەندامەت نییە.']);
        exit;
    }

    $first_name = trim($_POST['first_name'] ?? '');
    $gender = trim($_POST['gender'] ?? 'نێر');
    $education = trim($_POST['education'] ?? '');
    $profession = trim($_POST['profession'] ?? '');
    $blood_group = trim($_POST['blood_group'] ?? '');
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $birth_place = trim($_POST['birth_place'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $id_card_number = trim($_POST['id_card_number'] ?? '');
    $security_code = trim($_POST['security_code'] ?? '');

    if (!empty($first_name)) {
        $full_name = $first_name;
        $uStmt = $conn->prepare("
            UPDATE members 
            SET first_name = ?, full_name = ?, gender = ?, education = ?, education_level = ?, 
                profession = ?, job = ?, blood_group = ?, birth_date = ?, birth_place = ?, 
                address = ?, phone = ?, id_card_number = ?, security_code = ?, updated_at = NOW() 
            WHERE id = ? AND parent_id = ?
        ");
        $uStmt->bind_param(
            "ssssssssssssssii",
            $first_name, $full_name, $gender, $education, $education, 
            $profession, $profession, $blood_group, $birth_date, $birth_place, 
            $address, $phone, $id_card_number, $security_code, $child_id, $member_id
        );
    } else {
        $uStmt = $conn->prepare("
            UPDATE members 
            SET gender = ?, education = ?, education_level = ?, profession = ?, job = ?, 
                blood_group = ?, birth_date = ?, birth_place = ?, address = ?, phone = ?, 
                id_card_number = ?, security_code = ?, updated_at = NOW() 
            WHERE id = ? AND parent_id = ?
        ");
        $uStmt->bind_param(
            "ssssssssssssii",
            $gender, $education, $education, $profession, $profession, 
            $blood_group, $birth_date, $birth_place, $address, $phone, 
            $id_card_number, $security_code, $child_id, $member_id
        );
    }

    if ($uStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'زانیارییەکانی منداڵەکەت بە سەرکەوتوویی نوێکرانەوە.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'هەڵەیەک ڕوویدا: ' . $uStmt->error]);
    }
    $uStmt->close();
    exit;
}

// 6. UPLOAD CHILD PHOTO
if ($action === 'upload_child_photo') {
    $child_id = isset($_POST['child_id']) ? (int)$_POST['child_id'] : 0;
    if ($child_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ئایدی منداڵ دیاری نەکراوە.']);
        exit;
    }

    // Verify father-child relationship
    $chk = $conn->prepare("SELECT id FROM members WHERE id = ? AND parent_id = ?");
    $chk->bind_param("ii", $child_id, $member_id);
    $chk->execute();
    if (!$chk->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'دەسەڵاتی ئەم کردارەت نییە.']);
        exit;
    }
    $chk->close();

    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'هیچ وێنەیەک هەڵنەبژێردراوە.']);
        exit;
    }

    $upload_dir = __DIR__ . '/../uploads/members/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'تەنها وێنەی (JPG, PNG, WEBP) ڕێگەپێدراوە.']);
        exit;
    }

    $filename = 'child_' . $child_id . '_' . time() . '.' . $ext;
    $target_path = $upload_dir . $filename;
    $db_path = 'uploads/members/' . $filename;

    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
        $uStmt = $conn->prepare("UPDATE members SET profile_image = ?, updated_at = NOW() WHERE id = ? AND parent_id = ?");
        $uStmt->bind_param("sii", $db_path, $child_id, $member_id);
        $uStmt->execute();
        $uStmt->close();
        echo json_encode(['success' => true, 'message' => 'وێنەی منداڵەکە بە سەرکەوتوویی بارکرا.', 'photo_url' => '../' . $db_path]);
    } else {
        echo json_encode(['success' => false, 'message' => 'هەڵە لە پاشەکەوتکردنی وێنە.']);
    }
    exit;
}

// 7. UPDATE PENDING CHILD REQUEST
if ($action === 'update_child_request') {
    $req_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $child_name = trim($_POST['child_name'] ?? '');
    $gender = trim($_POST['gender'] ?? 'نێر');
    $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $notes = trim($_POST['notes'] ?? '');

    if (empty($child_name)) {
        echo json_encode(['success' => false, 'message' => 'تکایە ناوی منداڵ بنووسە.']);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE member_requests 
        SET child_name = ?, gender = ?, birth_date = ?, notes = ?, updated_at = NOW() 
        WHERE id = ? AND member_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("ssssii", $child_name, $gender, $birth_date, $notes, $req_id, $member_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'داواکارییەکە بە سەرکەوتوویی چاککرا.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'هەڵەیەک ڕوویدا: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'کردارێکی نەناسراوە.']);
exit;
