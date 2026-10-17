<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

// Ensure is_alive column exists (Safe Check)
$check_col = $conn->query("SHOW COLUMNS FROM members LIKE 'is_alive'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE members ADD COLUMN is_alive TINYINT(1) DEFAULT 1");
}

// Load FontAwesome
?>
<?php

// --- Handle Delete Action ---

if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    
    // Fetch image path before deleting
    $stmt = $conn->prepare("SELECT profile_image FROM members WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($row && !empty($row['profile_image']) && strpos($row['profile_image'], 'assets/') === false && file_exists($row['profile_image'])) {
        @unlink($row['profile_image']);
    }
    
    $stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['msg'] = "ئەندام بە سەرکەوتوویی سڕایەوە.";
    $_SESSION['msg_type'] = "success";
    header("Location: members_list.php");
    exit;
}

$families = [];
$res = $conn->query("SELECT id, family_name FROM families ORDER BY family_name ASC");
while ($row = $res->fetch_assoc()) $families[] = $row;

$ranks = ["ئەندام", "سەرۆکی دەستەی کارگیری", "دەستەی دامەزرێنەر", "دەستەی کارگێڕی (هەمیشەیی)", "دەستەی کارگیڕی (یەدەگ)", "ئەنجومەنی ڕاوێژکاری", "ئەندامی کارا", "ئەندامی کارگیڕی"];

$raw_search = $_GET['search'] ?? '';
// Trim whitespace, tabs, NBSP, zero-width spaces, and compress multiple spaces
$search = trim(preg_replace('/[\x{00a0}\x{200b}\s]+/u', ' ', $raw_search));

$filter_rank = trim($_GET['rank'] ?? '');
$filter_family = trim($_GET['family'] ?? '');
$filter_phone = trim($_GET['filter_phone'] ?? ''); // 'empty' or 'not_empty'
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

// Pagination settings
$items_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $items_per_page;

// تەنها ئەندامانی فەرمی یان خاوەن کۆد پیشان دەدرێن
$where = "WHERE (m.is_official_member = 1 OR (m.member_code IS NOT NULL AND m.member_code != ''))";
$params = [];
$types = "";

function normalizeForSearch($str) {
    $map = [
        'ێ' => 'ی', 'ي' => 'ی', 'ى' => 'ی', 'ئ' => 'ی',
        'ك' => 'ک', 'ة' => 'ه', 'ە' => 'ه', 'ڕ' => 'ر',
        'ڵ' => 'ل', 'ۆ' => 'و', 'أ' => 'ا', 'إ' => 'ا',
        'آ' => 'ا', 'ـ' => '', "\t" => '', "\r" => '', "\n" => '', ' ' => ''
    ];
    return str_replace(array_keys($map), array_values($map), $str);
}

// SQL-side normalization helper
function sqlNormalize($col) {
    $replacements = [
        'ێ' => 'ی', 'ي' => 'ی', 'ى' => 'ی', 'ئ' => 'ی',
        'ك' => 'ک', 'ة' => 'ه', 'ە' => 'ه', 'ڕ' => 'ر',
        'ڵ' => 'ل', 'ۆ' => 'و', 'أ' => 'ا', 'إ' => 'ا',
        'آ' => 'ا', 'ـ' => '', '\t' => '', ' ' => ''
    ];
    $sql = "CAST($col AS CHAR) COLLATE utf8mb4_unicode_ci";
    foreach ($replacements as $from => $to) {
        $sql = "REPLACE($sql, '$from', '$to')";
    }
    return $sql;
}

$clean_direct = str_replace(' ', '', $search);

