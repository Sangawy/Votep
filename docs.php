<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$user_id = $_SESSION['user_id'];

// Handle Open Local Word File via shell_exec
if (isset($_GET['open_file'])) {
    $id = (int)$_GET['open_file'];
    $res = $conn->query("SELECT file_path FROM archive_docs WHERE id = $id");
    if ($res && $row = $res->fetch_assoc()) {
        $path = $row['file_path'];
        if (file_exists($path)) {
            // Use explorer.exe which is more reliable for file opening on Windows via PHP
            $winPath = str_replace('/', '\\', $path);
            $cmd = 'explorer.exe "' . $winPath . '"';
            @exec($cmd);
            echo "Request sent to Windows Explorer: " . $cmd;
        } else {

            echo "File not found: " . htmlspecialchars($path);
        }
    }
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("SELECT file_path FROM archive_docs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $path = $row['file_path'];
        if (file_exists($path)) {
            @unlink($path);
        }
        $delStmt = $conn->prepare("DELETE FROM archive_docs WHERE id = ?");
        $delStmt->bind_param("i", $id);
        $delStmt->execute();
    }
    header('Location: docs.php?msg=deleted');
    exit;
}

// Handle open local file directly (bypasses browser security warning)
if (isset($_GET['open_local'])) {
    $id = (int)$_GET['open_local'];
    $stmt = $conn->prepare("SELECT file_path FROM archive_docs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $winPath = str_replace('/', '\\', $row['file_path']);
        $cmd = 'cmd /c start "" "' . str_replace('"', '\"', $winPath) . '"';
        @pclose(@popen($cmd, 'r'));
    }
    // Return 204 No Content to prevent page reload
    http_response_code(204);
    exit;
}

$search = $_GET['search'] ?? '';
$type_filter = $_GET['doc_type'] ?? '';
$month_filter = 0;
$year_filter = 0;
$month_year = trim($_GET['month_year'] ?? '');
$legacy_month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$legacy_year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$month_picker_value = '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

if (preg_match('/^(\d{4})-(\d{2})$/', $month_year, $m)) {
    $year_filter = (int)$m[1];
    $month_filter = (int)$m[2];
    $month_picker_value = sprintf('%04d-%02d', $year_filter, $month_filter);
} else {
    if ($legacy_month >= 1 && $legacy_month <= 12) $month_filter = $legacy_month;
    if ($legacy_year >= 1900 && $legacy_year <= 2100) $year_filter = $legacy_year;
    if ($month_filter >= 1 && $year_filter >= 1900) {
        $month_picker_value = sprintf('%04d-%02d', $year_filter, $month_filter);
    }
}

