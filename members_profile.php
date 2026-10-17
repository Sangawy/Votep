<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$id = (int)($_GET['id'] ?? 0);
$member = null;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT m.*, f.family_name 
                            FROM members m 
                            LEFT JOIN families f ON m.family_id = f.id 
                            WHERE m.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$member) {
    die("ئەندام نەدۆزرایەوە.");
}

$page_title = 'پڕۆفایلی: ' . $member['full_name'];
$active_nav = 'members_list';
$sys_module = 'nusinga';
require_once 'includes/layout.php';
?>

<style>
    .profile-card {
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        overflow: hidden;
        border: 1px solid #eee;
        display: grid;
        grid-template-columns: 350px 1fr;
        width: 100%;
        margin: 20px 0;
    }
    .profile-sidebar {
        background: #f8f9fa;
        padding: 40px;
        text-align: center;
        border-left: 1px solid #eee;
    }
    .profile-photo {
        width: 220px;
        height: 220px;
        border-radius: 20px;
        margin: 0 auto 25px;
        border: 5px solid #fff;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    .profile-photo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        background: #f8f9fa;
    }
    .profile-content {
        padding: 40px;
    }
    .info-group {
        margin-bottom: 30px;
    }
    .info-group h4 {
        color: var(--primary);
        border-bottom: 2px solid #eee;
        padding-bottom: 10px;
        margin-bottom: 20px;
        font-size: 1.2rem;
    }
    .info-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }
    .info-box {
        background: #fcfcfc;
        padding: 15px;
        border-radius: 12px;
        border: 1px solid #f0f0f0;
    }
    .info-box label {
        display: block;
        color: #888;
        font-size: 0.85rem;
        margin-bottom: 5px;
    }
    .info-box span {
        font-weight: bold;
        color: #333;
        font-size: 1.1rem;
    }
    @media (max-width: 900px) {
        .profile-card { grid-template-columns: 1fr; }
        .profile-sidebar { border-left: none; border-bottom: 1px solid #eee; }
    }
</style>

<div class="profile-card">
    <div class="profile-sidebar">
        <div class="profile-photo">
            <?php 
            $p_img = $member['profile_image'];
            if (empty($p_img)) $p_img = "assets/imgae/user.png";
            ?>
            <img src="stream_image.php?path=<?php echo urlencode($p_img); ?>" alt="Profile" onerror="this.src='assets/imgae/user.png'">
        </div>
        
        <?php
        // Smart 4-generation name builder for Profile
        $full_display_name = trim($member['full_name']);
        $current_parent_id = $member['parent_id'];
        
        $normalize_prof = function($str) {
            $str = preg_replace('/[أإآء]/u', 'ا', $str);
            $str = preg_replace('/(علاء|علأ|علا)/u', 'علا', $str);
            $str = preg_replace('/(ی|ي)/u', 'ی', $str);
            $str = str_replace([' ', 'ملا', 'مەلا', 'شێخ', 'شیخ', 'السید', 'سید'], '', $str);
            return $str;
        };

        $title_pattern_prof = '/\b(السید|سید|سەید|ملا|مەلا|مولا|شێخ|شیخ|السيد|سيد)\b/u';
        
        for ($g = 0; $g < 3; $g++) {
            if (!$current_parent_id) break;
            $p_res = $conn->query("SELECT full_name, parent_id FROM members WHERE id = $current_parent_id");
            if ($p_res && $p_row = $p_res->fetch_assoc()) {
                $p_full = $p_row['full_name'];
                $p_name_clean = trim(preg_replace($title_pattern_prof, '', $p_full));
                if (empty($p_name_clean)) {
                    $current_parent_id = $p_row['parent_id'];
                    continue; 
                }
                $p_parts = explode(' ', $p_name_clean);
                $p_first = $p_parts[0] ?? '';
                $compounds = ['عبد', 'صلاح', 'علاء', 'علأ', 'علا', 'سيف', 'نور', 'فخر', 'بهاء', 'جمال', 'کمال'];
                $ancestor_core = $p_first;
                if (in_array($p_first, $compounds) && isset($p_parts[1])) {
                    $ancestor_core = $p_first . ' ' . $p_parts[1];
                }
                if (stripos($normalize_prof($full_display_name), $normalize_prof($ancestor_core)) === false) {
                    $full_display_name .= ' ' . $ancestor_core;
                }
                $current_parent_id = $p_row['parent_id'];
            } else {
                break;
            }
        }
        ?>
        <h3 style="font-size: 1.5rem; margin-bottom: 10px;"><?php echo htmlspecialchars($full_display_name); ?></h3>
        
        <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px;">
            <span class="lib-badge lib-badge-primary" style="font-size: 1rem; padding: 8px 20px;">کۆد: <?php echo htmlspecialchars($member['member_code'] ?? ''); ?></span>
            <span class="lib-badge lib-badge-gold" style="font-size: 0.9rem; padding: 8px 20px;"><?php echo htmlspecialchars($member['member_rank'] ?? 'ئەندام'); ?></span>
        </div>

        <?php if(!empty($member['member_post'])): ?>
            <div style="background: #fff; padding: 10px; border-radius: 10px; border: 1px dashed var(--gold); margin-bottom: 20px;">
                <label style="display: block; font-size: 0.8rem; color: #888;">پایە</label>
                <strong style="color: var(--primary);"><?php echo htmlspecialchars($member['member_post']); ?></strong>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; display: flex; flex-direction: column; gap: 10px;">
            <a href="members_print.php?id=<?php echo $id; ?>" target="_blank" class="lib-btn lib-btn-success">🖨️ چاپکردنی فۆڕم</a>
            <a href="members_edit.php?id=<?php echo $id; ?>" class="lib-btn lib-btn-warning">✏️ دەستکاری زانیارییەکان</a>
            <a href="members_list.php" class="lib-btn lib-btn-danger">🔙 گەڕانەوە بۆ لیست</a>
        </div>
    </div>
    
    <div class="profile-content">
        <div class="info-group">
            <h4>📌 زانیارییە کەسییەکان</h4>
            <div class="info-grid">
                <div class="info-box">
                    <label>بنەماڵە:</label>
                    <span><?php echo htmlspecialchars(($member['family_name'] ?? '') ?: '-'); ?></span>
                </div>
                <div class="info-box">
                    <label>ئاستی خوێندن:</label>
                    <span><?php echo htmlspecialchars(($member['education'] ?? '') ?: '-'); ?></span>
                </div>
                <div class="info-box">
                    <label>بەرواری لەدایکبوون:</label>
                    <span><?php echo htmlspecialchars(($member['birth_date'] ?? '') ?: '-'); ?></span>
                </div>
                <div class="info-box">
                    <label>شوێنی لەدایکبوون:</label>
                    <span><?php echo htmlspecialchars(($member['birth_place'] ?? '') ?: '-'); ?></span>
                </div>
                <div class="info-box">
                    <label>ناونیشان:</label>
                    <span><?php echo htmlspecialchars(($member['address'] ?? '') ?: '-'); ?></span>
                </div>
            </div>
        </div>

        <div class="info-group">
            <h4>📞 پەیوەندی و کار</h4>
            <div class="info-grid">
                <div class="info-box">
                    <label>پیشە:</label>
                    <span><?php echo htmlspecialchars(($member['profession'] ?? '') ?: '-'); ?></span>
                </div>
                <div class="info-box">
                    <label>ژمارەی مۆبایل:</label>
                    <span style="direction: ltr; display: inline-block;"><?php echo htmlspecialchars(($member['phone'] ?? '') ?: '-'); ?></span>
                </div>
                <div class="info-box">
                    <label>بەرواری ئەندامێتی:</label>
                    <span><?php echo htmlspecialchars(($member['membership_date'] ?? '') ?: '-'); ?></span>
                </div>
                <div class="info-box">
                    <label>گرووپی خوێن:</label>
                    <span><?php echo htmlspecialchars(($member['blood_group'] ?? '') ?: '-'); ?></span>
                </div>
                <div class="info-box">
                    <label>ژمارەی ناسنامە:</label>
                    <span><?php echo htmlspecialchars(($member['id_card_number'] ?? '') ?: '-'); ?></span>
                </div>
                <div class="info-box">
                    <label>کۆدی ئاسایش:</label>
                    <span><?php echo htmlspecialchars(($member['security_code'] ?? '') ?: '-'); ?></span>
                </div>
            </div>
        </div>

        <div class="info-group">
            <h4>📁 بەڵگەنامەکان</h4>
            <?php
            $doc_stmt = $conn->prepare("SELECT * FROM member_documents WHERE member_id = ?");
            $doc_stmt->bind_param("i", $id);
            $doc_stmt->execute();
            $docs_res = $doc_stmt->get_result();
            ?>
            <?php if ($docs_res->num_rows === 0): ?>
                <div style="padding: 20px; background: #f9f9f9; border-radius: 12px; border: 1px dashed #ddd; text-align: center; color: #888;">
                    هیچ بەڵگەنامەیەک بارنەکراوە
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 15px;">
                    <?php while($d = $docs_res->fetch_assoc()): ?>
                        <div class="doc-item" style="text-align: center;">
                            <a href="stream_image.php?path=<?php echo urlencode($d['doc_path']); ?>" target="_blank" style="text-decoration: none;">
                                <div style="width: 100%; aspect-ratio: 1; background: #f8f9fa; border: 1px solid #eee; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 5px; overflow: hidden; box-shadow: 0 3px 6px rgba(0,0,0,0.05); transition: transform 0.2s;">
                                    <img src="stream_image.php?path=<?php echo urlencode($d['doc_path']); ?>" style="width: 100%; height: 100%; object-fit: contain;" onerror="this.src='assets/imgae/doc_placeholder.png'">
                                </div>
                                <span style="font-size: 0.8rem; color: #555; font-weight: bold;"><?php echo htmlspecialchars($d['doc_type']); ?></span>
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/layout_end.php'; ?>