if ($search !== '') {
    // 1. Remove common Kurdish/Arabic titles if in a phrase
    $title_pattern = '/\b(السید|سید|سەید|ملا|مەلا|مولا|شێخ|شیخ|السيد|سيد)\b/u';
    $search_filtered = trim(preg_replace($title_pattern, '', $search));
    if ($search_filtered === '') {
        $search_filtered = $search;
    }
    
    // 2. Split into words
    $words = explode(' ', $search_filtered);
    $words = array_filter($words, function($w) { 
        return mb_strlen(trim($w)) > 0; 
    });
    $words = array_values($words);

    if (empty($words)) {
        $words = [$search];
    }

    // 3. Search across all name components, parent names, family, address, etc.
    $search_columns = "CONCAT_WS(' ', m.full_name, m.first_name, m.father_name, m.grandfather_name, m.great_grandfather_name, m.nickname, p1.full_name, p2.full_name, p3.full_name, f.family_name, m.address, m.member_rank, m.member_post)";
    
    $word_clauses = [];
    $word_params = [];
    $word_types = "";
    
    foreach ($words as $word) {
        $clean_word = normalizeForSearch($word);
        if ($clean_word === '') continue;

        $word_clauses[] = sqlNormalize($search_columns) . " LIKE ?";
        $word_params[] = "%$clean_word%";
        $word_types .= "s";
    }

    if (!empty($word_clauses)) {
        $bag_clause = "(" . implode(" AND ", $word_clauses) . ")";
        
        // Also allow direct matches for numeric codes or phones (against cleaned search)
        $direct_clause = "(m.member_code LIKE ? OR REPLACE(m.phone, ' ', '') LIKE ? OR m.id_card_number LIKE ?)";
        $search_param = "%$clean_direct%";
        
        $where .= " AND ($bag_clause OR $direct_clause)";
        $params = array_merge($params, $word_params, [$search_param, $search_param, $search_param]);
        $types .= $word_types . "sss";
    }
}

if ($filter_rank) {
    $where .= " AND m.member_rank = ?";
    $params[] = $filter_rank;
    $types .= "s";
}
if ($filter_family) {
    $where .= " AND m.family_id = ?";
    $params[] = $filter_family;
    $types .= "i";
}

