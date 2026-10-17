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

if (!$election) {
    header('Location: elections_dashboard.php');
    exit;
}

// Handle Vote Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_votes') {
    $votes = $_POST['votes'] ?? [];
    foreach ($votes as $candidate_id => $count) {
        $candidate_id = (int)$candidate_id;
        $count = (int)$count;
        $conn->query("UPDATE candidates SET votes_count = $count WHERE id = $candidate_id");
    }
    $_SESSION['msg'] = 'ئەنجامەکان بە سەرکەوتوویی تۆمارکران.';
    header("Location: elections_results.php");
    exit;
}

// Fetch Candidates grouped by position
$positions = [];
$res_p = $conn->query("SELECT * FROM election_positions WHERE election_id = {$election['id']} ORDER BY id ASC");
while ($p = $res_p->fetch_assoc()) {
    $c_list = [];
    $res_c = $conn->query("SELECT c.*, m.full_name, m.profile_image, m.member_code 
                           FROM candidates c 
                           JOIN members m ON c.member_id = m.id 
                           WHERE c.position_id = {$p['id']} 
                           ORDER BY c.votes_count DESC, m.full_name ASC");
    while ($c = $res_c->fetch_assoc()) $c_list[] = $c;
    $p['candidates'] = $c_list;
    $positions[] = $p;
}

$page_title = 'ئەنجامەکانی هەڵبژاردن';
$active_nav = 'elections';
$sys_module = 'elections';
require_once 'includes/layout.php';
?>

<div class="lib-container">
    <div class="lib-header-row">
        <div class="lib-title-group">
            <h1 class="lib-page-title">📊 ئەنجامەکانی کۆنگرە: <?php echo htmlspecialchars($election['title']); ?></h1>
            <p class="lib-subtitle">تۆمارکردن و بینینی ڕیزبەندی بەربژێرەکان بەپێی دەنگەکان</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="lib-btn lib-btn-secondary">🖨️ چاپی ئەنجامەکان</button>
            <a href="elections_dashboard.php" class="lib-btn">➡️ گەڕانەوە</a>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="update_votes">
        
        <?php foreach ($positions as $p): ?>
            <div class="lib-card" style="margin-bottom: 30px; border-top: 5px solid var(--gold);">
                <div class="card-header" style="background: #fcfcfc; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="color: #1a5653; font-family: 'Rabar013';">🏆 پۆستی: <?php echo htmlspecialchars($p['position_name']); ?></h3>
                    <span class="lib-badge lib-badge-gold"><?php echo count($p['candidates']); ?> بەربژێر</span>
                </div>
                <div class="lib-table-container">
                    <table class="lib-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">ڕیزبەندی</th>
                                <th style="width: 80px;">وێنە</th>
                                <th style="text-align: right;">ناوی بەربژێر</th>
                                <th>کۆد</th>
                                <th style="width: 200px;">ژمارەی دەنگەکان</th>
                                <th style="width: 100px;">دۆخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($p['candidates'])): ?>
                                <tr><td colspan="6" style="padding: 30px; color: #999;">هیچ بەربژێرێک بۆ ئەم پۆستە نییە.</td></tr>
                            <?php else: ?>
                                <?php 
                                $max_votes = 0;
                                if (!empty($p['candidates'])) $max_votes = $p['candidates'][0]['votes_count'];
                                ?>
                                <?php foreach ($p['candidates'] as $idx => $c): ?>
                                    <tr style="<?php echo ($idx === 0 && $c['votes_count'] > 0) ? 'background: rgba(200,169,81,0.05);' : ''; ?>">
                                        <td style="font-weight: bold; font-size: 1.2rem;"><?php echo $idx + 1; ?></td>
                                        <td>
                                            <div style="width: 50px; height: 50px; border-radius: 12px; overflow: hidden; border: 2px solid #eee; margin: 0 auto;">
                                                <img src="stream_image.php?path=<?php echo urlencode($c['profile_image'] ?: 'assets/imgae/user.png'); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                            </div>
                                        </td>
                                        <td style="text-align: right; font-weight: bold; font-size: 1.1rem;"><?php echo htmlspecialchars($c['full_name']); ?></td>
                                        <td><small style="color: #888;">#<?php echo htmlspecialchars($c['member_code']); ?></small></td>
                                        <td>
                                            <input type="number" name="votes[<?php echo $c['id']; ?>]" value="<?php echo $c['votes_count']; ?>" 
                                                   style="width: 120px; padding: 10px; border: 2px solid #ddd; border-radius: 10px; text-align: center; font-size: 1.2rem; font-weight: bold; font-family: 'Consolas', monospace;" 
                                                   min="0">
                                        </td>
                                        <td>
                                            <?php if ($idx === 0 && $c['votes_count'] > 0): ?>
                                                <span style="font-size: 1.5rem;" title="براوە">👑</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php foreach ($positions as $p): ?>
        <?php endforeach; ?>
        <?php endforeach; ?>

        <div style="position: sticky; bottom: 20px; z-index: 100; text-align: center;">
            <button type="submit" class="lib-btn lib-btn-primary" style="padding: 18px 60px; font-size: 1.3rem; box-shadow: 0 10px 30px rgba(26, 86, 83, 0.4); border-radius: 50px;">
                💾 خەزنکردنی هەموو ئەنجامەکان
            </button>
        </div>
    </form>
</div>

<style>
    @media print {
        .lib-sidebar, .lib-topbar, .lib-btn, input[type="submit"], .lib-subtitle { display: none !important; }
        .lib-main { margin: 0 !important; width: 100% !important; }
        .lib-page { padding: 0 !important; }
        input[type="number"] { border: none !important; background: transparent !important; pointer-events: none; }
        .lib-card { box-shadow: none !important; border: 1px solid #eee !important; }
    }
</style>

<?php require_once 'includes/layout_end.php'; ?>
