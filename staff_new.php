<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$ranks = [];
$units = [];

if ($conn) {
    $res = $conn->query("SELECT * FROM staff_ranks ORDER BY priority DESC");
    while ($row = $res->fetch_assoc()) $ranks[] = $row;

    $res = $conn->query("SELECT * FROM staff_units ORDER BY unit_name ASC");
    while ($row = $res->fetch_assoc()) $units[] = $row;

    // Fetch existing values for suggestions
    $suggest_positions = $conn->query("SELECT DISTINCT position FROM staff_members WHERE position IS NOT NULL AND position != '' ORDER BY position ASC");
    $suggest_branches = $conn->query("SELECT DISTINCT branch FROM staff_members WHERE branch IS NOT NULL AND branch != '' ORDER BY branch ASC");
    $suggest_educations = $conn->query("SELECT DISTINCT education FROM staff_members WHERE education IS NOT NULL AND education != '' ORDER BY education ASC");
    $suggest_cities = $conn->query("SELECT DISTINCT city FROM staff_members WHERE city IS NOT NULL AND city != '' ORDER BY city ASC");
    $suggest_towns = $conn->query("SELECT DISTINCT town FROM staff_members WHERE town IS NOT NULL AND town != '' ORDER BY town ASC");
    $suggest_districts = $conn->query("SELECT DISTINCT district FROM staff_members WHERE district IS NOT NULL AND district != '' ORDER BY district ASC");
    $suggest_villages = $conn->query("SELECT DISTINCT village FROM staff_members WHERE village IS NOT NULL AND village != '' ORDER BY village ASC");
    $suggest_birth_places = $conn->query("SELECT DISTINCT birth_place FROM staff_members WHERE birth_place IS NOT NULL AND birth_place != '' ORDER BY birth_place ASC");
}

