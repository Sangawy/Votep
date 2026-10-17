<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$search = $_GET['search'] ?? '';
$filter_type = $_GET['type'] ?? '';

$where = "WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $where .= " AND (m.full_name LIKE ? OR m.id_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}
if ($filter_type) {
    $where .= " AND a.leave_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

$query = "SELECT a.*, m.full_name, r.rank_name, u.unit_name 
          FROM staff_attendance a 
          JOIN staff_members m ON a.staff_id = m.id 
          JOIN staff_ranks r ON m.rank_id = r.id 
          JOIN staff_units u ON m.unit_id = u.id 
          $where 
          ORDER BY a.start_date DESC LIMIT 100";

$stmt = $conn->prepare($query);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$attendance_list = $stmt->get_result();

$page_title = 'غیابات و مۆڵەت';
$active_nav = 'staff_attendance';
$sys_module = 'nusinga';
require_once 'includes/layout.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    @font-face {
        font-family: 'Rabar013';
        src: url('assets/font/Rabar_013.ttf') format('truetype');
    }
    
    h1, h2, h3, .lib-page-title, .lib-modal-header h3, thead th {
        font-family: 'Rabar013', 'Segoe UI', Tahoma, sans-serif !important;
    }

    /* Select2 Custom Styling */
    .select2-container--default .select2-selection--single {
        height: 45px;
        border: 2px solid var(--border);
        border-radius: var(--radius);
        display: flex;
        align-items: center;
        padding: 0 10px;
        background: #fcfcfc;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 43px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        font-family: 'Rabar022', sans-serif;
        color: var(--text);
        font-size: 0.95rem;
    }
    .select2-dropdown {
        border: 2px solid var(--maroon);
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        z-index: 9999;
    }
    .select2-search__field {
        border: 2px solid var(--border) !important;
        border-radius: 8px !important;
        padding: 8px !important;
    }

    /* Rank Badge in Dropdown */
    .rank-badge {
        background-color: #c8a951;
        color: #fff;
        padding: 3px 10px;
        border-radius: 6px;
        margin-left: 10px;
        font-weight: bold;
        font-size: 0.8rem;
        display: inline-block;
        min-width: 60px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(200,169,81,0.2);
    }
    .select2-results__option--highlighted .rank-badge {
        background-color: #fff;
        color: #c8a951;
    }
</style>

