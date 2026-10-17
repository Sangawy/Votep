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
    header('Location: archive_docs.php?msg=deleted');
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
        doc_number LIKE '%$term%'
        OR subject LIKE '%$term%'
        OR doc_type LIKE '%$term%'
        OR correspondent LIKE '%$term%'
        OR doc_date LIKE '%$term%'
        OR file_path LIKE '%$term%'
        OR id LIKE '%$term%'
    )";
}
if ($type_filter) {
    $term = $conn->real_escape_string($type_filter);
    $whereClause .= " AND doc_type = '$term'";
}
if ($month_filter >= 1 && $month_filter <= 12) {
    $whereClause .= " AND MONTH(doc_date) = $month_filter";
}
if ($year_filter >= 1900 && $year_filter <= 2100) {
    $whereClause .= " AND YEAR(doc_date) = $year_filter";
}
if ($date_from !== '') {
    $df = $conn->real_escape_string($date_from);
    $whereClause .= " AND doc_date >= '$df'";
}
if ($date_to !== '') {
    $dt = $conn->real_escape_string($date_to);
    $whereClause .= " AND doc_date <= '$dt'";
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$total_rows = $conn->query("SELECT COUNT(*) FROM archive_docs $whereClause")->fetch_row()[0];
$total_pages = ceil($total_rows / $limit);

$docs = $conn->query("SELECT * FROM archive_docs $whereClause ORDER BY id DESC LIMIT $offset, $limit");
$gallery_files = [];

$sys_module = 'docs';
$page_title = 'نوسراوەکان';
$active_nav = 'archive_docs';

require_once 'includes/layout.php';
?>

<style>
@font-face {
    font-family: 'Rabar022';
    src: url('assets/font/Rabar_022.ttf') format('truetype');
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
.docs-filter-item .lib-btn-danger { background-color: #f56565; border-color: #f56565; color: white; }
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
                <a href="archive_docs.php" class="lib-btn lib-btn-danger">
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
                            'path' => (string)$d['file_path'],
                            'name' => basename($normalizedPath),
                            'subject' => (string)$d['subject'],
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
                        <td style="font-family: monospace; font-size: 0.75rem; color: #888;">
                            <?php echo htmlspecialchars($d['file_path']); ?>
                        </td>
                        <td>
                            <div class="lib-actions" style="flex-direction: row; gap: 5px;">
                                <button type="button" onclick="openFileViewer(<?php echo $rowIndex; ?>)" class="lib-btn lib-btn-primary" style="padding: 6px 10px;" title="پیشاندانی فایل">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                    ڤیو (View)
                                </button>
                                <button type="button" onclick="fetch('archive_docs.php?open_local=<?php echo $d['id']; ?>');" class="lib-btn lib-btn-gold" style="padding: 6px 10px;" title="کردنەوە لە ناو سیستەم">
                                    وۆرد (Word)
                                </button>


                                <a href="archive_docs.php?delete=<?php echo $d['id']; ?>" class="lib-btn lib-btn-danger" style="padding: 6px;" onclick="return confirm('ئایا دڵنیایت؟')">
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

<div id="fileViewerOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:1050;"></div>
<div id="fileViewerModal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:min(1000px, 95vw); max-height:90vh; background:#fff; border-radius:12px; overflow:hidden; z-index:1060; box-shadow:0 18px 50px rgba(0,0,0,0.3);">
    <div style="display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background:#003a4a; color:#fff;">
        <div>
            <strong id="viewerTitle">پیشاندانی فایل</strong>
            <div id="viewerSubTitle" style="font-size:12px; opacity:0.9;"></div>
        </div>
        <button type="button" onclick="closeFileViewer()" style="border:none; background:transparent; color:#fff; font-size:28px; line-height:1; cursor:pointer;">&times;</button>
    </div>
    <div id="fileViewerBody" style="min-height:300px; max-height:calc(90vh - 120px); overflow:hidden;">
        <aside id="fileViewerPanel">
            <div id="viewerCounter" style="font-size:13px; color:#555; text-align:center; margin-bottom:10px;"></div>
            <button type="button" id="viewerPrevBtn" class="lib-btn lib-btn-primary" style="padding:7px 12px; width:100%;">قبڵە</button>
            <button type="button" id="viewerNextBtn" class="lib-btn lib-btn-primary" style="padding:7px 12px; width:100%;">دواتر</button>
            <button type="button" id="viewerOpenSystemBtn" class="lib-btn lib-btn-gold" style="padding:7px 12px; width:100%;">کردنەوە بە سیستەم</button>
            <hr style="border:none; border-top:1px solid #e3e6ea; margin:12px 0;">
            <label for="viewerSearchInput" style="font-size:12px; color:#666;">گەڕان لە ناو دەق</label>
            <input id="viewerSearchInput" type="text" class="lib-field" placeholder="وشە بنوسە..." style="width:100%; margin:6px 0 8px;">
            <button type="button" id="viewerSearchBtn" class="lib-btn lib-btn-primary" style="padding:7px 12px; width:100%;">گەڕان</button>
            <div style="display:flex; gap:6px;">
                <button type="button" id="viewerSearchPrevBtn" class="lib-btn lib-btn-primary" style="padding:6px 10px; flex:1;">↑</button>
                <button type="button" id="viewerSearchNextBtn" class="lib-btn lib-btn-primary" style="padding:6px 10px; flex:1;">↓</button>
            </div>
            <div id="viewerSearchCount" style="margin-top:8px; font-size:12px; color:#666; text-align:center;">-</div>
        </aside>
        <section id="fileViewerMain">
            <div id="fileViewerContent"></div>
        </section>
    </div>
</div>

<style>
#pageToast {
    position: fixed;
    top: 22px;
    left: 50%;
    transform: translateX(-50%);
    min-width: 300px;
    max-width: 86vw;
    padding: 12px 16px;
    border-radius: 10px;
    color: #fff;
    font-weight: 700;
    z-index: 2000;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
}
#pageToast.show { opacity: 1; }
#pageToast.success { background: #1f8f54; }
#pageToast.info { background: #0a5c7a; }
#fileViewerBody { background: #eef1f5; display: flex; flex-direction: row-reverse; }
#fileViewerPanel { width: 230px; background: #fff; border-left: 1px solid #dfe3e8; padding: 12px; display: flex; flex-direction: column; gap: 8px; overflow-y: auto; }
#fileViewerMain { flex: 1; overflow: auto; padding: 14px; }
#fileViewerContent { min-height: 100%; }
.a4-stage { display: flex; justify-content: center; padding: 18px 0; }
.a4-page {
    width: 100%;
    min-height: calc(90vh - 220px);
    max-width: 100%;
    background: #fff;
    border: 1px solid #d4d9de;
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.16);
    border-radius: 4px;
    padding: 70px 58px;
    color: #1f2328;
    font-family: Tahoma, Arial, sans-serif;
    font-size: 16px;
    line-height: 1.95;
    direction: rtl;
    text-align: right;
    unicode-bidi: plaintext;
    word-break: break-word;
}
.a4-page, .a4-page * { direction: rtl; text-align: right; unicode-bidi: plaintext; }
.a4-page p { margin: 0 0 10px; }
.a4-page h1, .a4-page h2, .a4-page h3, .a4-page h4 { margin: 0 0 14px; line-height: 1.5; }
.a4-page table { width: 100%; border-collapse: collapse; margin: 10px 0; }
.a4-page td, .a4-page th { border: 1px solid #d8d8d8; padding: 8px; vertical-align: top; }
.a4-page img { max-width: 100%; height: auto; }
.viewer-hit { background: #ffec99; border-radius: 3px; padding: 0 2px; }
.viewer-hit.active { background: #ffc107; }
@media (max-width: 860px) {
    #fileViewerBody { flex-direction: column; }
    #fileViewerPanel { width: 100%; border-left: none; border-bottom: 1px solid #dfe3e8; }
    .a4-page { padding: 28px 18px; min-height: auto; }
}
</style>

<div id="pageToast"></div>

<script src="https://cdn.jsdelivr.net/npm/mammoth@1.8.0/mammoth.browser.min.js"></script>
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

    fetch('archive_docs.php?open_file=' + id)
        .then(response => response.text())
        .then(txt => {
            console.log('Debug Response:', txt);
            alert('ئەنجامی کردنەوە: ' + txt);
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
    return btoa(unescape(encodeURIComponent(text)));
}

async function fetchFileBlob(item) {
    const encodedPath = encodeURIComponent(toBase64Utf8(item.path || ''));
    const response = await fetch('stream_doc.php?f=' + encodedPath);
    if (!response.ok) {
        throw new Error('stream_failed');
    }
    const payload = await response.json();
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
            const buffer = await fetchFileBlob(item);
            if (typeof mammoth === 'undefined') {
                throw new Error('mammoth_missing');
            }
            const result = await mammoth.convertToHtml({ arrayBuffer: buffer });
            const cleanHtml = (result.value || '').trim();
            currentDocxHtml = cleanHtml || '<p>هیچ ناوەڕۆکێک نەدۆزرایەوە.</p>';
            renderDocxHtml(currentDocxHtml);
        } else {
            content.innerHTML = '<div style="padding:20px; border:1px dashed #ccc; border-radius:8px; text-align:center; color:#555;">'
                + '<p style="margin-bottom:10px;">ئەم جۆرە فایلە لە مۆداڵەکەدا پشتگیری ناکرێت.</p>'
                + '<button type="button" class="lib-btn lib-btn-gold" onclick="openLocalFile(' + item.id + ')">کردنەوە بە سیستەم</button>'
                + '</div>';
        }
    } catch (e) {
        content.innerHTML = '<div style="padding:20px; border:1px dashed #d66; border-radius:8px; text-align:center; color:#a33;">'
            + '<p style="margin-bottom:10px;">نەتوانرا ناوەڕۆکی فایل پیشان بدرێت.</p>'
            + '<button type="button" class="lib-btn lib-btn-gold" onclick="openLocalFile(' + item.id + ')">هەوڵی کردنەوە بە سیستەم</button>'
            + '</div>';
    }

    document.getElementById('viewerPrevBtn').disabled = activeFileIndex === 0;
    document.getElementById('viewerNextBtn').disabled = activeFileIndex === galleryFiles.length - 1;
}

function openFileViewer(index) {
    if (!galleryFiles.length) return;
    document.getElementById('fileViewerOverlay').style.display = 'block';
    document.getElementById('fileViewerModal').style.display = 'block';
    renderFileInViewer(index);
}

function closeFileViewer() {
    document.getElementById('fileViewerOverlay').style.display = 'none';
    document.getElementById('fileViewerModal').style.display = 'none';
    revokeActiveObjectUrl();
}

document.getElementById('viewerPrevBtn').addEventListener('click', function() {
    if (activeFileIndex > 0) renderFileInViewer(activeFileIndex - 1);
});

document.getElementById('viewerNextBtn').addEventListener('click', function() {
    if (activeFileIndex < galleryFiles.length - 1) renderFileInViewer(activeFileIndex + 1);
});

document.getElementById('viewerOpenSystemBtn').addEventListener('click', function() {
    if (!galleryFiles.length) return;
    openLocalFile(galleryFiles[activeFileIndex].id);
});

document.getElementById('viewerSearchBtn').addEventListener('click', runDocxSearch);
document.getElementById('viewerSearchPrevBtn').addEventListener('click', function() { focusMatch(currentSearchIndex - 1); });
document.getElementById('viewerSearchNextBtn').addEventListener('click', function() { focusMatch(currentSearchIndex + 1); });
document.getElementById('viewerSearchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        runDocxSearch();
    }
});

document.getElementById('fileViewerOverlay').addEventListener('click', closeFileViewer);

</script>

<?php require_once 'includes/layout_end.php'; ?>
