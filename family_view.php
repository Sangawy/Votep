<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$family_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($family_id <= 0) {
    header('Location: families_manage.php');
    exit;
}

// Fetch family details
$res = $conn->query("SELECT * FROM families WHERE id = $family_id");
$family = $res->fetch_assoc();
if (!$family) {
    header('Location: families_manage.php');
    exit;
}

// Grouping logic
$grouped_view = isset($_GET['grouped']) && $_GET['grouped'] === '1';

// Filter selection (active, ancestors, all)
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'active';
if (!in_array($filter, ['active', 'ancestors', 'all'])) {
    $filter = 'active';
}

// Toggle Family Head Status
if (isset($_GET['toggle_head'])) {
    $m_id = (int)$_GET['toggle_head'];
    $status = (int)$_GET['status'];
    $conn->query("UPDATE members SET is_family_head = $status WHERE id = $m_id");
    header("Location: family_view.php?id=$family_id&filter=$filter" . ($grouped_view ? "&grouped=1" : ""));
    exit;
}

// Toggle Member Status (active <-> inactive)
if (isset($_GET['toggle_status'])) {
    $m_id = (int)$_GET['toggle_status'];
    $new_status = (isset($_GET['new_status']) && $_GET['new_status'] === 'active') ? 'active' : 'inactive';
    $conn->query("UPDATE members SET member_status = '$new_status' WHERE id = $m_id AND family_id = $family_id");
    header("Location: family_view.php?id=$family_id&filter=$filter" . ($grouped_view ? "&grouped=1" : ""));
    exit;
}

// Counts for filters
$count_res = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN member_status = 'active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN member_status != 'active' OR member_status IS NULL THEN 1 ELSE 0 END) as inactive_count
    FROM members WHERE family_id = $family_id");
$counts = $count_res ? $count_res->fetch_assoc() : [];
$total_count = (int)($counts['total'] ?? 0);
$active_count = (int)($counts['active_count'] ?? 0);
$inactive_count = (int)($counts['inactive_count'] ?? 0);

// Fetch All Members of this family for descendant hierarchy
$all_family_members = [];
$all_res = $conn->query("SELECT id, parent_id, is_family_head, full_name, member_status FROM members WHERE family_id = $family_id");
while ($ar = $all_res->fetch_assoc()) {
    $all_family_members[$ar['id']] = $ar;
}

// Fetch Household Heads IDs
$heads_ids = [];
foreach ($all_family_members as $m) {
    if (!empty($m['is_family_head'])) {
        $heads_ids[] = $m['id'];
    }
}

// Filter WHERE clause
$where_status = "";
if ($filter === 'active') {
    $where_status = "AND m.member_status = 'active'";
} elseif ($filter === 'ancestors') {
    $where_status = "AND (m.member_status != 'active' OR m.member_status IS NULL)";
}