if ($filter_phone === 'empty') {
    $where .= " AND (m.phone IS NULL OR m.phone = '' OR m.phone = '-')";
} elseif ($filter_phone === 'not_empty') {
    $where .= " AND (m.phone IS NOT NULL AND m.phone != '' AND m.phone != '-')";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM members m 
                LEFT JOIN families f ON m.family_id = f.id 
                LEFT JOIN members p1 ON m.parent_id = p1.id
                LEFT JOIN members p2 ON p1.parent_id = p2.id
                LEFT JOIN members p3 ON p2.parent_id = p3.id
                $where";
$count_stmt = $conn->prepare($count_query);
if ($types) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result()->fetch_assoc();
$total_items = $total_result['total'];
$total_pages = ceil($total_items / $items_per_page);

// Ordering: if searching by pure code, boost exact match
$order_prefix = "";
if ($clean_direct !== '' && is_numeric($clean_direct)) {
    $order_prefix = "CASE WHEN m.member_code = " . (int)$clean_direct . " THEN 0 ELSE 1 END ASC, ";
}

// Main query with LIMIT
$query = "SELECT m.*, f.family_name, 
                 p1.full_name as p1_name, p1.parent_id as p1_parent,
                 p2.full_name as p2_name, p2.parent_id as p2_parent,
                 p3.full_name as p3_name, p3.parent_id as p3_parent
          FROM members m 
          LEFT JOIN families f ON m.family_id = f.id 
          LEFT JOIN members p1 ON m.parent_id = p1.id
          LEFT JOIN members p2 ON p1.parent_id = p2.id
          LEFT JOIN members p3 ON p2.parent_id = p3.id
          $where 
          ORDER BY $order_prefix CASE m.member_rank
              WHEN 'سەرۆکی دەستەی کارگیری' THEN 1
              WHEN 'دەستەی کارگێڕی (هەمیشەیی)' THEN 2
              WHEN 'دەستەی کارگیڕی (یەدەگ)' THEN 3
              WHEN 'ئەنجومەنی ڕاوێژکاری' THEN 4
              WHEN 'ئەندامی کارا' THEN 5
              WHEN 'ئەندامی کارگیڕی' THEN 6
              WHEN 'ئەندام' THEN 7
              ELSE 8
          END ASC, CAST(m.member_code AS UNSIGNED) ASC, m.full_name ASC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$limit_types = $types . "ii";
$limit_params = array_merge($params, [$items_per_page, $offset]);
$stmt->bind_param($limit_types, ...$limit_params);
$stmt->execute();
$members_list = $stmt->get_result();

$qs = "&search=" . urlencode($search) . "&rank=" . urlencode($filter_rank) . "&family=" . urlencode($filter_family);

function renderMembersTableAndPagination($members_list, $total_pages, $total_items, $offset, $items_per_page, $page, $qs, $filter_phone) {
?>
    <div class="lib-table-wrapper">
        <form id="bulkForm" method="POST">
            <input type="hidden" name="bulk_action" id="bulk_action_input" value="">
            <input type="hidden" name="election_id" id="bulk_election_id">
            <table class="lib-table">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="select_all" style="width: 18px; height: 18px;"></th>
                        <th style="width: 80px;">وێنە</th>
                        <th style="width: 120px;">کۆدی ئەندام</th>
                        <th class="align-right">ناوی تەواو</th>
                        <th>پلە / پایە</th>
                        <th>بنەماڵە</th>
                        <th style="width: 150px;">
                            مۆبایل
                            <a href="?<?php echo ltrim($qs, '&'); ?>&filter_phone=<?php echo $filter_phone === 'empty' ? '' : 'empty'; ?>" 
                               title="فلتەری بێ مۆبایل" 
                               style="margin-right: 5px; color: <?php echo $filter_phone === 'empty' ? '#ffc107' : '#fff'; ?>;">
                                <i class="fas fa-filter" style="font-size: 0.8rem;"></i>
                            </a>
                        </th>
                        <th style="width: 150px;">کردارەکان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($members_list->num_rows === 0): ?>
                        <tr><td colspan="8" style="padding: 50px;">هیچ ئەندامێک نەدۆزرایەوە</td></tr>
                    <?php else: ?>
                        <?php while($s = $members_list->fetch_assoc()): ?>
                            <tr data-id="<?php echo $s['id']; ?>" ondblclick="window.location.href='members_profile.php?id=<?php echo $s['id']; ?>'">
                                <td><input type="checkbox" name="member_ids[]" value="<?php echo $s['id']; ?>" class="member-checkbox" style="width: 18px; height: 18px;"></td>
                                <td>
                                <div style="width: 45px; height: 45px; border-radius: 50%; overflow: hidden; margin: 0 auto; border: 2px solid var(--gold); background: #eee;">
                                    <?php 
                                    $img_src = $s['profile_image'];
                                    if (empty($img_src)) $img_src = "assets/imgae/user.png";
                                    ?>
                                    <img src="stream_image.php?path=<?php echo urlencode($img_src); ?>" style="width: 100%; height: 100%; object-fit: contain; background: #fff;" onerror="this.src='assets/imgae/user.png'">
                                </div>
                            </td>
                            <td>
                                <span class="lib-badge lib-badge-primary">
                                    <?php echo htmlspecialchars(!empty($s['member_code']) ? $s['member_code'] : $s['id']); ?>
                                </span>
                            </td>
                            <td class="align-right" style="font-weight: 700;">
                                <?php echo htmlspecialchars(buildFourPartName($s)); ?>
                            </td>
                            <td style="font-size: 0.9rem; line-height: 1.4;">
                                <div style="font-weight: 600; color: var(--gold);"><?php echo htmlspecialchars($s['member_rank'] ?: '-'); ?></div>
                                <div style="color: #666;"><?php echo htmlspecialchars($s['member_post'] ?: '-'); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($s['family_name'] ?: '-'); ?></td>
                            <td><span style="direction: ltr; display: inline-block;"><?php echo htmlspecialchars($s['phone'] ?: '-'); ?></span></td>
                            <td>
                                <div class="lib-actions" style="display: flex; gap: 8px; justify-content: center; flex-direction: row;">
                                    <a href="members_profile.php?id=<?php echo $s['id']; ?>" class="lib-btn lib-btn-success" title="پڕۆفایل" style="padding: 5px 10px;">👁️</a>
                                    <a href="members_edit.php?id=<?php echo $s['id']; ?>" class="lib-btn lib-btn-warning" title="دەستکاری" style="padding: 5px 10px;">✏️</a>

                                    <button type="button" 
                                            onclick="event.stopPropagation(); confirmAction('members_list.php?delete=<?php echo $s['id']; ?><?php echo $qs; ?>', 'سڕینەوە!', 'ئایا بە ڕاستی دەتەوێت ئەم ئەندامە بەیەکجاری بسڕیتەوە؟', 'warning')" 
                                            class="lib-btn lib-btn-danger" title="سڕینەوە" style="padding: 5px 10px; cursor: pointer; border: none;">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </form>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                پیشاندانی <b><?php echo $offset + 1; ?></b> بۆ <b><?php echo min($offset + $items_per_page, $total_items); ?></b> لە کۆی <b><?php echo $total_items; ?></b> ئەندام
            </div>
            <div class="pagination-links">
                <!-- Previous Page -->
                <a href="?page=<?php echo $page - 1; ?><?php echo $qs; ?>" 
                   class="pagination-link <?php echo ($page <= 1) ? 'disabled' : ''; ?>" title="پێشتر">
                    <i class="fas fa-chevron-right"></i>
                </a>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);

                if ($start_page > 1) {
                    echo '<a href="?page=1'.$qs.'" class="pagination-link">1</a>';
                    if ($start_page > 2) echo '<span style="padding: 10px;">...</span>';
                }

                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo $qs; ?>" 
                       class="pagination-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor;

                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span style="padding: 10px;">...</span>';
                    echo '<a href="?page='.$total_pages.$qs.'" class="pagination-link">'.$total_pages.'</a>';
                }
                ?>

                <!-- Next Page -->
                <a href="?page=<?php echo $page + 1; ?><?php echo $qs; ?>" 
                   class="pagination-link <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>" title="دواتر">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>
