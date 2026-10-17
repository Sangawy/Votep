<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$user_id = (int)$_SESSION['user_id'];
$user_data = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$current_user = strtolower($user_data['username'] ?? '');
$is_super_admin = ($current_user === 'admin' || $current_user === 'sangawy' || ($user_data['role'] ?? '') === 'owner');

// Self-healing: ensure related_doc_id column exists
$check_rel = $conn->query("SHOW COLUMNS FROM archive_docs LIKE 'related_doc_id'");
if ($check_rel && $check_rel->num_rows == 0) {
    $conn->query("ALTER TABLE archive_docs ADD COLUMN related_doc_id INT NULL AFTER created_by");
}
$check_word = $conn->query("SHOW COLUMNS FROM archive_docs LIKE 'word_content'");
if ($check_word && $check_word->num_rows == 0) {
    $conn->query("ALTER TABLE archive_docs ADD COLUMN word_content TEXT NULL AFTER subject");
}
$check_committee = $conn->query("SHOW COLUMNS FROM archive_docs LIKE 'committee_data'");
if ($check_committee && $check_committee->num_rows == 0) {
    $conn->query("ALTER TABLE archive_docs ADD COLUMN committee_data JSON NULL AFTER word_content");
}
$check_attach_list = $conn->query("SHOW COLUMNS FROM archive_docs LIKE 'attachments_list_text'");
if ($check_attach_list && $check_attach_list->num_rows == 0) {
    $conn->query("ALTER TABLE archive_docs ADD COLUMN attachments_list_text TEXT NULL AFTER committee_data");
}
$check_attach_preview = $conn->query("SHOW COLUMNS FROM archive_docs LIKE 'attachments_preview_text'");
if ($check_attach_preview && $check_attach_preview->num_rows == 0) {
    $conn->query("ALTER TABLE archive_docs ADD COLUMN attachments_preview_text TEXT NULL AFTER attachments_list_text");
}
$check_sign_by = $conn->query("SHOW COLUMNS FROM archive_docs LIKE 'sign_by'");
if ($check_sign_by && $check_sign_by->num_rows == 0) {
    $conn->query("ALTER TABLE archive_docs ADD COLUMN sign_by VARCHAR(50) NULL DEFAULT 'director' AFTER attachments_preview_text");
}

if (!$is_super_admin && !(isset($user_data['can_access_docs']) && $user_data['can_access_docs'])) {
    header('Location: portal.php');
    exit;
}

$doc_types = [];
$types_res = $conn->query("SELECT * FROM document_types ORDER BY id ASC");
if ($types_res) {
    while ($type = $types_res->fetch_assoc()) {
        $doc_types[] = $type;
    }
}
if (empty($doc_types)) {
    $doc_types = [
        ['id' => 0, 'type_name' => 'هاتوو'],
        ['id' => 0, 'type_name' => 'دەرچوو'],
    ];
}

$correspondent_options = [];
$corr_sql = "
    SELECT name FROM (
        SELECT TRIM(correspondent) AS name, MAX(id) AS last_id
        FROM archive_docs
        WHERE correspondent IS NOT NULL AND TRIM(correspondent) <> ''
        GROUP BY TRIM(correspondent)
        UNION
        SELECT TRIM(sender_receiver) AS name, MAX(id) AS last_id
        FROM documents
        WHERE sender_receiver IS NOT NULL AND TRIM(sender_receiver) <> ''
        GROUP BY TRIM(sender_receiver)
    ) AS names
    ORDER BY last_id DESC
    LIMIT 300
";
$corr_res = $conn->query($corr_sql);
if ($corr_res) {
    while ($row = $corr_res->fetch_assoc()) {
        $name = trim((string)($row['name'] ?? ''));
        if ($name !== '') $correspondent_options[] = $name;
    }
}
$correspondent_options = array_values(array_unique($correspondent_options));

// Fetch recent documents for linking
$recent_docs = [];
$rd_res = $conn->query("SELECT id, doc_number, subject, doc_type FROM archive_docs ORDER BY id DESC LIMIT 100");
if ($rd_res) {
    while($row = $rd_res->fetch_assoc()) $recent_docs[] = $row;
}

function normalizeArchiveDocTypeLocal(string $type): string {
    $type = trim($type);
    if (in_array($type, ['هاتوو', 'Inbound'], true)) return 'Inbound';
    if (in_array($type, ['دەرچوو', 'دەرچو', 'Outbound'], true)) return 'Outbound';
    return $type ?: 'Outbound';
}

function getNextArchiveDocNumberByType(mysqli $conn, string $docType): int {
    $normalized = $conn->real_escape_string(normalizeArchiveDocTypeLocal($docType));
    $sql = "SELECT MAX(CAST(doc_number AS UNSIGNED)) AS max_num
            FROM archive_docs
            WHERE doc_number REGEXP '^[0-9]+$'
              AND (doc_type = '$normalized' OR doc_type = '" . ($normalized === 'Inbound' ? 'هاتوو' : 'دەرچوو') . "')";
    $res = $conn->query($sql);
    $max = 0;
    if ($res && $row = $res->fetch_assoc()) {
        $max = (int)($row['max_num'] ?? 0);
    }
    return $max + 1;
}

if (isset($_GET['next_doc_num'])) {
    header('Content-Type: application/json; charset=utf-8');
    $requestedType = $_GET['type'] ?? 'Outbound';
    echo json_encode(['next' => getNextArchiveDocNumberByType($conn, $requestedType)]);
    exit;
}

$sys_module = 'docs';
$page_title = 'دروستکردنی نوسراوی فەرمی';
$active_nav = 'docs_create';

