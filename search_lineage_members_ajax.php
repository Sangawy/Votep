<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config.php';
$conn = getDB();

$family_id = isset($_GET['family_id']) ? (int)$_GET['family_id'] : 0;
$query = trim($_GET['q'] ?? $_GET['name'] ?? '');

if (empty($query)) {
    echo json_encode([]);
    exit;
}

function normalizeSearchText($str) {
    if (!$str) return '';
    $str = mb_strtolower($str, 'UTF-8');
    // Remove Harakat, Tatweel
    $str = preg_replace('/[\x{064B}-\x{065F}\x{0640}\x{0670}]/u', '', $str);
    $str = preg_replace('/[آأإٱ]/u', 'ا', $str);
    $str = preg_replace('/[يىیێئ]/u', 'ی', $str);
    $str = preg_replace('/[هةھە]/u', 'ه', $str);
    $str = preg_replace('/[ك]/u', 'ک', $str);
    $str = preg_replace('/[ۆۇۉ]/u', 'و', $str);
    $str = preg_replace('/[ڕ]/u', 'ر', $str);
    $str = preg_replace('/[ڵ]/u', 'ل', $str);
    $str = preg_replace('/[()[\]{}.,_\-–—\/\\\\:;#]/u', ' ', $str);
    
    $titles = ['السيد', 'السید', 'سید', 'سەید', 'ملا', 'مەلا', 'شێخ', 'شیخ', 'الحاج', 'حاجی', 'حاج', 'العلامة', 'د.'];
    foreach ($titles as $t) {
        $tNorm = preg_replace('/[يىیێ]/u', 'ی', $t);
        $tNorm = preg_replace('/[هةھە]/u', 'ه', $tNorm);
        $str = preg_replace('/\b' . preg_quote($tNorm, '/') . '\b/u', ' ', $str);
    }
    return trim(preg_replace('/\s+/u', ' ', $str));
}

function cleanHonorific($name) {
    if (!$name) return '';
    return trim(preg_replace('/^(?:السید|السيد|سید|سەید|ملا|مەلا|شێخ|شیخ|الحاج|حاجی|حاج|العلامة|د\.|ئەندام)\s+/u', '', $name));
}

function toRegexPattern($token) {
    $t = preg_quote($token, '/');
    $t = preg_replace('/[يىیێ]/u', '[يىیێ]', $t);
    $t = preg_replace('/[هةھە]/u', '[هةھە]', $t);
    $t = preg_replace('/[كک]/u', '[كک]', $t);
    $t = preg_replace('/[ۆۇۉو]/u', '[ۆۇۉو]', $t);
    $t = preg_replace('/[آأإاٱ]/u', '[آأإاٱ]', $t);
    $t = preg_replace('/[ڕر]/u', '[ڕر]', $t);
    $t = preg_replace('/[ڵل]/u', '[ڵل]', $t);
    return $t;
}

$norm = normalizeSearchText($query);
$tokens = preg_split('/\s+/u', $norm, -1, PREG_SPLIT_NO_EMPTY);
if (empty($tokens)) {
    echo json_encode([]);
    exit;
}

$whereClauses = [];
$params = [];
$types = "";

foreach ($tokens as $token) {
    $reg = toRegexPattern($token);
    $whereClauses[] = "(
        CONCAT_WS(' ', m.full_name, m.first_name, m.father_name, m.grandfather_name, m.great_grandfather_name, 
                       COALESCE(fat.full_name, ''), COALESCE(fat.first_name, ''), COALESCE(m.member_code, ''), m.id) REGEXP ?
    )";
    $params[] = $reg;
    $types .= "s";
}

$whereSql = implode(' AND ', $whereClauses);

// Prioritize selected family, then official/tree order
$orderClause = "ORDER BY ";
if ($family_id > 0) {
    $orderClause .= "(m.family_id = $family_id) DESC, ";
}
$orderClause .= "m.id ASC";

$sql = "SELECT m.id, m.full_name, m.first_name, m.father_name, m.grandfather_name, m.great_grandfather_name, 
               m.parent_id, m.family_id, f.family_name, m.member_code, m.is_alive, m.is_official_member, m.profile_image,
               fat.full_name as parent_full_name, fat.first_name as parent_first_name, fat.father_name as parent_fat_name,
               gfat.full_name as grand_full_name, gfat.first_name as grand_first_name
        FROM members m 
        LEFT JOIN families f ON m.family_id = f.id 
        LEFT JOIN members fat ON m.parent_id = fat.id 
        LEFT JOIN members gfat ON fat.parent_id = gfat.id 
        WHERE $whereSql 
        $orderClause 
        LIMIT 30";

$stmt = $conn->prepare($sql);
if ($types && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$results = [];
while ($row = $res->fetch_assoc()) {
    $pName = cleanHonorific($row['first_name'] ?: $row['full_name']);
    $fName = cleanHonorific(!empty($row['father_name']) && $row['father_name'] !== 'نادیار' ? $row['father_name'] : ($row['parent_first_name'] ?: $row['parent_full_name']));
    $gName = cleanHonorific(!empty($row['grandfather_name']) && $row['grandfather_name'] !== 'نادیار' ? $row['grandfather_name'] : ($row['parent_fat_name'] ?: $row['grand_first_name']));
    $ggName = cleanHonorific(!empty($row['great_grandfather_name']) && $row['great_grandfather_name'] !== 'نادیار' ? $row['great_grandfather_name'] : '');
    
    // Build full clean lineage chain: e.g. "سامان علاءالدين رضا محمد"
    $parts = [$pName];
    if ($fName && !in_array($fName, $parts)) $parts[] = $fName;
    if ($gName && !in_array($gName, $parts)) $parts[] = $gName;
    if ($ggName && !in_array($ggName, $parts)) $parts[] = $ggName;
    $fullChain = implode(' ', $parts);

    $results[] = [
        'id' => (int)$row['id'],
        'full_name' => $row['full_name'],
        'lineage_chain' => $fullChain,
        'first_name' => $pName,
        'father_name' => $fName,
        'grandfather_name' => $gName,
        'great_grandfather_name' => $ggName,
        'parent_id' => (int)$row['parent_id'],
        'family_id' => (int)$row['family_id'],
        'family_name' => $row['family_name'] ?? '',
        'member_code' => $row['member_code'] ?? '',
        'is_alive' => (int)$row['is_alive'],
        'is_official_member' => (int)$row['is_official_member'],
        'profile_image' => !empty($row['profile_image']) ? $row['profile_image'] : ''
    ];
}

echo json_encode($results, JSON_UNESCAPED_UNICODE);
