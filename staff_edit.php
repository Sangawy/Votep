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

if ($id > 0) {
    // Self-healing: Ensure table and columns exist
    $conn->query("CREATE TABLE IF NOT EXISTS staff_service_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id INT,
        unit_name VARCHAR(255),
        position VARCHAR(255),
        service_period VARCHAR(255),
        order_number VARCHAR(255),
        FOREIGN KEY (staff_id) REFERENCES staff_members(id) ON DELETE CASCADE
    )");

    $check_notes = $conn->query("SHOW COLUMNS FROM staff_members LIKE 'notes'");
    if ($check_notes->num_rows == 0) {
        $conn->query("ALTER TABLE staff_members ADD COLUMN notes TEXT");
    }

    $stmt = $conn->prepare("SELECT m.*, u.unit_name, r.rank_name FROM staff_members m 
                            JOIN staff_units u ON m.unit_id = u.id 
                            JOIN staff_ranks r ON m.rank_id = r.id
                            WHERE m.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $staff = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$staff) {
    die("کارمەند نەدۆزرایەوە.");
}

// Fetch ranks and suggestions (same as staff_new.php)
$ranks = [];
$res = $conn->query("SELECT * FROM staff_ranks ORDER BY priority DESC");
while ($row = $res->fetch_assoc()) $ranks[] = $row;

$units = [];
$res = $conn->query("SELECT * FROM staff_units ORDER BY unit_name ASC");
while ($row = $res->fetch_assoc()) $units[] = $row;

$suggest_positions = $conn->query("SELECT DISTINCT position FROM staff_members WHERE position != '' ORDER BY position ASC");
$suggest_branches = $conn->query("SELECT DISTINCT branch FROM staff_members WHERE branch != '' ORDER BY branch ASC");
$suggest_educations = $conn->query("SELECT DISTINCT education FROM staff_members WHERE education != '' ORDER BY education ASC");
$suggest_cities = $conn->query("SELECT DISTINCT city FROM staff_members WHERE city != '' ORDER BY city ASC");
$suggest_towns = $conn->query("SELECT DISTINCT town FROM staff_members WHERE town != '' ORDER BY town ASC");
$suggest_districts = $conn->query("SELECT DISTINCT district FROM staff_members WHERE district != '' ORDER BY district ASC");
$suggest_villages = $conn->query("SELECT DISTINCT village FROM staff_members WHERE village != '' ORDER BY village ASC");
$suggest_birth_places = $conn->query("SELECT DISTINCT birth_place FROM staff_members WHERE birth_place != '' ORDER BY birth_place ASC");

$page_title = 'دەستکاریکردنی: ' . $staff['rank_name'] . ' ' . $staff['full_name'];
$active_nav = 'staff_list';
$sys_module = 'nusinga';
require_once 'includes/layout.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--default .select2-selection--single {
        height: 50px;
        border: 2px solid var(--border);
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        padding: 0 10px;
        background: #fcfcfc;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 48px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        font-family: 'Rabar022';
        color: var(--text);
        font-size: 0.95rem;
    }

    /* Tabs Styling */
    .form-tabs {
        display: flex;
        gap: 5px;
        margin-bottom: 20px;
        border-bottom: 2px solid #eee;
        padding-bottom: 0;
    }
    .tab-btn {
        padding: 12px 25px;
        border: none;
        background: #f8f9fa;
        cursor: pointer;
        font-family: 'Rabar022';
        font-weight: bold;
        border-radius: 10px 10px 0 0;
        transition: all 0.3s;
        border: 2px solid transparent;
        border-bottom: none;
        color: #666;
    }
    .tab-btn.active {
        background: white;
        border: 2px solid #eee;
        border-bottom-color: white;
        margin-bottom: -2px;
        color: var(--primary);
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.3s ease; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    /* Profile Edit Layout */
    .profile-edit-container { display: flex; gap: 30px; align-items: flex-start; }
    .profile-photo-section { flex: 0 0 200px; display: flex; flex-direction: column; gap: 15px; }
    .profile-preview-box { 
        width: 200px; 
        height: 250px; 
        border: 3px solid var(--border); 
        border-radius: 15px; 
        overflow: hidden; 
        position: relative; 
        background: #f8f9fa;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    .profile-preview-box img { width: 100%; height: 100%; object-fit: cover; }
    .delete-image-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(220, 53, 69, 0.9);
        color: white;
        border: none;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        transition: all 0.2s;
        z-index: 999;
    }
    .delete-image-btn:hover { background: #dc3545; transform: scale(1.1); }
    .lib-field.photo-upload { margin-top: 5px; }
    .personal-fields { flex: 1; }

    .lang-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        background: #f9f9f9;
        padding: 15px;
        border-radius: 10px;
    }
    .lang-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
</style>

<div class="lib-card">
    <div class="card-header">
        <h3>✏️ دەستکاریکردنی زانیارییەکانی <span style="color: #c8a951;"><?php echo $staff['rank_name'] . ' ' . $staff['full_name']; ?></span></h3>
    </div>
    <div class="lib-modal-body">
        
        <div class="form-tabs">
            <button type="button" class="tab-btn active" onclick="showTab('personal')">👤 زانیاری کەسی</button>
            <button type="button" class="tab-btn" onclick="showTab('professional')">🎖️ زانیاری پیشەیی</button>
            <button type="button" class="tab-btn" onclick="showTab('health')">🩺 تەندروستی و زمان</button>
            <button type="button" class="tab-btn" onclick="showTab('history')">📜 مێژوو و ڕێزلێنان</button>
        </div>

        <form action="staff_update.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $staff['id']; ?>">
            
            <!-- Tab: Personal Info -->
            <div id="personal" class="tab-content active">
                <div class="profile-edit-container">
                    <!-- Right: Personal Fields -->
                    <div class="personal-fields">
                        <div class="lib-form-grid">
                            <div class="lib-field">
                                <label>پلە:</label>
                                <select name="rank_id" class="searchable-select" required>
                                    <?php foreach ($ranks as $r): ?>
                                        <option value="<?php echo $r['id']; ?>" <?php echo ($r['id'] == $staff['rank_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($r['rank_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="lib-field">
                                <label>پایە (پۆست):</label>
                                <input type="text" name="position" list="position_list" value="<?php echo htmlspecialchars($staff['position'] ?? ''); ?>" placeholder="بۆ نموونە: ئەفسەری ئیدارە">
                            </div>

                            <div class="lib-field">
                                <label>ناوی چواری:</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($staff['full_name'] ?? ''); ?>" required>
                            </div>

                            <div class="lib-field">
                                <label>باری خێزانی:</label>
                                <select name="marital_status" class="lib-input">
                                    <?php $ms = $staff['marital_status'] ?? ''; ?>
                                    <option value="سەڵت" <?php echo ($ms == 'سەڵت') ? 'selected' : ''; ?>>سەڵت</option>
                                    <option value="خێزاندار" <?php echo ($ms == 'خێزاندار') ? 'selected' : ''; ?>>خێزاندار</option>
                                    <option value="جیابووەوە" <?php echo ($ms == 'جیابووەوە') ? 'selected' : ''; ?>>جیابووەوە</option>
                                    <option value="بێوەژن/پیاو" <?php echo ($ms == 'بێوەژن/پیاو') ? 'selected' : ''; ?>>بێوەژن/پیاو</option>
                                </select>
                            </div>

                            <div class="lib-field">
                                <label>ڕەگەز:</label>
                                <select name="gender" class="lib-input">
                                    <option value="نێر" <?php echo ($staff['gender'] == 'نێر') ? 'selected' : ''; ?>>نێر</option>
                                    <option value="مێ" <?php echo ($staff['gender'] == 'مێ') ? 'selected' : ''; ?>>مێ</option>
                                </select>
                            </div>

                            <div class="lib-field">
                                <label>ئیش / کار:</label>
                                <input type="text" name="job" value="<?php echo htmlspecialchars($staff['job'] ?? ''); ?>" placeholder="ئیشی کارمەند">
                            </div>

                            <div class="lib-field">
                                <label>ژمارەی منداڵەکان:</label>
                                <input type="number" name="children_count" value="<?php echo (int)($staff['children_count'] ?? 0); ?>" min="0">
                            </div>

                            <div class="lib-field">
                                <label>ساڵی لە دایک بوون:</label>
                                <input type="number" name="birth_year" value="<?php echo $staff['birth_year']; ?>" required>
                            </div>

                            <div class="lib-field">
                                <label>شوێنی لە دایک بوون:</label>
                                <input type="text" name="birth_place" list="birth_place_list" value="<?php echo htmlspecialchars($staff['birth_place'] ?? ''); ?>">
                            </div>

                            <div class="lib-field">
                                <label>ژمارەی موبایل:</label>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($staff['phone'] ?? ''); ?>">
                            </div>

                            <div class="lib-field">
                                <label>ژمارەی فۆڕمی خۆراک:</label>
                                <input type="text" name="form_number" value="<?php echo htmlspecialchars($staff['form_number'] ?? ''); ?>">
                            </div>
                            <div class="lib-field">
                                <label>ژمارەی دەنگدان:</label>
                                <input type="text" name="voting_number" value="<?php echo htmlspecialchars($staff['voting_number'] ?? ''); ?>">
                            </div>
                            <div class="lib-field">
                                <label>ژمارەی بایۆ مەتری:</label>
                                <input type="text" name="biometric_number" value="<?php echo htmlspecialchars($staff['biometric_number'] ?? ''); ?>">
                            </div>

                            <div class="lib-field" style="grid-column: 1 / -1; margin-top: 15px;">
                                <label>ناونیشان (شار - شارۆچکە - ناحیە - گوند):</label>
                                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                                    <input type="text" name="city" list="city_list" value="<?php echo htmlspecialchars($staff['city'] ?? ''); ?>" placeholder="شار">
                                    <input type="text" name="town" list="town_list" value="<?php echo htmlspecialchars($staff['town'] ?? ''); ?>" placeholder="شارۆچکە">
                                    <input type="text" name="district" list="district_list" value="<?php echo htmlspecialchars($staff['district'] ?? ''); ?>" placeholder="ناحیە">
                                    <input type="text" name="village" list="village_list" value="<?php echo htmlspecialchars($staff['village'] ?? ''); ?>" placeholder="گوند">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Left: Profile Photo Section -->
                    <div class="profile-photo-section">
                        <label style="font-weight: bold; margin-bottom: 5px; display: block;">وێنە:</label>
                        <div class="profile-preview-box">
                            <?php 
                            $img_src = 'assets/imgae/user.png';
                            if (!empty($staff['profile_image']) && file_exists($staff['profile_image'])) {
                                $imgData = base64_encode(file_get_contents($staff['profile_image']));
                                $mime = mime_content_type($staff['profile_image']);
                                $img_src = "data:$mime;base64,$imgData";
                            }
                            ?>
                            <img src="<?php echo $img_src; ?>" id="profile-preview" alt="Profile">
                            <?php if(!empty($staff['profile_image']) && strpos($staff['profile_image'], 'assets/') === false): ?>
                                <button type="button" class="delete-image-btn" id="delete_btn_ui" 
                                    onclick="document.getElementById('profile-preview').src='assets/imgae/user.png'; document.getElementById('delete_image_check').value='1'; this.style.display='none'; if(document.getElementById('filename_info')) document.getElementById('filename_info').style.display='none'; if(document.getElementById('profile_image_input')) document.getElementById('profile_image_input').value='';" 
                                    title="سڕینەوەی وێنە">🗑️</button>
                            <?php endif; ?>
                            <input type="hidden" name="delete_current_image" value="0" id="delete_image_check">
                        </div>
                        
                        <div class="lib-field photo-upload" style="margin-top: 10px;">
                            <input type="file" name="profile_image" id="profile_image_input" accept="image/*" onchange="previewImage(this)">
                            <?php if(!empty($staff['profile_image']) && strpos($staff['profile_image'], 'assets/') === false): ?>
                                <small style="display: block; margin-top: 5px; color: #666;" id="filename_info">ناوی وێنە: <?php echo basename($staff['profile_image']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Professional Info -->
            <div id="professional" class="tab-content">
                <div class="lib-form-grid">
                    <div class="lib-field">
                        <label>ناوی یەکە:</label>
                        <input type="text" name="unit_name" list="unit_list" value="<?php echo htmlspecialchars($staff['unit_name'] ?? ''); ?>" required>
                    </div>

                    <div class="lib-field">
                        <label>بەش / لقی:</label>
                        <input type="text" name="sub_unit" value="<?php echo htmlspecialchars($staff['sub_unit'] ?? ''); ?>" placeholder="بۆ نموونە: بەشی ئایتی">
                    </div>


                    <div class="lib-field">
                        <label>صنف (جۆری چەک):</label>
                        <select name="military_branch_sinf" class="lib-input">
                            <?php $mbs = $staff['military_branch_sinf'] ?? ''; ?>
                            <option value="پیادە" <?php echo ($mbs == 'پیادە') ? 'selected' : ''; ?>>پیادە</option>
                            <option value="زرێپۆش" <?php echo ($mbs == 'زرێپۆش') ? 'selected' : ''; ?>>زرێپۆش</option>
                            <option value="تۆپخانە" <?php echo ($mbs == 'تۆپخانە') ? 'selected' : ''; ?>>تۆپخانە</option>
                            <option value="ئەندازیاری" <?php echo ($mbs == 'ئەندازیاری') ? 'selected' : ''; ?>>ئەندازیاری</option>
                            <option value="موخابەرە" <?php echo ($mbs == 'موخابەرە') ? 'selected' : ''; ?>>موخابەرە</option>
                            <option value="پزیشکی" <?php echo ($mbs == 'پزیشکی') ? 'selected' : ''; ?>>پزیشکی</option>
                            <option value="لۆجستی" <?php echo ($mbs == 'لۆجستی') ? 'selected' : ''; ?>>لۆجستی</option>
                            <option value="هتد" <?php echo ($mbs == 'هتد') ? 'selected' : ''; ?>>هتد</option>
                        </select>
                    </div>

                    <div class="lib-field">
                        <label>ڕشتە (پسپۆڕی):</label>
                        <input type="text" name="branch" list="branch_list" value="<?php echo htmlspecialchars($staff['branch'] ?? ''); ?>">
                    </div>

                    <div class="lib-field">
                        <label>بڕوانامەی خوێندن:</label>
                        <input type="text" name="education" list="education_list" value="<?php echo htmlspecialchars($staff['education'] ?? ''); ?>">
                    </div>

                    <div class="lib-field">
                        <label>ئاستی خوێندن:</label>
                        <input type="text" name="education_level" value="<?php echo htmlspecialchars($staff['education_level'] ?? ''); ?>" placeholder="ئاستی بڕوانامە">
                    </div>

                    <div class="lib-field">
                        <label>بڕوانامەی سەربازی:</label>
                        <input type="text" name="military_education" value="<?php echo htmlspecialchars($staff['military_education'] ?? ''); ?>" placeholder="دەرچووی کۆلێژی سەربازی...">
                    </div>

                    <div class="lib-field">
                        <label>بەرواری دوا پلە:</label>
                        <input type="date" name="last_promotion_date" value="<?php echo $staff['last_promotion_date']; ?>" onchange="calculateNextPromotion()">
                    </div>

                    <div class="lib-field">
                        <label>بەرواری شایستەی پلە بەرزکردنەوە:</label>
                        <input type="date" name="promotion_due_date" value="<?php echo $staff['promotion_due_date']; ?>">
                    </div>

                    <div class="lib-field">
                        <label>ژمارەی ناسنامەی سەربازی:</label>
                        <input type="text" name="id_number" value="<?php echo htmlspecialchars($staff['id_number'] ?? ''); ?>">
                    </div>
                    <div class="lib-field">
                        <label>ژ. فەرمانی دامەزاردن:</label>
                        <input type="text" name="appointment_order" value="<?php echo htmlspecialchars($staff['appointment_order'] ?? ''); ?>">
                    </div>
                    <div class="lib-field">
                        <label>بەرواری فەرمانی دامەزاردن:</label>
                        <input type="date" name="appointment_date" value="<?php echo $staff['appointment_date'] ?? ''; ?>">
                    </div>
                    <div class="lib-field">
                        <label>ژمارەی ناسنامەی مەدەنی:</label>
                        <input type="text" name="civil_id" value="<?php echo htmlspecialchars($staff['civil_id'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="lib-card" style="margin-top: 20px;">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>📜 تۆماری خزمەت و گواستنەوەکان</h3>
                        <button type="button" class="lib-btn lib-btn-primary" onclick="addServiceRow()">➕ زیادکردن</button>
                    </div>
                    <div style="padding: 10px;">
                        <table class="lib-table" id="serviceHistoryTable" style="min-width: 100%;">
                            <thead>
                                <tr>
                                    <th>یەکە / بەش</th>
                                    <th>پایە (ناونیشان)</th>
                                    <th>ماوەی خزمەت</th>
                                    <th>فەرمانی گواستنەوە</th>
                                    <th style="width: 50px;">🗑️</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $hist_res = $conn->query("SELECT * FROM staff_service_history WHERE staff_id = $id ORDER BY id ASC");
                                while($h = $hist_res->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><input type="text" name="service_unit[]" class="lib-input" style="padding: 8px;" value="<?php echo htmlspecialchars($h['unit_name']); ?>"></td>
                                    <td><input type="text" name="service_position[]" class="lib-input" style="padding: 8px;" value="<?php echo htmlspecialchars($h['position']); ?>"></td>
                                    <td><input type="text" name="service_period[]" class="lib-input" style="padding: 8px;" value="<?php echo htmlspecialchars($h['service_period']); ?>"></td>
                                    <td><input type="text" name="service_order[]" class="lib-input" style="padding: 8px;" value="<?php echo htmlspecialchars($h['order_number']); ?>"></td>
                                    <td><button type="button" class="lib-btn lib-btn-danger" onclick="this.closest('tr').remove()" style="padding: 5px 10px;">X</button></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Health & Skills -->
            <div id="health" class="tab-content">
                <div class="lib-form-grid">
                    <div class="lib-field">
                        <label>گرووپی خوێن:</label>
                        <select name="blood_group" class="lib-input">
                            <?php $bg = $staff['blood_group'] ?? ''; ?>
                            <option value="">-- هەڵبژێرە --</option>
                            <?php foreach(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'] as $g): ?>
                                <option value="<?php echo $g; ?>" <?php echo ($bg == $g) ? 'selected' : ''; ?>><?php echo $g; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="lib-field">
                        <label>لێهاتوویی جەستەیی:</label>
                        <input type="text" name="physical_fitness" value="<?php echo htmlspecialchars($staff['physical_fitness'] ?? ''); ?>" placeholder="نمرەی کۆتا تاقیکردنەوە">
                    </div>
                    <div class="lib-field" style="grid-column: 1 / -1;">
                        <label>باری تەندروستی گشتی:</label>
                        <textarea name="health_status" rows="3"><?php echo htmlspecialchars($staff['health_status'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <label style="display: block; margin-bottom: 10px; font-weight: bold;">زمانەکان:</label>
                    <div class="lang-grid">
                        <?php 
                        $cur_langs = $staff['languages'] ?? '';
                        function hasLang($cur_langs, $lang) { return strpos($cur_langs, $lang) !== false; }
                        function getLevel($cur_langs, $lang) {
                            if (preg_match('/' . $lang . '\s*\(([^)]+)\)/', $cur_langs, $matches)) return $matches[1];
                            return '';
                        }
                        ?>
                        <div class="lang-item">
                            <label><input type="checkbox" name="langs[]" value="کوردی" <?php echo hasLang($cur_langs, 'کوردی') ? 'checked' : ''; ?>> کوردی</label>
                            <select name="lang_level_kurdi" class="lib-input">
                                <?php $lvl = getLevel($cur_langs, 'کوردی'); ?>
                                <option value="باش" <?php echo ($lvl == 'باش') ? 'selected' : ''; ?>>باش</option>
                                <option value="زۆر باش" <?php echo ($lvl == 'زۆر باش') ? 'selected' : ''; ?>>زۆر باش</option>
                                <option value="نایاب" <?php echo ($lvl == 'نایاب') ? 'selected' : ''; ?>>نایاب</option>
                            </select>
                        </div>
                        <div class="lang-item">
                            <label><input type="checkbox" name="langs[]" value="عەرەبی" <?php echo hasLang($cur_langs, 'عەرەبی') ? 'checked' : ''; ?>> عەرەبی</label>
                            <select name="lang_level_arabi" class="lib-input">
                                <?php $lvl = getLevel($cur_langs, 'عەرەبی'); ?>
                                <option value="باش" <?php echo ($lvl == 'باش') ? 'selected' : ''; ?>>باش</option>
                                <option value="زۆر باش" <?php echo ($lvl == 'زۆر باش') ? 'selected' : ''; ?>>زۆر باش</option>
                                <option value="نایاب" <?php echo ($lvl == 'نایاب') ? 'selected' : ''; ?>>نایاب</option>
                            </select>
                        </div>
                        <div class="lang-item">
                            <label><input type="checkbox" name="langs[]" value="ئینگلیزی" <?php echo hasLang($cur_langs, 'ئینگلیزی') ? 'checked' : ''; ?>> ئینگلیزی</label>
                            <select name="lang_level_english" class="lib-input">
                                <?php $lvl = getLevel($cur_langs, 'ئینگلیزی'); ?>
                                <option value="باش" <?php echo ($lvl == 'باش') ? 'selected' : ''; ?>>باش</option>
                                <option value="زۆر باش" <?php echo ($lvl == 'زۆر باش') ? 'selected' : ''; ?>>زۆر باش</option>
                                <option value="نایاب" <?php echo ($lvl == 'نایاب') ? 'selected' : ''; ?>>نایاب</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: History & Honors -->
            <div id="history" class="tab-content">
                <div class="lib-form-grid">
                    <div class="lib-field">
                        <label>نیشانەی ئازایەتی:</label>
                        <input type="text" name="honors_medals" value="<?php echo htmlspecialchars($staff['honors_medals'] ?? ''); ?>">
                    </div>
                    <div class="lib-field">
                        <label>برینداربوون:</label>
                        <input type="text" name="injuries" value="<?php echo htmlspecialchars($staff['injuries'] ?? ''); ?>">
                    </div>
                    <div class="lib-field" style="grid-column: 1 / -1;">
                        <label>تێبینی گشتی:</label>
                        <textarea name="notes" rows="3"><?php echo htmlspecialchars($staff['notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="lib-card" style="margin-top: 20px;">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3>🎓 خولە سەربازییەکان</h3>
                        <button type="button" class="lib-btn lib-btn-primary" onclick="addCourseRow()">➕ زیادکردن</button>
                    </div>
                    <div style="padding: 10px;">
                        <table class="lib-table" id="coursesTable" style="min-width: 100%;">
                            <thead>
                                <tr>
                                    <th>ناوی خول</th>
                                    <th>شوێن</th>
                                    <th>بەروار</th>
                                    <th>پلەی سەرکەوتن</th>
                                    <th>تێبینی</th>
                                    <th style="width: 50px;">🗑️</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $course_res = $conn->query("SELECT * FROM staff_courses WHERE staff_id = $id ORDER BY id ASC");
                                while($c = $course_res->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><input type="text" name="course_name[]" class="lib-input" value="<?php echo htmlspecialchars($c['course_name']); ?>"></td>
                                    <td><input type="text" name="course_place[]" class="lib-input" value="<?php echo htmlspecialchars($c['place']); ?>"></td>
                                    <td><input type="date" name="course_date[]" class="lib-input" value="<?php echo $c['course_date']; ?>"></td>
                                    <td><input type="text" name="course_grade[]" class="lib-input" value="<?php echo htmlspecialchars($c['grade'] ?? ''); ?>"></td>
                                    <td><input type="text" name="course_notes[]" class="lib-input" value="<?php echo htmlspecialchars($c['notes'] ?? ''); ?>"></td>
                                    <td><button type="button" class="lib-btn lib-btn-danger" onclick="this.closest('tr').remove()">X</button></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div style="grid-column: 1 / -1; display: flex; gap: 15px; margin-top: 30px; padding-bottom: 30px;">
                <button type="submit" class="lib-btn lib-btn-primary" style="flex: 2; padding: 15px; font-size: 1.1rem;">💾 نوێکردنەوەی زانیارییەکان</button>
                <a href="staff_list.php" class="lib-btn lib-btn-danger" style="flex: 1; padding: 15px; text-align: center; text-decoration: none;">پاشگەزبوونەوە</a>
            </div>
        </form>

        <!-- Datalists -->
        <datalist id="unit_list"><?php foreach($units as $u): ?><option value="<?php echo htmlspecialchars($u['unit_name']); ?>"><?php endforeach; ?></datalist>
        <datalist id="position_list"><?php $suggest_positions->data_seek(0); while($r=$suggest_positions->fetch_assoc()): ?><option value="<?php echo htmlspecialchars($r['position']); ?>"><?php endwhile; ?></datalist>
        <datalist id="city_list"><?php $suggest_cities->data_seek(0); while($r=$suggest_cities->fetch_assoc()): ?><option value="<?php echo htmlspecialchars($r['city']); ?>"><?php endwhile; ?></datalist>
        <datalist id="town_list"><?php $suggest_towns->data_seek(0); while($r=$suggest_towns->fetch_assoc()): ?><option value="<?php echo htmlspecialchars($r['town']); ?>"><?php endwhile; ?></datalist>
        <datalist id="district_list"><?php $suggest_districts->data_seek(0); while($r=$suggest_districts->fetch_assoc()): ?><option value="<?php echo htmlspecialchars($r['district']); ?>"><?php endwhile; ?></datalist>
        <datalist id="village_list"><?php $suggest_villages->data_seek(0); while($r=$suggest_villages->fetch_assoc()): ?><option value="<?php echo htmlspecialchars($r['village']); ?>"><?php endwhile; ?></datalist>
        <datalist id="birth_place_list"><?php $suggest_birth_places->data_seek(0); while($r=$suggest_birth_places->fetch_assoc()): ?><option value="<?php echo htmlspecialchars($r['birth_place']); ?>"><?php endwhile; ?></datalist>
        <datalist id="branch_list"><?php $suggest_branches->data_seek(0); while($r=$suggest_branches->fetch_assoc()): ?><option value="<?php echo htmlspecialchars($r['branch']); ?>"><?php endwhile; ?></datalist>
        <datalist id="education_list"><?php $suggest_educations->data_seek(0); while($r=$suggest_educations->fetch_assoc()): ?><option value="<?php echo htmlspecialchars($r['education']); ?>"><?php endwhile; ?></datalist>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.searchable-select').select2({ dir: "rtl", width: '100%' });
});

function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    event.currentTarget.classList.add('active');
}

function calculateNextPromotion() {
    const lastDateVal = document.getElementsByName('last_promotion_date')[0].value;
    const rankId = document.getElementsByName('rank_id')[0].value;
    if (!lastDateVal || !rankId) return;
    const lastDate = new Date(lastDateVal);
    const yearsToAdd = 3; 
    lastDate.setFullYear(lastDate.getFullYear() + yearsToAdd);
    const nextDate = lastDate.toISOString().split('T')[0];
    document.getElementsByName('promotion_due_date')[0].value = nextDate;
}

function addServiceRow() {
    const tbody = document.querySelector('#serviceHistoryTable tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" name="service_unit[]" class="lib-input" style="padding: 8px;"></td>
        <td><input type="text" name="service_position[]" class="lib-input" style="padding: 8px;"></td>
        <td><input type="text" name="service_period[]" class="lib-input" style="padding: 8px;" placeholder="2020 - 2022"></td>
        <td><input type="text" name="service_order[]" class="lib-input" style="padding: 8px;"></td>
        <td><button type="button" class="lib-btn lib-btn-danger" onclick="this.closest('tr').remove()" style="padding: 5px 10px;">X</button></td>
    `;
    tbody.appendChild(row);
}

function addCourseRow() {
    const tbody = document.querySelector('#coursesTable tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" name="course_name[]" class="lib-input" style="padding: 8px;"></td>
        <td><input type="text" name="course_place[]" class="lib-input" style="padding: 8px;"></td>
        <td><input type="date" name="course_date[]" class="lib-input" style="padding: 8px;"></td>
        <td><input type="text" name="course_grade[]" class="lib-input" style="padding: 8px;" placeholder="باش، زۆر باش..."></td>
        <td><input type="text" name="course_notes[]" class="lib-input" style="padding: 8px;"></td>
        <td><button type="button" class="lib-btn lib-btn-danger" onclick="this.closest('tr').remove()" style="padding: 5px 10px;">X</button></td>
    `;
    tbody.appendChild(row);
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profile-preview').src = e.target.result;
            const flag = document.getElementById('delete_image_check');
            if (flag) flag.value = "0";
            // Restore UI if it was hidden
            const btn = document.getElementById('delete_btn_ui');
            if(btn) btn.style.display = 'flex';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function removeImage() {
    if (confirm('ئایا دڵنیایت لە سڕینەوەی ئەم وێنەیە؟')) {
        const preview = document.getElementById('profile-preview');
        const flag = document.getElementById('delete_image_check');
        const fileInput = document.getElementById('profile_image_input');
        const btn = document.getElementById('delete_btn_ui');
        const info = document.getElementById('filename_info');

        if (preview) preview.src = 'assets/imgae/user.png';
        if (flag) flag.value = "1";
        if (fileInput) fileInput.value = '';
        if (btn) btn.style.display = 'none';
        if (info) info.style.display = 'none';
        
        return true;
    }
    return false;
}
</script>

<?php require_once 'includes/layout_end.php'; ?>
