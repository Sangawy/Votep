<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$month = $_GET['month'] ?? date('Y-m');
$month_start = $month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));

// 1. Most absences this month
$top_absentees = $conn->query("SELECT m.full_name, r.rank_name, u.unit_name, SUM(a.duration_days) as total_days 
                             FROM staff_attendance a 
                             JOIN staff_members m ON a.staff_id = m.id 
                             JOIN staff_ranks r ON m.rank_id = r.id 
                             JOIN staff_units u ON m.unit_id = u.id 
                             WHERE a.leave_type = 'Absence' AND a.start_date BETWEEN '$month_start' AND '$month_end'
                             GROUP BY a.staff_id ORDER BY total_days DESC LIMIT 20");

// 2. People finishing annual leave this month
$finishing_leaves = $conn->query("SELECT m.full_name, r.rank_name, a.end_date, a.duration_days 
                                FROM staff_attendance a 
                                JOIN staff_members m ON a.staff_id = m.id 
                                JOIN staff_ranks r ON m.rank_id = r.id 
                                WHERE a.leave_type = 'Annual Leave' AND a.end_date BETWEEN '$month_start' AND '$month_end'
                                ORDER BY a.end_date ASC");

// 3. General Stats for the month
$stats = $conn->query("SELECT leave_type, COUNT(*) as count, SUM(duration_days) as total_days 
                      FROM staff_attendance 
                      WHERE start_date BETWEEN '$month_start' AND '$month_end'
                      GROUP BY leave_type")->fetch_all(MYSQLI_ASSOC);

$page_title = 'ڕاپۆرتی غیابات و مۆڵەت';
$active_nav = 'staff_attendance';
$sys_module = 'nusinga';
require_once 'includes/layout.php';
?>

<div class="lib-card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3>📊 ڕاپۆرتی مانگانەی غیابات و مۆڵەت</h3>
        <form method="GET" style="display: flex; gap: 10px; align-items: center;">
            <input type="month" name="month" value="<?php echo $month; ?>" class="lib-input" style="width: 200px;" onchange="this.form.submit()">
            <button type="button" onclick="window.print()" class="lib-btn lib-btn-gold">🖨️ چاپکردن</button>
        </form>
    </div>

    <div style="padding: 25px; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
        <!-- General Stats Table -->
        <div class="lib-card" style="margin-bottom: 0;">
            <div class="card-header"><h4>📈 ئاماری گشتی</h4></div>
            <div class="lib-table-wrapper">
                <table class="lib-table">
                    <thead><tr><th>جۆر</th><th>تۆمارەکان</th><th>کۆی ڕۆژ</th></tr></thead>
                    <tbody>
                        <?php foreach($stats as $s): ?>
                            <tr>
                                <td><?php echo $s['leave_type']; ?></td>
                                <td><?php echo $s['count']; ?></td>
                                <td><?php echo $s['total_days']; ?> ڕۆژ</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($stats)): ?><tr><td colspan="3">هیچ ئامارێک نییە</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Absentees -->
        <div class="lib-card" style="margin-bottom: 0;">
            <div class="card-header"><h4>🚫 زۆرترین غیابات</h4></div>
            <div class="lib-table-wrapper">
                <table class="lib-table">
                    <thead><tr><th>ناو</th><th>یەکە</th><th>ڕۆژ</th></tr></thead>
                    <tbody>
                        <?php while($row = $top_absentees->fetch_assoc()): ?>
                            <tr>
                                <td style="text-align: right;"><?php echo $row['rank_name'] . ' ' . $row['full_name']; ?></td>
                                <td><?php echo $row['unit_name']; ?></td>
                                <td><span class="lib-badge lib-badge-red"><?php echo $row['total_days']; ?> ڕۆژ</span></td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if($top_absentees->num_rows == 0): ?><tr><td colspan="3">هیچ غیاباتێک نییە</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div style="padding: 25px;">
        <div class="lib-card">
            <div class="card-header"><h4>🏠 ئەو کەسانەی مۆڵەتیان تەواو کردووە (یان تەواو دەکەن)</h4></div>
            <div class="lib-table-wrapper">
                <table class="lib-table">
                    <thead>
                        <tr>
                            <th>ناو</th>
                            <th>پلە</th>
                            <th>بەرواری تەواوبوون</th>
                            <th>ماوەی مۆڵەت</th>
                            <th>دۆخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $finishing_leaves->fetch_assoc()): 
                            $is_past = (strtotime($row['end_date']) < time());
                        ?>
                            <tr>
                                <td style="text-align: right;"><?php echo $row['full_name']; ?></td>
                                <td><?php echo $row['rank_name']; ?></td>
                                <td><?php echo $row['end_date']; ?></td>
                                <td><?php echo $row['duration_days']; ?> ڕۆژ</td>
                                <td>
                                    <span class="lib-badge <?php echo $is_past ? 'lib-badge-red' : 'lib-badge-green'; ?>">
                                        <?php echo $is_past ? 'تەواو بووە' : 'لە مۆڵەتە'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if($finishing_leaves->num_rows == 0): ?><tr><td colspan="5">هیچ تۆمارێک نییە</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/layout_end.php'; ?>
