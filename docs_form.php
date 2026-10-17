<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$user_id = $_SESSION['user_id'];
$user_data = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$current_user = strtolower($user_data['username'] ?? '');
$is_super_admin = ($current_user === 'admin' || $current_user === 'sangawy' || ($user_data['role'] ?? '') === 'owner');

if (!$is_super_admin && !(isset($user_data['can_access_docs']) && $user_data['can_access_docs'])) {
    header('Location: portal.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$doc_number = '';
$doc_date = date('Y-m-d');
$subject = '';
$type_id = 1;
$sender_receiver = '';
$physical_location = '';
$file_path = ''; // Keeping this for backward compatibility (main file)
$linked_doc_id = 0;
$content = '';

$error = '';
$success = '';

// Load existing doc
if ($id > 0) {
    $res = $conn->query("SELECT * FROM documents WHERE id = $id");
    if ($res && $row = $res->fetch_assoc()) {
        $doc_number = $row['doc_number'];
        $doc_date = $row['doc_date'];
        $subject = $row['subject'];
        $type_id = $row['type_id'];
        $sender_receiver = $row['sender_receiver'];
        $physical_location = $row['physical_location'] ?? '';
        $file_path = $row['file_path'];
        $content = $row['content'] ?? '';
        
        // check link
        $link_res = $conn->query("SELECT parent_id FROM document_links WHERE child_id = $id");
        if ($link_res && $l = $link_res->fetch_assoc()) {
            $linked_doc_id = $l['parent_id'];
        }
    }
}

// Fetch current attachments
$attachments = [];
if ($id > 0) {
    $att_res = $conn->query("SELECT * FROM document_attachments WHERE doc_id = $id");
    while($att = $att_res->fetch_assoc()) { $attachments[] = $att; }
}

// Fetch all documents for linking options (exclude self)
$all_docs = [];
$doc_res = $conn->query("SELECT id, doc_number, subject FROM documents WHERE id != $id ORDER BY id DESC");
if ($doc_res) {
    while($r = $doc_res->fetch_assoc()) {
        $all_docs[] = $r;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doc_number = $conn->real_escape_string($_POST['doc_number']);
    $doc_date = $conn->real_escape_string($_POST['doc_date']);
    $subject = $conn->real_escape_string($_POST['subject']);
    $type_id = (int)$_POST['type_id'];
    $sender_receiver = $conn->real_escape_string($_POST['sender_receiver']);
    $physical_location = $conn->real_escape_string($_POST['physical_location']);
    $linked_parent = (int)$_POST['linked_doc_id'];
    $content = isset($_POST['content']) ? $conn->real_escape_string($_POST['content']) : '';
    
    // File upload
    $upload_dir = 'assets/uploads/docs/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $uploaded_files = [];
    // Handle Multiple Files
    if (isset($_FILES['files'])) {
        foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                $orig_name = $_FILES['files']['name'][$key];
                $safe_name = time() . '_' . $key . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '', $orig_name);
                $dest = $upload_dir . $safe_name;
                
                if (move_uploaded_file($tmp_name, $dest)) {
                    $uploaded_files[] = ['path' => $dest, 'name' => $orig_name];
                }
            }
        }
    }
    
    // Handle single file from scan bridge
    if (isset($_POST['auto_scan_path']) && !empty($_POST['auto_scan_path'])) {
        $uploaded_files[] = ['path' => $_POST['auto_scan_path'], 'name' => basename($_POST['auto_scan_path'])];
    }

    if (empty($error)) {
        if ($id > 0) {
            $sql = "UPDATE documents SET 
                doc_number = '$doc_number', 
                doc_date = '$doc_date', 
                subject = '$subject', 
                type_id = $type_id, 
                sender_receiver = '$sender_receiver',
                physical_location = '$physical_location',
                content = '$content'";
            
            // If new files uploaded, update the main file_path if it was empty
            if (!empty($uploaded_files) && empty($file_path)) {
                $file_path = $uploaded_files[0]['path'];
                $sql .= ", file_path = '$file_path'";
            }
            
            $sql .= " WHERE id = $id";
            
            if ($conn->query($sql)) {
                // Save additional attachments
                foreach ($uploaded_files as $f) {
                    $conn->query("INSERT INTO document_attachments (doc_id, file_path, file_name) VALUES ($id, '{$f['path']}', '{$f['name']}')");
                }
                
                // Update links
                $conn->query("DELETE FROM document_links WHERE child_id = $id");
                if ($linked_parent > 0) {
                    $conn->query("INSERT INTO document_links (parent_id, child_id, relation_type) VALUES ($linked_parent, $id, 'Reply')");
                }
                
                logAction($user_id, 'Edit Document', "Updated document #$doc_number", $id);
                $success = 'نوسراو بە سەرکەوتوویی نوێکرایەوە.';
            } else {
                $error = 'هەڵە لە نوێکردنەوەی داتا';
            }
        } else {
            // Main file path from first upload
            $main_file = !empty($uploaded_files) ? $uploaded_files[0]['path'] : '';
            
            $sql = "INSERT INTO documents (doc_number, doc_date, subject, type_id, sender_receiver, physical_location, file_path, content) 
                    VALUES ('$doc_number', '$doc_date', '$subject', $type_id, '$sender_receiver', '$physical_location', '$main_file', '$content')";
            if ($conn->query($sql)) {
                $new_id = $conn->insert_id;
                
                // Save all attachments
                foreach ($uploaded_files as $f) {
                    $conn->query("INSERT INTO document_attachments (doc_id, file_path, file_name) VALUES ($new_id, '{$f['path']}', '{$f['name']}')");
                }
                
                if ($linked_parent > 0) {
                    $conn->query("INSERT INTO document_links (parent_id, child_id, relation_type) VALUES ($linked_parent, $new_id, 'Reply')");
                }
                
                logAction($user_id, 'Create Document', "Created document #$doc_number", $new_id);
                header("Location: docs.php");
                exit;
            }
        }
    }
}

