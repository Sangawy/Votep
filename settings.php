<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') { header('Location: dashboard.php'); exit; }

require_once 'config.php';
$conn = getDB();

// Release session lock to allow concurrent AJAX polling requests for updates to proceed
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    session_write_close();
    header('Content-Type: application/json');
    $step = $_GET['step'] ?? '';
    $progress_file = __DIR__ . '/download_progress.json';
    define('UPDATE_JSON_URL', 'https://pirxdry.org/updates/version.json');
    
    // Quick progress status check (no remote HTTP requests)
    if ($step === 'download_progress') {
        if (file_exists($progress_file)) {
            echo file_get_contents($progress_file);
        } else {
            echo json_encode([
                'total' => 0,
                'downloaded' => 0,
                'percent' => 0,
                'status' => 'idle'
            ]);
        }
        exit;
    }
    
    // Fetch remote version data again for downloading/extracting/migrating
    $context = stream_context_create([
        "http" => [
            "timeout" => 15,
            "user_agent" => "PHP-Updater-Client",
            "follow_location" => 1
        ]
    ]);
    $json_data = @file_get_contents(UPDATE_JSON_URL, false, $context);
    if (!$json_data) {
        echo json_encode(['success' => false, 'message' => 'ناتوانرێت زانیاری نوێکردنەوە لە سێرڤەری ئۆنلاینەوە وەربگیرێت.']);
        exit;
    }
    $remote_info = json_decode($json_data, true);
    
    switch ($step) {
        case 'download_start':
            // Start of chunked download: get file size and clear temp files
            $download_url = $remote_info['download_url'];
            $temp_zip = __DIR__ . '/temp_update.zip';
            
            if (file_exists($temp_zip)) {
                @unlink($temp_zip);
            }
            if (file_exists($progress_file)) {
                @unlink($progress_file);
            }
            
            // Fetch total size via HEAD request
            $total_bytes = 0;
            $dl_context = stream_context_create([
                "http" => [
                    "method" => "HEAD",
                    "timeout" => 15,
                    "user_agent" => "PHP-Updater-Client",
                    "follow_location" => 1
                ]
            ]);
            $headers = @get_headers($download_url, 1, $dl_context);
            if ($headers) {
                if (isset($headers['Content-Length'])) {
                    $total_bytes = is_array($headers['Content-Length']) ? (int)end($headers['Content-Length']) : (int)$headers['Content-Length'];
                } elseif (isset($headers['content-length'])) {
                    $total_bytes = is_array($headers['content-length']) ? (int)end($headers['content-length']) : (int)$headers['content-length'];
                }
            }
            
            if ($total_bytes <= 0) {
                // Try a quick stream open if HEAD failed to return content-length
                $stream = @fopen($download_url, 'r', false, stream_context_create([
                    "http" => [
                        "timeout" => 10,
                        "user_agent" => "PHP-Updater-Client",
                        "follow_location" => 1
                    ]
                ]));
                if ($stream) {
                    $meta = stream_get_meta_data($stream);
                    $wrapper_data = $meta['wrapper_data'] ?? [];
                    foreach ($wrapper_data as $hdr) {
                        if (preg_match('/^Content-Length:\s*(\d+)/i', $hdr, $m)) {
                            $total_bytes = (int)$m[1];
                        }
                    }
                    fclose($stream);
                }
            }
            
            if ($total_bytes <= 0) {
                // Fallback to known size of update_1.2.0.zip
                $total_bytes = 275071099;
            }
            
            file_put_contents($progress_file, json_encode([
                'total' => $total_bytes,
                'downloaded' => 0,
                'percent' => 0,
                'status' => 'downloading'
            ]));
            
            echo json_encode([
                'success' => true,
                'total' => $total_bytes,
                'chunk_size' => 5 * 1024 * 1024 // 5MB chunk size
            ]);
            break;
            
        case 'download_chunk':
            // Download a single chunk (byte range) and append it to disk
            $start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
            $end = isset($_GET['end']) ? (int)$_GET['end'] : 0;
            $download_url = $remote_info['download_url'];
            $temp_zip = __DIR__ . '/temp_update.zip';
            
            $dl_context = stream_context_create([
                "http" => [
                    "method" => "GET",
                    "header" => "Range: bytes=$start-$end\r\n",
                    "timeout" => 60,
                    "user_agent" => "PHP-Updater-Client",
                    "follow_location" => 1
                ]
            ]);
            
            $chunk_data = @file_get_contents($download_url, false, $dl_context);
            if ($chunk_data === false) {
                echo json_encode(['success' => false, 'message' => "کێشە لە داونلۆدکردنی پارچەی $start-$end"]);
                exit;
            }
            
            $fp = @fopen($temp_zip, 'ab');
            if (!$fp) {
                echo json_encode(['success' => false, 'message' => 'شکست لە دروستکردن یان کردنەوەی فایلی کاتی ZIP.']);
                exit;
            }
            flock($fp, LOCK_EX);
            $bytes_written = fwrite($fp, $chunk_data);
            flock($fp, LOCK_UN);
            fclose($fp);
            
            if ($bytes_written === false) {
                echo json_encode(['success' => false, 'message' => 'شکست لە نووسینی داتا لەسەر دیسک.']);
                exit;
            }
            
            // Read total size from progress file to compute actual percentage
            $total_bytes = 0;
            if (file_exists($progress_file)) {
                $prog = json_decode(file_get_contents($progress_file), true);
                $total_bytes = isset($prog['total']) ? (int)$prog['total'] : 0;
            }
            
            $current_size = file_exists($temp_zip) ? filesize($temp_zip) : 0;
            $percent = $total_bytes > 0 ? round(($current_size / $total_bytes) * 100) : 0;
            if ($percent > 100) $percent = 100;
            
            file_put_contents($progress_file, json_encode([
                'total' => $total_bytes,
                'downloaded' => $current_size,
                'percent' => $percent,
                'status' => ($percent >= 100) ? 'done' : 'downloading'
            ]));
            
            echo json_encode([
                'success' => true,
                'downloaded' => $current_size,
                'percent' => $percent
            ]);
            break;
            
        case 'extract':
            // Step 2: Extract files
            $temp_zip = __DIR__ . '/temp_update.zip';
            if (file_exists($progress_file)) {
                @unlink($progress_file); // Clean progress file
            }
            
            if (!file_exists($temp_zip)) {
                echo json_encode(['success' => false, 'message' => 'فایلی دابەزێنراو بوونی نییە.']);
                exit;
            }
            
            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive;
                if ($zip->open($temp_zip) === TRUE) {
                    // Extract files one by one, skipping online_updater.php, settings.php, and config.php
                    // to prevent overwriting our AJAX-enabled updater, settings, or local DB credentials
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        $base = basename($filename);
                        if ($base === 'online_updater.php' || $base === 'config.php' || $base === 'settings.php') {
                            continue;
                        }
                        
                        // Extract this single entry
                        $zip->extractTo(__DIR__ . '/', $filename);
                    }
                    $zip->close();
                    @unlink($temp_zip); // delete temp zip
                    echo json_encode(['success' => true, 'message' => 'فایلە نوێیەکان بە سەرکەوتوویی خرانە شوێنی خۆیان.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'پەڕگە تێکچووە؛ ناتوانرێت فۆڵدەری ZIP بکرێتەوە.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ZipArchive لەسەر سێرڤەر کارا نییە.']);
            }
            break;
            
        case 'migrate':
            // Step 3: Run Database Migrations and update version
            $fix_db_file = __DIR__ . '/fix_database_final.php';
            $db_log = '';
            
            if (file_exists($fix_db_file)) {
                ob_start();
                include $fix_db_file;
                $db_log = ob_get_clean();
            }
            
            // Update version in settings table
            $new_ver = $conn->real_escape_string($remote_info['version']);
            $conn->query("UPDATE settings SET current_version = '$new_ver' WHERE id = 1");
            
            echo json_encode([
                'success' => true, 
                'message' => 'داتابەیس بە سەرکەوتوویی نوێکرایەوە.',
                'db_log' => $db_log,
                'new_version' => $remote_info['version']
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'کرداری نادیار.']);
    }
    exit;
}

// Self-healing: ensure audit_logs table exists
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

// Self-healing: Ensure settings table has all required columns
$checkCols = $conn->query("SHOW COLUMNS FROM settings");
$existingCols = [];
if ($checkCols) {
    while($col = $checkCols->fetch_assoc()) $existingCols[] = $col['Field'];
}

