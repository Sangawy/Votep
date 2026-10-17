<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

require_once 'config.php';
$conn = getDB();

$action = $_POST['action'] ?? '';

if ($action === 'sync_congresses') {
    $member_id = (int)$_POST['member_id'];
    $selected_congresses = $_POST['congresses'] ?? []; // Array of election IDs

    $conn->query("DELETE FROM congress_members WHERE member_id = $member_id");

    if (!empty($selected_congresses)) {
        $stmt = $conn->prepare("INSERT INTO congress_members (member_id, election_id) VALUES (?, ?)");
        foreach ($selected_congresses as $eid) {
            $eid = (int)$eid;
            $stmt->bind_param("ii", $member_id, $eid);
            $stmt->execute();
        }
    }

    $_SESSION['msg'] = "بەشداریکردنی کۆنگرەکان بۆ ئەم ئەندامە نوێکرایەوە.";
    $_SESSION['msg_type'] = "success";
    header("Location: members_list.php?updated_id=" . $member_id);
    exit;
}

if ($action === 'bulk_sync') {
    $election_id = (int)$_POST['election_id'];
    $member_ids = $_POST['member_ids'] ?? [];

    if ($election_id > 0 && !empty($member_ids)) {
        $stmt = $conn->prepare("INSERT IGNORE INTO congress_members (member_id, election_id) VALUES (?, ?)");
        foreach ($member_ids as $mid) {
            $mid = (int)$mid;
            $stmt->bind_param("ii", $mid, $election_id);
            $stmt->execute();
        }
        $_SESSION['msg'] = count($member_ids) . " ئەندام بە سەرکەوتوویی بۆ ئەم کۆنگرەیە زیادکران.";
        $_SESSION['msg_type'] = "success";
    } else {
        $_SESSION['msg'] = "هیچ ئەندامێک یان کۆنگرەیەک هەڵنەبژێردراوە.";
        $_SESSION['msg_type'] = "warning";
    }
    header("Location: members_list.php");
    exit;
}

header("Location: members_list.php");
?>