<?php
}

if ($is_ajax) {
    renderMembersTableAndPagination($members_list, $total_pages, $total_items, $offset, $items_per_page, $page, $qs, $filter_phone);
    exit;
}

$page_title = 'لیستی ئەندامان';
$active_nav = 'members_list';
$sys_module = 'nusinga';
require_once 'includes/layout.php';
?>

<style>
    @keyframes flash-row {
        0% { background-color: transparent; }
        20% { background-color: rgba(200, 169, 81, 0.3); outline: 2px solid var(--gold); }
        100% { background-color: transparent; }
    }
    .row-updated {
        animation: flash-row 3s ease-out;
    }
    .custom-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #fff;
        color: #333;
        padding: 15px 25px;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        border-right: 6px solid var(--gold);
        display: flex;
        align-items: center;
        gap: 12px;
        z-index: 9999;
        transform: translateX(120%);
        transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        font-family: 'Rabar022', sans-serif;
        font-weight: bold;
    }
    .custom-toast.show { transform: translateX(0); }
    .lib-table th { text-align: center; vertical-align: middle; padding: 15px 10px; }
    .lib-table td { text-align: center; vertical-align: middle; padding: 12px 10px; }
    .lib-table .align-right { text-align: right; }
    
    /* Pagination Styles */
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #eee;
        font-family: 'Rabar022', sans-serif;
    }
    .pagination-info {
        color: #666;
        font-size: 0.95rem;
    }
    .pagination-links {
        display: flex;
        gap: 8px;
    }
    .pagination-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        height: 40px;
        padding: 0 10px;
        border-radius: 8px;
        background: #fff;
        border: 1px solid #ddd;
        color: #333;
        text-decoration: none;
        transition: all 0.3s ease;
        font-weight: bold;
    }
    .pagination-link:hover {
        background: #f5f5f5;
        border-color: var(--gold);
        color: var(--gold);
    }
    .pagination-link.active {
        background: var(--gold);
        border-color: var(--gold);
        color: #fff;
    }
    .pagination-link.disabled {
        opacity: 0.5;
        pointer-events: none;
        background: #f9f9f9;
    }
</style>

<style>
    .filter-bar {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 12px;
        display: flex;
        gap: 15px;
        align-items: center;
        margin-bottom: 25px;
        border: 1px solid #eee;
    }
    .search-input-group {
        flex: 2;
        display: flex;
        background: #fff;
        border: 1px solid #ced4da;
        border-radius: 8px;
        overflow: hidden;
    }
    .search-input-group input {
        flex: 1;
        border: none;
        padding: 10px 15px;
        outline: none;
    }
    .search-input-group i {
        padding: 12px;
        color: #adb5bd;
    }
    .filter-select {
        flex: 1;
        padding: 10px;
        border: 1px solid #ced4da;
        border-radius: 8px;
        background: #fff;
    }
</style>

<?php
// Build query string for preservation
$qs = "&search=" . urlencode($search) . "&rank=" . urlencode($filter_rank) . "&family=" . urlencode($filter_family);
?>

