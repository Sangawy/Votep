<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$id = (int)($_GET['id'] ?? 0);
$staff = null;
$courses = [];
$courses = [];
$courses = [];
$docs = [];
$conduct_records = [];

// Self-healing: ensure staff_conduct table exists
$conn->query("CREATE TABLE IF NOT EXISTS staff_conduct (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    type ENUM('penalty', 'award') DEFAULT 'penalty',
    conduct_date DATE NOT NULL,
    penalty_name VARCHAR(255),
    crime_type VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$settings = $conn->query("SELECT * FROM settings WHERE id = 1")->fetch_assoc();
$archive_base = $settings['archive_base_dir'] ?? 'D:/Archive/';

if ($id > 0) {
    $stmt = $conn->prepare("SELECT m.*, r.rank_name, r.category, u.unit_name 
                            FROM staff_members m 
                            JOIN staff_ranks r ON m.rank_id = r.id 
                            JOIN staff_units u ON m.unit_id = u.id 
                            WHERE m.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($staff) {
        // Self-healing: Ensure staff_documents exists
        $conn->query("CREATE TABLE IF NOT EXISTS staff_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            staff_id INT,
            doc_title VARCHAR(255),
            file_path VARCHAR(255),
            file_type VARCHAR(50),
            upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (staff_id) REFERENCES staff_members(id) ON DELETE CASCADE
        )");

        $res = $conn->query("SELECT * FROM staff_courses WHERE staff_id = $id");
        while ($row = $res->fetch_assoc()) $courses[] = $row;
        
        $res = $conn->query("SELECT * FROM staff_documents WHERE staff_id = $id ORDER BY upload_date DESC");
        while ($row = $res->fetch_assoc()) $docs[] = $row;

        $res_c = $conn->query("SELECT * FROM staff_conduct WHERE staff_id = $id ORDER BY conduct_date DESC");
        while ($row = $res_c->fetch_assoc()) $conduct_records[] = $row;

        // Fetch all staff for handover selection
        $all_staff = [];
        $res_s = $conn->query("SELECT m.id, m.full_name, r.rank_name FROM staff_members m JOIN staff_ranks r ON m.rank_id = r.id WHERE m.id != $id ORDER BY m.full_name ASC");
        while ($row = $res_s->fetch_assoc()) $all_staff[] = $row;

        // Calculate Hierarchical Archive Path
        $clean_unit = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $staff['unit_name']);
        $clean_rank = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $staff['rank_name']);
        $clean_name = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $staff['full_name']);
        
        $hierarchical_path = rtrim($archive_base, '/\\') . DIRECTORY_SEPARATOR . $clean_unit . DIRECTORY_SEPARATOR . $clean_rank . DIRECTORY_SEPARATOR . $clean_name;
        $folder_exists = is_dir($hierarchical_path);

        // Document Checklist Logic
        $mandatory_docs = [
            "زانیاری ئەفەسەر",
            "بڕیاری دامەزراندن (ڕاژە)",
            "بڕیاری دوا پلە",
            "گواستنەوە",
            "بڕیاری پایە",
            "جدول ركن",
            "بڕوانامەی مەدەنی ئەسڵی",
            "بڕوانامەی سەربازی کۆپی",
            "وێنەی سەربازی",
            "بەڵگەنامەکان + باوک و دایک",
            "عقد زەواج"
        ];
        $existing_doc_titles = array_map(function($d) { return trim($d['doc_title']); }, $docs);
        $missing_docs = [];
        $completed_docs_count = 0;
        foreach ($mandatory_docs as $m_doc) {
            if (in_array($m_doc, $existing_doc_titles)) {
                $completed_docs_count++;
            } else {
                $missing_docs[] = $m_doc;
            }
        }
        $completion_percent = round(($completed_docs_count / count($mandatory_docs)) * 100);
    }
}

if (!$staff) {
    die("کارمەند نەدۆزرایەوە.");
}

$page_title = 'پڕۆفایلی: ' . $staff['full_name'];
$active_nav = 'staff_list';
$sys_module = 'nusinga';
require_once 'includes/layout.php';
?>

