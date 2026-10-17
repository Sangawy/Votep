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
$current_user = strtolower($user_data['username'] ?? '');
$is_super_admin = ($current_user === 'admin' || $current_user === 'sangawy' || ($user_data['role'] ?? '') === 'owner');

$sys_module = 'archive';
$page_title = 'زیادکردنی چالاکی نوێ';
$active_nav = 'archive_new';


require_once 'includes/layout.php';
?>

<style>
    .upload-zone {
        border: 2px dashed #ddd;
        border-radius: 16px;
        padding: 30px;
        text-align: center;
        background: #fcfcfc;
        transition: all 0.2s;
        cursor: pointer;
        position: relative;
    }
    .upload-zone:hover { border-color: var(--primary); background: #fff5f5; }
    .upload-zone svg { width: 48px; height: 48px; color: #ccc; margin-bottom: 15px; }
    .file-preview-strip {
        margin-top: 15px;
        padding: 10px;
        background: #f0f0f0;
        border-radius: 10px;
        display: none;
        flex-direction: column;
        align-items: flex-start;
    }
</style>

<div class="lib-card" style="max-width: 850px; margin: 0 auto;">
    <div class="card-header">
        <h3><?php echo $page_title; ?></h3>
        <a href="archive_list.php" class="lib-btn lib-btn-gold">⬅️ گەڕانەوە</a>
    </div>
    
    <div style="padding: 25px;">
        <form action="archive_save.php" method="POST" enctype="multipart/form-data">
            <div class="lib-field" style="margin-bottom: 20px;">
                <label>ناوی چالاکی *</label>
                <input type="text" name="name" required placeholder="ناوی چالاکی بنوسە">
            </div>
            
            <div class="lib-form-grid" style="margin-bottom: 20px;">
                <div class="lib-field">
                    <label>کاتی چالاکی *</label>
                    <input type="datetime-local" name="activity_date" required>
                </div>
                <div class="lib-field">
                    <label>ژمارەی بەشداربوان</label>
                    <input type="number" name="participants_count" placeholder="0">
                </div>
            </div>
            
            <div class="lib-form-grid" style="margin-bottom: 20px;">
                <div class="lib-field">
                    <label>شوێنی چالاکی</label>
                    <input type="text" name="location" placeholder="شوێنی ئەنجامدانی چالاکی">
                </div>
                <div class="lib-field">
                    <label>جێ بەجێ کراوە لەلایەن</label>
                    <input type="text" name="implemented_by" placeholder="ناوی کەسی بەرپرس">
                </div>
            </div>

            <div class="lib-field" style="margin-bottom: 20px;">
                <label>کورتەیەک لەسەر چالاکی</label>
                <textarea name="summary" rows="3" placeholder="کورتەیەکی چالاکییەکە لێرە بنوسە..." style="width:100%; border-radius:10px; border:2px solid var(--border); padding:10px; font-family:inherit;"></textarea>
            </div>

            <div class="lib-field" style="margin-bottom: 20px;">
                <label>تێبینی</label>
                <textarea name="notes" rows="2" placeholder="تێبینییەکان لێرە بنوسە..." style="width:100%; border-radius:10px; border:2px solid var(--border); padding:10px; font-family:inherit;"></textarea>
            </div>
            
            <div class="lib-field" style="margin-bottom: 25px;">
                <label>وێنە و ڤیدیۆکان (هاوپێچەکان) *</label>
                <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <h4>کلیک بکە یان فایلەکان ڕابکێشە بۆ ئێرە</h4>
                    <p>دەتوانیت وێنە و ڤیدیۆکان بە کۆمەڵ باربکەیت</p>
                    <input type="file" name="files[]" id="fileInput" accept="image/*,video/*" multiple style="display: none;" onchange="handleFileSelect(this)">
                </div>
                <div id="filePreviewStrip" class="file-preview-strip"></div>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="submit" class="lib-btn lib-btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    پاشەکەوتکردن
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function handleFileSelect(input) {
    const strip = document.getElementById('filePreviewStrip');
    strip.innerHTML = '';
    
    if (input.files && input.files.length > 0) {
        strip.style.display = 'flex';
        Array.from(input.files).forEach(file => {
            const item = document.createElement('div');
            item.style.cssText = 'display:flex; align-items:center; gap:10px; margin-bottom:5px; background:#fff; padding:5px 10px; border-radius:6px; border:1px solid #ddd; width:100%;';
            item.innerHTML = `
                <span style="font-size:0.8rem; font-weight:600; flex:1;">${file.name}</span>
                <span class="lib-badge lib-badge-green" style="font-size:0.7rem;">ئامادەیە</span>
            `;
            strip.appendChild(item);
        });
    }
}

let zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => { zone.classList.remove('dragover'); });
zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        document.getElementById('fileInput').files = e.dataTransfer.files;
        handleFileSelect(document.getElementById('fileInput'));
    }
});
</script>
<?php 
echo '</div></div></div></body></html>'; 
?>
