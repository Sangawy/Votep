<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

require_once 'config.php';
$conn = getDB();

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS publications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    issue_number VARCHAR(100) NOT NULL,
    publish_year INT NOT NULL,
    total_quantity INT NOT NULL DEFAULT 0,
    remaining_quantity INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS publication_distributions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    publication_id INT NOT NULL,
    location VARCHAR(255) NOT NULL,
    distributed_quantity INT NOT NULL,
    receiver_name VARCHAR(255) NOT NULL,
    receiver_phone VARCHAR(50),
    distributed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Ensure 'user_id' column exists in distributions
$check_user_col = $conn->query("SHOW COLUMNS FROM publication_distributions LIKE 'user_id'");
if ($check_user_col->num_rows == 0) {
    $conn->query("ALTER TABLE publication_distributions ADD COLUMN user_id INT DEFAULT NULL");
}
$check_col = $conn->query("SHOW COLUMNS FROM publications LIKE 'type'");
if ($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE publications ADD COLUMN type VARCHAR(100) DEFAULT NULL");
}

// Fix existing rows (one-time logic)
$conn->query("UPDATE publications SET type = 'گۆڤاری پێشمەرگە' WHERE type IS NULL AND name LIKE '%گۆڤاری پێشمەرگە%'");
$conn->query("UPDATE publications SET type = 'بڵاوکراوەی رەوشەن' WHERE type IS NULL AND name LIKE '%رەوشەن%'");

$page_title = 'بڵاوکراوەکان';
$active_nav = 'publications';
require_once 'includes/layout.php';
?>

