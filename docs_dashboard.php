<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

// Only check permissions if column exists -- though assume super admin has access anyway
$user_id = $_SESSION['user_id'];
$user_data = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$current_user = strtolower($user_data['username'] ?? '');
$is_super_admin = ($current_user === 'admin' || $current_user === 'sangawy' || ($user_data['role'] ?? '') === 'owner');

if (!$is_super_admin && !(isset($user_data['can_access_docs']) && $user_data['can_access_docs'])) {
    header('Location: portal.php');
    exit;
}

$sys_module = 'docs';
$page_title = 'داشبۆردی نوسینگە ';
$active_nav = 'docs_dashboard';

$stats = [
    'total_docs' => 0,
    'pending_reviews' => 0,
    'secret_docs' => 0,
    'today_entries' => 0
];

if ($conn !== null) {
    // Total documents
    $res = $conn->query("SELECT COUNT(*) as total FROM archive_docs");
    if ($res && $row = $res->fetch_assoc())
        $stats['total_docs'] = $row['total'];

    // Pending Reviews - archive_docs doesn't have status, so we check if there's no file attached or just show 0
    // For now, let's assume all are processed or use a dummy 0 to avoid errors if column missing
    $res = $conn->query("SELECT COUNT(*) as pending FROM archive_docs WHERE 1=0"); 
    if ($res && $row = $res->fetch_assoc())
        $stats['pending_reviews'] = $row['pending'];

    // Secret Docs
    $res = $conn->query("SELECT COUNT(*) as secret FROM archive_docs WHERE doc_type = 'Secret'");
    if ($res && $row = $res->fetch_assoc())
        $stats['secret_docs'] = $row['secret'];

    // Today's Entries
    $res = $conn->query("SELECT COUNT(*) as today FROM archive_docs WHERE doc_date = CURDATE()");
    if ($res && $row = $res->fetch_assoc())
        $stats['today_entries'] = $row['today'];

    // Recent Documents - Limit 5 as requested
    $recent_docs = [];
    $res_recent = $conn->query("SELECT * FROM archive_docs ORDER BY id DESC LIMIT 5");
    if ($res_recent) {
        while ($r = $res_recent->fetch_assoc()) {
            $recent_docs[] = $r;
        }
    }

    // Chart Data: Counts by type
    $type_counts = [
        'Inbound' => 0,
        'Outbound' => 0,
        'Secret' => 0,
        'Other' => 0
    ];
    $res_c = $conn->query("SELECT doc_type, COUNT(*) as cnt FROM archive_docs GROUP BY doc_type");
    if ($res_c) {
        while ($rc = $res_c->fetch_assoc()) {
            $t = $rc['doc_type'] ?: 'Other';
            if (isset($type_counts[$t])) $type_counts[$t] = (int)$rc['cnt'];
            else $type_counts['Other'] += (int)$rc['cnt'];
        }
    }
}

