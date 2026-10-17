<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'owner' && $_SESSION['role'] !== 'admin')) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_type'])) {
        $type_name = trim($_POST['type_name']);
        if (!empty($type_name)) {
            $stmt = $conn->prepare("INSERT INTO document_types (type_name) VALUES (?)");
            if ($stmt) {
                $stmt->bind_param("s", $type_name);
                if ($stmt->execute()) { $message = 'جۆری نوسراو بە سەرکەوتوویی زیادکرا'; $messageType = 'success'; }
                else { $message = 'هەڵە: ' . $conn->error; $messageType = 'error'; }
                $stmt->close();
            }
        }
    }

    if (isset($_POST['update_type'])) {
        $id = (int)$_POST['type_id'];
        $type_name = trim($_POST['type_name']);
        if (!empty($type_name)) {
            $stmt = $conn->prepare("UPDATE document_types SET type_name=? WHERE id=?");
            if ($stmt) {
                $stmt->bind_param("si", $type_name, $id);
                if ($stmt->execute()) { $message = 'زانیارییەکان نوێکرانەوە'; $messageType = 'success'; }
                else { $message = 'هەڵە: ' . $conn->error; $messageType = 'error'; }
                $stmt->close();
            }
        }
    }
    
    if (isset($_POST['delete_type'])) {
        $id = (int)$_POST['type_id'];
        // Check if used in documents
        $check = $conn->query("SELECT id FROM documents WHERE type_id = $id LIMIT 1");
        if ($check->num_rows > 0) {
            $message = 'ناتوانیت ئەم جۆرە بسڕیتەوە چونکە بەکارهاتووە لە نوسراوەکاندا';
            $messageType = 'error';
        } else {
            $conn->query("DELETE FROM document_types WHERE id=$id");
            $message = 'جۆری نوسراو سڕایەوە'; $messageType = 'success';
        }
    }
}

$types = $conn->query("SELECT * FROM document_types ORDER BY id DESC");

// Layout Config
$sys_module = 'docs';
$page_title = 'بەڕێوەبردنی جۆرەکانی نوسراو';
$active_nav = 'docs_settings';
require_once 'includes/layout.php';
?>

<style>
    .types-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; align-items: start; }
    @media (max-width: 900px) { .types-grid { grid-template-columns: 1fr; } }
</style>

<?php if ($message): ?>
<div class="lib-toast lib-toast-<?php echo $messageType; ?>" style="position: static; margin-bottom: 25px; transform: none; width: 100%;">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="types-grid">
    <!-- Add Type Form -->
    <div class="lib-card">
        <div class="card-header"><h3>➕ زیادکردنی جۆری نوێ</h3></div>
        <div style="padding: 25px;">
            <form method="POST">
                <div class="lib-field">
                    <label>ناوی جۆری نوسراو</label>
                    <input type="text" name="type_name" placeholder="بۆ نموونە: هاتوو، دەرچوو" required>
                </div>
                <button type="submit" name="add_type" class="lib-btn lib-btn-primary" style="width:100%; margin-top: 25px; padding: 12px;">پاشەکەوتکردنی جۆر</button>
            </form>
        </div>
    </div>

    <!-- Types List -->
    <div class="lib-card">
        <div class="card-header"><h3>📋 لیست</h3></div>
        <div class="lib-table-wrapper">
            <table class="lib-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th style="text-align: right; padding-right: 25px;">ناوی جۆر</th>
                        <th style="width: 160px;">کردارەکان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($t = $types->fetch_assoc()): ?>
                    <tr>
                        <td><span class="b-num"><?php echo $t['id']; ?></span></td>
                        <td style="text-align: right; padding-right: 25px;"><div style="font-weight: 700;"><?php echo htmlspecialchars($t['type_name']); ?></div></td>
                        <td>
                            <div style="display: flex; gap: 8px; justify-content: center;">
                                <button class="lib-btn" style="background:#f5f5f5; color:#555; padding: 6px 10px; font-size: 0.75rem;" onclick='editType(<?php echo json_encode($t); ?>)'>دەستکاری</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('ئایا دڵنیایت؟')">
                                    <input type="hidden" name="type_id" value="<?php echo $t['id']; ?>">
                                    <button type="submit" name="delete_type" class="lib-btn lib-btn-danger" style="padding: 6px 10px; font-size: 0.75rem;">سڕینەوە</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Type Modal -->
<div class="lib-overlay" id="editOverlay" onclick="closeModal()"></div>
<div class="lib-modal" id="editModal" style="width: min(500px, 90%);">
    <div class="lib-modal-header">
        <h3>دەستکاری جۆری نوسراو</h3>
        <button class="lib-modal-close" onclick="closeModal()">&times;</button>
    </div>
    <div class="lib-modal-body">
        <form method="POST">
            <input type="hidden" name="type_id" id="e_id">
            <div class="lib-field">
                <label>ناوی جۆر</label>
                <input type="text" name="type_name" id="e_name" required>
            </div>
            <button type="submit" name="update_type" class="lib-btn lib-btn-primary" style="width:100%; margin-top: 25px; padding: 12px;">نوێکردنەوەی زانیارییەکان</button>
        </form>
    </div>
</div>

<script>
    function editType(t) {
        document.getElementById('e_id').value = t.id;
        document.getElementById('e_name').value = t.type_name;
        document.getElementById('editModal').classList.add('show');
        document.getElementById('editOverlay').classList.add('show');
    }

    function closeModal() {
        document.getElementById('editModal').classList.remove('show');
        document.getElementById('editOverlay').classList.remove('show');
    }
</script>

<?php require_once 'includes/layout_end.php'; ?>
