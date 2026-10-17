<?php
// Environment-aware DB configuration
$is_local_env = false;
if (isset($_SERVER['HTTP_HOST'])) {
    $hname = $_SERVER['HTTP_HOST'];
    if ($hname === 'localhost' || $hname === '127.0.0.1' || $hname === '::1' || strpos($hname, '.test') !== false || strpos($hname, '.local') !== false) {
        $is_local_env = true;
    }
} else {
    $is_local_env = true;
}

if ($is_local_env) {
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_USER')) define('DB_USER', 'root');
    if (!defined('DB_PASS')) define('DB_PASS', '');
    if (!defined('DB_NAME')) define('DB_NAME', 'pirxdry_db');
} else {
    // Online Production (cPanel / Host)
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_USER')) define('DB_USER', 'pirxdry_sangawy');
    if (!defined('DB_PASS')) define('DB_PASS', 'Sabate  12@12');
    if (!defined('DB_NAME')) define('DB_NAME', 'pirxdry_web');
}

function getDB() {
    try {
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            die("<div style='font-family:sans-serif; text-align:center; padding:50px; color:#d32f2f; background:#fff5f5; border:1px solid #ffcdd2; border-radius:10px; max-width:500px; margin:50px auto;'>
                <h2>داتابەیس کار ناکات!</h2>
                <p>تکایە بەرنامەی Poodle بکەرەوە و دڵنیابە کە MySQL کار دەکات (Start All بکە).</p>
                </div>");
        }
        
        $conn->set_charset("utf8mb4");

    // Auto-migrate check (Run once if table missing)
    static $migrated = false;
    if (!$migrated) {
        // Check for table
        $check = $conn->query("SHOW TABLES LIKE 'member_documents'");
        if ($check && $check->num_rows == 0) {
            // Check if columns exist before adding
            $cols = $conn->query("SHOW COLUMNS FROM members LIKE 'id_card_number'");
            if ($cols && $cols->num_rows == 0) {
                $conn->query("ALTER TABLE members ADD COLUMN id_card_number VARCHAR(50) AFTER phone");
            }
            $cols2 = $conn->query("SHOW COLUMNS FROM members LIKE 'security_code'");
            if ($cols2 && $cols2->num_rows == 0) {
                $conn->query("ALTER TABLE members ADD COLUMN security_code VARCHAR(50) AFTER id_card_number");
            }
            
            $conn->query("CREATE TABLE IF NOT EXISTS member_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT,
                doc_type VARCHAR(100),
                doc_path VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        $checkReq = $conn->query("SHOW TABLES LIKE 'member_requests'");
        if ($checkReq && $checkReq->num_rows == 0) {
            $conn->query("CREATE TABLE IF NOT EXISTS member_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                request_type VARCHAR(50) DEFAULT 'add_child',
                child_name VARCHAR(255) NOT NULL,
                gender ENUM('نێر', 'مێ') DEFAULT 'نێر',
                birth_date DATE NULL,
                birth_year INT NULL,
                notes TEXT NULL,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                admin_notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_member (member_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }
        $migrated = true;
    }

    return $conn;
    } catch (Throwable $e) {
        die("<div style='font-family:sans-serif; text-align:center; padding:50px; color:#d32f2f; background:#fff5f5; border:1px solid #ffcdd2; border-radius:10px; max-width:500px; margin:50px auto;'>
            <h2>داتابەیس کار ناکات!</h2>
            <p>تکایە بەرنامەی Poodle بکەرەوە و دڵنیابە کە MySQL کار دەکات (Start All بکە).</p>
            <p style='font-size:12px; color:#666;'>{$e->getMessage()}</p>
            </div>");
    }
}

function isDBConnected() {
    try {
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            return false;
        }
        $conn->close();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

if (!defined('INCLUDED_CONFIG')) {
    define('INCLUDED_CONFIG', true);
}

function logAction($user_id, $action, $details = null, $doc_id = null) {
    $conn = getDB();
    if (!$conn) return;
    
    $user_id = (int)$user_id;
    $action = $conn->real_escape_string($action);
    $details = $details ? "'" . $conn->real_escape_string($details) . "'" : "NULL";
    $doc_id = $doc_id ? (int)$doc_id : "NULL";
    
    $conn->query("INSERT INTO action_logs (user_id, doc_id, action, details) VALUES ($user_id, $doc_id, '$action', $details)");
}
function logAudit($conn, $userId, $action, $tableName, $recordId, $oldValues = null, $newValues = null) {
    if (!$conn) return;
    
    // Auto-create table if missing
    $conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(50) NOT NULL,
        table_name VARCHAR(100) NOT NULL,
        record_id INT,
        old_values TEXT,
        new_values TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $oldValuesJson = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
    $newValuesJson = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
    
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ississs", $userId, $action, $tableName, $recordId, $oldValuesJson, $newValuesJson, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Normalizes Eastern Arabic numerals (٠١٢٣٤٥٦٧٨٩) to Western Arabic numerals (0123456789)
 */
function normalizeNumerals($string) {
    if (!$string) return $string;
    $eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    return str_replace($eastern, $western, $string);
}

/**
 * Fixes date strings by normalizing numerals and ensuring a 4-digit year.
 * Specifically handles cases where 2-digit years or 00xx years are provided.
 */
function fixDate($date) {
    if (empty($date)) return null;
    $date = normalizeNumerals($date);
    
    // Handle YYYY-MM-DD where year is 00xx
    if (preg_match('/^00(\d{2})-(\d{2})-(\d{2})$/', $date, $matches)) {
        return "19" . $matches[1] . "-" . $matches[2] . "-" . $matches[3];
    }
    
    // Handle YY-MM-DD
    if (preg_match('/^(\d{2})-(\d{2})-(\d{2})$/', $date, $matches)) {
        $year = (int)$matches[1];
        $prefix = ($year > 30) ? "19" : "20";
        return $prefix . $matches[1] . "-" . $matches[2] . "-" . $matches[3];
    }
    
    return $date;
}

/**
 * Extracts the core first name from a full name, removing common Kurdish/Arabic titles
 * and correctly handling compound names (e.g., عبد السلام, علاء الدين).
 */
function extractFirstName($fullName) {
    if (empty($fullName)) return '';
    
    // Remove parenthetical nicknames
    $cleaned = preg_replace('/\s*\(.*?\)/u', '', $fullName);
    $cleaned = preg_replace('/\s+الملقب\s+(?:بـ|ب)?\s*[\p{L}\s]+/u', '', $cleaned);
    $cleaned = preg_replace('/\s+في\s+[\p{L}\s]+/u', '', $cleaned);
    $cleaned = trim(preg_replace('/\s+/u', ' ', $cleaned));

    $titles = [
        'العلامة', 'العلامه', 
        'الحاجی', 'الحاجى', 'الحاج', 'حاجی', 'حاجى', 'حاج',
        'الشيخ', 'الشیخ', 'شێخ', 'شیخ', 'بابەشێخ', 'بابە شێخ', 'بابەشیخ',
        'ملا', 'مەلا', 'مولا', 
        'السيد', 'السید', 'سید', 'سەید', 
        'دکتۆر', 'دكتور', 'د.', 'مامۆستا', 'الأستاذ', 'الاستاذ',
        'کاکە', 'کاک'
    ];
    usort($titles, function($a, $b) { return mb_strlen($b) - mb_strlen($a); });

    $changed = true;
    while ($changed) {
        $changed = false;
        foreach ($titles as $t) {
            if (mb_strpos($cleaned, $t . ' ') === 0 || $cleaned === $t) {
                $cleaned = trim(mb_substr($cleaned, mb_strlen($t)));
                $changed = true;
                break;
            }
        }
    }

    if (empty($cleaned)) return '';

    $doubleNames = [
        'محمد امین', 'محمد أمين', 'محمد علی', 'محمد صالح', 'محمد باقر', 'محمد تقی', 'محمد جواد', 'محمد مهدی', 'محمد صادق', 'محمد حسین', 'محمد حسن', 'محمد رشید', 'محمد نوری', 'محمد رؤوف', 'محمد ڕؤوف', 'محمد صدیق',
        'علی رضا', 'علی اصغر', 'علی اکبر', 'علی حیدر', 'علی نقی',
        'کاکه حمه', 'کاکە حەمە', 'کاکه ڕەش', 'کاکە ڕەش', 'کاکه رش',
        'حمه امین', 'حەمە امین', 'حمه صالح', 'حەمە صالح', 'حمه رشید', 'حەمە رشید'
    ];
    foreach ($doubleNames as $dn) {
        if (mb_strpos($cleaned, $dn . ' ') === 0 || $cleaned === $dn) {
            return $dn;
        }
    }

    $parts = preg_split('/\s+/', $cleaned);
    $first = $parts[0] ?? '';
    $second = $parts[1] ?? '';
    
    $compounds = ['عبد', 'صلاح', 'علاء', 'علأ', 'علا', 'سيف', 'نور', 'فخر', 'بهاء', 'جمال', 'کمال', 'نجم', 'تاج', 'عماد', 'بدر', 'شمس', 'شهاب', 'شرف', 'ضياء', 'غیاث', 'حسام', 'محی', 'نصر'];
    $suffixes = ['الدین', 'الدين', 'الله', 'اللە', 'الرحمن', 'الکریم', 'الرضا', 'الصادق', 'الباقر', 'الکاظم', 'الجواد', 'الهادی', 'العزیز', 'الخالق', 'السلام', 'القادر', 'اللطیف'];

    if (in_array($first, $compounds) && !empty($second)) {
        return $first . ' ' . $second;
    }
    if (in_array($second, $suffixes)) {
        return $first . ' ' . $second;
    }
    return $first;
}

/**
 * Builds a strict 4-part name (ناوی چواری: کەس، باوک، باپیر، باپیری گەورە)
 * Never returns more than 4 names. Strips all honorific titles.
 */
function buildFourPartName($row) {
    if (empty($row)) return '';

    // 1. If 4 distinct name columns are filled in the database
    $f1 = trim($row['first_name'] ?? '');
    $f2 = trim($row['father_name'] ?? '');
    $f3 = trim($row['grandfather_name'] ?? '');
    $f4 = trim($row['great_grandfather_name'] ?? '');

    $c1 = extractFirstName($f1);
    $c2 = extractFirstName($f2);
    $c3 = extractFirstName($f3);
    $c4 = extractFirstName($f4);

    if (!empty($c1) && !empty($c2) && !empty($c3) && !empty($c4)) {
        return "$c1 $c2 $c3 $c4";
    }

    // 2. Extract from full_name
    $fullName = trim($row['full_name'] ?? '');
    
    // Remove parenthetical nicknames/aliases e.g. (ڕەشەکانی)
    $clean = preg_replace('/\s*\(.*?\)/u', '', $fullName);
    $clean = trim(preg_replace('/\s+/u', ' ', $clean));

    // Strip titles from the start
    $titlePrefixes = [
        'السيد ملا', 'السید مەلا', 'السيد شێخ', 'السید شیخ',
        'العلامة', 'العلامه', 'الحاجی', 'الحاجى', 'الحاج', 'حاجی', 'حاجى', 'حاج',
        'الشيخ', 'الشیخ', 'شێخ', 'شیخ', 'ملا', 'مەلا', 'مولا', 
        'السيد', 'السید', 'سید', 'سەید', 
        'دکتۆر', 'دكتور', 'د.', 'مامۆستا', 'الأستاذ', 'الاستاذ',
        'کاکە', 'کاک'
    ];
    usort($titlePrefixes, function($a, $b) { return mb_strlen($b) - mb_strlen($a); });

    $changed = true;
    while ($changed) {
        $changed = false;
        foreach ($titlePrefixes as $t) {
            if (mb_strpos($clean, $t . ' ') === 0) {
                $clean = trim(mb_substr($clean, mb_strlen($t)));
                $changed = true;
                break;
            }
        }
    }

    // Parse into tokens respecting compounds and double names
    $names = [];
    $words = preg_split('/\s+/u', $clean);
    $wCount = count($words);

    $compoundsPrefix = ['عبد', 'عەبد', 'عەبدول', 'عبدال', 'صلاح', 'علاء', 'علأ', 'علا', 'سيف', 'سیف', 'نور', 'فخر', 'بهاء', 'جمال', 'کمال', 'كمال', 'نجم', 'تاج', 'عماد', 'بدر', 'شمس', 'شهاب', 'شرف', 'ضياء', 'غیاث', 'حسام', 'محی', 'محي', 'تقي', 'تقی', 'نصر'];
    $compoundsSuffix = ['الدین', 'الدين', 'ئەدین', 'ئەلدین', 'الاسلام', 'الله', 'اللە', 'الرحمن', 'الکریم', 'الرضا', 'الصادق', 'الباقر', 'الکاظم', 'الجواد', 'الهادی', 'العزیز', 'الخالق', 'السلام', 'القادر', 'اللطیف'];
    $doubleFirst = [
        'محمد' => ['امین', 'أمين', 'علی', 'صالح', 'باقر', 'تقی', 'جواد', 'مهدی', 'صادق', 'حسین', 'حسن', 'رشید', 'نوری', 'رؤوف', 'ڕؤوف', 'صدیق', 'رضا', 'فاضل', 'کامل', 'محمود', 'طاهر'],
        'محەمەد' => ['امین', 'أمين', 'علی', 'صالح', 'باقر', 'تقی', 'جواد', 'مهدی', 'صادق', 'حسین', 'حسن', 'رشید', 'نوری', 'رؤوف', 'ڕؤوف', 'صدیق', 'رضا', 'فاضل', 'کامل', 'محمود', 'طاهر'],
        'علی' => ['رضا', 'اصغر', 'اکبر', 'حیدر', 'نقی'],
        'حمه' => ['امین', 'ساڵح', 'صالح', 'رشید', 'علی', 'قادر', 'فرج'],
        'حەمە' => ['امین', 'ساڵح', 'صالح', 'رشید', 'علی', 'قادر', 'فرج'],
        'کاکە' => ['حەمە', 'ڕەش'],
        'کاکه' => ['حەمە', 'ڕەش', 'رش']
    ];

    $i = 0;
    while ($i < $wCount && count($names) < 4) {
        $w = $words[$i];
        $nextW = $words[$i + 1] ?? '';

        // Check if word is a title that appeared mid-chain (e.g. "الحاج" or "العلامة") - skip it!
        if (in_array($w, ['الحاج', 'الحاجی', 'الحاجى', 'حاجی', 'حاج', 'العلامة', 'العلامه', 'الشيخ', 'الشیخ', 'شێخ', 'شیخ', 'ملا', 'مەلا', 'سید', 'السید', 'السيد', 'بن', 'ابن'])) {
            $i++;
            continue;
        }

        // 1. Prefix compound (e.g. عبد الرحمن, علاء الدين)
        if (in_array($w, $compoundsPrefix) && !empty($nextW)) {
            $names[] = $w . ' ' . $nextW;
            $i += 2;
            continue;
        }

        // 2. Suffix compound (e.g. بهاءالدين where split)
        if (in_array($nextW, $compoundsSuffix)) {
            $names[] = $w . ' ' . $nextW;
            $i += 2;
            continue;
        }

        // 3. Double names (e.g. محمد امین, علی رضا, حمه صالح)
        if (isset($doubleFirst[$w]) && in_array($nextW, $doubleFirst[$w])) {
            $names[] = $w . ' ' . $nextW;
            $i += 2;
            continue;
        }

        $names[] = $w;
        $i++;
    }

    // 3. If fewer than 4 names, fill remaining from ancestor tree (p1, p2, p3)
    if (count($names) < 4 && !empty($row['p1_name'])) {
        $p1 = extractFirstName($row['p1_name']);
        if (!empty($p1) && !in_array($p1, $names)) $names[] = $p1;
    }
    if (count($names) < 4 && !empty($row['p2_name'])) {
        $p2 = extractFirstName($row['p2_name']);
        if (!empty($p2) && !in_array($p2, $names)) $names[] = $p2;
    }
    if (count($names) < 4 && !empty($row['p3_name'])) {
        $p3 = extractFirstName($row['p3_name']);
        if (!empty($p3) && !in_array($p3, $names)) $names[] = $p3;
    }

    // Strictly limit to at most 4 names
    $names = array_slice($names, 0, 4);

    return !empty($names) ? implode(' ', $names) : extractFirstName($fullName);
}