$page_title = 'تۆمارکردنی کارمەندی نوێ';
$active_nav = 'staff_new';
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
    .select2-dropdown {
        border: 2px solid var(--maroon);
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
    }
    .select2-search__field {
        border: 2px solid var(--border) !important;
        border-radius: 8px !important;
        padding: 8px !important;
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
        background: #fff;
        color: var(--maroon);
        border-color: #eee;
        border-bottom: 2px solid #fff;
        margin-bottom: -2px;
    }
    .tab-content {
        display: none;
        animation: fadeIn 0.3s;
    }
    .tab-content.active {
        display: block;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
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
        <h3>📝 فۆڕمی زانیاری کارمەندان</h3>
    </div>
    <div class="lib-modal-body">
        
        <div class="form-tabs">
            <button type="button" class="tab-btn active" onclick="showTab('personal')">👤 زانیاری کەسی</button>
            <button type="button" class="tab-btn" onclick="showTab('professional')">🎖️ زانیاری پیشەیی</button>
            <button type="button" class="tab-btn" onclick="showTab('health')">🩺 تەندروستی و زمان</button>
            <button type="button" class="tab-btn" onclick="showTab('history')">📜 مێژوو و ڕێزلێنان</button>
        </div>

        <form action="staff_save.php" method="POST" enctype="multipart/form-data">
            
            <!-- Tab: Personal Info -->
            <div id="personal" class="tab-content active">
                <div class="lib-form-grid">
                    <div class="lib-field">
                        <label>پلە:</label>
                        <select name="rank_id" class="searchable-select" required>
                            <option value="">-- پلە هەڵبژێرە --</option>
                            <?php foreach ($ranks as $r): ?>
                                <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['rank_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="lib-field">
                        <label>پایە (پۆست):</label>
                        <input type="text" name="position" list="position_list" placeholder="بۆ نموونە: بەڕێوەبەری کارگێڕی، سەرۆکی بەش">
                    </div>

                    <div class="lib-field">
                        <label>ناوی چواری:</label>
                        <input type="text" name="full_name" required placeholder="ناوی سیانی و ناوی باپیری باپیری">
                    </div>

                    <div class="lib-field">
                        <label>باری خێزانی:</label>
                        <select name="marital_status" class="lib-input">
                            <option value="سەڵت">سەڵت</option>
                            <option value="خێزاندار">خێزاندار</option>
                            <option value="جیابووەوە">جیابووەوە</option>
                            <option value="بێوەژن/پیاو">بێوەژن/پیاو</option>
                        </select>
                    </div>

                    <div class="lib-field">
                        <label>ڕەگەز:</label>
                        <select name="gender" class="lib-input">
                            <option value="نێر">نێر</option>
                            <option value="مێ">مێ</option>
                        </select>
                    </div>

                    <div class="lib-field">
                        <label>ئیش / کار:</label>
                        <input type="text" name="job" placeholder="ئیشی کارمەند">
                    </div>

                    <div class="lib-field">
                        <label>ژمارەی منداڵەکان:</label>
                        <input type="number" name="children_count" value="0" min="0">
                    </div>

                    <div class="lib-field">
                        <label>ساڵی لە دایک بوون:</label>
                        <input type="number" name="birth_year" value="<?php echo date('Y') - 30; ?>" required>
                    </div>

                    <div class="lib-field">
                        <label>شوێنی لە دایک بوون:</label>
                        <input type="text" name="birth_place" list="birth_place_list" placeholder="سلێمانی">
                    </div>

                    <div class="lib-field">
                        <label>ژمارەی موبایل:</label>
                        <input type="text" name="phone">
                    </div>

                    <div class="lib-field">
                        <label>ژمارەی فۆڕمی خۆراک:</label>
                        <input type="text" name="form_number">
                    </div>
                    <div class="lib-field">
                        <label>ژمارەی دەنگدان:</label>
                        <input type="text" name="voting_number">
                    </div>
                    <div class="lib-field">
                        <label>ژمارەی بایۆ مەتری:</label>
                        <input type="text" name="biometric_number">
                    </div>

                    <div class="lib-field">
                        <label>وێنەی کارمەند:</label>
                        <input type="file" name="profile_image" accept="image/*">
                    </div>

                    <div class="lib-field" style="grid-column: 1 / -1; margin-top: 15px;">
                        <label>ناونیشان (شار - شارۆچکە - ناحیە - گوند):</label>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;">
                            <input type="text" name="city" list="city_list" placeholder="شار">
                            <input type="text" name="town" list="town_list" placeholder="شارۆچکە">
                            <input type="text" name="district" list="district_list" placeholder="ناحیە">
                            <input type="text" name="village" list="village_list" placeholder="گوند">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Professional Info -->
            <div id="professional" class="tab-content">
                <div class="lib-form-grid">
                    <div class="lib-field">
                        <label>ناوی یەکە:</label>
                        <input type="text" name="unit_name" list="unit_list" placeholder="بۆ نموونە: بەڕێوەبەرایەتی ڕاگەیاندن" required>
                    </div>

                    <div class="lib-field">
                        <label>بەش / لقی:</label>
                        <input type="text" name="sub_unit" placeholder="بۆ نموونە: بەشی ئایتی، لقی کارگێڕی">
                    </div>


                    <div class="lib-field">
                        <label>صنف (جۆری چەک):</label>
                        <select name="military_branch_sinf" class="lib-input">
                            <option value="پیادە">پیادە</option>
                            <option value="زرێپۆش">زرێپۆش</option>
                            <option value="تۆپخانە">تۆپخانە</option>
                            <option value="ئەندازیاری">ئەندازیاری</option>
                            <option value="موخابەرە">موخابەرە</option>
                            <option value="پزیشکی">پزیشکی</option>
                            <option value="لۆجستی">لۆجستی</option>
                            <option value="هتد">هتد</option>
                        </select>
                    </div>

                    <div class="lib-field">
                        <label>ڕشتە (پسپۆڕی):</label>
                        <input type="text" name="branch" list="branch_list" placeholder="پیادە، زرێپۆش، ڕاگەیاندن...">
                    </div>

                    <div class="lib-field">
                        <label>بڕوانامەی خوێندن:</label>
                        <input type="text" name="education" list="education_list" placeholder="دبلۆم، بەکالۆریۆس...">
                    </div>

                    <div class="lib-field">
                        <label>ئاستی خوێندن:</label>
                        <input type="text" name="education_level" placeholder="ئاستی بڕوانامە">
                    </div>

                    <div class="lib-field">
                        <label>بڕوانامەی سەربازی:</label>
                        <input type="text" name="military_education" placeholder="دەرچووی کۆلێژی سەربازی...">
                    </div>

                    <div class="lib-field">
                        <label>بەرواری دوا پلە:</label>
                        <input type="date" name="last_promotion_date" required onchange="calculateNextPromotion()">
                    </div>

                    <div class="lib-field">
                        <label>بەرواری شایستەی پلە بەرزکردنەوە:</label>
                        <input type="date" name="promotion_due_date" id="promotion_due_date">
                    </div>

                    <div class="lib-field">
                        <label>ژمارەی ناسنامەی سەربازی:</label>
                        <input type="text" name="id_number">
                    </div>
                    <div class="lib-field">
                        <label>ژ. فەرمانی دامەزاردن:</label>
                        <input type="text" name="appointment_order">
                    </div>
                    <div class="lib-field">
                        <label>بەرواری فەرمانی دامەزاردن:</label>
                        <input type="date" name="appointment_date">
                    </div>
                    <div class="lib-field">
                        <label>ژمارەی ناسنامەی مەدەنی:</label>
                        <input type="text" name="civil_id">
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
                            <tbody></tbody>
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
                            <option value="">-- هەڵبژێرە --</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                        </select>
                    </div>
                    <div class="lib-field">
                        <label>لێهاتوویی جەستەیی:</label>
                        <input type="text" name="physical_fitness" placeholder="نمرەی کۆتا تاقیکردنەوە یان ئاست">
                    </div>
                    <div class="lib-field" style="grid-column: 1 / -1;">
                        <label>باری تەندروستی گشتی:</label>
                        <textarea name="health_status" rows="3" placeholder="تێبینی تەندروستی یان نەخۆشی درێژخایەن"></textarea>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <label style="display: block; margin-bottom: 10px; font-weight: bold;">زمانەکان:</label>
                    <div class="lang-grid">
                        <div class="lang-item">
                            <label><input type="checkbox" name="langs[]" value="کوردی"> کوردی</label>
                            <select name="lang_level_kurdi" class="lib-input">
                                <option value="باش">باش</option>
                                <option value="زۆر باش">زۆر باش</option>
                                <option value="نایاب">نایاب</option>
                            </select>
                        </div>
                        <div class="lang-item">
                            <label><input type="checkbox" name="langs[]" value="عەرەبی"> عەرەبی</label>
                            <select name="lang_level_arabi" class="lib-input">
                                <option value="باش">باش</option>
                                <option value="زۆر باش">زۆر باش</option>
                                <option value="نایاب">نایاب</option>
                            </select>
                        </div>
                        <div class="lang-item">
                            <label><input type="checkbox" name="langs[]" value="ئینگلیزی"> ئینگلیزی</label>
                            <select name="lang_level_english" class="lib-input">
                                <option value="باش">باش</option>
                                <option value="زۆر باش">زۆر باش</option>
                                <option value="نایاب">نایاب</option>
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
                        <input type="text" name="honors_medals" placeholder="مەدالیا یان سوپاسنامە">
                    </div>
                    <div class="lib-field">
                        <label>برینداربوون:</label>
                        <input type="text" name="injuries" placeholder="مێژوو و جۆری برینداربوون">
                    </div>
                    <div class="lib-field" style="grid-column: 1 / -1;">
                        <label>تێبینی گشتی:</label>
                        <textarea name="notes" rows="3"></textarea>
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
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div style="grid-column: 1 / -1; margin-top: 30px; display: flex; gap: 15px; padding-bottom: 30px;">
                <button type="submit" class="lib-btn lib-btn-primary" style="padding: 15px 40px; font-size: 1.1rem;">💾 پاشکەوتکردنی زانیارییەکان</button>
                <a href="staff_dashboard.php" class="lib-btn lib-btn-danger" style="padding: 15px 30px;">پاشگەزبوونەوە</a>
            </div>

        </form>

        <!-- Datalists moved here for global access -->
        <datalist id="unit_list">
            <?php foreach ($units as $u): ?>
                <option value="<?php echo htmlspecialchars($u['unit_name']); ?>">
            <?php endforeach; ?>
        </datalist>
        <datalist id="position_list">
            <?php while($row = $suggest_positions->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($row['position']); ?>">
            <?php endwhile; ?>
        </datalist>
        <datalist id="birth_place_list">
            <?php while($row = $suggest_birth_places->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($row['birth_place']); ?>">
            <?php endwhile; ?>
        </datalist>
        <datalist id="branch_list">
            <?php while($row = $suggest_branches->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($row['branch']); ?>">
            <?php endwhile; ?>
        </datalist>
        <datalist id="education_list">
            <?php while($row = $suggest_educations->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($row['education']); ?>">
            <?php endwhile; ?>
        </datalist>
        <datalist id="city_list">
            <?php while($row = $suggest_cities->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($row['city']); ?>">
            <?php endwhile; ?>
        </datalist>
        <datalist id="town_list">
            <?php while($row = $suggest_towns->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($row['town']); ?>">
            <?php endwhile; ?>
        </datalist>
        <datalist id="district_list">
            <?php while($row = $suggest_districts->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($row['district']); ?>">
            <?php endwhile; ?>
        </datalist>
        <datalist id="village_list">
            <?php while($row = $suggest_villages->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($row['village']); ?>">
            <?php endwhile; ?>
        </datalist>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.searchable-select').select2({
        dir: "rtl",
        width: '100%'
    });
});

function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    
    document.getElementById(tabId).classList.add('active');
    event.currentTarget.classList.add('active');
}

function calculateNextPromotion() {
    const lastDateVal = document.getElementsByName('last_promotion_date')[0].value;
    const rankSelect = document.getElementsByName('rank_id')[0];
    const dueField = document.getElementById('promotion_due_date');
    
    if (!lastDateVal || !rankSelect.value) return;
    
    let yearsToAdd = 3;
    const lastDate = new Date(lastDateVal);
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

document.getElementsByName('rank_id')[0].addEventListener('change', calculateNextPromotion);
</script>

<?php require_once 'includes/layout_end.php'; ?>
