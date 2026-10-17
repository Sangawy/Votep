<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$election_id = (int)($_GET['id'] ?? 0);
$election = $conn->query("SELECT * FROM elections WHERE id = $election_id")->fetch_assoc();

if (!$election) {
    header('Location: elections_manage.php');
    exit;
}

// Fetch members of this congress
$query = "SELECT m.*, f.family_name, c.votes_count 
          FROM congress_members cm 
          JOIN members m ON cm.member_id = m.id 
          LEFT JOIN families f ON m.family_id = f.id 
          LEFT JOIN candidates c ON (c.member_id = m.id AND c.election_id = cm.election_id)
          WHERE cm.election_id = $election_id 
          ORDER BY (c.votes_count IS NOT NULL) DESC, c.votes_count DESC, m.full_name ASC";
$members_res = $conn->query($query);
$member_count = $members_res ? $members_res->num_rows : 0;

$page_title = 'ئەندامانی ' . $election['title'];
$active_nav = 'elections';
$sys_module = 'elections';
require_once 'includes/layout.php';
?>

<div class="lib-container">
    <div class="lib-header-row">
        <div class="lib-title-group">
            <h1 class="lib-page-title">👥 ئەندامانی کۆنگرە: <?php echo htmlspecialchars($election['title']); ?></h1>
            <p class="lib-subtitle"><?php echo $member_count . " ئەندام بەشدارن لەم کۆنگرەیەدا"; ?></p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" class="lib-btn lib-btn-secondary">🖨️ چاپکردنی لیست</button>
            <a href="elections_manage.php" class="lib-btn">➡️ گەڕانەوە</a>
        </div>
    </div>

    <div class="lib-card">
        <div class="lib-table-wrapper">
            <table class="lib-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">وێنە</th>
                        <th style="width: 100px;">کۆدی ئەندام</th>
                        <th style="text-align: right;">ناوی تەواو</th>
                        <th>پلە / پایە</th>
                        <th>بنەماڵە</th>
                        <th style="width: 130px;">مۆبایل</th>
                        <th style="width: 110px; text-align: center;">دەنگەکان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($member_count === 0): ?>
                        <tr><td colspan="7" style="padding: 50px; text-align: center; color: #999;">هیچ ئەندامێک بۆ ئەم کۆنگرەیە تۆمار نەکراوە.</td></tr>
                    <?php else: ?>
                        <?php while($m = $members_res->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div style="width: 45px; height: 45px; border-radius: 50%; overflow: hidden; margin: 0 auto; border: 2px solid var(--gold);">
                                        <img src="stream_image.php?path=<?php echo urlencode(!empty($m['profile_image']) ? $m['profile_image'] : 'assets/imgae/user.png'); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                </td>
                                <td><span class="lib-badge lib-badge-primary"><?php echo htmlspecialchars($m['member_code'] ?? ''); ?></span></td>
                                <td style="text-align: right; font-weight: bold;"><?php echo htmlspecialchars($m['full_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($m['member_rank'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(!empty($m['family_name']) ? $m['family_name'] : '-'); ?></td>
                                <td style="direction: ltr;"><?php echo htmlspecialchars($m['phone'] ?? ''); ?></td>
                                <td style="text-align: center;">
                                    <?php if (isset($m['votes_count']) && $m['votes_count'] !== null): ?>
                                        <span class="lib-badge lib-badge-gold" style="font-weight: bold; font-size: 1rem;"><?php echo (int)$m['votes_count']; ?></span>
                                    <?php else: ?>
                                        <span style="color: #bbb;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    .lib-sidebar, .lib-topbar, .lib-btn { display: none !important; }
    .lib-main { margin: 0 !important; width: 100% !important; }
}
</style>

<?php require_once 'includes/layout_end.php'; ?>
