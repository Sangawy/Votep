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

$docNumber = trim($_POST['doc_num'] ?? '');
$docDate = trim($_POST['doc_date'] ?? '');
$subject = trim($_POST['doc_subject'] ?? '');
$correspondent = trim($_POST['doc_to'] ?? '');
$docType = trim($_POST['doc_type'] ?? 'Inbound');
$relatedDocId = !empty($_POST['related_doc_id']) ? (int)$_POST['related_doc_id'] : 'NULL';
$userId = (int)$_SESSION['user_id'];

// Default settings
$archiveBaseDir = 'D:/Archive/';
$res = $conn->query("SELECT archive_base_dir FROM settings WHERE id = 1");
if ($res && $row = $res->fetch_assoc()) {
    if (!empty($row['archive_base_dir'])) $archiveBaseDir = $row['archive_base_dir'];
}

$year = date('Y', strtotime($docDate) ?: time());
$month = date('m', strtotime($docDate) ?: time());

$normalizedType = 'Other';
if (in_array($docType, ['هاتوو', 'Inbound'])) $normalizedType = 'Inbound';
elseif (in_array($docType, ['دەرچوو', 'Outbound'])) $normalizedType = 'Outbound';
elseif (in_array($docType, ['نهێنی', 'Secret'])) $normalizedType = 'Secret';

// Folder structure: Archive/Year/Inbound/Month/DocNumber/
$targetDir = rtrim(str_replace('\\', '/', $archiveBaseDir), '/') . '/' . $year . '/' . $normalizedType . '/' . $month . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $docNumber);

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

$finalFilePath = '';
if (!empty($_POST['scanned_base64'])) {
    $data = $_POST['scanned_base64'];
    if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
        $data = substr($data, strpos($data, ',') + 1);
        $type = strtolower($type[1]); // jpg, png, etc
        $data = base64_decode($data);
        if ($data !== false) {
            $fileName = 'scanned_' . time() . '.' . $type;
            $targetPath = $targetDir . '/' . $fileName;
            if (file_put_contents($targetPath, $data)) {
                $finalFilePath = $targetPath;
            }
        }
    }
} elseif (isset($_FILES['scanned_file']) && $_FILES['scanned_file']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['scanned_file']['name'], PATHINFO_EXTENSION);
    $fileName = 'scanned_' . time() . '.' . $ext;
    $targetPath = $targetDir . '/' . $fileName;
    if (move_uploaded_file($_FILES['scanned_file']['tmp_name'], $targetPath)) {
        $finalFilePath = $targetPath;
    }
}

$dbDocNumber = $conn->real_escape_string($docNumber);
$dbSubject = $conn->real_escape_string($subject);
$dbType = $conn->real_escape_string($normalizedType);
$dbCorr = $conn->real_escape_string($correspondent);
$dbDate = $conn->real_escape_string($docDate);
$dbPath = $conn->real_escape_string(str_replace('/', DIRECTORY_SEPARATOR, $finalFilePath));
$signBy = isset($_POST['sign_by_organization']) ? 'organization' : 'director';
$dbSignBy = $conn->real_escape_string($signBy);

$sql = "INSERT INTO archive_docs (doc_number, subject, doc_type, correspondent, doc_date, file_path, created_by, related_doc_id, sign_by) 
        VALUES ('$dbDocNumber', '$dbSubject', '$dbType', '$dbCorr', '$dbDate', '$dbPath', $userId, $relatedDocId, '$dbSignBy')";

if ($conn->query($sql)) {
    // Log audit
    if (function_exists('logAudit')) {
        logAudit($conn, $userId, 'Insert', 'archive_docs', $conn->insert_id, null, ['doc_number' => $docNumber, 'subject' => $subject, 'type' => 'Inbound']);
    }
    header('Location: docs.php?msg=created');
} else {
    echo "Error: " . $conn->error;
}
exit;