// Fetch Members based on selected filter
$members_by_id = [];
$res = $conn->query("SELECT m.*, f.full_name as father_name 
                    FROM members m 
                    LEFT JOIN members f ON m.parent_id = f.id 
                    WHERE m.family_id = $family_id $where_status
                    ORDER BY m.id DESC");
while ($row = $res->fetch_assoc()) {
    $members_by_id[$row['id']] = $row;
}

// Recursive function to count all descendants
if (!function_exists('countDescendants')) {
    function countDescendants($parentId, $allMembers) {
        $count = 0;
        foreach ($allMembers as $m) {
            if ($m['parent_id'] == $parentId) {
                $count += 1 + countDescendants($m['id'], $allMembers);
            }
        }
        return $count;
    }
}

// Function to check if someone is a descendant of a list of heads (Recursive check)
if (!function_exists('isDescendantOfHeads')) {
    function isDescendantOfHeads($member, $allMembers, $heads) {
        $pid = $member['parent_id'];
        while ($pid > 0) {
            if (in_array($pid, $heads)) return true;
            if (!isset($allMembers[$pid])) break;
            $pid = $allMembers[$pid]['parent_id'];
        }
        return false;
    }
}

// Filter members based on toggle
$display_members = [];
foreach ($members_by_id as $m) {
    if ($grouped_view && isDescendantOfHeads($m, $all_family_members, $heads_ids)) {
        continue; 
    }
    $display_members[] = $m;
}

$page_title = $family['family_name'];
$active_nav = 'families_manage';
$sys_module = 'lineage';
require_once 'includes/layout.php';
?>

<style>
    .section-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin: 30px 0 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #eee;
    }
    .section-header i { color: var(--gold); font-size: 1.5rem; }
    .section-header h3 { margin: 0; color: #1a5653; }
    
    /* Grid Layout for Household Folders */
    .folder-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }
    
    .family-folder {
        background: #fff;
        border-radius: 15px;
        padding: 30px 15px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        cursor: pointer;
        border: 1px solid #eee;
        position: relative;
        text-decoration: none;
        display: block;
    }
    .family-folder:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-color: var(--gold);
    }
    .folder-icon { font-size: 3.5rem; color: #f1c40f; margin-bottom: 15px; display: block; }
    .folder-name { font-family: 'Rabar022', sans-serif; font-weight: bold; color: #1a5653; font-size: 1.1rem; margin-bottom: 5px; display: block; }
    .folder-meta { font-size: 0.8rem; color: #666; display: block; }
    
    .member-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        background: var(--gold);
        color: #fff;
        font-size: 0.75rem;
        font-weight: bold;
        padding: 4px 10px;
        border-radius: 20px;
        box-shadow: 0 2px 8px rgba(212, 175, 55, 0.4);
    }

    /* Toggle Switch Style */
    .switch-container {
        display: flex;
        align-items: center;
        gap: 12px;
        background: #f8f9fa;
        padding: 8px 20px;
        border-radius: 30px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .switch {
        position: relative;
        display: inline-block;
        width: 46px;
        height: 24px;
    }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 18px; width: 18px;
        left: 3px; bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    input:checked + .slider { background-color: var(--gold); }
    input:checked + .slider:before { transform: translateX(22px); }
    
    .member-list-card {
        background: #fff;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    .member-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.2s;
    }
    .member-row:hover { background: #f9f9f9; }
    .member-info { display: flex; align-items: center; gap: 15px; }
    .member-avatar {
        width: 45px;
        height: 45px;
        background: #e8f0fe;
        color: #1a73e8;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    .badge-head {
        background: #fff3cd;
        color: #856404;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: bold;
        border: 1px solid #ffeeba;
    }
    .filter-tabs {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 15px 0 20px;
        flex-wrap: wrap;
    }
    .filter-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 700;
        text-decoration: none;
        background: #f8fafc;
        color: #475569;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .filter-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
        transform: translateY(-1px);
    }
    .filter-btn.active {
        background: #1a5653;
        color: #ffffff;
        border-color: #1a5653;
        box-shadow: 0 4px 12px rgba(26, 86, 83, 0.22);
    }
    .filter-btn .filter-count {
        background: rgba(0, 0, 0, 0.07);
        color: inherit;
        padding: 2px 9px;
        border-radius: 15px;
        font-size: 0.78rem;
        font-weight: 800;
    }
    .filter-btn.active .filter-count {
        background: rgba(255, 255, 255, 0.25);
        color: #ffffff;
    }
    .status-badge-active {
        font-size: 0.75rem;
        background: #ecfdf5;
        color: #047857;
        padding: 2px 9px;
        border-radius: 12px;
        border: 1px solid #a7f3d0;
        font-weight: 700;
    }
    .status-badge-ancestor {
        font-size: 0.75rem;
        background: #f8fafc;
        color: #64748b;
        padding: 2px 9px;
        border-radius: 12px;
        border: 1px solid #cbd5e1;
        font-weight: 700;
    }
    .search-box-wrapper {
        position: relative;
        max-width: 380px;
        margin-bottom: 20px;
    }
    .search-box-wrapper input {
        width: 100%;
        padding: 10px 42px 10px 15px;
        border-radius: 25px;
        border: 1px solid #cbd5e1;
        background: #fff;
        outline: none;
        font-family: inherit;
        font-size: 0.9rem;
        transition: all 0.2s ease;
        box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    }
    .search-box-wrapper input:focus {
        border-color: #1a5653;
        box-shadow: 0 0 0 3px rgba(26, 86, 83, 0.12);
    }
    .search-box-wrapper i {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 0.95rem;
    }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; padding: 0 20px;">
    <div style="display: flex; align-items: center; gap: 10px;">
        <a href="families_manage.php" style="color: #666; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 0.95rem;">
            <i class="fas fa-arrow-right"></i> گەڕانەوە
        </a>
        <span style="color: #cbd5e1; font-size: 1.2rem;">/</span>
        <h2 style="margin: 0; color: #1a5653; font-size: 1.6rem; font-weight: bold;"><?php echo htmlspecialchars($family['family_name']); ?></h2>
    </div>

    <div>
        <a href="lineage_tree.php?family_id=<?php echo $family_id; ?>" class="lib-btn lib-btn-primary" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 10px 20px; font-size: 0.95rem; font-weight: bold; border-radius: 10px; box-shadow: 0 4px 12px rgba(26, 86, 83, 0.22); transition: all 0.2s ease;">
            <i class="fas fa-sitemap" style="color: var(--gold); font-size: 1.15rem;"></i>
            داری خێزانی ئەم بنەماڵەیە
        </a>
    </div>
</div>

<div style="padding: 0 20px;">
    <!-- Households Section -->
    <div class="section-header">
        <i class="fas fa-users-cog"></i>
        <h3>خێزانەکانی ناو ئەم بنەماڵەیە (سەرۆک خێزانەکان)</h3>
    </div>
    
    <?php 
    $heads_only = array_filter($all_family_members, function($m) { return !empty($m['is_family_head']); });
    if(empty($heads_only)): 
    ?>
        <div style="padding: 30px; text-align: center; background: #f8f9fa; border-radius: 15px; border: 2px dashed #ddd;">
            <p style="color: #888; margin: 0;">هیچ سەرۆک خێزانێک دیاری نەکراوە.</p>
        </div>
    <?php else: ?>
        <div class="folder-grid">
            <?php foreach ($heads_only as $h): 
                $descCount = countDescendants($h['id'], $all_family_members);
            ?>
            <a href="household_view.php?id=<?php echo $h['id']; ?>&family_id=<?php echo $family_id; ?>" class="family-folder">
                <div class="member-badge">
                    <i class="fas fa-users" style="margin-left: 4px;"></i> <?php echo number_format($descCount + 1); ?>
                </div>
                <i class="fas fa-folder folder-icon"></i>
                <span class="folder-name"><?php echo htmlspecialchars($h['full_name']); ?></span>
                <span class="folder-meta">سەرۆک خێزان</span>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- All Members Section Header with Toggle -->
    <div class="section-header" style="justify-content: space-between; flex-wrap: wrap; gap: 15px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <i class="fas fa-list-ul"></i>
            <h3>لیستی ئەندامانی بنەماڵە</h3>
        </div>
        
        <div class="switch-container">
            <span style="font-size: 0.85rem; color: #555; font-weight: bold;">کۆکردنەوەی خێزانی</span>
            <label class="switch" title="شاردنەوەی نەوەکان لە لیستی گشتی">
                <input type="checkbox" id="groupToggle" <?php echo $grouped_view ? 'checked' : ''; ?> onchange="toggleGrouping()">
                <span class="slider"></span>
            </label>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="family_view.php?id=<?php echo $family_id; ?>&filter=active<?php echo $grouped_view ? '&grouped=1' : ''; ?>" 
           class="filter-btn <?php echo $filter === 'active' ? 'active' : ''; ?>">
            <i class="fas fa-user-check"></i> ئەندامانی چالاک / لە ژیاندا ماوان
            <span class="filter-count"><?php echo number_format($active_count); ?></span>
        </a>
        <a href="family_view.php?id=<?php echo $family_id; ?>&filter=ancestors<?php echo $grouped_view ? '&grouped=1' : ''; ?>" 
           class="filter-btn <?php echo $filter === 'ancestors' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> باوباپیران و کەسایەتییە مێژووییەکان
            <span class="filter-count"><?php echo number_format($inactive_count); ?></span>
        </a>
        <a href="family_view.php?id=<?php echo $family_id; ?>&filter=all<?php echo $grouped_view ? '&grouped=1' : ''; ?>" 
           class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> سەرجەم تۆمارکراوەکان (هەموو)
            <span class="filter-count"><?php echo number_format($total_count); ?></span>
        </a>
    </div>

    <!-- Instant Search Box -->
    <div class="search-box-wrapper">
        <i class="fas fa-search"></i>
        <input type="text" id="memberSearchInput" onkeyup="filterMemberRows()" placeholder="گەڕان بەدوای ناو یان باوک لەم لیستەدا...">
    </div>

    <div class="member-list-card">
        <?php foreach ($display_members as $m): ?>
        <div class="member-row" data-name="<?php echo htmlspecialchars(mb_strtolower($m['full_name'] . ' ' . ($m['father_name'] ?? ''))); ?>">
            <div class="member-info">
                <div class="member-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <span style="font-weight: bold; color: #333; font-size: 1.05rem;"><?php echo htmlspecialchars($m['full_name']); ?></span>
                        <?php if(!empty($m['is_family_head'])): ?>
                            <span class="badge-head">👑 باوکی گەورەی بنەماڵە</span>
                        <?php endif; ?>
                        <?php if(($m['member_status'] ?? '') === 'active'): ?>
                            <span class="status-badge-active">🟢 لە ژیاندا ماو / چالاک</span>
                        <?php else: ?>
                            <span class="status-badge-ancestor">📜 باوباپیر / کەسایەتی مێژوویی</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 0.85rem; color: #777; margin-top: 3px;">
                        کۆد: <strong><?php echo htmlspecialchars($m['member_code'] ?: $m['id']); ?></strong> | 
                        ناوچە: <?php echo htmlspecialchars($m['address'] ?: '-'); ?> | 
                        باوک: <?php echo htmlspecialchars($m['father_name'] ?: 'نادیار'); ?>
                    </div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                <?php if(($m['member_status'] ?? '') === 'active'): ?>
                    <button onclick="toggleMemberStatus(<?php echo $m['id']; ?>, 'inactive', '<?php echo addslashes(htmlspecialchars($m['full_name'])); ?>')" 
                            class="lib-btn" 
                            style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 0.85rem; padding: 6px 12px;" 
                            title="گۆڕینی دۆخ بۆ باوباپیران و کۆچکردووان">
                        <i class="fas fa-history"></i> بیخە باوباپیران
                    </button>
                <?php else: ?>
                    <button onclick="toggleMemberStatus(<?php echo $m['id']; ?>, 'active', '<?php echo addslashes(htmlspecialchars($m['full_name'])); ?>')" 
                            class="lib-btn" 
                            style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 0.85rem; padding: 6px 12px;" 
                            title="گۆڕینی دۆخ بۆ ئەندامی چالاک و لە ژیاندا ماو">
                        <i class="fas fa-user-check"></i> بیخە لە ژیاندا ماوان
                    </button>
                <?php endif; ?>

                <?php if(!empty($m['is_family_head'])): ?>
                    <button onclick="toggleHead(<?php echo $m['id']; ?>, 0)" class="lib-btn" style="background: #f8f9fa; color: #dc3545; border: 1px solid #ddd; font-size: 0.85rem; padding: 6px 12px;">❌ لابردنی باوکی گەورەی بنەماڵە</button>
                <?php else: ?>
                    <button onclick="toggleHead(<?php echo $m['id']; ?>, 1)" class="lib-btn lib-btn-gold" style="font-size: 0.85rem; padding: 6px 12px;">👑 دیاریکردن وەک باوکی گەورەی بنەماڵە</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if(empty($display_members)): ?>
            <div id="noMembersAlert" style="padding: 40px; text-align: center; color: #888;">هیچ ئەندامێک لەژێر ئەم فلتەرەدا بۆ نیشاندان نییە.</div>
        <?php else: ?>
            <div id="noSearchMatches" style="display: none; padding: 40px; text-align: center; color: #888;">هیچ ناوێک نەدۆزرایەوە لەگەڵ گەڕانەکەتدا یەک بگرێتەوە.</div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleGrouping() {
    const isChecked = document.getElementById('groupToggle').checked;
    const url = new URL(window.location.href);
    if (isChecked) {
        url.searchParams.set('grouped', '1');
    } else {
        url.searchParams.delete('grouped');
    }
    window.location.href = url.toString();
}

async function toggleHead(memberId, status) {
    const title = status === 1 ? 'کردن بە سەرۆک خێزان' : 'لابردنی سەرۆک خێزان';
    const text = status === 1 ? 'ئایا دڵنیای لەوەی ئەم ئەندامە بکەیت بە سەرۆک خێزان؟' : 'ئایا دڵنیای لە لابردنی ئەم ئەندامە لە لیستی سەرۆک خێزانەکان؟';
    
    const confirmed = await showConfirm(title, text, status === 1 ? 'question' : 'warning');
    if (confirmed) {
        let redirectUrl = `family_view.php?id=<?php echo $family_id; ?>&filter=<?php echo $filter; ?>&toggle_head=${memberId}&status=${status}`;
        <?php if($grouped_view): ?>
            redirectUrl += '&grouped=1';
        <?php endif; ?>
        window.location.href = redirectUrl;
    }
}

async function toggleMemberStatus(memberId, newStatus, memberName) {
    const isToActive = newStatus === 'active';
    const title = isToActive ? 'گواستنەوە بۆ لە ژیاندا ماوان' : 'گواستنەوە بۆ باوباپیران / کۆچکردووان';
    const text = isToActive 
        ? `ئایا دڵنیای لە گۆڕینی دۆخی (${memberName}) بۆ لیستی ئەندامانی چالاک و لە ژیاندا ماو؟` 
        : `ئایا دڵنیای لە گۆڕینی دۆخی (${memberName}) بۆ لیستی باوباپیران و کەسایەتییە مێژووییەکان؟`;
    
    const confirmed = await showConfirm(title, text, isToActive ? 'question' : 'warning');
    if (confirmed) {
        let redirectUrl = `family_view.php?id=<?php echo $family_id; ?>&filter=<?php echo $filter; ?>&toggle_status=${memberId}&new_status=${newStatus}`;
        <?php if($grouped_view): ?>
            redirectUrl += '&grouped=1';
        <?php endif; ?>
        window.location.href = redirectUrl;
    }
}

function normalizeKurdish(str) {
    if (!str) return '';
    return str
        .toString()
        .toLowerCase()
        .replace(/[يى]/g, 'ی')
        .replace(/[ك]/g, 'ک')
        .replace(/[ة]/g, 'ە')
        .replace(/[\u064B-\u065F\u0670]/g, '')
        .replace(/[آأإ]/g, 'ا')
        .replace(/\s+/g, ' ')
        .trim();
}

function filterMemberRows() {
    const query = normalizeKurdish(document.getElementById('memberSearchInput').value);
    const rows = document.querySelectorAll('.member-row');
    let visibleCount = 0;
    rows.forEach(r => {
        const text = normalizeKurdish(r.getAttribute('data-name') || '');
        if (!query || text.includes(query)) {
            r.style.display = 'flex';
            visibleCount++;
        } else {
            r.style.display = 'none';
        }
    });
    const noMatch = document.getElementById('noSearchMatches');
    if (noMatch) {
        noMatch.style.display = (visibleCount === 0 && rows.length > 0) ? 'block' : 'none';
    }
}
</script>

<?php require_once 'includes/layout_end.php'; ?>
