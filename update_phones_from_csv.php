<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

require_once 'config.php';
$conn = getDB();

$csvFile = 'f:/laragon/www/pirxdry/File/لیستی ناوی ئەندامان داتا بەیس.csv';

if (!file_exists($csvFile)) {
    die("Error: CSV file not found at $csvFile");
}

// Helper for normalization
function normalizeForSearch($str) {
    $map = ['ێ'=>'ی','ي'=>'ی','ى'=>'ی','ك'=>'ک','ة'=>'ه','ە'=>'ه','ڕ'=>'ر','ڵ'=>'ل','ۆ'=>'و','أ'=>'ا','إ'=>'ا','آ'=>'ا'];
    $titles = ['السيد', 'السید', 'سید', 'ملا', 'مەلا', 'شێخ', 'شیخ'];
    $str = str_replace($titles, '', $str);
    return str_replace(array_keys($map), array_values($map), $str);
}

echo "دەستکردن بە نوێکردنەوەی ژمارەی مۆبایلەکان بە پێی ناو و بنەماڵە...<br>";

$handle = fopen($csvFile, "r");
$updated = 0;
$not_found = 0;

while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
    $csv_full_name = trim($data[1] ?? '');
    $csv_family_name = trim($data[4] ?? ''); // 5th column is family/village
    $csv_phone = trim($data[5] ?? '');
    
    if (empty($csv_full_name) || empty($csv_phone)) continue;

    // Format phone
    if (!empty($csv_phone) && $csv_phone[0] !== '0' && strlen($csv_phone) >= 10) {
        $csv_phone = '0' . $csv_phone;
    }

    $norm_name = normalizeForSearch($csv_full_name);
    $norm_family = normalizeForSearch($csv_family_name);

    // Search for member using name AND joining with families table
    $stmt = $conn->prepare("SELECT m.id FROM members m 
                            LEFT JOIN families f ON m.family_id = f.id 
                            WHERE m.normalized_name = ? 
                            AND (f.family_name = ? OR ? = '') 
                            LIMIT 1");
    $stmt->bind_param("sss", $norm_name, $csv_family_name, $csv_family_name);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $m_id = $row['id'];
        $up = $conn->prepare("UPDATE members SET phone = ? WHERE id = ?");
        $up->bind_param("si", $csv_phone, $m_id);
        if ($up->execute()) {
            $updated++;
        }
        $up->close();
    } else {
        // Try fallback search: maybe family is part of address or slightly different
        // If not found with exact family, try just the name again but only if we are very sure
        $stmt2 = $conn->prepare("SELECT id FROM members WHERE normalized_name = ? LIMIT 1");
        $stmt2->bind_param("s", $norm_name);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($row2 = $res2->fetch_assoc()) {
            $m_id = $row2['id'];
            $up2 = $conn->prepare("UPDATE members SET phone = ? WHERE id = ?");
            $up2->bind_param("si", $csv_phone, $m_id);
            if ($up2->execute()) $updated++;
            $up2->close();
        } else {
            $not_found++;
        }
        $stmt2->close();
    }
    $stmt->close();
}
fclose($handle);

echo "<br><b>کردارەکە تەواو بوو!</b><br>";
echo "بە سەرکەوتوویی ژمارەی مۆبایلی $updated ئەندام نوێکرایەوە.<br>";
echo "زانیاری $not_found ئەندام لە داتابەیسەکەدا نەدۆزرانەوە.<br>";
echo "<a href='members_list.php'>بڕۆ بۆ لیستی ئەندامان</a>";
?>
