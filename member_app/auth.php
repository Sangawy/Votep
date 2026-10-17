<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
$conn = getDB();

if (!isset($_SESSION['member_portal_id']) || (int)$_SESSION['member_portal_id'] <= 0) {
    header('Location: login.php');
    exit;
}

$member_id = (int)$_SESSION['member_portal_id'];

// Fetch member details
$stmt = $conn->prepare("
    SELECT m.*, f.family_name, 
           fat.full_name as father_full_name,
           gfat.full_name as grandfather_full_name
    FROM members m
    LEFT JOIN families f ON m.family_id = f.id
    LEFT JOIN members fat ON m.parent_id = fat.id
    LEFT JOIN members gfat ON fat.parent_id = gfat.id
    WHERE m.id = ?
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$current_member = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current_member) {
    unset($_SESSION['member_portal_id']);
    header('Location: login.php');
    exit;
}

// Fetch all member documents
$docs_stmt = $conn->prepare("SELECT * FROM member_documents WHERE member_id = ? ORDER BY id DESC");
$docs_stmt->bind_param("i", $member_id);
$docs_stmt->execute();
$docs_result = $docs_stmt->get_result();
$member_docs = [];
while ($d = $docs_result->fetch_assoc()) {
    $member_docs[$d['doc_type']] = $d;
}
$docs_stmt->close();

// Clean family name
$rawFamily = $current_member['family_name'] ?: ($current_member['bnamala'] ?: 'ساداتی پیرخدری شاهۆ');
$cleanFamily = trim(preg_replace('/^(ساداتی|سادات|سادتی)\s+/u', '', $rawFamily));
