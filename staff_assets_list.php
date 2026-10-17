<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? 'all';
$status = $_GET['status'] ?? 'Held';

$where = "1=1";
if (!empty($search)) {
    $where .= " AND (a.item_name LIKE '%$search%' OR a.serial_number LIKE '%$search%' OR m.full_name LIKE '%$search%')";
}
if ($category != 'all') {
    $where .= " AND a.category = '$category'";
}
if ($status != 'all') {
    $where .= " AND a.status = '$status'";
}

$sql = "SELECT a.*, m.full_name, r.rank_name 
        FROM staff_assets a 
        JOIN staff_members m ON a.staff_id = m.id 
        JOIN staff_ranks r ON m.rank_id = r.id
        WHERE $where 
        ORDER BY a.received_date DESC";

$res = $conn->query($sql);
$assets = [];
while ($row = $res->fetch_assoc()) $assets[] = $row;

$page_title = 'لیستی گشتی زممە و مەوجودات';
$active_nav = 'staff_assets';
$sys_module = 'nusinga';
require_once 'includes/layout.php';
?>

<div class="lib-card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <h3>📋 گەڕان و ڕاپۆرتی گشتی زممە</h3>
        <form method="GET" style="display: flex; gap: 10px; flex: 1; max-width: 800px;">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="گەڕان بەپێی ناو، پارچە، S/N..." style="flex: 2; padding: 10px; border-radius: 8px; border: 1px solid var(--border);">
            <select name="category" style="flex: 1; padding: 10px; border-radius: 8px; border: 1px solid var(--border);">
                <option value="all">هەموو جۆرەکان</option>
                <option value="Weapon" <?php echo $category=='Weapon'?'selected':''; ?>>🔫 چەک</option>
                <option value="Vehicle" <?php echo $category=='Vehicle'?'selected':''; ?>>🚗 ئۆتۆمبێل</option>
                <option value="Property" <?php echo $category=='Property'?'selected':''; ?>>🏠 خانووبەرە</option>
                <option value="Tech" <?php echo $category=='Tech'?'selected':''; ?>>💻 تەکنەلۆژیا</option>
            </select>
            <select name="status" style="flex: 1; padding: 10px; border-radius: 8px; border: 1px solid var(--border);">
                <option value="all">هەموو دۆخەکان</option>
                <option value="Held" <?php echo $status=='Held'?'selected':''; ?>>📦 لای کارمەندە</option>
                <option value="Returned" <?php echo $status=='Returned'?'selected':''; ?>>✅ گەڕاوەتەوە</option>
            </select>
            <button type="submit" class="lib-btn lib-btn-primary">فلتەر</button>
        </form>
    </div>

    <div style="padding: 20px;">
        <div class="lib-table-wrapper">
            <table class="lib-table">
                <thead>
                    <tr>
                        <th>جۆر</th>
                        <th>ناوی پارچە (S/N)</th>
                        <th>بەرپرسی زممە (کارمەند)</th>
                        <th>بەرواری وەرگرتن</th>
                        <th>موستەنەد</th>
                        <th>دۆخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assets)): ?>
                        <tr><td colspan="6" style="padding: 50px; color: #999;">هیچ تۆمارێک نەدۆزرایەوە</td></tr>
                    <?php else: ?>
                        <?php foreach ($assets as $a): ?>
                            <tr>
                                <td><?php echo $a['category']; ?></td>
                                <td style="text-align: right; font-weight: 700;">
                                    <?php echo htmlspecialchars($a['item_name']); ?>
                                    <div style="font-size: 0.7rem; color: #888;">S/N: <?php echo htmlspecialchars($a['serial_number']); ?></div>
                                </td>
                                <td style="text-align: right;">
                                    <a href="staff_profile.php?id=<?php echo $a['staff_id']; ?>" style="text-decoration: none; color: var(--maroon); font-weight: 700;">
                                        <?php echo htmlspecialchars($a['rank_name'] . ' / ' . $a['full_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo $a['received_date']; ?></td>
                                <td><code><?php echo htmlspecialchars($a['order_number']); ?></code></td>
                                <td>
                                    <span class="lib-badge <?php echo ($a['status']=='Returned') ? 'lib-badge-green' : 'lib-badge-gold'; ?>">
                                        <?php echo ($a['status']=='Returned') ? 'گەڕاوەتەوە' : 'لای کارمەندە'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/layout_end.php'; ?>
