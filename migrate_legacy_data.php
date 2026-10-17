<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

require_once 'config.php';
$new_conn = getDB();

// Source Database connection (election_system)
$old_conn = new mysqli(DB_HOST, DB_USER, DB_PASS, 'election_system');
if ($old_conn->connect_error) {
    die("Error connecting to legacy database: " . $old_conn->connect_error);
}
$old_conn->set_charset("utf8mb4");

// 1. Clear current data in new DB
echo "پاککردنەوەی داتابەیسی نوێ...<br>";
$new_conn->query("SET FOREIGN_KEY_CHECKS = 0");
$new_conn->query("TRUNCATE TABLE member_documents");
$new_conn->query("TRUNCATE TABLE members");
$new_conn->query("TRUNCATE TABLE families");
$new_conn->query("SET FOREIGN_KEY_CHECKS = 1");

// Helper for normalization (Keeping spaces)
function normalizeForSearch($str) {
    $map = ['ێ'=>'ی','ي'=>'ی','ى'=>'ی','ك'=>'ک','ة'=>'ه','ە'=>'ه','ڕ'=>'ر','ڵ'=>'ل','ۆ'=>'و','أ'=>'ا','إ'=>'ا','آ'=>'ا'];
    $titles = ['السيد', 'السید', 'سید', 'ملا', 'مەلا', 'شێخ', 'شیخ'];
    $str = str_replace($titles, '', $str);
    return str_replace(array_keys($map), array_values($map), $str);
}

echo "دەستکردن بە گواستنەوەی زانیارییەکان...<br>";

// 2. Fetch all from legacy
$res = $old_conn->query("SELECT * FROM members ORDER BY id ASC");
$count = 0;

while ($row = $res->fetch_assoc()) {
    $full_name = trim($row['full_name']);
    if (empty($full_name)) continue;

    // SPLIT NAME LOGIC
    $cleanName = str_replace(['السيد', 'السید', 'سید', 'ملا', 'مەلا', 'شێخ', 'شیخ'], '', $full_name);
    $parts = preg_split('/\s+/', trim($cleanName), -1, PREG_SPLIT_NO_EMPTY);
    
    $first_name = $parts[0] ?? '';
    $father_name = $parts[1] ?? '';
    $grandfather_name = $parts[2] ?? '';
    $great_grandfather_name = $parts[3] ?? '';
    
    $normalized_name = normalizeForSearch($full_name);
    $address = $row['address'] ?? '';
    $birth_date = $row['birth_date'] ?? null;
    $phone = $row['phone'] ?? '';
    $education = $row['education_level'] ?? '';
    $profession = $row['profession'] ?? '';
    $family_name = $row['family_name'] ?? '';
    $nickname = $row['nickname'] ?? '';
    $blood_group = $row['blood_group'] ?? '';
    $languages = $row['language'] ?? '';
    $member_rank = $row['membership_type'] ?? 'ئەندام';
    $member_post = $row['position'] ?? '';

    // Family handling in NEW DB
    $family_id = 0;
    if (!empty($family_name)) {
        $f_check = $new_conn->prepare("SELECT id FROM families WHERE family_name = ?");
        $f_check->bind_param("s", $family_name);
        $f_check->execute();
        $f_res = $f_check->get_result();
        if ($f_row = $f_res->fetch_assoc()) {
            $family_id = $f_row['id'];
        } else {
            $f_in = $new_conn->prepare("INSERT INTO families (family_name) VALUES (?)");
            $f_in->bind_param("s", $family_name);
            $f_in->execute();
            $family_id = $f_in->insert_id;
        }
    }

    // Insert into NEW DB
    $stmt = $new_conn->prepare("INSERT INTO members (
        full_name, first_name, father_name, grandfather_name, great_grandfather_name, 
        normalized_name, nickname, member_rank, member_post, family_id, 
        education, address, birth_date, languages, blood_group, 
        phone, profession, member_status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");

    $stmt->bind_param("sssssssssisssssss", 
        $full_name, $first_name, $father_name, $grandfather_name, $great_grandfather_name,
        $normalized_name, $nickname, $member_rank, $member_post, $family_id,
        $education, $address, $birth_date, $languages, $blood_group,
        $phone, $profession
    );
    
    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        $new_conn->query("UPDATE members SET member_code = '$new_id' WHERE id = $new_id");
        $count++;
    } else {
        echo "Error importing $full_name: " . $stmt->error . "<br>";
    }
}

echo "<br><b>گواستنەوە تەواو بوو!</b><br>";
echo "بە سەرکەوتوویی $count ئەندام گوازرانەوە و ناوەکانیان بە ٤ ئاست دابەش کرا.<br>";
echo "<a href='members_list.php'>بگەڕێوە بۆ لیستی ئەندامان</a>";

$old_conn->close();
?>