// Logic to fetch latest scan from local folder (Manual override)
if (isset($_GET['fetch_scan'])) {
    $user_profile = getenv('USERPROFILE');
    $scan_folders = [$user_profile . '\Pictures\Scans', $user_profile . '\Documents\Scans', 'C:\Scans'];
    $latest_file = null; $latest_mtime = 0;
    foreach ($scan_folders as $folder) {
        if (is_dir($folder)) {
            $files = glob($folder . '\*.*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) > $latest_mtime) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                            $latest_mtime = filemtime($file); $latest_file = $file;
                        }
                    }
                }
            }
        }
    }
    if ($latest_file) {
        $upload_dir = 'assets/uploads/docs/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $new_name = 'fetch_scan_' . time() . '_' . basename($latest_file);
        $dest = $upload_dir . $new_name;
        if (copy($latest_file, $dest)) {
            $file_path = $dest;
            $success = 'فایلەکە بە سەرکەوتوویی لە سکانەرەوە وەرگیرا: ' . basename($latest_file);
        }
    } else {
        $error = 'هیچ فایلێکی نوێ لە فۆڵدەری سکانی ویندۆز نەدۆزرایەوە.';
    }
}

// Handle triggering the native Windows Scan Wizard
if (isset($_GET['trigger_wizard'])) {
    header('Content-Type: application/json');
    // Using start command to launch the Windows Image Acquisition Wizard wirelessly
    pclose(popen('start wiaacmgr.exe', 'r'));
    echo json_encode(['success' => true]);
    exit;
}

