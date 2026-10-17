<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$today = date('Y-m-d');
$three_months_later = date('Y-m-d', strtotime('+3 months'));

// Statistics
$total_due = $conn->query("SELECT COUNT(*) as cnt FROM staff_members WHERE promotion_due_date <= '$today'")->fetch_assoc()['cnt'];
$coming_soon = $conn->query("SELECT COUNT(*) as cnt FROM staff_members WHERE promotion_due_date > '$today' AND promotion_due_date <= '$three_months_later'")->fetch_assoc()['cnt'];

// Schedule filtering
$schedule = $_GET['schedule'] ?? 'all'; // all, nawroz (1-6), aylul (7-12)
$where = "1=1";
if ($schedule == 'nawroz') {
    $where = "MONTH(promotion_due_date) BETWEEN 1 AND 6";
} elseif ($schedule == 'aylul') {
    $where = "MONTH(promotion_due_date) BETWEEN 7 AND 12";
}

$sql = "SELECT m.*, r.rank_name, u.unit_name, r.priority 
        FROM staff_members m 
        JOIN staff_ranks r ON m.rank_id = r.id 
        JOIN staff_units u ON m.unit_id = u.id 
        WHERE $where AND (m.promotion_due_date <= '$three_months_later')
        ORDER BY r.priority DESC, m.promotion_due_date ASC";

$res = $conn->query($sql);
$staff_list = [];
while ($row = $res->fetch_assoc()) $staff_list[] = $row;

$page_title = 'بەڕێوەبردنی پلە بەرزکردنەوە';
$active_nav = 'staff_promotions';
$sys_module = 'nusinga';
require_once 'includes/layout.php';
?>

<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); margin-bottom: 30px;">
    <div class="lib-card" style="background: linear-gradient(135deg, #fff 0%, #fff5f5 100%);">
        <div style="padding: 25px; text-align: center;">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">🚩</div>
            <h2 style="color: var(--danger);"><?php echo $total_due; ?></h2>
            <p style="font-weight: 700; color: #666;">کاتی هاتووە (شایستە)</p>
        </div>
    </div>
    <div class="lib-card" style="background: linear-gradient(135deg, #fff 0%, #fffdf0 100%);">
        <div style="padding: 25px; text-align: center;">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">⏳</div>
            <h2 style="color: #856404;"><?php echo $coming_soon; ?></h2>
            <p style="font-weight: 700; color: #666;">نزیک بۆتەوە (٣ مانگی ماوە)</p>
        </div>
    </div>
</div>

<div class="lib-card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3>📋 لیستی شایستەبووانی پلە بەرزکردنەوە</h3>
        <div style="display: flex; gap: 10px;">
            <a href="?schedule=all" class="lib-btn <?php echo ($schedule=='all') ? 'lib-btn-primary' : 'lib-btn-warning'; ?>">هەمووی</a>
            <a href="?schedule=nawroz" class="lib-btn <?php echo ($schedule=='nawroz') ? 'lib-btn-primary' : 'lib-btn-warning'; ?>">🌸 خشتەی نەورۆز (١-٦)</a>
            <a href="?schedule=aylul" class="lib-btn <?php echo ($schedule=='aylul') ? 'lib-btn-primary' : 'lib-btn-warning'; ?>">🍂 خشتەی ئەیلول (٧-١٢)</a>
        </div>
    </div>
    <div style="padding: 20px;">
        <div class="lib-table-wrapper">
            <table class="lib-table">
                <thead>
                    <tr>
                        <th>پلە</th>
                        <th>ناوی چواری</th>
                        <th>یەکە</th>
                        <th>بەرواری دوا پلە</th>
                        <th>بەرواری شایستەبوون</th>
                        <th>دۆخ</th>
                        <th>کردار</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($staff_list)): ?>
                        <tr><td colspan="7" style="padding: 50px; color: #999;">هیچ کەسێک شایستە نییە لەم خشتەیەدا</td></tr>
                    <?php else: ?>
                        <?php foreach ($staff_list as $s): ?>
                            <?php 
                                $is_due = (strtotime($s['promotion_due_date']) <= strtotime($today));
                                $is_warning = (!$is_due && strtotime($s['promotion_due_date']) <= strtotime($three_months_later));
                                
                                $status_class = "lib-badge-green";
                                $status_text = "کاتی ماوە";
                                if ($is_due) {
                                    $status_class = "lib-badge-red";
                                    $status_text = "کاتی هاتووە";
                                } elseif ($is_warning) {
                                    $status_class = "lib-badge-gold";
                                    $status_text = "ئامادەکاری";
                                }
                            ?>
                            <tr>
                                <td><span class="lib-badge lib-badge-maroon"><?php echo htmlspecialchars($s['rank_name']); ?></span></td>
                                <td style="font-weight: 700;"><?php echo htmlspecialchars($s['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($s['unit_name']); ?></td>
                                <td><?php echo $s['last_promotion_date']; ?></td>
                                <td style="font-weight: 700; color: <?php echo $is_due ? 'red' : 'inherit'; ?>"><?php echo $s['promotion_due_date']; ?></td>
                                <td><span class="lib-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                <td>
                                    <?php if ($is_due): ?>
                                        <button onclick="promoteStaff(<?php echo $s['id']; ?>, '<?php echo addslashes($s['full_name']); ?>')" class="lib-btn lib-btn-success">⏫ بەرزکردنەوە</button>
                                    <?php else: ?>
                                        <a href="staff_profile.php?id=<?php echo $s['id']; ?>" class="lib-btn lib-btn-primary">👤 بینین</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function promoteStaff(id, name) {
    if (confirm(`ئایا دڵنیایت لە بەرزکردنەوەی پلەی (${name})؟\nئەمەش پلەی داهاتوو و بەرواری شایستەبوونی نوێ دیاری دەکات.`)) {
        window.location.href = `staff_promote_action.php?id=${id}`;
    }
}
</script>

<?php require_once 'includes/layout_end.php'; ?>
