<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'تکایە سەرەتا بچۆ ژوورەوە']);
    exit;
}

require_once 'config.php';
$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'کردارەکە ڕێگەپێدراو نییە']);
    exit;
}

$family_name = trim($_POST['family_name'] ?? '');
$origin_area = trim($_POST['origin_area'] ?? '');
$ancestor_name = trim($_POST['ancestor_name'] ?? '');
$history = trim($_POST['history'] ?? '');

if (empty($family_name)) {
    echo json_encode(['success' => false, 'message' => 'تکایە ناوی بنەماڵە بنووسە']);
    exit;
}

// Check if family already exists
$check = $conn->prepare("SELECT id, family_name FROM families WHERE family_name = ? LIMIT 1");
$check->bind_param("s", $family_name);
$check->execute();
$res = $check->get_result();

if ($row = $res->fetch_assoc()) {
    $check->close();
    echo json_encode([
        'success' => true,
        'id' => (int)$row['id'],
        'family_name' => $row['family_name'],
        'is_existing' => true,
        'message' => 'ئەم بنەماڵەیە پێشتر تۆمارکراوە و دەستنیشانکرا'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$check->close();

// Insert new family
$stmt = $conn->prepare("INSERT INTO families (family_name, origin_area, ancestor_name, history) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $family_name, $origin_area, $ancestor_name, $history);

if ($stmt->execute()) {
    $new_id = $conn->insert_id;
    $stmt->close();
    echo json_encode([
        'success' => true,
        'id' => (int)$new_id,
        'family_name' => $family_name,
        'is_existing' => false,
        'message' => 'بنەماڵەی نوێ بە سەرکەوتوویی زیادکرا و هەڵبژێردرا'
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'هەڵەیەک ڕوویدا لە زیادکردنی بنەماڵە: ' . $conn->error
    ], JSON_UNESCAPED_UNICODE);
}
