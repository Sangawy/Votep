<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

require_once 'config.php';
$conn = getDB();

// Ensure base_lineage column exists in settings table
$checkCols = $conn->query("SHOW COLUMNS FROM settings LIKE 'base_lineage'");
if ($checkCols && $checkCols->num_rows == 0) {
    $conn->query("ALTER TABLE settings ADD COLUMN base_lineage TEXT AFTER description");
}

$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lineage_settings'])) {
    $base_lineage = trim($_POST['base_lineage'] ?? '');
    $gemini_api_key = trim($_POST['gemini_api_key'] ?? '');
    
    $stmt = $conn->prepare("UPDATE settings SET base_lineage = ?, gemini_api_key = ? WHERE id = 1");
    if ($stmt) {
        $stmt->bind_param("ss", $base_lineage, $gemini_api_key);
        if ($stmt->execute()) {
            $message = 'ڕێکخستنەکان و کلیلی ژیری دەستکرد بە سەرکەوتوویی پاشەکەوت کران ✅';
            $messageType = 'success';
        } else {
            $message = 'هەڵەیەک ڕوویدا لە کاتی پاشەکەوتکردن: ' . $conn->error;
            $messageType = 'error';
        }
        $stmt->close();
    }
}

// Fetch current settings
$res = $conn->query("SELECT * FROM settings WHERE id = 1");
$settings = $res->fetch_assoc();

$page_title = 'ڕێکخستنەکانی نەسەب و ژیری دەستکرد';
$active_nav = 'lineage_settings';
$sys_module = 'lineage';

require_once 'includes/layout.php';
?>

<div class="lib-card" style="max-width: 1000px; margin: 0 auto;">
    <div class="card-header">
        <h3>⚙️ ڕێکخستنەکانی سیستەمی نەسەب و ژیری دەستکرد</h3>
    </div>
    <div style="padding: 30px;">
        <?php if ($message): ?>
            <div class="lib-toast <?php echo $messageType; ?>" style="position: static; margin-bottom: 25px; transform: none; width: 100%;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="lib-field">
                <label style="font-size: 1.1rem; color: var(--maroon); margin-bottom: 12px; display: block;">📜 ڕەچەڵەکی بنچینەیی (Base Lineage)</label>
                <p style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">
                    هەموو ئەو بنەماڵە و ناوانەی زیادیان دەکەین لە کۆتاییدا دەبێت بگەرێنەوە سەر ئەم نەسەبەی لێرە دایدەنێیت. 
                    ئەمە وەک "ڕەگی" سەرەکی هەموو شەجەرەکان کار دەکات.
                </p>
                <textarea name="base_lineage" rows="12" class="lib-input" style="line-height: 1.6; font-size: 1.1rem; padding: 20px;" placeholder="نەسەبی بنچینەیی لێرە بنووسە..."><?php echo htmlspecialchars($settings['base_lineage'] ?? ''); ?></textarea>
            </div>

            <div class="lib-field" style="margin-top: 25px; background: #fafafa; padding: 20px; border-radius: 12px; border: 1px solid #eee;">
                <label style="font-size: 1.05rem; color: #1976d2; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                    <span>✨ کلیلی زیرەکی دەستکرد (Google Gemini API Key)</span>
                </label>
                <p style="color: #666; font-size: 0.85rem; margin-bottom: 12px; line-height: 1.6;">
                    بۆ چالاککردنی شیکاری مێژوویی و ڕاوێژکاری ئەنساب بە مۆدێلی Google Gemini. دەتوانیت لە Google AI Studio کلیلی بەخۆڕایی (Free Tier) وەربگریت و لێرە پاشەکەوتی بکەیت.
                </p>
                <input type="text" name="gemini_api_key" class="lib-input" style="direction: ltr; font-family: monospace; font-size: 0.95rem;" value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>" placeholder="AQ.Ab8RN... یان AIzaSy...">
            </div>

            <div style="margin-top: 20px; background: #f0f7ff; padding: 15px; border-radius: 10px; border: 1px solid #d0e3ff;">
                <h4 style="color: #004085; margin-bottom: 10px; font-size: 0.95rem;">🔍 پشکنینی پاشەکەوت (Parsed Chain):</h4>
                <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <?php 
                    require_once 'lineage_engine.php';
                    $chain = getBaseChain($conn);
                    if (empty($chain)) {
                        echo '<span style="color: #666;">هیچ زنجیرەیەک نییە</span>';
                    } else {
                        foreach ($chain as $idx => $p) {
                            echo '<span style="background: #fff; border: 1px solid #b8daff; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem;">' . ($idx+1) . '. ' . htmlspecialchars($p) . '</span>';
                        }
                    }
                    ?>
                </div>
            </div>

            <div style="margin-top: 30px; display: flex; gap: 15px;">
                <button type="submit" name="save_lineage_settings" class="lib-btn lib-btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">
                    💾 پاشەکەوتکردنی ڕێکخستنەکان
                </button>
                <a href="lineage_dashboard.php" class="lib-btn lib-btn-gold" style="padding: 15px 40px; font-size: 1.1rem;">
                    گەڕانەوە بۆ داشبۆرد
                </a>
            </div>
        </form>

        <div style="margin-top: 50px; background: #fff9f9; padding: 25px; border-radius: 15px; border: 1px dashed var(--maroon);">
            <h4 style="color: var(--maroon); margin-bottom: 15px;">💡 تێبینی گرنگ:</h4>
            <ul style="color: #555; font-size: 0.95rem; margin-right: 20px; line-height: 1.8;">
                <li>سیستەمەکە بە شێوەیەکی ئۆتۆماتیکی هەوڵ دەدات زنجیرە نوێیەکان ببەستێتەوە بە دوا کەسی ناو ئەم ڕەچەڵەکەیە.</li>
                <li>بەکارهێنانی نازناوەکان (وەک سەیید، مەلا، شێخ) کێشە نییە، سیستەمەکە خۆی پشتگوێیان دەخات لە کاتی بەراوردکاریدا.</li>
                <li>ئەگەر نەسەبێکی نوێ هیچ خاڵێکی بەستنەوەی لە داتابەیسدا نەبوو، سیستەمەکە پێشنیاری بەستنەوەی بەم ڕەچەڵەکی بنچینەییەوە دەکات.</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once 'includes/layout_end.php'; ?>
