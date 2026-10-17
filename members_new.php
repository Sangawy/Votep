<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$families = [];
$res = $conn->query("SELECT id, family_name FROM families ORDER BY family_name ASC");
while ($row = $res->fetch_assoc()) $families[] = $row;

$is_edit = false;
$edit_id = (int)($_GET['id'] ?? 0);
$member = null;
$existing_docs = [];

if ($edit_id > 0) {
    $stmt = $conn->prepare("
        SELECT m.*, f.family_name,
               p1.full_name as p1_name,
               p2.full_name as p2_name,
               p3.full_name as p3_name
        FROM members m 
        LEFT JOIN families f ON m.family_id = f.id 
        LEFT JOIN members p1 ON m.parent_id = p1.id
        LEFT JOIN members p2 ON p1.parent_id = p2.id
        LEFT JOIN members p3 ON p2.parent_id = p3.id
        WHERE m.id = ?
    ");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($member) {
        $is_edit = true;
        $prefill_lineage = $member;

        // Fetch existing documents
        $dstmt = $conn->prepare("SELECT * FROM member_documents WHERE member_id = ? ORDER BY id ASC");
        $dstmt->bind_param("i", $edit_id);
        $dstmt->execute();
        $dres = $dstmt->get_result();
        while ($drow = $dres->fetch_assoc()) {
            $existing_docs[] = $drow;
        }
        $dstmt->close();
    }
}