// Ajax handler to check for new scans
if (isset($_GET['check_new_scan'])) {
    header('Content-Type: application/json');
    $user_profile = getenv('USERPROFILE');
    $scan_folders = [$user_profile . '\Pictures\Scans', $user_profile . '\Documents\Scans', 'C:\Scans'];
    $latest_file = null; $latest_mtime = 0;
    foreach ($scan_folders as $folder) {
        if (is_dir($folder)) {
            $files = glob($folder . '\*.*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) > $latest_mtime) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                            $latest_mtime = filemtime($file); $latest_file = $file;
                        }
                    }
                }
            }
        }
    }
    if ($latest_file && $latest_mtime > ($_GET['since'] ?? 0)) {
        $upload_dir = 'assets/uploads/docs/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $new_name = 'live_scan_' . time() . '_' . basename($latest_file);
        $dest = $upload_dir . $new_name;
        if (copy($latest_file, $dest)) {
            echo json_encode(['found' => true, 'path' => $dest, 'name' => basename($latest_file), 'mtime' => $latest_mtime]);
            exit;
        }
    }
    echo json_encode(['found' => false]);
    exit;
}

// Logic to fetch latest scan from local folder
if (isset($_GET['fetch_scan'])) {
    $user_profile = getenv('USERPROFILE');
    $scan_folders = [
        $user_profile . '\Pictures\Scans',
        $user_profile . '\Documents\Scans',
        'C:\Scans'
    ];
    $latest_file = null; $latest_mtime = 0;
    foreach ($scan_folders as $folder) {
        if (is_dir($folder)) {
            $files = glob($folder . '\*.*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) > $latest_mtime) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                            $latest_mtime = filemtime($file); $latest_file = $file;
                        }
                    }
                }
            }
        }
    }
    if ($latest_file) {
        $upload_dir = 'assets/uploads/docs/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $new_name = 'auto_scan_' . time() . '_' . basename($latest_file);
        $dest = $upload_dir . $new_name;
        if (copy($latest_file, $dest)) {
            $file_path = $dest;
            $success = 'فایلەکە بە سەرکەوتوویی لە سکانەرەوە وەرگیرا: ' . basename($latest_file);
        }
    } else {
        $error = 'هیچ فایلێکی نوێ لە فۆڵدەری سکانی ویندۆز نەدۆزرایەوە.';
    }
}

$sys_module = 'docs';
$page_title = $id > 0 ? 'دەستکاری نوسراو' : 'زیادکردنی نوسراو';
$active_nav = 'docs';

require_once 'includes/layout.php';
?>
<!-- Camera Scan Modal -->
<div id="cameraModal" class="lib-overlay">
    <div class="lib-modal" style="width: 500px; max-width: 95%;">
        <div class="lib-modal-header">
            <h3>سکانکردن بە کامێرا</h3>
            <button class="lib-modal-close" onclick="closeCamera()">&times;</button>
        </div>
        <div class="lib-modal-body" style="text-align: center;">
            <video id="video" width="100%" height="auto" autoplay style="border-radius: 12px; background: #000;"></video>
            <canvas id="canvas" style="display:none;"></canvas>
            <div id="photoPreviewArea" style="display:none; margin-top: 15px;">
                <img id="photoPreview" style="width: 100%; border-radius: 12px;">
            </div>
        </div>
        <div class="lib-modal-footer">
            <button type="button" class="lib-btn lib-btn-gold" id="captureBtn" onclick="takeSnapshot()">📷 وێنەگرتن</button>
            <button type="button" class="lib-btn lib-btn-success" id="usePhotoBtn" style="display:none;" onclick="usePhoto()">✅ بەکارهێنان</button>
            <button type="button" class="lib-btn lib-btn-danger" onclick="closeCamera()">داخستن</button>
        </div>
    </div>
</div>

