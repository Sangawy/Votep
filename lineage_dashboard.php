<?php
$sys_module = 'lineage';
require_once 'config.php';
$conn = getDB();
require_once 'includes/layout.php';

// Fetch statistics
$total_lineage = $conn->query("SELECT COUNT(*) FROM members")->fetch_row()[0];
$linked_members = $conn->query("SELECT COUNT(*) FROM members WHERE parent_id > 0")->fetch_row()[0];
$official_members = $conn->query("SELECT COUNT(*) FROM members WHERE is_official_member = 1")->fetch_row()[0];
$total_families = $conn->query("SELECT COUNT(*) FROM families")->fetch_row()[0];
$pending_requests = $conn->query("SELECT COUNT(*) FROM member_requests WHERE status = 'pending'")->fetch_row()[0] ?? 0;

$page_title = 'نەسەب و ڕەچەڵەک';
$active_nav = 'lineage_dashboard';
require_once 'includes/layout.php';
?>

<div class="dashboard-header">
    <div class="header-content">
        <h1>بەڕێوەبردنی نەسەب و ڕەچەڵەک</h1>
        <p>بەخێرهاتیت بۆ پانێڵی تایبەت بە پشکنین و تۆمارکردنی نەسەبی بنەماڵەکان و شجەرە.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: #e3f2fd; color: #1976d2;">📜</div>
        <div class="stat-info">
            <h3>کۆی تۆمارکراوانی نەسەب</h3>
            <p><?php echo $total_lineage; ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #e8f5e9; color: #388e3c;">🌳</div>
        <div class="stat-info">
            <h3>بەستراوەتەوە (شەجەرە)</h3>
            <p><?php echo $linked_members; ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #fdf2e9; color: #d35400;">🏛️</div>
        <div class="stat-info">
            <h3>ئەندامانی فەرمی ئیدارە</h3>
            <p><?php echo $official_members; ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #fff3e0; color: #f57c00;">🏡</div>
        <div class="stat-info">
            <h3>کۆی بنەماڵەکان</h3>
            <p><?php echo $total_families; ?></p>
        </div>
    </div>
</div>

<div class="action-grid" style="margin-top: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
    <a href="member_requests_confirm.php" class="portal-card" style="padding: 35px; text-decoration: none; color: inherit; position: relative; border: 2px solid <?php echo $pending_requests > 0 ? '#f59e0b' : 'transparent'; ?>;">
        <?php if ($pending_requests > 0): ?>
            <span style="position: absolute; top: 18px; left: 18px; background: #e11d48; color: #fff; padding: 4px 14px; border-radius: 20px; font-weight: 800; font-size: 0.8rem; box-shadow: 0 4px 12px rgba(225,29,72,0.35);">
                <?php echo $pending_requests; ?> داواکاری نوێ
            </span>
        <?php endif; ?>
        <div class="card-icon" style="font-size: 46px; margin-bottom: 16px;">📱</div>
        <h3 style="color: #1a5653;">پەسەندکردنی داواکارییەکان</h3>
        <p>پەسەندکردن و خستنەسەری ئەو منداڵانەی لەلایەن ئەندامانەوە لە ئەپەوە داوادەکرێن.</p>
    </a>

    <a href="lineage_explorer.php" class="portal-card" style="padding: 35px; text-decoration: none; color: inherit;">
        <div class="card-icon" style="font-size: 46px; margin-bottom: 16px;">🔍</div>
        <h3>ناسینەوەی ڕەچەڵەک</h3>
        <p>بەکاربردنی بزوێنەری زیرەک بۆ ناسینەوەی زنجیرەی ناوەکان و بەستنەوەیان بە داتابەیس.</p>
    </a>

    <a href="kinship_search.php" class="portal-card" style="padding: 35px; text-decoration: none; color: inherit;">
        <div class="card-icon" style="font-size: 46px; margin-bottom: 16px;">🧬</div>
        <h3>ناسینەوەی کەسی و خزمایەتی</h3>
        <p>گەڕان بەدوای تاک و دۆزینەوەی تەواوی تۆڕی خزمایەتی (برا، مام، منداڵ، ئامۆزا و هێڵی باوباپیران).</p>
    </a>
    
    <a href="families_manage.php" class="portal-card" style="padding: 35px; text-decoration: none; color: inherit;">
        <div class="card-icon" style="font-size: 46px; margin-bottom: 16px;">🏘️</div>
        <h3>بەڕێوەبردنی بنەماڵەکان</h3>
        <p>تۆمارکردن و دەستکاریکردنی زانیاری بنەماڵەکان و زنجیرە جێگیرەکانیان.</p>
    </a>
    
    <a href="lineage_reports.php" class="portal-card" style="padding: 35px; text-decoration: none; color: inherit;">
        <div class="card-icon" style="font-size: 46px; margin-bottom: 16px;">🖨️</div>
        <h3>ڕاپۆرت و بڕوانامە</h3>
        <p>دروستکردن و چاپکردنی بڕوانامەی فەرمی نەسەب (شەجەرە) بۆ ئەندامان.</p>
    </a>
</div>

<style>
.dashboard-header {
    background: #fff;
    padding: 30px;
    border-radius: 20px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    border-right: 5px solid var(--maroon);
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}
.stat-card {
    background: #fff;
    padding: 25px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
}
.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.stat-info h3 {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 5px;
}
.stat-info p {
    font-size: 1.5rem;
    font-weight: 800;
    color: #222;
}
</style>

<?php require_once 'includes/layout_end.php'; ?>
