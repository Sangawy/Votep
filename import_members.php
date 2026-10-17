<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$message = "";
$message_type = "";

// Helper to normalize name (Keeping spaces now)
function normalizeForSearch($str) {
    $map = ['ێ'=>'ی','ي'=>'ی','ى'=>'ی','ك'=>'ک','ة'=>'ه','ە'=>'ه','ڕ'=>'ر','ڵ'=>'ل','ۆ'=>'و','أ'=>'ا','إ'=>'ا','آ'=>'ا'];
    $titles = ['السيد', 'السید', 'سید', 'ملا', 'مەلا', 'شێخ', 'شیخ'];
    $str = str_replace($titles, '', $str);
    return str_replace(array_keys($map), array_values($map), $str);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    if (empty($file)) {
        $message = "تکایە فایلێک هەڵبژێرە.";
        $message_type = "danger";
    } else {
        $handle = fopen($file, "r");
        $count = 0;
        
        // Skip header
        fgetcsv($handle, 1000, ",");
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $full_name = trim($data[1] ?? '');
            if (empty($full_name)) continue;

            $address = trim($data[2] ?? '');
            $birth_year = trim($data[3] ?? '');
            $family_name = trim($data[4] ?? '');
            $phone = trim($data[5] ?? '');
            
            // SPLIT NAME LOGIC
            $cleanName = str_replace(['السيد', 'السید', 'سید', 'ملا', 'مەلا', 'شێخ', 'شیخ'], '', $full_name);
            $parts = preg_split('/\s+/', trim($cleanName), -1, PREG_SPLIT_NO_EMPTY);
            
            $first_name = $parts[0] ?? '';
            $father_name = $parts[1] ?? '';
            $grandfather_name = $parts[2] ?? '';
            $great_grandfather_name = $parts[3] ?? '';
            
            $normalized_name = normalizeForSearch($full_name);
            $birth_date = (!empty($birth_year) && is_numeric($birth_year)) ? fixDate($birth_year . "-01-01") : null;

            // Duplicate Check
            $check = $conn->prepare("SELECT id FROM members WHERE normalized_name = ? LIMIT 1");
            $check->bind_param("s", $normalized_name);
            $check->execute();
            if ($row = $check->get_result()->fetch_assoc()) {
                $m_id = $row['id'];
                $up = $conn->prepare("UPDATE members SET phone=?, address=?, birth_date=?, first_name=?, father_name=?, grandfather_name=?, great_grandfather_name=? WHERE id=?");
                $up->bind_param("sssssssi", $phone, $address, $birth_date, $first_name, $father_name, $grandfather_name, $great_grandfather_name, $m_id);
                $up->execute();
            } else {
                // Family handling
                $family_id = 0;
                if (!empty($family_name)) {
                    $f_check = $conn->prepare("SELECT id FROM families WHERE family_name = ?");
                    $f_check->bind_param("s", $family_name);
                    $f_check->execute();
                    $f_res = $f_check->get_result();
                    if ($f_row = $f_res->fetch_assoc()) { $family_id = $f_row['id']; }
                    else {
                        $f_in = $conn->prepare("INSERT INTO families (family_name) VALUES (?)");
                        $f_in->bind_param("s", $family_name);
                        $f_in->execute();
                        $family_id = $f_in->insert_id;
                    }
                }

                $stmt = $conn->prepare("INSERT INTO members (full_name, first_name, father_name, grandfather_name, great_grandfather_name, normalized_name, address, birth_date, family_id, phone, member_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
                $stmt->bind_param("ssssssssis", $full_name, $first_name, $father_name, $grandfather_name, $great_grandfather_name, $normalized_name, $address, $birth_date, $family_id, $phone);
                $stmt->execute();
                $m_id = $stmt->insert_id;
                $conn->query("UPDATE members SET member_code = '$m_id' WHERE id = $m_id");
                $count++;
            }
        }
        fclose($handle);

        $message = "بە سەرکەوتوویی $count ئەندام هاوردە کران. ئێستا هەموو ناوەکان بە شێوەی چوارینە پۆلێن کراون.";
        $message_type = "success";
    }
}

$page_title = 'هاوردەکردنی ئەندامان لە ئەکسەل';
$active_nav = 'members_new';
require_once 'includes/layout.php';
?>

<div class="lib-card" style="max-width: 800px; margin: 40px auto; padding: 40px;">
    <div style="text-align: center; margin-bottom: 30px;">
        <i class="fas fa-file-import" style="font-size: 3rem; color: var(--gold);"></i>
        <h2 style="margin-top: 15px;">هاوردەکردنی پێشەنگ</h2>
        <p style="color: #666;">ناوەکان بە ئۆتۆماتیکی دابەش دەبن بۆ ناوی (یەکەم، باوک، باپیر، باپیری گەورە).</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>" style="padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; background: #d4edda; color: #155724;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" style="background: #f8f9fa; padding: 30px; border-radius: 15px; border: 2px dashed #ddd;">
        <div style="margin-bottom: 25px;">
            <label style="display: block; font-weight: bold; margin-bottom: 10px;">فایلی CSV هەڵبژێرە:</label>
            <input type="file" name="csv_file" accept=".csv" required style="width: 100%; padding: 10px; background: white; border: 1px solid #ccc; border-radius: 8px;">
        </div>

        <button type="submit" class="lib-btn lib-btn-gold" style="width: 100%; padding: 15px; font-size: 1.1rem;">
            🚀 دەستپێکردنی هاوردەکردن
        </button>
    </form>
</div>

<?php require_once 'includes/layout_end.php'; ?>
