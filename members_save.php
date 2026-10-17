<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

require_once 'config.php';
require_once 'lineage_core.php';
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $father_name = trim($_POST['father_name'] ?? '');
    $grandfather_name = trim($_POST['grandfather_name'] ?? '');
    $great_grandfather_name = trim($_POST['great_grandfather_name'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    
    if (empty($full_name)) {
        $full_name = implode(' ', array_filter([$first_name, $father_name, $grandfather_name, $great_grandfather_name]));
    }

    // 1. Mandatory Family Requirement
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
        $_SESSION['msg'] = "مەرجی سەرەکی تۆمارکردنی ئەندام دیاریکردنی بنەماڵەیە! ناتوانیت بەبێ بنەماڵە هیچ کەس تۆمار بکەیت.";
        $_SESSION['msg_type'] = "error";
        header("Location: members_new.php");
        exit;
    }

    $link_lineage_id = (int)($_POST['link_lineage_id'] ?? 0);
    $member_code = trim($_POST['member_code'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $member_rank = trim($_POST['member_rank'] ?? 'ئەندام');
    $member_post = trim($_POST['member_post'] ?? '');
    
    $membership_date = fixDate($_POST['membership_date'] ?? null);
    $profession = trim($_POST['profession'] ?? '');
    $education = trim($_POST['education'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $birth_place = trim($_POST['birth_place'] ?? '');
    $birth_date = fixDate($_POST['birth_date'] ?? null);
    $languages = trim($_POST['languages'] ?? '');
    $blood_group = $_POST['blood_group'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $id_card_number = trim($_POST['id_card_number'] ?? '');
    $security_code = trim($_POST['security_code'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : 0;

    $image_path = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = "uploads/members/";
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $new_name = "member_" . time() . "_" . uniqid() . "." . $ext;
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $new_name)) {
            $image_path = $upload_dir . $new_name;
        }
    }

    function normalizeForSearch($str) {
        $map = ['ێ'=>'ی','ي'=>'ی','ى'=>'ی','ك'=>'ک','ة'=>'ه','ە'=>'ه','ڕ'=>'ر','ڵ'=>'ل','ۆ'=>'و','أ'=>'ا','إ'=>'ا','آ'=>'ا'];
        $titles = ['السيد', 'السید', 'سید', 'ملا', 'مەلا', 'شێخ', 'شیخ'];
        $str = str_replace($titles, '', $str);
        return str_replace(array_keys($map), array_values($map), $str);
    }
    $normalized_name = normalizeForSearch($full_name);

    $member_id = 0;

    // SCENARIO A: Direct link to existing Lineage Person
    if ($link_lineage_id > 0) {
        $member_id = $link_lineage_id;
        
        // Fetch current image if no new image was uploaded
        if (!$image_path) {
            $c_res = $conn->query("SELECT profile_image FROM members WHERE id = $member_id");
            if ($c_row = $c_res->fetch_assoc()) {
                $image_path = $c_row['profile_image'] ?: 'assets/image/user.png';
            } else {
                $image_path = 'assets/image/user.png';
            }
        }

        $stmt = $conn->prepare("UPDATE members SET 
            is_official_member = 1,
            member_status = 'active',
            member_code = ?,
            membership_date = ?,
            profession = ?,
            full_name = ?,
            first_name = ?,
            father_name = ?,
            grandfather_name = ?,
            great_grandfather_name = ?,
            normalized_name = ?,
            nickname = ?,
            member_rank = ?,
            member_post = ?,
            family_id = ?,
            education = ?,
            address = ?,
            birth_place = ?,
            birth_date = ?,
            languages = ?,
            blood_group = ?,
            phone = ?,
            id_card_number = ?,
            security_code = ?,
            profile_image = ?,
            parent_id = ?
            WHERE id = ?");

        $stmt->bind_param("ssssssssssssissssssssssii", 
            $member_code, $membership_date, $profession, $full_name, $first_name, $father_name, $grandfather_name, $great_grandfather_name, $normalized_name, $nickname, $member_rank, $member_post, $family_id, 
            $education, $address, $birth_place, $birth_date, $languages, 
            $blood_group, $phone, $id_card_number, $security_code, $image_path, $parent_id, $member_id
        );
        $stmt->execute();
        $stmt->close();

    } else {
        // SCENARIO B: No explicit link ID was passed
        // 1. Check if this person is already an ACTIVE OFFICIAL member -> duplicate!
        $dup_check = $conn->prepare("SELECT id, full_name FROM members WHERE is_official_member = 1 AND normalized_name = ? AND member_status = 'active' LIMIT 1");
        $dup_check->bind_param("s", $normalized_name);
        $dup_check->execute();
        $dup_row = $dup_check->get_result()->fetch_assoc();
        $dup_check->close();

        if ($dup_row) {
            $_SESSION['msg'] = "ئەم کەسە پێشتر وەک ئەندامی فەرمی تۆمارکراوە (" . htmlspecialchars($dup_row['full_name']) . "). ناتوانرێت دووبارە بکرێتەوە!";
            $_SESSION['msg_type'] = "error";
            header("Location: members_new.php");
            exit;
        }

        // 2. Check if this person exists as a Lineage node (is_official_member = 0)
        // If they exist in the lineage, update THAT record so no duplicate person is created!
        $lineage_check = $conn->prepare("SELECT id, profile_image FROM members WHERE (is_official_member = 0 OR is_official_member IS NULL) AND normalized_name = ? AND family_id = ? LIMIT 1");
        $lineage_check->bind_param("si", $normalized_name, $family_id);
        $lineage_check->execute();
        $lineage_row = $lineage_check->get_result()->fetch_assoc();
        $lineage_check->close();

        if (!$image_path) {
            $image_path = $lineage_row['profile_image'] ?? 'assets/image/user.png';
        }

        if ($lineage_row) {
            $member_id = (int)$lineage_row['id'];
            $stmt = $conn->prepare("UPDATE members SET 
                is_official_member = 1,
                member_status = 'active',
                member_code = ?,
                membership_date = ?,
                profession = ?,
                full_name = ?,
                first_name = ?,
                father_name = ?,
                grandfather_name = ?,
                great_grandfather_name = ?,
                normalized_name = ?,
                nickname = ?,
                member_rank = ?,
                member_post = ?,
                family_id = ?,
                education = ?,
                address = ?,
                birth_place = ?,
                birth_date = ?,
                languages = ?,
                blood_group = ?,
                phone = ?,
                id_card_number = ?,
                security_code = ?,
                profile_image = ?,
                parent_id = ?
                WHERE id = ?");

            $stmt->bind_param("ssssssssssssissssssssssii", 
                $member_code, $membership_date, $profession, $full_name, $first_name, $father_name, $grandfather_name, $great_grandfather_name, $normalized_name, $nickname, $member_rank, $member_post, $family_id, 
                $education, $address, $birth_place, $birth_date, $languages, 
                $blood_group, $phone, $id_card_number, $security_code, $image_path, $parent_id, $member_id
            );
            $stmt->execute();
            $stmt->close();
        } else {
            // 3. New record altogether: both in lineage & official member
            $stmt = $conn->prepare("INSERT INTO members (
                member_code, membership_date, profession, full_name, first_name, father_name, grandfather_name, great_grandfather_name, normalized_name, nickname, member_rank, member_post, family_id, education, address, birth_place, birth_date, languages, blood_group, phone, id_card_number, security_code, profile_image, parent_id, member_status, is_official_member, is_alive, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 1, 1, NOW())");

            $stmt->bind_param("ssssssssssssissssssssssi", 
                $member_code, $membership_date, $profession, $full_name, $first_name, $father_name, $grandfather_name, $great_grandfather_name, $normalized_name, $nickname, $member_rank, $member_post, $family_id, 
                $education, $address, $birth_place, $birth_date, $languages, 
                $blood_group, $phone, $id_card_number, $security_code, $image_path, $parent_id
            );
            $stmt->execute();
            $member_id = $stmt->insert_id;
            $stmt->close();
        }
    }

    if ($member_id > 0) {
        // Auto-assign member_code if empty
        if (empty($member_code)) {
            $conn->query("UPDATE members SET member_code = '$member_id' WHERE id = $member_id");
        }

        // Update 4-generation fields
        updateMemberNameFields($conn, $member_id);

        // Upload documents if any
        if (!empty($_FILES['docs_files'])) {
            $doc_upload_dir = "uploads/member_docs/";
            if (!is_dir($doc_upload_dir)) @mkdir($doc_upload_dir, 0777, true);
            foreach ($_FILES['docs_files']['name'] as $idx => $name) {
                if ($_FILES['docs_files']['error'][$idx] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['docs_files']['tmp_name'][$idx];
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $doc_type = $_POST['docs_types'][$idx] ?? 'ناسنامە';
                    $new_doc_name = "doc_" . $member_id . "_" . time() . "_" . $idx . "." . $ext;
                    $doc_path = $doc_upload_dir . $new_doc_name;
                    if (move_uploaded_file($tmp_name, $doc_path)) {
                        $doc_stmt = $conn->prepare("INSERT INTO member_documents (member_id, doc_type, doc_path) VALUES (?, ?, ?)");
                        $doc_stmt->bind_param("iss", $member_id, $doc_type, $doc_path);
                        $doc_stmt->execute();
                        $doc_stmt->close();
                    }
                }
            }
        }

        $_SESSION['msg'] = "ئەندام بە سەرکەوتوویی لە بەشی کارگێڕی تۆمارکرا و بە نەسەبی بنەماڵەکەی بەسترایەوە.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "هەڵەیەک ڕوویدا لە تۆمارکردن: " . $conn->error;
        $_SESSION['msg_type'] = "error";
    }

    header("Location: members_list.php?updated_id=" . $member_id);
    exit;
}
?>