<style>
    .profile-header { display: grid; grid-template-columns: 200px 1fr; gap: 30px; margin-bottom: 30px; background: #fff; padding: 30px; border-radius: 20px; box-shadow: var(--shadow-md); border: 1px solid var(--border); }
    .profile-img-large { width: 200px; height: 200px; border-radius: 15px; border: 4px solid var(--gold); overflow: hidden; background: #f9f9f9; box-shadow: var(--shadow-sm); }
    .profile-img-large img { width: 100%; height: 100%; object-fit: cover; }
    
    .profile-main-info h2 { font-size: 2.2rem; color: var(--maroon); margin-bottom: 5px; }
    .profile-main-info .rank-badge { font-size: 1.1rem; padding: 8px 20px; margin-bottom: 15px; display: inline-block; }
    
    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
    .info-item { background: #fcfcfc; padding: 15px; border-radius: 12px; border: 1px solid #eee; }
    .info-item label { display: block; font-size: 0.8rem; color: #888; font-weight: 600; margin-bottom: 5px; }
    .info-item span { font-size: 1.05rem; font-weight: 700; color: #333; }

    .docs-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; margin-top: 20px; }
    .doc-card { border-radius: 12px; overflow: hidden; border: 1px solid #ddd; background: #eee; cursor: pointer; transition: 0.2s; position: relative; }
    .doc-card:hover { transform: scale(1.05); box-shadow: var(--shadow-lg); z-index: 5; }
    .doc-card img { width: 100%; height: 150px; object-fit: cover; }
    
    @media (max-width: 768px) {
        .profile-header { grid-template-columns: 1fr; text-align: center; }
        .profile-img-large { margin: 0 auto; }
    }
    .lib-dropdown { position: relative; display: inline-block; }
    .lib-dropdown-content { 
        display: none; position: absolute; left: 0; background-color: #fff; 
        min-width: 180px; box-shadow: 0 8px 16px rgba(0,0,0,0.2); 
        z-index: 1000; border-radius: 8px; overflow: hidden; border: 1px solid #ddd;
    }
    .lib-dropdown-content a, .lib-dropdown-content button { 
        color: #333; padding: 12px 16px; text-decoration: none; 
        display: block; width: 100%; text-align: right; border: none; 
        background: none; cursor: pointer; font-family: inherit; font-size: 0.95rem;
    }
    .lib-dropdown-content a:hover, .lib-dropdown-content button:hover { background-color: #f1f1f1; color: var(--maroon); }
    .lib-dropdown:hover .lib-dropdown-content { display: block; }
</style>

<?php if (isset($_SESSION['msg'])): ?>
    <div class="lib-toast <?php echo $_SESSION['msg_type']; ?>" style="position: static; margin-bottom: 20px; width: 100%; transform: none;">
        <?php echo $_SESSION['msg']; unset($_SESSION['msg']); unset($_SESSION['msg_type']); ?>
    </div>
<?php endif; ?>

<div class="profile-header">
    <div class="profile-img-large">
        <?php 
        $p_img = $staff['profile_image'];
        if (empty($p_img)) $p_img = "assets/imgae/user.png";
        ?>
        <img src="stream_image.php?path=<?php echo urlencode($p_img); ?>" alt="Profile" onerror="this.src='assets/imgae/user.png'">

    </div>
    <div class="profile-main-info">
        <span class="lib-badge lib-badge-maroon rank-badge"><?php echo htmlspecialchars($staff['rank_name'] ?? ''); ?></span>
        <h2><?php echo htmlspecialchars($staff['full_name'] ?? ''); ?></h2>
        <div style="font-size: 1.1rem; color: var(--gold); font-weight: bold; margin-bottom: 10px; direction: ltr; text-align: right;">
            📞 <?php echo htmlspecialchars($staff['phone'] ?: '-'); ?>
        </div>
        <p style="font-size: 1.2rem; color: #666; margin-bottom: 20px;">
            یەکە: <strong><?php echo htmlspecialchars($staff['unit_name'] ?? ''); ?></strong> 
            <?php if(!empty($staff['sub_unit'])): ?> | بەش: <strong><?php echo htmlspecialchars($staff['sub_unit']); ?></strong><?php endif; ?>
            | ڕشتە: <?php echo htmlspecialchars($staff['branch'] ?? ''); ?>
        </p>
        
        <div style="margin-bottom: 20px; background: #f0f4f8; padding: 10px 15px; border-radius: 10px; display: inline-flex; align-items: center; gap: 15px; border: 1px solid #d1d5db;">
            <div style="font-weight: 700; color: #374151; font-size: 0.9rem;">📊 تەواوکاری بەڵگەنامەکان:</div>
            <div style="width: 150px; height: 10px; background: #e5e7eb; border-radius: 5px; overflow: hidden; position: relative;">
                <div style="width: <?php echo $completion_percent; ?>%; height: 100%; background: <?php echo $completion_percent == 100 ? '#10b981' : '#f59e0b'; ?>; transition: 0.5s;"></div>
            </div>
            <div style="font-weight: 800; color: <?php echo $completion_percent == 100 ? '#059669' : '#d97706'; ?>; font-size: 1rem;"><?php echo $completion_percent; ?>%</div>
        </div>
        
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="staff_edit.php?id=<?php echo $id; ?>" class="lib-btn lib-btn-warning">✏️ دەستکاری زانیارییەکان</a>
            <a href="staff_print.php?id=<?php echo $id; ?>" target="_blank" class="lib-btn lib-btn-gold">🖨️ چاپکردنی پرۆفیشناڵ</a>
            <a href="staff_delete.php?id=<?php echo $id; ?>" class="lib-btn lib-btn-danger" onclick="return confirm('ئایا دڵنیایت لە سڕینەوەی ئەم کارمەندە؟ هەموو زانیاری و بەڵگەنامەکانی دەسڕێنەوە!')">🗑️ سڕینەوەی کارمەند</a>
            <a href="staff_list.php" class="lib-btn lib-btn-primary">🔙 گەڕانەوە</a>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <div class="lib-card">
        <div class="card-header"><h3>📌 زانیارییە کەسی و سەربازییەکان</h3></div>
        <div style="padding: 25px;">
            <div class="info-grid">
                <div class="info-item"><label>پایە:</label><span><?php echo htmlspecialchars($staff['position'] ?: '-'); ?></span></div>
                <div class="info-item"><label>ئیش / کار:</label><span><?php echo htmlspecialchars($staff['job'] ?: '-'); ?></span></div>
                <div class="info-item"><label>ساڵ و شوێنی لە دایک بوون:</label><span><?php echo $staff['birth_year']; ?> - <?php echo htmlspecialchars($staff['birth_place'] ?? ''); ?></span></div>
                
                <div class="info-item">
                    <label>ڕەگەز و باری خێزانی:</label>
                    <span>
                        (<?php echo $staff['gender'] ?: 'نێر'; ?> - 
                         <?php echo $staff['marital_status'] ?: 'سەڵت'; ?> - 
                         <?php echo (int)($staff['children_count'] ?? 0); ?> منداڵ)
                    </span>
                </div>

                <div class="info-item"><label>صنف (جۆری چەک):</label><span><?php echo htmlspecialchars($staff['military_branch_sinf'] ?: '-'); ?></span></div>
                
                <div class="info-item">
                    <label>بڕوانامە (سەربازی / مەدەنی):</label>
                    <span>
                        (<?php echo htmlspecialchars($staff['military_education'] ?: 'نییە'); ?> / 
                         <?php echo htmlspecialchars($staff['education'] ?: 'نییە'); ?>)
                    </span>
                </div>

                <div class="info-item"><label>ژمارەی ناسنامە:</label><span><?php echo htmlspecialchars($staff['id_number'] ?: '-'); ?></span></div>
                
                <div class="info-item">
                    <label>بەرواری دوا پلە / شایستە:</label>
                    <span>
                        <?php echo $staff['last_promotion_date']; ?> - 
                        <span style="color: <?php echo (strtotime($staff['promotion_due_date']) <= time()) ? 'red' : 'green'; ?>">
                            <?php echo $staff['promotion_due_date']; ?>
                        </span>
                    </span>
                </div>

                <div class="info-item"><label>ڕشتە:</label><span><?php echo htmlspecialchars($staff['branch'] ?: '-'); ?></span></div>
                <div class="info-item"><label>ئاستی خوێندن:</label><span><?php echo htmlspecialchars($staff['education_level'] ?: '-'); ?></span></div>
                <div class="info-item" style="background: #fffdf0;">
                    <label>📝 دامەزراندن:</label>
                    <span style="font-size: 0.9rem;"><?php echo htmlspecialchars($staff['appointment_order'] ?: '-'); ?> (<?php echo $staff['appointment_date'] ?: '-'; ?>)</span>
                </div>
                <div class="info-item" style="background: #fff5f5; border-color: #ffdada;">
                    <label style="color: #c62828;">غیابات و سزا:</label>
                    <span style="color: #c62828;"><?php echo (int)$staff['absence_penalty_days']; ?> ڕۆژ</span>
                </div>

                <!-- Identity Info (Full Width) -->
                <div class="info-item" style="grid-column: 1 / -1; background: #f9f9f9; border-top: 2px solid #eee; margin-top: 10px;">
                    <label style="color: #666; font-size: 0.85rem; margin-bottom: 10px; display: block;">🆔 زانیارییە ناسنامەییەکان:</label>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
                        <div><label style="color: #888; font-size: 0.75rem;">ناسنامەی مەدەنی:</label><br><span><?php echo htmlspecialchars($staff['civil_id'] ?: '-'); ?></span></div>
                        <div><label style="color: #888; font-size: 0.75rem;">فۆڕمی خۆراک:</label><br><span><?php echo htmlspecialchars($staff['form_number'] ?: '-'); ?></span></div>
                        <div><label style="color: #888; font-size: 0.75rem;">ژمارەی دەنگدان:</label><br><span><?php echo htmlspecialchars($staff['voting_number'] ?: '-'); ?></span></div>
                        <div><label style="color: #888; font-size: 0.75rem;">بایۆ مەتری:</label><br><span><?php echo htmlspecialchars($staff['biometric_number'] ?: '-'); ?></span></div>
                    </div>
                </div>

                <div class="info-item" style="grid-column: 1 / -1;"><label>ناونیشان:</label><span><?php echo htmlspecialchars($staff['city'] . " - " . $staff['town'] . " - " . $staff['district'] . " - " . $staff['village']); ?></span></div>
            </div>

            <?php if (!empty($staff['notes'])): ?>
                <div class="lib-card" style="margin-top: 15px; border-right: 5px solid var(--gold);">
                    <div style="padding: 20px;">
                        <h4 style="color: var(--maroon); margin-bottom: 10px;">📝 تێبینی:</h4>
                        <p style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.6;"><?php echo htmlspecialchars($staff['notes']); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div style="margin-top: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="lib-card" style="margin: 0; border-right: 5px solid #4a90e2;">
                    <div style="padding: 15px;">
                        <h4 style="color: #4a90e2; margin-bottom: 10px;">🩺 پڕۆفایلی تەندروستی</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div><label style="color: #888; font-size: 0.8rem;">گرووپی خوێن:</label><br><strong><?php echo $staff['blood_group'] ?: '-'; ?></strong></div>
                            <div><label style="color: #888; font-size: 0.8rem;">لێهاتوویی جەستەیی:</label><br><strong><?php echo htmlspecialchars($staff['physical_fitness'] ?: '-'); ?></strong></div>
                            <div style="grid-column: 1 / -1;"><label style="color: #888; font-size: 0.8rem;">باری تەندروستی:</label><br><p style="font-size: 0.9rem;"><?php echo nl2br(htmlspecialchars($staff['health_status'] ?: 'جێگیرە')); ?></p></div>
                        </div>
                    </div>
                </div>
                <div class="lib-card" style="margin: 0; border-right: 5px solid #27ae60;">
                    <div style="padding: 15px;">
                        <h4 style="color: #27ae60; margin-bottom: 10px;">🗣️ زمان و لێهاتوویی</h4>
                        <p style="font-size: 1rem;"><?php echo htmlspecialchars($staff['languages'] ?: 'تۆمار نەکراوە'); ?></p>
                        <hr style="margin: 10px 0; border: 0; border-top: 1px solid #eee;">
                        <div><label style="color: #888; font-size: 0.8rem;">نیشانەی ئازایەتی:</label><br><strong><?php echo htmlspecialchars($staff['honors_medals'] ?: '-'); ?></strong></div>
                        <div style="margin-top: 5px;"><label style="color: #888; font-size: 0.8rem;">برینداربوون:</label><br><strong><?php echo htmlspecialchars($staff['injuries'] ?: '-'); ?></strong></div>
                    </div>
                </div>
            </div>

            <?php
            $history = $conn->query("SELECT * FROM staff_service_history WHERE staff_id = $id ORDER BY id ASC");
            if ($history->num_rows > 0):
            ?>
                <div style="margin-top: 35px; border-top: 2px solid #eee; padding-top: 25px;">
                    <h4 style="margin-bottom: 20px; color: var(--maroon); display: flex; align-items: center; gap: 10px;">
                        📜 تۆماری خزمەت و گواستنەوەکان
                    </h4>
                    <table class="lib-table" style="min-width: 100%;">
                        <thead style="background: #fdf2f2;">
                            <tr>
                                <th>ڕیزبەندی</th>
                                <th>یەکە / بەش</th>
                                <th>پایە (ناونیشان)</th>
                                <th>ماوەی خزمەت</th>
                                <th>فەرمانی گواستنەوە</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i=1; while($h = $history->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td style="font-weight: 700;"><?php echo htmlspecialchars($h['unit_name']); ?></td>
                                    <td><?php echo htmlspecialchars($h['position']); ?></td>
                                    <td><span class="lib-badge lib-badge-gold"><?php echo htmlspecialchars($h['service_period']); ?></span></td>
                                    <td><code><?php echo htmlspecialchars($h['order_number']); ?></code></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($courses)): ?>
                <div style="margin-top: 30px;">
                    <h4 style="margin-bottom: 15px; color: var(--maroon);">🎓 خولە سەربازییەکان</h4>
                    <table class="lib-table" style="min-width: 100%;">
                        <thead><tr><th>ناوی خول</th><th>شوێن</th><th>بەروار</th><th>پلە</th><th>تێبینی</th></tr></thead>
                        <tbody>
                            <?php foreach ($courses as $c): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($c['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($c['place']); ?></td>
                                    <td><?php echo $c['course_date']; ?></td>
                                    <td><span class="lib-badge lib-badge-green"><?php echo htmlspecialchars($c['grade'] ?? '-'); ?></span></td>
                                    <td><?php echo htmlspecialchars($c['notes']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div style="margin-top: 30px; border-top: 2px solid #eee; padding-top: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h4 style="color: var(--maroon);">📅 تۆماری غیابات و مۆڵەتەکان</h4>
                    <a href="staff_attendance.php?search=<?php echo urlencode($staff['full_name']); ?>" class="lib-btn lib-btn-warning" style="font-size: 0.8rem;">بینینی هەموو</a>
                </div>
                <?php
                $attendance_res = $conn->query("SELECT * FROM staff_attendance WHERE staff_id = $id ORDER BY start_date DESC LIMIT 10");
                if ($attendance_res && $attendance_res->num_rows > 0):
                ?>
                <table class="lib-table" style="min-width: 100%;">
                    <thead style="background: #fff8f8;">
                        <tr>
                            <th>جۆر</th>
                            <th>لە ڕێکەوتی</th>
                            <th>تا ڕێکەوتی</th>
                            <th>ماوە</th>
                            <th>فەرمان</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($at = $attendance_res->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $type_name = $at['leave_type'];
                                    $badge = 'lib-badge-gold';
                                    if($type_name == 'Absence') { $badge = 'lib-badge-red'; $type_name = 'غیابات'; }
                                    else if($type_name == 'Annual Leave') { $badge = 'lib-badge-green'; $type_name = 'مۆڵەتی ئاسایی'; }
                                    else if($type_name == 'Sick Leave') { $badge = 'lib-badge-maroon'; $type_name = 'مۆڵەتی نەخۆشی'; }
                                    else if($type_name == 'Official Mission') { $badge = 'lib-badge-gold'; $type_name = 'ئیفاد'; }
                                    ?>
                                    <span class="lib-badge <?php echo $badge; ?>"><?php echo $type_name; ?></span>
                                </td>
                                <td><?php echo $at['start_date']; ?></td>
                                <td><?php echo $at['end_date']; ?></td>
                                <td><strong><?php echo $at['duration_days']; ?></strong> ڕۆژ</td>
                                <td><?php echo htmlspecialchars($at['order_number'] ?: '-'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div style="padding: 15px; text-align: center; color: #888; background: #f9f9f9; border-radius: 8px;">هیچ تۆمارێکی غیابات یان مۆڵەت نییە.</div>
                <?php endif; ?>
            </div>

            <!-- Assets & Equipment Section -->

            <div style="margin-top: 40px; border-top: 2px solid #eee; padding-top: 30px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4 style="color: var(--maroon); display: flex; align-items: center; gap: 10px;">
                        📦 زممە و مەوجودات (کەلوپەلەکان)
                    </h4>
                    <div style="display: flex; gap: 10px;">
                        <div class="lib-dropdown">
                            <button class="lib-btn lib-btn-gold">🖨️ چاپی لیستەکان ▾</button>
                            <div class="lib-dropdown-content">
                                <a href="staff_assets_print.php?id=<?php echo $id; ?>" target="_blank">📜 لیستی مەوجودات</a>
                                <button type="button" onclick="document.getElementById('handoverBox').style.display='flex'">📑 لیستی ئاڵوگۆڕ</button>
                            </div>
                        </div>
                        <button type="button" class="lib-btn lib-btn-primary" onclick="document.getElementById('assetBox').style.display='block'; document.getElementById('editAssetBox').style.display='none'">➕ زیادکردنی زممە</button>
                    </div>
                </div>

                <!-- Add Asset Form -->
                <div id="assetBox" style="display:none; background: #f0f4f8; padding: 25px; border-radius: 12px; border: 1px solid #bcccdc; margin-bottom: 25px;">
                    <form action="staff_asset_action.php" method="POST">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="staff_id" value="<?php echo $id; ?>">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                            <div class="lib-field">
                                <label>جۆری کەلوپەل:</label>
                                <select name="category" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border);">
                                    <option value="Weapon">چەک و تەقەمەنی</option>
                                    <option value="Vehicle">ئۆتۆمبێل و ئامێر</option>
                                    <option value="Property">خانووبەرە و شوێن</option>
                                    <option value="Tech">تەکنەلۆژیا و نووسینگە</option>
                                    <option value="Other">تر</option>
                                </select>
                            </div>
                            <div class="lib-field"><label>ناوی پارچە:</label><input type="text" name="item_name" placeholder="بۆ نموونە: کڵاشینکۆف" required></div>
                            <div class="lib-field"><label>ژمارەی زنجیرەیی (S/N):</label><input type="text" name="serial_number"></div>
                            <div class="lib-field"><label>باری فیزیکی:</label><input type="text" name="physical_condition" placeholder="نوێ، بەکارهاتوو..."></div>
                            <div class="lib-field"><label>بەرواری وەرگرتن:</label><input type="date" name="received_date" value="<?php echo date('Y-m-d'); ?>"></div>
                            <div class="lib-field"><label>موستەنەد/فەرمان:</label><input type="text" name="order_number"></div>
                        </div>
                        <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: flex-end;">
                            <button type="submit" class="lib-btn lib-btn-success">💾 پاشەکەوتکردن</button>
                            <button type="button" class="lib-btn lib-btn-danger" onclick="document.getElementById('assetBox').style.display='none'">❌ پاشگەزبوونەوە</button>
                        </div>
                    </form>
                </div>

                <!-- Edit Asset Form -->
                <div id="editAssetBox" style="display:none; background: #fffdf0; padding: 25px; border-radius: 12px; border: 1px solid #e6db55; margin-bottom: 25px;">
                    <form action="staff_asset_action.php" method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="staff_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="asset_id" id="edit_asset_id">
                        <h4 style="margin-bottom: 15px; color: #856404;">✏️ دەستکاریکردنی زممە</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px;">
                            <div class="lib-field">
                                <label>جۆری کەلوپەل:</label>
                                <select name="category" id="edit_category" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border);">
                                    <option value="Weapon">چەک و تەقەمەنی</option>
                                    <option value="Vehicle">ئۆتۆمبێل و ئامێر</option>
                                    <option value="Property">خانووبەرە و شوێن</option>
                                    <option value="Tech">تەکنەلۆژیا و نووسینگە</option>
                                    <option value="Other">تر</option>
                                </select>
                            </div>
                            <div class="lib-field"><label>ناوی پارچە:</label><input type="text" name="item_name" id="edit_item_name" required></div>
                            <div class="lib-field"><label>ژمارەی زنجیرەیی (S/N):</label><input type="text" name="serial_number" id="edit_serial_number"></div>
                            <div class="lib-field"><label>باری فیزیکی:</label><input type="text" name="physical_condition" id="edit_physical_condition"></div>
                            <div class="lib-field"><label>بەرواری وەرگرتن:</label><input type="date" name="received_date" id="edit_received_date"></div>
                            <div class="lib-field"><label>موستەنەد/فەرمان:</label><input type="text" name="order_number" id="edit_order_number"></div>
                            <div class="lib-field">
                                <label>دۆخی زممە:</label>
                                <select name="status" id="edit_status" onchange="toggleEditReturnDate()" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border);">
                                    <option value="Held">لای کارمەندە</option>
                                    <option value="Returned">گەڕاوەتەوە</option>
                                </select>
                            </div>
                            <div class="lib-field" id="edit_return_date_container"><label>بەرواری گەڕانەوە:</label><input type="date" name="return_date" id="edit_return_date"></div>
                        </div>
                        <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: flex-end;">
                            <button type="submit" class="lib-btn lib-btn-success">💾 پاشەکەوتکردنی گۆڕانکارییەکان</button>
                            <button type="button" class="lib-btn lib-btn-danger" onclick="document.getElementById('editAssetBox').style.display='none'">❌ پاشگەزبوونەوە</button>
                        </div>
                    </form>
                </div>

                <?php
                // Self-healing check for table
                $conn->query("CREATE TABLE IF NOT EXISTS staff_assets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    staff_id INT,
                    category ENUM('Weapon', 'Vehicle', 'Property', 'Tech', 'Other') DEFAULT 'Other',
                    item_name VARCHAR(255) NOT NULL,
                    serial_number VARCHAR(100),
                    physical_condition VARCHAR(100),
                    received_date DATE,
                    order_number VARCHAR(100),
                    status ENUM('Held', 'Returned') DEFAULT 'Held',
                    return_date DATE,
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (staff_id) REFERENCES staff_members(id) ON DELETE CASCADE
                )");

                $assets = $conn->query("SELECT * FROM staff_assets WHERE staff_id = $id ORDER BY status ASC, received_date DESC");
                if ($assets->num_rows > 0):
                ?>
                    <table class="lib-table" style="min-width: 100%;">
                        <thead style="background: #f0f4f8;">
                            <tr>
                                <th>جۆر</th>
                                <th>ناوی پارچە (S/N)</th>
                                <th>باری فیزیکی</th>
                                <th>بەروار</th>
                                <th>موستەنەد</th>
                                <th>دۆخی زممە</th>
                                <th>کردار</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($a = $assets->fetch_assoc()): 
                                $is_returned = ($a['status'] == 'Returned');
                            ?>
                                <tr style="<?php echo $is_returned ? 'opacity: 0.6; background: #f9f9f9;' : ''; ?>">
                                    <td>
                                        <?php 
                                            $cats = ['Weapon'=>'🔫','Vehicle'=>'🚗','Property'=>'🏠','Tech'=>'💻','Other'=>'📦'];
                                            echo $cats[$a['category']] ?? '📦';
                                        ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="font-weight: 700;"><?php echo htmlspecialchars($a['item_name']); ?></div>
                                        <div style="font-size: 0.75rem; color: #888;">S/N: <?php echo htmlspecialchars($a['serial_number'] ?: '-'); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($a['physical_condition']); ?></td>
                                    <td><?php echo $a['received_date']; ?></td>
                                    <td><code><?php echo htmlspecialchars($a['order_number']); ?></code></td>
                                    <td>
                                        <span class="lib-badge <?php echo $is_returned ? 'lib-badge-green' : 'lib-badge-gold'; ?>">
                                            <?php echo $is_returned ? '✅ گەڕاوەتەوە' : '📦 لای کارمەندە'; ?>
                                        </span>
                                        <?php if($is_returned): ?><div style="font-size: 0.7rem; margin-top: 3px;">لە: <?php echo $a['return_date']; ?></div><?php endif; ?>
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <div style="display: flex; gap: 5px; justify-content: center;">
                                            <button type="button" class="lib-btn lib-btn-primary" style="padding: 5px 8px; font-size: 0.75rem;" 
                                                    onclick='openEditAsset(<?php echo json_encode($a); ?>)' title="دەستکاری">✏️</button>
                                            
                                            <a href="staff_asset_action.php?action=delete&id=<?php echo $a['id']; ?>&staff_id=<?php echo $id; ?>" 
                                               class="lib-btn lib-btn-danger" style="padding: 5px 8px; font-size: 0.75rem;"
                                               onclick="return confirm('ئایا دڵنیایت لە سڕینەوەی ئەم زممەیە؟')" title="سڕینەوە">🗑️</a>

                                            <?php if(!$is_returned): ?>
                                                <a href="staff_asset_action.php?action=return&id=<?php echo $a['id']; ?>&staff_id=<?php echo $id; ?>" 
                                                   class="lib-btn lib-btn-warning" style="padding: 5px 8px; font-size: 0.75rem;"
                                                   onclick="return confirm('ئایا دڵنیایت لە گەڕانەوەی ئەم زممەیە؟')" title="گەڕانەوە">🔄</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="padding: 30px; text-align: center; color: #bbb; background: #fbfbfb; border-radius: 12px; border: 1px dashed #ddd;">هیچ زممەیەک تۆمار نەکراوە بۆ ئەم کارمەندە</div>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </div>

    <!-- Conduct Section (Penalties & Awards) -->
    <div class="lib-card" style="margin-top: 30px;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>⚖️ سزا و پاداشتەکان (مێژووی ڕەوشت)</h3>
            <button type="button" class="lib-btn lib-btn-primary" onclick="document.getElementById('conductBox').style.display='flex'">➕ تۆمارکردنی نوێ</button>
        </div>
        <div style="padding: 20px;">
            <?php if (empty($conduct_records)): ?>
                <div style="text-align: center; padding: 30px; color: #888;">هیچ تۆمارێکی سزا و پاداشت بۆ ئەم ئەفسەرە نییە.</div>
            <?php else: ?>
                <div class="lib-table-wrapper">
                    <table class="lib-table">
                        <thead>
                            <tr>
                                <th>جۆر</th>
                                <th>ڕێکەوت</th>
                                <th>سزا / پاداشت</th>
                                <th>جۆری تاوان / هۆکار</th>
                                <th>تێبینی</th>
                                <th>کردار</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($conduct_records as $c): ?>
                            <tr>
                                <td>
                                    <span class="lib-badge <?php echo $c['type'] == 'penalty' ? 'lib-badge-red' : 'lib-badge-green'; ?>">
                                        <?php echo $c['type'] == 'penalty' ? 'سزا' : 'پاداشت'; ?>
                                    </span>
                                </td>
                                <td><?php echo $c['conduct_date']; ?></td>
                                <td><strong><?php echo htmlspecialchars($c['penalty_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['crime_type']); ?></td>
                                <td><?php echo htmlspecialchars($c['notes']); ?></td>
                                <td>
                                    <a href="staff_conduct_delete.php?id=<?php echo $c['id']; ?>&staff_id=<?php echo $id; ?>" 
                                       class="lib-btn lib-btn-danger" style="padding: 4px 8px;" onclick="return confirm('ئایە دڵنیایت لە سڕینەوەی ئەم تۆمارە؟')">🗑️</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="lib-card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>📷 ئەلبوم و بەڵگەنامەکان</h3>
            <div style="display: flex; gap: 10px;">
                <a href="staff_checklist_print.php?id=<?php echo $id; ?>" class="lib-btn lib-btn-gold" target="_blank">🖨️ چاپی چەک لیست</a>
                <a href="staff_conduct_print.php?id=<?php echo $id; ?>" class="lib-btn lib-btn-primary" target="_blank">📜 چاپی خشتەی ڕەوشت</a>
                <button type="button" class="lib-btn lib-btn-primary" onclick="document.getElementById('uploadBox').style.display='block'">➕ زیادکردنی وێنە</button>
            </div>
        </div>
        <div style="padding: 20px;">
            <!-- Upload Box (Hidden by default) -->
            <div id="uploadBox" style="display:none; background: #fdf2f2; padding: 20px; border-radius: 12px; border: 1px solid var(--maroon); margin-bottom: 20px;">
                <form action="staff_upload_doc.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="staff_id" value="<?php echo $id; ?>">
                    <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: end;">
                        <div class="lib-field" style="margin:0;">
                            <label>ناوی بەڵگەنامە (تایتڵ):</label>
                            <input type="text" name="doc_title" id="doc_title_input" placeholder="بۆ نموونە: فۆڕمی خۆراک" required list="mandatory_titles">
                            <datalist id="mandatory_titles">
                                <?php foreach($mandatory_docs as $m): ?>
                                    <option value="<?php echo $m; ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="lib-field" style="margin:0;">
                            <label>هەڵبژاردنی فایل (وێنە یان PDF):</label>
                            <input type="file" name="doc_file" accept="image/*,application/pdf" required>
                        </div>
                        <div style="display:flex; gap:5px;">
                            <button type="submit" class="lib-btn lib-btn-success">💾 پاشەکەوت</button>
                            <button type="button" class="lib-btn lib-btn-danger" onclick="document.getElementById('uploadBox').style.display='none'">❌</button>
                        </div>
                    </div>
                </form>
            </div>

            <div style="margin-bottom: 25px; background: #fff; border: 1px solid #edf2f7; border-radius: 12px; overflow: hidden;">
                <div style="background: #f8fafc; padding: 12px 20px; border-bottom: 1px solid #edf2f7; font-weight: 700; color: #4a5568; display: flex; justify-content: space-between;">
                    <span>📋 چەک لیستی بەڵگەنامە پێویستەکان</span>
                    <span style="font-size: 0.8rem; color: #718096;"><?php echo $completed_docs_count; ?> لە ناو <?php echo count($mandatory_docs); ?> تەواو بووە</span>
                </div>
                <div style="padding: 15px; display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px;">
                    <?php foreach($mandatory_docs as $m_doc): 
                        $is_done = in_array($m_doc, $existing_doc_titles);
                    ?>
                        <div style="display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-radius: 8px; background: <?php echo $is_done ? '#f0fdf4' : '#fff5f5'; ?>; border: 1px solid <?php echo $is_done ? '#bcf0da' : '#fed7d7'; ?>;">
                            <span style="font-size: 1.2rem;"><?php echo $is_done ? '✅' : '❌'; ?></span>
                            <span style="font-size: 0.9rem; font-weight: 600; color: <?php echo $is_done ? '#166534' : '#9b1c1c'; ?>;"><?php echo $m_doc; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <p style="font-size: 0.85rem; color: #888;">ئەمانە هەموو ئەو وێنانەن کە لە فۆڵدەری ئەرشیفی کارمەنددا هەن:</p>
            <div class="docs-gallery">
                <?php if (empty($docs)): ?>
                    <div style="grid-column: 1 / -1; padding: 40px; text-align: center; color: #bbb;">هیچ وێنەیەک نییە</div>
                <?php else: ?>
                    <?php foreach ($docs as $d): ?>
                        <div class="doc-card">
                            <div onclick="window.open('stream_image.php?path=<?php echo urlencode($d['file_path']); ?>', '_blank')">
                                <?php if ($d['file_type'] == 'pdf'): ?>
                                    <div style="height: 150px; display: flex; align-items: center; justify-content: center; background: #fff; font-size: 3rem;">📄</div>
                                <?php else: ?>
                                    <img src="stream_image.php?path=<?php echo urlencode($d['file_path']); ?>" loading="lazy">
                                <?php endif; ?>
                            </div>
                            <div style="padding: 8px; font-size: 0.85rem; background: #fff; text-align: center; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 700; color: var(--maroon); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($d['doc_title']); ?>">
                                    <?php echo htmlspecialchars($d['doc_title']); ?>
                                </span>
                                <a href="staff_delete_doc.php?id=<?php echo $d['id']; ?>&staff_id=<?php echo $id; ?>" 
                                   onclick="return confirm('ئایا دڵنیایت لە سڕینەوەی ئەم بەڵگەنامەیە؟')" 
                                   style="color: red; text-decoration: none; font-size: 1.1rem;" title="سڕینەوە">🗑️</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 25px; border-top: 1px solid #eee; padding-top: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                    <label style="font-size: 0.8rem; color: #888; font-weight: 700;">📍 ڕێڕەوی فۆڵدەر لە ناو کۆمپیوتەر:</label>
                    <?php if (!$folder_exists): ?>
                        <a href="staff_create_folder.php?id=<?php echo $id; ?>&path=<?php echo urlencode($hierarchical_path); ?>" 
                           class="lib-btn lib-btn-danger" style="padding: 4px 12px; font-size: 0.75rem;">📁 دروستکردنی فۆڵدەر</a>
                    <?php else: ?>
                        <span style="color: #27ae60; font-size: 0.75rem; font-weight: 700;">✅ فۆڵدەرەکە هەیە</span>
                    <?php endif; ?>
                </div>
                <code style="background: #f5f5f5; padding: 10px; border-radius: 8px; display: block; direction: ltr; text-align: left; font-size: 0.85rem; border: 1px solid <?php echo $folder_exists ? '#27ae60' : '#e74c3c'; ?>;">
                    <?php echo htmlspecialchars($hierarchical_path); ?>
                </code>
            </div>
        </div>
    </div>
</div>

<script>
function openEditAsset(asset) {
    document.getElementById('editAssetBox').style.display = 'block';
    document.getElementById('assetBox').style.display = 'none';
    
    document.getElementById('edit_asset_id').value = asset.id;
    document.getElementById('edit_category').value = asset.category;
    document.getElementById('edit_item_name').value = asset.item_name;
    document.getElementById('edit_serial_number').value = asset.serial_number;
    document.getElementById('edit_physical_condition').value = asset.physical_condition;
    document.getElementById('edit_received_date').value = asset.received_date;
    document.getElementById('edit_order_number').value = asset.order_number;
    document.getElementById('edit_status').value = asset.status;
    document.getElementById('edit_return_date').value = asset.return_date || '';
    
    toggleEditReturnDate();
    
    // Scroll to the edit box
    document.getElementById('editAssetBox').scrollIntoView({ behavior: 'smooth' });
}

function toggleEditReturnDate() {
    const status = document.getElementById('edit_status').value;
    document.getElementById('edit_return_date_container').style.display = (status === 'Returned') ? 'block' : 'none';
}
</script>

    <!-- Conduct Entry Modal -->
    <div id="conductBox" class="lib-overlay" style="display: none; align-items: center; justify-content: center; z-index: 9999;" onclick="if(event.target==this)this.style.display='none'">
        <div class="lib-modal" style="max-width: 500px; width: 90%; position: relative; left: auto; top: auto; transform: none; opacity: 1; pointer-events: auto; display: flex; flex-direction: column; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div class="lib-modal-header">
                <h3>تۆمارکردنی سزا یان پاداشت</h3>
                <span class="lib-modal-close" onclick="document.getElementById('conductBox').style.display='none'">&times;</span>
            </div>
            <form action="staff_conduct_save.php" method="POST">
                <input type="hidden" name="staff_id" value="<?php echo $id; ?>">
                <div class="lib-modal-body" style="padding: 20px;">
                    <div class="lib-field" style="margin-bottom: 15px;">
                        <label>جۆری تۆمار</label>
                        <select name="type" class="lib-input">
                            <option value="penalty">سزا (Penalty)</option>
                            <option value="award">پاداشت (Award)</option>
                        </select>
                    </div>
                    <div class="lib-field" style="margin-bottom: 15px;">
                        <label>ڕێکەوت</label>
                        <input type="date" name="conduct_date" value="<?php echo date('Y-m-d'); ?>" class="lib-input" required>
                    </div>
                    <div class="lib-field" style="margin-bottom: 15px;">
                        <label>ناوی سزا / پاداشت</label>
                        <input type="text" name="penalty_name" placeholder="وەک: سەرزەنشت، ١٠ ڕۆژ زیندانی..." class="lib-input" required>
                    </div>
                    <div class="lib-field" style="margin-bottom: 15px;">
                        <label>جۆری تاوان / هۆکار</label>
                        <input type="text" name="crime_type" placeholder="وەک: دواکەوتن، جێبەجێکردنی فەرمان..." class="lib-input">
                    </div>
                    <div class="lib-field">
                        <label>تێبینی</label>
                        <textarea name="notes" rows="2" class="lib-input"></textarea>
                    </div>
                </div>
                <div class="lib-modal-footer">
                    <button type="button" class="lib-btn" onclick="document.getElementById('conductBox').style.display='none'">پاشگەزبوونەوە</button>
                    <button type="submit" class="lib-btn lib-btn-primary">💾 پاشەکەوتکردن</button>
                </div>
            </form>
        </div>
    </div>
</div>
    <!-- Handover Selection Modal -->
    <div id="handoverBox" class="lib-overlay" style="display: none; align-items: center; justify-content: center; z-index: 10000;" onclick="if(event.target==this)this.style.display='none'">
        <div class="lib-modal" style="max-width: 450px; width: 90%; position: relative; left: auto; top: auto; transform: none; opacity: 1; pointer-events: auto; display: flex; flex-direction: column; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <div class="lib-modal-header">
                <h3>ئامادەکردنی فۆڕمی دەور و تەسلیم</h3>
                <span class="lib-modal-close" onclick="document.getElementById('handoverBox').style.display='none'">&times;</span>
            </div>
            <div class="lib-modal-body" style="padding: 25px;">
                <p style="font-size: 0.9rem; color: #666; margin-bottom: 20px;">تکایە ئەو ئەفسەرە هەڵبژێرە کە کەلوپەلەکان (زممە) وەردەگرێت:</p>
                
                <!-- Search Input -->
                <div class="lib-field" style="margin-bottom: 10px;">
                    <input type="text" id="officerSearch" class="lib-input" placeholder="🔍 گەڕان بەپێی پلە یان ناو..." onkeyup="filterOfficers()">
                </div>

                <div class="lib-field">
                    <label>هەڵبژاردنی ئەفسەری هاتوو:</label>
                    <select id="to_officer_id" class="lib-input" style="width: 100%; height: 180px;" size="5">
                        <option value="0" selected>-- بە بەتاڵی جێی بهێڵە --</option>
                        <?php foreach($all_staff as $s): ?>
                            <option value="<?php echo $s['id']; ?>" class="officer-option">
                                <?php echo htmlspecialchars($s['rank_name'] . ' ' . $s['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="lib-modal-footer">
                <button type="button" class="lib-btn" onclick="document.getElementById('handoverBox').style.display='none'">پاشگەزبوونەوە</button>
                <button type="button" class="lib-btn lib-btn-success" onclick="printHandover()">🖨️ بینینی فۆڕم بۆ چاپ</button>
            </div>
        </div>
    </div>

    <script>
    function filterOfficers() {
        const input = document.getElementById('officerSearch');
        const filter = input.value.toLowerCase();
        const select = document.getElementById('to_officer_id');
        const options = select.getElementsByTagName('option');

        for (let i = 0; i < options.length; i++) {
            if (options[i].value == "0") continue;
            const txtValue = options[i].textContent || options[i].innerText;
            if (txtValue.toLowerCase().indexOf(filter) > -1) {
                options[i].style.display = "";
            } else {
                options[i].style.display = "none";
            }
        }
    }

    function printHandover() {
        const toId = document.getElementById('to_officer_id').value;
        const fromId = '<?php echo $id; ?>';
        window.open(`staff_handover_print.php?from_id=${fromId}&to_id=${toId}`, '_blank');
        document.getElementById('handoverBox').style.display = 'none';
    }
    </script>
</div>
<?php require_once 'includes/layout_end.php'; ?>
