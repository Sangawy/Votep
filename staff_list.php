<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$filter_unit = $_GET['unit'] ?? '';
$filter_rank_cat = $_GET['rank_cat'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination Config
$limit = 30;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$where = "WHERE 1=1";
$params = [];
$types = "";

// Unit Isolation: If user has a specific unit assigned, they can only see that unit
if ($_SESSION['role'] === 'user' && isset($_SESSION['unit_id']) && $_SESSION['unit_id'] > 0) {
    $where .= " AND m.unit_id = ?";
    $params[] = (int)$_SESSION['unit_id'];
    $types .= "i";
    $filter_unit = $_SESSION['unit_id']; // Lock the filter
}

if ($filter_unit) {
    $where .= " AND m.unit_id = ?";
    $params[] = (int)$filter_unit;
    $types .= "i";
}
if ($filter_rank_cat) {
    $where .= " AND r.category = ?";
    $params[] = $filter_rank_cat;
    $types .= "s";
}
if ($search) {
    $where .= " AND m.full_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

// 1. Get Total Count for Pagination
$count_query = "SELECT COUNT(*) as total FROM staff_members m JOIN staff_ranks r ON m.rank_id = r.id $where";
$stmt_count = $conn->prepare($count_query);
if ($types) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_rows = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// 2. Get Paginated Data
$query = "SELECT m.*, r.rank_name, r.category, u.unit_name 
          FROM staff_members m 
          JOIN staff_ranks r ON m.rank_id = r.id 
          JOIN staff_units u ON m.unit_id = u.id 
          $where 
          ORDER BY r.priority DESC, m.full_name ASC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$page_types = $types . "ii";
$page_params = array_merge($params, [$limit, $offset]);
$stmt->bind_param($page_types, ...$page_params);
$stmt->execute();
$staff_list = $stmt->get_result();

// Fetch units for filter
$units = $conn->query("SELECT * FROM staff_units ORDER BY unit_name ASC");

$page_title = 'لیستی کارمەندان';
$active_nav = 'staff_list';
$sys_module = 'nusinga';
require_once 'includes/layout.php';

// Helper for pagination links
function getPageUrl($p) {
    $params = $_GET;
    $params['page'] = $p;
    return 'staff_list.php?' . http_build_query($params);
}
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
    .custom-toast.show {
        transform: translateX(0);
    }
    .toast-icon {
        background: var(--gold);
        color: #fff;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }
    .lib-table tbody tr {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .lib-table tbody tr:hover {
        background-color: #f0f7ff !important;
    }
    .pagination-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-top: 25px;
        padding-bottom: 20px;
    }
    .page-link {
        padding: 8px 16px;
        border-radius: 10px;
        background: #fff;
        border: 1px solid #ddd;
        color: #333;
        text-decoration: none;
        font-weight: bold;
        transition: 0.3s;
    }
    .page-link:hover {
        border-color: var(--gold);
        color: var(--maroon);
    }
    .page-link.active {
        background: var(--maroon);
        color: #fff;
        border-color: var(--maroon);
    }
    .page-info {
        font-size: 0.9rem;
        color: #666;
        margin: 0 15px;
    }
</style>

<div class="lib-card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3>👥 لیستی ئەفسەران و کارمەندان (<?php echo $total_rows; ?>)</h3>
        <div style="display: flex; gap: 10px;">
            <a href="staff_new.php" class="lib-btn lib-btn-primary">➕ زیادکردنی نوێ</a>
            <a href="custom_reports/index.php" class="lib-btn lib-btn-gold">📊 ڕاپۆرتەکان</a>
        </div>
    </div>
    
    <div style="padding: 20px; background: #f9f9f9; border-bottom: 1px solid #eee;">
        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
            <div class="lib-field">
                <label>گەڕان بەپێی ناو:</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="ناو بنوسە...">
            </div>
            <div class="lib-field">
                <label>فلتەر بەپێی یەکە:</label>
                <select name="unit" <?php echo ($_SESSION['role'] === 'user' && $_SESSION['unit_id'] > 0) ? 'disabled' : ''; ?>>
                    <option value="">هەموو یەکەکان</option>
                    <?php 
                    $units->data_seek(0);
                    while($u = $units->fetch_assoc()): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $filter_unit == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['unit_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <?php if ($_SESSION['role'] === 'user' && $_SESSION['unit_id'] > 0): ?>
                    <input type="hidden" name="unit" value="<?php echo $_SESSION['unit_id']; ?>">
                <?php endif; ?>
            </div>
            <div class="lib-field">
                <label>فلتەر بەپێی جۆر:</label>
                <select name="rank_cat">
                    <option value="">هەموو جۆرەکان</option>
                    <option value="Officer" <?php echo $filter_rank_cat == 'Officer' ? 'selected' : ''; ?>>ئەفسەر</option>
                    <option value="NCO" <?php echo $filter_rank_cat == 'NCO' ? 'selected' : ''; ?>>پلەدار</option>
                    <option value="Soldier" <?php echo $filter_rank_cat == 'Soldier' ? 'selected' : ''; ?>>سەرباز</option>
                </select>
            </div>
            <div style="display: flex; align-items: flex-end; gap: 10px;">
                <button type="submit" class="lib-btn lib-btn-gold" style="flex: 1;">🔍 فلتەر بکە</button>
                <a href="staff_list.php" class="lib-btn lib-btn-danger">❌ پاککردنەوە</a>
            </div>
        </form>
    </div>

    <div class="lib-table-wrapper">
        <table class="lib-table">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>وێنە</th>
                    <th>پلە</th>
                    <th>ناوی تەواو</th>
                    <th>یەکە</th>
                    <th>ڕشتە</th>
                    <th>مۆبایل</th>
                    <th>دوا پلە</th>
                    <th>شایستەی داهاتوو</th>
                    <th>کردارەکان</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($staff_list->num_rows === 0): ?>
                    <tr><td colspan="10" style="padding: 50px;">هیچ کارمەندێک نەدۆزرایەوە</td></tr>
                <?php else: ?>
                    <?php 
                    $counter = $offset + 1;
                    while($s = $staff_list->fetch_assoc()): ?>
                        <tr ondblclick="window.location.href='staff_profile.php?id=<?php echo $s['id']; ?>'">
                            <td style="font-weight: bold; color: #888;"><?php echo $counter++; ?></td>
                            <td>
                                <div style="width: 45px; height: 45px; border-radius: 50%; overflow: hidden; margin: 0 auto; border: 2px solid var(--gold); background: #eee;">
                                    <?php 
                                    $img_src = $s['profile_image'];
                                    if (empty($img_src)) $img_src = "assets/imgae/user.png";
                                    ?>
                                    <img src="stream_image.php?path=<?php echo urlencode($img_src); ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='assets/imgae/user.png'">
                                </div>
                            </td>
                            <td><span class="lib-badge lib-badge-maroon"><?php echo htmlspecialchars($s['rank_name']); ?></span></td>
                            <td style="text-align: right; font-weight: 700;"><?php echo htmlspecialchars($s['full_name']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($s['unit_name']); ?>
                                <?php if(!empty($s['sub_unit'])): ?>
                                    <br><small style="color: #666; font-weight: normal;">(<?php echo htmlspecialchars($s['sub_unit']); ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($s['branch']); ?></td>
                            <td><span style="direction: ltr; display: inline-block;"><?php echo htmlspecialchars($s['phone']); ?></span></td>
                            <td><?php echo !empty($s['last_promotion_date']) ? $s['last_promotion_date'] : 'دیاری نەکراوە'; ?></td>
                            <td>
                                <?php 
                                    $is_due = (!empty($s['promotion_due_date']) && strtotime($s['promotion_due_date']) <= time());
                                ?>
                                <span class="lib-badge <?php echo $is_due ? 'lib-badge-red' : 'lib-badge-green'; ?>">
                                    <?php echo !empty($s['promotion_due_date']) ? $s['promotion_due_date'] : 'دیاری نەکراوە'; ?>
                                    <?php if ($is_due) echo ' ⚠️'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="lib-actions" style="flex-direction: row; justify-content: center;">
                                    <a href="staff_profile.php?id=<?php echo $s['id']; ?>" class="lib-btn lib-btn-success" title="پڕۆفایل">👁️</a>
                                    <a href="staff_edit.php?id=<?php echo $s['id']; ?>" class="lib-btn lib-btn-warning" title="دەستکاری">✏️</a>
                                    <a href="staff_folder.php?id=<?php echo $s['id']; ?>" class="lib-btn lib-btn-gold" title="فۆڵدەری ئەرشیف">📁</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination-container">
        <?php if ($page > 1): ?>
            <a href="<?php echo getPageUrl(1); ?>" class="page-link" title="لاپەڕەی یەکەم">«</a>
            <a href="<?php echo getPageUrl($page - 1); ?>" class="page-link">پێشتر</a>
        <?php endif; ?>

        <?php 
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        
        for ($i = $start; $i <= $end; $i++): ?>
            <a href="<?php echo getPageUrl($i); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $total_pages): ?>
            <a href="<?php echo getPageUrl($page + 1); ?>" class="page-link">دواتر</a>
            <a href="<?php echo getPageUrl($total_pages); ?>" class="page-link" title="کۆتا لاپەڕە">»</a>
        <?php endif; ?>
        
        <span class="page-info">لاپەڕە <?php echo $page; ?> لە کۆی <?php echo $total_pages; ?></span>
    </div>
    <?php endif; ?>
</div>

<div id="successToast" class="custom-toast">
    <div class="toast-icon">✓</div>
    <span id="toastMessage">زانیارییەکان بە سەرکەوتوویی نوێکرانەوە</span>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const updatedId = urlParams.get('updated_id');
    
    if (updatedId) {
        // Show Custom Toast
        const toast = document.getElementById('successToast');
        toast.classList.add('show');
        setTimeout(() => {
            toast.classList.remove('show');
        }, 4000);
        
        // Find the row and apply effect
        const rows = document.querySelectorAll('tbody tr');
        let found = false;
        
        rows.forEach(row => {
            const profileLink = row.querySelector('a[href*="staff_profile.php?id="]');
            if (profileLink && profileLink.href.includes('id=' + updatedId)) {
                row.classList.add('row-updated');
                row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                found = true;
            }
        });
    }
});
</script>

<?php require_once 'includes/layout_end.php'; ?>