$whereClause = "WHERE 1=1";
if ($search) {
    $term = $conn->real_escape_string($search);
    $whereClause .= " AND (
        a.doc_number LIKE '%$term%'
        OR a.subject LIKE '%$term%'
        OR a.doc_type LIKE '%$term%'
        OR a.correspondent LIKE '%$term%'
        OR a.doc_date LIKE '%$term%'
        OR a.file_path LIKE '%$term%'
        OR a.id LIKE '%$term%'
    )";
}
if ($type_filter) {
    $term = $conn->real_escape_string($type_filter);
    $whereClause .= " AND a.doc_type = '$term'";
}
if ($month_filter >= 1 && $month_filter <= 12) {
    $whereClause .= " AND MONTH(a.doc_date) = $month_filter";
}
if ($year_filter >= 1900 && $year_filter <= 2100) {
    $whereClause .= " AND YEAR(a.doc_date) = $year_filter";
}
if ($date_from !== '') {
    $df = $conn->real_escape_string($date_from);
    $whereClause .= " AND a.doc_date >= '$df'";
}
if ($date_to !== '') {
    $dt = $conn->real_escape_string($date_to);
    $whereClause .= " AND a.doc_date <= '$dt'";
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$total_rows = $conn->query("SELECT COUNT(*) FROM archive_docs a $whereClause")->fetch_row()[0];
$total_pages = ceil($total_rows / $limit);

$docs = $conn->query("SELECT a.*, r.doc_number as rel_doc_num, r.subject as rel_subject, r.doc_type as rel_doc_type 
                      FROM archive_docs a 
                      LEFT JOIN archive_docs r ON a.related_doc_id = r.id
                      $whereClause 
                      ORDER BY a.id DESC 
                      LIMIT $offset, $limit");
$gallery_files = [];

$sys_module = 'docs';
$page_title = 'نوسراوەکان';
$active_nav = 'docs';

require_once 'includes/layout.php';
?>

<style>
@font-face {
    font-family: 'Rabar022';
    src: url('assets/font/Rabar_022.ttf') format('truetype');
    font-display: swap;
}
@font-face {
    font-family: 'Rabar005';
    src: url('assets/font/Rabar_005.ttf') format('truetype');
    font-display: swap;
}
.docs-filter-font {
    font-family: 'Rabar022', Tahoma, Arial, sans-serif !important;
}
.docs-filter-form { display: flex; flex-direction: column; gap: 16px; flex: 1; width: 100%; }
.docs-filter-row { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
.docs-filter-item { display: flex; flex-direction: column; flex: 1; min-width: 200px; }
.docs-filter-item.search-box { flex: 2; min-width: 300px; }
.docs-filter-item.btn-box { flex: 0 0 auto; min-width: 140px; }
.docs-filter-item.clear-box { flex: 0 0 auto; min-width: 140px; }
.docs-filter-item label { display: block; font-size: 0.85rem; margin-bottom: 6px; color: #4a5568; font-weight: 700; }
.docs-filter-item .hint { display: block; font-size: 0.75rem; color: #a0aec0; margin-top: 6px; }
.docs-filter-item .lib-field { width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px; transition: all 0.2s ease; background: #f8fafc; height: 42px; box-sizing: border-box; }
.docs-filter-item .lib-field:focus { border-color: var(--gold); background: #fff; outline: none; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15); }
.docs-filter-item .lib-btn { height: 42px; width: 100%; justify-content: center; font-weight: 600; letter-spacing: 0.5px; border-radius: 8px; }
.docs-filter-item .lib-btn-danger { background-color: #f56565; border-color: #f56565; }
.docs-filter-item .lib-btn-danger:hover { background-color: #e53e3e; border-color: #e53e3e; }
.filter-header-wrap { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #edf2f7; padding-bottom: 15px; }
.filter-header-wrap h4 { margin: 0; color: #2d3748; font-size: 1.1rem; font-weight: 700; }
</style>

<div class="lib-card" style="margin-bottom: 25px; padding: 25px;">
    <div class="filter-header-wrap">
        <h4>گەڕان و فلتەرکردن</h4>
        <a href="docs_create.php" class="lib-btn lib-btn-gold">➕ تۆمارکردنی نوێ</a>
    </div>
    <form method="GET" class="docs-filter-form">
        <div class="docs-filter-row">
            <div class="docs-filter-item search-box">
                <label>سێرچ بۆکس</label>
                <input type="text" name="search" placeholder="گەڕان لە هەموو کۆڵۆمەکان..." value="<?php echo htmlspecialchars($search); ?>" class="lib-field docs-filter-font">
                <span class="hint">بە بابەت، ژمارە، لایەن، ڕێڕەو...</span>
            </div>
            <div class="docs-filter-item">
                <label>جۆری نوسراوە</label>
                <select name="doc_type" class="lib-field docs-filter-font">
                    <option value="">هەموو جۆرەکان</option>
                    <option value="Inbound" <?php echo $type_filter == 'Inbound' ? 'selected' : ''; ?>>هاتوو (Inbound)</option>
                    <option value="Outbound" <?php echo $type_filter == 'Outbound' ? 'selected' : ''; ?>>دەرچوو (Outbound)</option>
                </select>
                <span class="hint">فلتەر بە جۆر</span>
            </div>
            <div class="docs-filter-item btn-box">
                <button type="submit" class="lib-btn lib-btn-primary">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="margin-inline-end: 6px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    گەڕان
                </button>
                <span class="hint" style="text-align: center;">جێبەجێکردنی فلتەر</span>
            </div>
        </div>

        <div class="docs-filter-row">
            <div class="docs-filter-item">
                <label>مانگ/ساڵ</label>
                <input type="month" name="month_year" class="lib-field docs-filter-font" value="<?php echo htmlspecialchars($month_picker_value); ?>">
                <span class="hint">وەک: `2026-05`</span>
            </div>
            <div class="docs-filter-item">
                <label>لە بەروار (From)</label>
                <input type="date" name="date_from" class="lib-field docs-filter-font" value="<?php echo htmlspecialchars($date_from); ?>">
                <span class="hint">دەستپێکی ماوە</span>
            </div>
            <div class="docs-filter-item">
                <label>بۆ بەروار (To)</label>
                <input type="date" name="date_to" class="lib-field docs-filter-font" value="<?php echo htmlspecialchars($date_to); ?>">
                <span class="hint">کۆتایی ماوە</span>
            </div>
            <div class="docs-filter-item clear-box">
                <a href="docs.php" class="lib-btn lib-btn-danger">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" style="margin-inline-end: 6px;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    پاککردنەوە
                </a>
                <span class="hint" style="text-align: center;">سڕینەوەی فلتەرەکان</span>
            </div>
        </div>
    </form>
</div>

<div class="lib-card">
    <div class="lib-table-wrapper">
        <table class="lib-table">
            <thead>
                <tr>
                    <th style="width: 80px;">ژمارە</th>
                    <th>بابەت</th>
                    <th>جۆر</th>
                    <th>لایەنی بەرامبەر</th>
                    <th>ڕێکەوت</th>
                    <th>ڕێڕەوی فایل</th>
                    <th style="width: 180px; text-align: center;">کردارەکان</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($docs->num_rows == 0): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 40px; color: #999;">هیچ دۆکیومێنتێک نەدۆزرایەوە</td></tr>
                <?php else: $rowIndex = 0; while($d = $docs->fetch_assoc()): ?>
                    <?php
                        $normalizedPath = str_replace('\\', '/', (string)$d['file_path']);
                        $gallery_files[] = [
                            'id' => (int)$d['id'],
                            'doc_number' => (string)$d['doc_number'],
                            'doc_type' => (string)$d['doc_type'],
                            'path' => (string)$d['file_path'],
                            'name' => basename($normalizedPath),
                            'subject' => (string)$d['subject'],
                            'rel_id' => $d['related_doc_id'],
                            'rel_text' => $d['rel_doc_num'] ? "[{$d['rel_doc_type']}] ژمارە {$d['rel_doc_num']} - {$d['rel_subject']}" : null,
                        ];
                    ?>
                    <tr>
                        <td><strong><?php echo $d['doc_number']; ?></strong></td>
                        <td><?php echo htmlspecialchars($d['subject']); ?></td>
                        <td>
                            <span class="lib-badge <?php echo $d['doc_type'] == 'Inbound' ? 'lib-badge-green' : 'lib-badge-gold'; ?>">
                                <?php echo $d['doc_type'] == 'Inbound' ? '📥 هاتوو' : '📤 دەرچوو'; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($d['correspondent']); ?></td>
                        <td><?php echo $d['doc_date']; ?></td>
                        <td style="text-align: center;">
                            <?php 
                            if (!empty($d['file_path'])): 
                                $ext = strtolower(pathinfo($d['file_path'], PATHINFO_EXTENSION));
                                $iconImg = 'd.png'; // Default
                                
                                if (in_array($ext, ['docx', 'doc'])) { $iconImg = 'Word.png'; }
                                elseif ($ext === 'pdf') { $iconImg = 'PDF.png'; }
                                elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) { $iconImg = 'ImageFolder.png'; }
                                elseif (in_array($ext, ['xlsx', 'xls'])) { $iconImg = 'd.png'; }
                            ?>
                                <div onclick="openLocalFile('<?php echo $d['id']; ?>')" 
                                     title="<?php echo htmlspecialchars($d['file_path']); ?>" 
                                     style="cursor: pointer; transition: 0.2s; display: inline-block;">
                                    <img src="assets/imgae/<?php echo $iconImg; ?>" alt="icon" style="width: 32px; height: 32px; object-fit: contain;">
                                </div>
                            <?php else: ?>
                                <span style="color: #ccc;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="lib-actions" style="flex-direction: row; gap: 5px;">
                                <button type="button" onclick="openFileViewer(<?php echo $rowIndex; ?>)" class="lib-btn lib-btn-primary" style="padding: 6px 10px;" title="بینینی فایل">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    بینین
                                </button>
                                
                                <a href="docs_create.php?edit_id=<?php echo $d['id']; ?>" class="lib-btn lib-btn-gold" style="padding: 6px 10px;" title="دەستکاریکردن">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px; height:14px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    دەستکاری
                                </a>

                                <button type="button" onclick="fetch('docs.php?open_local=<?php echo $d['id']; ?>');" class="lib-btn lib-btn-gold" style="padding: 6px 10px;" title="کردنەوە لە ناو سیستەم">
                                    کردنەوەی Word
                                </button>
                                <a href="docs.php?delete=<?php echo $d['id']; ?>" class="lib-btn lib-btn-danger" style="padding: 6px;" onclick="return confirm('ئایا دڵنیایت؟')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php $rowIndex++; ?>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($total_pages > 1): ?>
    <div class="lib-pagination">
        <?php for($i=1; $i<=$total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&doc_type=<?php echo urlencode($type_filter); ?>&month_year=<?php echo urlencode($month_picker_value); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" class="lib-page-btn <?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<!-- Modern File Viewer Modal -->
<div id="fileViewerModal">
    <div class="viewer-container">
        <div class="viewer-header">
            <div class="viewer-header-info">
                <h3 id="viewerTitle">پیشاندانی فایل</h3>
                <span id="viewerSubTitle">پشکنینی ناوەڕۆکی دۆکیومێنت</span>
            </div>
            <button type="button" class="viewer-close" onclick="closeFileViewer()">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <div class="viewer-body">
            <aside class="viewer-sidebar">
                <div>
                    <span class="viewer-section-title">جوڵە لە نێوان فایلەکان</span>
                    <div id="viewerCounter" style="text-align:center; margin-bottom:12px; font-weight:700; color:#1e293b;">- / -</div>
                    <div class="viewer-nav-grid">
                        <button type="button" id="viewerPrevBtn" class="viewer-sidebar-btn btn-nav-secondary">پێشتر</button>
                        <button type="button" id="viewerNextBtn" class="viewer-sidebar-btn btn-nav-secondary">دواتر</button>
                    </div>
                </div>

                <div style="margin-top: 10px;">
                    <span class="viewer-section-title">کردارە خێراکان</span>
                    <button type="button" id="viewerOpenSystemBtn" class="viewer-sidebar-btn btn-nav-gold" style="width:100%;">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                        کردنەوە بە سیستم
                    </button>
                </div>

                <div class="viewer-search-box">
                    <span class="viewer-section-title">گەڕان لە ناو دەق</span>
                    <input id="viewerSearchInput" type="text" class="viewer-search-input" placeholder="چی دەپشکنیت؟">
                    <button type="button" id="viewerSearchBtn" class="viewer-sidebar-btn btn-nav-primary" style="width:100%;">دەستپێکردنی گەڕان</button>
                    <div style="display:flex; gap:8px; margin-top:8px;">
                        <button type="button" id="viewerSearchPrevBtn" class="viewer-sidebar-btn btn-nav-secondary" style="flex:1;">↑</button>
                        <button type="button" id="viewerSearchNextBtn" class="viewer-sidebar-btn btn-nav-secondary" style="flex:1;">↓</button>
                    </div>
                    <div id="viewerSearchCount" style="margin-top:10px; font-size:0.8rem; color:#64748b; text-align:center;">-</div>
                </div>
                
                <div id="relatedDocSection" style="display:none;">
                    <span class="viewer-section-title">🔗 نوسراوی پەیوەندیدار</span>
                    <div id="relatedDocLink" style="font-size:0.85rem; color:#0f172a; background:#f1f5f9; padding:12px; border-radius:12px; cursor:pointer; border:1px solid #e2e8f0; transition:all 0.2s;"></div>
                </div>

                <div id="responsesSection" style="display:none;">
                    <span class="viewer-section-title">📨 وەڵامەکان</span>
                    <div id="responsesList" style="display:flex; flex-direction:column; gap:8px;"></div>
                </div>
            </aside>
            <section class="viewer-main-content">
                <div id="fileViewerContent"></div>
            </section>
        </div>
    </div>
</div>


<style>
/* --- Modern Page Elements --- */
#pageToast {
    position: fixed;
    top: 25px;
    left: 50%;
    transform: translateX(-50%);
    min-width: 320px;
    padding: 16px 24px;
    border-radius: 16px;
    color: #fff;
    font-weight: 600;
    z-index: 3000;
    opacity: 0;
    pointer-events: none;
    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    display: flex;
    align-items: center;
    gap: 12px;
}
#pageToast.show { opacity: 1; transform: translateX(-50%) translateY(10px); }
#pageToast.success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
#pageToast.info { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }

/* --- Modern File Viewer Styles --- */
#fileViewerModal {
    display: none;
    position: fixed;
    z-index: 2000;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(12px);
    justify-content: center;
    align-items: center;
    padding: 20px;
}

#fileViewerModal .viewer-container {
    background: #fff;
    width: 100%;
    max-width: 1300px;
    height: 94vh;
    border-radius: 24px;
    box-shadow: 0 25px 70px -10px rgba(0, 0, 0, 0.4);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: viewerSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes viewerSlideUp {
    from { opacity: 0; transform: translateY(40px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.viewer-header {
    background: #ffffff;
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #f1f5f9;
}

.viewer-header-info h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 800;
    color: #0f172a;
}

.viewer-header-info span {
    font-size: 0.85rem;
    color: #64748b;
}

.viewer-close {
    background: #f1f5f9;
    border: none;
    color: #64748b;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.viewer-close:hover {
    background: #fee2e2;
    color: #ef4444;
    transform: rotate(90deg);
}

.viewer-body {
    flex: 1;
    display: flex;
    background: #f8fafc;
    overflow: hidden;
}

.viewer-sidebar {
    width: 320px;
    background: #ffffff;
    border-inline-end: 1px solid #f1f5f9;
    padding: 30px;
    display: flex;
    flex-direction: column;
    gap: 25px;
    overflow-y: auto;
}

.viewer-section-title {
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #94a3b8;
    letter-spacing: 0.1em;
    margin-bottom: 12px;
    display: block;
}

.viewer-nav-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.viewer-sidebar-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px;
    border-radius: 14px;
    font-weight: 700;
    font-size: 0.95rem;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    border: 2px solid transparent;
    font-family: inherit;
}

.btn-nav-primary { background: #0f172a; color: #fff; }
.btn-nav-primary:hover { background: #1e293b; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(15, 23, 42, 0.15); }

.btn-nav-secondary { background: #f1f5f9; color: #475569; }
.btn-nav-secondary:hover { background: #e2e8f0; color: #1e293b; }

.btn-nav-gold { background: #fef3c7; color: #92400e; border-color: #fde68a; }
.btn-nav-gold:hover { background: #fde68a; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(251, 191, 36, 0.15); }

.viewer-search-box {
    background: #f8fafc;
    border-radius: 20px;
    padding: 20px;
    border: 1px solid #f1f5f9;
}

.viewer-search-input {
    width: 100%;
    padding: 12px 16px;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    font-size: 0.9rem;
    margin-bottom: 12px;
    transition: border-color 0.2s;
}

.viewer-search-input:focus { border-color: #0f172a; outline: none; }

.viewer-main-content {
    flex: 1;
    overflow-y: auto;
    padding: 50px;
    display: flex;
    justify-content: center;
    background: #f1f5f9;
    background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
    background-size: 24px 24px;
}

.a4-page {
    background: #ffffff;
    width: 100%;
    max-width: 850px;
    min-height: 1100px;
    padding: 80px 100px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.05), 0 1px 3px rgba(0,0,0,0.1);
    border-radius: 2px;
    position: relative;
    animation: pageReveal 0.6s ease;
}

@keyframes pageReveal {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.docs-docx-render {
    line-height: 2;
    font-size: 1.15rem;
    color: #1e293b;
    font-family: 'Rabar005', 'Rabar022', Tahoma, sans-serif;
}

.viewer-hit { background: #fef08a; border-radius: 4px; box-shadow: 0 0 0 2px #fef08a; }
.viewer-hit.active { background: #fbbf24; box-shadow: 0 0 0 2px #fbbf24; }

@media (max-width: 1000px) {
    .viewer-body { flex-direction: column; }
    .viewer-sidebar { width: 100%; height: auto; padding: 20px; }
    .viewer-container { height: 98vh; }
}
</style>

<div id="pageToast"></div>

<script src="assets/js/mammoth.browser.min.js"></script>
<script>
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('msg') && urlParams.get('msg') === 'created') {
        showPageToast('سەرکەوتوو: نوسراوەکە تۆمارکرا.', 'success');
        const cleanUrl = new URL(window.location.href);
        cleanUrl.searchParams.delete('msg');
        history.replaceState(null, '', cleanUrl.toString());
    }
}

function showPageToast(message, type) {
    const toast = document.getElementById('pageToast');
    toast.textContent = message;
    toast.className = '';
    toast.classList.add(type === 'success' ? 'success' : 'info');
    toast.classList.add('show');
    setTimeout(function() {
        toast.classList.remove('show');
    }, 2600);
}

function openLocalFile(id) {
    fetch('docs.php?open_file=' + id)
        .then(response => response.text())
        .then(txt => {
            console.log('Open Local File Result:', txt);
        });
}

const galleryFiles = <?php echo json_encode($gallery_files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
let activeFileIndex = 0;
let activeObjectUrl = null;
let currentDocxHtml = '';
let currentSearchMatches = [];
let currentSearchIndex = -1;

function detectFileType(url) {
    const cleanUrl = String(url || '').split('?')[0].toLowerCase();
    if (/\.(png|jpe?g|gif|webp|bmp|svg)$/.test(cleanUrl)) return 'image';
    if (/\.pdf$/.test(cleanUrl)) return 'pdf';
    if (/\.docx$/.test(cleanUrl)) return 'docx';
    return 'other';
}

function toBase64Utf8(text) {
    // Use TextEncoder for reliable UTF-8 encoding (supports Kurdish/Arabic/Unicode)
    const bytes = new TextEncoder().encode(text);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
}

async function fetchFileBlob(item) {
    const encodedPath = encodeURIComponent(toBase64Utf8(item.path || ''));
    const response = await fetch('stream_doc.php?f=' + encodedPath);
    if (!response.ok) {
        throw new Error('stream_failed');
    }
    const payload = await response.json();
    console.log('[DEBUG stream_doc response]', payload);
    if (payload.error) {
        throw new Error('stream_error:' + payload.error);
    }
    if (!payload || !payload.data) {
        throw new Error('empty_payload');
    }
    const binary = atob(payload.data);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

function revokeActiveObjectUrl() {
    if (activeObjectUrl) {
        URL.revokeObjectURL(activeObjectUrl);
        activeObjectUrl = null;
    }
}

function getViewerContentEl() {
    return document.getElementById('fileViewerContent');
}

function renderDocxHtml(html) {
    getViewerContentEl().innerHTML = '<div class="a4-stage"><article class="a4-page">' + html + '</article></div>';
}

function resetSearchState() {
    currentSearchMatches = [];
    currentSearchIndex = -1;
    document.getElementById('viewerSearchCount').textContent = '-';
}

function escapeRegExp(text) {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function clearDocxSearchRender() {
    if (!currentDocxHtml) return;
    renderDocxHtml(currentDocxHtml);
    resetSearchState();
}

function highlightMatches(term) {
    clearDocxSearchRender();
    const root = document.querySelector('#fileViewerContent .a4-page');
    if (!root || !term) return 0;
    const regex = new RegExp(escapeRegExp(term), 'gi');
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
    const textNodes = [];
    while (walker.nextNode()) {
        const node = walker.currentNode;
        if (node.nodeValue && node.nodeValue.trim()) textNodes.push(node);
    }

    for (let i = 0; i < textNodes.length; i++) {
        const node = textNodes[i];
        const value = node.nodeValue;
        regex.lastIndex = 0;
        let match = regex.exec(value);
        if (!match) continue;
        const frag = document.createDocumentFragment();
        let last = 0;
        do {
            const start = match.index;
            const end = start + match[0].length;
            if (start > last) frag.appendChild(document.createTextNode(value.slice(last, start)));
            const mark = document.createElement('mark');
            mark.className = 'viewer-hit';
            mark.textContent = value.slice(start, end);
            frag.appendChild(mark);
            last = end;
            match = regex.exec(value);
        } while (match);
        if (last < value.length) frag.appendChild(document.createTextNode(value.slice(last)));
        node.parentNode.replaceChild(frag, node);
    }

    currentSearchMatches = Array.from(document.querySelectorAll('#fileViewerContent .viewer-hit'));
    return currentSearchMatches.length;
}

function focusMatch(index) {
    if (!currentSearchMatches.length) return;
    if (index < 0) index = currentSearchMatches.length - 1;
    if (index >= currentSearchMatches.length) index = 0;
    currentSearchIndex = index;
    currentSearchMatches.forEach(function(el) { el.classList.remove('active'); });
    const current = currentSearchMatches[currentSearchIndex];
    current.classList.add('active');
    current.scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.getElementById('viewerSearchCount').textContent = (currentSearchIndex + 1) + ' / ' + currentSearchMatches.length;
}

function runDocxSearch() {
    const term = document.getElementById('viewerSearchInput').value.trim();
    if (!currentDocxHtml) {
        document.getElementById('viewerSearchCount').textContent = 'تەنها بۆ DOCX';
        return;
    }
    if (!term) {
        clearDocxSearchRender();
        return;
    }
    const total = highlightMatches(term);
    if (total > 0) {
        focusMatch(0);
    } else {
        document.getElementById('viewerSearchCount').textContent = 'نەدۆزرایەوە';
    }
}

async function renderFileInViewer(index) {
    if (!galleryFiles.length) return;
    activeFileIndex = Math.max(0, Math.min(index, galleryFiles.length - 1));
    const item = galleryFiles[activeFileIndex];
    const fileType = detectFileType(item.path || item.name || '');

    document.getElementById('viewerTitle').textContent = item.subject || 'پیشاندانی فایل';
    document.getElementById('viewerSubTitle').textContent = item.name || '';
    document.getElementById('viewerCounter').textContent = (activeFileIndex + 1) + ' / ' + galleryFiles.length;

    const content = getViewerContentEl();
    content.innerHTML = '<div class="a4-stage"><div class="a4-page" style="min-height:260px; display:flex; align-items:center; justify-content:center; color:#666;">چاوەڕوانبە...</div></div>';
    revokeActiveObjectUrl();
    currentDocxHtml = '';
    resetSearchState();
    document.getElementById('viewerSearchInput').value = '';

    try {
        if (fileType === 'image' || fileType === 'pdf') {
            const buffer = await fetchFileBlob(item);
            const mime = fileType === 'image' ? 'image/*' : 'application/pdf';
            const blob = new Blob([buffer], { type: mime });
            activeObjectUrl = URL.createObjectURL(blob);

            if (fileType === 'image') {
                content.innerHTML = '<img src="' + activeObjectUrl + '" alt="" style="display:block; width:100%; max-height:62vh; object-fit:contain; border:1px solid #eee; border-radius:8px; background:#f7f7f7;">';
            } else {
                content.innerHTML = '<iframe src="' + activeObjectUrl + '" style="width:100%; height:62vh; border:1px solid #ddd; border-radius:8px;" loading="lazy"></iframe>';
            }
        } else if (fileType === 'docx') {
            // Use server-side PHP conversion (avoids mammoth.js 'Not implemented' errors)
            const encodedPath = encodeURIComponent(toBase64Utf8(item.path || ''));
            const convResponse = await fetch('stream_docx_html.php?f=' + encodedPath);
            if (!convResponse.ok) {
                throw new Error('stream_failed');
            }
            const convPayload = await convResponse.json();
            console.log('[DEBUG docx convert]', convPayload.error || 'OK');
            if (convPayload.error) {
                throw new Error('stream_error:' + convPayload.error);
            }
            currentDocxHtml = convPayload.html || '<p>هیچ ناوەڕۆکێک نەدۆزرایەوە.</p>';
            renderDocxHtml(currentDocxHtml);
        } else {
            content.innerHTML = '<div style="padding:20px; border:1px dashed #ccc; border-radius:8px; text-align:center; color:#555;">'
                + '<p style="margin-bottom:10px;">ئەم جۆرە فایلە لە مۆداڵەکەدا پشتگیری ناکرێت.</p>'
                + '<button type="button" class="lib-btn lib-btn-gold" onclick="openLocalFile(' + item.id + ')">کردنەوە بە سیستەم</button>'
                + '</div>';
        }
    } catch (e) {
        console.error('File Viewer Error:', e);
        let errorMsg = 'نەتوانرا ناوەڕۆکی فایل پیشان بدرێت.';
        
        if (e.message && e.message.includes('conversion_failed:complex_docx')) {
            errorMsg = 'ئەم نوسراوە زانیاری تێدایە (وەک خشتەی ئاڵۆز یان وێنە) کە سیستەمەکە ناتوانێت ڕاستەوخۆ لێرەدا پیشانی بدات.';
        } else if (e.message && e.message.includes('stream_error:')) {
            errorMsg += '<br><small style="color:#d66;">هەڵەی سێرڤەر: ' + e.message.replace('stream_error:', '') + '</small>';
        } else if (e.message === 'stream_failed') {
            errorMsg += '<br><small style="color:#d66;">پەیوەندی لەگەڵ سێرڤەر سەرکەوتوو نەبوو.</small>';
        }

        content.innerHTML = '<div style="padding:20px; border:1px dashed #d66; border-radius:8px; text-align:center; color:#a33;">'
            + '<p style="margin-bottom:10px;">' + errorMsg + '</p>'
            + '<button type="button" class="lib-btn lib-btn-gold" onclick="openLocalFile(' + item.id + ')">کردنەوە بە سیستم (Word)</button>'
            + '</div>';
    }

    document.getElementById('viewerPrevBtn').disabled = activeFileIndex === 0;
    document.getElementById('viewerNextBtn').disabled = activeFileIndex === galleryFiles.length - 1;

    // Related & Responses Logic
    const relSection = document.getElementById('relatedDocSection');
    const relLink = document.getElementById('relatedDocLink');
    const respSection = document.getElementById('responsesSection');
    const respList = document.getElementById('responsesList');
    
    // Parent Link (The doc this one points to)
    if (item.rel_id) {
        relSection.style.display = 'block';
        relLink.textContent = item.rel_text || ('نوسراوی ژمارە ' + item.rel_id);
        relLink.onclick = function() {
            const idx = galleryFiles.findIndex(f => f.id == item.rel_id);
            if (idx >= 0) renderFileInViewer(idx);
            else alert('ئەم نوسراوە لەم لاپەڕەیەدا نییە، تکایە لە سێرچ بۆی بگەڕێ.');
        };
    } else {
        relSection.style.display = 'none';
    }

    // Responses (Other docs pointing to this one)
    const allResponses = galleryFiles.filter(f => f.rel_id == item.id);
    // Filter to ensure unique IDs
    const responses = Array.from(new Map(allResponses.map(r => [r.id, r])).values());
    
    if (responses.length > 0) {
        respSection.style.display = 'block';
        respList.innerHTML = '';
        responses.forEach(r => {
            const div = document.createElement('div');
            div.style = 'font-size:12px; color:#2d3748; background:#f0fff4; padding:8px; border-radius:6px; cursor:pointer; border:1px solid #c6f6d5; margin-bottom:5px;';
            div.innerHTML = `<span style="font-weight:bold; color:#2e7d32;">↩ وەڵام:</span> ژمارە ${r.doc_number || r.number || 'N/A'} - ${r.subject}`;
            div.onclick = () => {
                const idx = galleryFiles.findIndex(f => f.id == r.id);
                if (idx !== -1) renderFileInViewer(idx);
            };
            respList.appendChild(div);
        });
    } else {
        respSection.style.display = 'none';
    }
}

function openLocalFile(id) {
    fetch('docs.php?open_file=' + id)
        .then(response => response.text())
        .then(data => console.log('Open request sent: ' + data));
}

function openFileViewer(index) {
    if (!galleryFiles.length) return;
    document.getElementById('fileViewerModal').style.display = 'flex';
    renderFileInViewer(index);
}

function closeFileViewer() {
    document.getElementById('fileViewerModal').style.display = 'none';
    revokeActiveObjectUrl();
}

async function renderFileInViewer(index) {
    if (!galleryFiles.length) return;
    activeFileIndex = Math.max(0, Math.min(index, galleryFiles.length - 1));
    const item = galleryFiles[activeFileIndex];
    const fileType = detectFileType(item.path || item.name || '');

    document.getElementById('viewerTitle').textContent = item.subject || 'پیشاندانی فایل';
    document.getElementById('viewerSubTitle').textContent = item.name || '';
    document.getElementById('viewerCounter').textContent = (activeFileIndex + 1) + ' / ' + galleryFiles.length;

    const content = getViewerContentEl();
    content.innerHTML = '<div class="a4-page" style="min-height:260px; display:flex; align-items:center; justify-content:center; color:#64748b; font-weight:600;">چاوەڕوانبە...</div>';
    revokeActiveObjectUrl();
    currentDocxHtml = '';
    resetSearchState();
    document.getElementById('viewerSearchInput').value = '';

    try {
        if (fileType === 'image' || fileType === 'pdf') {
            const buffer = await fetchFileBlob(item);
            const mime = fileType === 'image' ? 'image/*' : 'application/pdf';
            const blob = new Blob([buffer], { type: mime });
            activeObjectUrl = URL.createObjectURL(blob);

            if (fileType === 'image') {
                content.innerHTML = '<img src="' + activeObjectUrl + '" alt="" style="display:block; width:100%; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.1);">';
            } else {
                content.innerHTML = '<iframe src="' + activeObjectUrl + '" style="width:100%; height:80vh; border:none; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.1);" loading="lazy"></iframe>';
            }
        } else if (fileType === 'docx') {
            const encodedPath = encodeURIComponent(toBase64Utf8(item.path || ''));
            const convResponse = await fetch('stream_docx_html.php?f=' + encodedPath);
            if (!convResponse.ok) throw new Error('stream_failed');
            
            const convPayload = await convResponse.json();
            if (convPayload.error) throw new Error('stream_error:' + convPayload.error);
            
            currentDocxHtml = convPayload.html || '<p>هیچ ناوەڕۆکێک نەدۆزرایەوە.</p>';
            renderDocxHtml(currentDocxHtml);
        } else {
            content.innerHTML = '<div style="padding:40px; border:2px dashed #e2e8f0; border-radius:20px; text-align:center; color:#64748b;">'
                + '<p style="margin-bottom:20px; font-weight:600;">ئەم جۆرە فایلە لە مۆداڵەکەدا پشتگیری ناکرێت.</p>'
                + '<button type="button" class="viewer-sidebar-btn btn-nav-gold" style="margin:0 auto;" onclick="openLocalFile(' + item.id + ')">کردنەوە بە سیستم</button>'
                + '</div>';
        }
    } catch (e) {
        console.error('File Viewer Error:', e);
        let errorMsg = 'نەتوانرا ناوەڕۆکی فایل پیشان بدرێت.';
        if (e.message && e.message.includes('stream_error:')) errorMsg += '<br><small style="color:#ef4444;">هەڵە: ' + e.message.replace('stream_error:', '') + '</small>';

        content.innerHTML = '<div style="padding:40px; border:2px dashed #fecaca; border-radius:24px; text-align:center; color:#991b1b; background:#fef2f2;">'
            + '<div style="font-size:1.1rem; font-weight:800; margin-bottom:15px;">' + errorMsg + '</div>'
            + '<button type="button" class="viewer-sidebar-btn btn-nav-gold" style="margin:0 auto;" onclick="openLocalFile(' + item.id + ')">کردنەوە بە سیستم (Word)</button>'
            + '</div>';
    }

    document.getElementById('viewerPrevBtn').style.opacity = activeFileIndex === 0 ? '0.5' : '1';
    document.getElementById('viewerPrevBtn').style.pointerEvents = activeFileIndex === 0 ? 'none' : 'auto';
    document.getElementById('viewerNextBtn').style.opacity = activeFileIndex === galleryFiles.length - 1 ? '0.5' : '1';
    document.getElementById('viewerNextBtn').style.pointerEvents = activeFileIndex === galleryFiles.length - 1 ? 'none' : 'auto';

    // Related & Responses Logic
    const relSection = document.getElementById('relatedDocSection');
    const relLink = document.getElementById('relatedDocLink');
    const respSection = document.getElementById('responsesSection');
    const respList = document.getElementById('responsesList');
    
    if (item.rel_id) {
        relSection.style.display = 'block';
        relLink.textContent = item.rel_text || ('نوسراوی ژمارە ' + item.rel_id);
        relLink.onclick = () => {
            const idx = galleryFiles.findIndex(f => f.id == item.rel_id);
            if (idx >= 0) renderFileInViewer(idx);
            else showPageToast('ئەم نوسراوە لەم لیستەدا نییە', 'info');
        };
    } else relSection.style.display = 'none';

    const responses = Array.from(new Map(galleryFiles.filter(f => f.rel_id == item.id).map(r => [r.id, r])).values());
    if (responses.length > 0) {
        respSection.style.display = 'block';
        respList.innerHTML = '';
        responses.forEach(r => {
            const div = document.createElement('div');
            div.className = 'viewer-sidebar-btn btn-nav-secondary';
            div.style = 'font-size:0.8rem; padding:10px; justify-content:flex-start; text-align:right;';
            div.innerHTML = `<span style="font-weight:800; color:#10b981;">↩ وەڵام:</span> ${r.doc_number || r.id}`;
            div.onclick = () => {
                const idx = galleryFiles.findIndex(f => f.id == r.id);
                if (idx !== -1) renderFileInViewer(idx);
            };
            respList.appendChild(div);
        });
    } else respSection.style.display = 'none';
}

document.getElementById('viewerPrevBtn').addEventListener('click', () => { if (activeFileIndex > 0) renderFileInViewer(activeFileIndex - 1); });
document.getElementById('viewerNextBtn').addEventListener('click', () => { if (activeFileIndex < galleryFiles.length - 1) renderFileInViewer(activeFileIndex + 1); });
document.getElementById('viewerOpenSystemBtn').addEventListener('click', () => { if (galleryFiles.length) openLocalFile(galleryFiles[activeFileIndex].id); });
document.getElementById('viewerSearchBtn').addEventListener('click', runDocxSearch);
document.getElementById('viewerSearchPrevBtn').addEventListener('click', () => focusMatch(currentSearchIndex - 1));
document.getElementById('viewerSearchNextBtn').addEventListener('click', () => focusMatch(currentSearchIndex + 1));
document.getElementById('viewerSearchInput').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); runDocxSearch(); } });
</script>

<?php require_once 'includes/layout_end.php'; ?>