<!-- Scanner Workflow Modal (The direct bridge) -->
<div id="scannerWorkflowModal" class="lib-overlay">
    <div class="lib-modal" style="width: 500px; text-align: center;">
        <div class="lib-modal-header">
            <h3>سیستەمی سکانەری ناوخۆیی</h3>
            <button class="lib-modal-close" onclick="closeScannerWorkflow()">&times;</button>
        </div>
        <div class="lib-modal-body" style="padding: 30px;">
            <div id="scanStep1">
                <div style="font-size: 3rem; margin-bottom: 20px;">📠</div>
                <h4 style="margin-bottom: 15px;">سیستەمی سکانەری زیرەک</h4>
                <p style="color: #666; margin-bottom: 25px;">کلیک لە "دەستپێکردن" بکە و سکانەکە ئەنجام بدە، سیستمەکە خۆی فایلەکە دەهێنێت.</p>
                <button type="button" class="lib-btn lib-btn-gold" onclick="startScannerWorkflow()" style="width: 100%; justify-content: center; padding: 15px; font-weight: 700;">🚀 دەستپێکردنی سکانکردن</button>
            </div>
            
            <div id="scanStepLoading" style="display: none; padding: 20px;">
                <div class="scanner-loader" style="width: 50px; height: 50px; border: 4px solid #f3f3f3; border-top: 4px solid var(--gold); border-radius: 50%; margin: 0 auto 20px; animation: spin 1s linear infinite;"></div>
                <h4 style="color: var(--maroon);">لە چاوەڕوانی سکانەر...</h4>
                <p style="font-size: 0.9rem; color: #666; margin-top: 10px;">کاتێک سکانەکەت تەواو کرد و سەیڤت کرد، فایلەکە لێرە دەردەکەوێت.</p>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </div>
    </div>
</div>

