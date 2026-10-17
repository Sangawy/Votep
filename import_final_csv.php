<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

require_once 'config.php';
$conn = getDB();

$csvFile = 'f:/laragon/www/pirxdry/File/لیستی ناوی ئەندامان داتا بەیس.csv';

if (!file_exists($csvFile)) {
    die("Error: CSV file not found at $csvFile");
}

// 1. Clear tables
echo "پاککردنەوەی داتابەیس...<br>";
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("TRUNCATE TABLE member_documents");
$conn->query("TRUNCATE TABLE members");
$conn->query("TRUNCATE TABLE families");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

// Helper for normalization
function normalizeForSearch($str) {
    $map = ['ێ'=>'ی','ي'=>'ی','ى'=>'ی','ك'=>'ک','ة'=>'ه','ە'=>'ه','ڕ'=>'ر','ڵ'=>'ل','ۆ'=>'و','أ'=>'ا','إ'=>'ا','آ'=>'ا'];
    $titles = ['السيد', 'السید', 'سید', 'ملا', 'مەلا', 'شێخ', 'شیخ'];
    $str = str_replace($titles, '', $str);
    return str_replace(array_keys($map), array_values($map), $str);
}

echo "دەستکردن بە هاوردەکردن لە CSV...<br>";

$handle = fopen($csvFile, "r");
$count = 0;

while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    $full_name = trim($data[1] ?? '');
    if (empty($full_name)) continue;

    $address = trim($data[2] ?? '');
    $birth_year = trim($data[3] ?? '');
    $family_name = trim($data[4] ?? '');
    $phone = trim($data[5] ?? '');
    
    // Format phone: if it starts with 7, add 0
    if (!empty($phone) && strlen($phone) >= 10 && $phone[0] !== '0') {
        $phone = '0' . $phone;
    }

    // SPLIT NAME LOGIC
    $cleanName = str_replace(['السيد', 'السید', 'سید', 'ملا', 'مەلا', 'شێخ', 'شیخ'], '', $full_name);
    $parts = preg_split('/\s+/', trim($cleanName), -1, PREG_SPLIT_NO_EMPTY);
    
    $first_name = $parts[0] ?? '';
    $father_name = $parts[1] ?? '';
    $grandfather_name = $parts[2] ?? '';
    $great_grandfather_name = $parts[3] ?? '';
    
    $normalized_name = normalizeForSearch($full_name);
    $birth_date = (!empty($birth_year) && is_numeric($birth_year)) ? fixDate($birth_year . "-01-01") : null;

    // Handle Family
    $family_id = 0;
    if (!empty($family_name)) {
        $f_check = $conn->prepare("SELECT id FROM families WHERE family_name = ?");
        $f_check->bind_param("s", $family_name);
        $f_check->execute();
        $f_res = $f_check->get_result();
        if ($f_row = $f_res->fetch_assoc()) {
            $family_id = $f_row['id'];
        } else {
            $f_in = $conn->prepare("INSERT INTO families (family_name) VALUES (?)");
            $f_in->bind_param("s", $family_name);
            $f_in->execute();
            $family_id = $f_in->insert_id;
        }
    }

    // Insert Member
    $stmt = $conn->prepare("INSERT INTO members (
        full_name, first_name, father_name, grandfather_name, great_grandfather_name, 
        normalized_name, address, birth_date, family_id, phone, member_status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");

    $stmt->bind_param("ssssssssis", 
        $full_name, $first_name, $father_name, $grandfather_name, $great_grandfather_name,
        $normalized_name, $address, $birth_date, $family_id, $phone
    );
    
    if ($stmt->execute()) {
        $m_id = $stmt->insert_id;
        $conn->query("UPDATE members SET member_code = '$m_id' WHERE id = $m_id");
        $count++;
    }
}
fclose($handle);

echo "<br><b>هاوردەکردن تەواو بوو!</b><br>";
echo "بە سەرکەوتوویی $count ئەندام گوازرانەوە لەگەڵ ژمارەی مۆبایل و ناونیشانەکانیان.<br>";
echo "<a href='members_list.php'>بڕۆ بۆ لیستی ئەندامان</a>";
?>
