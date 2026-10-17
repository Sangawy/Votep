<?php
require_once 'config.php';
$conn = getDB();

$name = trim($_GET['name'] ?? '');
if (empty($name)) {
    echo json_encode(['exists' => false]);
    exit;
}

function normalizeForSearch($str) {
    $map = [
        'ێ' => 'ی', 'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'ە' => 'ه',
        'ڕ' => 'ر', 'ڵ' => 'ل', 'ۆ' => 'و', 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', ' ' => ''
    ];
    $titles = ['السيد', 'السید', 'سید', 'ملا', 'مەلا', 'شێخ', 'شیخ'];
    $str = str_replace($titles, '', $str);
    return str_replace(array_keys($map), array_values($map), $str);
}

// پارچە پارچە کردنی ناوەکە بۆ پشکنینی وردتر
function getWords($str) {
    $titles = ['السيد', 'السید', 'سید', 'ملا', 'مەلا', 'شێخ', 'شیخ'];
    $str = str_replace($titles, '', $str);
    $words = preg_split('/\s+/', $str, -1, PREG_SPLIT_NO_EMPTY);
    $normalized_words = [];
    foreach($words as $w) {
        if(mb_strlen($w) > 2) $normalized_words[] = normalizeForSearch($w);
    }
    return $normalized_words;
}

$input_words = getWords($name);
if (count($input_words) < 2) {
    echo json_encode(['exists' => false]);
    exit;
}

// گەڕان بەدوای ئەندامانی فەرمی و کەسانی نەسەب
$res = $conn->query("SELECT m.id, m.full_name, m.normalized_name, m.member_status, m.is_official_member, m.family_id, f.family_name 
                     FROM members m 
                     LEFT JOIN families f ON m.family_id = f.id 
                     WHERE m.member_status = 'active'");
$found_official = false;
$found_official_name = "";
$found_lineage = false;
$lineage_record = null;

while ($row = $res->fetch_assoc()) {
    $existing_words = getWords($row['full_name']);
    
    $match_count = 0;
    foreach($existing_words as $ew) {
        if (in_array($ew, $input_words)) {
            $match_count++;
        }
    }
    
    // ئەگەر لانیکەم ٢ ناوی یەکسان بوون و یەکێکیان ناوی یەکەم بوو
    if ($match_count >= 2 && in_array($input_words[0], $existing_words)) {
        if ((int)$row['is_official_member'] === 1) {
            $found_official = true;
            $found_official_name = $row['full_name'];
            break;
        } else if (!$found_lineage) {
            $found_lineage = true;
            $lineage_record = [
                'id' => (int)$row['id'],
                'full_name' => $row['full_name'],
                'family_id' => (int)$row['family_id'],
                'family_name' => $row['family_name'] ?? ''
            ];
        }
    }
}

echo json_encode([
    'exists' => $found_official, 
    'status' => 'active', 
    'matched_name' => $found_official_name,
    'exists_in_lineage' => $found_lineage,
    'lineage_record' => $lineage_record
]);
?>
