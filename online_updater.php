<?php
session_start();
require_once 'config.php';

// Ensure only admins/owners can access the updater
$conn = getDB();
if (!$conn) {
    die("Database connection failed.");
}

$user_id = $_SESSION['user_id'] ?? 0;
$user_data = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$current_user = strtolower($user_data['username'] ?? '');
$is_super_admin = ($current_user === 'admin' || $current_user === 'sangawy' || ($user_data['role'] ?? '') === 'owner');

if (!$is_super_admin) {
    header('Location: portal.php');
    exit;
}

// Release session lock to allow concurrent AJAX polling requests to proceed
session_write_close();

// Configuration: Change this URL to your hosting server or GitHub repository URL
define('UPDATE_JSON_URL', 'https://pirxdry.org/updates/version.json');

// Fetch current version from database
$local_version = '1.0.0';
$ver_res = $conn->query("SELECT current_version FROM settings WHERE id = 1");
if ($ver_res && $row = $ver_res->fetch_assoc()) {
    $local_version = $row['current_version'] ?? '1.0.0';
}

// ----------------------------------------------------
// AJAX HANDLERS (To prevent memory limit exhaustion)
// ----------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    $step = $_GET['step'] ?? '';
    $progress_file = __DIR__ . '/download_progress.json';
    
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
                    // Extract files one by one, skipping online_updater.php and config.php
                    // to prevent overwriting our AJAX-enabled updater or local DB credentials
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        $base = basename($filename);
                        if ($base === 'online_updater.php' || $base === 'config.php') {
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

// ----------------------------------------------------
// STANDARD PAGE VIEW
// ----------------------------------------------------
$error = '';
$remote_info = null;

try {
    $context = stream_context_create([
        "http" => [
            "timeout" => 5,
            "user_agent" => "PHP-Updater-Client"
        ]
    ]);
    $json_data = @file_get_contents(UPDATE_JSON_URL, false, $context);
    if ($json_data) {
        $remote_info = json_decode($json_data, true);
    } else {
        $error = 'ناتوانرێت زانیاری نوێکردنەوە لە سێرڤەری سەرەکی وەربگیرێت (سێرڤەرەکە هێڵەکەی کار ناکات یان ناونیشانەکەی هەڵەیە).';
    }
} catch (Exception $e) {
    $error = 'هەڵەیەک ڕوویدا لە کاتی پەیوەندیکردن بە سێرڤەر: ' . $e->getMessage();
}

$page_title = 'سیستەمی ئەپدەیتی ئۆنلاین';
$active_nav = 'online_updater';
require_once 'includes/layout.php';
?>

<div class="settings-dashboard" style="max-width: 800px; margin: 30px auto; padding: 20px;">
    
    <div class="mgmt-card" style="background:#fff; border-radius:16px; border: 1px solid #eee; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow:hidden;">
        <div class="card-hdr" style="background:#5a0202; color:#fff; padding:20px 25px; display:flex; align-items:center; gap:12px;">
            <span style="font-size:1.5rem;">🔄</span>
            <h3 style="margin:0; font-size:1.2rem; font-weight:700;">سیستەمی نوێکردنەوەی ئۆنلاین (Auto-Updater)</h3>
        </div>

        <div class="card-body" style="padding: 30px;">
            
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
                <div style="font-size: 2rem; color: #5a0202;">➡️</div>
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
                    <span id="progressStatus" style="font-weight:700; color:#5a0202; font-size:0.9rem;">دەستپێکردنی ئەپدەیت...</span>
                    <span id="progressPercentage" style="font-weight:700; color:#C8A951; font-size:0.95rem;">0%</span>
                </div>
                <div style="width:100%; height:14px; background:#e0e0e0; border-radius:10px; overflow:hidden; box-shadow:inset 0 1px 3px rgba(0,0,0,0.1);">
                    <div id="progressBar" style="width:0%; height:100%; background:linear-gradient(90deg, #5a0202, #C8A951); transition:width 0.4s ease; border-radius:10px;"></div>
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
                        <button onclick="startAutoUpdate()" class="lib-btn lib-btn-primary" style="width:100%; height:50px; font-size:1.1rem; display:flex; align-items:center; justify-content:center; gap:10px;">
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
            <?php endif; ?>

            <div style="margin-top:40px; text-align:center;">
                <a href="portal.php" class="back-btn" style="text-decoration:none;">
                    ↩️ گەڕانەوە بۆ پۆرتاڵ
                </a>
            </div>

        </div>
    </div>

</div>

<script>
async function startAutoUpdate() {
    const confirmUpdate = await showConfirm(
        'دڵنیایت لە ئەپدیتکردن؟', 
        'هەموو فایلە کۆنەکان دەگۆڕدرێن بە فایلە نوێکان و داتابەیس نوێ دەبێتەوە.'
    );
    if (!confirmUpdate.isConfirmed) return;
    
    // Hide info panel and update button
    const infoPanel = document.getElementById('updateInfoPanel');
    if (infoPanel) infoPanel.style.display = 'none';
    document.getElementById('updateActionPanel').style.display = 'none';
    
    // Show progress bar
    const progressContainer = document.getElementById('progressContainer');
    progressContainer.style.display = 'block';
    
    updateProgress(0, 'خەریکی دەستپێکردنی دابەزاندنی پەڕگەی نوێکارییەکان (Download)...');
    
    try {
        // Step 1: Start Chunked Download
        let startResp = await fetch('online_updater.php?ajax=1&step=download_start');
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
                    let chunkResp = await fetch(`online_updater.php?ajax=1&step=download_chunk&start=${start}&end=${end}`);
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
        let resp = await fetch('online_updater.php?ajax=1&step=extract');
        let res = await resp.json();
        
        if (!res.success) {
            handleUpdateError(res.message);
            return;
        }
        
        // Step 3: Migration
        updateProgress(90, 'خەریکی نوێکردنەوە و چاکسازی داتابەیس (Database Migration)...');
        resp = await fetch('online_updater.php?ajax=1&step=migrate');
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
        
        showAlert('ئەپدەیت کرا!', 'سیستم بە سەرکەوتوویی نوێکرایەوە بۆ وەشانی نوێ.', 'success');
        
    } catch (err) {
        handleUpdateError('پەیوەندی لەگەڵ لۆکاڵ هۆست پچڕا یان هەڵەیەکی ناوخۆیی ڕوویدا.');
    }
}

function updateProgress(percent, statusText) {
    const progressBar = document.getElementById('progressBar');
    const progressStatus = document.getElementById('progressStatus');
    const progressPercentage = document.getElementById('progressPercentage');
    
    progressBar.style.width = percent + '%';
    progressPercentage.innerHTML = percent + '%';
    progressStatus.innerHTML = statusText;
}

function handleUpdateError(msg) {
    const progressStatus = document.getElementById('progressStatus');
    progressStatus.innerHTML = '❌ هەڵە: ' + msg;
    progressStatus.style.color = '#d32f2f';
    
    showAlert('هەڵە لە ئەپدیت', msg, 'error');
    document.getElementById('updateActionPanel').style.display = 'block';
}
</script>

<?php require_once 'includes/layout_end.php'; ?>
