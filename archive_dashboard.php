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

if (!$is_super_admin && !(isset($user_data['can_access_archive']) && $user_data['can_access_archive'])) {
    header('Location: portal.php');
    exit;
}

// Statistics
$total_activities = $conn->query("SELECT COUNT(*) as count FROM activities")->fetch_assoc()['count'];
$pending_activities = $conn->query("SELECT COUNT(*) as count FROM activities WHERE is_completed = 0")->fetch_assoc()['count'];

$page_title = 'داشبۆردی ئەرشیف';
$active_nav = 'archive_dashboard';
$sys_module = 'archive';

require_once 'includes/layout.php';
?>

<style>
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: #fff;
        padding: 25px;
        border-radius: 20px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: 0.3s;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
    }
    .stat-info h4 {
        font-size: 0.9rem;
        color: var(--text-muted);
        margin-bottom: 5px;
    }
    .stat-info .value {
        font-size: 1.8rem;
        font-weight: 800;
        color: var(--maroon);
    }
</style>

<div class="lib-card" style="max-width: 1200px; margin: 0 auto;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3><?php echo $page_title; ?></h3>
        <a href="archive_new.php" class="lib-btn lib-btn-primary">+ زیادکردنی چالاکی</a>
    </div>
    
    <div style="padding: 25px;">
        <!-- Count Cards -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(90,2,2,0.1); color: var(--maroon);">📊</div>
                <div class="stat-info">
                    <h4>کۆی گشتی چالاکییەکان</h4>
                    <div class="value"><?php echo $total_activities; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(204,169,81,0.1); color: var(--gold-dark);">⏳</div>
                <div class="stat-info">
                    <h4>چالاکییە ماوە بکرێن</h4>
                    <div class="value"><?php echo $pending_activities; ?></div>
                </div>
            </div>
        </div>

        <h4 style="margin: 30px 0 20px 0; color: var(--maroon); border-bottom: 2px solid var(--gold); padding-bottom: 10px;">چالاکییە نوێیەکان (کاتی ئەنجام دانی نەهاتووە)</h4>
        
        <div style="overflow-x: auto;">
            <table class="lib-table">
                <thead>
                    <tr>
                        <th>ناوی چالاکی</th>
                        <th>کاتی چالاکی</th>
                        <th>شوێن</th>
                        <th>بەرپرسی</th>
                        <th>بەشداربوان</th>
                        <th>بارودۆخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $now = date('Y-m-d H:i:s');
                    $res_upcoming = $conn->query("SELECT * FROM activities WHERE is_completed = 0 AND activity_date > '$now' ORDER BY activity_date ASC");
                    if ($res_upcoming && $res_upcoming->num_rows > 0):
                        while($row = $res_upcoming->fetch_assoc()):
                    ?>
                        <tr>
                            <td style="font-weight: bold;"><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['activity_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['location']); ?></td>
                            <td><?php echo htmlspecialchars($row['implemented_by']); ?></td>
                            <td style="text-align:center;"><?php echo $row['participants_count']; ?></td>
                            <td style="text-align:center;">
                                <span class="lib-badge lib-badge-gold">چاوەڕوان</span>
                            </td>
                        </tr>
                    <?php 
                        endwhile;
                    else:
                        echo '<tr><td colspan="6" style="text-align:center;">هیچ چالاکییەکی داهاتوو تۆمار نەکراوە.</td></tr>';
                    endif;
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once 'includes/layout_end.php'; ?>