<style>
    .page-hdr { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
    .page-hdr h2 { font-size:1.4rem; color:#5a0202; font-weight:700; }

    .filters-card { background:#fff; border-radius:14px; padding:20px; box-shadow:0 4px 16px rgba(0,0,0,0.07); margin-bottom:22px; border:1px solid #eaeaea; }
    .search-row { display:flex; gap:12px; align-items:flex-end; }
    .search-row .lib-field { flex:1; margin-bottom:0; }

    .pub-card { background:#fff; border-radius:14px; box-shadow:0 4px 16px rgba(0,0,0,0.07); border:1px solid #eaeaea; width:100%; overflow:hidden; }
    
    .b-num { background:#f0f0f0; color:#555; padding:3px 9px; border-radius:6px; font-size:.82rem; font-weight:700; }
    .b-qty { background:#e8f5e9; color:#2e7d32; padding:3px 10px; border-radius:14px; font-weight:700; }
    .b-rem { background:#fff3e0; color:#e65100; padding:3px 10px; border-radius:14px; font-weight:700; }

    .action-col { min-width:160px; padding:10px 12px !important; }
    .act-btn { width:100%; padding:7px 10px; border-radius:8px; font-weight:700; font-size:.82rem; font-family:inherit; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:5px; transition:all .18s; margin-bottom:5px; border:1px solid transparent; }
    
    .act-dist { background:rgba(200,169,81,.12); color:#8e7323; border-color:rgba(200,169,81,.3); }
    .act-dist:hover { background:#C8A951; color:#fff; }
    
    .act-history { background:rgba(46,125,50,.08); color:#2e7d32; border-color:rgba(46,125,50,.25); }
    .act-history:hover { background:#2e7d32; color:#fff; }

    .act-row2 { display:flex; gap:5px; }
    .act-edit { flex:1; background:rgba(0,0,0,.05); color:#555; border-color:#ddd; }
    .act-edit:hover { background:#555; color:#fff; }
    .act-del { flex:1; background:rgba(211,47,47,.08); color:#d32f2f; border-color:rgba(211,47,47,.2); }
    .act-del:hover { background:#d32f2f; color:#fff; }

    .modal-form { display:grid; grid-template-columns:repeat(2,1fr); gap:15px; }
    .modal-form .full { grid-column:span 2; }

    /* Tabs Styling */
    .lib-tabs { display:flex; gap:10px; margin-bottom:20px; border-bottom:2px solid #eaeaea; padding-bottom:10px; }
    .lib-tab-btn { padding:10px 20px; border-radius:10px; border:none; background:#fff; color:#555; font-weight:700; cursor:pointer; font-family:inherit; transition:.2s; border:1px solid #ddd; }
    .lib-tab-btn.active { background:#5a0202; color:#fff; border-color:#5a0202; box-shadow:0 4px 12px rgba(90,2,2,0.2); }
    .tab-content { display:none; }
    .tab-content.active { display:block; }

    /* Report Styling */
    .report-paper { background:#fff; padding:40px; max-width:210mm; margin:10px auto; min-height:297mm; border:1px solid #ddd; font-family:'Inter', 'Roboto', sans-serif; position:relative; display:flex; flex-direction:column; }
    .report-header { display:flex; justify-content:space-between; align-items:flex-end; border-bottom:1px solid #000; padding-bottom:10px; margin-bottom:20px; }
    .report-title { font-size:1.1rem; font-weight:800; color:#000; margin:0; }
    .report-subtitle { font-size:1rem; font-weight:700; color:#000; margin:0; }
    
    .report-summary { display:grid; grid-template-columns:repeat(3,1fr); gap:5px; margin-bottom:15px; padding:0; }
    .sum-box { text-align:right; padding:5px; }
    .sum-box .label { display:inline-block; font-size:.8rem; color:#555; }
    .sum-box .val { display:inline-block; font-size:.85rem; font-weight:800; color:#000; margin-right:5px; }

    .report-table { width:100%; border-collapse:collapse; margin-top:5px; }
    .report-table th, .report-table td { border:1px solid #ccc; padding:5px 8px; text-align:right; font-size:.8rem; }
    .report-table th { background:#f2f2f2; color:#000; font-weight:700; }
    .report-table tr { height:32px; }

    .report-footer { position:absolute; bottom:50px; left:40px; right:40px; display:flex; justify-content:space-between; }
    .sig-box { text-align:center; width:180px; }
    .sig-line { margin-top:35px; border-top:1px solid #000; padding-top:5px; font-size:.8rem; font-weight:700; }

    @media print {
        @page { size: A4; margin: 0; }
        html, body { margin: 0; padding: 0; background: #fff !important; }
        body * { visibility: hidden; }
        .report-paper, .report-paper * { visibility: visible; }
        .report-paper { 
            position: absolute; 
            left: 0; 
            top: 0; 
            width: 210mm; 
            height: 296.5mm; /* Slightly less than 297 to avoid blank page */
            box-shadow:none; 
            border:none; 
            padding:15mm; 
            box-sizing: border-box;
            overflow: hidden;
        }
        .no-print { display:none !important; }
    }
</style>

<div class="page-hdr">
    <h2>بڵاوکراوەکان</h2>
    <button class="lib-btn lib-btn-primary" onclick="openPubModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        زیادکردنی بڵاوکراوە
    </button>
</div>

<div class="lib-tabs">
    <button class="lib-tab-btn active" onclick="switchTab('listTab')">لیستی بڵاوکراوەکان</button>
    <button class="lib-tab-btn" onclick="switchTab('historyTab')">مێژووی دابەشکردن</button>
    <button class="lib-tab-btn" onclick="switchTab('reportTab')">ڕاپۆرتەکان</button>
</div>

<div id="listTab" class="tab-content active">
    <div class="filters-card">
        <div class="search-row">
            <div class="lib-field" style="flex: 2;">
                <label for="pub_search">گەڕان</label>
                <input id="pub_search" type="text" placeholder="ناوی بڵاوکراوە یان ژمارە بنووسە...">
            </div>
            <div class="lib-field" style="flex: 1;">
                <label for="pub_type_filter">جۆری بڵاوکراوە</label>
                <select id="pub_type_filter">
                    <option value="">هەموو</option>
                    <option value="گۆڤاری پێشمەرگە">گۆڤاری پێشمەرگە</option>
                    <option value="بڵاوکراوەی رەوشەن">بڵاوکراوەی رەوشەن</option>
                </select>
            </div>
        </div>
    </div>

    <div class="pub-card">
        <div class="lib-table-wrapper">
            <table class="lib-table">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>ناوی بڵاوکراوە</th>
                        <th>ژمارە</th>
                        <th>ساڵی چاپ</th>
                        <th>بڕی چاپکراو</th>
                        <th>بڕی ماوە</th>
                        <th style="width:180px;">کردارەکان</th>
                    </tr>
                </thead>
                <tbody id="pubBody">
                    <tr><td colspan="7" style="text-align:center;padding:40px;color:#999;">چاوەڕوانبکە...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="lib-pagination" id="pubPagination"></div>
    </div>
</div>

<div id="historyTab" class="tab-content">
    <div class="filters-card">
        <div class="search-row">
            <div class="lib-field">
                <label for="hist_search">گەڕان لە مێژوو</label>
                <input id="hist_search" type="text" placeholder="بۆ کوێ دابەشکراوە؟ یان ناوی بڵاوکراوە...">
            </div>
            <button class="lib-btn" style="background:#eee;color:#555;" onclick="loadAllHistory()">پاککردنەوەی فلتەر</button>
        </div>
    </div>
    <div class="pub-card">
        <div class="lib-table-wrapper">
            <table class="lib-table">
                <thead>
                    <tr>
                        <th>بڵاوکراوە</th>
                        <th>ژمارە</th>
                        <th>شوێن</th>
                        <th>دانە</th>
                        <th>وەرگر</th>
                        <th>بەروار</th>
                        <th style="width:100px;">کردار</th>
                    </tr>
                </thead>
                <tbody id="histTableBody">
                    <tr><td colspan="7" style="text-align:center;padding:40px;color:#999;">چاوەڕوانبکە...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="reportTab" class="tab-content">
    <div class="filters-card no-print">
        <div class="search-row">
            <div class="lib-field" style="flex: 2;">
                <label>هەڵبژاردنی بڵاوکراوە</label>
                <select id="report_pub_id">
                    <option value="">هەڵبژێرە...</option>
                    <!-- Options loaded via JS -->
                </select>
            </div>
            <button class="lib-btn lib-btn-primary" onclick="generateReport()">پیشاندانی ڕاپۆرت</button>
            <button class="lib-btn" style="background:#555;color:#fff;" onclick="window.print()">پرینت</button>
        </div>
    </div>

    <div id="reportContentArea">
        <p style="text-align:center;padding:100px;color:#999;">تکایە بڵاوکراوەیەک هەڵبژێرە بۆ بینینی ڕاپۆرت</p>
    </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="lib-overlay" id="pubOverlay" onclick="closePubModal()"></div>
<div class="lib-modal" id="pubModal" style="width:min(500px,calc(100% - 30px));">
    <div class="lib-modal-header">
        <h3 id="pubModalTitle">زیادکردنی بڵاوکراوە</h3>
        <button class="lib-modal-close" onclick="closePubModal()">&times;</button>
    </div>
    <div class="lib-modal-body">
        <form id="pubForm">
            <input type="hidden" id="pubId">
            <div class="modal-form">
                <div class="lib-field full">
                    <label>جۆری بڵاوکراوە <span style="color:red">*</span></label>
                    <select id="p_type" required>
                        <option value="">هەڵبژێرە...</option>
                        <option value="گۆڤاری پێشمەرگە">گۆڤاری پێشمەرگە</option>
                        <option value="بڵاوکراوەی رەوشەن">بڵاوکراوەی رەوشەن</option>
                    </select>
                </div>
                <div class="lib-field">
                    <label>ناوی بڵاوکراوە <span style="color:red">*</span></label>
                    <input type="text" id="p_name" required>
                </div>
                <div class="lib-field">
                    <label>ژمارە <span style="color:red">*</span></label>
                    <input type="text" id="p_issue" required>
                </div>
                <div class="lib-field">
                    <label>ساڵی چاپ</label>
                    <input type="number" id="p_year" value="<?php echo date('Y'); ?>">
                </div>
                <div class="lib-field full">
                    <label>بڕی چاپکراو</label>
                    <input type="number" id="p_qty" value="0">
                </div>
            </div>
        </form>
    </div>
    <div class="lib-modal-footer">
        <button class="lib-btn lib-btn-primary" onclick="savePub()">پاشەکەوتکردن</button>
        <button class="lib-btn" onclick="closePubModal()">داخستن</button>
    </div>
</div>

<!-- DISTRIBUTE MODAL -->
<div class="lib-overlay" id="distOverlay" onclick="closeDistModal()"></div>
<div class="lib-modal" id="distModal" style="width:min(500px,calc(100% - 30px));">
    <div class="lib-modal-header">
        <h3>دابەشکردنی بڵاوکراوە</h3>
        <button class="lib-modal-close" onclick="closeDistModal()">&times;</button>
    </div>
    <div class="lib-modal-body">
        <p id="distPubInfo" style="margin-bottom:15px;font-weight:700;color:#5a0202;"></p>
        <form id="distForm">
            <input type="hidden" id="distPubId">
            <input type="hidden" id="edit_dist_id">
            <div class="modal-form">
                <div class="lib-field full">
                    <label>شوێن <span style="color:red">*</span></label>
                    <input type="text" id="d_location" list="locationList" required oninput="checkLocation()" onchange="checkLocation()">
                    <datalist id="locationList"></datalist>
                </div>
                <div class="lib-field">
                    <label>دانە <span style="color:red">*</span></label>
                    <input type="number" id="d_qty" value="1" min="1" required>
                </div>
                <div class="lib-field full">
                    <label>کەسی وەرگر <span style="color:red">*</span></label>
                    <input type="text" id="d_receiver" list="receiverList" required oninput="checkReceiver()" onchange="checkReceiver()">
                    <datalist id="receiverList"></datalist>
                    <datalist id="staffList"></datalist>
                </div>
                <div class="lib-field full">
                    <label>ژمارەی مۆبایل</label>
                    <input type="text" id="d_phone">
                </div>
            </div>
        </form>
    </div>
    <div class="lib-modal-footer">
        <button class="lib-btn lib-btn-gold" id="distSubmitBtn" onclick="saveDist()">دابەشکردن</button>
        <button class="lib-btn" onclick="closeDistModal()">داخستن</button>
    </div>
</div>

<!-- DETAIL MODAL -->
<div class="lib-overlay" id="detailOverlay" onclick="closeDetailModal()"></div>
<div class="lib-modal" id="detailModal" style="width:min(500px,calc(100% - 30px));">
    <div class="lib-modal-header">
        <h3>زانیاری دابەشکردن</h3>
        <button class="lib-modal-close" onclick="closeDetailModal()">&times;</button>
    </div>
    <div class="lib-modal-body" id="detailContent">
        <!-- Details injected here -->
    </div>
    <div class="lib-modal-footer">
        <button class="lib-btn" onclick="closeDetailModal()">داخستن</button>
    </div>
</div>

<script>
let uniqueLocations = [];
let currentPage = 1;
const userRole = '<?php echo $_SESSION['role'] ?? ''; ?>';
const isAdminOrOwner = ['ئادمین', 'خاوەن', 'admin', 'owner'].includes(userRole.toLowerCase());

function loadPubs(page = 1) {
    currentPage = page;
    const body = document.getElementById('pubBody');
    body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#999;">کەمێک چاوەڕوانبە...</td></tr>';

    const search = document.getElementById('pub_search').value;
    const type   = document.getElementById('pub_type_filter').value;
    fetch(`fetch_data.php?action=filter_publications&search=${search}&type=${encodeURIComponent(type)}&page=${page}`)
        .then(r => r.json())
        .then(data => {
            const pubs = data.publications || [];
            if (!pubs.length) {
                body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#999;">هیچ داتایەک نییە</td></tr>';
                return;
            }
            body.innerHTML = pubs.map((p, i) => `
                <tr>
                    <td><span class="b-num">${(page-1)*30 + i + 1}</span></td>
                    <td style="font-weight:700;">${p.name}</td>
                    <td>${p.issue_number}</td>
                    <td>${p.publish_year}</td>
                    <td><span class="b-qty">${p.total_quantity}</span></td>
                    <td><span class="b-rem">${p.remaining_quantity}</span></td>
                    <td class="action-col">
                        <button class="act-btn act-dist" onclick="openDistModal(${p.id}, '${p.name.replace(/'/g, "\\'")}', '${p.issue_number}', ${p.remaining_quantity})">دابەشکردن</button>
                        <button class="act-btn act-history" onclick="viewHistory(${p.id})">مێژووی دابەشکردن</button>
                        ${isAdminOrOwner ? `
                        <div class="act-row2">
                            <button class="act-btn act-edit" onclick="openPubModal(${p.id})">دەستکاری</button>
                            <button class="act-btn act-del" onclick="deletePub(${p.id})">سڕینەوە</button>
                        </div>
                        ` : ''}
                    </td>
                </tr>
            `).join('');
            renderPagination(data.total_pages, data.page);
        });
}

function renderPagination(totalPages, page) {
    const c = document.getElementById('pubPagination');
    if (totalPages <= 1) { c.innerHTML = ''; return; }
    let h = '';
    for (let i = 1; i <= totalPages; i++) {
        h += `<button class="lib-page-btn ${i===page?'active':''}" onclick="loadPubs(${i})">${i}</button>`;
    }
    c.innerHTML = h;
}

// CRUD
function openPubModal(id = null) {
    document.getElementById('pubForm').reset();
    document.getElementById('pubId').value = '';
    document.getElementById('pubModalTitle').textContent = 'زیادکردنی بڵاوکراوە';
    if (id) {
        document.getElementById('pubModalTitle').textContent = 'دەستکاری بڵاوکراوە';
        fetch(`fetch_data.php?action=get_publication&id=${id}`)
            .then(r => r.json())
            .then(p => {
                document.getElementById('pubId').value = p.id;
                document.getElementById('p_type').value = p.type || '';
                document.getElementById('p_name').value = p.name;
                document.getElementById('p_issue').value = p.issue_number;
                document.getElementById('p_year').value = p.publish_year;
                document.getElementById('p_qty').value = p.total_quantity;
            });
    }
    document.getElementById('pubModal').classList.add('show');
    document.getElementById('pubOverlay').classList.add('show');
}

function closePubModal() {
    document.getElementById('pubModal').classList.remove('show');
    document.getElementById('pubOverlay').classList.remove('show');
}

function savePub() {
    const fd = new FormData(document.getElementById('pubForm'));
    fd.append('action', 'save');
    fd.append('id', document.getElementById('pubId').value);
    fd.append('type', document.getElementById('p_type').value);
    fd.append('name', document.getElementById('p_name').value);
    fd.append('issue_number', document.getElementById('p_issue').value);
    fd.append('publish_year', document.getElementById('p_year').value);
    fd.append('total_quantity', document.getElementById('p_qty').value);

    fetch('save_publication.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closePubModal();
                loadPubs(currentPage);
            } else {
                alert(res.message);
            }
        });
}

function deletePub(id) {
    if (!confirm('ئایا دڵنیایت لە سڕینەوە؟')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    fetch('save_publication.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) loadPubs(currentPage);
        });
}

// DISTRIBUTION
let allStaffNames = [];

function openDistModal(id, name, issue, rem) {
    loadUniqueLocations(); // Refresh data on open
    document.getElementById('distForm').reset();
    document.getElementById('distPubId').value = id;
    document.getElementById('distPubInfo').innerText = `بڵاوکراوە: ${name} (${issue}) (بەردەست: ${rem})`;
    document.getElementById('d_qty').max = rem;
    document.getElementById('distModal').classList.add('show');
    document.getElementById('distOverlay').classList.add('show');
    
    document.getElementById('edit_dist_id').value = '';
    document.getElementById('distSubmitBtn').innerText = 'دابەشکردن';
    document.getElementById('distModal').querySelector('h3').innerText = 'دابەشکردنی بڵاوکراوە';
    
    // Clear receiver list when opening new
    document.getElementById('receiverList').innerHTML = '';
    
    // Formula: Default Qty for specific types
    if (name.includes('گۆڤاری پێشمەرگە')) {
        document.getElementById('d_qty').value = 6;
    }
}

function checkLocation() {
    const locVal = document.getElementById('d_location').value.trim();
    if (!locVal) return;

    const rd = document.getElementById('receiverList');
    
    // Filter receivers for this location (from history)
    const filtered = uniqueLocations.filter(l => l.location === locVal);
    const historyNames = filtered.map(f => f.receiver_name);
    
    // Combine history names with all staff names
    const allNames = [...new Set([...historyNames, ...allStaffNames])];
    rd.innerHTML = allNames.map(n => `<option value="${n}">`).join('');
    
    // Auto-fill with the MOST RECENT receiver for this location if available
    if (filtered.length > 0) {
        const latest = filtered[0]; // uniqueLocations is sorted by distributed_at DESC in fetch
        document.getElementById('d_receiver').value = latest.receiver_name;
        document.getElementById('d_qty').value = latest.distributed_quantity;
        document.getElementById('d_phone').value = latest.receiver_phone || '';
    } else {
        // If it's a new location, maybe suggest a standard quantity (the "Formula")
        // In the screenshot, we saw 6 copies for "Be-rez Wezir"
        const pubInfo = document.getElementById('distPubInfo').innerText;
        if (pubInfo.includes('گۆڤاری پێشمەرگە')) {
            document.getElementById('d_qty').value = 6;
        }
    }
}

function checkReceiver() {
    const locVal = document.getElementById('d_location').value.trim();
    const recVal = document.getElementById('d_receiver').value.trim();
    if (!recVal) return;

    const found = uniqueLocations.find(l => l.location === locVal && l.receiver_name === recVal);
    if (found) {
        document.getElementById('d_qty').value = found.distributed_quantity;
        document.getElementById('d_phone').value = found.receiver_phone || '';
    } else {
        // If new receiver, try to fetch phone from staff database
        fetch(`fetch_data.php?action=get_staff_details&name=${encodeURIComponent(recVal)}`)
            .then(r => r.json())
            .then(data => {
                if (data.phone) document.getElementById('d_phone').value = data.phone;
            });
    }
}

function loadUniqueLocations() {
    fetch('fetch_data.php?action=get_unique_locations')
        .then(r => r.json())
        .then(data => {
            if (!Array.isArray(data)) return;
            uniqueLocations = data;
            const dl = document.getElementById('locationList');
            if (dl) {
                const uniqueLocs = [...new Set(data.map(d => d.location))];
                dl.innerHTML = uniqueLocs.map(l => `<option value="${l}">`).join('');
            }
        })
        .catch(err => console.error("Error loading locations:", err));

    fetch('fetch_data.php?action=get_all_staff_names')
        .then(r => r.json())
        .then(names => {
            allStaffNames = names;
            const sl = document.getElementById('staffList');
            if (sl) sl.innerHTML = names.map(n => `<option value="${n}">`).join('');
        });
}

// Load initially
loadUniqueLocations();

function closeDistModal() {
    document.getElementById('distModal').classList.remove('show');
    document.getElementById('distOverlay').classList.remove('show');
}

function saveDist() {
    const fd = new FormData(document.getElementById('distForm'));
    const editId = document.getElementById('edit_dist_id').value;
    
    fd.append('action', editId ? 'update_distribution' : 'distribute');
    if (editId) fd.append('id', editId);
    
    fd.append('publication_id', document.getElementById('distPubId').value);
    fd.append('location', document.getElementById('d_location').value);
    fd.append('quantity', document.getElementById('d_qty').value);
    fd.append('receiver_name', document.getElementById('d_receiver').value);
    fd.append('receiver_phone', document.getElementById('d_phone').value);

    fetch('save_publication.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                closeDistModal();
                loadPubs(currentPage);
                loadHistory();
            } else {
                alert(res.message);
            }
        });
}

function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.lib-tab-btn').forEach(b => b.classList.remove('active'));
    
    document.getElementById(tabId).classList.add('active');
    // Find button that calls this tab
    document.querySelectorAll('.lib-tab-btn').forEach(b => {
        if (b.getAttribute('onclick').includes(tabId)) b.classList.add('active');
    });

    if (tabId === 'historyTab') {
        loadHistory();
    }
    if (tabId === 'reportTab') {
        loadReportOptions();
    }
}

function loadReportOptions() {
    fetch('fetch_data.php?action=filter_publications&limit=1000')
        .then(r => r.json())
        .then(data => {
            const select = document.getElementById('report_pub_id');
            const pubs = data.publications || [];
            select.innerHTML = '<option value="">هەڵبژێرە...</option>' + 
                pubs.map(p => `<option value="${p.id}">${p.name} - ژمارە (${p.issue_number})</option>`).join('');
        });
}

function generateReport() {
    const id = document.getElementById('report_pub_id').value;
    if (!id) return;

    const area = document.getElementById('reportContentArea');
    area.innerHTML = '<p style="text-align:center;padding:100px;color:#999;">چاوەڕوانبە...</p>';

    // Fetch pub info + distributions
    Promise.all([
        fetch(`fetch_data.php?action=get_publication&id=${id}`).then(r => r.json()),
        fetch(`fetch_data.php?action=get_publication_distributions&publication_id=${id}`).then(r => r.json())
    ]).then(([pub, dists]) => {
        const totalDist = dists.reduce((sum, d) => sum + parseInt(d.distributed_quantity), 0);
        
        area.innerHTML = `
            <div class="report-paper">
                <div class="report-header">
                    <div class="report-subtitle">کتێبخانەی پێشمەرگە</div>
                    <div class="report-title">ڕاپۆرتی دابەشکردنی بڵاوکراوە</div>
                </div>

                <div class="report-summary">
                    <div class="sum-box">
                        <span class="label">ناوی بڵاوکراوە:</span>
                        <span class="val">${pub.name} (${pub.issue_number})</span>
                    </div>
                    <div class="sum-box">
                        <span class="label">بڕی چاپکراو:</span>
                        <span class="val">${pub.total_quantity}</span>
                    </div>
                    <div class="sum-box">
                        <span class="label">ساڵی چاپ:</span>
                        <span class="val">${pub.publish_year}</span>
                    </div>
                    <div class="sum-box">
                        <span class="label">بڕی دابەشکراو:</span>
                        <span class="val">${totalDist}</span>
                    </div>
                    <div class="sum-box">
                        <span class="label">بڕی ماوە:</span>
                        <span class="val">${pub.remaining_quantity}</span>
                    </div>
                    <div class="sum-box">
                        <span class="label">بەروار:</span>
                        <span class="val">${new Date().toLocaleDateString('ku-IQ')}</span>
                    </div>
                </div>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th>شوێن</th>
                            <th style="width:50px;">دانە</th>
                            <th>وەرگر</th>
                            <th>مۆبایل</th>
                            <th>بەروار</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${dists.map(d => `
                            <tr>
                                <td>${d.location}</td>
                                <td style="font-weight:700;text-align:center;">${d.distributed_quantity}</td>
                                <td>${d.receiver_name}</td>
                                <td>${d.receiver_phone || '-'}</td>
                                <td style="font-size:0.7rem;">${d.distributed_at}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                
                <div class="report-footer">
                    <div class="sig-box">
                        <p>واژووی سەرۆکی بەش</p>
                        <div class="sig-line"></div>
                    </div>
                    <div class="sig-box">
                        <p>واژووی تۆمارکەر</p>
                        <div class="sig-line"></div>
                    </div>
                </div>
            </div>
        `;
    });
}

function loadHistory(pubId = null, search = '') {
    const body = document.getElementById('histTableBody');
    body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#999;">کەمێک چاوەڕوانبە...</td></tr>';

    let url = `fetch_data.php?action=get_publication_distributions`;
    if (pubId) url += `&publication_id=${pubId}`;
    if (search) url += `&search=${encodeURIComponent(search)}`;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                body.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:#999;">هیچ داتایەک نییە</td></tr>';
                return;
            }
            body.innerHTML = data.map(d => `
                <tr>
                    <td style="font-weight:700;">${d.publication_name || '-'}</td>
                    <td>${d.issue_number || '-'}</td>
                    <td>${d.location}</td>
                    <td><span class="b-qty">${d.distributed_quantity}</span></td>
                    <td>${d.receiver_name}</td>
                    <td style="font-size:0.85rem;">${d.distributed_at}</td>
                    <td>
                        <div style="display:flex;gap:5px;justify-content:center;">
                            <button class="lib-btn" style="padding:4px 8px;font-size:0.75rem;background:#eee;color:#555;border:1px solid #ddd;" onclick="viewDistDetail(${JSON.stringify(d).replace(/"/g, '&quot;')})">بینین</button>
                            <button class="lib-btn lib-btn-warning" style="padding:4px 8px;font-size:0.75rem;" onclick="editDist(${JSON.stringify(d).replace(/"/g, '&quot;')})">ئیدیت</button>
                            <button class="lib-btn lib-btn-danger" style="padding:4px 8px;font-size:0.75rem;" onclick="deleteDist(${d.id})">سڕینەوە</button>
                        </div>
                    </td>
                </tr>
            `).join('');
        });
}

function viewDistDetail(d) {
    const content = document.getElementById('detailContent');
    content.innerHTML = `
        <div style="display:flex;flex-direction:column;gap:12px;">
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding-bottom:8px;">
                <span style="color:#666;">بڵاوکراوە:</span>
                <span style="font-weight:700;">${d.publication_name} (${d.issue_number})</span>
            </div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding-bottom:8px;">
                <span style="color:#666;">شوێن:</span>
                <span style="font-weight:700;">${d.location}</span>
            </div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding-bottom:8px;">
                <span style="color:#666;">بڕی دابەشکراو:</span>
                <span style="font-weight:700;color:#2e7d32;">${d.distributed_quantity} دانە</span>
            </div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding-bottom:8px;">
                <span style="color:#666;">وەرگر:</span>
                <span style="font-weight:700;">${d.receiver_name}</span>
            </div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding-bottom:8px;">
                <span style="color:#666;">مۆبایل:</span>
                <span style="font-weight:700;">${d.receiver_phone || 'دیاری نەکراوە'}</span>
            </div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding-bottom:8px;">
                <span style="color:#666;">بەروار:</span>
                <span style="font-weight:700;">${d.distributed_at}</span>
            </div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid #eee;padding-bottom:8px;background:#f9f9f9;padding:10px;border-radius:8px;">
                <span style="color:#666;">تۆمارکەر:</span>
                <span style="font-weight:700;color:#5a0202;">${d.entry_user || 'نەزانراو'}</span>
            </div>
        </div>
    `;
    document.getElementById('detailModal').classList.add('show');
    document.getElementById('detailOverlay').classList.add('show');
}

function closeDetailModal() {
    document.getElementById('detailModal').classList.remove('show');
    document.getElementById('detailOverlay').classList.remove('show');
}

function viewHistory(id) {
    document.getElementById('hist_search').value = '';
    switchTab('historyTab');
    loadHistory(id);
}

function loadAllHistory() {
    document.getElementById('hist_search').value = '';
    loadHistory();
}

function editDist(d) {
    document.getElementById('distPubId').value = d.publication_id;
    document.getElementById('edit_dist_id').value = d.id;
    document.getElementById('d_location').value = d.location;
    document.getElementById('d_qty').value = d.distributed_quantity;
    document.getElementById('d_receiver').value = d.receiver_name;
    document.getElementById('d_phone').value = d.receiver_phone || '';
    
    document.getElementById('distPubInfo').innerText = `${d.publication_name} (${d.issue_number})`;
    document.getElementById('distSubmitBtn').innerText = 'نوێکردنەوە';
    document.getElementById('distModal').querySelector('h3').innerText = 'ئیدیت کردنی دابەشکردن';
    
    document.getElementById('distModal').classList.add('show');
    document.getElementById('distOverlay').classList.add('show');
}

function deleteDist(id) {
    if (!confirm('ئایا دڵنیایت لە سڕینەوەی ئەم دابەشکردنە؟')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_distribution');
    formData.append('id', id);
    
    fetch('save_publication.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            loadHistory();
            loadPubs(currentPage);
        } else {
            alert(res.message);
        }
    });
}

document.getElementById('pub_search').addEventListener('input', () => loadPubs(1));
document.getElementById('pub_type_filter').addEventListener('change', () => loadPubs(1));
document.getElementById('hist_search').addEventListener('input', (e) => {
    loadHistory(null, e.target.value);
});
loadPubs(1);
</script>

<?php require_once 'includes/layout_end.php'; ?>