$required_cols = [
    'system_subtitle'  => 'VARCHAR(255) DEFAULT "وەزارەتی پێشمەرگە"',
    'sidebar_color'    => 'VARCHAR(50) DEFAULT "#5a0202"',
    'archive_base_dir' => 'VARCHAR(255) DEFAULT "D:/Archive/"',
    'description'      => 'TEXT',
    'owner_name'       => 'VARCHAR(255) DEFAULT "sangawy"',
    'remote_sync_host' => 'VARCHAR(255)',
    'remote_sync_db'   => 'VARCHAR(255)',
    'remote_sync_user' => 'VARCHAR(255)',
    'remote_sync_pass' => 'VARCHAR(255)',
    'remote_sync_port' => 'VARCHAR(10) DEFAULT "3306"',
    'remote_sync_key'  => 'VARCHAR(255) DEFAULT "PIRX_SECURE_SYNC_2026"'
];


foreach ($required_cols as $col => $definition) {
    if (!in_array($col, $existingCols)) {
        $conn->query("ALTER TABLE settings ADD COLUMN $col $definition");
    }
}

$message = ''; $messageType = '';
$active_tab = $_GET['tab'] ?? 'general';

// Handle sections updates (Visibility + Text)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sections'])) {
    if (isset($_POST['sections'])) {
        foreach ($_POST['sections'] as $sid => $data) {
            $sid = (int)$sid;
            $title = trim($data['title'] ?? '');
            $desc = trim($data['description'] ?? '');
            $is_active = isset($_POST['section_active_' . $sid]) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE system_sections SET section_title = ?, section_description = ?, is_active = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("ssii", $title, $desc, $is_active, $sid);
                $stmt->execute();
                $stmt->close();
            }
        }
        $message = 'گۆڕانکارییەکانی کارتەکان بە سەرکەوتوویی پاشەکەوت کران ✅';
        $messageType = 'success';
        $active_tab = 'sections';
    }
}

// Ensure at least one row exists in settings table
$checkRow = $conn->query("SELECT id, remote_sync_key FROM settings WHERE id = 1");
if ($checkRow && $checkRow->num_rows == 0) {
    $conn->query("INSERT INTO settings (id, system_name, description, remote_sync_key) VALUES (1, 'کتێبخانەی پێشمەرگە', 'سیستەمی بەڕێوەبردنی کتێبخانە', 'PIRX_SECURE_SYNC_2026')");
} else {
    $row = $checkRow->fetch_assoc();
    if (empty($row['remote_sync_key'])) {
        $conn->query("UPDATE settings SET remote_sync_key = 'PIRX_SECURE_SYNC_2026' WHERE id = 1");
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $system_name = trim($_POST['system_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $owner_name  = trim($_POST['owner_name'] ?? '');
    $system_subtitle = trim($_POST['system_subtitle'] ?? 'وەزارەتی پێشمەرگە');
    $sidebar_color = trim($_POST['sidebar_color'] ?? '#5a0202');
    $archive_base_dir = trim($_POST['archive_base_dir'] ?? 'D:/Archive/');
    
    if (empty($system_name)) { 
        $message = 'ناوی سیستەم پێویستە'; $messageType = 'error'; 
    } else {




        $stmt = $conn->prepare("UPDATE settings SET system_name = ?, system_subtitle = ?, description = ?, owner_name = ?, sidebar_color = ?, archive_base_dir = ? WHERE id = 1");
        if ($stmt) {
            $stmt->bind_param("ssssss", $system_name, $system_subtitle, $description, $owner_name, $sidebar_color, $archive_base_dir);
            if ($stmt->execute()) { 
                $message = 'سەرکەوتووبوو! زانیارییەکان نوێکرانەوە'; $messageType = 'success'; 
                
                // --- SYNC SETTINGS TO PUBLIC SITE ---
                require_once 'sync_utils.php';
                syncSettingsToPublic([
                    'name' => $system_name,
                    'subtitle' => $system_subtitle,
                    'color' => $sidebar_color,
                    'desc' => $description
                ]);
            } else {

                $message = 'هەڵەیەک لە نوێکردنەوەدا ڕوویدا: ' . $conn->error; $messageType = 'error';
            }
            $stmt->close();
        }
    }
}

// Handle Remote Sync Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_remote_sync'])) {
    $r_host = trim($_POST['remote_sync_host'] ?? '');
    $r_db   = trim($_POST['remote_sync_db'] ?? '');
    $r_user = trim($_POST['remote_sync_user'] ?? '');
    $r_pass = trim($_POST['remote_sync_pass'] ?? '');
    $r_port = trim($_POST['remote_sync_port'] ?? '3306');
    $r_key  = trim($_POST['remote_sync_key'] ?? '');

    $stmt = $conn->prepare("UPDATE settings SET remote_sync_host = ?, remote_sync_db = ?, remote_sync_user = ?, remote_sync_pass = ?, remote_sync_port = ?, remote_sync_key = ? WHERE id = 1");
    if ($stmt) {
        $stmt->bind_param("ssssss", $r_host, $r_db, $r_user, $r_pass, $r_port, $r_key);
        if ($stmt->execute()) {
            $message = 'ڕێکخستنەکانی هۆست بە سەرکەوتوویی پاشەکەوت کران ✅';
            $messageType = 'success';
        } else {
            $message = 'هەڵە لە پاشەکەوتکردن: ' . $conn->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
    $active_tab = 'database';
}

// Handle Manual Settings Sync
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_sync_settings'])) {
    require_once 'sync_utils.php';
    
    // Values from the FORM
    $r_host = trim($_POST['remote_sync_host'] ?? '');
    $r_db   = trim($_POST['remote_sync_db'] ?? '');
    $r_user = trim($_POST['remote_sync_user'] ?? '');
    $r_pass = trim($_POST['remote_sync_pass'] ?? '');
    $r_port = trim($_POST['remote_sync_port'] ?? '3306');
    $r_key  = trim($_POST['remote_sync_key'] ?? '');

    // Debug logging
    file_put_contents('sync_utils_debug.log', date('Y-m-d H:i:s') . " - POST Host: $r_host - POST Key: $r_key\n", FILE_APPEND);

    // 1. Save to DB using prepared statement

    $stmt = $conn->prepare("UPDATE settings SET remote_sync_host=?, remote_sync_db=?, remote_sync_user=?, remote_sync_pass=?, remote_sync_port=?, remote_sync_key=? WHERE id=1");
    $stmt->bind_param("ssssss", $r_host, $r_db, $r_user, $r_pass, $r_port, $r_key);
    $stmt->execute();

    // 2. Get system info
    $res = $conn->query("SELECT system_name, system_subtitle, sidebar_color, description, logo FROM settings WHERE id = 1");
    $s = $res->fetch_assoc();
    
    // 3. Sync using the FRESH values from the form
    $response = syncSettingsToPublic([
        'name' => $s['system_name'],
        'subtitle' => $s['system_subtitle'],
        'color' => $s['sidebar_color'],
        'desc' => $s['description'],
        'logo' => $s['logo']
    ], [

        'remote_sync_host' => $r_host,
        'remote_sync_key'  => $r_key
    ]);
    
    if ($response['success']) {
        $message = 'زانیارییەکان بە سەرکەوتوویی پاشەکەوت و هاوکات (Sync) کران ✅';
        $messageType = 'success';
    } else {
        $message = 'هەڵە لە هاوکاتکردن: ' . ($response['message'] ?? 'پەیوەندی دروست نەکرا');
        if (strpos($message, '403') !== false) {
            $message .= ' (دڵنیابە کە کۆدی دڵنیایی Sync Key ڕاستە)';
        }
        $messageType = 'error';
    }
    $active_tab = 'database';
}

// Handle Sync ALL Books
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_all_books'])) {
    require_once 'sync_utils.php';

    // Save connection settings first
    $r_host = trim($_POST['remote_sync_host'] ?? '');
    $r_db   = trim($_POST['remote_sync_db'] ?? '');
    $r_user = trim($_POST['remote_sync_user'] ?? '');
    $r_pass = trim($_POST['remote_sync_pass'] ?? '');
    $r_port = trim($_POST['remote_sync_port'] ?? '3306');
    $r_key  = trim($_POST['remote_sync_key'] ?? '');

    $stmt = $conn->prepare("UPDATE settings SET remote_sync_host=?, remote_sync_db=?, remote_sync_user=?, remote_sync_pass=?, remote_sync_port=?, remote_sync_key=? WHERE id=1");
    $stmt->bind_param("ssssss", $r_host, $r_db, $r_user, $r_pass, $r_port, $r_key);
    $stmt->execute();

    // Bulk sync
    $response = syncAllBooksToPublic([
        'remote_sync_host' => $r_host,
        'remote_sync_key'  => $r_key
    ]);

    if ($response['success']) {
        $synced = $response['api_response']['synced'] ?? $response['total_sent'] ?? '?';
        $message = "هەموو کتێبەکان بە سەرکەوتوویی هاوکات کران: $synced کتێب ✅";
        $messageType = 'success';
    } else {
        $message = 'هەڵە لە هاوکاتکردنی کتێبەکان: ' . ($response['message'] ?? 'نەزانراو');
        $messageType = 'error';
    }
    $active_tab = 'database';
}

// Handle Sync ALL Activities
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_all_activities'])) {
    require_once 'sync_utils.php';

    // Save connection settings first
    $r_host = trim($_POST['remote_sync_host'] ?? '');
    $r_db   = trim($_POST['remote_sync_db'] ?? '');
    $r_user = trim($_POST['remote_sync_user'] ?? '');
    $r_pass = trim($_POST['remote_sync_pass'] ?? '');
    $r_port = trim($_POST['remote_sync_port'] ?? '3306');
    $r_key  = trim($_POST['remote_sync_key'] ?? '');

    $stmt = $conn->prepare("UPDATE settings SET remote_sync_host=?, remote_sync_db=?, remote_sync_user=?, remote_sync_pass=?, remote_sync_port=?, remote_sync_key=? WHERE id=1");
    $stmt->bind_param("ssssss", $r_host, $r_db, $r_user, $r_pass, $r_port, $r_key);
    $stmt->execute();

    // Bulk sync activities
    $response = syncAllActivitiesToPublic([
        'remote_sync_host' => $r_host,
        'remote_sync_key'  => $r_key
    ]);

    if ($response['success']) {
        $message = "چالاکییەکان و وێنەی گالەرییەکان بە سەرکەوتوویی هاوکات کران ✅";
        $messageType = 'success';
    } else {
        $message = 'هەڵە لە هاوکاتکردنی چالاکییەکان: ' . ($response['message'] ?? 'نەزانراو');
        $messageType = 'error';
    }
    $active_tab = 'database';
}

// Handle Pull ALL Books from Host
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pull_all_books'])) {
    require_once 'sync_utils.php';

    // Save connection settings first
    $r_host = trim($_POST['remote_sync_host'] ?? '');
    $r_db   = trim($_POST['remote_sync_db'] ?? '');
    $r_user = trim($_POST['remote_sync_user'] ?? '');
    $r_pass = trim($_POST['remote_sync_pass'] ?? '');
    $r_port = trim($_POST['remote_sync_port'] ?? '3306');
    $r_key  = trim($_POST['remote_sync_key'] ?? '');

    $stmt = $conn->prepare("UPDATE settings SET remote_sync_host=?, remote_sync_db=?, remote_sync_user=?, remote_sync_pass=?, remote_sync_port=?, remote_sync_key=? WHERE id=1");
    $stmt->bind_param("ssssss", $r_host, $r_db, $r_user, $r_pass, $r_port, $r_key);
    $stmt->execute();

    // Pull books from public server
    $response = pullAllBooksFromPublic([
        'remote_sync_host' => $r_host,
        'remote_sync_key'  => $r_key
    ]);

    if ($response['success']) {
        $message = $response['message'];
        $messageType = 'success';
    } else {
        $message = 'هەڵە لە داگرتنی کتێبەکان: ' . ($response['message'] ?? 'نەزانراو');
        $messageType = 'error';
    }
    $active_tab = 'database';
}