<div class="lib-card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3>📅 غیابات و مۆڵەت</h3>
        <div style="display: flex; gap: 10px;">
            <a href="staff_attendance_reports.php" class="lib-btn lib-btn-gold">📊 ڕاپۆرتەکان</a>
            <button onclick="openAttendanceModal()" class="lib-btn lib-btn-primary">➕ تۆمارکردنی نوێ</button>
        </div>
    </div>



    <div style="padding: 20px; background: #f9f9f9; border-bottom: 1px solid #eee;">
        <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
            <div class="lib-field">
                <label>گەڕان بەپێی ناو یان ژمارە:</label>
                <input type="text" name="search" id="attendanceSearch" value="<?php echo htmlspecialchars($search); ?>" placeholder="بگەڕێ...">
            </div>
            <div class="lib-field">
                <label>جۆری نەهاتن:</label>
                <select name="type">
                    <option value="">هەموو جۆرەکان</option>
                    <option value="Annual Leave" <?php echo $filter_type == 'Annual Leave' ? 'selected' : ''; ?>>مۆڵەتی ئاسایی</option>
                    <option value="Sick Leave" <?php echo $filter_type == 'Sick Leave' ? 'selected' : ''; ?>>مۆڵەتی نەخۆشی</option>
                    <option value="Absence" <?php echo $filter_type == 'Absence' ? 'selected' : ''; ?>>غیابات</option>
                    <option value="Official Mission" <?php echo $filter_type == 'Official Mission' ? 'selected' : ''; ?>>ئیفاد</option>
                </select>
            </div>
            <div style="display: flex; align-items: flex-end; gap: 10px;">
                <button type="submit" class="lib-btn lib-btn-gold" style="flex: 1;">🔍 فلتەر بکە</button>
                <a href="staff_attendance.php" class="lib-btn lib-btn-danger">❌ پاککردنەوە</a>
            </div>
        </form>
    </div>

    <div class="lib-table-wrapper">
        <table class="lib-table">
            <thead>
                <tr>
                    <th>پلە و ناو</th>
                    <th>جۆری مۆڵەت</th>
                    <th>ماوە</th>
                    <th>دۆخ</th>
                    <th>کردارەکان</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($attendance_list->num_rows === 0): ?>
                    <tr><td colspan="5" style="padding: 50px;">هیچ تۆمارێک نەدۆزرایەوە</td></tr>
                <?php else: ?>
                    <?php while($a = $attendance_list->fetch_assoc()): ?>
                        <tr>
                            <td style="text-align: right;">
                                <small><?php echo htmlspecialchars($a['rank_name']); ?></small><br>
                                <strong><?php echo htmlspecialchars($a['full_name']); ?></strong>
                            </td>
                            <td>
                                <?php 
                                $badge_class = 'lib-badge-gold';
                                $type_name = $a['leave_type'];
                                switch($a['leave_type']) {
                                    case 'Annual Leave': $badge_class = 'lib-badge-green'; $type_name = 'مۆڵەتی ئاسایی'; break;
                                    case 'Sick Leave': $badge_class = 'lib-badge-blue'; $type_name = 'مۆڵەتی نەخۆشی'; break;
                                    case 'Absence': $badge_class = 'lib-badge-red'; $type_name = 'غیابات'; break;
                                    case 'Official Mission': $badge_class = 'lib-badge-maroon'; $type_name = 'ئیفاد'; break;
                                }
                                ?>
                                <span class="lib-badge <?php echo $badge_class; ?>"><?php echo $type_name; ?></span>
                            </td>
                            <td><strong><?php echo $a['duration_days']; ?></strong> ڕۆژ</td>
                            <td>
                                <?php 
                                $status_class = 'lib-badge-gold';
                                $status_text = $a['status'] ?? 'Pending';
                                if($status_text == 'Approved') $status_class = 'lib-badge-green';
                                if($status_text == 'Rejected') $status_class = 'lib-badge-red';
                                ?>
                                <span class="lib-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                            </td>
                            <td>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; width: 80px; margin: 0 auto;">
                                    <button onclick='viewAttendance(<?php echo json_encode($a); ?>)' class="lib-btn lib-btn-gold" style="padding: 5px; font-size: 0.8rem;" title="بینین">👁️</button>
                                    <button onclick='editAttendance(<?php echo json_encode($a); ?>)' class="lib-btn lib-btn-blue" style="padding: 5px; font-size: 0.8rem;" title="دەستکاری">✏️</button>
                                    <a href="staff_leave_print.php?id=<?php echo $a['id']; ?>" class="lib-btn lib-btn-primary" style="padding: 5px; font-size: 0.8rem;" title="پرینت">🖨️</a>
                                    <button onclick="deleteAttendance(<?php echo $a['id']; ?>)" class="lib-btn lib-btn-danger" style="padding: 5px; font-size: 0.8rem;" title="سڕینەوە">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Attendance Modal -->
<div id="viewAttendanceModal" class="lib-modal">
    <div class="lib-modal-content" style="max-width: 600px;">
        <div class="lib-modal-header">
            <h3>👁️ زانیارییە تەواوەکانی مۆڵەت</h3>
            <button type="button" class="lib-modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div class="lib-modal-body" id="viewModalBody">
            <!-- Content populated by JS -->
        </div>
        <div class="lib-modal-footer">
            <button type="button" class="lib-btn lib-btn-secondary" onclick="closeViewModal()">داخستن</button>
        </div>
    </div>
</div>

