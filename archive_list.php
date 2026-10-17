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
$page_title = 'لیستی هەموو چالاکییەکان';
$active_nav = 'archive_list';
// Auto-fix for columns
$check_col = $conn->query("SHOW COLUMNS FROM activities LIKE 'is_deleted'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE activities ADD COLUMN is_deleted TINYINT DEFAULT 0 AFTER status");
}
$check_col = $conn->query("SHOW COLUMNS FROM activities LIKE 'summary'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE activities ADD COLUMN summary TEXT AFTER participants_count");
}
$check_col = $conn->query("SHOW COLUMNS FROM activities LIKE 'notes'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE activities ADD COLUMN notes TEXT AFTER summary");
}

require_once 'includes/layout.php';
?>
<style>
@keyframes blink-red {
    0% { background-color: #fff; border-color: #ffcc80; }
    50% { background-color: #ffebee; border-color: #f44336; box-shadow: 0 0 15px rgba(244, 67, 54, 0.4); }
    100% { background-color: #fff; border-color: #ffcc80; }
}
.overdue-flash {
    animation: blink-red 1.5s infinite;
    border-width: 2px !important;
}
</style>

<div style="display: flex; gap: 25px; align-items: flex-start; padding: 0 10px;">
    <!-- Main Content: Archive Activities (Right Side) -->
    <div class="lib-card" style="flex: 3; min-width: 0; margin: 20px 0 0 0;">
        <div class="card-header">
            <h3 style="text-align: center;"><?php echo $page_title; ?></h3>
        </div>

        <div style="padding: 25px;">
            <?php if (isset($_GET['success'])): ?>
                <?php if ($_GET['success'] == '1'): ?>
                    <div class="lib-toast success" style="position:relative; transform:none; margin-bottom:20px;">چالاکیەکە بە سەرکەوتوویی تۆمارکرا.</div>
                <?php elseif ($_GET['success'] == 'edit'): ?>
                    <div class="lib-toast success" style="position:relative; transform:none; margin-bottom:20px;">گۆڕانکارییەکان بە سەرکەوتوویی پاشەکەوت کران.</div>
                <?php elseif ($_GET['success'] == 'deleted'): ?>
                    <div class="lib-toast success" style="position:relative; transform:none; margin-bottom:20px; background: #f44336;">چالاکیەکە بە سەرکەوتوویی سڕایەوە.</div>
                <?php endif; ?>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid var(--secondary); padding-bottom: 10px;">
                <h4 style="margin: 0; color: var(--primary);">چالاکییەکانی ئەرشیف</h4>
                <div style="display: flex; gap: 10px;">
                    <a href="archive_print_list.php?type=completed" target="_blank" class="lib-btn lib-btn-gold" style="margin: 0;">🖨️ چاپکردنی لیست</a>
                    <a href="archive_new.php" class="lib-btn lib-btn-primary" style="margin: 0;">+ زیادکردنی چالاکی نوێ</a>
                </div>
            </div>

            <!-- Filter & Search Bar -->
            <div style="background: #ffffff; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; gap: 20px; align-items: flex-end; border: 1px solid #eef0f2; box-shadow: var(--shadow-sm); flex-wrap: wrap;">
                <div style="flex: 2; min-width: 280px;">
                    <label style="display: block; font-size: 0.95rem; margin-bottom: 8px; color: #444; font-weight: 600; font-family: 'Rabar013';">🔍 گەڕان بەناو هەموو دەقەکان</label>
                    <input type="text" id="searchInput" class="lib-input" placeholder="ناو، شوێن، تێبینی، کورتە..." 
                           value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" 
                           style="margin: 0; padding: 12px 18px; border-radius: 8px; border: 1.5px solid #ddd; font-family: 'Rabar022'; font-size: 1rem; width: 100%; transition: border-color 0.2s;">
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; font-size: 0.95rem; margin-bottom: 8px; color: #444; font-weight: 600; font-family: 'Rabar013';">📊 جۆری چالاکی</label>
                    <?php $f_val = $_GET['f_status'] ?? '1'; ?>
                    <select id="statusFilter" class="lib-input" style="margin: 0; padding: 12px 18px; border-radius: 8px; border: 1.5px solid #ddd; font-family: 'Rabar022'; font-size: 1rem; cursor: pointer; width: 100%;">
                        <option value="all" <?php echo $f_val === 'all' ? 'selected' : ''; ?>>هەمووی</option>
                        <option value="1" <?php echo $f_val === '1' ? 'selected' : ''; ?>>✅ ئەنجام دراو</option>
                        <option value="0" <?php echo $f_val === '0' ? 'selected' : ''; ?>>⏳ ئەنجام نەدراو</option>
                        <option value="2" <?php echo $f_val === '2' ? 'selected' : ''; ?>>🚫 پشتگوێ خراو</option>
                    </select>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="archive_list.php" class="lib-btn lib-btn-gold" style="padding: 12px 25px; border-radius: 8px; height: 50px; display: flex; align-items: center; justify-content: center; font-family: 'Rabar013';">سفرکردنەوە</a>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="lib-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ڕیز</th>
                            <th>ناوی چالاکی</th>
                            <th>کاتی چالاکی</th>
                            <th>شوێن</th>
                            <th>بەرپرسی</th>
                            <th>بەشداربوان</th>
                            <th>کردار</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $filter_status = isset($_GET['f_status']) ? $_GET['f_status'] : '1';
                        $search = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';

                        $where = "is_deleted = 0";
                        if ($filter_status !== 'all') {
                            $where .= " AND status = " . (int)$filter_status;
                        } else {
                            // If 'all', we might want to exclude nothing, but usually users want to see all non-deleted
                        }

                        if (!empty($search)) {
                            $where .= " AND (name LIKE '%$search%' OR summary LIKE '%$search%' OR notes LIKE '%$search%' OR location LIKE '%$search%' OR implemented_by LIKE '%$search%')";
                        }

                        $res = $conn->query("SELECT * FROM activities WHERE $where ORDER BY activity_date DESC");
                        if ($res && $res->num_rows > 0):
                            $i = 1;
                            while ($row = $res->fetch_assoc()):
                        ?>
                                <tr ondblclick="openViewModal(<?php echo $row['id']; ?>)" style="cursor: pointer;" title="بۆ بینینی وردەکاری دووجار کلیک بکە">
                                    <td><?php echo $i++; ?></td>
                                    <td style="font-weight: bold;"><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['activity_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['location']); ?></td>
                                    <td><?php echo htmlspecialchars($row['implemented_by']); ?></td>
                                    <td style="text-align:center;"><?php echo $row['participants_count']; ?></td>
                                    <td style="text-align:center; display: flex; gap: 5px; justify-content: center;">
                                        <?php if ($row['status'] == 1): ?>
                                            <a href="archive_gallery.php?id=<?php echo $row['id']; ?>" class="lib-btn lib-btn-gold"
                                                style="padding: 5px 10px; font-size: 0.8rem;">🖼️ گەلەری</a>
                                        <?php else: ?>
                                            <span class="lib-badge" style="background: #9e9e9e; color: #fff;">پشتگوێخراوە</span>
                                        <?php endif; ?>
                                        <button type="button" class="lib-btn lib-btn-gold"
                                            style="padding: 5px 10px; font-size: 0.8rem;"
                                            onclick='openPrintModal(<?php echo $row['id']; ?>)'>🖨️ ڕاپۆرت</button>
                                        <button type="button" class="lib-btn lib-btn-primary"
                                            style="padding: 5px 10px; font-size: 0.8rem;"
                                            onclick='openEditModal(<?php echo json_encode($row); ?>)'>✏️ ئیدیت</button>
                                        <?php if ($is_super_admin): ?>
                                            <button type="button" class="lib-btn lib-btn-danger"
                                                style="padding: 5px 10px; font-size: 0.8rem;"
                                                onclick='deleteActivity(<?php echo $row['id']; ?>)'>🗑️ سڕینەوە</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php
                            endwhile;
                        else:
                            echo '<tr><td colspan="7" style="text-align:center;">هیچ چالاکییەک تۆمار نەکراوە.</td></tr>';
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sidebar: Uncompleted Activities (Left Side) -->
    <div class="lib-card"
        style="flex: 1; min-width: 320px; margin: 20px 0 0 0; background: #fffbf2; border-right: 4px solid #f39c12;">
        <div class="card-header" style="background: #ffe0b2; border-bottom: 1px solid #ffcc80; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="color: #d35400; font-size: 1.1rem; margin:0;">⚠️ چالاکییە ئەنجام نەدراوەکان</h3>
            <a href="archive_print_list.php?type=pending" target="_blank" class="lib-btn lib-btn-gold" style="padding: 5px 10px; font-size: 0.75rem;">🖨️ چاپ</a>
        </div>

        <div style="padding: 20px;">
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php
                $today = date('Y-m-d');
                $res_pending = $conn->query("SELECT * FROM activities WHERE is_deleted = 0 AND status = 0 ORDER BY activity_date ASC");
                if ($res_pending && $res_pending->num_rows > 0):
                    $p = 1;
                    while ($row = $res_pending->fetch_assoc()):
                        $is_overdue = ($row['activity_date'] < $today);
                        $flash_class = $is_overdue ? 'overdue-flash' : '';
                        ?>
                        <div class="activity-card <?php echo $flash_class; ?>"
                            style="background: #fff; padding: 15px; border-radius: 10px; border: 1px solid #ffcc80; position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.03); margin-bottom: 15px;">
                            <span
                                style="position: absolute; top: -10px; right: -10px; background: <?php echo $is_overdue ? '#f44336' : '#f39c12'; ?>; color: #fff; width: 25px; height: 25px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.2);"><?php echo $p++; ?></span>
                            
                            <?php if ($is_overdue): ?>
                                <div style="color: #d32f2f; font-size: 0.75rem; font-weight: bold; margin-bottom: 5px;">⚠️ کاتەکەی تێپەڕیوە!</div>
                            <?php endif; ?>

                            <div style="font-weight: bold; margin-bottom: 5px; color: var(--primary); font-size: 0.95rem;">
                                <?php echo htmlspecialchars($row['name']); ?></div>
                            <div style="font-size: 0.8rem; color: #666; margin-bottom: 3px;">📅
                                <?php echo htmlspecialchars($row['activity_date']); ?></div>
                            <div style="font-size: 0.8rem; color: #666; margin-bottom: 10px;">📍
                                <?php echo htmlspecialchars($row['location']); ?></div>
                            
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <button type="button" class="lib-btn" style="background: #4caf50; color: #fff; padding: 4px 10px; font-size: 0.75rem; border: none; border-radius: 5px;" 
                                        onclick='updateStatus(<?php echo $row['id']; ?>, 1)'>✅ ئەنجامدرا</button>
                                    <button type="button" class="lib-btn lib-btn-primary"
                                        style="padding: 4px 10px; font-size: 0.75rem;"
                                        onclick='openEditModal(<?php echo json_encode($row); ?>)'>✏️ ئیدیت</button>
                                    <?php if ($is_super_admin): ?>
                                        <button type="button" class="lib-btn lib-btn-danger"
                                            style="padding: 4px 10px; font-size: 0.75rem;"
                                            onclick='deleteActivity(<?php echo $row['id']; ?>)'>🗑️</button>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="lib-btn" style="background: #9e9e9e; color: #fff; padding: 4px 10px; font-size: 0.75rem; border: none; border-radius: 5px; width: 100%;" 
                                    onclick='updateStatus(<?php echo $row['id']; ?>, 2)'>🚫 ئەنجام نادرێ - پشت گوێی بخە</button>
                            </div>
                        </div>
                    <?php
                    endwhile;
                else:
                    echo '<div style="text-align:center; color: #999; padding: 20px;">هیچ چالاکییەکی ئەنجام نەدراو نییە.</div>';
                endif;
                ?>
            </div>
        </div>
    </div>
</div>
        <!-- Edit Activity Modal -->
        <div class="lib-overlay" id="editModalOverlay"></div>
        <div class="lib-modal" id="editActivityModal" style="width: 600px;">
            <div class="lib-modal-header">
                <h3>دەستکاریکردنی چالاکی</h3>
                <button class="lib-modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form action="archive_save.php" method="POST" enctype="multipart/form-data">
                <div class="lib-modal-body">
                    <input type="hidden" name="activity_id" id="edit_id">

                    <div class="lib-field" style="margin-bottom: 15px;">
                        <label>ناوی چالاکی</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>

                    <div class="lib-form-grid" style="margin-bottom: 15px;">
                        <div class="lib-field">
                            <label>ڕێکەوتی چالاکی</label>
                            <input type="date" name="activity_date" id="edit_date" required>
                        </div>
                        <div class="lib-field">
                            <label>ژمارەی بەشداربوان</label>
                            <input type="number" name="participants_count" id="edit_participants" required>
                        </div>
                    </div>

                    <div class="lib-form-grid" style="margin-bottom: 15px;">
                        <div class="lib-field">
                            <label>شوێن</label>
                            <input type="text" name="location" id="edit_location" required>
                        </div>
                        <div class="lib-field">
                            <label>بەرپرسی چالاکی</label>
                            <input type="text" name="implemented_by" id="edit_implemented" required>
                        </div>
                    </div>

                    <div class="lib-field" style="margin-bottom: 15px;">
                        <label>بارودۆخ</label>
                        <select name="status" id="edit_status">
                            <option value="0">چاوەڕوانە (Pending)</option>
                            <option value="1">ئەنجامدراوە (Completed)</option>
                            <option value="2">ئەنجام نادرێت / پشتگوێخراوە (Cancelled)</option>
                        </select>
                    </div>

                    <div class="lib-field" style="margin-bottom: 15px;">
                        <label>کورتەیەک لەسەر چالاکی</label>
                        <textarea name="summary" id="edit_summary" rows="3"></textarea>
                    </div>

                    <div class="lib-field" style="margin-bottom: 15px;">
                        <label>تێبینی</label>
                        <textarea name="notes" id="edit_notes" rows="2"></textarea>
                    </div>

                    <div class="lib-field">
                        <label>زیادکردنی وێنە (ئارەزوومەندانە)</label>
                        <input type="file" name="files[]" multiple accept="image/*">
                        <small style="color: #888;">دەتوانیت وێنەی زیاتر زیاد بکەیت بۆ ئەم چالاکییە.</small>
                    </div>
                </div>
                <div class="lib-modal-footer">
                    <button type="button" class="lib-btn lib-btn-gold" onclick="closeEditModal()">پاشگەزبوونەوە</button>
                    <button type="submit" class="lib-btn lib-btn-primary">پاشەکەوتکردنی گۆڕانکارییەکان</button>
                </div>
            </form>
        </div>

        <!-- View Activity Modal -->
        <div class="lib-overlay" id="viewModalOverlay"></div>
        <div class="lib-modal" id="viewActivityModal" style="width: 850px; max-width: 95%;">
            <div class="lib-modal-header" style="background: var(--maroon); color: #fff;">
                <h3 id="view_title">وردەکاری چالاکی</h3>
                <button class="lib-modal-close" onclick="closeViewModal()" style="color: #fff;">&times;</button>
            </div>
            <div class="lib-modal-body" style="padding: 0;">
                <!-- Info Section -->
                <div style="padding: 25px; background: #fcfcfc; display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; border-bottom: 1px solid #eee;">
                    <div class="view-field"><strong>📅 ڕێکەوت:</strong> <span id="view_date"></span></div>
                    <div class="view-field"><strong>📍 شوێن:</strong> <span id="view_location"></span></div>
                    <div class="view-field"><strong>👤 بەرپرسی:</strong> <span id="view_implemented"></span></div>
                    <div class="view-field"><strong>👥 بەشداربوان:</strong> <span id="view_participants"></span></div>
                    <div style="grid-column: 1 / -1; border-top: 1px solid #eee; padding-top: 15px;">
                        <p style="font-weight: bold; margin-bottom: 8px;">📝 کورتەی چالاکی:</p>
                        <div id="view_summary" style="padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 8px; min-height: 50px;"></div>
                    </div>
                    <div style="grid-column: 1 / -1;">
                        <p style="font-weight: bold; margin-bottom: 8px;">🗒️ تێبینییەکان:</p>
                        <div id="view_notes" style="padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 8px; min-height: 50px;"></div>
                    </div>
                </div>
                
                <!-- Gallery Section -->
                <div style="padding: 25px;">
                    <h4 style="margin-bottom: 15px; color: var(--maroon);">🖼️ گەلەری وێنەکان</h4>
                    <div id="view_gallery" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px;">
                        <!-- Images injected here -->
                    </div>
                </div>
            </div>
            <div class="lib-modal-footer">
                <button type="button" class="lib-btn lib-btn-gold" onclick="closeViewModal()">داخستن</button>
            </div>
        </div>

        <style>
            .view-field { font-size: 1rem; color: #444; }
            .view-field strong { color: var(--maroon); margin-left: 5px; }
            .modal-thumb { width: 100%; height: 130px; object-fit: contain; border-radius: 8px; border: 1px solid #eee; background: #fdfdfd; cursor: pointer; transition: 0.2s; }
            .modal-thumb:hover { transform: scale(1.05); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        </style>

        <script>
            function openPrintModal(id) {
                document.getElementById('print_activity_id').value = id;
                document.getElementById('include_images_check').checked = false;
                document.getElementById('printModalOverlay').classList.add('show');
                document.getElementById('printActivityModal').classList.add('show');
            }

            function closePrintModal() {
                document.getElementById('printModalOverlay').classList.remove('show');
                document.getElementById('printActivityModal').classList.remove('show');
            }

            function generateReport() {
                const id = document.getElementById('print_activity_id').value;
                const withImages = document.getElementById('include_images_check').checked ? '1' : '0';
                window.open('archive_print_single.php?id=' + id + '&images=' + withImages, '_blank');
                closePrintModal();
            }

            function openEditModal(data) {
                document.getElementById('edit_id').value = data.id;
                document.getElementById('edit_name').value = data.name;
                
                // Fix for date loading: Ensure it's YYYY-MM-DD
                if (data.activity_date) {
                    const dateVal = data.activity_date.split(' ')[0];
                    document.getElementById('edit_date').value = dateVal;
                }
                
                document.getElementById('edit_location').value = data.location;
                document.getElementById('edit_implemented').value = data.implemented_by;
                document.getElementById('edit_participants').value = data.participants_count;
                document.getElementById('edit_summary').value = data.summary || '';
                document.getElementById('edit_notes').value = data.notes || '';
                document.getElementById('edit_status').value = data.status;

                document.getElementById('editModalOverlay').classList.add('show');
                document.getElementById('editActivityModal').classList.add('show');
            }

            function updateStatus(id, status) {
                if (!confirm('ئایا دڵنیایت لەم کردارە؟')) return;
                
                const formData = new FormData();
                formData.append('activity_id', id);
                formData.append('status', status);
                
                fetch('archive_get.php?id=' + id)
                    .then(r => r.json())
                    .then(data => {
                        formData.append('name', data.name);
                        formData.append('activity_date', data.activity_date);
                        formData.append('location', data.location);
                        formData.append('implemented_by', data.implemented_by);
                        formData.append('participants_count', data.participants_count);
                        return fetch('archive_save.php', { method: 'POST', body: formData });
                    })
                    .then(() => window.location.reload());
            }

            function deleteActivity(id) {
                if (confirm('ئایا دڵنیایت لە سڕینەوەی ئەم چالاکییە؟ ئەم کردارە ناگەڕێتەوە.')) {
                    window.location.href = 'archive_delete.php?id=' + id;
                }
            }

            let searchTimeout;
            function applyFilters() {
                const q = document.getElementById('searchInput').value;
                const f = document.getElementById('statusFilter').value;
                window.location.href = `archive_list.php?q=${encodeURIComponent(q)}&f_status=${f}`;
            }

            document.getElementById('searchInput').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(applyFilters, 500); // Wait for 500ms after last keystroke
            });

            document.getElementById('statusFilter').addEventListener('change', applyFilters);

            function closeEditModal() {
                document.getElementById('editModalOverlay').classList.remove('show');
                document.getElementById('editActivityModal').classList.remove('show');
            }

            function openViewModal(id) {
                fetch('archive_get.php?id=' + id)
                    .then(r => r.json())
                    .then(data => {
                        document.getElementById('view_title').innerText = data.name;
                        document.getElementById('view_date').innerText = data.activity_date;
                        document.getElementById('view_location').innerText = data.location;
                        document.getElementById('view_implemented').innerText = data.implemented_by;
                        document.getElementById('view_participants').innerText = data.participants_count;
                        document.getElementById('view_summary').innerText = data.summary || 'تۆمار نەکراوە';
                        document.getElementById('view_notes').innerText = data.notes || 'هیچ تێبینییەک نییە';
                        
                        const gallery = document.getElementById('view_gallery');
                        gallery.innerHTML = '';
                        if (data.images && data.images.length > 0) {
                            data.images.forEach(img => {
                                const el = document.createElement('img');
                                el.src = img;
                                el.className = 'modal-thumb';
                                el.onclick = () => window.open(img, '_blank');
                                gallery.appendChild(el);
                            });
                        } else {
                            gallery.innerHTML = '<p style="color:#999; grid-column: 1/-1;">هیچ وێنەیەک بۆ ئەم چالاکییە بارنەکراوە.</p>';
                        }

                        document.getElementById('viewModalOverlay').classList.add('show');
                        document.getElementById('viewActivityModal').classList.add('show');
                    });
            }

            function closeViewModal() {
                document.getElementById('viewModalOverlay').classList.remove('show');
                document.getElementById('viewActivityModal').classList.remove('show');
            }

            document.getElementById('viewModalOverlay').onclick = closeViewModal;
        </script>

        <?php
        echo '</div></div></div></body></html>';
        ?>