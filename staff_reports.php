<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

require_once 'config.php';
$conn = getDB();

$page_title = 'ڕاپۆرت و فۆرمەکان';
$active_nav = 'staff_reports';
$sys_module = 'nusinga';

require_once 'includes/layout.php';
?>

<style>
    .reports-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
    }
    .report-card {
        background: #fff;
        border-radius: 16px;
        padding: 30px;
        text-align: center;
        border: 1px solid var(--border);
        transition: 0.3s;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px;
        box-shadow: var(--shadow-sm);
    }
    .report-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
        border-color: var(--maroon);
    }
    .report-icon {
        width: 70px;
        height: 70px;
        background: rgba(90,2,2,0.05);
        color: var(--maroon);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
    }
    .report-card h3 { margin: 0; color: var(--maroon); font-size: 1.2rem; }
    .report-card p { color: #666; font-size: 0.9rem; margin: 0; line-height: 1.5; }
</style>

<div class="reports-grid">
    <!-- Individual Reports (Redirect to Staff List or Selection) -->
    <div class="report-card">
        <div class="report-icon">📜</div>
        <h3>خشتەی ڕەوشت</h3>
        <p>دروستکردنی خشتەی ڕەوشت و سزاکان بۆ هەر ئەفسەرێک بە شێوەی تاکەکەسی.</p>
        <a href="staff_list.php" class="lib-btn lib-btn-primary" style="width: 100%; justify-content: center;">هەڵبژاردنی ئەفسەر</a>
    </div>

    <!-- Unit Wide Reports -->
    <div class="report-card">
        <div class="report-icon">📝</div>
        <h3>فەرمانەکانی بەشی ٢</h3>
        <p>تۆمارکردن و چاپکردنی فەرمانی ڕۆژانەی یەکە (جوڵە، مۆڵەت، سزادان...).</p>
        <button class="lib-btn lib-btn-primary" style="width: 100%; justify-content: center;" onclick="alert('ئەم بەشە لە وەشانی داهاتوودا چالاک دەبێت')">کردنەوەی بەش</button>
    </div>

    <div class="report-card">
        <div class="report-icon">📉</div>
        <h3>مستند ١٠٢</h3>
        <p>فۆرمی فەرمی مستند ١٠٢ بۆ بەڕێوەبردنی زممە و مەوجوداتی یەکە.</p>
        <button class="lib-btn lib-btn-primary" style="width: 100%; justify-content: center;" onclick="alert('ئەم بەشە لە وەشانی داهاتوودا چالاک دەبێت')">دروستکردنی فۆرم</button>
    </div>

    <div class="report-card">
        <div class="report-icon">📊</div>
        <h3>ئاماری گشتی</h3>
        <p>بینینی ئاماری گشتی ئەفسەران بەپێی پلە، تەمەن، و یەکەکان.</p>
        <button class="lib-btn lib-btn-primary" style="width: 100%; justify-content: center;" onclick="alert('ئەم بەشە لە گەشەپێداندایە')">بینینی ئامار</button>
    </div>
</div>

<?php require_once 'includes/layout_end.php'; ?>