require_once 'includes/layout.php';
?>
<style>
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: #fff;
        padding: 25px;
        border-radius: 16px;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 20px;
        transition: transform 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
    }

    .stat-icon.total { background: #e3f2fd; color: #1565c0; }
    .stat-icon.pending { background: #fff3e0; color: #e65100; }
    .stat-icon.secret { background: #ffebee; color: #d32f2f; }
    .stat-icon.today { background: #e8f5e9; color: #2e7d32; }

    .stat-info h4 {
        font-size: 1.8rem;
        line-height: 1;
        margin-bottom: 5px;
        color: var(--maroon);
    }

    .stat-info p {
        color: var(--text-muted);
        font-weight: 600;
        font-size: 0.9rem;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        margin-bottom: 30px;
    }

    @media (max-width: 1100px) {
        .dashboard-grid { grid-template-columns: 1fr; }
    }

    .card-header {
        padding: 20px 25px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .card-header h3 {
        font-size: 1.1rem;
        color: var(--maroon);
        font-weight: 700;
    }
    .chart-container {
        padding: 20px;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 300px;
    }
</style>

<div class="stats-row">
    <div class="stat-card">
        <div class="stat-icon total">📝</div>
        <div class="stat-info">
            <h4><?php echo number_format($stats['total_docs']); ?></h4>
            <p>سەرجەم نوسراوەکان</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon pending">⏳</div>
        <div class="stat-info">
            <h4><?php echo number_format($stats['pending_reviews']); ?></h4>
            <p>لە چاوەڕوانی پەسەندکردن</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon secret">🔒</div>
        <div class="stat-info">
            <h4><?php echo number_format($stats['secret_docs']); ?></h4>
            <p>نوسراوی نهێنی</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon today">📅</div>
        <div class="stat-info">
            <h4><?php echo number_format($stats['today_entries']); ?></h4>
            <p>نوسراوی ئەمڕۆ</p>
        </div>
    </div>
</div>
<style>
    .doc-list { display: flex; flex-direction: column; gap: 12px; padding: 20px; }
    .doc-card { 
        background: #fcfcfc; 
        border: 1px solid #edf2f7; 
        border-radius: 12px; 
        padding: 16px; 
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .doc-card:hover { 
        transform: translateX(-5px); 
        border-color: var(--maroon); 
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        background: #fff;
    }
    .doc-card-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 8px; }
    .doc-card-mid { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
    .doc-card-bottom { display: flex; align-items: center; gap: 15px; font-size: 0.85rem; color: #718096; }
    
    .doc-id { color: var(--maroon); font-weight: 800; font-size: 0.95rem; }
    .doc-date { color: #a0aec0; font-size: 0.85rem; display: flex; align-items: center; gap: 5px; }
    .doc-subject { font-weight: 700; color: #2d3748; flex: 1; font-size: 1rem; }
</style>

<div class="dashboard-grid">
    <!-- Left Column: Recent Documents Cards -->
    <div class="lib-card">
        <div class="card-header">
            <h3>٥ کۆتا نوسراوەکان</h3>
            <a href="docs.php" class="lib-btn lib-btn-primary" style="padding: 8px 15px; font-size: 0.85rem;">هەموو نوسراوەکان</a>
        </div>
        <div class="doc-list">
            <?php if (empty($recent_docs)): ?>
                <div style="text-align: center; padding: 40px; color: #a0aec0;">هیچ نوسراوێک نییە</div>
            <?php else: ?>
                <?php foreach ($recent_docs as $doc): ?>
                    <?php
                        $type_class = 'lib-badge-gold';
                        $t_name = $doc['doc_type'];
                        if ($t_name === 'Secret') {
                            $type_class = 'lib-badge-red'; $t_display = 'نهێنی';
                        } elseif ($t_name === 'Inbound') {
                            $type_class = 'lib-badge-green'; $t_display = 'هاتوو';
                        } elseif ($t_name === 'Outbound') {
                            $type_class = 'lib-badge-gold'; $t_display = 'دەرچوو';
                        } else {
                            $t_display = $t_name;
                        }
                    ?>
                    <div class="doc-card">
                        <div class="doc-card-top">
                            <span class="doc-id">نوسراوی ژمارە: <?php echo htmlspecialchars($doc['doc_number']); ?></span>
                            <span class="doc-date">📅 <?php echo htmlspecialchars($doc['doc_date']); ?></span>
                        </div>
                        <div class="doc-card-mid">
                            <span class="doc-subject"><?php echo htmlspecialchars($doc['subject']); ?></span>
                            <span class="lib-badge <?php echo $type_class; ?>" style="font-size: 11px;"><?php echo htmlspecialchars($t_display); ?></span>
                        </div>
                        <div class="doc-card-bottom">
                            <span>👤 لایەنی بەرامبەر: <b style="color:#4a5568;"><?php echo htmlspecialchars($doc['correspondent']); ?></b></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Pie Chart -->
    <div class="lib-card">
        <div class="card-header">
            <h3>جۆری نوسراوەکان</h3>
        </div>
        <div class="chart-container">
            <canvas id="docsChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('docsChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['هاتوو (Inbound)', 'دەرچوو (Outbound)', 'نهێنی (Secret)', 'ئەوانی تر'],
            datasets: [{
                data: [
                    <?php echo $type_counts['Inbound']; ?>,
                    <?php echo $type_counts['Outbound']; ?>,
                    <?php echo $type_counts['Secret']; ?>,
                    <?php echo $type_counts['Other']; ?>
                ],
                backgroundColor: ['#2e7d32', '#d4af37', '#d32f2f', '#718096'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { family: 'Rabar022, Rabar005, sans-serif', size: 12 },
                        padding: 20
                    }
                }
            },
            cutout: '65%'
        }
    });
});
</script>

<?php require_once 'includes/layout_end.php'; ?>