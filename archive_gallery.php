<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: archive_list.php');
    exit;
}

$res = $conn->query("SELECT * FROM activities WHERE id = $id");
$activity = $res->fetch_assoc();

if (!$activity) {
    die("چالاکیەکە نەدۆزرایەوە.");
}

$sys_module = 'archive';
$page_title = 'گەلەریا: ' . $activity['name'];
$active_nav = 'archive_list';

require_once 'includes/layout.php';
?>

<style>
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 20px;
        padding: 20px 0;
    }
    .gallery-item {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transition: 0.3s;
        background: #fff;
        border: 1px solid #eee;
        cursor: pointer;
    }
    .gallery-item:hover {
        transform: scale(1.05);
        box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    }
    .gallery-item img, .gallery-item video {
        width: 100%;
        height: 180px;
        object-fit: contain;
        display: block;
        background: #fdfdfd;
    }
    .gallery-item .info {
        padding: 10px;
        font-size: 0.8rem;
        text-align: center;
        color: #666;
    }
    .modal-overlay {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.9);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 10000;
        cursor: zoom-out;
    }
    .modal-content {
        max-width: 90%;
        max-height: 90%;
        box-shadow: 0 0 30px rgba(0,0,0,0.5);
    }
</style>

<div class="lib-card" style="max-width: 1200px; margin: 0 auto;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3><?php echo $page_title; ?></h3>
        <a href="archive_list.php" class="lib-btn lib-btn-gold">⬅️ گەڕانەوە</a>
    </div>
    
    <div style="padding: 25px;">
        <div style="background: #f9f9f9; padding: 20px; border-radius: 15px; margin-bottom: 30px; border-left: 5px solid var(--primary);">
            <p><strong>کاتی چالاکی:</strong> <?php echo htmlspecialchars($activity['activity_date']); ?></p>
            <p><strong>شوێن:</strong> <?php echo htmlspecialchars($activity['location']); ?></p>
            <p><strong>جێبەجێکار:</strong> <?php echo htmlspecialchars($activity['implemented_by']); ?></p>
            <p><strong>بەشداربوان:</strong> <?php echo $activity['participants_count']; ?></p>
        </div>

        <div class="gallery-grid">
            <?php 
            $folder = $activity['folder_path'];
            if ($folder && is_dir($folder)) {
                $files = scandir($folder);
                $found = false;
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $full_path = $folder . '/' . $file;
                    
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $found = true;
                        echo '<div class="gallery-item" onclick="openModal(\''.$full_path.'\', \'img\')">
                                <img src="'.$full_path.'" alt="'.$file.'">
                                <div class="info">'.$file.'</div>
                              </div>';
                    } elseif (in_array($ext, ['mp4', 'webm', 'ogg'])) {
                        $found = true;
                        echo '<div class="gallery-item" onclick="openModal(\''.$full_path.'\', \'video\')">
                                <video src="'.$full_path.'" muted></video>
                                <div class="info">'.$file.'</div>
                              </div>';
                    }
                }
                if (!$found) {
                    echo '<p style="text-align:center; grid-column: 1/-1; color: #999;">هیچ وێنە یان ڤیدیۆیەکی لێرە نییە.</p>';
                }
            } else {
                echo '<p style="text-align:center; grid-column: 1/-1; color: #999;">فۆڵدەری وێنەکان نەدۆزرایەوە.</p>';
            }
            ?>
        </div>
    </div>
</div>

<div id="modal" class="modal-overlay" onclick="closeModal()">
    <div class="modal-content" onclick="event.stopPropagation()">
        <!-- Content injected by JS -->
    </div>
</div>

<script>
function openModal(src, type) {
    const modal = document.getElementById('modal');
    const content = modal.querySelector('.modal-content');
    
    if (type === 'img') {
        content.innerHTML = `<img src="${src}" style="width:100%; height:auto; display:block;">`;
    } else if (type === 'video') {
        content.innerHTML = `<video src="${src}" controls autoplay style="width:100%; height:auto; display:block;"></video>`;
    }
    
    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('modal').style.display = 'none';
    document.getElementById('modal').querySelector('.modal-content').innerHTML = '';
}
</script>
<?php 
echo '</div></div></div></body></html>'; 
?>
