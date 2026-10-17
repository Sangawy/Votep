<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

require_once 'config.php';
$conn = getDB();

$categories = [];
if ($conn !== null) {
    // Fetch categories and their most common shelf code
    $sql = "SELECT c.id, c.name, 
            (SELECT shelf_code FROM books b WHERE b.category_id = c.id AND shelf_code IS NOT NULL AND shelf_code != '' GROUP BY shelf_code ORDER BY COUNT(*) DESC LIMIT 1) as current_shelf
            FROM categories c ORDER BY c.name";
    $r = $conn->query($sql);
    while ($row = $r->fetch_assoc()) $categories[] = $row;
}

$page_title = 'سێتینگی کتێبخانە';
$active_nav = 'lib_settings';
require_once 'includes/layout.php';
?>

<style>
    .settings-dashboard { max-width: 1100px; margin: 0 auto; padding: 20px; }
    
    /* Dashboard Grid */
    .settings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
    
    .settings-card-item { 
        background: #fff; border-radius: 20px; padding: 30px 20px; text-align: center; 
        border: 1px solid #eee; box-shadow: 0 4px 15px rgba(0,0,0,0.03); 
        transition: all 0.3s ease; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 15px;
    }
    .settings-card-item:hover { 
        transform: translateY(-8px); box-shadow: 0 12px 30px rgba(90,2,2,0.1); 
        border-color: #5a020222; 
    }
    
    .settings-card-item .icon-box { 
        width: 70px; height: 70px; background: #fdf2f2; color: #5a0202; 
        border-radius: 18px; display: flex; align-items: center; justify-content: center; 
        transition: .3s;
    }
    .settings-card-item:hover .icon-box { background: #5a0202; color: #fff; transform: rotate(-5deg); }
    
    .settings-card-item h4 { margin: 0; font-size: 1.1rem; font-weight: 700; color: #333; }
    .settings-card-item p { margin: 0; font-size: 0.85rem; color: #777; line-height: 1.5; }

    /* Detail View Styles */
    .settings-detail-view { display: none; animation: fadeIn 0.3s ease; }
    .settings-detail-view.active { display: block; }
    
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    
    .back-btn { 
        display: inline-flex; align-items: center; gap: 8px; color: #5a0202; 
        font-weight: 700; cursor: pointer; margin-bottom: 20px; padding: 8px 15px; 
        border-radius: 8px; background: #fff; border: 1px solid #eee; transition: .2s;
    }
    .back-btn:hover { background: #fdf2f2; border-color: #5a0202; }

    /* Shelf Management Styles (from before) */
    .mgmt-card { background: #fff; border-radius: 16px; border: 1px solid #eee; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; }
    .card-hdr { background: #5a0202; color: #fff; padding: 18px 24px; display: flex; align-items: center; gap: 12px; }
    .card-hdr h3 { margin: 0; font-size: 1.15rem; font-weight: 700; }
    .card-body { padding: 25px; }
    .settings-info { background: #fff8e1; border-right: 4px solid #ffc107; padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 0.9rem; color: #6d4c41; line-height: 1.6; }
    .category-list-header { display: grid; grid-template-columns: 1fr 200px 150px; gap: 20px; padding: 10px 15px; background: #f8f9fa; border-radius: 8px; font-weight: 700; font-size: 0.85rem; color: #666; margin-bottom: 10px; }
    .category-row { display: grid; grid-template-columns: 1fr 200px 150px; gap: 20px; align-items: center; padding: 12px 15px; border-bottom: 1px solid #f5f5f5; transition: .2s; }
    .category-row:hover { background: #f9f9f9; }
    .cat-name { font-weight: 700; color: #333; }
    .shelf-input { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9rem; }
    .shelf-input:focus { border-color: #5a0202; outline: none; box-shadow: 0 0 0 3px rgba(90,2,2,0.05); }
    .btn-apply { background: #5a0202; color: #fff; border: none; padding: 8px 15px; border-radius: 8px; font-weight: 700; font-size: 0.82rem; cursor: pointer; transition: .2s; display: flex; align-items: center; justify-content: center; gap: 6px; }
    .btn-apply:hover { background: #7a0303; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
    .btn-apply.loading { opacity: 0.7; pointer-events: none; }

    .btn-circle { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #eee; background: #fff; color: #666; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: .2s; }
    .btn-circle:hover { background: #f8f9fa; color: #5a0202; border-color: #5a0202; }
    .btn-circle.del:hover { color: #d32f2f; border-color: #d32f2f; background: #fff5f5; }

    .btn-cat-edit {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #fdf2f2;
        color: #5a0202;
        border: 1px solid #fecaca;
        padding: 5px 12px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        font-family: inherit;
        white-space: nowrap;
    }
    .btn-cat-edit:hover {
        background: #5a0202;
        color: #ffffff;
        border-color: #5a0202;
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(90, 2, 2, 0.2);
    }
</style>

<div class="settings-dashboard">
    
    <!-- DASHBOARD VIEW -->
    <div id="dashboardView" class="settings-detail-view active">
        <div style="margin-bottom: 30px;">
            <h2 style="color:#5a0202; font-weight:800; font-size:1.6rem; margin-bottom:10px;">سێتینگی کتێبخانە</h2>
            <p style="color:#666;">لێرەدا دەتوانیت سەرجەم ڕێکخستنەکانی بەشی کتێبخانە بەڕێوە ببەیت.</p>
        </div>

        <div class="settings-grid">
            <!-- Card: Shelf Management -->
            <div class="settings-card-item" onclick="loadShelfMgmt()">
                <div class="icon-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:32px;height:32px;"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </div>
                <h4>ڕێکخستنی ڕەفەکان</h4>
                <p>گۆڕینی بەکۆمەڵی کۆدی ڕەفە بۆ هەموو کتێبەکانی بەشێکی دیاریکراو.</p>
            </div>

            <!-- Card: User Entry Stats -->
            <div class="settings-card-item" onclick="loadUserStats()">
                <div class="icon-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:32px;height:32px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <h4>ئاماری بەکارهێنەران</h4>
                <p>بینین و چاپی ئاماری تۆمارکردنی کتێبەکان لەلایەن بەکارهێنەرانەوە.</p>
            </div>

            <!-- Card: Categories Management -->
            <div class="settings-card-item" onclick="loadCategoriesMgmt()">
                <div class="icon-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:32px;height:32px;"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                </div>
                <h4>ڕێکخستنی بەشەکان</h4>
                <p>زیادکردن، سڕینەوە و دەستکاریکردنی بەشەکانی کتێبخانە.</p>
            </div>

            <!-- Card: Shelf Labels Print -->
            <div class="settings-card-item" onclick="window.open('print_shelf_stickers.php', '_blank')">
                <div class="icon-box" style="background:#fef3c7; color:#b45309;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:32px;height:32px;"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                </div>
                <h4>چاپکردنی لەزگەی ڕەفەکان</h4>
                <p>چاپی ناوی پیتەکانی ڕەفە (6cm × 4cm) بە قەبارەی گەورە بۆ سەر ڕەفەکان.</p>
            </div>
        </div>
    </div>

    <!-- SECTION: USER STATISTICS -->
    <div id="userStatsView" class="settings-detail-view">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div class="back-btn" onclick="showSection('dashboard')" style="margin-bottom:0;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><polyline points="9 18 15 12 9 6"/></svg>
                گەڕانەوە بۆ داشبۆرد
            </div>
            <button class="lib-btn lib-btn-primary" onclick="printUserStats()" style="display:flex; align-items:center; gap:8px; height:40px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                چاپی ڕاپۆرت
            </button>
        </div>

        <div class="mgmt-card" id="printableStats">
            <div class="card-hdr no-print">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:22px;height:22px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                <h3>ئاماری تۆمارکردنی کتێب لەلایەن بەکارهێنەرانەوە</h3>
            </div>
            
            <!-- Print Header (Hidden on screen) -->
            <div class="print-header" style="display:none; text-align:center; padding:20px; border-bottom:2px solid #5a0202; margin-bottom:30px;">
                <h2 style="color:#5a0202; margin:0 0 5px 0;">ڕاپۆرتی ئاماری بەکارهێنەران</h2>
                <p style="margin:0; font-size:0.9rem; color:#666;">سیستەمی بەڕێوەبردنی کتێبخانەی پێشمەرگە</p>
                <p style="margin:5px 0 0 0; font-size:0.8rem; color:#999;">ڕێکەوتی چاپ: <?php echo date('Y-m-d H:i'); ?></p>
            </div>

            <div class="card-body">
                <div id="userStatsTableArea">
                    <!-- Loaded via JS -->
                    <div style="text-align:center; padding:50px; color:#999;">کەمێک چاوەڕوانبە...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION: CATEGORIES MANAGEMENT -->
    <div id="categoriesMgmtView" class="settings-detail-view">
        <div class="back-btn" onclick="showSection('dashboard')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><polyline points="9 18 15 12 9 6"/></svg>
            گەڕانەوە بۆ داشبۆرد
        </div>

        <div class="mgmt-card">
            <div class="card-hdr" style="display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:22px;height:22px;"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    <h3>بەڕێوەبردنی بەشەکانی کتێبخانە</h3>
                </div>
                <button class="lib-btn lib-btn-gold" onclick="promptAddCategoryFull()" style="height:35px; padding:0 15px; font-size:0.8rem;">
                    + زیادکردنی بەشی نوێ
                </button>
            </div>
            <div class="card-body">
                <div id="categoriesTableArea">
                    <!-- Loaded via JS -->
                    <div style="text-align:center; padding:50px; color:#999;">کەمێک چاوەڕوانبە...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION: SHELF MANAGEMENT -->
    <div id="shelfMgmtView" class="settings-detail-view">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <div class="back-btn" onclick="showSection('dashboard')" style="margin-bottom:0;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><polyline points="9 18 15 12 9 6"/></svg>
                گەڕانەوە بۆ داشبۆرد
            </div>
            <a href="print_shelf_stickers.php" target="_blank" style="display:inline-flex; align-items:center; gap:8px; height:40px; text-decoration:none; background:#5a0202; color:#fff; padding:0 18px; border-radius:8px; font-weight:700; font-size:0.86rem; box-shadow:0 3px 10px rgba(90,2,2,0.2); transition:.2s;" onmouseover="this.style.background='#7a0303'" onmouseout="this.style.background='#5a0202'">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                🖨️ چاپکردنی لەزگەی ڕەفەکان (6cm × 4cm)
            </a>
        </div>

        <div class="mgmt-card">
            <div class="card-hdr">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:22px;height:22px;"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                <h3>ڕێکخستنی ڕەفەکان بەگوێرەی بەش</h3>
            </div>
            <div class="card-body">
                <div class="settings-info">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;vertical-align:middle;margin-left:5px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    کاتێک کۆدێک دەنووسیت و "جێبەجێکردن" دەکەیت، <strong>هەموو کتێبەکانی ناو ئەو بەشە</strong> کۆدی ڕەفەکەیان دەگۆڕێت.
                </div>

                <div class="category-list" id="shelfMgmtTableArea">
                    <!-- Loaded via JS -->
                    <div style="text-align:center; padding:50px; color:#999;">کەمێک چاوەڕوانبە...</div>
                </div>
            </div>
        </div>
    </div>

</div> <!-- Close .settings-dashboard -->

<style>
@media print {
    body * { visibility: hidden; }
    #printableStats, #printableStats * { visibility: visible; }
    #printableStats { position: absolute; left: 0; top: 0; width: 100%; border:none; box-shadow:none; }
    .no-print, .back-btn, .lib-btn { display: none !important; }
    .print-header { display: block !important; }
    .lib-sidebar, .lib-topbar { display: none !important; }
    .settings-dashboard { padding: 0; margin: 0; max-width: 100%; }
}
</style>

<script>
function showSection(section) {
    document.querySelectorAll('.settings-detail-view').forEach(v => v.classList.remove('active'));
    if (section === 'dashboard') {
        document.getElementById('dashboardView').classList.add('active');
    } else if (section === 'shelfMgmt') {
        document.getElementById('shelfMgmtView').classList.add('active');
    } else if (section === 'userStats') {
        document.getElementById('userStatsView').classList.add('active');
    } else if (section === 'categoriesMgmt') {
        document.getElementById('categoriesMgmtView').classList.add('active');
    }
}

async function loadCategoriesMgmt() {
    showSection('categoriesMgmt');
    const area = document.getElementById('categoriesTableArea');
    area.innerHTML = '<div style="text-align:center; padding:50px; color:#999;">کەمێک چاوەڕوانبە...</div>';
    
    try {
        const resp = await fetch('fetch_data.php?action=get_categories');
        const categories = await resp.json();
        
        let html = `
            <table class="lib-table" style="width:100%; border-collapse:collapse; margin-top:10px;">
                <thead>
                    <tr style="background:#f8f9fa; border-bottom:2px solid #eee;">
                        <th style="padding:15px; text-align:right;">ناوی بەش</th>
                        <th style="padding:15px; text-align:center; width:150px;">کردارەکان</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        categories.forEach(c => {
            html += `
                <tr style="border-bottom:1px solid #f5f5f5;">
                    <td style="padding:15px; font-weight:700; color:#333;">${c.name}</td>
                    <td style="padding:15px; text-align:center; display:flex; gap:8px; justify-content:center;">
                        <button class="btn-circle" onclick="editCategory(${c.id}, '${c.name.replace(/'/g, "\\'")}')" title="دەستکاری">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="btn-circle del" onclick="deleteCategory(${c.id}, '${c.name.replace(/'/g, "\\'")}')" title="سڕینەوە">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        area.innerHTML = html;
        
    } catch (err) {
        area.innerHTML = '<div style="text-align:center; padding:50px; color:red;">هەڵە لە وەرگرتنی زانیارییەکان</div>';
    }
}

async function loadShelfMgmt() {
    showSection('shelfMgmt');
    const area = document.getElementById('shelfMgmtTableArea');
    area.innerHTML = '<div style="text-align:center; padding:50px; color:#999;">کەمێک چاوەڕوانبە...</div>';
    
    try {
        const resp = await fetch('fetch_data.php?action=get_shelf_categories');
        const categories = await resp.json();
        
        if (!categories.length) {
            area.innerHTML = '<div style="padding:40px; text-align:center; color:#999;">هیچ بەشێک نەدۆزرایەوە.</div>';
            return;
        }
        
        let html = `
            <div class="category-list-header">
                <div>ناوی بەش</div>
                <div>کۆدی ڕەفە</div>
                <div>کردار</div>
            </div>
        `;
        
        categories.forEach(cat => {
            html += `
                <div class="category-row">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                        <span class="cat-name">${cat.name}</span>
                        <button type="button" class="btn-cat-edit" onclick="editCategoryFromShelf(${cat.id}, '${cat.name.replace(/'/g, "\\'")}')" title="دەستکاریکردنی ناوی بەش (${cat.name})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:13px;height:13px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            <span>ئیدیت</span>
                        </button>
                    </div>
                    <div>
                        <input type="text" class="shelf-input" id="shelf_${cat.id}" placeholder="کۆدی ڕەفە..." value="${cat.current_shelf || ''}">
                    </div>
                    <div>
                        <button class="btn-apply" onclick="applyShelfCode(${cat.id}, '${cat.name.replace(/'/g, "\\'")}', this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:14px;height:14px;"><polyline points="20 6 9 17 4 12"/></svg>
                            جێبەجێکردن
                        </button>
                    </div>
                </div>
            `;
        });
        
        area.innerHTML = html;
        
    } catch (err) {
        area.innerHTML = '<div style="text-align:center; padding:50px; color:red;">هەڵە لە وەرگرتنی زانیارییەکان</div>';
    }
}

async function editCategoryFromShelf(id, currentName) {
    const newName = prompt('ناوی نوێ بۆ ئەم بەشە بنووسە:', currentName);
    if (!newName || !newName.trim() || newName.trim() === currentName) return;
    
    const fd = new FormData();
    fd.append('action', 'update_category');
    fd.append('id', id);
    fd.append('name', newName.trim());
    
    try {
        const resp = await fetch('fetch_data.php', { method: 'POST', body: fd });
        const res = await resp.json();
        if (res.success) {
            libToast(`ناوی بەش گۆڕدرا بۆ (${newName.trim()})`, 'success');
            loadShelfMgmt();
        } else {
            libToast(res.error || 'هەڵەیەک ڕوویدا', 'error');
        }
    } catch (err) {
        libToast('پەیوەندی سەرنەکەوت', 'error');
    }
}

async function promptAddCategoryFull() {
    const name = prompt('ناوی بەشی نوێ بنووسە:');
    if (!name || !name.trim()) return;
    
    const fd = new FormData();
    fd.append('action', 'add_category');
    fd.append('name', name.trim());
    
    try {
        const resp = await fetch('fetch_data.php', { method: 'POST', body: fd });
        const res = await resp.json();
        if (res.success) {
            libToast(res.exists ? 'ئەم بەشە پێشتر هەبووە' : 'بەشەکە بە سەرکەوتوویی زیادکرا', res.exists ? 'warning' : 'success');
            loadCategoriesMgmt();
        } else {
            libToast(res.message || 'هەڵەیەک ڕوویدا', 'error');
        }
    } catch (err) {
        libToast('پەیوەندی سەرنەکەوت', 'error');
    }
}

async function editCategory(id, currentName) {
    const newName = prompt('ناوی نوێ بۆ ئەم بەشە بنووسە:', currentName);
    if (!newName || !newName.trim() || newName.trim() === currentName) return;
    
    const fd = new FormData();
    fd.append('action', 'update_category');
    fd.append('id', id);
    fd.append('name', newName.trim());
    
    try {
        const resp = await fetch('fetch_data.php', { method: 'POST', body: fd });
        const res = await resp.json();
        if (res.success) {
            libToast('بەشەکە نوێکرایەوە', 'success');
            loadCategoriesMgmt();
        } else {
            libToast(res.error || 'هەڵەیەک ڕوویدا', 'error');
        }
    } catch (err) {
        libToast('پەیوەندی سەرنەکەوت', 'error');
    }
}

async function deleteCategory(id, name) {
    if (!confirm(`ئایا دڵنیایت دەتەوێت بەشی (${name}) بسڕیتەوە؟\nتێبینی: ئەگەر کتێب لەم بەشەدا هەبێت، ناتوانیت بیسڕیتەوە.`)) return;
    
    const fd = new FormData();
    fd.append('action', 'delete_category');
    fd.append('id', id);
    
    try {
        const resp = await fetch('fetch_data.php', { method: 'POST', body: fd });
        const res = await resp.json();
        if (res.success) {
            libToast('بەشەکە سڕایەوە', 'success');
            loadCategoriesMgmt();
        } else {
            libToast(res.error || 'هەڵەیەک ڕوویدا (لەوانەیە کتێبی تێدا بێت)', 'error');
        }
    } catch (err) {
        libToast('پەیوەندی سەرنەکەوت', 'error');
    }
}

async function loadUserStats() {
    showSection('userStats');
    const area = document.getElementById('userStatsTableArea');
    area.innerHTML = '<div style="text-align:center; padding:50px; color:#999;">کەمێک چاوەڕوانبە...</div>';
    
    try {
        const resp = await fetch('fetch_data.php?action=get_user_entry_stats');
        const stats = await resp.json();
        
        if (!stats.length) {
            area.innerHTML = '<div style="text-align:center; padding:50px; color:#999;">هیچ داتایەک نەدۆزرایەوە.</div>';
            return;
        }
        
        let html = `
            <table class="lib-table" style="width:100%; border-collapse:collapse; margin-top:10px;">
                <thead>
                    <tr style="background:#f8f9fa; border-bottom:2px solid #eee;">
                        <th style="padding:15px; text-align:right;">ناوی بەکارهێنەر</th>
                        <th style="padding:15px; text-align:center;">ژمارەی ناونیشانی جیاواز</th>
                        <th style="padding:15px; text-align:center;">کۆی گشتی دانەکان (نوسخە)</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        stats.forEach(s => {
            html += `
                <tr style="border-bottom:1px solid #f5f5f5;">
                    <td style="padding:15px; font-weight:700; color:#5a0202;">${s.full_name || 'بەکارهێنەری سڕاوە'}</td>
                    <td style="padding:15px; text-align:center;">${s.total_titles}</td>
                    <td style="padding:15px; text-align:center; font-weight:700;">${s.total_qty || 0}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        area.innerHTML = html;
        
    } catch (err) {
        area.innerHTML = '<div style="text-align:center; padding:50px; color:red;">هەڵە لە وەرگرتنی زانیارییەکان</div>';
    }
}

function printUserStats() {
    window.print();
}

async function applyShelfCode(catId, catName, btn) {
    const input = document.getElementById('shelf_' + catId);
    const shelfCode = input.value.trim();
    if (!shelfCode) { libToast('تکایە کۆدی ڕەفە بنووسە', 'error'); return; }
    if (!confirm(`ئایا دڵنیایت دەتەوێت کۆدی ڕەفەی هەموو کتێبەکانی بەشی (${catName}) بگۆڕیت بۆ (${shelfCode})؟`)) return;
    
    const originalContent = btn.innerHTML;
    btn.classList.add('loading');
    btn.innerHTML = '...';
    
    const fd = new FormData();
    fd.append('action', 'update_category_shelf');
    fd.append('category_id', catId);
    fd.append('shelf_code', shelfCode);
    
    try {
        const resp = await fetch('fetch_data.php', { method: 'POST', body: fd });
        const res = await resp.json();
        if (res.success) libToast(res.message, 'success');
        else libToast(res.error || 'هەڵەیەک ڕوویدا', 'error');
    } catch (err) {
        libToast('پەیوەندی سەرنەکەوت', 'error');
    } finally {
        btn.classList.remove('loading');
        btn.innerHTML = originalContent;
    }
}
</script>

<?php require_once 'includes/layout_end.php'; ?>
