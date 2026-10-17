<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$stats = [
    'total_books' => 0,
    'available_books' => 0,
    'borrowed_books' => 0,
    'total_users' => 0
];

if ($conn !== null) {
    // Basic Stats
    $result = $conn->query("SELECT COUNT(*) as total FROM books");
    if ($result && $row = $result->fetch_assoc()) $stats['total_books'] = $row['total'];
    
    $result = $conn->query("SELECT SUM(quantity) as total_qty FROM books");
    if ($result && $row = $result->fetch_assoc()) $stats['total_books_qty'] = $row['total_qty'] ?? 0;

    $result = $conn->query("SELECT COUNT(*) as borrowed FROM borrow_records WHERE status = 'borrowed'");
    if ($result && $row = $result->fetch_assoc()) $stats['borrowed_books'] = $row['borrowed'];
    
    $stats['available_books'] = $stats['total_books'] - $stats['borrowed_books'];

    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    if ($result && $row = $result->fetch_assoc()) $stats['total_users'] = $row['total'];

    // Category Stats Chart
    $cat_stats = [];
    $res = $conn->query("SELECT c.name, COUNT(b.id) as count FROM categories c LEFT JOIN books b ON c.id = b.category_id GROUP BY c.id HAVING count > 0 ORDER BY count DESC LIMIT 6");
    while ($row = $res->fetch_assoc()) $cat_stats[] = $row;

    // Recent Activity
    $activities = [];
    // Recent Additions
    $res = $conn->query("SELECT 'add' as type, title as item, created_at as time FROM books ORDER BY created_at DESC LIMIT 5");
    while ($row = $res->fetch_assoc()) $activities[] = $row;
    // Recent Borrows
    $res = $conn->query("SELECT 'borrow' as type, b.title as item, br.borrow_date as time FROM borrow_records br JOIN books b ON br.book_id = b.id ORDER BY br.id DESC LIMIT 5");
    while ($row = $res->fetch_assoc()) $activities[] = $row;
    
    usort($activities, function($a, $b) { return strtotime($b['time']) - strtotime($a['time']); });
    $activities = array_slice($activities, 0, 7);
}

// Layout Config
$page_title = 'داشبۆرد';
$active_nav = 'dashboard';
require_once 'includes/layout.php';
?>

<style>
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 30px; }
    .stat-card { background: #fff; padding: 25px; border-radius: 16px; box-shadow: var(--shadow-md); border: 1px solid var(--border); display: flex; align-items: center; gap: 20px; transition: transform 0.2s; }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-icon { width: 60px; height: 60px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; }
    .stat-icon.total { background: #efebe9; color: var(--maroon); }
    .stat-icon.available { background: #e8f5e9; color: #2e7d32; }
    .stat-icon.borrowed { background: #fff3e0; color: #e65100; }
    .stat-icon.users { background: #e3f2fd; color: #1565c0; }
    
    .stat-info h4 { font-size: 1.8rem; line-height: 1; margin-bottom: 5px; color: var(--maroon); }
    .stat-info p { color: var(--text-muted); font-weight: 600; font-size: 0.9rem; }

    .dashboard-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 25px; align-items: start; }
    
    .card-header { padding: 20px 25px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
    .card-header h3 { font-size: 1.1rem; color: var(--maroon); font-weight: 700; }

    .activity-list { display: flex; flex-direction: column; }
    .activity-item { display: flex; align-items: center; gap: 15px; padding: 18px 25px; border-bottom: 1px solid #f8f8f8; }
    .activity-item:last-child { border-bottom: none; }
    .act-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem; }
    .act-icon.add { background: #e8f5e9; color: #2e7d32; }
    .act-icon.borrow { background: #fff3e0; color: #e65100; }
    
    .act-info { flex: 1; }
    .act-info h5 { font-size: 0.95rem; font-weight: 600; margin-bottom: 2px; }
    .act-info p { font-size: 0.8rem; color: var(--text-muted); }
    
    @media (max-width: 1024px) {
        .dashboard-grid { grid-template-columns: 1fr; }
    }
</style>

<!-- Top Statistics -->
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-icon total">📚</div>
        <div class="stat-info">
            <h4><?php echo number_format($stats['total_books']); ?></h4>
            <p>سەرجەم ناونیشانەکان</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon available">✅</div>
        <div class="stat-info">
            <h4><?php echo number_format($stats['available_books']); ?></h4>
            <p>کتێبی بەردەست</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon borrowed">📤</div>
        <div class="stat-info">
            <h4><?php echo number_format($stats['borrowed_books']); ?></h4>
            <p>کتێبی خواستراو</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon users">👥</div>
        <div class="stat-info">
            <h4><?php echo number_format($stats['total_users']); ?></h4>
            <p>سەرجەم بەکارهێنەران</p>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Chart Column -->
    <div class="lib-card">
        <div class="card-header">
            <h3>📊 کتێبەکان بەپێی بەش</h3>
        </div>
        <div style="padding: 25px; height: 400px; display: flex; align-items: center; justify-content: center;">
            <canvas id="categoryChart"></canvas>
        </div>
    </div>

    <!-- Activity Column -->
    <div class="lib-card">
        <div class="card-header">
            <h3>🔔 دوایین چالاکییەکان</h3>
        </div>
        <div class="activity-list">
            <?php if (empty($activities)): ?>
                <div style="padding: 40px; text-align: center; color: var(--text-muted);">هیچ چالاکییەک نییە</div>
            <?php else: ?>
                <?php foreach ($activities as $act): ?>
                    <div class="activity-item">
                        <div class="act-icon <?php echo $act['type']; ?>">
                            <?php echo $act['type'] === 'add' ? '➕' : '📖'; ?>
                        </div>
                        <div class="act-info">
                            <h5><?php echo htmlspecialchars($act['item']); ?></h5>
                            <p><?php echo $act['type'] === 'add' ? 'کتێبی نوێ زیادکرا' : 'کتێبەکە خواسترا'; ?></p>
                        </div>
                        <div style="font-size: 0.75rem; color: #aaa; white-space: nowrap;">
                            <?php echo date('Y/m/d H:i', strtotime($act['time'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="assets/js/chart.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('categoryChart').getContext('2d');
        const catData = <?php echo json_encode($cat_stats); ?>;
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: catData.map(d => d.name),
                datasets: [{
                    data: catData.map(d => d.count),
                    backgroundColor: [
                        '#5a0202', '#C8A951', '#2e7d32', 
                        '#1565c0', '#e65100', '#6a1b9a'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        rtl: true,
                        labels: {
                            font: { family: 'Rabar022', size: 12 },
                            padding: 20
                        }
                    }
                },
                cutout: '70%'
            }
        });
    });
</script>

<?php require_once 'includes/layout_end.php'; ?>