<!-- Include SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function confirmAction(url, title, text, icon = 'warning') {
    Swal.fire({
        title: title,
        text: text,
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: '#1a5653',
        cancelButtonColor: '#d33',
        confirmButtonText: 'بەڵێ، دڵنیام',
        cancelButtonText: 'پاشگەزبوونەوە',
        reverseButtons: true,
        customClass: {
            popup: 'swal-rtl'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
    return false;
}
</script>
<style>
    .swal-rtl {
        direction: rtl !important;
        font-family: 'Rabar022' !important;
    }
</style>

<div class="lib-card" style="padding: 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin: 0; font-size: 1.6rem;">
            <i class="fas fa-users" style="color: var(--gold); margin-left: 10px;"></i> بەڕێوەبردنی ئەندامان
        </h2>
        <div style="display: flex; gap: 10px;">
            <a href="members_reports.php" class="lib-btn" style="background: #f8f9fa; color: #333; border: 1px solid #ddd;"><i class="fas fa-print"></i> ڕاپۆرت</a>
            <a href="members_new.php" class="lib-btn lib-btn-primary"><i class="fas fa-plus"></i> ئەندامی نوێ</a>
        </div>
    </div>

    <!-- Redesigned Filter Bar -->
    <div class="filter-bar">
        <form method="GET" id="searchFilterForm" style="display: flex; flex: 1; gap: 12px; align-items: center;" onsubmit="return false;">
            <div class="search-input-group" style="position: relative;">
                <i class="fas fa-search"></i>
                <input type="text" name="search" id="memberSearchInput" value="<?php echo htmlspecialchars($search); ?>" 
                       autocomplete="off"
                       placeholder="گەڕان بەدوای ناو یان کۆد...">
                <span id="searchSpinner" style="display: none; position: absolute; left: 15px; top: 11px; color: var(--gold);">
                    <i class="fas fa-spinner fa-spin"></i>
                </span>
            </div>
            
            <select name="rank" id="memberRankSelect" class="filter-select">
                <option value="">هەموو پلەکان</option>
                <?php foreach ($ranks as $r): ?>
                    <option value="<?php echo htmlspecialchars($r); ?>" <?php echo $filter_rank === $r ? 'selected' : ''; ?>><?php echo htmlspecialchars($r); ?></option>
                <?php endforeach; ?>
            </select>

            <button type="button" id="searchSubmitBtn" class="lib-btn lib-btn-gold" style="padding: 10px 25px;">فلتەر</button>
            <a href="members_list.php" id="clearFilterBtn" class="lib-btn" style="background: #fff; color: #dc3545; border: 1px solid #ffc107; <?php echo ($search !== '' || $filter_rank !== '') ? '' : 'display:none;'; ?>">❌</a>
        </form>
    </div>

    <div style="padding: 15px 20px; background: #f0f4f7; border-bottom: 2px solid #ddd; display: flex; align-items: center; justify-content: space-between; gap: 15px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-weight: bold; color: #1a5653;">📦 کرداری بە کۆمەڵ:</span>
            
            <div style="display: flex; gap: 5px; border-left: 2px solid #ddd; padding-left: 10px; margin-left: 10px;">
                <select id="bulk_congress_id" style="padding: 8px; border: 1px solid #ccc; border-radius: 5px; font-family: 'Rabar022';">
                    <option value="">-- هەڵبژاردنی کۆنگرە --</option>
                    <?php
                    $e_res = $conn->query("SELECT id, title FROM elections ORDER BY election_date DESC");
                    while($e = $e_res->fetch_assoc()) echo "<option value='{$e['id']}'>".htmlspecialchars($e['title'])."</option>";
                    ?>
                </select>
                <button type="button" onclick="submitBulkAction('bulk_sync')" class="lib-btn lib-btn-primary" style="padding: 8px 20px;">🏛️ زیادکردن بۆ کۆنگرە</button>
            </div>

        </div>
        <div style="font-size: 0.9rem; color: #666;">
            <span id="selected_count">0</span> ئەندام هەڵبژێردراوە
        </div>
    </div>

    <script>
    function submitBulkAction(action) {
        const selected = document.querySelectorAll('.member-checkbox:checked');
        if (selected.length === 0) {
            Swal.fire('ئاگاداری', 'تکایە سەرەتا چەند ئەندامێک هەڵبژێرە', 'warning');
            return;
        }

        const form = document.getElementById('bulkForm');
        if (action === 'bulk_sync') {
            const congressId = document.getElementById('bulk_congress_id').value;
            if (!congressId) {
                Swal.fire('هەڵە', 'تکایە کۆنگرەیەک هەڵبژێرە', 'error');
                return;
            }
            form.action = 'congress_members_save.php';
            document.getElementById('bulk_action_input').value = 'bulk_sync';
            document.getElementById('bulk_election_id').value = congressId;
            form.submit();
        }
    }
    </script>

    <div id="membersContentArea">
        <?php renderMembersTableAndPagination($members_list, $total_pages, $total_items, $offset, $items_per_page, $page, $qs, $filter_phone); ?>
    </div>
</div>

<!-- Congress Management Modal -->
<div id="congressModal" class="lib-modal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px);">
    <div class="lib-modal-content" style="background: #fff; margin: 10% auto; padding: 0; border-radius: 15px; width: 450px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.3);">
        <div style="background: #1a5653; color: #fff; padding: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-family: 'Rabar013';">🏛️ دیاریکردنی ئەندامی کۆنگرە</h3>
            <span onclick="closeCongressModal()" style="cursor: pointer; font-size: 24px;">&times;</span>
        </div>
        <form action="congress_members_save.php" method="POST" style="padding: 25px;">
            <input type="hidden" name="action" value="sync_congresses">
            <input type="hidden" name="member_id" id="modal_member_id">
            
            <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px; border-right: 4px solid var(--gold);">
                <p style="margin: 0; color: #666; font-size: 0.9rem;">بۆ ئەندام:</p>
                <h4 id="modal_member_name" style="margin: 5px 0 0 0; color: #1a5653;"></h4>
            </div>

            <p style="font-weight: bold; margin-bottom: 15px; color: #333;">کۆنگرەکان هەڵبژێرە:</p>
            <div id="congress_list" style="max-height: 250px; overflow-y: auto; padding: 5px;">
                <?php
                $all_elections = $conn->query("SELECT * FROM elections ORDER BY election_date DESC");
                while ($e = $all_elections->fetch_assoc()):
                ?>
                <label style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #fff; border: 1px solid #eee; border-radius: 8px; margin-bottom: 8px; cursor: pointer; transition: all 0.2s;">
                    <input type="checkbox" name="congresses[]" value="<?php echo $e['id']; ?>" class="congress-checkbox-<?php echo $e['id']; ?>" style="width: 20px; height: 20px; accent-color: #1a5653;">
                    <div style="flex: 1;">
                        <div style="font-weight: bold;"><?php echo htmlspecialchars($e['title']); ?></div>
                        <div style="font-size: 0.8rem; color: #888;"><?php echo $e['election_date']; ?> - <?php echo htmlspecialchars($e['location']); ?></div>
                    </div>
                </label>
                <?php endwhile; ?>
            </div>

            <div style="margin-top: 25px; display: flex; gap: 10px;">
                <button type="submit" class="lib-btn lib-btn-primary" style="flex: 1; padding: 12px;">💾 نوێکردنەوە</button>
                <button type="button" onclick="closeCongressModal()" class="lib-btn" style="flex: 1; background: #eee;">پەشیمانبوونەوە</button>
            </div>
        </form>
    </div>
</div>

<script>
let searchDebounceTimer = null;
let currentSearchAbortController = null;

function bindMemberCheckboxes() {
    const selectAll = document.getElementById('select_all');
    const checkboxes = document.querySelectorAll('.member-checkbox');
    const selectedCount = document.getElementById('selected_count');

    function updateCount() {
        if (selectedCount) {
            const count = document.querySelectorAll('.member-checkbox:checked').length;
            selectedCount.innerText = count;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = selectAll.checked);
            updateCount();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateCount);
    });
    updateCount();
}

function doLiveSearch(page = 1) {
    const searchInput = document.getElementById('memberSearchInput');
    const rankSelect = document.getElementById('memberRankSelect');
    const spinner = document.getElementById('searchSpinner');
    const clearBtn = document.getElementById('clearFilterBtn');
    const contentArea = document.getElementById('membersContentArea');

    if (!searchInput || !contentArea) return;

    const searchValue = searchInput.value.trim();
    const rankValue = rankSelect ? rankSelect.value : '';

    // Show/hide clear button
    if (clearBtn) {
        if (searchValue !== '' || rankValue !== '') {
            clearBtn.style.display = 'inline-flex';
        } else {
            clearBtn.style.display = 'none';
        }
    }

    if (spinner) spinner.style.display = 'inline-block';

    if (currentSearchAbortController) {
        currentSearchAbortController.abort();
    }
    currentSearchAbortController = new AbortController();

    const params = new URLSearchParams();
    if (searchValue) params.set('search', searchValue);
    if (rankValue) params.set('rank', rankValue);
    if (page > 1) params.set('page', page);

    const queryStr = params.toString();
    const newBrowserUrl = 'members_list.php' + (queryStr ? '?' + queryStr : '');
    window.history.replaceState(null, '', newBrowserUrl);

    params.set('ajax', '1');
    const fetchUrl = 'members_list.php?' + params.toString();

    fetch(fetchUrl, { signal: currentSearchAbortController.signal })
        .then(response => {
            if (!response.ok) throw new Error('Search failed');
            return response.text();
        })
        .then(html => {
            contentArea.innerHTML = html;
            bindMemberCheckboxes();
            if (spinner) spinner.style.display = 'none';
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                console.error('Live search error:', err);
                if (spinner) spinner.style.display = 'none';
            }
        });
}

