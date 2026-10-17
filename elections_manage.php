<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

// Auto-fix table schema if needed
$conn->query("ALTER TABLE elections MODIFY COLUMN status VARCHAR(20) DEFAULT 'inactive'");
$check_loc = $conn->query("SHOW COLUMNS FROM elections LIKE 'location'");
if ($check_loc->num_rows == 0) {
    $conn->query("ALTER TABLE elections ADD COLUMN location VARCHAR(255) AFTER election_date");
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_election') {
        $title = $conn->real_escape_string($_POST['title']);
        $date = $conn->real_escape_string($_POST['election_date']);
        $location = $conn->real_escape_string($_POST['location']);
        $description = $conn->real_escape_string($_POST['description']);
        $status = 'inactive';
        
        $sql = "INSERT INTO elections (title, election_date, location, description, status) 
                VALUES ('$title', '$date', '$location', '$description', '$status')";
        if ($conn->query($sql)) {
            $_SESSION['msg'] = 'کۆنگرە بە سەرکەوتوویی زیادکرا.';
        } else {
            $_SESSION['error'] = 'کێشەیەک لە زیادکردنەکەدا هەیە: ' . $conn->error;
        }
    } elseif ($_POST['action'] === 'set_active') {
        $id = (int)$_POST['election_id'];
        // Deactivate all others
        $conn->query("UPDATE elections SET status = 'inactive'");
        // Activate this one
        $conn->query("UPDATE elections SET status = 'active' WHERE id = $id");
        $_SESSION['msg'] = 'کۆنگرەی دیاریکراو چالاک کرا.';
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)$_POST['election_id'];
        $conn->query("DELETE FROM elections WHERE id = $id");
        $_SESSION['msg'] = 'کۆنگرە سڕایەوە.';
    }
    header('Location: elections_manage.php');
    exit;
}

// Fetch all elections - ASC order so Congress 1 is at the top
$elections = [];
$res = $conn->query("SELECT * FROM elections ORDER BY election_date ASC, id ASC");
while ($row = $res->fetch_assoc()) $elections[] = $row;

$page_title = 'بەڕێوەبردنی کۆنگرەکان';
$active_nav = 'elections';
$sys_module = 'elections';
require_once 'includes/layout.php';
?>

<script>
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.setProperty('display', 'flex', 'important');
        modal.style.setProperty('visibility', 'visible', 'important');
        modal.style.setProperty('opacity', '1', 'important');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}
</script>