<style>
    .upload-zone {
        border: 2px dashed var(--border);
        border-radius: 16px;
        padding: 30px;
        text-align: center;
        background: #fcfcfc;
        transition: all 0.2s;
        cursor: pointer;
        position: relative;
    }
    .upload-zone:hover { border-color: var(--maroon); background: #fff5f5; }
    .upload-zone.dragover { border-color: var(--gold); background: #fff9e6; }
    .upload-zone svg { width: 48px; height: 48px; color: #ccc; margin-bottom: 15px; }
    .upload-zone h4 { margin-bottom: 5px; color: #444; }
    .upload-zone p { font-size: 0.85rem; color: #999; }
    .file-preview-strip {
        margin-top: 15px;
        padding: 10px;
        background: #f0f0f0;
        border-radius: 10px;
        display: none;
        align-items: center;
        gap: 10px;
    }
</style>

<div class="lib-card" style="max-width: 850px; margin: 0 auto;">
    <div class="card-header">
        <h3><?php echo $page_title; ?></h3>
        <a href="docs.php" class="lib-btn lib-btn-gold">⬅️ گەڕانەوە</a>
    </div>
    
    <div style="padding: 25px;">
        <?php if($error): ?>
            <div class="lib-toast error" style="position:relative; transform:none; margin-bottom:20px;"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if($success): ?>
            <div class="lib-toast success" style="position:relative; transform:none; margin-bottom:20px;"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="lib-form-grid" style="margin-bottom: 20px;">
                <div class="lib-field">
                    <label>ژمارەی نوسراو *</label>
                    <input type="text" name="doc_number" required value="<?php echo htmlspecialchars($doc_number); ?>">
                </div>
                <div class="lib-field">
                    <label>ڕێکەوتی نوسراو *</label>
                    <input type="date" name="doc_date" required value="<?php echo htmlspecialchars($doc_date); ?>">
                </div>
            </div>
            
            <div class="lib-field" style="margin-bottom: 20px;">
                <label>بابەت *</label>
                <input type="text" name="subject" required value="<?php echo htmlspecialchars($subject); ?>">
            </div>
            
            <div class="lib-form-grid" style="margin-bottom: 20px;">
                <div class="lib-field">
                    <label>جۆری نوسراو *</label>
                    <select name="type_id">
                        <?php 
                        $types_res = $conn->query("SELECT * FROM document_types ORDER BY id ASC");
                        while($t = $types_res->fetch_assoc()):
                            // Security check for secret docs
                            if (($t['type_name'] === 'نهێنی' || $t['id'] == 5) && !$is_super_admin && !(isset($user_data['can_access_secret']) && $user_data['can_access_secret'])) {
                                continue;
                            }
                        ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo $type_id == $t['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['type_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="lib-field">
                    <label>نێرەر / وەرگر *</label>
                    <input type="text" name="sender_receiver" required value="<?php echo htmlspecialchars($sender_receiver); ?>">
                </div>
            </div>

            <div class="lib-field" style="margin-bottom: 20px;">
                <label>شوێنی فیزیایی (ئەرشیف)</label>
                <input type="text" name="physical_location" placeholder="بۆ نموونە: ژووری ئەرشیف، ڕەفەی ئەی" value="<?php echo htmlspecialchars($physical_location); ?>">
            </div>
            
            <div class="lib-field" style="margin-bottom: 25px;">
                <label>بارکردنی فایل (وێنە، PDF، وۆرد، ئێکسڵ) *</label>
                
                <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                    <button type="button" class="lib-btn lib-btn-gold" onclick="openScannerWorkflow()" style="flex: 1; justify-content: center; background: #27ae60; border-color: #27ae60;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        📠 سکانکردن و هێنانی نوسراو (Scanner)
                    </button>
                </div>

                <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <h4>کلیک بکە یان فایلەکان ڕابکێشە بۆ ئێرە</h4>
                    <p>دەتوانیت چەند فایلێک بە هەمان کات دیاری بکەیت</p>
                    <input type="file" name="files[]" id="fileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" multiple style="display: none;" onchange="handleFileSelect(this)">
                </div>

                <div id="filePreviewStrip" class="file-preview-strip" style="display: flex; flex-direction: column; align-items: flex-start;">
                    <!-- Previews go here -->
                </div>

                <?php if (!empty($attachments)): ?>
                    <div style="margin-top: 15px;">
                        <label style="font-weight: bold; display: block; margin-bottom: 10px;">هاوپێچەکانی ئێستا:</label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;">
                            <?php foreach($attachments as $att): ?>
                                <div style="background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; display: flex; align-items: center; justify-content: space-between;">
                                    <a href="<?php echo htmlspecialchars($att['file_path']); ?>" target="_blank" style="font-size: 0.8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($att['file_name']); ?></a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($file_path)): ?>
                    <div style="margin-top: 10px; font-size: 0.85rem; background: #e3f2fd; padding: 10px; border-radius: 8px;">
                        📄 فایلی ئێستا: <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" style="color: #1565c0; font-weight: 700;">بینینی فایل</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="lib-field" style="margin-bottom: 20px;">
                <label>بەستنەوە بە نوسراوی پێشوو</label>
                <select name="linked_doc_id" id="linked_doc_id">
                    <option value="0">هیچ (بەستنەوە مەبە)</option>
                    <?php foreach($all_docs as $d): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo $linked_doc_id == $d['id'] ? 'selected' : ''; ?>>
                            نوسراوی ژمارە <?php echo htmlspecialchars($d['doc_number']); ?> - <?php echo htmlspecialchars($d['subject']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <hr style="border:0; border-top: 1px solid var(--border); margin: 25px 0;">
            
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="submit" class="lib-btn lib-btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    پاشەکەوتکردن
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let video = document.getElementById('video');
let canvas = document.getElementById('canvas');
let stream = null;

let pollTimer = null;
let lastCheckTime = Math.floor(Date.now() / 1000);

function openScannerWorkflow() {
    document.getElementById('scannerWorkflowModal').classList.add('show');
    document.getElementById('scannerWorkflowModal').querySelector('.lib-modal').classList.add('show');
    document.getElementById('scanStep1').style.display = 'block';
    document.getElementById('scanStepLoading').style.display = 'none';
}

function startScannerWorkflow() {
    lastCheckTime = Math.floor(Date.now() / 1000);
    document.getElementById('scanStep1').style.display = 'none';
    document.getElementById('scanStepLoading').style.display = 'block';
    
    // Call the PHP handler to launch the official Windows Scanner Wizard
    fetch('?trigger_wizard=1')
        .then(res => res.json())
        .then(data => {
            // Start polling for new file
            startPolling();
        });
}

function startPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(() => {
        fetch(`?check_new_scan=1&since=${lastCheckTime}`)
            .then(res => res.json())
            .then(data => {
                if (data.found) {
                    clearInterval(pollTimer);
                    // Automatically add the file to the form
                    finishAutoScan(data.path, data.name);
                }
            });
    }, 2000);
}

function finishAutoScan(path, name) {
    closeScannerWorkflow();
    // Simulate file input selection or show preview
    document.getElementById('filePreviewStrip').style.display = 'flex';
    document.getElementById('fileNameDisplay').textContent = name;
    
    // Add a hidden input to the form to pass the path
    let hiddenPath = document.createElement('input');
    hiddenPath.type = 'hidden';
    hiddenPath.name = 'auto_scan_path';
    hiddenPath.value = path;
    document.querySelector('form').appendChild(hiddenPath);
    
    // Toast notification
    alert('فایلەکە بە سەرکەوتوویی وەرگیرا و ئامادەیە بۆ ناردن');
}

function closeScannerWorkflow() {
    if (pollTimer) clearInterval(pollTimer);
    document.getElementById('scannerWorkflowModal').classList.remove('show');
    document.getElementById('scannerWorkflowModal').querySelector('.lib-modal').classList.remove('show');
}

async function openCamera() {
    document.getElementById('cameraModal').classList.add('show');
    document.getElementById('cameraModal').querySelector('.lib-modal').classList.add('show');
    document.getElementById('photoPreviewArea').style.display = 'none';
    document.getElementById('video').style.display = 'block';
    document.getElementById('captureBtn').style.display = 'inline-flex';
    document.getElementById('usePhotoBtn').style.display = 'none';
    
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
        video.srcObject = stream;
    } catch (err) {
        alert("ناتوانرێت دەست بە کامێرا بگات: " + err);
        closeCamera();
    }
}

function closeCamera() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
    document.getElementById('cameraModal').classList.remove('show');
    document.getElementById('cameraModal').querySelector('.lib-modal').classList.remove('show');
}

function takeSnapshot() {
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    
    let imgData = canvas.toDataURL('image/jpeg');
    document.getElementById('photoPreview').src = imgData;
    document.getElementById('photoPreviewArea').style.display = 'block';
    document.getElementById('video').style.display = 'none';
    document.getElementById('captureBtn').style.display = 'none';
    document.getElementById('usePhotoBtn').style.display = 'inline-flex';
}

function handleFileSelect(input) {
    const strip = document.getElementById('filePreviewStrip');
    strip.innerHTML = '';
    
    if (input.files && input.files.length > 0) {
        strip.style.display = 'flex';
        Array.from(input.files).forEach(file => {
            const item = document.createElement('div');
            item.style.cssText = 'display:flex; align-items:center; gap:10px; margin-bottom:5px; background:#fff; padding:5px 10px; border-radius:6px; border:1px solid #ddd; width:100%;';
            item.innerHTML = `
                <span style="font-size:0.8rem; font-weight:600; flex:1;">${file.name}</span>
                <span class="lib-badge lib-badge-green" style="font-size:0.7rem;">ئامادەیە</span>
            `;
            strip.appendChild(item);
        });
    }
}

// Drag & Drop logic
let zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => { zone.classList.remove('dragover'); });
zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        document.getElementById('fileInput').files = e.dataTransfer.files;
        handleFileSelect(document.getElementById('fileInput'));
    }
});

function usePhoto() {
    canvas.toBlob(function(blob) {
        let file = new File([blob], "camera_snap_" + Date.now() + ".jpg", { type: "image/jpeg" });
        let container = new DataTransfer();
        container.items.add(file);
        document.getElementById('fileInput').files = container.files;
        handleFileSelect(document.getElementById('fileInput'));
        closeCamera();
    }, 'image/jpeg');
}
</script>

<?php 
echo '</div></div></div></body></html>'; 
?>