// Handle Sync ALL Lineage to Host (Push)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_all_lineage'])) {
    require_once 'sync_utils.php';

    // Save connection settings first
    $r_host = trim($_POST['remote_sync_host'] ?? '');
    $r_db   = trim($_POST['remote_sync_db'] ?? '');
    $r_user = trim($_POST['remote_sync_user'] ?? '');
    $r_pass = trim($_POST['remote_sync_pass'] ?? '');
    $r_port = trim($_POST['remote_sync_port'] ?? '3306');
    $r_key  = trim($_POST['remote_sync_key'] ?? '');

    $stmt = $conn->prepare("UPDATE settings SET remote_sync_host=?, remote_sync_db=?, remote_sync_user=?, remote_sync_pass=?, remote_sync_port=?, remote_sync_key=? WHERE id=1");
    $stmt->bind_param("ssssss", $r_host, $r_db, $r_user, $r_pass, $r_port, $r_key);
    $stmt->execute();

    $response = syncAllLineageToPublic([
        'remote_sync_host' => $r_host,
        'remote_sync_key'  => $r_key
    ]);

    if ($response['success']) {
        $message = $response['message'];
        $messageType = 'success';
    } else {
        $message = 'هەڵە لە هاوکاتکردنی نەسەب بۆ هۆست: ' . ($response['message'] ?? 'نەزانراو');
        $messageType = 'error';
    }
    $active_tab = 'database';
}

// Handle Pull ALL Lineage from Host (Pull)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pull_all_lineage'])) {
    require_once 'sync_utils.php';

    // Save connection settings first
    $r_host = trim($_POST['remote_sync_host'] ?? '');
    $r_db   = trim($_POST['remote_sync_db'] ?? '');
    $r_user = trim($_POST['remote_sync_user'] ?? '');
    $r_pass = trim($_POST['remote_sync_pass'] ?? '');
    $r_port = trim($_POST['remote_sync_port'] ?? '3306');
    $r_key  = trim($_POST['remote_sync_key'] ?? '');

    $stmt = $conn->prepare("UPDATE settings SET remote_sync_host=?, remote_sync_db=?, remote_sync_user=?, remote_sync_pass=?, remote_sync_port=?, remote_sync_key=? WHERE id=1");
    $stmt->bind_param("ssssss", $r_host, $r_db, $r_user, $r_pass, $r_port, $r_key);
    $stmt->execute();

    $response = pullAllLineageFromPublic([
        'remote_sync_host' => $r_host,
        'remote_sync_key'  => $r_key
    ]);

    if ($response['success']) {
        $message = $response['message'];
        $messageType = 'success';
    } else {
        $message = 'هەڵە لە داگرتنی نەسەب لە هۆستەوە: ' . ($response['message'] ?? 'نەزانراو');
        $messageType = 'error';
    }
    $active_tab = 'database';
}
// Logo Upload
if (isset($_POST['upload_logo']) && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'svg'];
    
    if (in_array($ext, $allowed)) {
        if (!is_dir('assets')) mkdir('assets', 0755, true);
        $uploadPath = 'assets/logo.' . $ext;
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
            $conn->query("UPDATE settings SET logo = '$uploadPath' WHERE id = 1");
            $message = 'لۆگۆکە بە سەرکەوتوویی نوێکرایەوە'; $messageType = 'success';
            
            // Re-fetch settings to show the new logo immediately
            $res = $conn->query("SELECT * FROM settings WHERE id = 1");
            if ($res) $settings = $res->fetch_assoc();
        } else {
            $message = 'نەتوانرا فایلەکە پاشەکەوت بکرێت'; $messageType = 'error';
        }
    } else {
        $message = 'جۆری فایلەکە ڕێگەپێنەدراوە (تەنها JPG, PNG, SVG)'; $messageType = 'error';
    }
}

