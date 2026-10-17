<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

// Fetch active election
$election = $conn->query("SELECT * FROM elections WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetch_assoc();

$page_title = 'پانێڵی هەڵبژاردن';
$active_nav = 'elections';
$sys_module = 'elections';
require_once 'includes/layout.php';
?>

<div class="lib-container">
    <div class="lib-header-row">
        <div class="lib-title-group">
            <h1 class="lib-page-title">🗳️ داشبۆردی هەڵبژاردن</h1>
            <p class="lib-subtitle">بەڕێوەبردنی بەربژێرەکان و پڕۆسەی دەنگدان</p>
        </div>
    </div>

    <?php if (!$election): ?>
        <div class="lib-card" style="text-align: center; padding: 60px 40px; border-radius: 20px; border: 2px dashed #ddd; background: #fafafa;">
            <div style="font-size: 5rem; margin-bottom: 25px;">🏛️</div>
            <h2 style="color: #333; margin-bottom: 15px;">هیچ کۆنگرەیەکی چالاک نییە</h2>
            <p style="color: #777; margin-bottom: 30px; font-size: 1.1rem;">تکایە سەرەتا کۆنگرەیەک زیاد بکە یان کۆنگرەیەکی هەبوو "چالاک" بکە تاوەکو بتوانیت لێرە ئامارەکان ببینی.</p>
            <a href="elections_manage.php" class="lib-btn lib-btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">🏛️ بەڕێوەبردنی کۆنگرەکان</a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <!-- Stats Cards -->
            <div class="lib-card" style="display: flex; align-items: center; gap: 20px; padding: 25px;">
                <div style="width: 60px; height: 60px; border-radius: 15px; background: rgba(200, 169, 81, 0.1); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    👤
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.8rem;"><?php 
                        $res = $conn->query("SELECT COUNT(*) as cnt FROM candidates WHERE election_id = {$election['id']}");
                        echo $res->fetch_assoc()['cnt'];
                    ?></h3>
                    <p style="margin: 0; color: #666;">کۆی بەربژێرەکان</p>
                </div>
            </div>

            <div class="lib-card" style="display: flex; align-items: center; gap: 20px; padding: 25px;">
                <div style="width: 60px; height: 60px; border-radius: 15px; background: rgba(39, 174, 96, 0.1); color: #27ae60; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    📊
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.8rem;"><?php 
                        $res = $conn->query("SELECT COUNT(*) as cnt FROM election_positions WHERE election_id = {$election['id']}");
                        echo $res->fetch_assoc()['cnt'];
                    ?></h3>
                    <p style="margin: 0; color: #666;">پۆستەکان</p>
                </div>
            </div>

            <div class="lib-card" style="display: flex; align-items: center; gap: 20px; padding: 25px;">
                <div style="width: 60px; height: 60px; border-radius: 15px; background: rgba(52, 152, 219, 0.1); color: #3498db; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                    📅
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 1.1rem;"><?php echo htmlspecialchars($election['title']); ?></h4>
                    <p style="margin: 0; color: #666;">ناونیشانی هەڵبژاردن</p>
                </div>
            </div>
        </div>

        <div class="lib-grid-2" style="grid-template-columns: 2fr 1fr;">
            <!-- Main Content Area -->
            <div class="lib-card" style="padding: 30px;">
                <h3 style="border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px;">📋 بەشە سەرەکییەکان</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <a href="elections_manage.php" class="module-link-card">
                        <div class="icon">🏛️</div>
                        <div class="details">
                            <h4>کۆنگرەکان</h4>
                            <p>بەڕێوەبردن و زیادکردنی کۆنگرەکان</p>
                        </div>
                    </a>
                    <a href="elections_candidates.php" class="module-link-card">
                        <div class="icon">🪪</div>
                        <div class="details">
                            <h4>بەربژێرەکان</h4>
                            <p>تۆمارکردن و دیاریکردنی بەربژێر بۆ پۆستەکان</p>
                        </div>
                    </a>
                    <a href="elections_ballot.php" class="module-link-card">
                        <div class="icon">📄</div>
                        <div class="details">
                            <h4>فۆڕمی دەنگدان</h4>
                            <p>دروستکردنی فۆڕمی دەنگدان بۆ چاپکردن</p>
                        </div>
                    </a>
                    <a href="elections_results.php" class="module-link-card">
                        <div class="icon">📊</div>
                        <div class="details">
                            <h4>ئەنجامەکان</h4>
                            <p>تۆمارکردن و بینینی ئەنجامی دەنگدان</p>
                        </div>
                    </a>
                    <a href="#" class="module-link-card disabled">
                        <div class="icon">⚙️</div>
                        <div class="details">
                            <h4>ڕێکخستن</h4>
                            <p>دیاریکردنی پۆستەکان و وادەی هەڵبژاردن</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Side Info Area -->
            <div class="lib-card" style="padding: 25px; background: #fafafa;">
                <h3 style="margin-bottom: 20px;">ℹ️ زانیاری هەڵبژاردن</h3>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 15px; display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span style="color: #666;">بەرواری دەنگدان:</span>
                        <span style="font-weight: bold;"><?php echo $election['election_date'] ?: 'دیاری نەکراوە'; ?></span>
                    </li>
                    <li style="margin-bottom: 15px; display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                        <span style="color: #666;">دۆخی هەڵبژاردن:</span>
                        <span class="lib-badge lib-badge-success">چالاکە</span>
                    </li>
                </ul>
                <div style="margin-top: 30px; padding: 15px; background: rgba(200, 169, 81, 0.05); border-radius: 12px; border: 1px dashed var(--gold);">
                    <p style="margin: 0; font-size: 0.9rem; line-height: 1.6; color: #555;">
                        💡 
                        تێبینی: لێرەوە دەتوانیت سەرجەم بەربژێرەکان بۆ کۆنگرەی داهاتوو دیاری بکەیت. هەر ئەندامێک دەتوانێت بۆ یەک پۆست بەربژێر بێت.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .module-link-card {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 20px;
        background: #fff;
        border: 1px solid #eee;
        border-radius: 15px;
        text-decoration: none;
        color: inherit;
        transition: all 0.3s ease;
    }
    .module-link-card:hover:not(.disabled) {
        border-color: var(--gold);
        background: rgba(200, 169, 81, 0.02);
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    .module-link-card.disabled {
        opacity: 0.6;
        cursor: not-allowed;
        background: #fcfcfc;
    }
    .module-link-card .icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: var(--primary);
        transition: 0.3s;
    }
    .module-link-card:hover:not(.disabled) .icon {
        background: var(--primary);
        color: #fff;
    }
    .module-link-card h4 {
        margin: 0 0 5px 0;
        font-size: 1.1rem;
    }
    .module-link-card p {
        margin: 0;
        font-size: 0.85rem;
        color: #777;
    }
</style>

<?php require_once 'includes/layout_end.php'; ?>