<div class="lib-container">
    <!-- Add Election Modal -->
    <div id="X_MODAL_TEST" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; backdrop-filter: blur(8px);">
        <div style="background: #fff; border-radius: 24px; width: 95%; max-width: 550px; box-shadow: 0 40px 100px rgba(0,0,0,0.5); overflow: hidden; position: relative; animation: modalPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); direction: rtl;">
            <div style="padding: 25px 35px; background: #f8f9fa; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-family: 'Rabar022'; color: #1a5653; font-size: 1.5rem;">🏛️ زیادکردنی کۆنگرەی نوێ</h3>
                <button type="button" onclick="closeModal('X_MODAL_TEST')" style="background: none; border: none; font-size: 2.2rem; cursor: pointer; color: #bbb; line-height: 1;">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_election">
                <div style="padding: 35px;">
                    <div style="margin-bottom: 25px;">
                        <label style="display: block; margin-bottom: 10px; font-weight: bold; font-family: 'Rabar022'; color: #333;">ناونیشانی کۆنگرە <span style="color: red;">*</span></label>
                        <input type="text" name="title" style="width: 100%; padding: 15px; border: 2px solid #eee; border-radius: 14px; font-family: 'Rabar022'; font-size: 1.1rem;" placeholder="بۆ نمونە: کۆنگرەی ٣" required>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px;">
                        <div>
                            <label style="display: block; margin-bottom: 10px; font-weight: bold; font-family: 'Rabar022'; color: #333;">بەرواری کۆنگرە</label>
                            <input type="date" name="election_date" style="width: 100%; padding: 15px; border: 2px solid #eee; border-radius: 14px; font-family: 'Rabar022'; font-size: 1rem;" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 10px; font-weight: bold; font-family: 'Rabar022'; color: #333;">شوێنی کۆنگرە</label>
                            <input type="text" name="location" style="width: 100%; padding: 15px; border: 2px solid #eee; border-radius: 14px; font-family: 'Rabar022'; font-size: 1rem;" placeholder="شار یان هۆڵ">
                        </div>
                    </div>

                    <div style="margin-bottom: 5px;">
                        <label style="display: block; margin-bottom: 10px; font-weight: bold; font-family: 'Rabar022'; color: #333;">دەربارەی کۆنگرە</label>
                        <textarea name="description" style="width: 100%; padding: 15px; border: 2px solid #eee; border-radius: 14px; font-family: 'Rabar022'; font-size: 1rem; resize: none; min-height: 100px;" placeholder="تێبینی یان کورتەیەک..."></textarea>
                    </div>
                </div>
                <div style="padding: 25px 35px; background: #f8f9fa; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 15px;">
                    <button type="button" style="background: #eee; color: #666; border: none; padding: 12px 25px; border-radius: 12px; cursor: pointer; font-family: 'Rabar022';" onclick="closeModal('X_MODAL_TEST')">پاشگەزبوونەوە</button>
                    <button type="submit" style="background: #1a5653; color: #fff; border: none; padding: 12px 40px; border-radius: 12px; cursor: pointer; font-family: 'Rabar022'; font-weight: bold; font-size: 1.1rem;">خەزنکردن</button>
                </div>
            </form>
        </div>
    </div>

    <style>
    @keyframes modalPop {
        from { transform: scale(0.95); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
    </style>

    <div class="lib-header-row">
        <div class="lib-title-group">
            <h1 class="lib-page-title">🏛️ بەڕێوەبردنی کۆنگرەکان</h1>
            <p class="lib-subtitle">لیستی هەموو کۆنگرەکان و چالاککردنیان</p>
        </div>
        <button class="lib-btn lib-btn-primary" onclick="openModal('X_MODAL_TEST')">➕ زیادکردنی کۆنگرەی نوێ</button>
    </div>

    <?php if (isset($_SESSION['msg'])): ?>
        <div class="lib-alert lib-alert-success" style="margin-bottom: 20px;">
            <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?>
        </div>
    <?php endif; ?>

    <div class="lib-card">
        <div class="lib-table-container">
            <table class="lib-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ناونیشانی کۆنگرە</th>
                        <th>بەروار و شوێن</th>
                        <th>دۆخ</th>
                        <th>کردارەکان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($elections)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 30px; color: #999;">هیچ کۆنگرەیەک تۆمار نەکراوە.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($elections as $index => $e): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <div style="font-weight: bold; color: #1a5653; font-size: 1.1rem;"><?php echo htmlspecialchars($e['title']); ?></div>
                            <?php if ($e['description']): ?>
                                <small style="color: #666; display: block; margin-top: 4px; line-height: 1.5;"><?php echo htmlspecialchars($e['description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight: 500;">📅 <?php echo $e['election_date']; ?></div>
                            <?php if ($e['location']): ?>
                                <small style="color: #666; display: block; margin-top: 2px;">📍 <?php echo htmlspecialchars($e['location']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($e['status'] === 'active'): ?>
                                <span class="lib-badge lib-badge-success" style="padding: 8px 15px; font-size: 0.9rem;">چالاکە</span>
                            <?php else: ?>
                                <span class="lib-badge lib-badge-secondary" style="padding: 8px 15px; font-size: 0.9rem;">ناچالاک</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 10px;">
                                <?php if ($e['status'] !== 'active'): ?>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="action" value="set_active">
                                        <input type="hidden" name="election_id" value="<?php echo $e['id']; ?>">
                                        <button type="submit" class="lib-btn lib-btn-gold" style="padding: 8px 15px; font-size: 0.9rem;">✅ چالاککردن</button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="elections_congress_members.php?id=<?php echo $e['id']; ?>" class="lib-btn lib-btn-primary" style="padding: 8px 15px; font-size: 0.9rem; text-decoration: none;">👥 ئەندامان</a>

                                <form method="POST" style="margin: 0;" onsubmit="return confirm('ئایا دڵنیای لە سڕینەوە؟')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="election_id" value="<?php echo $e['id']; ?>">
                                    <button type="submit" class="lib-btn" style="background: #e74c3c; color: #fff; padding: 8px 15px; font-size: 0.9rem; border: none; border-radius: 10px; cursor: pointer;">🗑️ سڕینەوە</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/layout_end.php'; ?>