// Backup Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    $backup_dir = 'backups/';
    if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);
    
    $filename = 'backup_system_' . date('Y-m-d_H-i-s') . '.sql';
    $filePath = $backup_dir . $filename;
    
    // Command for mysqldump (standard for Laragon/XAMPP if in PATH)
    $command = sprintf(
        'mysqldump --user=%s --password=%s --host=%s %s > %s',
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_NAME),
        escapeshellarg($filePath)
    );
    
    // Attempt to run the command
    $output = [];
    $returnVar = -1;
    exec($command . ' 2>&1', $output, $returnVar);
    
    if ($returnVar === 0 && file_exists($filePath) && filesize($filePath) > 0) {
        $message = "باکئەپ بە سەرکەوتوویی دروستکرا: $filename";
        $messageType = 'success';
        if (function_exists('logAudit')) {
            logAudit($conn, $_SESSION['user_id'], 'BACKUP', 'database', 0, null, json_encode(['file' => $filename]));
        }
    } else {
        // Fallback: If mysqldump fails, try a manual PHP export for small databases
        $sqlDump = "-- Database Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
        $tables = [];
        $res = $conn->query("SHOW TABLES");
        while($row = $res->fetch_array()) $tables[] = $row[0];
        
        foreach($tables as $table) {
            $res = $conn->query("SELECT * FROM $table");
            $numFields = $res->field_count;
            
            $sqlDump .= "DROP TABLE IF EXISTS $table;\n";
            $row2 = $conn->query("SHOW CREATE TABLE $table")->fetch_row();
            $sqlDump .= $row2[1].";\n\n";
            
            for ($i = 0; $i < $numFields; $i++) {
                while($row = $res->fetch_row()) {
                    $sqlDump .= "INSERT INTO $table VALUES(";
                    for($j=0; $j<$numFields; $j++) {
                        if ($row[$j] === null) {
                            $sqlDump .= 'NULL';
                        } else {
                            $escaped = $conn->real_escape_string((string)$row[$j]);
                            $sqlDump .= '"' . $escaped . '"';
                        }
                        if ($j < ($numFields - 1)) $sqlDump .= ',';
                    }
                    $sqlDump .= ");\n";
                }
            }
            $sqlDump .= "\n\n\n";
        }
        
        if (file_put_contents($filePath, $sqlDump)) {
            $message = "باکئەپ دروستکرا (PHP Export): $filename";
            $messageType = 'success';
        } else {
            $message = 'هەڵە لە دروستکردنی باکئەپ: ' . implode("\n", $output);
            $messageType = 'error';
        }
    }
}

// Handle Add New Unit (Positions Tab)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_unit'])) {
    $new_unit_name = trim($_POST['new_unit_name'] ?? '');
    if (!empty($new_unit_name)) {
        $stmt = $conn->prepare("INSERT INTO staff_units (unit_name) VALUES (?)");
        $stmt->bind_param("s", $new_unit_name);
        if ($stmt->execute()) {
            $message = "یەکەی نوێ بە سەرکەوتوویی زیادکرا ✅"; $messageType = "success";
        } else {
            $message = "هەڵە لە زیادکردنی یەکە: " . $conn->error; $messageType = "error";
        }
        $stmt->close();
    }
    $active_tab = 'positions';
}

// Handle Update Unit Signatories (Positions Tab)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_unit_config'])) {
    $unit_id = $_POST['unit_id'];
    $unit_name = trim($_POST['unit_name'] ?? '');
    $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
    $deputy_id = !empty($_POST['deputy_id']) ? $_POST['deputy_id'] : null;

    $stmt = $conn->prepare("UPDATE staff_units SET unit_name = ?, manager_id = ?, deputy_id = ? WHERE id = ?");
    $stmt->bind_param("siii", $unit_name, $manager_id, $deputy_id, $unit_id);
    if ($stmt->execute()) {
        $message = "گۆڕانکارییەکان پۆستەکان بە سەرکەوتوویی پاشکەوت کران ✅"; $messageType = "success";
    } else {
        $message = "هەڵە لە پاشکەوتکردن: " . $conn->error; $messageType = "error";
    }
    $stmt->close();
    $active_tab = 'positions';
}

// Layout Config
$sys_module = 'settings';
$page_title = 'ڕێکخستنەکان';
$active_nav = $active_tab;
require_once 'includes/layout.php';
?>