<!-- Edit Attendance Modal -->
<div id="editAttendanceModal" class="lib-modal">
    <div class="lib-modal-content">
        <div class="lib-modal-header">
            <h3>✏️ دەستکاریکردنی مۆڵەت</h3>
            <button type="button" class="lib-modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form action="staff_attendance_update.php" method="POST">
            <input type="hidden" name="id" id="edit_id">
            <div class="lib-modal-body">
                <div class="lib-form-group">
                    <label>کارمەند</label>
                    <input type="text" id="edit_staff_name" class="lib-input" disabled>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="lib-form-group">
                        <label>جۆری مۆڵەت</label>
                        <select name="leave_type" id="edit_leave_type" class="lib-input" required>
                            <option value="Annual Leave">مۆڵەتی ئاسایی</option>
                            <option value="Sick Leave">مۆڵەتی نەخۆشی</option>
                            <option value="Absence">غیابات</option>
                            <option value="Official Mission">ئیفاد (ئەرکی فەرمی)</option>
                        </select>
                    </div>
                    <div class="lib-form-group">
                        <label>دۆخ (Status)</label>
                        <select name="status" id="edit_status" class="lib-input">
                            <option value="Pending">چاوەڕوانکراو (Pending)</option>
                            <option value="Approved">پەسەندکراو (Approved)</option>
                            <option value="Rejected">ڕەتکراوە (Rejected)</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="lib-form-group">
                        <label>لە ڕێکەوتی</label>
                        <input type="date" name="start_date" id="edit_start_date" class="lib-input" required onchange="calculateEditDays()">
                    </div>
                    <div class="lib-form-group">
                        <label>تا ڕێکەوتی</label>
                        <input type="date" name="end_date" id="edit_end_date" class="lib-input" required onchange="calculateEditDays()">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="lib-form-group">
                        <label>ماوە (ڕۆژ)</label>
                        <input type="text" id="edit_duration_display" class="lib-input" readonly style="background: #f0f0f0;">
                    </div>
                    <div class="lib-form-group">
                        <label>فەرمانی کارگێڕی (ئارەزوومەندانە)</label>
                        <input type="text" name="order_number" id="edit_order_number" class="lib-input">
                    </div>
                </div>

                <div class="lib-form-group">
                    <label>مەبەست / شوێنی مۆڵەت</label>
                    <textarea name="reason" id="edit_reason" class="lib-input" rows="2" placeholder="بۆ نموونە: گەشتکردن بۆ دەرەوەی وڵات"></textarea>
                </div>

                <div class="lib-form-group">
                    <label>تێبینی</label>
                    <textarea name="notes" id="edit_notes" class="lib-input" rows="2"></textarea>
                </div>
            </div>

            <div class="lib-modal-footer">
                <button type="button" class="lib-btn lib-btn-danger" onclick="closeEditModal()">پاشگەزبوونەوە</button>
                <button type="submit" class="lib-btn lib-btn-primary">💾 نوێکردنەوە</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Overlay -->
<div id="modalOverlay" class="lib-overlay" onclick="closeAttendanceModal()"></div>

