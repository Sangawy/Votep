<?php
session_start();
if (!isset($_SESSION['user_id'])) die("Access Denied");

require_once 'config.php';
$conn = getDB();

$id = (int)($_GET['id'] ?? 0);
$staff = null;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT full_name, archive_folder FROM staff_members WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$staff) die("کارمەند نەدۆزرایەوە.");

$folder = $staff['archive_folder'];

// Attempt to open locally if running on Windows and server is local
// Note: This only works if the browser and server are on the same machine.
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    if (is_dir($folder)) {
        @pclose(@popen("start explorer.exe " . escapeshellarg($folder), "r"));
    }
}

$page_title = 'ئەرشیفی: ' . $staff['full_name'];
$active_nav = 'staff_list';
require_once 'includes/layout.php';
?>

<div class="lib-card" style="max-width: 600px; margin: 50px auto;">
    <div class="card-header"><h3>📁 کردنەوەی فۆڵدەری ئەرشیف</h3></div>
    <div style="padding: 30px; text-align: center;">
        <div style="font-size: 4rem; margin-bottom: 20px;">📂</div>
        <p style="margin-bottom: 20px; color: #666;">ئەگەر سێرڤەرەکە لەسەر کۆمپیوتەرەکەت بێت، فۆڵدەرەکە خۆی کراوەتەوە.</p>
        
        <div style="background: #f5f5f5; padding: 15px; border-radius: 12px; border: 1px solid #ddd; margin-bottom: 20px; word-break: break-all; direction: ltr; font-family: monospace;">
            <span id="folderPath"><?php echo htmlspecialchars($folder); ?></span>
        </div>

        <button onclick="copyPath()" class="lib-btn lib-btn-gold" style="width: 100%; margin-bottom: 10px;">📋 کۆپیکردنی ناونیشان</button>
        <a href="staff_list.php" class="lib-btn lib-btn-danger" style="width: 100%; text-decoration: none; display: block;">🔙 گەڕانەوە</a>
    </div>
</div>

<script>
function copyPath() {
    const path = document.getElementById('folderPath').innerText;
    navigator.clipboard.writeText(path).then(() => {
        alert("ناونیشانی فۆڵدەر کۆپی کرا!");
    });
}
</script>

<?php require_once 'includes/layout_end.php'; ?>
