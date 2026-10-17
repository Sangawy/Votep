<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Forbidden');
}

require_once 'config.php';
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: docs.php');
    exit;
}

$docId = (int)$_POST['doc_id'];
$docNumber = trim($_POST['doc_num'] ?? '');
$docDate = trim($_POST['doc_date'] ?? '');
$subject = trim($_POST['doc_subject'] ?? '');
$correspondent = trim($_POST['doc_to'] ?? '');
$docType = trim($_POST['doc_type'] ?? '');
$wordContent = trim($_POST['doc_content'] ?? '');
$attachmentsListText = trim($_POST['attachments_list_text'] ?? '');
$attachmentsPreviewText = trim($_POST['attachments_preview_text'] ?? '');
$relatedId = isset($_POST['related_doc_id']) && $_POST['related_doc_id'] !== '' ? (int)$_POST['related_doc_id'] : 'NULL';

$committeeHeaders = $_POST['committee_headers'] ?? [];
$committeeCells = $_POST['committee_cells'] ?? [];
$committeeDataJson = null;
if (!empty($committeeHeaders)) {
    $committeeDataJson = json_encode([
        'headers' => $committeeHeaders,
        'cells' => $committeeCells
    ], JSON_UNESCAPED_UNICODE);
}
$dbCommittee = $committeeDataJson ? "'" . $conn->real_escape_string($committeeDataJson) . "'" : "NULL";
$userId = (int)$_SESSION['user_id'];

// Fetch current doc to know the path
$res = $conn->query("SELECT * FROM archive_docs WHERE id = $docId");
if (!$res || !$currentDoc = $res->fetch_assoc()) {
    die("Document not found.");
}

$dbDocNumber = $conn->real_escape_string($docNumber);
$dbSubject = $conn->real_escape_string($subject);
$dbCorr = $conn->real_escape_string($correspondent);
$dbDate = $conn->real_escape_string($docDate);

$normType = $docType;
if (in_array($docType, ['هاتوو', 'Inbound'])) $normType = 'Inbound';
elseif (in_array($docType, ['دەرچوو', 'Outbound'])) $normType = 'Outbound';
elseif (in_array($docType, ['نهێنی', 'Secret'])) $normType = 'Secret';

$dbType = $conn->real_escape_string($normType);
$dbWord = $conn->real_escape_string($wordContent);
$dbAttachmentsList = $attachmentsListText !== '' ? "'" . $conn->real_escape_string($attachmentsListText) . "'" : "NULL";
$dbAttachmentsPreview = $attachmentsPreviewText !== '' ? "'" . $conn->real_escape_string($attachmentsPreviewText) . "'" : "NULL";

// Handle file update if provided
$newFilePath = $currentDoc['file_path'];
if (isset($_FILES['scanned_file']) && $_FILES['scanned_file']['error'] === UPLOAD_ERR_OK) {
    $targetDir = 'uploads/archive/' . date('Y/m');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $ext = pathinfo($_FILES['scanned_file']['name'], PATHINFO_EXTENSION);
    $fileName = 'updated_' . time() . '.' . $ext;
    $targetPath = $targetDir . '/' . $fileName;
    
    if (move_uploaded_file($_FILES['scanned_file']['tmp_name'], $targetPath)) {
        $newFilePath = $targetPath;
    }
} elseif (!empty($_POST['scanned_base64'])) {
    // Handle base64 from scanner
    $targetDir = 'uploads/archive/' . date('Y/m');
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    $data = explode(',', $_POST['scanned_base64']);
    $content = base64_decode(end($data));
    $fileName = 'scan_' . time() . '.png';
    $targetPath = $targetDir . '/' . $fileName;
    if (file_put_contents($targetPath, $content)) {
        $newFilePath = $targetPath;
    }
}

$dbPath = $conn->real_escape_string($newFilePath);
$signBy = isset($_POST['sign_by_organization']) ? 'organization' : 'director';
$dbSignBy = $conn->real_escape_string($signBy);

$sql = "UPDATE archive_docs SET 
        doc_number = '$dbDocNumber', 
        subject = '$dbSubject', 
        correspondent = '$dbCorr', 
        doc_date = '$dbDate', 
        doc_type = '$dbType',
        word_content = '$dbWord',
        related_doc_id = $relatedId,
        file_path = '$dbPath',
        committee_data = $dbCommittee,
        attachments_list_text = $dbAttachmentsList,
        attachments_preview_text = $dbAttachmentsPreview,
        sign_by = '$dbSignBy'
        WHERE id = $docId";

if ($conn->query($sql)) {
    // Log audit
    if (function_exists('logAudit')) {
        logAudit($conn, $userId, 'Update', 'archive_docs', $docId, $currentDoc, [
            'doc_number' => $docNumber,
            'subject' => $subject,
            'correspondent' => $correspondent,
            'doc_date' => $docDate
        ]);
    }
    header('Location: docs.php?msg=updated');
} else {
    echo "Error: " . $conn->error;
}
exit;
