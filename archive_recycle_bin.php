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
$is_owner = (($user_data['role'] ?? '') === 'owner');

if (!$is_owner) {
    die("تەنها ئۆنەر دەتوانێت بچێتە ئەم بەشە.");
}

$page_title = "ڕیسایکڵ بین (تەنەکەی خۆڵ)";
$active_nav = "archive_recycle";
$sys_module = "archive";

include 'includes/layout.php';
?>

<div class="lib-card" style="margin: 0;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; background: #eceff1;">
        <h3>♻️ ڕیسایکڵ بین - چالاکییە سڕاوەکان</h3>
        <a href="archive_list.php" class="lib-btn lib-btn-primary">گەرانەوە بۆ لیستی سەرەکی</a>
    </div>

    <div style="padding: 25px;">
        <?php if (isset($_GET['success'])): ?>
            <?php if ($_GET['success'] == 'restored'): ?>
                <div class="lib-toast success" style="position:relative; transform:none; margin-bottom:20px;">چالاکیەکە بە سەرکەوتوویی گەڕێنرایەوە.</div>
            <?php elseif ($_GET['success'] == 'permanent_deleted'): ?>
                <div class="lib-toast success" style="position:relative; transform:none; margin-bottom:20px; background: #d32f2f;">چالاکیەکە بە هەمیشەیی سڕایەوە.</div>
            <?php endif; ?>
        <?php endif; ?>

        <div style="overflow-x: auto;">
            <table class="lib-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">ڕیز</th>
                        <th>ناوی چالاکی</th>
                        <th>کاتی سڕینەوە (کاتی چالاکی)</th>
                        <th>شوێن</th>
                        <th>بەرپرسی</th>
                        <th>کردار</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $res = $conn->query("SELECT * FROM activities WHERE is_deleted = 1 ORDER BY id DESC");
                    if ($res && $res->num_rows > 0):
                        $i = 1;
                        while ($row = $res->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td style="font-weight: bold;"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['activity_date']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td><?php echo htmlspecialchars($row['implemented_by']); ?></td>
                                <td style="text-align:center; display: flex; gap: 8px; justify-content: center;">
                                    <a href="archive_restore.php?id=<?php echo $row['id']; ?>" class="lib-btn lib-btn-success"
                                        style="padding: 5px 12px; font-size: 0.85rem;">🔄 گەڕاندنەوە</a>
                                    
                                    <button type="button" class="lib-btn lib-btn-danger"
                                        style="padding: 5px 12px; font-size: 0.85rem;"
                                        onclick="confirmPermanentDelete(<?php echo $row['id']; ?>)">🗑️ سڕینەوەی هەمیشەیی</button>
                                </td>
                            </tr>
                        <?php
                        endwhile;
                    else:
                        echo '<tr><td colspan="6" style="text-align:center; padding: 40px; color: #999;">هیچ چالاکییەک لە ڕیسایکڵ بین نییە.</td></tr>';
                    endif;
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function confirmPermanentDelete(id) {
    if (confirm('ئایا دڵنیایت لە سڕینەوەی هەمیشەیی؟ ئەم چالاکییە و وێنەکانی بە تەواوی لە سیستەمەکە لادەبرێن و ناگەڕێنرێنەوە.')) {
        window.location.href = 'archive_delete.php?id=' + id + '&permanent=1';
    }
}
</script>

<?php include 'includes/layout_end.php'; ?>