// Edit Mode Check
$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$edit_doc = null;
if ($edit_id > 0) {
    $res = $conn->query("SELECT a.*, r.doc_number as rel_num, r.subject as rel_subject, r.doc_type as rel_type 
                          FROM archive_docs a 
                          LEFT JOIN archive_docs r ON a.related_doc_id = r.id 
                          WHERE a.id = $edit_id");
    if ($res && $edit_doc = $res->fetch_assoc()) {
        $page_title = 'دەستکاریکردنی نوسراو';
        $active_nav = 'docs';
    }
}

$defaultTypeName = $edit_doc ? $edit_doc['doc_type'] : ($doc_types[0]['type_name'] ?? 'دەرچوو');
$doc_num_val = $edit_doc ? $edit_doc['doc_number'] : getNextArchiveDocNumberByType($conn, $defaultTypeName);
$doc_date_val = $edit_doc ? $edit_doc['doc_date'] : date('Y-m-d');
$doc_to_val = $edit_doc ? $edit_doc['correspondent'] : '';
$doc_subject_val = $edit_doc ? $edit_doc['subject'] : '';
$rel_id_val = $edit_doc ? $edit_doc['related_doc_id'] : '';
$rel_text_val = ($edit_doc && $edit_doc['rel_num']) ? "[{$edit_doc['rel_type']}] ژمارە {$edit_doc['rel_num']} - {$edit_doc['rel_subject']}" : '--- هیچ نوسراوێک ببەستەرەوە ---';
$attachments_list_val = $edit_doc['attachments_list_text'] ?? '';
$attachments_preview_val = $edit_doc['attachments_preview_text'] ?? '';

require_once 'includes/layout.php';
?>

<style>
    .doc-template-card { max-width: 100%; margin: 0; border-radius: 0; border: none; box-shadow: none; }
    .lib-container { padding: 10px !important; }
    .template-help { background: #fff8e1; border: 1px solid #ffe0a3; color: #8a5a00; border-radius: 12px; padding: 14px 16px; margin-bottom: 22px; font-weight: 700; line-height: 1.7; }
    .doc-action-row { display: flex; justify-content: flex-end; gap: 10px; margin-top: 28px; }
    .doc-action-row .lib-btn { padding: 14px 28px; font-size: 1rem; }
    .content-row-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
    .ready-templates-modal { width: min(1080px, 98vw); max-width: 98vw; }
    .ready-templates-grid { display: grid; grid-template-columns: 1.1fr 1fr; gap: 14px; }
    .ready-search-row { margin-bottom: 12px; }
    .ready-list-box { border: 1px solid #e3e7eb; border-radius: 10px; background: #fcfdff; max-height: 380px; overflow: auto; padding: 8px; }
    .ready-item { border: 1px solid #e6eaef; border-radius: 8px; padding: 10px; margin-bottom: 8px; background: #fff; }
    .ready-item-title { font-weight: 700; color: #073b4c; margin-bottom: 6px; }
    .ready-item-preview { font-size: 12px; color: #666; max-height: 44px; overflow: hidden; }
    .ready-item-actions { display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap; }
    .quick-add-wrap { margin-top: 8px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .quick-add-list { display: flex; gap: 6px; flex-wrap: wrap; }
    .quick-add-chip { border: 1px solid #cfd8dc; background: #f7fbfc; color: #0b3a4a; border-radius: 999px; padding: 6px 10px; cursor: pointer; font-size: 12px; }
    .quick-add-del { border: none; background: #ffe8e8; color: #a22; border-radius: 999px; padding: 5px 8px; cursor: pointer; font-size: 11px; }
    @media (max-width: 860px) {
        .ready-templates-grid { grid-template-columns: 1fr; }
    }

    /* Custom Searchable Dropdown */
    .lib-search-dropdown { position: relative; width: 100%; }
    .lib-search-trigger { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; text-align: right; cursor: pointer; display: flex; justify-content: space-between; align-items: center; min-height: 42px; }
    .lib-search-trigger:after { content: '▼'; font-size: 10px; color: #718096; margin-right: 8px; }
    .lib-search-menu { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; margin-top: 5px; z-index: 1000; box-shadow: 0 10px 25px rgba(0,0,0,0.1); display: none; flex-direction: column; overflow: hidden; }
    .lib-search-menu.show { display: flex; }
    .lib-search-header { padding: 10px; background: #f8fafc; border-bottom: 1px solid #edf2f7; }
    .lib-search-input { width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 8px; font-size: 14px; }
    .lib-search-filters { display: flex; gap: 6px; }
    .filter-btn { flex: 1; padding: 6px; font-size: 12px; border: 1px solid #e2e8f0; border-radius: 4px; background: #fff; cursor: pointer; transition: all 0.2s; }
    .filter-btn.active { background: var(--gold); color: #fff; border-color: var(--gold); }
    .lib-search-list { max-height: 250px; overflow-y: auto; }
    .lib-search-item { padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f7fafc; font-size: 14px; transition: background 0.2s; }
    .lib-search-item:hover { background: #f0f4f8; }
    .lib-search-item:last-child { border-bottom: none; }
    .lib-search-item .type-badge { font-size: 10px; padding: 2px 6px; border-radius: 4px; margin-left: 6px; background: #edf2f7; color: #4a5568; }

    /* Scanner Modal */
    #scannerModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:2000; flex-direction:column; align-items:center; justify-content:center; padding: 20px; }
    .scanner-container { background:#fff; border-radius:15px; width:min(500px, 95vw); overflow:hidden; display:flex; flex-direction:column; }
    .scanner-header { padding:15px; background:#0b3a4a; color:#fff; display:flex; justify-content:space-between; align-items:center; }
    .scanner-body { padding:30px; text-align:center; }
    .scanner-footer { padding:15px; border-top:1px solid #eee; display:flex; gap:10px; justify-content:center; }
    .captured-preview { margin-top:10px; border:2px solid var(--gold); border-radius:8px; max-height: 200px; }

    /* Committee Table UI */
    .committee-section { background: #f8fafc; border: 1px solid #edf2f7; border-radius: 12px; padding: 20px; margin-bottom: 25px; width: 100%; box-sizing: border-box; }
    .committee-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #fff; padding-bottom: 12px; }
    .committee-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 12px; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
    .committee-table th { background: #fff; color: #4a5568; font-weight: 800; padding: 12px; border: 1px solid #e2e8f0; text-align: center; font-size: 13px; }
    .committee-table td { padding: 10px; border: 1px solid #e2e8f0; background: #fff; }
    .committee-row:hover td { background: #f8fafc; }
    .committee-num { width: 50px; text-align: center; color: #718096; font-weight: 700; background: #f1f5f9 !important; }
    .committee-del-cell { width: 50px; text-align: center; background: #fff !important; }
    .committee-field { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e0; border-radius: 8px; font-size: 14px; transition: all 0.2s; background: #fff; }
    .committee-field:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(11, 58, 74, 0.1); background: #fff; }
    .btn-add-row { background: #2d3748; color: #fff; border: none; border-radius: 6px; padding: 6px 12px; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 6px; }
    .btn-add-row:hover { background: #1a202c; }
    .btn-add-col { background: #edf2f7; color: #2d3748; border: 1px solid #cbd5e0; border-radius: 6px; padding: 6px 12px; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 6px; }
    .btn-add-col:hover { background: #e2e8f0; }
    .btn-del-row { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; border-radius: 4px; padding: 4px 8px; cursor: pointer; font-size: 14px; }
    .btn-del-row:hover { background: #fed7d7; }
    .header-input { background: transparent; border: none; font-weight: 700; color: #4a5568; text-align: center; width: 100%; font-size: 13px; }
    .header-input:focus { outline: none; background: #fff; }
    .col-del-btn { color: #a0aec0; cursor: pointer; font-size: 10px; margin-right: 4px; }
    .col-del-btn:hover { color: #e53e3e; }

    /* File Viewer Styles */
    #fileViewerBody { background: #eef1f5; display: flex; flex-direction: row-reverse; }
    #fileViewerPanel { width: 230px; background: #fff; border-left: 1px solid #dfe3e8; padding: 12px; display: flex; flex-direction: column; gap: 8px; overflow-y: auto; }
    #fileViewerMain { flex: 1; overflow: auto; padding: 14px; }
    #fileViewerContent { min-height: 100%; }
    .a4-stage { display: flex; justify-content: center; padding: 18px 0; }
    .a4-page {
        width: 100%; min-height: calc(90vh - 220px); max-width: 100%; background: #fff; border: 1px solid #d4d9de;
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.16); border-radius: 4px; padding: 70px 58px; color: #1f2328;
        font-family: 'Rabar005', 'Rabar022', Tahoma, Arial, sans-serif; font-size: 16px; line-height: 1.95; direction: rtl; text-align: right; unicode-bidi: plaintext; word-break: break-word;
    }
    .a4-page, .a4-page * { direction: rtl; text-align: right; unicode-bidi: plaintext; }
    .viewer-hit { background: #ffec99; border-radius: 3px; padding: 0 2px; }
    .viewer-hit.active { background: #ffc107; }
    @media (max-width: 860px) {
        #fileViewerBody { flex-direction: column; }
        #fileViewerPanel { width: 100%; border-left: none; border-bottom: 1px solid #dfe3e8; }
        .a4-page { padding: 28px 18px; min-height: auto; }
    }
</style>

<div class="lib-card doc-template-card">
    <div class="card-header">
        <h3 id="form_title"><?php echo $edit_doc ? 'نوسینگە - دەستکاریکردنی نوسراو' : 'نوسینگە - دروستکردنی نوسراو'; ?></h3>
    </div>

    <form id="docForm" action="<?php echo $edit_doc ? 'docs_update_process.php' : 'generate_docx.php'; ?>" method="POST" enctype="multipart/form-data" style="padding: 25px;">
        <?php if ($edit_doc): ?>
            <input type="hidden" name="doc_id" value="<?php echo $edit_id; ?>">
        <?php endif; ?>
        
        <div class="template-help">
            <?php if ($edit_doc): ?>
                تۆ ئێستا دەستکاری نوسراوی ژمارە <strong><?php echo htmlspecialchars($edit_doc['doc_number']); ?></strong> دەکەیت. دوای تەواوبوون کلیک لە "پاشەکەوتکردنی گۆڕانکارییەکان" بکە.
            <?php else: ?>
                ژمارەی نوسراو بە شێوەی ئۆتۆ پێشنیار دەکرێت. جۆری نوسراو دیاری بکە، پاشان کلیک لە «دەقی نوسراوە» بکە بۆ دروستکردنی فایل لە ڕێڕەوی ئەرشیف و کردنەوەی ڕاستەوخۆ لە Word.
            <?php endif; ?>
        </div>

        <div class="lib-form-grid" style="margin-bottom: 20px; grid-template-columns: repeat(3, minmax(0, 1fr));">
            <div class="lib-field">
                <label>ژمارەی نوسراو</label>
                <input type="text" id="doc_num" name="doc_num" value="<?php echo htmlspecialchars((string)$doc_num_val); ?>" required>
            </div>
            <div class="lib-field">
                <label>ڕێکەوت</label>
                <input type="date" name="doc_date" value="<?php echo htmlspecialchars($doc_date_val); ?>" required>
            </div>
            <div class="lib-field">
                <label>جۆری نوسراو</label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <select name="doc_type" id="doc_type" required style="flex: 1;" onchange="toggleSectionsByType(this.value)">
                        <?php foreach ($doc_types as $type): ?>
                            <?php 
                                $type_name = trim($type['type_name'] ?? ''); 
                                $doc_type_db = trim($edit_doc['doc_type'] ?? '');
                                
                                // Robust matching
                                $is_match = false;
                                if ($edit_doc) {
                                    if ($doc_type_db === $type_name) {
                                        $is_match = true;
                                    } else {
                                        $norm_db = normalizeArchiveDocTypeLocal($doc_type_db);
                                        $norm_ui = normalizeArchiveDocTypeLocal($type_name);
                                        if ($norm_db === $norm_ui) {
                                            $is_match = true;
                                        }
                                    }
                                }
                                $sel = $is_match ? 'selected' : '';
                            ?>
                            <option value="<?php echo htmlspecialchars($type_name); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($type_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="display: flex; align-items: center; gap: 5px; font-size: 0.8rem; white-space: nowrap;">
                        <input type="checkbox" id="pin_doc_type" style="width: 14px; height: 14px; cursor: pointer;">
                        <label for="pin_doc_type" style="cursor: pointer;">پین</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="lib-form-grid" style="margin-bottom: 20px;">
            <div class="lib-field">
                <label>بۆ بەڕێز</label>
                <input type="text" name="doc_to" list="correspondent_list" placeholder="ناوی بەڕێز یان وەرگر بنووسە..." value="<?php echo htmlspecialchars($doc_to_val); ?>" required>
                <datalist id="correspondent_list">
                    <?php foreach ($correspondent_options as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="lib-field">
                <label>بەستنەوە بە نوسراوی تر (Related Document)</label>
                <div class="lib-search-dropdown" id="relatedDocDropdown">
                    <div class="lib-search-trigger" id="relTrigger"><?php echo htmlspecialchars($rel_text_val); ?></div>
                    <input type="hidden" name="related_doc_id" id="relInput" value="<?php echo htmlspecialchars((string)$rel_id_val); ?>">
                    <div class="lib-search-menu" id="relMenu">
                        <div class="lib-search-header">
                            <input type="text" class="lib-search-input" id="relSearch" placeholder="بگەڕێ بۆ ژمارە یان بابەت...">
                            <div class="lib-search-filters">
                                <button type="button" class="filter-btn active" data-filter="all">هەموو</button>
                                <button type="button" class="filter-btn" data-filter="Inbound">هاتوو</button>
                                <button type="button" class="filter-btn" data-filter="Outbound">دەرچوو</button>
                            </div>
                        </div>
                        <div class="lib-search-list" id="relList">
                            <div class="lib-search-item" data-id="" data-type="all">-- هیچ نوسراوێک --</div>
                            <?php foreach ($recent_docs as $rd): ?>
                                <div class="lib-search-item" 
                                     data-id="<?php echo $rd['id']; ?>" 
                                     data-type="<?php echo $rd['doc_type']; ?>"
                                     data-number="<?php echo $rd['doc_number']; ?>"
                                     data-subject="<?php echo htmlspecialchars($rd['subject']); ?>">
                                    <span class="type-badge"><?php echo $rd['doc_type'] == 'Inbound' ? 'هاتوو' : 'دەرچوو'; ?></span>
                                    ژمارە <strong><?php echo $rd['doc_number']; ?></strong> - <?php echo htmlspecialchars($rd['subject']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="lib-field" style="margin-bottom: 20px;">
            <label>بابەت</label>
            <input type="text" name="doc_subject" placeholder="بابەتی نوسراوەکە بنووسە..." value="<?php echo htmlspecialchars($doc_subject_val); ?>" required>
        </div>

        <!-- Scanned File Upload (Visible for Inbound) -->
        <div id="scanned_file_section" class="lib-field" style="margin-bottom: 20px; display: none; background: #f0f4f8; padding: 15px; border-radius: 8px; border: 2px dashed #cbd5e0;">
            <label style="color: #2c5282;">📂 بارکردنی فایلی سکانکراو (Scanned Image/PDF)</label>
            
            <?php if ($edit_doc && !empty($edit_doc['file_path'])): ?>
                <div style="background: #fff; padding: 10px; border-radius: 8px; margin-bottom: 12px; border: 1px solid #cbd5e0; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">📄</span>
                        <div>
                            <div style="font-weight: 700; font-size: 13px; color: #2d3748;">فایلی ئێستا:</div>
                            <div style="font-size: 11px; color: #718096; direction: ltr; text-align: left;"><?php echo basename($edit_doc['file_path']); ?></div>
                        </div>
                    </div>
                    <button type="button" onclick="openFileViewer(0)" class="lib-btn lib-btn-secondary" style="padding: 5px 12px; font-size: 12px;">بینینی فایل</button>
                </div>
            <?php endif; ?>

            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="file" name="scanned_file" id="scanned_file_input" accept="image/*,.pdf" style="flex: 1;">
                <button type="button" id="startScanBtn" class="lib-btn lib-btn-gold" style="padding: 8px 15px; white-space: nowrap;">
                    🖨️ سکانکردنی فیزیایی
                </button>
            </div>
            <input type="hidden" name="scanned_base64" id="scanned_base64">
            <div id="scan_preview_wrap" style="display:none; margin-top:10px;">
                <img id="scan_preview_img" class="captured-preview" src="">
                <button type="button" class="lib-btn lib-btn-danger" id="removeScanBtn" style="padding:4px 8px; font-size:11px; margin-top:5px;">سڕینەوەی سکان</button>
            </div>
            <small style="color: #718096; display: block; margin-top: 5px;">بۆ نوسراوە هاتوەکان، وێنەی سکانکراو یان PDF لێرە ئەپلۆد بکە یان ڕاستەوخۆ سکانی بکە.</small>
        </div>

        <div id="word_content_section" class="lib-field" style="margin-bottom: 20px;">
            <div class="content-row-header">
                <label style="margin: 0;">ناوەڕۆکی نوسراوە (Word Content)</label>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="lib-btn lib-btn-gold" id="openReadyTemplatesBtn" style="padding: 8px 12px;">نوسراوی ئامادە</button>
                    <button type="button" class="lib-btn lib-btn-primary" id="saveCurrentAsTemplateBtn" style="padding: 8px 12px;">پاشەکەوت وەک تێمپلێت</button>
                </div>
            </div>
            <textarea id="doc_content" name="doc_content" rows="6" placeholder="ناوەڕۆکی نوسراوەکە بنووسە..."><?php echo $edit_doc ? htmlspecialchars($edit_doc['word_content'] ?? '') : ''; ?></textarea>
            <div class="quick-add-wrap">
                <button type="button" class="lib-btn lib-btn-primary" id="saveSelectionQuickAddBtn" style="padding:6px 10px;">کویک ئەد (هەڵبژاردە)</button>
                <div id="quickAddList" class="quick-add-list"></div>
            </div>
        </div>

        <div class="lib-field" style="margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0;">
            <label style="font-weight:700; color:#2d3748;">هاوپێچ بۆ Word</label>
            <small style="display:block; color:#718096; margin:6px 0 10px;">هەر هاوپێچێک لە سەتڕێکی جیا بنووسە. ئەم ناوانە لە `[[ATTACHMENTS_LIST]]` دەردەکەون.</small>
            <textarea name="attachments_list_text" rows="4" placeholder="بۆ نموونە:&#10;لێی نوسراوی فەرمی&#10;لیستی ناومان&#10;فەرمانی کارگێری"><?php echo htmlspecialchars($attachments_list_val); ?></textarea>
            <div style="height:10px;"></div>
            <label style="font-weight:700; color:#2d3748;">وێنەمەک بۆ</label>
            <small style="display:block; color:#718096; margin:6px 0 10px;">مەبەست: ئەم دۆکۆمێنتە بۆ کام بەش/فایل بۆکس/شوێنەکان کۆپی لێ هەبێت. هەر شوێنێک لە سەتڕێکی جیا بنووسە. ئەم ناوانە لە `[[ATTACHMENTS_PREVIEW]]` دەردەکەون.</small>
            <textarea name="attachments_preview_text" rows="4" placeholder="بۆ نموونە:&#10;نوسراوی فەرمی&#10;لیستی ناومان&#10;فەرمانی کارگێری"><?php echo htmlspecialchars($attachments_preview_val); ?></textarea>
        </div>

        <!-- Signature Type Section (Visible for Outbound) -->
        <div id="signature_type_section" class="lib-field" style="margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0;">
            <label style="font-weight:700; color:#2d3748; margin-bottom: 8px; display: block;">✍️ ڕێکخستنی واژووی نوسراو (Signature Settings)</label>
            <small style="display:block; color:#718096; margin-bottom:12px;">دیاری بکە نوسراوەکە بە ناوی بەڕێوەبەرەوە یان بە ناوی ڕێکخراوەکە واژوو دەکرێت:</small>
            <div style="display: flex; gap: 24px; align-items: center; flex-wrap: wrap;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 700; color: #2d3748;">
                    <input type="checkbox" id="sign_by_director" name="sign_by_director" value="1" <?php echo ($edit_doc && ($edit_doc['sign_by'] ?? '') === 'organization') ? '' : 'checked'; ?> style="width: 18px; height: 18px; cursor: pointer;" onchange="toggleSignBy('director')">
                    بە ناوی بەڕێوەبەرەوە
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 700; color: #2d3748;">
                    <input type="checkbox" id="sign_by_organization" name="sign_by_organization" value="1" <?php echo ($edit_doc && ($edit_doc['sign_by'] ?? '') === 'organization') ? 'checked' : ''; ?> style="width: 18px; height: 18px; cursor: pointer;" onchange="toggleSignBy('organization')">
                    بە ناوی ڕێکخراوەکەوە
                </label>
            </div>
        </div>

        <div class="committee-section" id="committee_section">
            <div class="committee-header">
                <label style="margin: 0; font-weight: 700; color: #2d3748;">📋 دروستکردنی خشتەی لیژنە (Committee Table)</label>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn-add-col" onclick="addCommitteeCol()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        کۆڵۆمی نوێ
                    </button>
                    <button type="button" class="btn-add-row" onclick="addCommitteeRow()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        ڕیزی نوێ
                    </button>
                </div>
            </div>
            <div style="overflow-x: auto;">
                <table class="committee-table" id="committee_table_el">
                    <thead>
                        <tr id="committee_thead_row">
                            <th class="committee-num">ژ</th>
                            <!-- Headers added here -->
                            <th class="committee-del-cell"></th>
                        </tr>
                    </thead>
                    <tbody id="committee_tbody">
                        <!-- Rows added here -->
                    </tbody>
                </table>
            </div>
            <small style="color: #718096; font-size: 12px;">تێبینی: هەر خانەیەک کە پڕی دەکەیتەوە لە خشتەکەدا دەردەکەوێت. دەتوانیت ناوی سەرەوەی ستوونەکان (Header) بگۆڕیت.</small>
        </div>


        <div class="doc-action-row">
            <?php if ($edit_doc): ?>
                <button type="button" onclick="submitForm('db')" class="lib-btn lib-btn-gold" style="flex: 1;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    تەنها نوێکردنەوەی زانیارییەکان
                </button>
                <button type="button" onclick="submitForm('word')" class="lib-btn lib-btn-primary" style="flex: 1;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    حەفز و کردنەوەی Word
                </button>
            <?php else: ?>
                <button type="submit" id="submitBtn" class="lib-btn lib-btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <span id="submitBtnText">دەقی نوسراوە (Word)</span>
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
function submitForm(type) {
    const form = document.getElementById('docForm');
    if (type === 'db') {
        form.action = 'docs_update_process.php';
    } else {
        form.action = 'generate_docx.php';
    }
    form.submit();
}

function escapeHtml(text) {
    if (!text) return '';
    return text
        .toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

let committeeCols = ['ناوی سیانی', 'ئەرک / لیژنە'];

window.updateHeader = function(index, value) {
    committeeCols[index] = value;
};

window.addCommitteeCol = function(title = 'کۆڵۆمی نوێ') {
    committeeCols.push(title);
    renderCommitteeTable();
};

window.removeCommitteeCol = function(index) {
    if (committeeCols.length <= 1) return;
    
    // Preserve data but skip the deleted column
    const newData = [];
    document.querySelectorAll('#committee_tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('input.committee-field').forEach((input, i) => {
            if (i !== index) row.push(input.value);
        });
        newData.push(row);
    });

    committeeCols.splice(index, 1);
    
    const tbody = document.getElementById('committee_tbody');
    tbody.innerHTML = '';
    committeeColsArr = committeeCols; // sync
    
    renderCommitteeTable(newData);
};

window.addCommitteeRow = function() {
    const tbody = document.getElementById('committee_tbody');
    const tr = document.createElement('tr');
    tr.className = 'committee-row';
    const rowIdx = tbody.children.length;
    
    let cellsHtml = `<td class="committee-num">${rowIdx + 1}</td>`;
    committeeCols.forEach((col, colIdx) => {
        cellsHtml += `<td><input type="text" name="committee_cells[]" class="committee-field" placeholder="..."></td>`;
    });
    cellsHtml += `<td class="committee-del-cell"><button type="button" class="btn-del-row" onclick="removeCommitteeRow(this)">&times;</button></td>`;
    
    tr.innerHTML = cellsHtml;
    tbody.appendChild(tr);
};

window.removeCommitteeRow = function(btn) {
    btn.closest('tr').remove();
    renumberCommitteeRows();
};

function renumberCommitteeRows() {
    document.querySelectorAll('#committee_tbody .committee-num').forEach((el, i) => el.textContent = i + 1);
}

function renderCommitteeTable(forcedData = null) {
    const theadRow = document.getElementById('committee_thead_row');
    const tbody = document.getElementById('committee_tbody');
    
    // Collect current data to preserve it if not forced
    const currentData = forcedData || [];
    if (!forcedData) {
        document.querySelectorAll('#committee_tbody tr').forEach(tr => {
            const row = [];
            tr.querySelectorAll('input.committee-field').forEach(input => row.push(input.value));
            currentData.push(row);
        });
    }

    // Render Headers
    let headHtml = '<th class="committee-num">ژ</th>';
    committeeCols.forEach((col, i) => {
        headHtml += `<th>
            <div style="display:flex; align-items:center; gap:5px; background: #fff; padding: 2px 5px; border-radius: 4px; border: 1px solid #e2e8f0;">
                <input type="text" name="committee_headers[]" class="header-input" value="${escapeHtml(col)}" oninput="updateHeader(${i}, this.value)" placeholder="ناونیشانی ستوون...">
                <span class="col-del-btn" onclick="removeCommitteeCol(${i})" title="سڕینەوەی ستوون">✕</span>
            </div>
        </th>`;
    });
    headHtml += '<th class="committee-del-cell"></th>';
    theadRow.innerHTML = headHtml;

    // Render Rows
    tbody.innerHTML = '';
    if (currentData.length === 0) {
        addCommitteeRow();
    } else {
        currentData.forEach((oldRow, rowIdx) => {
            const tr = document.createElement('tr');
            tr.className = 'committee-row';
            let cellsHtml = `<td class="committee-num">${rowIdx + 1}</td>`;
            committeeCols.forEach((_, colIdx) => {
                const val = oldRow[colIdx] || '';
                cellsHtml += `<td><input type="text" name="committee_cells[]" class="committee-field" value="${escapeHtml(val)}"></td>`;
            });
            cellsHtml += `<td class="committee-del-cell"><button type="button" class="btn-del-row" onclick="removeCommitteeRow(this)">&times;</button></td>`;
            tr.innerHTML = cellsHtml;
            tbody.appendChild(tr);
        });
    }
}

window.toggleSignBy = function(type) {
    const directorChk = document.getElementById('sign_by_director');
    const organizationChk = document.getElementById('sign_by_organization');
    if (!directorChk || !organizationChk) return;
    if (type === 'director') {
        if (directorChk.checked) {
            organizationChk.checked = false;
        } else {
            organizationChk.checked = true;
        }
    } else if (type === 'organization') {
        if (organizationChk.checked) {
            directorChk.checked = false;
        } else {
            directorChk.checked = true;
        }
    }
};

// Initial render will be handled in the main initialization block below
</script>

<div id="readyTemplatesOverlay" class="lib-overlay">
    <div class="lib-modal ready-templates-modal">
        <div class="lib-modal-header">
            <h3>بەڕێوەبردنی نوسراوە ئامادەکان</h3>
            <button class="lib-modal-close" id="closeReadyTemplatesBtn">&times;</button>
        </div>
        <div class="lib-modal-body">
            <div class="ready-search-row">
                <input type="text" id="readyTemplatesSearch" class="lib-field" placeholder="گەڕان بە ناوی تایتڵ...">
            </div>
            <div class="ready-templates-grid">
                <div>
                    <label style="font-weight:700; margin-bottom:8px; display:block;">لیستی نوسراوە ئامادەکان</label>
                    <div id="readyTemplatesList" class="ready-list-box"></div>
                </div>
                <div>
                    <label style="font-weight:700; margin-bottom:8px; display:block;">زیادکردنی نوسراوی نوێ</label>
                    <div class="lib-field" style="margin-bottom:10px;">
                        <input type="text" id="newReadyTitle" placeholder="تایتڵ (بۆ نموونە: پشتگیری)">
                    </div>
                    <div class="lib-field" style="margin-bottom:10px;">
                        <textarea id="newReadyContent" rows="7" placeholder="دەقی نوسراوی ئامادە..."></textarea>
                    </div>
                    <button type="button" id="saveReadyTemplateBtn" class="lib-btn lib-btn-primary">زیادکردن</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scanner Modal Structure -->
<div id="scannerModal">
    <div class="scanner-container">
        <div class="scanner-header">
            <strong>سکانکردنی فیزیایی (Epson / Physical Scanner)</strong>
            <button type="button" style="background:none; border:none; color:#fff; font-size:24px; cursor:pointer;" id="closeScannerBtn">&times;</button>
        </div>
        <div class="scanner-body">
            <div style="margin-bottom:20px; font-size:16px; color:#2d3748;">تکایە نوسراوەکە بخەرە ناو ئامێرەکەوە و کلیک لە "دەستپێکردنی سکان" بکە.</div>
            <div id="scannerStatus" style="color:#718096; font-size:14px; margin-bottom: 10px;">چاوەڕێی ئامێر...</div>
            
            <div id="setupLink" style="display:none; margin-top:15px; padding:10px; background:#fff5f5; border:1px solid #feb2b2; border-radius:6px;">
                <p style="font-size:12px; color:#c53030; margin-bottom:8px;">ئەگەر ئامێرەکە ناناسرێتەوە، پێویستە ئەم بەرنامەیە دابەزێنیت:</p>
                <a href="File/scan-setup.exe" class="lib-btn lib-btn-danger" style="text-decoration:none; display:inline-block; font-size:12px;">📥 دابەزاندنی بەرنامەی سکانەر</a>
            </div>
        </div>
        <div class="scanner-footer">
            <button type="button" class="lib-btn lib-btn-primary" id="captureBtn">▶️ دەستپێکردنی سکان</button>
        </div>
    </div>
</div>

<script src="https://cdn.asprise.com/scannerjs/scanner.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isEditMode = <?php echo $edit_id > 0 ? 'true' : 'false'; ?>;
    const initialCommitteeData = <?php echo ($edit_doc && !empty($edit_doc['committee_data'])) ? $edit_doc['committee_data'] : 'null'; ?>;
    
    // Initialize Committee Table
    if (isEditMode && initialCommitteeData) {
        committeeCols = initialCommitteeData.headers || ['ناوی سیانی', 'ئەرک / لیژنە'];
        const cells = initialCommitteeData.cells || [];
        const colCount = committeeCols.length;
        const rowData = [];
        for (let i = 0; i < cells.length; i += colCount) {
            rowData.push(cells.slice(i, i + colCount));
        }
        renderCommitteeTable(rowData);
    } else {
        renderCommitteeTable();
    }

    const select = document.getElementById('doc_type');
    const checkbox = document.getElementById('pin_doc_type');
    const docNumInput = document.getElementById('doc_num');
    const pinnedValue = localStorage.getItem('pinned_doc_type');

    // Custom Dropdown Logic
    const relTrigger = document.getElementById('relTrigger');
    const relMenu = document.getElementById('relMenu');
    const relSearch = document.getElementById('relSearch');
    const relList = document.getElementById('relList');
    const relInput = document.getElementById('relInput');
    const filterBtns = document.querySelectorAll('.filter-btn');
    let currentFilter = 'all';

    relTrigger.addEventListener('click', () => {
        relMenu.classList.toggle('show');
        if (relMenu.classList.contains('show')) relSearch.focus();
    });

    document.addEventListener('click', (e) => {
        if (!document.getElementById('relatedDocDropdown').contains(e.target)) {
            relMenu.classList.remove('show');
        }
    });

    function filterRelList() {
        const query = relSearch.value.toLowerCase();
        const items = relList.querySelectorAll('.lib-search-item');
        items.forEach(item => {
            const id = item.getAttribute('data-id');
            if (id === '') {
                item.style.display = (query === '' && currentFilter === 'all') ? 'block' : 'none';
                return;
            }
            const type = item.getAttribute('data-type');
            const num = (item.getAttribute('data-number') || '').toLowerCase();
            const sub = (item.getAttribute('data-subject') || '').toLowerCase();
            
            const matchQuery = num.includes(query) || sub.includes(query);
            const matchFilter = currentFilter === 'all' || type === currentFilter;
            
            item.style.display = (matchQuery && matchFilter) ? 'block' : 'none';
        });
    }

    relSearch.addEventListener('input', filterRelList);

    filterBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.getAttribute('data-filter');
            filterRelList();
        });
    });

    relList.addEventListener('click', (e) => {
        const item = e.target.closest('.lib-search-item');
        if (!item) return;
        const id = item.getAttribute('data-id');
        const text = item.textContent.trim();
        relInput.value = id;
        relTrigger.textContent = text;
        relMenu.classList.remove('show');
    });

    const docNumInputOrig = document.getElementById('doc_num');
    const contentInput = document.getElementById('doc_content');
    const readyOverlay = document.getElementById('readyTemplatesOverlay');
    const readyModal = readyOverlay.querySelector('.lib-modal');
    const readyList = document.getElementById('readyTemplatesList');
    const readyTemplatesSearch = document.getElementById('readyTemplatesSearch');
    const newReadyTitle = document.getElementById('newReadyTitle');
    const newReadyContent = document.getElementById('newReadyContent');
    const readyTemplatesKey = 'doc_ready_templates_v1';
    const quickAddKey = 'doc_content_quick_add_v1';
    const quickAddMax = 5;
    const quickAddListEl = document.getElementById('quickAddList');
    let editingReadyIndex = -1;
    let lastContentSelStart = 0;
    let lastContentSelEnd = 0;

    function loadNextDocNumber() {
        const type = encodeURIComponent(select.value || '');
        fetch('docs_create.php?next_doc_num=1&type=' + type)
            .then(r => r.json())
            .then(data => {
                if (data && typeof data.next !== 'undefined') {
                    docNumInput.value = data.next;
                }
            })
            .catch(() => {});
    }

    if (pinnedValue && !isEditMode) {
        select.value = pinnedValue;
        checkbox.checked = true;
    }
    
    if (!isEditMode) {
        loadNextDocNumber();
    }

    checkbox.addEventListener('change', function() {
        if (this.checked) {
            localStorage.setItem('pinned_doc_type', select.value);
        } else {
            localStorage.removeItem('pinned_doc_type');
        }
    });

    select.addEventListener('change', function() {
        if (checkbox.checked) {
            localStorage.setItem('pinned_doc_type', select.value);
        }
        if (!isEditMode) {
            loadNextDocNumber();
        }
        updateFormMode();
    });

    function updateFormMode() {
        const type = select.value;
        const isHatuu = (type === 'هاتوو' || type === 'Inbound');
        const wordSection = document.getElementById('word_content_section');
        const fileSection = document.getElementById('scanned_file_section');
        const formTitle = document.getElementById('form_title');
        const submitBtnText = document.getElementById('submitBtnText');
        const docForm = document.getElementById('docForm');

        if (isEditMode) {
            if (submitBtnText) submitBtnText.textContent = 'پاشەکەوتکردنی گۆڕانکارییەکان';
            docForm.action = 'docs_update_process.php';
            if (isHatuu) {
                if (wordSection) wordSection.style.display = 'none';
                if (fileSection) fileSection.style.display = 'block';
            } else {
                if (wordSection) wordSection.style.display = 'block';
                if (fileSection) fileSection.style.display = 'none';
            }
            return;
        }

        if (isHatuu) {
            if (wordSection) wordSection.style.display = 'none';
            if (fileSection) fileSection.style.display = 'block';
            if (formTitle) formTitle.textContent = 'نوسینگە - تۆمارکردنی نوسراوی هاتوو';
            if (submitBtnText) submitBtnText.textContent = 'پاشەکەوتکردنی نوسراوی هاتوو';
            docForm.action = 'docs_save_process.php'; 
            const contentInput = document.getElementById('doc_content');
            if (contentInput) contentInput.required = false;
        } else {
            if (wordSection) wordSection.style.display = 'block';
            if (fileSection) fileSection.style.display = 'none';
            if (formTitle) formTitle.textContent = 'نوسینگە - دروستکردنی تێمپلێتی وۆرد';
            if (submitBtnText) submitBtnText.textContent = 'دەقی نوسراوە (Word)';
            docForm.action = 'generate_docx.php';
        }
    }
    updateFormMode();

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getDefaultTemplates() {
        return [
            { title: 'لیژنەی نەسریە', content: 'بۆ بەڕێز،\nلیژنەی نەسریە...\nلەگەڵ ڕێزمان.' },
            { title: 'پشتگیری', content: 'بۆ بەڕێز،\nداوای پشتگیری دەکەین بۆ...\nسوپاس.' },
            { title: 'داواکاری', content: 'بۆ بەڕێز،\nداواکاری دەکرێت بۆ...\nتکایە هەنگاوە پێویستەکان بگرن.' }
        ];
    }

    function loadReadyTemplates() {
        try {
            const raw = localStorage.getItem(readyTemplatesKey);
            if (!raw) return getDefaultTemplates();
            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) return getDefaultTemplates();
            // Basic validation, now templates can have 'headers' and 'rows'
            return parsed.filter(t => t && t.title && (t.content || t.rows));
        } catch (e) {
            return getDefaultTemplates();
        }
    }

    function saveReadyTemplates(list) {
        localStorage.setItem(readyTemplatesKey, JSON.stringify(list));
    }

    let readyTemplates = loadReadyTemplates();
    saveReadyTemplates(readyTemplates);
    let quickAdds = [];

    function appendTemplateContent(text) {
        const nextText = String(text || '').trim();
        if (!nextText) return;
        let start = typeof contentInput.selectionStart === 'number' ? contentInput.selectionStart : contentInput.value.length;
        let end = typeof contentInput.selectionEnd === 'number' ? contentInput.selectionEnd : contentInput.value.length;

        // If textarea lost focus after clicking buttons, use the last known caret position.
        if (document.activeElement !== contentInput) {
            start = lastContentSelStart;
            end = lastContentSelEnd;
        }

        const value = contentInput.value || '';
        const before = value.slice(0, start);
        const after = value.slice(end);
        contentInput.value = before + nextText + after;

        const newCaret = before.length + nextText.length;
        contentInput.focus();
        contentInput.setSelectionRange(newCaret, newCaret);
        lastContentSelStart = newCaret;
        lastContentSelEnd = newCaret;
    }

    function cacheContentSelection() {
        lastContentSelStart = contentInput.selectionStart || 0;
        lastContentSelEnd = contentInput.selectionEnd || 0;
    }
    ['click', 'keyup', 'select', 'focus', 'input'].forEach(function(evt) {
        contentInput.addEventListener(evt, cacheContentSelection);
    });

    function removeTemplate(index) {
        if (index < 0 || index >= readyTemplates.length) return;
        readyTemplates.splice(index, 1);
        saveReadyTemplates(readyTemplates);
        renderReadyTemplates();
    }

    function startEditTemplate(index) {
        if (index < 0 || index >= readyTemplates.length) return;
        editingReadyIndex = index;
        newReadyTitle.value = readyTemplates[index].title || '';
        newReadyContent.value = readyTemplates[index].content || '';
        document.getElementById('saveReadyTemplateBtn').textContent = 'نوێکردنەوە';
        newReadyTitle.focus();
    }

    function resetEditState() {
        editingReadyIndex = -1;
        newReadyTitle.value = '';
        newReadyContent.value = '';
        document.getElementById('saveReadyTemplateBtn').textContent = 'زیادکردن';
    }

    function renderReadyTemplates() {
        const query = String(readyTemplatesSearch.value || '').trim().toLowerCase();
        const filtered = readyTemplates
            .map(function(item, index) { return { item: item, index: index }; })
            .filter(function(row) {
                if (!query) return true;
                return String(row.item.title || '').toLowerCase().includes(query);
            });

        if (!filtered.length) {
            readyList.innerHTML = '<div style="padding:14px; color:#777; text-align:center;">هیچ نوسراوێکی ئامادە نییە.</div>';
            return;
        }
        readyList.innerHTML = filtered.map(function(row) {
            const item = row.item;
            const index = row.index;
            return ''
                + '<div class="ready-item">'
                + '<div class="ready-item-title">' + escapeHtml(item.title) + '</div>'
                + '<div class="ready-item-preview">' + escapeHtml(item.content) + '</div>'
                + '<div class="ready-item-actions">'
                + '<button type="button" class="lib-btn lib-btn-primary js-use-ready" data-index="' + index + '" style="padding:6px 10px;">بەکاربهێنە</button>'
                + '<button type="button" class="lib-btn lib-btn-gold js-edit-ready" data-index="' + index + '" style="padding:6px 10px;">نوێکردنەوە</button>'
                + '<button type="button" class="lib-btn lib-btn-danger js-del-ready" data-index="' + index + '" style="padding:6px 10px;">سڕینەوە</button>'
                + '</div>'
                + '</div>';
        }).join('');
    }

    function openReadyTemplates() {
        renderReadyTemplates();
        readyOverlay.classList.add('show');
        readyModal.classList.add('show');
    }

    function closeReadyTemplates() {
        readyOverlay.classList.remove('show');
        readyModal.classList.remove('show');
        resetEditState();
    }

    document.getElementById('openReadyTemplatesBtn').addEventListener('click', openReadyTemplates);
    document.getElementById('closeReadyTemplatesBtn').addEventListener('click', closeReadyTemplates);
    
    document.getElementById('saveCurrentAsTemplateBtn').addEventListener('click', function() {
        const title = prompt('ناوی تێمپلێتەکە بنوسە:', document.querySelector('input[name="doc_subject"]').value);
        if (!title) return;
        
        const content = document.getElementById('doc_content').value;
        const headers = Array.from(document.querySelectorAll('input[name="committee_headers[]"]')).map(i => i.value);
        
        // Collect rows
        const rows = [];
        document.querySelectorAll('#committee_tbody tr').forEach(tr => {
            const row = [];
            tr.querySelectorAll('input.committee-field').forEach(input => row.push(input.value));
            // Only save non-empty rows
            if (row.some(c => c.trim() !== '')) rows.push(row);
        });

        readyTemplates.push({
            title: title,
            content: content,
            headers: headers,
            rows: rows
        });
        
        saveReadyTemplates(readyTemplates);
        alert('تێمپلێتەکە بە سەرکەوتوویی پاشەکەوت کرا.');
    });

    readyTemplatesSearch.addEventListener('input', renderReadyTemplates);
    readyOverlay.addEventListener('click', function(e) {
        if (e.target === readyOverlay) closeReadyTemplates();
    });

    readyList.addEventListener('click', function(e) {
        const useBtn = e.target.closest('.js-use-ready');
        const editBtn = e.target.closest('.js-edit-ready');
        const delBtn = e.target.closest('.js-del-ready');
        if (useBtn) {
            const i = parseInt(useBtn.getAttribute('data-index') || '-1', 10);
            const template = readyTemplates[i];
            if (i >= 0 && template) {
                // Set content
                if (template.content) {
                    document.getElementById('doc_content').value = template.content;
                }
                
                // Set Committee Table
                if (template.headers && template.headers.length > 0) {
                    committeeCols = template.headers;
                    const tableData = template.rows || [];
                    renderCommitteeTable(tableData);
                }
                
                closeReadyTemplates();
            }
            return;
        }
        if (editBtn) {
            const i = parseInt(editBtn.getAttribute('data-index') || '-1', 10);
            if (i >= 0 && readyTemplates[i]) {
                startEditTemplate(i);
            }
            return;
        }
        if (delBtn) {
            const i = parseInt(delBtn.getAttribute('data-index') || '-1', 10);
            if (i >= 0 && confirm('ئایا دڵنیایت لە سڕینەوەی ئەم نوسراوە ئامادەیە؟')) {
                removeTemplate(i);
            }
        }
    });

    document.getElementById('saveReadyTemplateBtn').addEventListener('click', function() {
        const title = (newReadyTitle.value || '').trim();
        const content = (newReadyContent.value || '').trim();
        if (!title || !content) {
            alert('تکایە تایتڵ و دەق هەردووکیان پڕ بکە.');
            return;
        }
        if (editingReadyIndex >= 0 && editingReadyIndex < readyTemplates.length) {
            readyTemplates[editingReadyIndex] = { title: title, content: content };
        } else {
            readyTemplates.unshift({ title: title, content: content });
        }
        saveReadyTemplates(readyTemplates);
        resetEditState();
        renderReadyTemplates();
    });

    function loadQuickAdds() {
        try {
            const raw = localStorage.getItem(quickAddKey);
            if (!raw) return [];
            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) return [];
            return parsed.filter(function(t) { return typeof t === 'string' && t.trim() !== ''; }).slice(0, quickAddMax);
        } catch (e) {
            return [];
        }
    }

    function saveQuickAdds() {
        localStorage.setItem(quickAddKey, JSON.stringify(quickAdds.slice(0, quickAddMax)));
    }

    function renderQuickAdds() {
        if (!quickAdds.length) {
            quickAddListEl.innerHTML = '<span style="font-size:12px; color:#777;">هیچ کویک ئەدێک نییە</span>';
            return;
        }
        quickAddListEl.innerHTML = quickAdds.map(function(text, index) {
            const short = text.length > 42 ? text.slice(0, 42) + '...' : text;
            return ''
                + '<button type="button" class="quick-add-chip js-quick-add-use" data-index="' + index + '" title="' + escapeHtml(text) + '">' + escapeHtml(short) + '</button>'
                + '<button type="button" class="quick-add-del js-quick-add-del" data-index="' + index + '" title="سڕینەوە">x</button>';
        }).join('');
    }

    function addSelectionToQuickAdd() {
        const start = contentInput.selectionStart || 0;
        const end = contentInput.selectionEnd || 0;
        const picked = String(contentInput.value || '').slice(start, end).trim();
        if (!picked) {
            alert('تکایە لە ناوەڕۆکدا دەقێک هەڵبژێرە، پاشان کویک ئەد بکە.');
            return;
        }
        quickAdds = [picked].concat(quickAdds.filter(function(t) { return t !== picked; }));
        if (quickAdds.length > quickAddMax) quickAdds = quickAdds.slice(0, quickAddMax);
        saveQuickAdds();
        renderQuickAdds();
    }

    document.getElementById('saveSelectionQuickAddBtn').addEventListener('click', addSelectionToQuickAdd);
    quickAddListEl.addEventListener('click', function(e) {
        const useBtn = e.target.closest('.js-quick-add-use');
        const delBtn = e.target.closest('.js-quick-add-del');
        if (useBtn) {
            const i = parseInt(useBtn.getAttribute('data-index') || '-1', 10);
            if (i >= 0 && quickAdds[i]) appendTemplateContent(quickAdds[i]);
            return;
        }
        if (delBtn) {
            const i = parseInt(delBtn.getAttribute('data-index') || '-1', 10);
            if (i >= 0) {
                quickAdds.splice(i, 1);
                saveQuickAdds();
                renderQuickAdds();
            }
        }
    });

    quickAdds = loadQuickAdds();
    renderQuickAdds();

    // Physical Scanner Logic (Scanner.js)
    const scannerModal = document.getElementById('scannerModal');
    const captureBtn = document.getElementById('captureBtn');
    const startScanBtn = document.getElementById('startScanBtn');
    const closeScannerBtn = document.getElementById('closeScannerBtn');
    const scannerStatus = document.getElementById('scannerStatus');
    const scanPreviewWrap = document.getElementById('scan_preview_wrap');
    const scanPreviewImg = document.getElementById('scan_preview_img');
    const scannedBase64 = document.getElementById('scanned_base64');
    const fileInput = document.getElementById('scanned_file_input');

    startScanBtn.addEventListener('click', () => {
        scannerModal.style.display = 'flex';
        scannerStatus.textContent = 'ئامادەیە بۆ سکانکردن لەڕێگەی ئامێرەوە.';
    });

    closeScannerBtn.addEventListener('click', () => {
        scannerModal.style.display = 'none';
    });

    captureBtn.addEventListener('click', () => {
        scannerStatus.textContent = 'پەیوەندی بە ئامێرەوە دەکرێت، تکایە چاوەڕوانبە...';
        
        // Scan with Scanner.js
        scanner.scan(displayImagesOnPage, {
            "output_settings": [
                {
                    "type": "return-base64",
                    "format": "jpg"
                }
            ]
        });
    });

    function displayImagesOnPage(successful, mesg, response) {
        if (!successful) {
            scannerStatus.textContent = 'هەڵە: ' + mesg;
            document.getElementById('setupLink').style.display = 'block';
            alert('کێشەیەک لە سکانەرەکەدا هەیە. دڵنیابەرەوە کە پرۆگرامی ScannerBridge کراوەیە و دامەزرێنراوە.');
            return;
        }

        const scannedImages = scanner.getScannedImages(response, true, false);
        if (scannedImages && scannedImages.length > 0) {
            const base64Data = scannedImages[0];
            const dataUrl = "data:image/jpeg;base64," + base64Data;
            
            scannedBase64.value = dataUrl;
            scanPreviewImg.src = dataUrl;
            scanPreviewWrap.style.display = 'block';
            fileInput.value = '';
            
            scannerStatus.textContent = 'سکانەکە بە سەرکەوتوویی وەرگیرا.';
            setTimeout(() => { scannerModal.style.display = 'none'; }, 1000);
        } else {
            scannerStatus.textContent = 'هیچ وێنەیەک وەرنەگیرا.';
        }
    }

    document.getElementById('removeScanBtn').addEventListener('click', () => {
        scannedBase64.value = '';
        scanPreviewImg.src = '';
        scanPreviewWrap.style.display = 'none';
    });

    // Helper to toggle sections based on doc type
    window.toggleSectionsByType = function(type) {
        const inboundKeywords = ['هاتوو', 'Inbound'];
        const isScanType = inboundKeywords.includes(type);
        
        document.getElementById('scanned_file_section').style.display = isScanType ? 'block' : 'none';
        document.getElementById('word_content_section').style.display = isScanType ? 'none' : 'block';
        
        const sigSection = document.getElementById('signature_type_section');
        if (sigSection) {
            sigSection.style.display = isScanType ? 'none' : 'block';
        }
    };

    // Initial trigger for Edit mode
    const initialType = document.getElementById('doc_type').value;
    if (initialType) toggleSectionsByType(initialType);
});
</script>

<!-- File Viewer Modal -->
<div id="fileViewerOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:9050;"></div>
<div id="fileViewerModal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:min(1000px, 95vw); max-height:90vh; background:#fff; border-radius:12px; overflow:hidden; z-index:9060; box-shadow:0 18px 50px rgba(0,0,0,0.3); font-family: 'Rabar005', 'Rabar022', sans-serif;">
    <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background:#003a4a; color:#fff;">
        <div><strong id="viewerTitle">پیشاندانی فایل</strong><div id="viewerSubTitle" style="font-size:12px; opacity:0.9;"></div></div>
        <button type="button" onclick="closeFileViewer()" style="border:none; background:transparent; color:#fff; font-size:28px; line-height:1; cursor:pointer;">&times;</button>
    </div>
    <div id="fileViewerBody" style="min-height:300px; max-height:calc(90vh - 120px); overflow:hidden;">
        <aside id="fileViewerPanel">
            <button type="button" id="viewerOpenSystemBtn" class="lib-btn lib-btn-gold" style="padding:7px 12px; width:100%;">کردنەوە بە سیستەم</button>
            <hr style="border:none; border-top:1px solid #e3e6ea; margin:12px 0;">
            <label for="viewerSearchInput" style="font-size:12px; color:#666;">گەڕان لە ناو دەق</label>
            <input id="viewerSearchInput" type="text" class="lib-field" placeholder="وشە بنوسە..." style="width:100%; margin:6px 0 8px;">
            <button type="button" id="viewerSearchBtn" class="lib-btn lib-btn-primary" style="padding:7px 12px; width:100%;">گەڕان</button>
            <div style="display:flex; gap:6px;">
                <button type="button" id="viewerSearchPrevBtn" class="lib-btn lib-btn-primary" style="padding:6px 10px; flex:1;">↑</button>
                <button type="button" id="viewerSearchNextBtn" class="lib-btn lib-btn-primary" style="padding:6px 10px; flex:1;">↓</button>
            </div>
            <div id="viewerSearchCount" style="margin-top:8px; font-size:12px; color:#666; text-align:center;">-</div>
        </aside>
        <section id="fileViewerMain"><div id="fileViewerContent"></div></section>
    </div>
</div>

<script src="assets/js/mammoth.browser.min.js"></script>
<script>
const galleryFiles = <?php echo json_encode($edit_doc ? [['path' => $edit_doc['file_path'], 'doc_number' => $edit_doc['doc_number'], 'subject' => $edit_doc['subject']]] : []); ?>;
let activeFileIndex = 0;
let activeObjectUrl = null;
let currentDocxHtml = '';
let currentSearchMatches = [];
let currentSearchIndex = -1;

function detectFileType(url) {
    const cleanUrl = String(url || '').split('?')[0].toLowerCase();
    if (/\.(png|jpe?g|gif|webp|bmp|svg)$/.test(cleanUrl)) return 'image';
    if (/\.pdf$/.test(cleanUrl)) return 'pdf';
    if (/\.docx$/.test(cleanUrl)) return 'docx';
    return 'other';
}

function toBase64Utf8(text) { return btoa(unescape(encodeURIComponent(text))); }

async function fetchFileBlob(item) {
    const encodedPath = encodeURIComponent(toBase64Utf8(item.path || ''));
    const response = await fetch('stream_doc.php?f=' + encodedPath);
    if (!response.ok) throw new Error('stream_failed');
    const payload = await response.json();
    if (!payload || !payload.data) throw new Error('empty_payload');
    const binary = atob(payload.data);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes.buffer;
}

function revokeActiveObjectUrl() { if (activeObjectUrl) { URL.revokeObjectURL(activeObjectUrl); activeObjectUrl = null; } }

function openFileViewer(index) {
    if (!galleryFiles[index]) return;
    activeFileIndex = index;
    document.getElementById('fileViewerOverlay').style.display = 'block';
    document.getElementById('fileViewerModal').style.display = 'block';
    renderFileInViewer(index);
}

function closeFileViewer() {
    document.getElementById('fileViewerOverlay').style.display = 'none';
    document.getElementById('fileViewerModal').style.display = 'none';
    revokeActiveObjectUrl();
}

async function renderFileInViewer(index) {
    const item = galleryFiles[index];
    if (!item) return;
    const contentEl = document.getElementById('fileViewerContent');
    contentEl.innerHTML = '<div style="padding:40px; text-align:center; color:#666;"><i class="fas fa-spinner fa-spin fa-2x"></i><br><br>چاوەڕوانبە، فایلەکە ئامادە دەکرێت...</div>';
    document.getElementById('viewerTitle').textContent = 'نوسراوی ژمارە ' + (item.doc_number || '-');
    document.getElementById('viewerSubTitle').textContent = item.subject || '';
    
    revokeActiveObjectUrl();
    const type = detectFileType(item.path);
    try {
        const buffer = await fetchFileBlob(item);
        if (type === 'image') {
            const blob = new Blob([buffer], {type: 'image/jpeg'});
            activeObjectUrl = URL.createObjectURL(blob);
            contentEl.innerHTML = '<div style="text-align:center; padding:20px;"><img src="'+activeObjectUrl+'" style="max-width:100%; box-shadow:0 10px 30px rgba(0,0,0,0.2); border-radius:8px;"></div>';
        } else if (type === 'pdf') {
            const blob = new Blob([buffer], {type: 'application/pdf'});
            activeObjectUrl = URL.createObjectURL(blob);
            contentEl.innerHTML = '<iframe src="'+activeObjectUrl+'" style="width:100%; height:calc(90vh - 120px); border:none;"></iframe>';
        } else if (type === 'docx') {
            const result = await mammoth.convertToHtml({arrayBuffer: buffer});
            currentDocxHtml = result.value;
            contentEl.innerHTML = '<div class="a4-stage"><article class="a4-page">' + currentDocxHtml + '</article></div>';
        } else {
            contentEl.innerHTML = '<div style="padding:60px; text-align:center;">ئەم جۆرە فایلە پشتگیری ناکرێت بۆ پیشاندان.<br><br><a href="view_file_raw.php?f='+encodeURIComponent(toBase64Utf8(item.path))+'" target="_blank" class="lib-btn lib-btn-primary">داگرتنی فایل</a></div>';
        }
    } catch (e) {
        contentEl.innerHTML = '<div style="padding:60px; text-align:center; color:#c00;">هەڵە لە بارکردنی فایل. تکایە دڵنیابەرەوە کە فایلەکە لە شوێنی خۆیەتی.</div>';
    }
}

document.getElementById('viewerOpenSystemBtn').addEventListener('click', function() {
    const item = galleryFiles[activeFileIndex];
    if (item) fetch('docs.php?open_local=' + item.id);
});

// Search Logic
function highlightMatches(term) {
    const root = document.querySelector('#fileViewerContent .a4-page');
    if (!root || !term) return 0;
    const regex = new RegExp(term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
    const nodes = [];
    while (walker.nextNode()) if (walker.currentNode.nodeValue.trim()) nodes.push(walker.currentNode);
    nodes.forEach(node => {
        const val = node.nodeValue;
        if (regex.test(val)) {
            const span = document.createElement('span');
            span.innerHTML = val.replace(regex, m => `<mark class="viewer-hit">${m}</mark>`);
            node.parentNode.replaceChild(span, node);
        }
    });
    currentSearchMatches = Array.from(document.querySelectorAll('.viewer-hit'));
    return currentSearchMatches.length;
}

document.getElementById('viewerSearchBtn').addEventListener('click', function() {
    const term = document.getElementById('viewerSearchInput').value.trim();
    const count = highlightMatches(term);
    document.getElementById('viewerSearchCount').textContent = count + ' دۆزرایەوە';
});

document.addEventListener('DOMContentLoaded', function() {
    // ... existing init logic if any ...
});
</script>

<?php require_once 'includes/layout_end.php'; ?>