function initLiveSearch() {
    const searchInput = document.getElementById('memberSearchInput');
    const rankSelect = document.getElementById('memberRankSelect');
    const clearBtn = document.getElementById('clearFilterBtn');
    const searchBtn = document.getElementById('searchSubmitBtn');
    const contentArea = document.getElementById('membersContentArea');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                doLiveSearch(1);
            }, 250);
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchDebounceTimer);
                doLiveSearch(1);
            }
        });
    }

    if (rankSelect) {
        rankSelect.addEventListener('change', function() {
            clearTimeout(searchDebounceTimer);
            doLiveSearch(1);
        });
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            clearTimeout(searchDebounceTimer);
            doLiveSearch(1);
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (searchInput) searchInput.value = '';
            if (rankSelect) rankSelect.value = '';
            clearTimeout(searchDebounceTimer);
            doLiveSearch(1);
            if (searchInput) searchInput.focus();
        });
    }

    // Intercept pagination clicks in contentArea
    if (contentArea) {
        contentArea.addEventListener('click', function(e) {
            const pageLink = e.target.closest('.pagination-link');
            if (pageLink && !pageLink.classList.contains('disabled') && !pageLink.classList.contains('active')) {
                e.preventDefault();
                const href = pageLink.getAttribute('href');
                if (href) {
                    const url = new URL(href, window.location.origin + window.location.pathname);
                    const page = url.searchParams.get('page') || 1;
                    doLiveSearch(page);
                }
            }
        });
    }
}