// Handle Pre-linking from Lineage Tree (for new member)
if (!$is_edit) {
    $prefill_lineage = null;
    if (isset($_GET['link_lineage_id'])) {
        $lid = (int)$_GET['link_lineage_id'];
        $lstmt = $conn->prepare("
            SELECT m.*, f.family_name,
                   p1.full_name as p1_name,
                   p2.full_name as p2_name,
                   p3.full_name as p3_name
            FROM members m 
            LEFT JOIN families f ON m.family_id = f.id 
            LEFT JOIN members p1 ON m.parent_id = p1.id
            LEFT JOIN members p2 ON p1.parent_id = p2.id
            LEFT JOIN members p3 ON p2.parent_id = p3.id
            WHERE m.id = ?
        ");
        $lstmt->bind_param("i", $lid);
        $lstmt->execute();
        $prefill_lineage = $lstmt->get_result()->fetch_assoc();
        $lstmt->close();
    }
}

if ($is_edit) {
    $page_title = 'دەستکاریکردنی ئەندام: ' . ($member['full_name'] ?? '');
    $active_nav = 'members_list';
} else {
    $page_title = 'تۆمارکردنی ئەندامی نوێ';
    $active_nav = 'members_new';
}
$sys_module = 'nusinga';
require_once 'includes/layout.php';
?>

<!-- Dependencies: jQuery, Select2, SweetAlert2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .member-form-container { width: 100%; margin: 20px 0; background: #fff; padding: 40px; border-radius: 15px; box-shadow: 0 5px 25px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
    .form-header { text-align: center; background: linear-gradient(135deg, #1a5653 0%, #2a726e 100%); color: white; padding: 22px 20px; margin: -40px -40px 30px -40px; border-radius: 14px 14px 0 0; }
    .form-header h2 { margin: 0; font-size: 1.6rem; letter-spacing: 0.5px; }

    /* Add Family Button */
    .frc-add-btn {
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #86efac;
        padding: 4px 12px;
        border-radius: 6px;
        font-family: 'Rabar022', sans-serif;
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease;
    }
    .frc-add-btn:hover {
        background: #dcfce7;
        border-color: #22c55e;
        color: #14532d;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(34, 197, 94, 0.15);
    }

    @keyframes modalPopIn {
        from { opacity: 0; transform: scale(0.92); }
        to { opacity: 1; transform: scale(1); }
    }

    /* Custom Select2 Styling to match Executive Card */
    .select2-container {
        width: 100% !important;
        direction: rtl;
    }
    .select2-container--default .select2-selection--single {
        height: 48px;
        border: 1.5px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        display: flex;
        align-items: center;
        padding: 0 14px;
        box-sizing: border-box;
        transition: all 0.2s ease;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        font-family: 'Rabar022', sans-serif;
        font-size: 1rem;
        font-weight: 600;
        color: #1e293b;
        padding-right: 0;
        padding-left: 25px;
        line-height: normal;
        width: 100%;
        text-align: right;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 46px;
        left: 12px;
        right: auto;
        display: flex;
        align-items: center;
    }
    .select2-container--default.select2-container--open .select2-selection--single,
    .select2-container--default.select2-container--focus .select2-selection--single {
        border-color: #1a5653;
        box-shadow: 0 0 0 3px rgba(26, 86, 83, 0.12);
        outline: none;
    }
    .select2-dropdown {
        border: 1.5px solid #1a5653;
        border-radius: 10px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        direction: rtl;
        overflow: hidden;
        padding: 8px;
        z-index: 10000;
        background: #ffffff;
    }
    .select2-search--dropdown {
        padding: 6px;
    }
    .select2-search--dropdown .select2-search__field {
        border: 1.5px solid #cbd5e1;
        border-radius: 8px;
        padding: 9px 12px;
        font-family: 'Rabar022', sans-serif;
        font-size: 0.95rem;
        direction: rtl;
        outline: none;
        box-sizing: border-box;
        width: 100%;
    }
    .select2-search--dropdown .select2-search__field:focus {
        border-color: #1a5653;
        box-shadow: 0 0 0 2px rgba(26, 86, 83, 0.12);
    }
    .select2-results__option {
        padding: 10px 14px;
        border-radius: 7px;
        font-family: 'Rabar022', sans-serif;
        font-size: 0.95rem;
        font-weight: 600;
        margin-bottom: 2px;
        color: #1e293b;
        text-align: right;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #1a5653;
        color: #ffffff;
    }
    .select2-container--default .select2-results__option[aria-selected=true] {
        background-color: #f0fdf4;
        color: #15803d;
        font-weight: 700;
    }

    /* Modern Executive Family & Lineage Card */
    .family-requirement-card {
        background: #fbfcfd;
        border: 1px solid #e2e8f0;
        border-top: 4px solid #1a5653;
        border-radius: 14px;
        padding: 24px 28px;
        margin-bottom: 30px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
    }
    .frc-grid {
        display: grid;
        grid-template-columns: 1fr 1.15fr;
        gap: 28px;
        align-items: start;
    }
    .frc-col {
        display: flex;
        flex-direction: column;
        gap: 8px;
        position: relative;
    }
    .frc-label {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 4px;
    }
    .frc-title {
        font-weight: 700;
        font-size: 1.02rem;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 9px;
    }
    .frc-badge-req {
        background: #fee2e2;
        color: #b91c1c;
        font-size: 0.78rem;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 20px;
        border: 1px solid #fecaca;
    }
    .frc-badge-opt {
        background: #fef3c7;
        color: #92400e;
        font-size: 0.78rem;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 20px;
        border: 1px solid #fde68a;
    }
    .frc-select-wrapper, .frc-input-wrapper {
        position: relative;
        width: 100%;
    }
    .frc-select {
        width: 100% !important;
        height: 48px;
        padding: 10px 14px;
        border: 1.5px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        font-family: 'Rabar022', sans-serif;
        font-size: 1rem;
        font-weight: 600;
        color: #1e293b;
        outline: none;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }
    .frc-select:focus {
        border-color: #1a5653;
        box-shadow: 0 0 0 3px rgba(26, 86, 83, 0.12);
    }
    .frc-search-icon {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1rem;
        pointer-events: none;
        z-index: 2;
    }
    .frc-input {
        width: 100% !important;
        height: 48px;
        padding: 10px 42px 10px 14px !important;
        border: 1.5px solid #cbd5e1;
        border-radius: 10px;
        background: #ffffff;
        font-family: 'Rabar022', sans-serif;
        font-size: 0.98rem;
        color: #1e293b;
        outline: none;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }
    .frc-input:focus {
        border-color: #1a5653;
        box-shadow: 0 0 0 3px rgba(26, 86, 83, 0.12);
    }
    .frc-hint {
        font-size: 0.83rem;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 2px;
    }
    .frc-linked-alert {
        margin-top: 18px;
        padding: 12px 18px;
        background: #f0fdf4;
        border: 1.5px solid #86efac;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        box-shadow: 0 2px 8px rgba(34, 197, 94, 0.08);
    }
    .frc-linked-info {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.95rem;
        color: #15803d;
    }
    .frc-unlink-btn {
        background: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fca5a5;
        padding: 6px 14px;
        border-radius: 7px;
        font-family: 'Rabar022', sans-serif;
        font-size: 0.85rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .frc-unlink-btn:hover {
        background: #fecaca;
        color: #991b1b;
    }

    .form-wrapper { display: flex; gap: 30px; margin-bottom: 30px; }
    .photo-section { width: 190px; flex-shrink: 0; }
    .photo-upload-box { 
        width: 190px; 
        height: 205px; 
        border: 2px dashed #cbd5e1; 
        border-radius: 12px; 
        overflow: hidden; 
        background: #f8fafc; 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        justify-content: center; 
        cursor: pointer; 
        position: relative; 
        transition: all 0.2s; 
    }
    .photo-upload-box:hover { border-color: #1a5653; background: #f0fdfa; }
    .photo-upload-box img { width: 100%; height: 100%; object-fit: contain; background: #f8f9fa; display: none; }
    .fields-section { flex: 1; display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
    .form-group { display: flex; flex-direction: column; gap: 8px; }
    .form-group label { font-weight: bold; color: #2c3e50; font-size: 0.95rem; }
    .form-group input, .form-group select { padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-family: 'Rabar022', sans-serif; font-size: 1rem; width: 100%; outline: none; background: #fdfdfd; transition: border-color 0.3s; box-sizing: border-box; }
    .form-group input:focus, .form-group select:focus { border-color: #1a5653; background: #fff; }
    .name-inputs-grid { grid-column: span 4; display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; }
    .suggestion-box { display:none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: white; border: 1.5px solid #1a5653; border-radius: 9px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 1050; max-height: 280px; overflow-y: auto; }
    .suggestion-item { padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s; }
    .suggestion-item:hover { background: #f0fdfa; }
    .suggestion-item strong { color: #1e293b; display: block; margin-bottom: 2px; }
    .suggestion-item small { color: #1a5653; font-size: 0.8rem; }
    .btn-disabled { background-color: #bdc3c7 !important; cursor: not-allowed !important; pointer-events: none; }

    @media (max-width: 1200px) { .fields-section { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 900px) { 
        .frc-grid { grid-template-columns: 1fr; gap: 18px; }
        .fields-section { grid-template-columns: repeat(2, 1fr); } 
        .form-wrapper { flex-direction: column-reverse; } 
        .photo-section { margin: 0 auto; } 
    }
    @media (max-width: 600px) { .fields-section { grid-template-columns: 1fr; } .name-inputs-grid { grid-template-columns: 1fr 1fr; } }
</style>

<div class="member-form-container">
    <div class="form-header">
        <h2><?php echo $is_edit ? 'دەستکاریکردنی زانیاری ئەندامی فەرمی' : 'فۆڕمی تۆمارکردنی ئەندامی فەرمی'; ?></h2>
        <p style="margin: 6px 0 0 0; font-size: 0.92rem; opacity: 0.92;">
            <?php if ($is_edit): ?>
                دەستکاریکردنی زانیارییەکانی: <strong><?php echo htmlspecialchars($member['full_name']); ?></strong> (کۆد: #<?php echo htmlspecialchars($member['member_code'] ?: $member['id']); ?>)
            <?php else: ?>
                دیاریکردنی بنەماڵە مەرجی سەرەکییە، دەتوانیت ئەندامەکە ببەستیتەوە بە کەسی ناو نەسەب بۆ ڕێگری لە دووبارەبوونەوە
            <?php endif; ?>
        </p>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <div style="margin-bottom: 20px; padding: 15px; border-radius: 8px; background: <?php echo ($_SESSION['msg_type'] ?? '') === 'error' ? '#fee2e2' : '#dcfce7'; ?>; color: <?php echo ($_SESSION['msg_type'] ?? '') === 'error' ? '#dc2626' : '#166534'; ?>; font-weight: bold;">
            <?php echo $_SESSION['msg']; unset($_SESSION['msg'], $_SESSION['msg_type']); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo $is_edit ? 'members_update.php' : 'members_save.php'; ?>" method="POST" enctype="multipart/form-data" id="memberForm">
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
        <?php endif; ?>

        <!-- 1. Mandatory Family & Smart Lineage Linking Section (Top Executive Card) -->
        <div class="family-requirement-card">
            <div class="frc-grid">
                <!-- Family Dropdown -->
                <div class="frc-col">
                    <div class="frc-label">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="frc-title">
                                <i class="fas fa-landmark" style="color: #1a5653;"></i> بنەماڵەی ئەندام
                            </span>
                            <span class="frc-badge-req">مەرجی بنەڕەتی *</span>
                        </div>
                        <button type="button" class="frc-add-btn" id="openFamilyModalBtn" onclick="openAddFamilyModal(event)">
                            <i class="fas fa-plus-circle"></i> زیادکردنی بنەماڵە
                        </button>
                    </div>
                    <div class="frc-select-wrapper">
                        <select name="family_id" id="family_id" required class="frc-select" onchange="onFamilyChange()">
                            <option value="">-- تکایە سەرەتا بنەماڵە دەستنیشان بکە --</option>
                            <?php foreach ($families as $f): ?>
                                <?php 
                                $selected = ($prefill_lineage && $prefill_lineage['family_id'] == $f['id']) ? 'selected' : ''; 
                                ?>
                                <option value="<?php echo $f['id']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($f['family_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="frc-hint">
                        <i class="fas fa-info-circle" style="color: #1a5653;"></i> تۆمارکردنی هیچ ئەندامێک بەبێ دیاریکردنی بنەماڵە قبووڵ ناکرێت.
                    </div>
                </div>

                <!-- Smart Link Search from Lineage -->
                <div class="frc-col">
                    <label class="frc-label">
                        <span class="frc-title">
                            <i class="fas fa-sitemap" style="color: #d4af37;"></i> بەستنەوە بە کەسی ناو شەجەرە
                        </span>
                        <span class="frc-badge-opt">خۆکار / بێ دووبارەبوونەوە</span>
                    </label>
                    <div class="frc-input-wrapper">
                        <i class="fas fa-search frc-search-icon"></i>
                        <input type="text" id="lineage_search_input" class="frc-input" placeholder="گەڕان بەدوای ناوی کەسەکە لە شەجەرەی ئەم بنەماڵەیەدا..." autocomplete="off">
                        <div id="lineage_suggestions" class="suggestion-box"></div>
                    </div>
                    <div class="frc-hint">
                        <i class="fas fa-magic" style="color: #d4af37;"></i> ئەگەر لە شەجەرەدا هەیە، هەڵبژاردنی خۆکارانە زانیارییەکان دەبەستێتەوە و پڕدەکاتەوە.
                    </div>
                </div>
            </div>

            <!-- Linked Status Display -->
            <div id="lineage_linked_alert" class="frc-linked-alert" style="<?php echo ($prefill_lineage && (!empty($prefill_lineage['parent_id']) || !empty($_GET['link_lineage_id']) || !empty($prefill_lineage['p1_name']))) ? 'display: flex;' : 'display: none;'; ?>">
                <div class="frc-linked-info">
                    <i class="fas fa-check-circle" style="color: #16a34a; font-size: 1.3rem;"></i>
                    <span>بە سەرکەوتوویی بەستراوەتەوە بە ناو نەسەب: <strong id="linked_person_text" style="color: #15803d;"><?php 
                        if ($is_edit && !empty($prefill_lineage['p1_name'])) {
                            echo htmlspecialchars($prefill_lineage['p1_name'] . ' (باوک)');
                        } elseif ($prefill_lineage) {
                            echo htmlspecialchars($prefill_lineage['full_name']);
                        }
                    ?></strong> <span style="color: #64748b; font-size: 0.85rem;">(کۆدی نەسەب: #<?php echo $is_edit ? ($prefill_lineage['parent_id'] ?? '') : ($prefill_lineage['id'] ?? ''); ?>)</span></span>
                </div>
                <button type="button" onclick="unlinkLineagePerson()" class="frc-unlink-btn">
                    <i class="fas fa-times"></i> هەڵوەشاندنەوەی بەستنەوە
                </button>
            </div>
            <input type="hidden" name="link_lineage_id" id="link_lineage_id" value="<?php echo (!$is_edit && $prefill_lineage) ? $prefill_lineage['id'] : ''; ?>">
        </div>

        <div class="form-wrapper">
            <div class="fields-section">
                <!-- 2. 4-Level Name Section -->
                <div class="name-inputs-grid">
                    <?php
                    $p_fn = trim($prefill_lineage['first_name'] ?? '');
                    $p_fa = trim($prefill_lineage['father_name'] ?? '');
                    $p_g  = trim($prefill_lineage['grandfather_name'] ?? '');
                    $p_gg = trim($prefill_lineage['great_grandfather_name'] ?? '');
                    $p_full = $prefill_lineage['full_name'] ?? '';
                    $p_parent_id = $prefill_lineage['parent_id'] ?? '';

                    $p_fn = extractFirstName($p_fn ?: $p_full);

                    if (!empty($p_parent_id)) {
                        if (!empty($prefill_lineage['p1_name'])) $p_fa = extractFirstName($prefill_lineage['p1_name']);
                        if (!empty($prefill_lineage['p2_name'])) $p_g  = extractFirstName($prefill_lineage['p2_name']);
                        if (!empty($prefill_lineage['p3_name'])) $p_gg = extractFirstName($prefill_lineage['p3_name']);
                    }

                    if (empty($p_fa) || empty($p_g)) {
                        $fullNameForSplit = str_replace(['السيد', 'السید', 'سید', 'ملا', 'مەلا', 'شێخ', 'شیخ'], '', $p_full);
                        $parts = preg_split('/\s+/', trim($fullNameForSplit), -1, PREG_SPLIT_NO_EMPTY);
                        $p_fn = empty($p_fn) ? ($parts[0] ?? '') : $p_fn;
                        $p_fa = empty($p_fa) ? ($parts[1] ?? '') : $p_fa;
                        $p_g  = empty($p_g)  ? ($parts[2] ?? '') : $p_g;
                        $p_gg = empty($p_gg) ? ($parts[3] ?? '') : $p_gg;
                    }
                    ?>
                    <div class="form-group">
                        <label>ناوی یەکەم <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="first_name" id="first_name" required placeholder="سامان" value="<?php echo htmlspecialchars($p_fn); ?>">
                    </div>
                    <div class="form-group" style="position: relative;">
                        <label>ناوی باوک (باوان لە نەسەبدا) <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="father_name" id="father_name" required placeholder="بۆ نموونە: علاءالدین" autocomplete="off" value="<?php echo htmlspecialchars($p_fa); ?>">
                        <div id="parent_suggestions" class="suggestion-box"></div>
                    </div>
                    <div class="form-group">
                        <label>ناوی باپیر <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="grandfather_name" id="grandfather_name" required value="<?php echo htmlspecialchars($p_g); ?>">
                    </div>
                    <div class="form-group">
                        <label>باپیری گەورە</label>
                        <input type="text" name="great_grandfather_name" id="great_grandfather_name" value="<?php echo htmlspecialchars($p_gg); ?>">
                    </div>
                    <!-- Hidden Full Name & Parent ID container -->
                    <div style="display: none;">
                        <input type="hidden" name="full_name" id="full_name_input" value="<?php echo htmlspecialchars($p_full); ?>">
                        <input type="hidden" name="parent_id" id="parent_id_hidden" value="<?php echo htmlspecialchars($p_parent_id); ?>">
                        <div id="link_status"></div>
                    </div>
                    <div style="grid-column: span 4;">
                        <div id="name_warn"></div>
                    </div>
                </div>

                <!-- 3. Administrative Information -->
                <div class="form-group"><label>نازناو</label><input type="text" name="nickname" value="<?php echo htmlspecialchars($prefill_lineage['nickname'] ?? ''); ?>"></div>
                <div class="form-group"><label>کۆدی ئەندام</label><input type="text" name="member_code" placeholder="ئۆتۆماتیک ئەگەر بەتاڵ بێت" value="<?php echo htmlspecialchars($prefill_lineage['member_code'] ?? ''); ?>"></div>
                <div class="form-group">
                    <label>پلەی ئەندام</label>
                    <select name="member_rank">
                        <?php 
                        $ranks = ["ئەندام", "سەرۆکی دەستەی کارگیری", "دەستەی دامەزرێنەر", "دەستەی کارگێڕی (هەمیشەیی)", "دەستەی کارگیڕی (یەدەگ)", "ئەنجومەنی ڕاوێژکاری", "ئەندامی کارا"];
                        $cur_rank = $prefill_lineage['member_rank'] ?? 'ئەندام';
                        foreach ($ranks as $r): 
                        ?>
                            <option value="<?php echo $r; ?>" <?php echo ($cur_rank === $r) ? 'selected' : ''; ?>><?php echo $r; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>پایە</label><input type="text" name="member_post" placeholder="بۆ نموونە: بەرپرسی لیژنە" value="<?php echo htmlspecialchars($prefill_lineage['member_post'] ?? ''); ?>"></div>
                <div class="form-group"><label>ناونیشان</label><input type="text" name="address" value="<?php echo htmlspecialchars($prefill_lineage['address'] ?? ''); ?>"></div>
                <div class="form-group"><label>ئاستی خوێندن</label><input type="text" name="education" value="<?php echo htmlspecialchars($prefill_lineage['education'] ?? ''); ?>"></div>
                <div class="form-group"><label>زمان</label><input type="text" name="languages" value="<?php echo htmlspecialchars($prefill_lineage['languages'] ?? ''); ?>"></div>
                <div class="form-group">
                    <label>گرووپی خوێن</label>
                    <select name="blood_group">
                        <option value="">-- هەڵبژێرە --</option>
                        <?php 
                        $b_groups = ["A+","A-","B+","B-","O+","O-","AB+","AB-"];
                        $cur_bg = $prefill_lineage['blood_group'] ?? '';
                        foreach ($b_groups as $bg): 
                        ?>
                            <option value="<?php echo $bg; ?>" <?php echo ($cur_bg === $bg) ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label>پیشە</label><input type="text" name="profession" value="<?php echo htmlspecialchars($prefill_lineage['profession'] ?? ''); ?>"></div>
                <div class="form-group"><label>بەرواری بە ئەندامبوون</label><input type="date" name="membership_date" value="<?php echo htmlspecialchars($prefill_lineage['membership_date'] ?? date('Y-m-d')); ?>"></div>
                <div class="form-group"><label>ژمارەی مۆبایل</label><input type="text" name="phone" value="<?php echo htmlspecialchars($prefill_lineage['phone'] ?? ''); ?>"></div>
                <div class="form-group"><label>کۆدی ئاسایش</label><input type="text" name="security_code" value="<?php echo htmlspecialchars($prefill_lineage['security_code'] ?? ''); ?>"></div>
                <div class="form-group"><label>ژمارەی ناسنامە</label><input type="text" name="id_card_number" value="<?php echo htmlspecialchars($prefill_lineage['id_card_number'] ?? ''); ?>"></div>
                <div class="form-group"><label>شوێنی لەدایکبوون</label><input type="text" name="birth_place" value="<?php echo htmlspecialchars($prefill_lineage['birth_place'] ?? ''); ?>"></div>
                <div class="form-group"><label>بەرواری لەدایکبوون</label><input type="date" name="birth_date" value="<?php echo htmlspecialchars($prefill_lineage['birth_date'] ?? ''); ?>"></div>
            </div>

            <div class="photo-section">
                <div class="photo-upload-box" onclick="document.getElementById('profile_image').click()" title="کلیک بکە بۆ بارکردنی وێنە">
                    <?php 
                    $curr_img = !empty($prefill_lineage['profile_image']) ? $prefill_lineage['profile_image'] : '';
                    ?>
                    <img id="preview" src="<?php echo htmlspecialchars($curr_img ?: 'assets/image/user.png'); ?>" alt="Preview" style="<?php echo !empty($curr_img) ? 'display:block;' : 'display:none;'; ?>">
                    <div id="upload-placeholder" style="<?php echo !empty($curr_img) ? 'display:none;' : 'display:flex; flex-direction:column; align-items:center; gap:8px; color:#64748b; font-weight:600; font-size:0.9rem; text-align:center; padding:15px;'; ?>">
                        <i class="fas fa-camera" style="font-size: 2.2rem; color: #cbd5e1;"></i>
                        <span style="font-size: 0.95rem; color: #475569;">وێنەی ئەندام</span>
                        <small style="font-size: 0.75rem; color: #94a3b8;">کلیک بکە بۆ بارکردن</small>
                    </div>
                    <input type="file" name="profile_image" id="profile_image" hidden accept="image/*" onchange="previewFile()">
                </div>
            </div>
        </div>

        <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: center;">
            <?php if ($is_edit): ?>
                <button type="submit" id="submitBtn" class="lib-btn lib-btn-primary" style="padding: 12px 60px;">💾 پاشەکەوتکردنی دەستکارییەکان</button>
                <a href="members_list.php" class="lib-btn lib-btn-danger" style="padding: 12px 30px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">پاشگەزبوونەوە</a>
            <?php else: ?>
                <button type="submit" id="submitBtn" class="lib-btn lib-btn-primary" style="padding: 12px 60px;">💾 تۆمارکردنی ئەندام لە ئیدارە</button>
                <button type="reset" class="lib-btn lib-btn-danger" style="padding: 12px 30px;" onclick="unlinkLineagePerson()">پاککردنەوە</button>
            <?php endif; ?>
        </div>

        <!-- Documents Section -->
        <div class="main-grid" style="margin-top: 30px;">
            <div style="grid-column: span 12; background: #fdfdfd; padding: 25px; border-radius: 10px; border: 1px solid #e2e8f0;">
                <h4 style="color: #2c3e50; border-bottom: 2px solid #34495e; padding-bottom: 10px; margin-bottom: 20px;">📁 بەڵگەنامە و وێنە پەیوەندیدارەکان</h4>

                <?php if ($is_edit && !empty($existing_docs)): ?>
                    <div style="margin-bottom: 25px;">
                        <h5 style="color: #1e293b; margin-bottom: 12px; font-weight: 700;">بەڵگەنامە پاشەکەوتکراوەکان:</h5>
                        <table class="lib-table" style="width: 100%; margin-bottom: 20px;">
                            <thead>
                                <tr>
                                    <th style="text-align: center;">جۆری بەڵگەنامە</th>
                                    <th style="text-align: center;">پەڕگە</th>
                                    <th style="text-align: center;">سڕینەوە</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($existing_docs as $edoc): ?>
                                    <tr>
                                        <td style="text-align: center; font-weight: 600;"><?php echo htmlspecialchars($edoc['doc_type']); ?></td>
                                        <td style="text-align: center;">
                                            <a href="<?php echo htmlspecialchars($edoc['doc_path']); ?>" target="_blank" class="lib-btn lib-btn-info" style="padding: 4px 12px; font-size: 0.85rem; text-decoration: none;">
                                                <i class="fas fa-eye"></i> بینین
                                            </a>
                                        </td>
                                        <td style="text-align: center;">
                                            <label style="color: #ef4444; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                                <input type="checkbox" name="delete_docs[]" value="<?php echo $edoc['id']; ?>">
                                                سڕینەوە
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 10px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">جۆری بەڵگەنامە:</label>
                        <input type="text" id="doc_type_new" list="doc_types_list" placeholder="جۆر هەڵبژێرە..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <datalist id="doc_types_list"><option value="ناسنامە"><option value="کارتی نیشتمانی"><option value="وێنەی کەسی"><option value="کارتی زانیاری"></datalist>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">هەڵبژاردنی فایل:</label>
                        <input type="file" id="doc_file_new" accept="image/*" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                </div>
                <button type="button" onclick="addDocument()" class="lib-btn lib-btn-gold">➕ زیادکردن بۆ لیست</button>
                <table class="lib-table" style="width: 100%; margin-top: 15px;">
                    <thead><tr><th>جۆری بەڵگەنامە</th><th>ناوی فایل</th><th>کردار</th></tr></thead>
                    <tbody id="docs_list_body"></tbody>
                </table>
                <div id="docs_hidden_inputs"></div>
            </div>
        </div>
    </form>
</div>

<!-- Modal: Add New Family (Custom Modal) -->
<div id="addFamilyModal" style="display: none; position: fixed; inset: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 999999; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box;">
    <div style="background: #ffffff; border-radius: 16px; width: 500px; max-width: 95%; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); border: 1px solid #e2e8f0; animation: modalPopIn 0.2s ease-out; position: relative;">
        <!-- Modal Header -->
        <div style="background: linear-gradient(135deg, #1a5653 0%, #2a726e 100%); color: #ffffff; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-family: 'Rabar022', sans-serif; font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-landmark" style="color: #fde047;"></i> زیادکردنی بنەماڵەی نوێ
            </div>
            <button type="button" onclick="closeAddFamilyModal()" style="background: rgba(255,255,255,0.15); border: none; color: #ffffff; width: 32px; height: 32px; border-radius: 8px; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'" title="داخستن">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <!-- Modal Form -->
        <form id="addFamilyForm" onsubmit="submitNewFamily(event)" style="padding: 24px;">
            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 700; margin-bottom: 6px; color: #1e293b; font-size: 0.95rem;">
                    ناوی بنەماڵە <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" id="modal_family_name" required placeholder="بۆ نموونە: ساداتی پیرخدری" 
                       style="width: 100%; height: 46px; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-family: 'Rabar022', sans-serif; font-size: 1rem; outline: none; box-sizing: border-box; transition: border-color 0.2s;"
                       onfocus="this.style.borderColor='#1a5653'" onblur="this.style.borderColor='#cbd5e1'">
            </div>

            <div style="margin-bottom: 16px;">
                <label style="display: block; font-weight: 700; margin-bottom: 6px; color: #1e293b; font-size: 0.95rem;">
                    ناوچە / شوێنی بنەڕەتی (ئارەزوومەندانە)
                </label>
                <input type="text" id="modal_origin_area" placeholder="بۆ نموونە: سلێمانی - سەنگاو" 
                       style="width: 100%; height: 46px; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-family: 'Rabar022', sans-serif; font-size: 1rem; outline: none; box-sizing: border-box; transition: border-color 0.2s;"
                       onfocus="this.style.borderColor='#1a5653'" onblur="this.style.borderColor='#cbd5e1'">
            </div>

            <div style="margin-bottom: 22px;">
                <label style="display: block; font-weight: 700; margin-bottom: 6px; color: #1e293b; font-size: 0.95rem;">
                    باوانی گەورەی بنەماڵە (ئارەزوومەندانە)
                </label>
                <input type="text" id="modal_ancestor_name" placeholder="بۆ نموونە: سەید پیرخدر" 
                       style="width: 100%; height: 46px; padding: 10px 14px; border: 1.5px solid #cbd5e1; border-radius: 10px; font-family: 'Rabar022', sans-serif; font-size: 1rem; outline: none; box-sizing: border-box; transition: border-color 0.2s;"
                       onfocus="this.style.borderColor='#1a5653'" onblur="this.style.borderColor='#cbd5e1'">
            </div>

            <div style="display: flex; gap: 12px; margin-top: 10px;">
                <button type="submit" id="modal_save_btn" class="lib-btn lib-btn-primary" style="flex: 1.5; padding: 12px; font-weight: bold; font-size: 0.95rem; border-radius: 8px; background: #1a5653; color: #fff; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fas fa-save"></i> پاشەکەوتکردن و دەستنیشانکردن
                </button>
                <button type="button" onclick="closeAddFamilyModal()" class="lib-btn" style="flex: 1; padding: 12px; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer;">
                    پەشیمانبوونەوە
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const firstNameInput = document.getElementById('first_name');
const fatherNameInput = document.getElementById('father_name');
const grandfatherNameInput = document.getElementById('grandfather_name');
const greatGrandfatherNameInput = document.getElementById('great_grandfather_name');
const fullNameInput = document.getElementById('full_name_input');
const familySelect = document.getElementById('family_id');
const suggestionBox = document.getElementById('parent_suggestions');
const submitBtn = document.getElementById('submitBtn');

const lineageSearchInput = document.getElementById('lineage_search_input');
const lineageSuggestions = document.getElementById('lineage_suggestions');
const lineageLinkedAlert = document.getElementById('lineage_linked_alert');
const linkedPersonText = document.getElementById('linked_person_text');
const linkLineageIdInput = document.getElementById('link_lineage_id');

// Kurdish matcher for Select2
function kurdishSelect2Matcher(params, data) {
    if ($.trim(params.term) === '') {
        return data;
    }
    if (typeof data.text === 'undefined') {
        return null;
    }
    
    function norm(s) {
        return s.replace(/[ێيى]/g, 'ی')
                .replace(/[ك]/g, 'ک')
                .replace(/[ةە]/g, 'ه')
                .replace(/[ڕ]/g, 'ر')
                .replace(/[ڵ]/g, 'ل')
                .replace(/[ۆ]/g, 'و')
                .replace(/[أإآ]/g, 'ا')
                .replace(/[\s\-_]/g, '')
                .toLowerCase();
    }
    
    const term = norm(params.term);
    const text = norm(data.text);
    
    if (text.indexOf(term) > -1) {
        return data;
    }
    return null;
}

$(document).ready(function() {
    $('#family_id').select2({
        dir: "rtl",
        placeholder: "-- تکایە سەرەتا بنەماڵە دەستنیشان بکە --",
        matcher: kurdishSelect2Matcher,
        width: '100%'
    }).on('change', function() {
        onFamilyChange();
    });
});

window.openAddFamilyModal = function(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    const modal = document.getElementById('addFamilyModal');
    if (!modal) {
        console.error('Modal addFamilyModal not found!');
        return;
    }
    const form = document.getElementById('addFamilyForm');
    if (form) form.reset();
    modal.style.display = 'flex';
    setTimeout(() => {
        const inp = document.getElementById('modal_family_name');
        if (inp) inp.focus();
    }, 150);
};

window.closeAddFamilyModal = function() {
    const modal = document.getElementById('addFamilyModal');
    if (modal) modal.style.display = 'none';
};

// Close modal on outside click and Escape key
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('addFamilyModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeAddFamilyModal();
            }
        });
    }
    const openBtn = document.getElementById('openFamilyModalBtn');
    if (openBtn) {
        openBtn.addEventListener('click', window.openAddFamilyModal);
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddFamilyModal();
    }
});

function submitNewFamily(e) {
    e.preventDefault();
    const name = document.getElementById('modal_family_name').value.trim();
    const area = document.getElementById('modal_origin_area').value.trim();
    const ancestor = document.getElementById('modal_ancestor_name').value.trim();
    const btn = document.getElementById('modal_save_btn');

    if (!name) {
        Swal.fire('ئاگاداری', 'تکایە ناوی بنەماڵە بنووسە', 'warning');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> چاوەڕوانبە...';

    const formData = new FormData();
    formData.append('family_name', name);
    formData.append('origin_area', area);
    formData.append('ancestor_name', ancestor);

    fetch('add_family_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> پاشەکەوتکردن و دەستنیشانکردن';

        if (data.success) {
            let $select = $('#family_id');
            if ($select.find("option[value='" + data.id + "']").length === 0) {
                const newOption = new Option(data.family_name, data.id, true, true);
                $select.append(newOption);
            }
            $select.val(data.id).trigger('change');

            closeAddFamilyModal();

            Swal.fire({
                icon: 'success',
                title: 'سەرکەوتوو بوو',
                text: data.message,
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-start'
            });

            setTimeout(() => {
                document.getElementById('lineage_search_input').focus();
            }, 300);
        } else {
            Swal.fire('هەڵە', data.message || 'هەڵەیەک ڕوویدا لە زیادکردنی بنەماڵە', 'error');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> پاشەکەوتکردن و دەستنیشانکردن';
        console.error(err);
        Swal.fire('هەڵە', 'کێشەیەک لە پەیوەندی بە سێرڤەرەوە هەیە', 'error');
    });
}

const isEditMode = <?php echo $is_edit ? 'true' : 'false'; ?>;

function updateFullName() {
    const f = firstNameInput.value.trim();
    const fa = fatherNameInput.value.trim();
    const g = grandfatherNameInput.value.trim();
    const gg = greatGrandfatherNameInput.value.trim();
    fullNameInput.value = [f, fa, g, gg].filter(Boolean).join(' ');
    
    // Check duplication unless this exact person is linked or in edit mode
    if (!isEditMode && !linkLineageIdInput.value) {
        checkDuplicate(fullNameInput.value);
    }
}
[firstNameInput, fatherNameInput, grandfatherNameInput, greatGrandfatherNameInput].forEach(el => el.addEventListener('input', updateFullName));

function onFamilyChange() {
    lineageSearchInput.value = '';
    lineageSuggestions.style.display = 'none';
}

// Lineage Search Autocomplete
let lineageSearchTimeout = null;
lineageSearchInput.addEventListener('input', function() {
    clearTimeout(lineageSearchTimeout);
    const q = this.value.trim();
    const famId = familySelect.value;
    
    if (q.length < 2) { 
        lineageSuggestions.style.display = 'none'; 
        return; 
    }
    
    lineageSearchTimeout = setTimeout(() => {
        let url = `search_lineage_members_ajax.php?q=${encodeURIComponent(q)}`;
        if (famId) url += `&family_id=${famId}`;
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                lineageSuggestions.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(p => {
                        const d = document.createElement('div');
                        d.className = 'suggestion-item';
                        const chain = p.lineage_chain || p.full_name;
                        const famBadge = p.family_name ? `<span style="background: #e0e7ff; color: #3730a3; padding: 2px 7px; border-radius: 4px; font-size: 0.75rem; margin-right: 5px;">${p.family_name}</span>` : '';
                        const officialBadge = (p.is_official_member == 1) 
                            ? `<span style="background: #fef3c7; color: #92400e; padding: 2px 7px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; margin-right: 4px;"><i class="fas fa-id-card"></i> ئەندامی فەرمی</span>` 
                            : `<span style="background: #ecfdf5; color: #047857; padding: 2px 7px; border-radius: 4px; font-size: 0.75rem;"><i class="fas fa-sitemap"></i> لە شەجەرەدا</span>`;
                        d.innerHTML = `
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="color: #0f172a; font-size: 0.95rem;">${chain}</strong>
                                <div>${officialBadge} ${famBadge}</div>
                            </div>
                            <small style="color: #64748b;">کۆدی نەسەب: #${p.member_code || p.id} | باوک: ${p.father_name || 'نادیار'} | باپیر: ${p.grandfather_name || 'نادیار'}</small>
                        `;
                        d.onclick = () => selectLineagePerson(p);
                        lineageSuggestions.appendChild(d);
                    });
                    lineageSuggestions.style.display = 'block';
                } else {
                    lineageSuggestions.innerHTML = '<div style="padding: 12px; color: #64748b; text-align: center; font-size: 0.85rem;">هیچ کەسێک بەم ناوە لە شەجەرەدا نەدۆزرایەوە. دەتوانیت وەک کەسی نوێ تۆماری بکەیت.</div>';
                    lineageSuggestions.style.display = 'block';
                }
            });
    }, 350);
});

function selectLineagePerson(p) {
    linkLineageIdInput.value = p.id;
    if (p.family_id) {
        $('#family_id').val(p.family_id).trigger('change');
    }
    
    firstNameInput.value = p.first_name || '';
    fatherNameInput.value = p.father_name || '';
    grandfatherNameInput.value = p.grandfather_name || '';
    greatGrandfatherNameInput.value = p.great_grandfather_name || '';
    fullNameInput.value = p.full_name;
    document.getElementById('parent_id_hidden').value = p.parent_id || 0;

    linkedPersonText.innerText = p.lineage_chain || p.full_name;
    lineageLinkedAlert.style.display = 'flex';
    lineageSuggestions.style.display = 'none';
    lineageSearchInput.value = '';

    // Update photo preview if person has a profile image
    if (p.profile_image) {
        const preview = document.getElementById('preview');
        const uploadPlaceholder = document.getElementById('upload-placeholder');
        preview.src = p.profile_image;
        preview.style.display = 'block';
        if (uploadPlaceholder) uploadPlaceholder.style.display = 'none';
    }

    const warnDiv = document.getElementById('name_warn');
    warnDiv.style.display = 'none';
    submitBtn.classList.remove('btn-disabled');
    submitBtn.disabled = false;
}

function unlinkLineagePerson() {
    linkLineageIdInput.value = '';
    document.getElementById('parent_id_hidden').value = 0;
    lineageLinkedAlert.style.display = 'none';
    linkedPersonText.innerText = '';
    if (!isEditMode) {
        const preview = document.getElementById('preview');
        const uploadPlaceholder = document.getElementById('upload-placeholder');
        preview.src = 'assets/image/user.png';
        preview.style.display = 'none';
        if (uploadPlaceholder) uploadPlaceholder.style.display = 'flex';
    }
    updateFullName();
}

function selectParent(p) {
    document.getElementById('parent_id_hidden').value = p.id;
    fatherNameInput.value = p.first_name || p.full_name || '';
    grandfatherNameInput.value = p.father_name || '';
    greatGrandfatherNameInput.value = p.grandfather_name || '';
    
    // Auto-select family if available
    if (p.family_id && !$('#family_id').val()) {
        $('#family_id').val(p.family_id).trigger('change');
    }
    
    updateFullName();
    suggestionBox.style.display = 'none';
    document.getElementById('link_status').innerHTML = `<div style="font-size: 0.85rem; color: #27ae60; margin-top: 5px; font-weight: bold;"><i class="fas fa-check-circle"></i> باوان دەستنیشانکرا: <strong>${p.lineage_chain || p.full_name}</strong></div>`;
}

function checkDuplicate(name) {
    if (isEditMode) return;
    if (name.length < 5 || linkLineageIdInput.value) return;
    fetch(`check_duplicate_name.php?name=${encodeURIComponent(name)}`)
        .then(res => res.json())
        .then(data => {
            const warnDiv = document.getElementById('name_warn');
            if (data.exists) {
                warnDiv.style.display = 'block';
                warnDiv.style.color = '#e74c3c';
                warnDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ئەم ناوە پێشتر وەک <strong>ئەندامی فەرمی</strong> تۆمارکراوە (${data.matched_name})! تکایە دووبارەی مەکەرەوە.`;
                submitBtn.classList.add('btn-disabled');
                submitBtn.disabled = true;
            } else if (data.exists_in_lineage && data.lineage_record) {
                warnDiv.style.display = 'block';
                warnDiv.style.color = '#2563eb';
                warnDiv.innerHTML = `
                    <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 10px 15px; border-radius: 6px; margin-top: 5px; display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="fas fa-info-circle"></i> ئەم کەسە پێشتر لە شەجەرەی بنەماڵەی (${data.lineage_record.family_name || 'سادات'}) هەیە. دەتەوێت بیبەستیتەوە بەو؟</span>
                        <button type="button" class="lib-btn lib-btn-primary" style="padding: 4px 12px; font-size: 0.8rem;" onclick='selectLineagePerson(${JSON.stringify(data.lineage_record)})'>🔗 بەستنەوە بە کەسی شەجەرە</button>
                    </div>
                `;
                submitBtn.classList.remove('btn-disabled');
                submitBtn.disabled = false;
            } else { 
                warnDiv.style.display = 'none'; 
                submitBtn.classList.remove('btn-disabled');
                submitBtn.disabled = false;
            }
        });
}

let searchTimeout = null;
fatherNameInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const q = this.value.trim();
    if (q.length < 2) { suggestionBox.style.display = 'none'; return; }
    searchTimeout = setTimeout(() => {
        fetch(`search_parents_ajax.php?name=${encodeURIComponent(q)}`)
            .then(res => res.json())
            .then(data => {
                suggestionBox.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(p => {
                        const d = document.createElement('div');
                        d.className = 'suggestion-item';
                        const chain = p.lineage_chain || `${p.full_name} ${p.father_name || ''} ${p.grandfather_name || ''}`.trim();
                        const famBadge = p.family_name ? `<span style="background: #e0e7ff; color: #3730a3; padding: 2px 7px; border-radius: 4px; font-size: 0.75rem; margin-right: 5px;">${p.family_name}</span>` : '';
                        d.innerHTML = `
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="color: #0f172a; font-size: 0.95rem;">${chain}</strong>
                                ${famBadge}
                            </div>
                            <small style="color: #64748b;">کۆد: #${p.member_code || p.id} | باوک: ${p.father_name || 'نادیار'} | باپیر: ${p.grandfather_name || 'نادیار'}</small>
                        `;
                        d.onclick = () => selectParent(p);
                        suggestionBox.appendChild(d);
                    });
                    suggestionBox.style.display = 'block';
                } else { suggestionBox.style.display = 'none'; }
            });
    }, 400);
});

let docIndex = 0;
function addDocument() {
    const typeS = document.getElementById('doc_type_new'), fileI = document.getElementById('doc_file_new'), tbody = document.getElementById('docs_list_body'), container = document.getElementById('docs_hidden_inputs');
    if (fileI.files.length === 0) { alert('فایل هەڵبژێرە'); return; }
    const file = fileI.files[0], type = typeS.value;
    const row = document.createElement('tr'); row.id = 'doc_row_' + docIndex;
    row.innerHTML = `<td style="text-align:center;">${type}</td><td style="text-align:center;">${file.name}</td><td style="text-align:center;"><button type="button" onclick="removeDoc(${docIndex})" class="lib-btn lib-btn-danger" style="padding:5px 10px;">لادان</button></td>`;
    tbody.appendChild(row);
    const newI = fileI.cloneNode(); newI.style.display = 'none'; newI.name = 'docs_files[' + docIndex + ']'; newI.id = 'docs_file_' + docIndex;
    const dt = new DataTransfer(); dt.items.add(file); newI.files = dt.files;
    container.appendChild(newI);
    const tI = document.createElement('input'); tI.type = 'hidden'; tI.name = 'docs_types[' + docIndex + ']'; tI.value = type; tI.id = 'docs_type_' + docIndex;
    container.appendChild(tI); docIndex++; fileI.value = '';
}
function removeDoc(idx) { document.getElementById('doc_row_' + idx).remove(); document.getElementById('docs_file_' + idx).remove(); document.getElementById('docs_type_' + idx).remove(); }
function previewFile() {
    const preview = document.getElementById('preview'), file = document.getElementById('profile_image').files[0], reader = new FileReader();
    reader.onloadend = () => { preview.src = reader.result; preview.style.display = "block"; document.getElementById('upload-placeholder').style.display = "none"; }
    if (file) reader.readAsDataURL(file);
}

// Form Validation on submit
document.getElementById('memberForm').addEventListener('submit', function(e) {
    if (!$('#family_id').val()) {
        e.preventDefault();
        Swal.fire({
            title: 'بنەماڵە دیاری نەکراوە!',
            text: 'مەرجی سەرەکی تۆمارکردنی ئەندام دیاریکردنی بنەماڵەیە. تکایە سەرەتا بنەماڵە هەڵبژێرە.',
            icon: 'warning',
            confirmButtonText: 'تێگەیشتم'
        }).then(() => {
            $('#family_id').select2('open');
        });
        return false;
    }
});

document.addEventListener('click', (e) => { 
    if (!fatherNameInput.contains(e.target) && !suggestionBox.contains(e.target)) suggestionBox.style.display = 'none'; 
    if (!lineageSearchInput.contains(e.target) && !lineageSuggestions.contains(e.target)) lineageSuggestions.style.display = 'none'; 
});
</script>

<?php require_once 'includes/layout_end.php'; ?>