<!-- Add Attendance Modal -->
<div id="addAttendanceModal" class="lib-modal">
    <div class="lib-modal-content" style="max-width: 650px; width: 95vw;">
        <div class="lib-modal-header">
            <h3>➕ تۆمارکردنی نەهاتنی نوێ</h3>
            <button type="button" class="lib-modal-close" onclick="closeAttendanceModal()">&times;</button>
        </div>

        <form action="staff_attendance_save.php" method="POST" id="attendanceForm">
            <div class="lib-modal-body">
                <div class="lib-form-group">
                    <label>دیاریکردنی کارمەند:</label>
                    <select name="staff_id" required class="searchable-select" style="width: 100%;">
                        <option value="">هەڵبژێرە...</option>
                        <?php 
                        $staff = $conn->query("SELECT m.id, m.full_name, r.rank_name FROM staff_members m JOIN staff_ranks r ON m.rank_id = r.id ORDER BY r.priority DESC, m.full_name ASC");
                        while($s = $staff->fetch_assoc()): ?>
                            <option value="<?php echo $s['id']; ?>" data-rank="<?php echo htmlspecialchars($s['rank_name']); ?>" data-name="<?php echo htmlspecialchars($s['full_name']); ?>">
                                <?php echo htmlspecialchars($s['rank_name'] . ' ' . $s['full_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="lib-field">
                        <label>جۆری نەهاتن:</label>
                        <select name="leave_type" required>
                            <option value="Annual Leave">مۆڵەتی ئاسایی</option>
                            <option value="Sick Leave">مۆڵەتی نەخۆشی</option>
                            <option value="Absence">غیابات</option>
                            <option value="Official Mission">ئیفاد</option>
                        </select>
                    </div>
                    <div class="lib-field">
                        <label>فەرمانی کارگێڕی (ئارەزوومەندانە):</label>
                        <input type="text" name="order_number" placeholder="ژمارەی فەرمان">
                    </div>
                </div>

                <div class="lib-field">
                    <label>مەبەست / شوێنی مەبەست:</label>
                    <input type="text" name="reason" placeholder="بۆچی؟ یان بۆ کوێ؟" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="lib-field">
                        <label>لە ڕێکەوتی:</label>
                        <input type="date" name="start_date" required onchange="calculateDays()">
                    </div>
                    <div class="lib-field">
                        <label>تا ڕێکەوتی:</label>
                        <input type="date" name="end_date" required onchange="calculateDays()">
                    </div>
                </div>

                <div class="lib-field">
                    <label>ماوە (ڕۆژ):</label>
                    <input type="number" id="duration_display" name="duration_days" readonly style="background: #eee;">
                </div>

                <div class="lib-field">
                    <label>تێبینی:</label>
                    <textarea name="notes" rows="2"></textarea>
                </div>
            </div>

            <div class="lib-modal-footer">
                <button type="button" class="lib-btn lib-btn-danger" onclick="closeAttendanceModal()">پاشگەزبوونەوە</button>
                <button type="submit" class="lib-btn lib-btn-primary">💾 پاشکەوتکردن</button>
            </div>
        </form>
    </div>
</div>

<!-- Print Confirmation Modal -->
<div id="printConfirmModal" class="lib-modal">
    <div class="lib-modal-content" style="max-width: 450px; text-align: center; padding: 30px;">
        <div style="font-size: 3rem; margin-bottom: 20px;">🖨️</div>
        <h3 style="margin-bottom: 15px; color: #333;">تۆمارکردن سەرکەوتوو بوو</h3>
        <p style="margin-bottom: 25px; color: #666; line-height: 1.6;">تۆمارەکە بە سەرکەوتوویی پاشکەوتکرا. ئایا دەتەوێت فۆڕمی داواکاری مۆڵەتەکە پرینت بکەیت بۆ واژۆکردن؟</p>
        
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button id="confirmPrintBtn" class="lib-btn lib-btn-primary" style="padding: 10px 25px;">بەڵێ، پرینت بکە</button>
            <button onclick="closePrintModal()" class="lib-btn lib-btn-danger" style="padding: 10px 25px;">نەخێر، دواتر</button>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmModal" class="lib-modal">
    <div class="lib-modal-content" style="max-width: 450px; text-align: center; padding: 30px;">
        <div style="font-size: 3rem; margin-bottom: 20px;">⚠️</div>
        <h3 style="margin-bottom: 15px; color: #d9534f;">دڵنیایت لە سڕینەوە؟</h3>
        <p style="margin-bottom: 25px; color: #666; line-height: 1.6;">ئەم تۆمارە بە هەمیشەیی دەسڕێتەوە و کاریگەری لەسەر پلە بەرزکردنەوەی کارمەندەکەش دەبێت. ئایا دڵنیایت؟</p>
        
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button id="confirmDeleteBtn" class="lib-btn lib-btn-danger" style="padding: 10px 25px;">بەڵێ، بسڕەوە</button>
            <button onclick="closeDeleteModal()" class="lib-btn lib-btn-secondary" style="padding: 10px 25px;">پاشگەزبوونەوە</button>
        </div>
    </div>
</div>

<script>
<?php if (isset($_GET['success']) && isset($_GET['print_id'])): ?>
    window.onload = function() {
        showPrintModal(<?php echo (int)$_GET['print_id']; ?>);
    };
<?php endif; ?>

function showPrintModal(id) {
    const modal = document.getElementById('printConfirmModal');
    const overlay = document.getElementById('modalOverlay');
    const printBtn = document.getElementById('confirmPrintBtn');
    
    printBtn.onclick = function() {
        window.location.href = 'staff_leave_print.php?id=' + id;
    };
    
    modal.classList.add('show');
    overlay.classList.add('show');
}

function closePrintModal() {
    document.getElementById('printConfirmModal').classList.remove('show');
    document.getElementById('modalOverlay').classList.remove('show');
}

function viewAttendance(data) {
    const body = document.getElementById('viewModalBody');
    let typeName = data.leave_type;
    if(data.leave_type === 'Annual Leave') typeName = 'مۆڵەتی ئاسایی';
    if(data.leave_type === 'Sick Leave') typeName = 'مۆڵەتی نەخۆشی';
    if(data.leave_type === 'Absence') typeName = 'غیابات';
    if(data.leave_type === 'Official Mission') typeName = 'ئیفاد';

    body.innerHTML = `
        <div style="text-align: center; background: #fdf5e6; padding: 20px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #eee;">
            <div style="font-family: 'Rabar013', sans-serif; font-size: 1.3rem; color: #333;">${data.rank_name} ${data.full_name}</div>
            <div style="color: #666; margin-top: 5px;">${data.unit_name}</div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px;">
            <div><strong>جۆری مۆڵەت:</strong><br>${typeName}</div>
            <div><strong>ماوە:</strong><br>${data.duration_days} ڕۆژ</div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px;">
            <div><strong>دەستپێک:</strong><br>${data.start_date}</div>
            <div><strong>کۆتایی:</strong><br>${data.end_date}</div>
        </div>
        <div style="margin-bottom: 15px;">
            <strong>مەبەست / شوێنی مەبەست:</strong><br>
            <div style="background: #f9f9f9; padding: 10px; border-radius: 5px; margin-top: 5px;">${data.reason || '-'}</div>
        </div>
        <div style="margin-bottom: 15px;">
            <strong>فەرمانی کارگێڕی:</strong><br>
            <div style="background: #f9f9f9; padding: 10px; border-radius: 5px; margin-top: 5px;">${data.order_number || '-'}</div>
        </div>
        <div>
            <strong>تێبینییەکان:</strong><br>
            <div style="background: #f9f9f9; padding: 10px; border-radius: 5px; margin-top: 5px;">${data.notes || '-'}</div>
        </div>
    `;
    
    document.getElementById('viewAttendanceModal').classList.add('show');
    document.getElementById('modalOverlay').classList.add('show');
}

function closeViewModal() {
    document.getElementById('viewAttendanceModal').classList.remove('show');
    document.getElementById('modalOverlay').classList.remove('show');
}

function editAttendance(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_staff_name').value = data.rank_name + ' ' + data.full_name;
    document.getElementById('edit_leave_type').value = data.leave_type;
    document.getElementById('edit_status').value = data.status || 'Pending';
    document.getElementById('edit_start_date').value = data.start_date;
    document.getElementById('edit_end_date').value = data.end_date;
    document.getElementById('edit_duration_display').value = data.duration_days;
    document.getElementById('edit_order_number').value = data.order_number || '';
    document.getElementById('edit_reason').value = data.reason || '';
    document.getElementById('edit_notes').value = data.notes || '';
    
    document.getElementById('editAttendanceModal').classList.add('show');
    document.getElementById('modalOverlay').classList.add('show');
}

function closeEditModal() {
    document.getElementById('editAttendanceModal').classList.remove('show');
    document.getElementById('modalOverlay').classList.remove('show');
}

function calculateEditDays() {
    const start = document.getElementById('edit_start_date').value;
    const end = document.getElementById('edit_end_date').value;
    if (start && end) {
        const d1 = new Date(start);
        const d2 = new Date(end);
        const diffTime = Math.abs(d2 - d1);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; 
        document.getElementById('edit_duration_display').value = diffDays;
    }
}

function openAttendanceModal() {
    document.getElementById('addAttendanceModal').classList.add('show');
    document.getElementById('modalOverlay').classList.add('show');
}

function closeAttendanceModal() {
    document.getElementById('addAttendanceModal').classList.remove('show');
    document.getElementById('modalOverlay').classList.remove('show');
}


function calculateDays() {
    const start = document.getElementsByName('start_date')[0].value;
    const end = document.getElementsByName('end_date')[0].value;
    if (start && end) {
        const d1 = new Date(start);
        const d2 = new Date(end);
        const diffTime = Math.abs(d2 - d1);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; 
        document.getElementById('duration_display').value = diffDays;
    }
}

function deleteAttendance(id) {
    const modal = document.getElementById('deleteConfirmModal');
    const overlay = document.getElementById('modalOverlay');
    const deleteBtn = document.getElementById('confirmDeleteBtn');
    
    deleteBtn.onclick = function() {
        window.location.href = 'staff_attendance_delete.php?id=' + id;
    };
    
    modal.classList.add('show');
    overlay.classList.add('show');
}

function closeDeleteModal() {
    document.getElementById('deleteConfirmModal').classList.remove('show');
    document.getElementById('modalOverlay').classList.remove('show');
}


document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('attendanceSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('.lib-table tbody tr');
            
            rows.forEach(row => {
                if (row.cells.length > 1) { 
                    let text = row.innerText.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                }
            });
        });
    }
});
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    function formatStaff(state) {
        if (!state.id) return state.text;
        var rank = $(state.element).data('rank');
        var name = $(state.element).data('name');
        if (!rank) return state.text;
        
        var $state = $(
            '<span><span class="rank-badge">' + rank + '</span> <span class="staff-name">' + name + '</span></span>'
        );
        return $state;
    };

    $('.searchable-select').select2({
        dir: "rtl",
        width: '100%',
        dropdownParent: $('#addAttendanceModal'),
        templateResult: formatStaff,
        templateSelection: formatStaff,
        escapeMarkup: function(m) { return m; }
    });
});
</script>

<style>
/* Simple Select2 fallback if not loaded */
select.select2 {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
</style>

<?php require_once 'includes/layout_end.php'; ?>
