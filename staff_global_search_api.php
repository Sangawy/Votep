<?php
session_start();
if (!isset($_SESSION['user_id'])) die(json_encode([]));

require_once 'config.php';
$conn = getDB();

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) die(json_encode([]));

$module = $_GET['module'] ?? '';
$results = [];
$search = "%$q%";

// 1. Search Staff (Name, Phone, ID, Bio, Voter, Location, Rank)
$stmt = $conn->prepare("SELECT m.id, m.full_name, r.rank_name, 'staff' as type 
                        FROM staff_members m 
                        JOIN staff_ranks r ON m.rank_id = r.id 
                        WHERE m.full_name LIKE ? 
                           OR m.phone LIKE ? 
                           OR m.id_number LIKE ? 
                           OR m.biometric_number LIKE ? 
                           OR m.voting_number LIKE ? 
                           OR m.civil_id LIKE ? 
                           OR m.village LIKE ? 
                           OR m.city LIKE ? 
                           OR m.district LIKE ? 
                           OR r.rank_name LIKE ? 
                        LIMIT 8");
$stmt->bind_param("ssssssssss", $search, $search, $search, $search, $search, $search, $search, $search, $search, $search);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $results[] = [
        'id' => $row['id'],
        'title' => $row['full_name'],
        'sub' => $row['rank_name'],
        'type' => 'staff',
        'link' => 'staff_profile.php?id=' . $row['id']
    ];
}

// 2. Search Documents (Title)
$stmt = $conn->prepare("SELECT d.id, d.staff_id, d.doc_title, d.file_type, m.full_name, 'doc' as type 
                        FROM staff_documents d 
                        JOIN staff_members m ON d.staff_id = m.id 
                        WHERE d.doc_title LIKE ? 
                        LIMIT 5");
$stmt->bind_param("s", $search);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $results[] = [
        'id' => $row['id'],
        'title' => $row['doc_title'],
        'sub' => "بەڵگەی: " . $row['full_name'],
        'type' => 'doc',
        'link' => 'staff_profile.php?id=' . $row['staff_id']
    ];
}

// 3. Search Service History (Unit, Order Number)
$stmt = $conn->prepare("SELECT s.staff_id, s.unit_name, s.order_number, m.full_name, 'service' as type 
                        FROM staff_service_history s 
                        JOIN staff_members m ON s.staff_id = m.id 
                        WHERE s.unit_name LIKE ? OR s.order_number LIKE ? 
                        LIMIT 5");
$stmt->bind_param("ss", $search, $search);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $results[] = [
        'id' => $row['staff_id'],
        'title' => $row['order_number'] ?: $row['unit_name'],
        'sub' => "فەرمانی: " . $row['full_name'],
        'type' => 'service',
        'link' => 'staff_profile.php?id=' . $row['staff_id']
    ];
}

// 4. Search Library (ONLY if module is library)
if ($module === 'library') {
    // Database Books
    $stmt = $conn->prepare("SELECT id, title, author, 'book' as type FROM books WHERE title LIKE ? OR author LIKE ? LIMIT 8");
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $results[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'sub' => "نووسەر: " . ($row['author'] ?: 'نەزانراو'),
            'type' => 'book',
            'link' => 'books.php?view=' . $row['id']
        ];
    }

    // PDF Library (Files)
    $pdf_dir = 'library Books/pdf_books/';
    if (is_dir($pdf_dir)) {
        $files = scandir($pdf_dir);
        $pdf_count = 0;
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (stripos($file, $q) !== false && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf') {
                $results[] = [
                    'id' => 'pdf_' . $pdf_count,
                    'title' => $file,
                    'sub' => 'فایلی PDF لە کتێبخانەی گشتی',
                    'type' => 'pdf_lib',
                    'link' => 'books.php?pdf=' . urlencode($file)
                ];
                $pdf_count++;
                if ($pdf_count >= 5) break;
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode($results);