function openCongressModal(id, name) {
    document.getElementById('modal_member_id').value = id;
    document.getElementById('modal_member_name').innerText = name;
    
    const checkboxes = document.querySelectorAll('#congress_list input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = false);
    
    fetch('get_member_congresses.php?id=' + id)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (Array.isArray(data)) {
                data.forEach(election_id => {
                    const cb = document.querySelector('.congress-checkbox-' + election_id);
                    if (cb) cb.checked = true;
                });
            }
            document.getElementById('congressModal').style.display = 'block';
        })
        .catch(err => {
            console.error('Error fetching congresses:', err);
            document.getElementById('congressModal').style.display = 'block';
        });
}

function closeCongressModal() {
    document.getElementById('congressModal').style.display = 'none';
}

function submitBulkCongress() {
    const electionId = document.getElementById('bulk_congress_id').value;
    const selected = document.querySelectorAll('.member-checkbox:checked');

    if (!electionId) {
        alert('تکایە کۆنگرەیەک هەڵبژێرە');
        return;
    }
    if (selected.length === 0) {
        alert('تکایە بەلایەنی کەم ئەندامێک هەڵبژێرە');
        return;
    }

    if (confirm(`ئایا دڵنیای لە زیادکردنی ${selected.length} ئەندام بۆ ئەم کۆنگرەیە؟`)) {
        document.getElementById('bulk_election_id').value = electionId;
        document.getElementById('bulkForm').submit();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    bindMemberCheckboxes();
    initLiveSearch();

    // Toast logic
    const urlParams = new URLSearchParams(window.location.search);
    const updatedId = urlParams.get('updated_id');
    if (updatedId) {
        const toast = document.getElementById('successToast');
        if (toast) {
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 4000);
        }
        const updatedRow = document.querySelector(`tbody tr[data-id="${updatedId}"]`);
        if (updatedRow) {
            updatedRow.classList.add('row-updated');
            updatedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});
</script>

<?php require_once 'includes/layout_end.php'; ?>
