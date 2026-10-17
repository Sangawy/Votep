<?php
session_start();
if (!isset($_SESSION['user_id'])) die(json_encode([]));

require_once 'config.php';
$conn = getDB();

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) die(json_encode([]));

$election_id = isset($_GET['election_id']) ? (int)$_GET['election_id'] : 0;

$results = [];
function normalizeForSearch($str) {
    $map = [
        'ێ' => 'ی', 'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'ە' => 'ه',
        'ڕ' => 'ر', 'ڵ' => 'ل', 'ۆ' => 'و', 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا'
    ];
    return str_replace(array_keys($map), array_values($map), $str);
}

function sqlNormalize($col) {
    return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(CAST($col AS CHAR) COLLATE utf8mb4_unicode_ci, 'ێ', 'ی'), 'ي', 'ی'), 'ى', 'ی'), 'ك', 'ک'), 'ة', 'ه'), 'ە', 'ه'), 'ڕ', 'ر'), 'ڵ', 'ل'), 'ۆ', 'و'), 'أ', 'ا'), 'إ', 'ا'), 'آ', 'ا'), ' ', '')";
}

$search_filtered = $q;
$title_pattern = '/\b(السید|سید|سەید|ملا|مەلا|مولا|شێخ|شیخ|السيد|سيد)\b/u';
$search_filtered = trim(preg_replace($title_pattern, '', $search_filtered));

$words = explode(' ', $search_filtered);
$words = array_filter($words, function($w) { return mb_strlen($w) >= 2; });
$words = array_values($words);
if (empty($words)) $words = [$q];

$search_columns = "CONCAT_WS(' ', m.full_name, p1.full_name, p2.full_name, p3.full_name, f.family_name, m.address, m.member_rank, m.member_post, m.education, m.profession, m.phone, m.member_code)";

$word_clauses = [];
$params = [];
$types = "";

foreach ($words as $word) {
    $norm_word = normalizeForSearch($word);
    $clean_word = str_replace(' ', '', $norm_word);
    if (empty($clean_word)) continue;

    $word_clauses[] = sqlNormalize($search_columns) . " LIKE ?";
    $params[] = "%$clean_word%";
    $types .= "s";
}

$where = " (1=1 ";
if (!empty($word_clauses)) {
    $where .= " AND (" . implode(" AND ", $word_clauses) . ")";
}
$where .= ") ";

$sql = "SELECT m.id, m.full_name, m.member_code, m.profile_image, f.family_name, 'member' as type 
        FROM members m 
        LEFT JOIN families f ON m.family_id = f.id 
        LEFT JOIN members p1 ON m.parent_id = p1.id
        LEFT JOIN members p2 ON p1.parent_id = p2.id
        LEFT JOIN members p3 ON p2.parent_id = p3.id
        WHERE $where";

if ($election_id > 0) {
    $sql .= " AND m.id NOT IN (SELECT member_id FROM candidates WHERE election_id = $election_id)";
}

$sql .= " LIMIT 15";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $results[] = [
        'id' => $row['id'],
        'title' => $row['full_name'],
        'sub' => ($row['family_name'] ? $row['family_name'] . " - " : "") . $row['member_code'],
        'img' => $row['profile_image'],
        'type' => 'member',
        'link' => 'members_profile.php?id=' . $row['id']
    ];
}

header('Content-Type: application/json');
echo json_encode($results);
?>