<style>
    .settings-container { max-width: 900px; }
    .tabs-nav { display: flex; gap: 8px; margin-bottom: 30px; border-bottom: 2px solid #eee; }
    .tab-link { padding: 12px 25px; text-decoration: none; color: #666; font-weight: 700; border-radius: 12px 12px 0 0; border-bottom: 2px solid transparent; transition: 0.2s; margin-bottom: -2px; }
    .tab-link:hover { background: #f5f5f5; color: var(--maroon); }
    .tab-link.active { border-bottom-color: var(--maroon); color: var(--maroon); background: #fdf2f2; }

    .settings-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; align-items: start; }
    @media (max-width: 800px) { .settings-grid { grid-template-columns: 1fr; } }

    .logo-preview-box { background: #fff; border-radius: 16px; padding: 30px; text-align: center; border: 1px solid var(--border); box-shadow: var(--shadow-sm); }
    .logo-preview-box img { max-width: 160px; height: 160px; object-fit: contain; border: 4px solid #f9f9f9; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }

    .db-badge { padding: 8px 16px; border-radius: 20px; font-weight: 700; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 8px; }
    .db-badge.connected { background: #e8f5e9; color: #2e7d32; }
    .db-badge.error { background: #ffebee; color: #d32f2f; }

    /* Professional Toggle Switch */
    .lib-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
    .lib-switch input { opacity: 0; width: 0; height: 0; }
    .lib-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
    .lib-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked + .lib-slider { background-color: #2e7d32; }
    input:focus + .lib-slider { box-shadow: 0 0 1px #2e7d32; }
    input:checked + .lib-slider:before { transform: translateX(24px); }
</style>

<div class="settings-container">
    <!-- 
    <div class="tabs-nav">
        <a href="?tab=general" class="tab-link <?php echo $active_tab == 'general' ? 'active' : ''; ?>">⚙️ گشتی</a>
        <a href="?tab=sections" class="tab-link <?php echo $active_tab == 'sections' ? 'active' : ''; ?>">🧩 کارتەکانی پۆرتاڵ</a>
        <a href="?tab=database" class="tab-link <?php echo $active_tab == 'database' ? 'active' : ''; ?>">🗄️ داتابەیس</a>
        <a href="?tab=backup" class="tab-link <?php echo $active_tab == 'backup' ? 'active' : ''; ?>">💾 باکئەپ</a>
        <a href="?tab=positions" class="tab-link <?php echo $active_tab == 'positions' ? 'active' : ''; ?>">🎖️ پۆستەکان</a>
        <a href="?tab=audit" class="tab-link <?php echo $active_tab == 'audit' ? 'active' : ''; ?>">📜 تۆماری چالاکییەکان</a>
        <a href="?tab=update" class="tab-link <?php echo $active_tab == 'update' ? 'active' : ''; ?>">🚀 نوێکردنەوە</a>
    </div>
    -->

    <?php if ($message): ?>
    <div class="lib-toast <?php echo $messageType; ?>" style="position: static; margin-bottom: 25px; transform: none; width: 100%;">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <?php if ($active_tab == 'general'): ?>
        <div class="settings-grid">
            <div class="logo-preview-box">
                <h3 style="margin-bottom: 20px; color: var(--maroon);">لۆگۆی سیستەم</h3>
                <img src="<?php echo htmlspecialchars($settings['logo']); ?>" alt="System Logo">
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="logo" id="logoInp" style="display:none;" onchange="this.form.submit()">
                    <button type="button" class="lib-btn lib-btn-gold" style="width: 100%;" onclick="document.getElementById('logoInp').click()">گۆڕینی لۆگۆ</button>
                    <input type="hidden" name="upload_logo" value="1">
                </form>
            </div>

            <div class="lib-card">
                <div class="card-header"><h3>زانیارییەکان</h3></div>
                <div style="padding: 25px;">
                    <form method="POST">
                        <div class="lib-field">
                            <label>ناوی سیستەم</label>
                            <input type="text" name="system_name" value="<?php echo htmlspecialchars($settings['system_name']); ?>" required>
                        </div>
                        <div class="lib-field">
                            <label>ناوی ژێر سیستەم (وەک: وەزارەتی پێشمەرگە)</label>
                            <input type="text" name="system_subtitle" value="<?php echo htmlspecialchars($settings['system_subtitle'] ?? 'وەزارەتی پێشمەرگە'); ?>">
                        </div>
                        <div class="lib-field">
                            <label>ڕەنگی ناڤیگەیشن بار (Sidebar Color)</label>
                            <input type="color" name="sidebar_color" value="<?php echo htmlspecialchars($settings['sidebar_color'] ?? '#5a0202'); ?>" style="height: 50px; padding: 5px; cursor: pointer;">
                        </div>
                        <div class="lib-field">
                            <label>دروشمی سیستەم (Description)</label>
                            <textarea name="description" rows="3"><?php echo htmlspecialchars($settings['description']); ?></textarea>
                        </div>
                        <div class="lib-field">
                            <label>ناوی خاوەن (Owner Name)</label>
                            <input type="text" name="owner_name" value="<?php echo htmlspecialchars($settings['owner_name']); ?>">
                        </div>
                        <div class="lib-field">
                            <label>ڕێڕەوی ئەرشیف (Base Archive Directory)</label>
                            <input type="text" name="archive_base_dir" value="<?php echo htmlspecialchars($settings['archive_base_dir'] ?? 'D:/Archive/'); ?>" placeholder="e.g. D:/Archive/">
                            <small style="color: #888;">ئەم ڕێڕەوە بەکاردێت بۆ پاشەکەوتکردنی فایلی وۆرد.</small>
                        </div>

                        <div style="margin-top: 20px;">
                            <button type="submit" name="save_settings" class="lib-btn lib-btn-primary" style="width: 100%; padding: 12px;">پاشەکەوتکردنی گۆڕانکارییەکان</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($active_tab == 'sections'): ?>
        <div class="lib-card">
            <div class="card-header"><h3>🎴 بەڕێوەبردنی کارتەکانی پۆرتاڵ</h3></div>
            <div style="padding: 25px;">
                <p style="color: #666; font-size: 0.9rem; margin-bottom: 25px;">
                    لێرەوە دەتوانیت دیزاین، ناو، و <strong style="color:var(--maroon);">بینین (Visibility)</strong>ی کارتەکانی پۆرتاڵی سەرەکی کۆنتڕۆڵ بکەیت.
                </p>
                <form method="POST">
                    <?php 
                    $secs = $conn->query("SELECT * FROM system_sections ORDER BY id ASC");
                    while($s = $secs->fetch_assoc()):
                        $sid = $s['id'];
                    ?>
                    <div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #ececec; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #f9f9f9; padding-bottom: 10px;">
                            <h4 style="display: flex; align-items: center; gap: 12px; color: var(--maroon); margin:0;">
                                <span style="font-size: 1.8rem; background: #fdf2f2; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 10px;"><?php echo $s['icon']; ?></span>
                                <div>
                                    <div style="font-size: 1.1rem;"><?php echo htmlspecialchars($s['section_title']); ?></div>
                                    <div style="font-size: 0.75rem; color: #999; font-family: monospace;"><?php echo $s['section_key']; ?></div>
                                </div>
                            </h4>
                            <div style="text-align: left; display: flex; flex-direction: column; align-items: flex-end; gap: 5px;">
                                <label class="lib-switch">
                                    <input type="checkbox" name="section_active_<?php echo $sid; ?>" <?php echo $s['is_active'] ? 'checked' : ''; ?>>
                                    <span class="lib-slider"></span>
                                </label>
                                <span style="font-size: 0.75rem; font-weight: 700; color: <?php echo $s['is_active'] ? '#2e7d32' : '#999'; ?>">
                                    <?php echo $s['is_active'] ? 'چالاکە' : 'شاراوەیە'; ?>
                                </span>
                            </div>
                        </div>

                        <div class="lib-form-grid" style="grid-template-columns: 1fr 2fr;">
                            <div class="lib-field">
                                <label>ناوی سەرەکی کارت</label>
                                <input type="text" name="sections[<?php echo $sid; ?>][title]" value="<?php echo htmlspecialchars($s['section_title']); ?>" required>
                            </div>
                            <div class="lib-field">
                                <label>وەسفی کورتی کارت (Description)</label>
                                <input type="text" name="sections[<?php echo $sid; ?>][description]" value="<?php echo htmlspecialchars($s['section_description']); ?>">
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <div style="margin-top: 30px; position: sticky; bottom: 0; background: #fff; padding: 20px; border-top: 1px solid #eee; margin-left: -25px; margin-right: -25px; border-radius: 0 0 16px 16px;">
                        <button type="submit" name="save_sections" class="lib-btn lib-btn-primary" style="width: 100%; padding: 15px; font-size: 1.1rem;">💾 پاشەکەوتکردنی هەموو گۆڕانکارییەکان</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($active_tab == 'database'): ?>
        <?php
            $is_connected = !$conn->connect_error;
        ?>
        <div class="lib-card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h3>پەیوەندی داتابەیس</h3>
                <span class="db-badge <?php echo $is_connected ? 'connected' : 'error'; ?>">
                    <?php echo $is_connected ? '🟢 بەستراوەتەوە' : '🔴 پەیوەندی نییە'; ?>
                </span>
            </div>
            <div style="padding: 25px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; background: #f9f9f9; padding: 20px; border-radius: 12px; border: 1px solid #eee;">
                    <div><small style="color: #888;">Host</small><br><strong><?php echo DB_HOST; ?></strong></div>
                    <div><small style="color: #888;">Database Name</small><br><strong><?php echo DB_NAME; ?></strong></div>
                    <div><small style="color: #888;">User</small><br><strong><?php echo DB_USER; ?></strong></div>
                </div>
                
                <div style="margin-top: 30px;">
                    <h4 style="margin-bottom: 15px; color: var(--maroon);">خشتەکانی ئێستا:</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php 
                        $res = $conn->query("SHOW TABLES");
                        while($tbl = $res->fetch_array()) {
                            echo '<span style="background:#eee; padding:5px 12px; border-radius:8px; font-size:0.85rem; font-family:monospace;">'.$tbl[0].'</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- REMOTE SYNC CONNECTION SETTINGS -->
        <div class="lib-card" style="margin-top: 30px;">
            <div class="card-header">
                <h3>🌐 ڕێکخستنی پەیوەندی بە هۆستی ماڵپەڕ (Sync Connection)</h3>
            </div>
            <div style="padding: 25px;">
                <p style="color: #666; font-size: 0.9rem; margin-bottom: 25px;">
                    زانیارییەکانی داتابەیسی ماڵپەڕەکەت (`www.pirxdry.org`) لێرە بنووسە بۆ ئەوەی داتاکان بە شێوەی خۆکار ڕەوانە بکرێن.
                </p>
                
                <form method="POST">
                    <div class="lib-form-grid" style="grid-template-columns: 1fr 1fr;">
                        <div class="lib-field">
                            <label>هۆستی دوور (Remote Host)</label>
                            <input type="text" name="remote_sync_host" value="<?php echo htmlspecialchars($settings['remote_sync_host'] ?? ''); ?>" placeholder="بۆ نموونە: sql123.hosting.com">
                        </div>
                        <div class="lib-field">
                            <label>ناوی داتابەیس (Remote DB Name)</label>
                            <input type="text" name="remote_sync_db" value="<?php echo htmlspecialchars($settings['remote_sync_db'] ?? ''); ?>" placeholder="pirxdry_web_db">
                        </div>
                        <div class="lib-field">
                            <label>یوزەری داتابەیس (Remote DB User)</label>
                            <input type="text" name="remote_sync_user" value="<?php echo htmlspecialchars($settings['remote_sync_user'] ?? ''); ?>" placeholder="db_user_123">
                        </div>
                        <div class="lib-field">
                            <label>پاسۆردی داتابەیس (Remote DB Pass)</label>
                            <input type="password" name="remote_sync_pass" value="<?php echo htmlspecialchars($settings['remote_sync_pass'] ?? ''); ?>" placeholder="••••••••">
                        </div>
                        <div class="lib-field">
                            <label>پۆرتی داتابەیس (Port)</label>
                            <input type="text" name="remote_sync_port" value="<?php echo htmlspecialchars($settings['remote_sync_port'] ?? '3306'); ?>" placeholder="3306">
                        </div>
                        <div class="lib-field">
                            <label>کۆدی دڵنیایی (Sync Key / Token)</label>
                            <input type="text" name="remote_sync_key" value="<?php echo htmlspecialchars($settings['remote_sync_key'] ?? ''); ?>" placeholder="PIRX_SECURE_SYNC_2026">
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="submit" name="save_remote_sync" class="lib-btn lib-btn-primary" style="flex: 2; padding: 12px; min-width: 180px;">پاشەکەوتکردنی ڕێکخستنی هۆست</button>
                        <button type="submit" name="manual_sync_settings" class="lib-btn lib-btn-gold" style="flex: 1; padding: 12px; min-width: 150px;">🔄 هاوکاتکردنی ڕێکخستنەکان</button>
                        <button type="submit" name="sync_all_books" class="lib-btn" style="flex: 1; padding: 12px; min-width: 150px; background: #2e7d32; color:#fff; border-radius:8px; border:none; cursor:pointer; font-family:inherit; font-size:1rem;">📚 هاوکاتکردنی کتێبەکان</button>
                        <button type="submit" name="pull_all_books" class="lib-btn" style="flex: 1; padding: 12px; min-width: 150px; background: #673ab7; color:#fff; border-radius:8px; border:none; cursor:pointer; font-family:inherit; font-size:1rem;">📥 داگرتنی کتێبەکان لە ماڵپەڕەوە</button>
                        <button type="submit" name="sync_all_activities" class="lib-btn" style="flex: 1; padding: 12px; min-width: 150px; background: #1976d2; color:#fff; border-radius:8px; border:none; cursor:pointer; font-family:inherit; font-size:1rem;">📅 هاوکاتکردنی چالاکییەکان</button>
                        <button type="submit" name="sync_all_lineage" class="lib-btn" style="flex: 1; padding: 12px; min-width: 175px; background: #1a5653; color:#fff; border-radius:8px; border:none; cursor:pointer; font-family:inherit; font-size:1rem; font-weight: bold; box-shadow: 0 4px 10px rgba(26,86,83,0.3);">🌳 هاوکاتکردنی نەسەب بۆ هۆست</button>
                        <button type="submit" name="pull_all_lineage" class="lib-btn" style="flex: 1; padding: 12px; min-width: 175px; background: #c5a059; color:#fff; border-radius:8px; border:none; cursor:pointer; font-family:inherit; font-size:1rem; font-weight: bold; box-shadow: 0 4px 10px rgba(197,160,89,0.3);">📥 داگرتنی نەسەب لە هۆستەوە</button>
                    </div>

                </form>
            </div>
        </div>
    <?php endif; ?>



    <?php if ($active_tab == 'backup'): ?>
        <div class="settings-grid">
            <div class="lib-card">
                <div class="card-header"><h3>پاشەکەوتکردن</h3></div>
                <div style="padding: 25px;">
                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 20px;">دروستکردنی باکئەپی داتابەیس بە شێوەی فایلی SQL بۆ پاراستنی زانیارییەکان.</p>
                    <form method="POST">
                        <button type="submit" name="create_backup" class="lib-btn lib-btn-primary" style="width: 100%;">دروستکردنی باکئەپ ئێستا</button>
                    </form>
                </div>
            </div>

            <div class="lib-card">
                <div class="card-header"><h3>فایلەکان</h3></div>
                <div style="padding: 10px;">
                    <?php
                    $backup_dir = 'backups/';
                    if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);
                    $files = array_diff(scandir($backup_dir), ['.', '..']);
                    rsort($files);
                    if (empty($files)):
                    ?>
                        <div style="padding: 40px; text-align: center; color: #999;">هیچ باکئەپێک نییە</div>
                    <?php else: ?>
                        <div class="lib-table-wrapper">
                            <table class="lib-table">
                                <thead><tr><th>ناوی فایل</th><th style="width: 100px;">کار</th></tr></thead>
                                <tbody>
                                    <?php foreach($files as $f): ?>
                                    <tr>
                                        <td style="font-family: monospace; font-size: 0.85rem;"><?php echo $f; ?></td>
                                        <td>
                                            <a href="backups/<?php echo $f; ?>" download class="lib-btn lib-btn-gold" style="padding: 5px 12px; font-size: 0.75rem;">داگرتن</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($active_tab == 'positions'): ?>
        <?php
        // NGO Context: Fetch from members table
        $units_res = $conn->query("SELECT * FROM staff_units ORDER BY id ASC");
        $staff_res = $conn->query("SELECT id, full_name, member_rank as rank_name FROM members WHERE member_status = 'active' ORDER BY full_name ASC");
        $all_staff = [];
        if ($staff_res) {
            while($s = $staff_res->fetch_assoc()) $all_staff[] = $s;
        }
        ?>

        <!-- 
        <div class="lib-card" style="margin-bottom: 25px;">
            <div class="card-header"><h3>➕ زیادکردنی بەشی نوێ</h3></div>
            <div style="padding: 20px;">
                <form method="POST" style="display: flex; gap: 15px; align-items: flex-end;">
                    <div class="lib-field" style="flex: 1; margin: 0;">
                        <label>ناوی بەش:</label>
                        <input type="text" name="new_unit_name" placeholder="بۆ نموونە: بەڕێوەبەرایەتی کارگێڕی" required>
                    </div>
                    <button type="submit" name="add_unit" class="lib-btn lib-btn-primary">➕ زیادکردن</button>
                </form>
            </div>
        </div>
        -->

        <div class="lib-card">
            <div class="card-header"><h3>⚙️ ڕێکخستنی واژووکاران</h3></div>
            <div class="lib-table-wrapper">
                <table class="lib-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;">ناوی پۆست / بەش</th>
                            <th>بەڕێوەبەر / سەرۆکی دەستەی کارگێڕی</th>
                            <th>جێگر / نوسراوی گشتی</th>
                            <th style="width: 100px;">کردار</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($u = $units_res->fetch_assoc()): ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="unit_id" value="<?php echo $u['id']; ?>">
                                <td>
                                    <input type="text" name="unit_name" value="<?php echo htmlspecialchars($u['unit_name']); ?>" 
                                           style="width: 100%; border: 1px solid #eee; padding: 6px; border-radius: 6px; font-weight: bold; color: var(--maroon);">
                                </td>
                                <td>
                                    <?php if ($u['unit_name'] === 'نوسراوی گشتی'): ?>
                                        <div style="padding: 8px; text-align: center; color: #888; font-style: italic;">
                                            (ئەم بەشە ناوی ڕێکخراوەکە بەکاردەهێنێت)
                                        </div>
                                        <input type="hidden" name="manager_id" value="">
                                    <?php else: ?>
                                        <select name="manager_id" class="searchable-select" style="width: 100%;">
                                            <option value="">-- دیاری نەکراوە --</option>
                                            <?php foreach($all_staff as $st): ?>
                                                <option value="<?php echo $st['id']; ?>" 
                                                        data-rank="<?php echo htmlspecialchars($st['rank_name'] ?? ''); ?>"
                                                        <?php echo $u['manager_id'] == $st['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($st['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['unit_name'] === 'نوسراوی گشتی'): ?>
                                        <div style="padding: 8px; background: #f9f9f9; border-radius: 6px; font-size: 0.85rem; color: #666; border: 1px dashed #ccc; text-align: center;">
                                            🏢 بەکارهێنانی ناوی ڕێکخراو:<br>
                                            <strong><?php echo htmlspecialchars($settings['system_name']); ?></strong>
                                        </div>
                                        <input type="hidden" name="deputy_id" value="">
                                    <?php else: ?>
                                        <select name="deputy_id" class="searchable-select" style="width: 100%;">
                                            <option value="">-- دیاری نەکراوە --</option>
                                            <?php foreach($all_staff as $st): ?>
                                                <option value="<?php echo $st['id']; ?>" 
                                                        data-rank="<?php echo htmlspecialchars($st['rank_name'] ?? ''); ?>"
                                                        <?php echo $u['deputy_id'] == $st['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($st['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="submit" name="save_unit_config" class="lib-btn lib-btn-primary" style="padding: 6px 12px; font-size: 0.8rem;">💾 پاشکەوت</button>
                                </td>
                            </form>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script>
        $(document).ready(function() {
            function formatMember(state) {
                if (!state.id) return state.text;
                var rank = $(state.element).data('rank');
                var $state = $(
                    '<div style="display:flex; flex-direction:column; padding:2px 0; line-height:1.2;">' +
                    '<span style="font-weight:700; font-size:0.92rem; color:var(--maroon);">' + state.text + '</span>' +
                    '<span style="font-size:0.75rem; color:#888;">' + (rank ? rank : 'ئەندام') + '</span>' +
                    '</div>'
                );
                return $state;
            }

            $('.searchable-select').select2({ 
                dir: "rtl", 
                width: '100%',
                templateResult: formatMember,
                templateSelection: formatMember
            });
        });
        </script>
        <style>
            .select2-container--default .select2-selection--single { height: 48px; border: 2px solid #eee; border-radius: 10px; display: flex; align-items: center; }
            .select2-results__option { padding: 8px 12px !important; }
        </style>
    <?php endif; ?>
    <?php if ($active_tab == 'audit'): ?>
        <div class="lib-card">
            <div class="card-header"><h3>📜 تۆماری گۆڕانکارییەکان (Audit Logs)</h3></div>
            <div style="padding: 20px; background: #f9f9f9; border-bottom: 1px solid #eee;">
                <form method="GET" style="display: flex; gap: 15px; align-items: flex-end;">
                    <input type="hidden" name="tab" value="audit">
                    <div class="lib-field" style="margin:0;">
                        <label>بەکارهێنەر:</label>
                        <select name="audit_user">
                            <option value="">هەمووان</option>
                            <?php 
                            $usrs = $conn->query("SELECT id, username FROM users");
                            while($u = $usrs->fetch_assoc()): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo ($_GET['audit_user'] ?? '') == $u['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['username']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="lib-field" style="margin:0;">
                        <label>ڕێکەوت:</label>
                        <input type="date" name="audit_date" value="<?php echo $_GET['audit_date'] ?? ''; ?>">
                    </div>
                    <button type="submit" class="lib-btn lib-btn-primary">🔍 فلتەر</button>
                    <a href="?tab=audit" class="lib-btn lib-btn-danger">پاککردنەوە</a>
                </form>
            </div>
            <div class="lib-table-wrapper">
                <table class="lib-table">
                    <thead>
                        <tr>
                            <th>بەکارهێنەر</th>
                            <th>کردار</th>
                            <th>خشتە</th>
                            <th>ناسنامە</th>
                            <th>ڕێکەوت</th>
                            <th>وردەکاری</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $a_user = (int)($_GET['audit_user'] ?? 0);
                        $a_date = $_GET['audit_date'] ?? '';
                        $awhere = "WHERE 1=1";
                        if ($a_user) $awhere .= " AND l.user_id = $a_user";
                        if ($a_date) $awhere .= " AND DATE(l.created_at) = '$a_date'";
                        
                        $logs = $conn->query("SELECT l.*, u.username FROM audit_logs l LEFT JOIN users u ON l.user_id = u.id $awhere ORDER BY l.created_at DESC LIMIT 100");
                        while($l = $logs->fetch_assoc()):
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($l['username'] ?? 'System'); ?></strong></td>
                            <td><span class="lib-badge <?php echo $l['action'] == 'INSERT' ? 'lib-badge-green' : 'lib-badge-maroon'; ?>"><?php echo $l['action']; ?></span></td>
                            <td><code><?php echo $l['table_name']; ?></code></td>
                            <td>#<?php echo $l['record_id']; ?></td>
                            <td style="font-size: 0.85rem; color: #666;"><?php echo $l['created_at']; ?></td>
                            <td>
                                <button class="lib-btn lib-btn-gold" style="padding: 4px 10px; font-size: 0.8rem;" 
                                        onclick="showAuditDetails(<?php echo $l['id']; ?>)">👁️ وردەکاری</button>
                                <script id="audit-data-<?php echo $l['id']; ?>" type="application/json">
                                    <?php echo json_encode(['old' => json_decode($l['old_values'], true), 'new' => json_decode($l['new_values'], true)]); ?>
                                </script>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Audit Details Modal -->
        <div id="auditModal" class="lib-modal" style="display: none;">
            <div class="lib-modal-content" style="max-width: 800px;">
                <div class="lib-modal-header">
                    <h3>بەرەوردکردنی گۆڕانکارییەکان</h3>
                    <span class="close-modal" onclick="closeAuditModal()">&times;</span>
                </div>
                <div style="padding: 20px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <h4 style="color: #d32f2f; margin-bottom: 10px;">🔴 کۆن (Old Values)</h4>
                            <div id="oldValuesBox" style="background: #fff8f8; padding: 15px; border-radius: 8px; border: 1px solid #fee; font-family: monospace; font-size: 0.85rem; max-height: 400px; overflow-y: auto;"></div>
                        </div>
                        <div>
                            <h4 style="color: #2e7d32; margin-bottom: 10px;">🟢 نوێ (New Values)</h4>
                            <div id="newValuesBox" style="background: #f8fff8; padding: 15px; border-radius: 8px; border: 1px solid #efe; font-family: monospace; font-size: 0.85rem; max-height: 400px; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function showAuditDetails(id) {
            const data = JSON.parse(document.getElementById('audit-data-' + id).textContent);
            const oldBox = document.getElementById('oldValuesBox');
            const newBox = document.getElementById('newValuesBox');
            
            oldBox.innerHTML = '';
            newBox.innerHTML = '';
            
            if (data.old) {
                for (let key in data.old) {
                    const row = document.createElement('div');
                    row.style.marginBottom = '5px';
                    row.innerHTML = `<strong>${key}:</strong> ${data.old[key]}`;
                    oldBox.appendChild(row);
                }
            } else {
                oldBox.innerHTML = '<em>داتای پێشوو نییە (تۆماری نوێ)</em>';
            }
            
            if (data.new) {
                for (let key in data.new) {
                    const row = document.createElement('div');
                    row.style.marginBottom = '5px';
                    // Highlight if changed
                    const isChanged = data.old && data.old[key] != data.new[key];
                    row.style.color = isChanged ? '#2e7d32' : '#666';
                    row.style.fontWeight = isChanged ? 'bold' : 'normal';
                    row.innerHTML = `<strong>${key}:</strong> ${data.new[key]}`;
                    newBox.appendChild(row);
                }
            }
            
            document.getElementById('auditModal').style.display = 'flex';
        }
        function closeAuditModal() {
            document.getElementById('auditModal').style.display = 'none';
        }
        </script>
    <?php endif; ?>

    <?php if ($active_tab == 'update'): ?>
        <div class="lib-card">
            <div class="card-header"><h3>🔄 نوێکردنەوەی سیستەمی ئۆنلاین (Online System Update)</h3></div>
            <div style="padding: 25px;">
                <?php
                $local_version = $settings['current_version'] ?? '1.0.0';
                $update_json_url = 'https://pirxdry.org/updates/version.json';
                $remote_info = null;
                $error = '';
                
                try {
                    $context = stream_context_create([
                        "http" => [
                            "timeout" => 5,
                            "user_agent" => "PHP-Updater-Client"
                        ]
                    ]);
                    $json_data = @file_get_contents($update_json_url, false, $context);
                    if ($json_data) {
                        $remote_info = json_decode($json_data, true);
                    } else {
                        $error = 'ناتوانرێت زانیاری نوێکردنەوە لە سێرڤەری سەرەکی وەربگیرێت (سێرڤەرەکە هێڵەکەی کار ناکات یان ناونیشانەکەی هەڵەیە).';
                    }
                } catch (Exception $e) {
                    $error = 'هەڵەیەک ڕوویدا لە کاتی پەیوەندیکردن بە سێرڤەر: ' . $e->getMessage();
                }
                ?>
                
                <?php if ($error): ?>
                    <div style="background:#ffebee; color:#c62828; padding:15px; border-radius:8px; margin-bottom:20px; font-weight:bold; font-size:0.9rem;">
                        ⚠️ <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div id="successMessage" style="display:none; background:#e8f5e9; color:#2e7d32; padding:15px; border-radius:8px; margin-bottom:20px; font-weight:bold; font-size:0.9rem;">
                </div>
                
                <div id="dbLogArea" style="display:none; margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 8px; border: 1px solid #eee; max-height: 200px; overflow-y: auto; font-size: 0.8rem; direction: ltr; text-align: left;">
                    <h4>Database Migrations Log:</h4>
                    <div id="dbLogContent"></div>
                </div>

                <div style="display:flex; justify-content:space-around; align-items:center; background:#f8f9fa; padding:20px; border-radius:12px; margin-bottom:30px;">
                    <div style="text-align:center;">
                        <p style="color:#777; margin-bottom:5px; font-size:0.9rem;">وەشانی ئێستا</p>
                        <span id="localVersionBadge" style="background:#eaeaea; color:#333; padding:5px 15px; border-radius:50px; font-weight:bold; font-size:1.1rem;">
                            v<?php echo $local_version; ?>
                        </span>
                    </div>
                    <div style="font-size: 2rem; color: var(--maroon);">➡️</div>
                    <div style="text-align:center;">
                        <p style="color:#777; margin-bottom:5px; font-size:0.9rem;">نوێترین وەشانی سێرڤەر</p>
                        <span style="background:#e8f5e9; color:#2e7d32; padding:5px 15px; border-radius:50px; font-weight:bold; font-size:1.1rem;">
                            <?php echo $remote_info ? 'v'.$remote_info['version'] : 'پەیوەست نەکراوە'; ?>
                        </span>
                    </div>
                </div>

                <!-- PROGRESS BAR CONTAINER (Hidden by default) -->
                <div id="progressContainer" style="display:none; margin-bottom:30px; padding:20px; background:#fcfcfc; border:1px solid #eee; border-radius:12px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <span id="progressStatus" style="font-weight:700; color:var(--maroon); font-size:0.9rem;">دەستپێکردنی ئەپدەیت...</span>
                        <span id="progressPercentage" style="font-weight:700; color:#C8A951; font-size:0.95rem;">0%</span>
                    </div>
                    <div style="width:100%; height:14px; background:#e0e0e0; border-radius:10px; overflow:hidden; box-shadow:inset 0 1px 3px rgba(0,0,0,0.1);">
                        <div id="progressBar" style="width:0%; height:100%; background:linear-gradient(90deg, var(--maroon), #C8A951); transition:width 0.4s ease; border-radius:10px;"></div>
                    </div>
                </div>

                <?php if ($remote_info): ?>
                    <?php if (version_compare($remote_info['version'], $local_version, '>')): ?>
                        <div id="updateInfoPanel" style="background:#fff8e1; border-right:4px solid #ffb300; padding:20px; border-radius:8px; margin-bottom:30px;">
                            <h4 style="color:#b78103; font-weight:bold; margin-bottom:10px; font-size:1rem;">📢 وەشانی نوێ بەردەستە!</h4>
                            <p style="font-size:0.9rem; line-height:1.6; color:#555;">
                                <b>نوێکارییەکان (Changelog):</b><br>
                                <?php echo nl2br(htmlspecialchars($remote_info['changelog'])); ?>
                            </p>
                            <p style="font-size:0.8rem; color:#888; margin-top:10px;">بڵاوکراوەتەوە لە ڕێکەوتی: <?php echo $remote_info['release_date'] ?? 'نادیار'; ?></p>
                        </div>

                        <div id="updateActionPanel">
                            <button onclick="startAutoUpdate()" class="lib-btn lib-btn-primary" style="width:100%; padding:15px; font-size:1.1rem; display:flex; align-items:center; justify-content:center; gap:10px;">
                                📥 دابەزاندن و ئەپدەیتی ئۆتۆماتیکی
                            </button>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding:30px; background:#f4faf6; border-radius:8px; border:1px solid #c8e6c9;">
                            <span style="font-size:2.5rem; color:#2e7d32;">🎉</span>
                            <h4 style="color:#2e7d32; font-weight:bold; margin-top:10px;">سیستمەکەت لەسەر نوێترین وەشانە!</h4>
                            <p style="color:#555; font-size:0.9rem; margin-top:5px;">هیچ ئەپدەیتێکی نوێ لە ئێستادا نییە.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:20px;">
                        <button onclick="window.location.reload()" class="lib-btn lib-btn-gold">🔄 دووبارە چێک بکەرەوە</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
        async function startAutoUpdate() {
            let proceed = false;
            if (typeof showConfirm === 'function') {
                const confirmUpdate = await showConfirm(
                    'دڵنیایت لە ئەپدیتکردن؟', 
                    'هەموو فایلە کۆنەکان دەگۆڕدرێن بە فایلە نوێکان (جگە لە settings.php و config.php) و داتابەیس نوێ دەبێتەوە.'
                );
                proceed = confirmUpdate.isConfirmed;
            } else {
                proceed = confirm('دڵنیایت لە ئەپدیتکردن؟\nهەموو فایلە کۆنەکان دەگۆڕدرێن بە فایلە نوێکان و داتابەیس نوێ دەبێتەوە.');
            }
            if (!proceed) return;
            
            // Hide info panel and update button
            const infoPanel = document.getElementById('updateInfoPanel');
            if (infoPanel) infoPanel.style.display = 'none';
            const actionPanel = document.getElementById('updateActionPanel');
            if (actionPanel) actionPanel.style.display = 'none';
            
            // Show progress bar
            const progressContainer = document.getElementById('progressContainer');
            progressContainer.style.display = 'block';
            
            updateProgress(0, 'خەریکی دەستپێکردنی دابەزاندنی پەڕگەی نوێکارییەکان (Download)...');
            
            try {
                // Step 1: Start Chunked Download
                let startResp = await fetch('settings.php?ajax=1&step=download_start');
                let startRes = await startResp.json();
                
                if (!startRes.success) {
                    handleUpdateError(startRes.message || 'شکست لە دەستپێکردنی دابەزاندن.');
                    return;
                }
                
                const totalBytes = startRes.total;
                const chunkSize = startRes.chunk_size;
                
                // Sequentially download chunks
                for (let start = 0; start < totalBytes; start += chunkSize) {
                    let end = Math.min(start + chunkSize - 1, totalBytes - 1);
                    let success = false;
                    let retries = 3;
                    
                    while (!success && retries > 0) {
                        try {
                            let chunkResp = await fetch(`settings.php?ajax=1&step=download_chunk&start=${start}&end=${end}`);
                            let chunkRes = await chunkResp.json();
                            
                            if (chunkRes.success) {
                                success = true;
                                let downloadedBytes = chunkRes.downloaded;
                                let percent = chunkRes.percent;
                                
                                let mbDownloaded = (downloadedBytes / (1024 * 1024)).toFixed(2);
                                let mbTotal = (totalBytes / (1024 * 1024)).toFixed(2);
                                let statusText = `خەریکی داونلۆدکردنی فایلی نوێکارییەکان (${percent}%): ${mbDownloaded} MB لە ${mbTotal} MB...`;
                                
                                // Scale download to represent 0% to 70% of the overall update progress
                                let overallPercent = Math.round(percent * 0.7);
                                updateProgress(overallPercent, statusText);
                            } else {
                                retries--;
                                if (retries === 0) {
                                    handleUpdateError(chunkRes.message || 'شکست لە داونلۆدکردنی پارچەیەک لە فایلی ئەپدیتەکە.');
                                    return;
                                }
                                await new Promise(r => setTimeout(r, 1000)); // wait 1s before retry
                            }
                        } catch (err) {
                            retries--;
                            if (retries === 0) {
                                throw err;
                            }
                            await new Promise(r => setTimeout(r, 1000)); // wait 1s before retry
                        }
                    }
                }
                
                // Step 2: Extracting
                updateProgress(75, 'پەڕگەی ZIP بە سەرکەوتوویی دابەزی. خەریکی دەرهێنان و نووسینەوەی فایلەکان (Extract)...');
                let resp = await fetch('settings.php?ajax=1&step=extract');
                let res = await resp.json();
                
                if (!res.success) {
                    handleUpdateError(res.message);
                    return;
                }
                
                // Step 3: Migration
                updateProgress(90, 'خەریکی نوێکردنەوە و چاکسازی داتابەیس (Database Migration)...');
                resp = await fetch('settings.php?ajax=1&step=migrate');
                res = await resp.json();
                
                if (!res.success) {
                    handleUpdateError(res.message);
                    return;
                }
                
                // Success
                updateProgress(100, 'نوێکردنەوەکە بە سەرکەوتوویی کۆتایی هات!');
                
                // Show success panel
                document.getElementById('successMessage').style.display = 'block';
                document.getElementById('successMessage').innerHTML = '✅ ' + res.message + ' وەشانی نوێ: v' + res.new_version;
                
                if (res.db_log) {
                    document.getElementById('dbLogArea').style.display = 'block';
                    document.getElementById('dbLogContent').innerHTML = res.db_log;
                }
                
                document.getElementById('localVersionBadge').innerHTML = 'v' + res.new_version;
                
                if (typeof showAlert === 'function') {
                    showAlert('ئەپدەیت کرا!', 'سیستم بە سەرکەوتوویی نوێکرایەوە بۆ وەشانی نوێ.', 'success');
                } else {
                    alert('سیستم بە سەرکەوتوویی نوێکرایەوە بۆ وەشانی نوێ.');
                }
                
            } catch (err) {
                handleUpdateError('پەیوەندی لەگەڵ لۆکاڵ هۆست پچڕا یان هەڵەیەکی ناوخۆیی ڕوویدا.');
            }
        }
        
        function updateProgress(percent, statusText) {
            const progressBar = document.getElementById('progressBar');
            const progressStatus = document.getElementById('progressStatus');
            const progressPercentage = document.getElementById('progressPercentage');
            
            if(progressBar) progressBar.style.width = percent + '%';
            if(progressPercentage) progressPercentage.innerHTML = percent + '%';
            if(progressStatus) progressStatus.innerHTML = statusText;
        }
        
        function handleUpdateError(msg) {
            const progressStatus = document.getElementById('progressStatus');
            if(progressStatus) {
                progressStatus.innerHTML = '❌ هەڵە: ' + msg;
                progressStatus.style.color = '#d32f2f';
            }
            
            if (typeof showAlert === 'function') {
                showAlert('هەڵە لە ئەپدیت', msg, 'error');
            } else {
                alert('هەڵە لە ئەپدیت: ' + msg);
            }
            const actionPanel = document.getElementById('updateActionPanel');
            if (actionPanel) actionPanel.style.display = 'block';
        }
        </script>
    <?php endif; ?>
</div>

<?php require_once 'includes/layout_end.php'; ?>
