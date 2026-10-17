<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'owner' && $_SESSION['role'] !== 'admin')) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$message = ''; $messageType = '';

// --- Self Healing for users table ---
$check_unit = $conn->query("SHOW COLUMNS FROM users LIKE 'unit_id'");
if ($check_unit->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD unit_id INT DEFAULT 0");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = normalizeNumerals($_POST['username']);
        $password = password_hash(normalizeNumerals($_POST['password']), PASSWORD_DEFAULT);
        $full_name = $_POST['full_name'];
        $role = $_POST['role'];
        $unit_id = (int)($_POST['unit_id'] ?? 0);
        $lib = isset($_POST['lib']) ? 1 : 0;
        $docs = isset($_POST['docs']) ? 1 : 0;
        $arch = isset($_POST['arch']) ? 1 : 0;
        $off = isset($_POST['off']) ? 1 : 0;
        $man = isset($_POST['man']) ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO users (username, password, role, full_name, unit_id, can_access_library, can_access_docs, can_access_archive, can_access_office, can_manage_users) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssssiiiiii", $username, $password, $role, $full_name, $unit_id, $lib, $docs, $arch, $off, $man);
            if ($stmt->execute()) { $message = 'بەکارهێنەر بە سەرکەوتوویی زیادکرا'; $messageType = 'success'; }
            else { $message = 'هەڵە: ' . $conn->error; $messageType = 'error'; }
        }
    }

    if (isset($_POST['update_user'])) {
        $id = $_POST['user_id'];
        $full_name = $_POST['full_name'];
        $role = $_POST['role'];
        $unit_id = (int)($_POST['unit_id'] ?? 0);
        $lib = isset($_POST['lib']) ? 1 : 0;
        $docs = isset($_POST['docs']) ? 1 : 0;
        $arch = isset($_POST['arch']) ? 1 : 0;
        $off = isset($_POST['off']) ? 1 : 0;
        $man = isset($_POST['man']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE users SET full_name=?, role=?, unit_id=?, can_access_library=?, can_access_docs=?, can_access_archive=?, can_access_office=?, can_manage_users=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("ssiiiiiii", $full_name, $role, $unit_id, $lib, $docs, $arch, $off, $man, $id);
            if (!empty($_POST['new_password'])) {
                $new_pass = password_hash(normalizeNumerals($_POST['new_password']), PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET password='$new_pass' WHERE id=$id");
            }
            if ($stmt->execute()) { $message = 'زانیارییەکان نوێکرانەوە'; $messageType = 'success'; }
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $id = $_POST['user_id'];
        if ($id != $_SESSION['user_id']) {
            $conn->query("DELETE FROM users WHERE id=$id");
            $message = 'بەکارهێنەر سڕایەوە'; $messageType = 'success';
        }
    }
}

$users = $conn->query("SELECT u.*, s.unit_name FROM users u LEFT JOIN staff_units s ON u.unit_id = s.id ORDER BY u.id DESC");
$staff_units = $conn->query("SELECT * FROM staff_units ORDER BY unit_name ASC");

// Layout Config
$page_title = 'بەرێوەبردنی بەکارهێنەران';
$active_nav = 'users';
require_once 'includes/layout.php';
?>

<style>
    .users-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 30px; align-items: start; }
    @media (max-width: 1100px) { .users-grid { grid-template-columns: 1fr; } }

    .perm-box { background: #fdfdfd; border: 1px dashed #ccc; border-radius: 12px; padding: 20px; margin-top: 10px; }
    .perm-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 15px; }
    .chk-group { display: flex; align-items: center; gap: 10px; cursor: pointer; }
    .chk-group input { width: 18px; height: 18px; cursor: pointer; }
    .chk-group label { font-weight: 600; font-size: 0.9rem; cursor: pointer; }

    .role-owner { background: #efebe9; color: var(--maroon); border: 1px solid rgba(90, 2, 2, 0.2); }
    .role-admin { background: #fff3e0; color: #e65100; border: 1px solid rgba(230, 81, 0, 0.2); }
    .role-user  { background: #e3f2fd; color: #1565c0; border: 1px solid rgba(21, 101, 192, 0.2); }
    .p-badge { width: 26px; height: 26px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.85rem; background: #eee; }
    .p-badge.active { background: #e8f5e9; color: #2e7d32; }
</style>

<?php if ($message): ?>
<div class="lib-toast lib-toast-<?php echo $messageType; ?>" style="position: static; margin-bottom: 25px; transform: none; width: 100%;">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="users-grid">
    <!-- Add User Form -->
    <div class="lib-card">
        <div class="card-header"><h3>➕ زیادکردنی بەکارهێنەری نوێ</h3></div>
        <div style="padding: 25px;">
            <form method="POST">
                <div class="lib-field">
                    <label>ناوی تەواو</label>
                    <input type="text" name="full_name" placeholder="بۆ نموونە: سامان محمد" required>
                </div>
                <div class="lib-field">
                    <label>ناوی بەکارهێنەر (Username)</label>
                    <input type="text" name="username" required>
                </div>
                <div class="lib-field">
                    <label>وشەی نهێنی</label>
                    <input type="password" name="password" required>
                </div>
                <div class="lib-field">
                    <label>ڕۆڵ (Role)</label>
                    <select name="role">
                        <option value="user">بەکارهێنەر (User)</option>
                        <option value="admin">سەرپەرشتیار (Admin)</option>
                        <option value="owner">خاوەن (Owner)</option>
                    </select>
                </div>
                <div class="lib-field">
                    <label>دیاریکردنی یەکە (بۆ بەشی نوسینگە)</label>
                    <select name="unit_id">
                        <option value="0">هەموو یەکەکان (Admin/Owner)</option>
                        <?php 
                        $staff_units->data_seek(0);
                        while($un = $staff_units->fetch_assoc()): ?>
                            <option value="<?php echo $un['id']; ?>"><?php echo htmlspecialchars($un['unit_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="perm-box">
                    <div style="font-weight: 700; color: var(--maroon); margin-bottom: 12px; font-size: 0.85rem;">دەسەڵاتەکانی چوونە ناو سیستم:</div>
                    <div class="perm-grid">
                        <div class="chk-group"><input type="checkbox" name="lib" id="l1" checked><label for="l1">کتێبخانە</label></div>
                        <div class="chk-group"><input type="checkbox" name="docs" id="l2"><label for="l2">نوسراوەکان</label></div>
                        <div class="chk-group"><input type="checkbox" name="arch" id="l3"><label for="l3">ئەرشیف</label></div>
                        <div class="chk-group"><input type="checkbox" name="off" id="l4"><label for="l4">نوسینگە</label></div>
                        <div class="chk-group"><input type="checkbox" name="man" id="l5"><label for="l5" style="color:red;">بەڕێوەبەری</label></div>
                    </div>
                </div>

                <button type="submit" name="add_user" class="lib-btn lib-btn-primary" style="width:100%; margin-top: 25px; padding: 12px;">پاشەکەوتکردنی بەکارهێنەر</button>
            </form>
        </div>
    </div>

    <!-- Users List -->
    <div class="lib-card">
        <div class="card-header"><h3>👥 لیستی بەکارهێنەران</h3></div>
        <div class="lib-table-wrapper">
            <table class="lib-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>ناوی تەواو</th>
                        <th>ڕۆڵ</th>
                        <th>دەسەڵاتەکان</th>
                        <th style="width: 150px;">کردارەکان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($u = $users->fetch_assoc()): ?>
                    <tr>
                        <td><span class="b-num"><?php echo $u['id']; ?></span></td>
                        <td>
                            <div style="font-weight: 700;"><?php echo htmlspecialchars($u['full_name']); ?></div>
                            <div style="font-size: 0.75rem; color: #888;"><?php echo htmlspecialchars($u['username']); ?></div>
                        </td>
                        <td>
                            <?php 
                                $rCls = $u['role'] === 'owner' ? 'role-owner' : ($u['role'] === 'admin' ? 'role-admin' : 'role-user');
                                $rTxt = $u['role'] === 'owner' ? 'خاوەن' : ($u['role'] === 'admin' ? 'ئادمین' : 'یوزەر');
                            ?>
                            <span class="rec-badge <?php echo $rCls; ?>" style="font-size: 0.75rem; border-radius: 6px;"><?php echo $rTxt; ?></span>
                            <div style="font-size: 0.7rem; margin-top: 4px; color: #666;">
                                یەکە: <?php echo $u['unit_name'] ?: 'گشتی'; ?>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; gap: 4px;">
                                <span class="p-badge <?php echo ($u['can_access_library']??0) ? 'active' : ''; ?>" title="Library">📖</span>
                                <span class="p-badge <?php echo ($u['can_access_docs']??0) ? 'active' : ''; ?>" title="Documents">📝</span>
                                <span class="p-badge <?php echo ($u['can_access_archive']??0) ? 'active' : ''; ?>" title="Archive">📁</span>
                                <span class="p-badge <?php echo ($u['can_access_office']??0) ? 'active' : ''; ?>" title="Office">👮</span>
                                <span class="p-badge <?php echo ($u['can_manage_users']??0) ? 'active' : ''; ?>" title="Settings">⚙️</span>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <button class="lib-btn" style="background:#f5f5f5; color:#555; padding: 6px 10px; font-size: 0.75rem;" onclick='editUser(<?php echo json_encode($u); ?>)'>دەستکاری</button>
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('ئایا دڵنیایت؟')">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" name="delete_user" class="lib-btn lib-btn-danger" style="padding: 6px 10px; font-size: 0.75rem;">سڕینەوە</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="lib-overlay" id="editOverlay" onclick="closeModal()"></div>
<div class="lib-modal" id="editModal" style="width: min(600px, 90%);">
    <div class="lib-modal-header">
        <h3>دەستکاری بەکارهێنەر</h3>
        <button class="lib-modal-close" onclick="closeModal()">&times;</button>
    </div>
    <div class="lib-modal-body">
        <form method="POST">
            <input type="hidden" name="user_id" id="e_id">
            <div class="lib-form-grid">
                <div class="lib-field">
                    <label>ناوی تەواو</label>
                    <input type="text" name="full_name" id="e_full_name" required>
                </div>
                <div class="lib-field">
                    <label>ناوی بەکارهێنەر (Username)</label>
                    <input type="text" name="username" id="e_username" required>
                </div>
                <div class="lib-field">
                    <label>ڕۆڵ</label>
                    <select name="role" id="e_role">
                        <option value="user">بەکارهێنەر</option>
                        <option value="admin">ئادمین</option>
                        <option value="owner">خاوەن</option>
                    </select>
                </div>
                <div class="lib-field">
                    <label>یەکە</label>
                    <select name="unit_id" id="e_unit_id">
                        <option value="0">هەموو یەکەکان (Admin/Owner)</option>
                        <?php 
                        $staff_units->data_seek(0);
                        while($un = $staff_units->fetch_assoc()): ?>
                            <option value="<?php echo $un['id']; ?>"><?php echo htmlspecialchars($un['unit_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="lib-field">
                    <label>وشەی نهێنی نوێ</label>
                    <input type="password" name="new_password" placeholder="بەتاڵی بهێڵەرەوە ئەگەر ناگۆڕێت">
                </div>
            </div>
            
            <div class="perm-box">
                <div style="font-weight: 700; color: var(--maroon); margin-bottom: 12px; font-size: 0.85rem;">دەسەڵاتەکان:</div>
                <div class="perm-grid">
                    <div class="chk-group"><input type="checkbox" name="lib" id="ee_lib"><label for="ee_lib">کتێبخانە</label></div>
                    <div class="chk-group"><input type="checkbox" name="docs" id="ee_docs"><label for="ee_docs">نوسراوەکان</label></div>
                    <div class="chk-group"><input type="checkbox" name="arch" id="ee_arch"><label for="ee_arch">ئەرشیف</label></div>
                    <div class="chk-group"><input type="checkbox" name="off" id="ee_off"><label for="ee_off">نوسینگە</label></div>
                    <div class="chk-group"><input type="checkbox" name="man" id="ee_man"><label for="ee_man">بەڕێوەبەری</label></div>
                </div>
            </div>

            <button type="submit" name="update_user" class="lib-btn lib-btn-primary" style="width:100%; margin-top: 25px; padding: 12px;">نوێکردنەوەی زانیارییەکان</button>
        </form>
    </div>
</div>

<script>
    function editUser(user) {
        document.getElementById('e_id').value = user.id;
        document.getElementById('e_full_name').value = user.full_name;
        document.getElementById('e_username').value = user.username;
        document.getElementById('e_role').value = user.role;
        document.getElementById('e_unit_id').value = user.unit_id;
        document.getElementById('ee_lib').checked = parseInt(user.can_access_library) == 1;
        document.getElementById('ee_docs').checked = parseInt(user.can_access_docs) == 1;
        document.getElementById('ee_arch').checked = parseInt(user.can_access_archive) == 1;
        document.getElementById('ee_off').checked = parseInt(user.can_access_office) == 1;
        document.getElementById('ee_man').checked = parseInt(user.can_manage_users) == 1;
        
        document.getElementById('editModal').classList.add('show');
        document.getElementById('editOverlay').classList.add('show');
    }

    function closeModal() {
        document.getElementById('editModal').classList.remove('show');
        document.getElementById('editOverlay').classList.remove('show');
    }
</script>

<?php require_once 'includes/layout_end.php'; ?>
