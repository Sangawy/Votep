<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

require_once 'config.php';
$conn = getDB();

$authors     = [];
$translators = [];
$categories  = [];
$years       = [];

if ($conn !== null) {
    $r = $conn->query("SELECT id, name FROM authors ORDER BY name");
    while ($row = $r->fetch_assoc()) $authors[] = $row;

    $r = $conn->query("SELECT id, name FROM translators ORDER BY name");
    while ($row = $r->fetch_assoc()) $translators[] = $row;

    $res = $conn->query("SELECT c.id, c.name, 
                        (SELECT shelf_code FROM books b WHERE b.category_id = c.id AND shelf_code IS NOT NULL AND shelf_code != '' GROUP BY shelf_code ORDER BY COUNT(*) DESC LIMIT 1) as shelf_code
                        FROM categories c ORDER BY c.name");
    while($row = $res->fetch_assoc()) $categories[] = $row;

    $r = $conn->query("SELECT DISTINCT publish_year FROM books WHERE publish_year IS NOT NULL ORDER BY publish_year DESC");
    while ($row = $r->fetch_assoc()) $years[] = $row['publish_year'];

    $unique_titles = [];
    $r = $conn->query("SELECT DISTINCT title FROM books ORDER BY title ASC");
    while ($row = $r->fetch_assoc()) $unique_titles[] = $row['title'];
}

// ── Layout variables ──────────────────────────────────────────────
$page_title = 'کتێبەکان';
$active_nav = 'books';
require_once 'includes/layout.php';
?>

<style>
    /* Page-specific styles for books.php */
    .page-hdr,
    .lib-tabs,
    #ourBooksTab,
    #bookModal,
    #viewModal,
    #pdfViewer,
    .page-hdr input,
    .page-hdr button,
    .lib-tabs button,
    #ourBooksTab input,
    #ourBooksTab button,
    #ourBooksTab select,
    #ourBooksTab textarea,
    #bookModal input,
    #bookModal button,
    #bookModal select,
    #bookModal textarea,
    #viewModal button {
        font-family:'Rabar022', 'Segoe UI', Tahoma, Arial, sans-serif;
    }
    .page-hdr { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
    .page-hdr h2 { font-size:1.4rem; color:#5a0202; font-weight:700; }

    /* Filters card */
    .filters-card { background:#fff; border-radius:14px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,0.04); border:1px solid #eee; margin-bottom:25px; }
    
    .search-row { display:flex; gap:12px; align-items:center; }
    .search-group { flex:1; display:flex; gap:10px; position:relative; }
    .search-input-wrapper { flex:1; position:relative; }
    
    .search-input-wrapper input { width:100%; padding:12px 45px 12px 15px; border:1px solid #ddd; border-radius:10px; font-size:0.95rem; transition:.2s; }
    .search-input-wrapper input:focus { border-color:#5a0202; outline:none; box-shadow:0 0 0 3px rgba(90,2,2,0.05); }
    .search-input-wrapper .search-icon { position:absolute; right:15px; top:50%; transform:translateY(-50%); color:#999; pointer-events:none; }
    
    .category-combo { position:relative; width:220px; flex:0 0 220px; font-family:'Rabar022', 'Segoe UI', Tahoma, Arial, sans-serif; }
    .category-combo-btn { width:100%; height:46px; padding:0 14px 0 34px; border:1px solid #ddd; border-radius:10px; font-size:0.9rem; font-weight:700; cursor:pointer; background:#f9f9f9; color:#111; display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .category-combo-btn:hover,
    .category-combo.open .category-combo-btn { border-color:#5a0202; background:#fff; box-shadow:0 0 0 3px rgba(90,2,2,0.05); }
    .category-combo-btn svg { width:16px; height:16px; flex-shrink:0; transition:.2s; }
    .category-combo.open .category-combo-btn svg { transform:rotate(180deg); }
    .category-combo-label { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .category-combo-menu { position:absolute; top:calc(100% + 6px); left:0; right:0; background:#fff; border:1px solid #ddd; border-radius:12px; box-shadow:0 12px 30px rgba(0,0,0,.14); padding:10px; z-index:200; display:none; }
    .category-combo.open .category-combo-menu { display:block; }
    .category-combo-search { width:100%; height:40px; padding:0 12px; border:1px solid #ddd; border-radius:9px; font-size:.88rem; outline:none; margin-bottom:8px; }
    .category-combo-search:focus { border-color:#5a0202; box-shadow:0 0 0 3px rgba(90,2,2,0.05); }
    .category-combo-list { max-height:290px; overflow-y:auto; }
    .category-combo-option { padding:9px 11px; border-radius:8px; cursor:pointer; font-size:.9rem; font-weight:600; color:#111; line-height:1.4; }
    .category-combo-option:hover,
    .category-combo-option.active { background:#fdf2f2; color:#5a0202; }
    .category-combo-empty { padding:18px 8px; text-align:center; color:#999; font-size:.85rem; }
    
    .btn-action { height:46px; padding:0 18px; border-radius:10px; font-weight:700; font-size:0.88rem; cursor:pointer; display:flex; align-items:center; gap:8px; border:1px solid #eee; transition:.2s; white-space:nowrap; }
    .btn-toggle { background:#f5f5f5; color:#5a0202; border:1px solid #ddd; }
    .btn-toggle:hover, .btn-toggle.open { background:#5a0202; color:#fff; border-color:#5a0202; }
    .btn-clear { background:#fff; color:#d32f2f; border-color:#ffcdd2; }
    .btn-clear:hover { background:#fff5f5; border-color:#d32f2f; }

    .adv-filters { display:none; margin-top:20px; padding-top:20px; border-top:1px dashed #eee; }
    .adv-filters.show { display:block; }
    .adv-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:16px; }
    
    /* Books grid layout */
    .books-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:25px; padding:5px; }
    .book-card { background:#fff; border-radius:16px; border:1px solid #eee; overflow:hidden; transition:all .25s ease; position:relative; box-shadow:0 2px 10px rgba(0,0,0,0.04); display:flex; flex-direction:column; }
    .book-card:hover { transform:translateY(-8px); box-shadow:0 12px 25px rgba(0,0,0,0.12); border-color:#5a020222; }
    
    .book-cover-area { width:100%; height:280px; background:#f5f5f5; position:relative; overflow:hidden; display:flex; align-items:center; justify-content:center; }
    .book-cover-area img { width:100%; height:100%; object-fit:cover; transition:.4s; }
    .book-card:hover .book-cover-area img { transform:scale(1.08); }
    .no-cover { display:flex; flex-direction:column; align-items:center; gap:10px; color:#ccc; }
    
    .book-details { padding:18px; flex:1; display:flex; flex-direction:column; }
    .book-title { font-family:'Rabar022', 'Segoe UI', Tahoma, Arial, sans-serif; font-size:1.05rem; font-weight:700; color:#1a1a1a; margin-bottom:4px; line-height:1.3; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; height:2.6rem; cursor:pointer; transition:.2s; }
    .book-category { font-size:0.72rem; color:#999; font-weight:700; margin-bottom:8px; display:inline-block; background:#f9f9f9; padding:2px 8px; border-radius:4px; transition:.2s; cursor:pointer; }
    .book-category:hover { background:#5a020211; color:#5a0202; }
    .book-author { font-size:0.85rem; color:#666; font-weight:600; margin-bottom:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    
    .book-meta { display:flex; justify-content:space-between; align-items:center; margin-top:auto; padding-top:12px; border-top:1px solid #f5f5f5; }
    .book-code-badge { font-family:monospace; background:#f0f0f0; padding:2px 8px; border-radius:5px; font-size:0.75rem; color:#5a0202; font-weight:700; }
    .book-qty-badge { background:#e8f5e9; color:#2e7d32; padding:2px 8px; border-radius:12px; font-size:0.75rem; font-weight:800; }
    
    .book-actions { position:absolute; top:12px; left:12px; display:flex; flex-direction:column; gap:8px; opacity:0; transform:translateX(-10px); transition:.25s; z-index:10; }
    .book-card:hover .book-actions { opacity:1; transform:translateX(0); }
    
    .btn-circle { width:36px; height:36px; border-radius:50%; background:#fff; border:1px solid #eee; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#555; box-shadow:0 3px 8px rgba(0,0,0,0.1); transition:.2s; }
    .btn-circle:hover { background:#5a0202; color:#fff; border-color:#5a0202; }
    .btn-circle.del:hover { background:#d32f2f; border-color:#d32f2f; }
    
    .pdf-indicator { position:absolute; top:12px; right:12px; background:rgba(211,47,47,0.9); color:#fff; padding:5px 10px; border-radius:8px; font-size:0.7rem; font-weight:800; display:flex; align-items:center; gap:5px; z-index:5; box-shadow:0 2px 5px rgba(0,0,0,0.2); }
 
    /* Toggle Switch */
    .lib-switch { display:inline-flex; align-items:center; gap:12px; cursor:pointer; user-select:none; margin-top:10px; padding:5px; }
    .lib-switch input { display:none !important; }
    .lib-switch .slider { width:44px; height:22px; background:#ccc; border-radius:22px; position:relative; transition:.3s; flex-shrink:0; display:block; border:1px solid #ddd; }
    .lib-switch .slider:before { content:''; position:absolute; width:18px; height:18px; background:#fff; border-radius:50%; top:1px; left:1px; transition:.3s; box-shadow:0 1px 3px rgba(0,0,0,0.2); }
    .lib-switch input:checked + .slider { background:#5a0202; border-color:#5a0202; }
    .lib-switch input:checked + .slider:before { left:23px; }
    .lib-switch .label { font-size:.85rem; font-weight:700; color:#555; }

    /* Badges */
    .b-num  { background:#f0f0f0; color:#555; padding:3px 9px; border-radius:6px; font-size:.82rem; font-weight:700; }
    .b-cat  { background:rgba(90,2,2,.07); color:#5a0202; padding:3px 9px; border-radius:6px; font-size:.82rem; font-weight:600; white-space:nowrap; max-width:150px; overflow:hidden; text-overflow:ellipsis; display:inline-block; }
    .b-qty  { background:#e8f5e9; color:#2e7d32; padding:3px 10px; border-radius:14px; font-weight:700; }
    .b-code { font-family:monospace; letter-spacing:.8px; color:#5a0202; font-weight:700; }

    /* Smart select in modal */
    .smart-select { position:relative; }
    .smart-input { width:100%; padding:11px 14px; border:2px solid #e0e0e0; border-radius:8px; font-family:inherit; font-size:.95rem; background:#fff; }
    .smart-input:focus { border-color:#5a0202; outline:none; }
    .smart-select .dd-menu { position:absolute; top:100%; left:0; right:0; background:#fff; border:2px solid #5a0202; border-top:none; border-radius:0 0 8px 8px; max-height:220px; overflow-y:auto; z-index:2000; display:none; box-shadow:0 4px 12px rgba(0,0,0,.12); }
    .smart-select .dd-menu.show { display:block; }
    .smart-select .dd-item { padding:11px 14px; cursor:pointer; border-bottom:1px solid #f0f0f0; transition:.15s; font-size:.9rem; }
    .smart-select .dd-item:hover { background:#fff3e0; color:#5a0202; }

    /* Modal form grid */
    .modal-form { display:grid; grid-template-columns:repeat(3,1fr); gap:14px 22px; }
    .modal-form .full { grid-column:span 3; }
    .modal-form .half { grid-column:span 1.5; } /* custom for 2 cols */
    .lang-opts { display:flex; gap:10px; flex-wrap:wrap; margin-top:4px; }
    .lang-cb { display:none; }
    .lang-lbl { padding:8px 12px; border:2px solid #e0e0e0; border-radius:8px; cursor:pointer; font-weight:600; font-size:.82rem; transition:.18s; }
    .lang-cb:checked + .lang-lbl { background:#5a0202; color:#fff; border-color:#5a0202; }

    /* File upload styles */
    .file-input-wrapper { position:relative; overflow:hidden; display:inline-block; width:100%; }
    .file-input-wrapper input[type=file] { font-size:100px; position:absolute; left:0; top:0; opacity:0; cursor:pointer; }
    .file-btn { display:flex; align-items:center; justify-content:center; gap:8px; background:#f8f9fa; border:2px dashed #ccc; border-radius:10px; padding:20px; color:#666; font-weight:600; transition:.2s; cursor:pointer; }
    .file-btn:hover { border-color:#5a0202; background:#fffcfc; color:#5a0202; }
    .file-btn svg { width:24px; height:24px; opacity:0.7; }
    .file-info { font-size:0.8rem; color:#888; margin-top:5px; word-break:break-all; }
    
    .cover-preview-box { width:100%; height:180px; border:2px solid #eee; border-radius:12px; background:#fdfdfd; display:flex; align-items:center; justify-content:center; overflow:hidden; position:relative; margin-bottom:10px; }
    .cover-preview-box img { max-width:100%; max-height:100%; object-fit:contain; }
    .cover-preview-box span { color:#ccc; font-weight:600; font-size:0.9rem; }
    
    .pdf-type-toggle { display:flex; background:#f0f0f0; border-radius:8px; padding:4px; margin-bottom:10px; }
    .pdf-type-btn { flex:1; padding:8px; border:none; background:none; font-family:inherit; font-weight:700; font-size:0.85rem; cursor:pointer; border-radius:6px; transition:.2s; }
    .pdf-type-btn.active { background:#fff; color:#5a0202; box-shadow:0 2px 5px rgba(0,0,0,0.1); }

    .remove-img-btn {
        position: absolute;
        top: 8px;
        right: 8px;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: rgba(211,47,47,0.85);
        color: #fff;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 20px;
        line-height: 1;
        z-index: 10;
        transition: .2s;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }
    .remove-img-btn:hover { background: #d32f2f; transform: scale(1.1); }

    /* PDF Viewer Modal */
    .pdf-viewer-modal { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:9999; display:none; flex-direction:column; }
    .pdf-viewer-modal.show { display:flex; }
    .pdf-viewer-hdr { padding:15px 25px; background:#1a1a1a; display:flex; justify-content:space-between; align-items:center; color:#fff; }
    .pdf-viewer-hdr h3 { margin:0; font-size:1.1rem; }
    .pdf-viewer-body { flex:1; background:#525659; }
    .pdf-viewer-body iframe { width:100%; height:100%; border:none; }

    .category-group-header {
        grid-column: 1 / -1;
        background: #fdf2f2;
        padding: 12px 20px;
        border-radius: 12px;
        margin: 25px 0 10px 0;
        color: #5a0202;
        font-weight: 800;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 12px;
        border-right: 5px solid #5a0202;
    }
    .pdf-close { background:none; border:none; color:#fff; font-size:2rem; cursor:pointer; line-height:1; }
    .pdf-close:hover { color:#ff5252; }

    /* Tabs System */
    .lib-tabs { display: flex; gap: 5px; margin-bottom: 25px; border-bottom: 2px solid #eee; padding: 0 10px; }
    .lib-tab { 
        padding: 12px 25px; cursor: pointer; font-weight: 800; color: #666; 
        border-radius: 12px 12px 0 0; transition: .2s; position: relative; bottom: -2px;
        border: 2px solid transparent; border-bottom: none;
    }
    .lib-tab:hover { color: #5a0202; background: #fdf2f2; }
    .lib-tab.active { color: #5a0202; border-color: #eee; background: #fff; border-bottom: 2px solid #fff; }
    
    /* PDF Library Styles */
    .pdf-lib-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; padding: 20px 0; }
    .pdf-file-card { background: #fff; border-radius: 14px; border: 1px solid #eee; padding: 20px; text-align: center; transition: .2s; position: relative; display: flex; flex-direction: column; align-items: center; gap: 12px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
    .pdf-file-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); border-color: #5a020233; }
    .pdf-file-icon { width: 60px; height: 60px; color: #d32f2f; background: #fff5f5; border-radius: 12px; display: flex; align-items: center; justify-content: center; transition: .2s; }
    .pdf-file-card:hover .pdf-file-icon { background: #d32f2f; color: #fff; }
    .pdf-file-name { font-size: 0.88rem; font-weight: 700; color: #333; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 2.5rem; word-break: break-all; }
    .pdf-file-meta { font-size: 0.72rem; color: #999; font-weight: 600; }
    .pdf-file-actions { position: absolute; top: 10px; right: 10px; display: none; }
    .pdf-file-card:hover .pdf-file-actions { display: block; }
    .pdf-del-btn { width: 28px; height: 28px; border-radius: 50%; background: rgba(211,47,47,0.1); color: #d32f2f; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: .2s; }
    .pdf-del-btn:hover { background: #d32f2f; color: #fff; }

    .pdf-lib-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; background: #fdf2f2; padding: 15px 20px; border-radius: 12px; }
    .pdf-search-wrapper { flex: 1; min-width: 280px; position: relative; }
    .pdf-search-wrapper input { width: 100%; padding: 11px 40px 11px 15px; border: 1px solid #ddd; border-radius: 10px; font-size: 0.9rem; outline: none; }
    .pdf-search-wrapper input:focus { border-color: #5a0202; }
    .pdf-search-wrapper svg { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #999; }
    
    .tab-content { display: none; animation: fadeIn 0.3s ease; }
    .tab-content.active { display: block; }
    
    .kurd-lib-container { height: calc(100vh - 250px); min-height: 600px; background: #fff; border-radius: 16px; border: 1px solid #eee; overflow: hidden; position: relative; }
    .kurd-lib-iframe { width: 100%; height: 100%; border: none; }
    
    .offline-msg { 
        position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; 
        gap: 24px; background: #fff; z-index: 10; padding: 40px; text-align: center;
        background: radial-gradient(circle at center, #fff 0%, #fef8f8 100%);
    }
    .offline-msg .icon-box {
        width: 120px; height: 120px; background: #fff5f5; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        box-shadow: 0 10px 25px rgba(211,47,47,0.1); margin-bottom: 10px; border: 4px solid #fff;
    }
    .offline-msg svg { width: 60px; height: 60px; color: #ff5252; }
    .offline-msg h3 { color: #5a0202; font-size: 1.8rem; margin: 0; font-weight: 800; }
    .offline-msg p { color: #666; max-width: 450px; line-height: 1.8; font-size: 1rem; }

    /* Fullscreen Mode */
    body.kurd-lib-fullscreen .page-hdr,
    body.kurd-lib-fullscreen .lib-tabs,
    body.kurd-lib-fullscreen #ourBooksTab {
        display: none !important;
    }
    body.kurd-lib-fullscreen .kurd-lib-container {
        position: fixed;
        top: var(--topbar-height);
        right: var(--sidebar-width);
        left: 0;
        bottom: 0;
        height: auto;
        min-height: auto;
        z-index: 500;
        border-radius: 0;
        border: none;
        margin: 0;
    }
    body.kurd-lib-fullscreen .lib-main { padding: 0 !important; }

    @media (max-width: 720px) {
        .search-row,
        .search-group { flex-direction:column; align-items:stretch; }
        .category-combo { width:100%; flex-basis:auto; }
        .btn-action { justify-content:center; }
    }
</style>

<!-- ─────────────── PAGE HEADER ─────────────── -->
<div class="page-hdr">
    <h2>کتێبەکان</h2>
    <button class="lib-btn lib-btn-primary" onclick="openBookModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        زیادکردنی کتێب
    </button>
</div>

<div class="lib-tabs">
    <div class="lib-tab active" onclick="switchTab('ourBooks', this)">کتێبەکانی ئێمە</div>
    <div class="lib-tab" onclick="switchTab('pdfLib', this)">کتێبە PDF-ەکان</div>
    <div class="lib-tab" onclick="switchTab('kurdishLib', this)">کتێبخانەی کوردی</div>
</div>

<div id="ourBooksTab" class="tab-content active">

<!-- ─────────────── FILTERS ─────────────── -->
<div class="filters-card">
    <div class="search-row">
        <div class="search-group">
            <div class="search-input-wrapper">
                <input type="text" id="filter_search" placeholder="ناوی کتێب یان کۆد بنووسە بۆ گەڕان..." onkeyup="if(event.key==='Enter')loadBooks(1)">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;height:20px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
            <div class="category-combo" id="categoryCombo">
                <input type="hidden" id="filter_category" value="">
                <button type="button" class="category-combo-btn" onclick="toggleCategoryCombo()" aria-haspopup="listbox" aria-expanded="false">
                    <span class="category-combo-label" id="categoryComboLabel">هەموو بەشەکان</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="category-combo-menu">
                    <input type="text" class="category-combo-search" id="categoryComboSearch" placeholder="گەڕان لە بەشەکان..." autocomplete="off">
                    <div class="category-combo-list" id="categoryComboList" role="listbox"></div>
                </div>
            </div>
        </div>
        
        <button class="btn-action btn-toggle" id="filterToggle" onclick="toggleFilters()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            فلتەری زیاتر
        </button>
        
        <button class="btn-action btn-clear" onclick="clearFilters()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            پاککردنەوە
        </button>
    </div>

    <div class="adv-filters" id="advFilters">
        <div class="adv-grid">
            <div class="lib-field">
                <label>نوسەر</label>
                <select id="filter_author">
                    <option value="">هەموو</option>
                    <?php foreach ($authors as $a): ?>
                    <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lib-field">
                <label>وەرگێڕ</label>
                <select id="filter_translator">
                    <option value="">هەموو</option>
                    <?php foreach ($translators as $t): ?>
                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lib-field">
                <label>ساڵ</label>
                <select id="filter_year">
                    <option value="">هەموو</option>
                    <?php foreach ($years as $y): ?>
                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lib-field" style="display:flex; align-items:flex-end; padding-bottom:5px;">
                <label class="lib-switch" style="margin-top:0;">
                    <input type="checkbox" id="group_by_category" onchange="loadBooks(1)">
                    <span class="slider"></span>
                    <span class="label">گرووپکردن بەپێی بەش</span>
                </label>
            </div>
        </div>
    </div>
</div>

<!-- ─────────────── GRID ─────────────── -->
<div class="books-container" style="padding:10px 0;">
    <div id="booksGrid" class="books-grid">
        <div style="grid-column:1/-1; text-align:center; padding:100px; color:#999;">چاوەڕوانبە...</div>
    </div>
    <div class="lib-pagination" id="paginationContainer" style="margin-top:30px;"></div>
    </div> <!-- End of books-container -->
</div> <!-- End of ourBooksTab -->

<div id="pdfLibTab" class="tab-content">
    <div class="pdf-lib-hdr">
        <div class="pdf-search-wrapper">
            <input type="text" id="pdf_lib_search" placeholder="گەڕان لە ناو فایلی PDF-ەکان..." onkeyup="filterPdfFiles()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>
        <button class="lib-btn lib-btn-primary" onclick="openPdfUploadModal()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            زیادکردنی PDF
        </button>
    </div>
    
    <div id="pdfLibGrid" class="pdf-lib-grid">
        <div style="grid-column:1/-1; text-align:center; padding:100px; color:#999;">چاوەڕوانبە...</div>
    </div>
</div>

<div id="kurdishLibTab" class="tab-content">
    <div class="kurd-lib-container" id="kurdLibContainer">
        <!-- Content loaded via JS -->
    </div>
</div>

<!-- ═══════════════ ADD/EDIT MODAL ═══════════════ -->
<div class="lib-overlay" id="bookOverlay" onclick="closeBookModal()"></div>
<div class="lib-modal" id="bookModal" style="width:min(960px,calc(100% - 30px));">
    <div class="lib-modal-header">
        <h3 id="bookModalTitle">زیادکردنی کتێبی نوێ</h3>
        <button class="lib-modal-close" onclick="closeBookModal()">&times;</button>
    </div>
    <div class="lib-modal-body">
        <form id="bookForm">
            <input type="hidden" id="bookId">
            <div class="modal-form">
                <div class="lib-field full">
                    <label>ناوی کتێب <span style="color:red">*</span></label>
                    <div class="smart-select" data-type="title">
                        <input class="smart-input" id="bk_title" type="text" placeholder="ناوی کتێب بنووسە..." autocomplete="off" required>
                        <div class="dd-menu"><div class="dd-items"></div></div>
                    </div>
                </div>

                <div class="lib-field">
                    <label>نوسەر</label>
                    <div class="smart-select" data-type="author">
                        <input class="smart-input" type="text" placeholder="ناوی نوسەر بنووسە..." autocomplete="off">
                        <div class="dd-menu"><div class="dd-items"></div></div>
                        <input type="hidden" id="bk_author_id">
                    </div>
                </div>

                <div class="lib-field">
                    <label>وەرگێڕ</label>
                    <div class="smart-select" data-type="translator">
                        <input class="smart-input" type="text" placeholder="ناوی وەرگێڕ بنووسە..." autocomplete="off">
                        <div class="dd-menu"><div class="dd-items"></div></div>
                        <input type="hidden" id="bk_translator_id">
                    </div>
                </div>

                <div class="lib-field">
                    <label>بەش <span style="color:red">*</span></label>
                    <div class="smart-select" data-type="category">
                        <input class="smart-input" type="text" placeholder="ناوی بەش بنووسە..." autocomplete="off">
                        <div class="dd-menu">
                            <div class="dd-items"></div>
                            <div class="dd-add-btn" onclick="promptAddCategory()">+ زیادکردنی بەشی نوێ</div>
                        </div>
                        <input type="hidden" id="bk_category_id">
                    </div>
                </div>

                <div class="lib-field">
                    <label>دانە <span style="color:red">*</span></label>
                    <input type="number" id="bk_quantity" value="1" min="1">
                </div>

                <div class="lib-field">
                    <label>کۆدی کتێب <span style="color:red">*</span></label>
                    <input type="text" id="bk_code" lang="en" placeholder="D-123..." style="direction:ltr;text-align:left;font-family:monospace;font-weight:bold;" required>
                </div>

                <div class="lib-field">
                    <label>کۆدی ڕەف</label>
                    <input type="text" id="bk_shelf" lang="en" placeholder="D-12..." style="direction:ltr;text-align:left;font-family:monospace;font-weight:bold;">
                </div>
                <div id="full_code_preview" style="grid-column: 1 / -1; background: #fdf2f2; padding: 10px; border-radius: 8px; border: 1px dashed #5a0202; font-weight: bold; color: #5a0202; text-align: center; margin-top: 5px; display: none;"></div>

                <div class="lib-field">
                    <label>ساڵی بڵاوکردنەوە</label>
                    <input type="number" id="bk_year" min="1800" max="2030" placeholder="ساڵ">
                </div>

                <div class="lib-field">
                    <label>زمان</label>
                    <select id="bk_language" class="smart-input">
                        <option value="کوردی">کوردی</option>
                        <option value="عەرەبی">عەرەبی</option>
                        <option value="ئینگلیزی">ئینگلیزی</option>
                        <option value="فارسی">فارسی</option>
                        <option value="تورکی">تورکی</option>
                        <option value="فەڕەنسی">فەڕەنسی</option>
                        <option value="ئەڵمانی">ئەڵمانی</option>
                    </select>
                </div>

                <div class="lib-field" style="display:flex; align-items:flex-end; padding-bottom:5px;">
                    <label class="lib-switch" style="margin-top:0;">
                        <input type="checkbox" id="bk_sync_pub">
                        <span class="slider"></span>
                        <span class="label">زیادکردن بۆ بەشی بڵاوکراوەکان</span>
                    </label>
                </div>

                <div class="lib-field full">
                    <label>زانیاری لەسەر کتێب (باسی کتێب)</label>
                    <textarea id="bk_description" rows="3" class="smart-input" style="resize:vertical;" placeholder="باسێکی کورتی کتێبەکە بنووسە..."></textarea>
                </div>

                <!-- Media & Files -->
                <div class="full" style="border-top:1px dashed #eee; padding-top:20px; margin-top:10px;">
                    <h4 style="color:#5a0202; margin-bottom:15px; font-weight:800;">میدیا و فایلەکان</h4>
                    <div style="display:grid; grid-template-columns: 280px 1fr; gap:25px;">
                        <!-- Cover Image -->
                        <div>
                            <label style="display:block; margin-bottom:8px; font-weight:700;">وێنەی بەرگ</label>
                             <div class="cover-preview-box" id="coverPreview">
                                <span>وێنە لێرە دەردەکەوێت</span>
                                <button type="button" class="remove-img-btn" id="removeImgBtn" onclick="removeCoverSelection()" style="display:none;" title="سڕینەوەی وێنە">&times;</button>
                            </div>
                            <input type="hidden" id="bk_delete_cover" value="0">
                            <div class="file-input-wrapper">
                                <label class="file-btn">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                    هەڵبژاردنی وێنە
                                    <input type="file" id="bk_cover" accept="image/*" onchange="previewCover(this)">
                                </label>
                            </div>
                            <div id="cover_file_name" class="file-info"></div>
                        </div>

                        <!-- PDF File/Link -->
                        <div>
                            <label style="display:block; margin-bottom:8px; font-weight:700;">فایلی PDF</label>
                            <div class="pdf-type-toggle">
                                <button type="button" class="pdf-type-btn active" id="btn_pdf_upload" onclick="setPdfType('upload')">بارکردنی فایل</button>
                                <button type="button" class="pdf-type-btn" id="btn_pdf_link" onclick="setPdfType('link')">بەستەری دەرەکی</button>
                            </div>

                            <div id="pdf_upload_area">
                                <div class="file-input-wrapper">
                                    <label class="file-btn" style="padding: 45px 20px;">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 12 15 15"/></svg>
                                        فایلی PDF هەڵبژێرە بۆ بارکردن
                                        <input type="file" id="bk_pdf_file" accept=".pdf" onchange="updatePdfInfo(this)">
                                    </label>
                                </div>
                                <div id="pdf_file_name" class="file-info"></div>
                            </div>

                            <div id="pdf_link_area" style="display:none;">
                                <div class="lib-field">
                                    <input type="text" id="bk_pdf_link" placeholder="بەستەری PDF بنووسە (بۆ نموونە: https://example.com/book.pdf)" style="direction:ltr; text-align:left;">
                                </div>
                            </div>
                            
                            <div id="existing_pdf_info" style="margin-top:10px; display:none;">
                                <span style="font-size:0.8rem; background:#fff3e0; color:#e65100; padding:4px 10px; border-radius:6px; font-weight:600;">ئەم کتێبە فایلێکی بارکراوی هەیە</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <div class="lib-modal-footer">
        <button class="lib-btn lib-btn-gold" onclick="saveBook(true)">پاشەکەوتکردن و نوێ</button>
        <button class="lib-btn lib-btn-primary" onclick="saveBook(false)">پاشەکەوتکردن</button>
        <button class="lib-btn" style="background:#eee;color:#555;" onclick="closeBookModal()">پاشگەزبوونەوە</button>
    </div>
</div>

<!-- ═══════════════ VIEW MODAL ═══════════════ -->
<div class="lib-overlay" id="viewOverlay" onclick="closeViewModal()"></div>
<div class="lib-modal" id="viewModal" style="width:min(900px,calc(100% - 30px));">
    <div class="lib-modal-header">
        <h3>زانیارییەکانی کتێب</h3>
        <button class="lib-modal-close" onclick="closeViewModal()">&times;</button>
    </div>
    <div class="lib-modal-body" id="viewModalContent" style="min-height:200px;"></div>
    <div class="lib-modal-footer">
        <button class="lib-btn" style="background:#eee;color:#555;" onclick="closeViewModal()">داخستن</button>
    </div>
</div>

<!-- ═══════════════ PDF VIEWER OVERLAY ═══════════════ -->
<div class="pdf-viewer-modal" id="pdfViewer">
    <div class="pdf-viewer-hdr">
        <h3 id="pdfViewerTitle">بینینی کتێب</h3>
        <button class="pdf-close" onclick="closePdfViewer()">&times;</button>
    </div>
    <div class="pdf-viewer-body">
        <iframe id="pdfFrame" src=""></iframe>
    </div>
</div>

<!-- ═══════════════ PDF UPLOAD MODAL ═══════════════ -->
<div class="lib-overlay" id="pdfUploadOverlay" onclick="closePdfUploadModal()"></div>
<div class="lib-modal" id="pdfUploadModal" style="width:min(500px,calc(100% - 30px));">
    <div class="lib-modal-header">
        <h3>زیادکردنی فایلی PDF</h3>
        <button class="lib-modal-close" onclick="closePdfUploadModal()">&times;</button>
    </div>
    <div class="lib-modal-body">
        <form id="pdfUploadForm">
            <div class="file-input-wrapper" style="margin-top:10px;">
                <label class="file-btn" style="padding: 60px 20px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 12 15 15"/></svg>
                    فایلێک هەڵبژێرە بۆ بارکردن
                    <input type="file" id="lib_pdf_file" accept=".pdf" onchange="document.getElementById('lib_pdf_name').textContent = this.files[0].name">
                </label>
            </div>
            <div id="lib_pdf_name" class="file-info" style="text-align:center; margin-top:10px; font-weight:700; color:#5a0202;"></div>
        </form>
    </div>
    <div class="lib-modal-footer">
        <button class="lib-btn lib-btn-primary" onclick="uploadLibPdf()">بارکردن</button>
        <button class="lib-btn" style="background:#eee;color:#555;" onclick="closePdfUploadModal()">پاشگەزبوونەوە</button>
    </div>
</div>

<script>
const ITEMS_PER_PAGE = 30;
let currentPage = 1;

const ALL_AUTHORS     = <?php echo json_encode($authors); ?>;
const ALL_TRANSLATORS = <?php echo json_encode($translators); ?>;
const ALL_CATEGORIES  = <?php echo json_encode($categories); ?>;
const ALL_TITLES      = <?php echo json_encode($unique_titles); ?>;

function toggleCategoryCombo(forceOpen = null) {
    const combo = document.getElementById('categoryCombo');
    const btn = combo?.querySelector('.category-combo-btn');
    const search = document.getElementById('categoryComboSearch');
    if (!combo || !btn) return;

    const shouldOpen = forceOpen === null ? !combo.classList.contains('open') : forceOpen;
    combo.classList.toggle('open', shouldOpen);
    btn.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

    if (shouldOpen) {
        renderCategoryComboOptions(search?.value || '');
        setTimeout(() => search?.focus(), 0);
    }
}

function renderCategoryComboOptions(query = '') {
    const list = document.getElementById('categoryComboList');
    const selectedValue = document.getElementById('filter_category')?.value || '';
    if (!list) return;

    const normalizedQuery = query.trim().toLowerCase();
    const matchedCategories = ALL_CATEGORIES.filter(c => (c.name || '').toLowerCase().includes(normalizedQuery));
    const options = normalizedQuery
        ? matchedCategories
        : [{ id: '', name: 'هەموو بەشەکان' }].concat(matchedCategories);

    list.innerHTML = '';
    if (!options.length) {
        list.innerHTML = '<div class="category-combo-empty">هیچ بەشێک نەدۆزرایەوە</div>';
        return;
    }

    options.forEach(option => {
        const item = document.createElement('div');
        item.className = 'category-combo-option' + (String(option.id) === String(selectedValue) ? ' active' : '');
        const displayName = option.shelf_code ? `${option.name} (${option.shelf_code})` : option.name;
        item.textContent = displayName;
        item.setAttribute('role', 'option');
        item.addEventListener('click', () => selectFilterCategory(option.id, option.name));
        list.appendChild(item);
    });
}

function selectFilterCategory(id, name) {
    const hidden = document.getElementById('filter_category');
    const label = document.getElementById('categoryComboLabel');
    const search = document.getElementById('categoryComboSearch');
    if (!hidden || !label) return;

    hidden.value = id || '';
    label.textContent = name || 'هەموو بەشەکان';
    if (search) search.value = '';
    toggleCategoryCombo(false);
    loadBooks(1);
}

function syncCategoryComboLabel() {
    const hidden = document.getElementById('filter_category');
    const label = document.getElementById('categoryComboLabel');
    if (!hidden || !label) return;

    const selected = ALL_CATEGORIES.find(c => String(c.id) === String(hidden.value));
    label.textContent = selected ? selected.name : 'هەموو بەشەکان';
    renderCategoryComboOptions(document.getElementById('categoryComboSearch')?.value || '');
}

function highlightText(text, term) {
    if (!term || !text) return text;
    const safeText = text.toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[m]));
    const regex = new RegExp(`(${term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return safeText.replace(regex, '<mark style="background:#ffeb3b; color:#000; padding:0 2px; border-radius:2px; font-weight:bold;">$1</mark>');
}

// ── Tab System ──────────────────────────────────────────────────
function switchTab(tabId, el = null) {
    document.querySelectorAll('.lib-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    const content = document.getElementById(tabId + 'Tab');
    if (content) content.classList.add('active');
    
    if (el) {
        el.classList.add('active');
    } else {
        const target = Array.from(document.querySelectorAll('.lib-tab')).find(t => t.getAttribute('onclick')?.includes(`'${tabId}'`));
        if (target) target.classList.add('active');
    }
    
    if (tabId === 'kurdishLib') {
        renderKurdishLib();
    }
    if (tabId === 'pdfLib') {
        loadPdfFiles();
    }
}

// ── PDF Library ────────────────────────────────────────────────
let allPdfFiles = [];

async function loadPdfFiles() {
    const grid = document.getElementById('pdfLibGrid');
    grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:100px; color:#999;">کەمێک چاوەڕوانبە...</div>';
    
    try {
        const res = await fetch('pdf_library_api.php?action=list').then(r => r.json());
        if (res.success) {
            allPdfFiles = res.files || [];
            renderPdfFiles(allPdfFiles);
        } else {
            grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:100px; color:red;">هەڵە لە وەرگرتنی فایلەکان</div>';
        }
    } catch (e) {
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:100px; color:red;">پەیوەندی سەرنەکەوت</div>';
    }
}

function renderPdfFiles(files) {
    const grid = document.getElementById('pdfLibGrid');
    if (!files.length) {
        grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:100px; color:#999;">هیچ فایلێک نییە</div>';
        return;
    }

    grid.innerHTML = files.map(f => {
        const sizeMb = (f.size / 1024 / 1024).toFixed(2);
        const dateStr = new Date(f.date * 1000).toLocaleDateString('ku-IQ');
        const viewUrl = `view_pdf.php?file=${encodeURIComponent(f.name)}`;
        
        return `
        <div class="pdf-file-card" onclick="viewPdf('${viewUrl}', '${f.name.replace(/'/g, "\\'")}')">
            <div class="pdf-file-actions" onclick="event.stopPropagation()">
                <button class="pdf-del-btn" onclick="deleteLibPdf('${f.name.replace(/'/g, "\\'")}')" title="سڕینەوە">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
            </div>
            <div class="pdf-file-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:32px;height:32px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            </div>
            <div class="pdf-file-name" title="${f.name}">${f.name}</div>
            <div class="pdf-file-meta">${sizeMb} MB • ${dateStr}</div>
        </div>`;
    }).join('');
}

function filterPdfFiles() {
    const q = document.getElementById('pdf_lib_search').value.toLowerCase();
    const filtered = allPdfFiles.filter(f => f.name.toLowerCase().includes(q));
    renderPdfFiles(filtered);
}

function openPdfUploadModal() {
    document.getElementById('pdfUploadModal').classList.add('show');
    document.getElementById('pdfUploadOverlay').classList.add('show');
    document.getElementById('pdfUploadForm').reset();
    document.getElementById('lib_pdf_name').textContent = '';
}

function closePdfUploadModal() {
    document.getElementById('pdfUploadModal').classList.remove('show');
    document.getElementById('pdfUploadOverlay').classList.remove('show');
}

async function uploadLibPdf() {
    const fileInput = document.getElementById('lib_pdf_file');
    if (!fileInput.files.length) {
        libToast('تکایە فایلێک هەڵبژێرە', 'error');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'upload');
    fd.append('pdf_file', fileInput.files[0]);

    try {
        const res = await fetch('pdf_library_api.php', { method: 'POST', body: fd }).then(r => r.json());
        libToast(res.message, res.success ? 'success' : 'error');
        if (res.success) {
            closePdfUploadModal();
            loadPdfFiles();
        }
    } catch (e) {
        libToast('پەیوەندی سەرنەکەوت', 'error');
    }
}

async function deleteLibPdf(filename) {
    if (!confirm('ئایا دڵنیایت لە سڕینەوەی ئەم فایلە؟')) return;
    
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('filename', filename);
    
    try {
        const res = await fetch('pdf_library_api.php', { method: 'POST', body: fd }).then(r => r.json());
        libToast(res.message, res.success ? 'success' : 'error');
        if (res.success) loadPdfFiles();
    } catch (e) {
        libToast('سڕینەوە سەرنەکەوت', 'error');
    }
}

function renderKurdishLib() {
    const container = document.getElementById('kurdLibContainer');
    if (!navigator.onLine) {
        container.innerHTML = `
            <div class="offline-msg">
                <div class="icon-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
                        <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/>
                        <path d="M10.71 5.05A16 16 0 0 1 22.58 9"/>
                        <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/>
                        <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                </div>
                <h3>هێڵی ئینتەرنێت بەردەست نییە</h3>
                <p>ئەم بەشەی کتێبخانە (ماڵپەڕی کتێبستان) پێویستی بە هێڵی ئینتەرنێت هەیە بۆ کارکردن. تکایە پەیوەندی هێڵەکەت بپشکنە و دووبارە هەوڵ بدەوە.</p>
                <div style="display:flex; gap:12px;">
                    <button class="lib-btn lib-btn-primary" onclick="renderKurdishLib()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        دووبارە پشکنینەوە
                    </button>
                </div>
            </div>`;
    } else {
        container.innerHTML = `
            <div style="height:100%; display:flex; flex-direction:column;">
                <div style="padding:15px 25px; background:#fdf2f2; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <h4 style="margin:0; color:#5a0202; font-weight:800;">ماڵپەڕی کتێبستان</h4>
                        <p style="margin:5px 0 0 0; font-size:0.8rem; color:#666;">ئەگەر ماڵپەڕەکە لە خوارەوە پیشان نەدرا، تکایە دوگمەی لای چەپ بەکاربهێنە.</p>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button class="lib-btn" onclick="toggleKurdLibFullscreen()" id="fsBtn" style="background:#fff; border:1px solid #ddd; display:flex; align-items:center; gap:8px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                            فراوانکردن
                        </button>
                        <a href="https://www.ktebstan.net/" target="_blank" class="lib-btn lib-btn-primary" style="text-decoration:none; display:flex; align-items:center; gap:8px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                            بچۆ ناو ماڵپەڕ (پەڕەی نوێ)
                        </a>
                    </div>
                </div>
                <div style="flex:1; position:relative; background:#fff;">
                    <iframe src="https://www.ktebstan.net/" class="kurd-lib-iframe"></iframe>
                </div>
            </div>`;
    }
}

function toggleKurdLibFullscreen() {
    const isFS = document.body.classList.toggle('kurd-lib-fullscreen');
    const btn = document.getElementById('fsBtn');
    if (isFS) {
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><path d="M8 3v5H3M21 8h-5V3M3 16h5v5M16 21v-5h5"/></svg> بچووککردنەوە`;
    } else {
        btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg> فراوانکردن`;
    }
}

// ── Filters toggle ──────────────────────────────────────────────
function toggleFilters() {
    const pnl = document.getElementById('advFilters');
    const btn = document.getElementById('filterToggle');
    pnl.classList.toggle('show');
    btn.classList.toggle('open');
}

function clearFilters() {
    document.getElementById('filter_search').value = '';
    document.getElementById('filter_category').value = '';
    syncCategoryComboLabel();
    document.getElementById('filter_author').value = '';
    document.getElementById('filter_translator').value = '';
    document.getElementById('filter_year').value = '';
    const groupBy = document.getElementById('group_by_category');
    if (groupBy) groupBy.checked = false;
    loadBooks(1);
}

function filterByAuthor(authorId) {
    if (!authorId) return;
    const authorSelect = document.getElementById('filter_author');
    if (authorSelect) {
        authorSelect.value = authorId;
        const pnl = document.getElementById('advFilters');
        if (!pnl.classList.contains('show')) toggleFilters();
        loadBooks(1);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function filterByCategory(catId) {
    if (!catId) return;
    const catSelect = document.getElementById('filter_category');
    if (catSelect) {
        catSelect.value = catId;
        syncCategoryComboLabel();
        const pnl = document.getElementById('advFilters');
        if (!pnl.classList.contains('show')) toggleFilters();
        loadBooks(1);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// ── Load books ──────────────────────────────────────────────────
function loadBooks(page = 1) {
    currentPage = page;
    const grid = document.getElementById('booksGrid');
    grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:100px; color:#999;">کەمێک چاوەڕوانبە...</div>';

    const params = new URLSearchParams({
        action: 'filter_books',
        category_id:   document.getElementById('filter_category')?.value   || '',
        author_id:     document.getElementById('filter_author')?.value      || '',
        translator_id: document.getElementById('filter_translator')?.value  || '',
        year:          document.getElementById('filter_year')?.value        || '',
        search:        document.getElementById('filter_search')?.value      || '',
        group_by:      document.getElementById('group_by_category')?.checked ? 'category' : '',
        page, limit: ITEMS_PER_PAGE
    });

    fetch('fetch_data.php?' + params)
        .then(r => r.json())
        .then(data => {
            const books = data.books || [];
            if (!books.length) {
                grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:100px; color:#999;">هیچ کتێبێک نەدۆزرایەوە</div>';
                document.getElementById('paginationContainer').innerHTML = '';
                return;
            }

            const isGrouped = document.getElementById('group_by_category')?.checked;
            let lastCategory = null;
            
            grid.innerHTML = books.map((b) => {
                let header = '';
                if (isGrouped && b.category_name !== lastCategory) {
                    lastCategory = b.category_name;
                    header = `
                        <div class="category-group-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:22px;height:22px;"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                            ${b.category_name || 'بەش دیاری نەکراوە'}
                        </div>`;
                }

                const viewSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
                const editSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>`;
                const delSvg  = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2 2v2"/></svg>`;
                const printSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>`;
                
                const pdfBadge = (b.pdf_file || b.pdf_link) ? `<div class="pdf-indicator"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:12px;height:12px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> PDF</div>` : '';
                
                const coverImg = b.cover_image 
                    ? `<img src="${b.cover_image}" alt="${b.title}">`
                    : `<img src="assets/imgae/book-logo.png" alt="Default Cover" style="opacity:0.6; padding:40px; object-fit:contain;">`;

                const searchTerm = document.getElementById('filter_search')?.value || '';
                const displayTitle = highlightText(b.title, searchTerm);
                const displayAuthor = highlightText(b.author_name || 'نوسەر دیاری نەکراوە', searchTerm);
                const displayCategory = highlightText(b.category_name || 'بەش دیاری نەکراوە', searchTerm);
                const combinedCode = (b.shelf_code && b.book_code) ? `${b.shelf_code}-${b.book_code}` : (b.book_code || '-');
                const displayCode = highlightText(combinedCode, searchTerm);

                return `
                ${header}
                <div class="book-card">
                    ${pdfBadge}
                    <div class="book-actions">
                        <button class="btn-circle" onclick="viewBook(${b.id})" title="بینین">${viewSvg}</button>
                        <button class="btn-circle" onclick="openBookModal(${b.id})" title="دەستکاری">${editSvg}</button>
                        <button class="btn-circle" onclick="window.open('print_sticker.php?id=${b.id}', '_blank')" title="چاپی ستیکەر">${printSvg}</button>
                        <button class="btn-circle del" onclick="deleteBook(${b.id})" title="سڕینەوە">${delSvg}</button>
                    </div>
                    <div class="book-cover-area" onclick="viewBook(${b.id})" style="cursor:pointer;">
                        ${coverImg}
                    </div>
                    <div class="book-details">
                        <div class="book-title" title="${b.title}">${displayTitle}</div>
                        <div class="book-category" onclick="filterByCategory(${b.category_id})">${displayCategory}</div>
                        <div class="book-author">
                            ${b.author_name 
                                ? `<span onclick="filterByAuthor(${b.author_id})" style="cursor:pointer; transition:.2s;" onmouseover="this.style.color='#5a0202'; this.style.textDecoration='underline'" onmouseout="this.style.color=''; this.style.textDecoration=''">${displayAuthor}</span>` 
                                : displayAuthor}
                        </div>
                        <div class="book-meta">
                            <span class="book-code-badge">${displayCode}</span>
                            <span class="book-qty-badge">${b.quantity || 0} دانە</span>
                        </div>
                    </div>
                </div>`;
            }).join('');

            renderPagination(data.total_pages, data.page);
        })
        .catch(() => {
            grid.innerHTML = '<div style="grid-column:1/-1; text-align:center; padding:100px; color:red;">هەڵە لە بارکردنەکان</div>';
        });
}

// ── Pagination ──────────────────────────────────────────────────
function renderPagination(totalPages, page) {
    const c = document.getElementById('paginationContainer');
    if (!c || totalPages <= 1) { if(c) c.innerHTML = ''; return; }

    let h = `<button class="lib-page-btn${page <= 1 ? ' disabled' : ''}" onclick="if(${page}>1)loadBooks(${page-1})">پێشێ</button>`;
    let s = Math.max(1, page - 2), e = Math.min(totalPages, s + 4);
    if (e - s < 4) s = Math.max(1, e - 4);
    for (let i = s; i <= e; i++)
        h += `<button class="lib-page-btn${i===page?' active':''}" onclick="loadBooks(${i})">${i}</button>`;
    h += `<button class="lib-page-btn${page >= totalPages ? ' disabled' : ''}" onclick="if(${page}<${totalPages})loadBooks(${page+1})">دواتر</button>`;
    c.innerHTML = h;
}

// ── Delete ──────────────────────────────────────────────────────
async function deleteBook(id) {
    if (!confirm('ئایا دڵنیایت لە سڕینەوەی ئەم کتێبە؟')) return;
    const fd = new FormData();
    fd.append('action', 'delete'); fd.append('id', id);
    const res = await fetch('save_book.php', { method:'POST', body:fd }).then(r => r.json()).catch(() => ({success:false}));
    libToast(res.message || (res.success ? 'سڕایەوە' : 'هەڵە'), res.success ? 'success' : 'error');
    if (res.success) loadBooks(currentPage);
}

// ── View Modal ──────────────────────────────────────────────────
function openViewModal() { document.getElementById('viewModal').classList.add('show'); document.getElementById('viewOverlay').classList.add('show'); }
function closeViewModal() { document.getElementById('viewModal').classList.remove('show'); document.getElementById('viewOverlay').classList.remove('show'); }

async function viewPdf(url, title) {
    const frame = document.getElementById('pdfFrame');
    document.getElementById('pdfViewerTitle').textContent = 'بینینی: ' + title;
    document.getElementById('pdfViewer').classList.add('show');
    
    frame.src = 'about:blank';
    
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error('Network response was not ok');
        
        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Error from server');

        // Convert base64 to blob
        const binaryString = window.atob(result.data);
        const len = binaryString.length;
        const bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        
        const blob = new Blob([bytes], { type: 'application/pdf' });
        const blobUrl = URL.createObjectURL(blob);
        frame.src = blobUrl;
    } catch (e) {
        console.error("Error loading PDF:", e);
        alert("هەڵە لە کردنەوەی فایلەکە: " + e.message);
    }
}

function closePdfViewer() {
    document.getElementById('pdfViewer').classList.remove('show');
    if (document.getElementById('pdfFrame').src.startsWith('blob:')) {
        URL.revokeObjectURL(document.getElementById('pdfFrame').src);
    }
    document.getElementById('pdfFrame').src = '';
}

function viewBook(id) {
    const body = document.getElementById('viewModalContent');
    body.innerHTML = '<div style="text-align:center;padding:60px;color:#999;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:50px;height:50px;margin:0 auto 15px;display:block;opacity:0.3;"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>کەمێک چاوەڕوانبە...</div>';
    openViewModal();
    fetch(`fetch_data.php?action=get_book&id=${id}`)
        .then(r => r.json())
        .then(b => {
            const searchTerm = document.getElementById('filter_search')?.value || '';
            const hl = (v) => highlightText(v, searchTerm);
            const val = (v) => (v && v !== 'null' && v !== 'undefined') ? v : '-';

            const coverHtml = b.cover_image
                ? `<img src="${b.cover_image}" alt="${b.title}" style="width:100%; border-radius:12px; box-shadow:0 8px 30px rgba(0,0,0,0.18); display:block;">`
                : `<div style="width:100%; aspect-ratio:2/3; background:linear-gradient(135deg,#f5e8e8,#eee); border-radius:12px; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 30px rgba(0,0,0,0.08);"><img src="assets/imgae/book-logo.png" style="width:70%; opacity:0.4; object-fit:contain;"></div>`;

            const pdfActionHtml = b.pdf_file
                ? `<button onclick="viewPdf('view_pdf.php?file=${encodeURIComponent(b.pdf_file)}', '${b.title}')" style="width:100%; margin-top:12px; padding:11px 0; border-radius:10px; background:linear-gradient(135deg,#5a0202,#8b1a1a); color:#fff; border:none; cursor:pointer; font-weight:700; font-size:0.9rem; display:flex; align-items:center; justify-content:center; gap:8px; transition:.2s; box-shadow:0 4px 12px rgba(90,2,2,0.3);" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                    بینینی PDF
                </button>`
                : b.pdf_link
                ? `<a href="${b.pdf_link}" target="_blank" style="width:100%; margin-top:12px; padding:11px 0; border-radius:10px; background:linear-gradient(135deg,#1565c0,#1976d2); color:#fff; border:none; cursor:pointer; font-weight:700; font-size:0.9rem; display:flex; align-items:center; justify-content:center; gap:8px; text-decoration:none; transition:.2s; box-shadow:0 4px 12px rgba(21,101,192,0.3);" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    بینینی بەستەر
                </a>`
                : `<div style="margin-top:12px; padding:10px; background:#f9f9f9; border-radius:10px; text-align:center; color:#aaa; font-size:0.82rem;">PDF بارنەکراوە</div>`;

            const printActionHtml = `<button onclick="window.open('print_sticker.php?id=${b.id}', '_blank')" style="width:100%; margin-top:12px; padding:11px 0; border-radius:10px; background:linear-gradient(135deg,#c8a951,#a88931); color:#fff; border:none; cursor:pointer; font-weight:700; font-size:0.9rem; display:flex; align-items:center; justify-content:center; gap:8px; transition:.2s; box-shadow:0 4px 12px rgba(200,169,81,0.3);" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                چاپی ستیکەر
            </button>`;

            const chip = (icon, label, val2) => val2 && val2 !== '-' ? `
                <div style="display:flex; align-items:flex-start; gap:12px; padding:12px 16px; background:#f9f9f9; border-radius:10px; border:1px solid #eee;">
                    <div style="width:32px; height:32px; background:#fdf2f2; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#5a0202" stroke-width="2" style="width:16px;height:16px;">${icon}</svg>
                    </div>
                    <div>
                        <div style="font-size:0.72rem; color:#999; font-weight:700; margin-bottom:2px;">${label}</div>
                        <div style="font-weight:700; color:#1a1a1a; font-size:0.95rem;">${val2}</div>
                    </div>
                </div>` : '';

            body.innerHTML = `
            <div style="display:grid; grid-template-columns:220px 1fr; gap:28px; align-items:start; padding:5px 0;">

                <!-- LEFT: Cover + PDF + Print -->
                <div>
                    ${coverHtml}
                    ${pdfActionHtml}
                    ${printActionHtml}
                </div>

                <!-- RIGHT: Details -->
                <div style="display:flex; flex-direction:column; gap:0;">
                    <!-- Title -->
                    <h2 style="font-size:1.5rem; font-weight:800; color:#1a1a1a; margin:0 0 6px 0; line-height:1.3;">${hl(val(b.title))}</h2>

                    <!-- Category badge -->
                    ${b.category_name ? `<span style="display:inline-block; background:#fdf2f2; color:#5a0202; font-size:0.75rem; font-weight:800; padding:4px 12px; border-radius:20px; margin-bottom:14px; border:1px solid #f5d0d0;">${hl(b.category_name)}</span>` : '<div style="margin-bottom:14px;"></div>'}

                    <!-- Description -->
                    ${b.description ? `
                    <div style="background:#fffdf5; border:1px solid #ffe082; border-radius:10px; padding:14px 16px; margin-bottom:14px; max-height:120px; overflow-y:auto;">
                        <div style="font-size:0.7rem; font-weight:700; color:#b8860b; margin-bottom:6px;">دەربارەی کتێب</div>
                        <div style="line-height:1.8; color:#444; font-size:0.88rem; white-space:pre-wrap;">${hl(b.description)}</div>
                    </div>` : ''}

                    <!-- Meta chips: author, translator, year only -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                        ${chip('<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>', 'نوسەر', hl(val(b.author_name)))}
                        ${chip('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>', 'وەرگێڕ', hl(val(b.translator_name)))}
                        ${chip('<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>', 'ساڵی چاپ', val(b.publish_year))}
                    </div>

                    <!-- Compact stats footer bar -->
                    <div style="background:#f8f8f8; border:1px solid #eee; border-radius:12px; padding:10px 16px; display:flex; align-items:center; justify-content:space-between; gap:6px; margin-top:auto;">
                        <div style="display:flex; align-items:center; gap:5px; flex:1; justify-content:center;">
                            <span style="font-size:0.68rem; color:#999; font-weight:700; white-space:nowrap;">کۆدی کتێب</span>
                            <span style="font-size:0.85rem; font-weight:800; color:#5a0202; font-family:monospace;">${val(b.book_code)}</span>
                        </div>
                        <div style="width:1px; height:28px; background:#ddd;"></div>
                        <div style="display:flex; align-items:center; gap:5px; flex:1; justify-content:center;">
                            <span style="font-size:0.68rem; color:#999; font-weight:700; white-space:nowrap;">کۆدی ڕەفە</span>
                            <span style="font-size:0.85rem; font-weight:800; color:#5a0202; font-family:monospace;">${val(b.shelf_code)}</span>
                        </div>
                        <div style="width:1px; height:28px; background:#ddd;"></div>
                        <div style="display:flex; align-items:center; gap:5px; flex:1; justify-content:center;">
                            <span style="font-size:0.68rem; color:#999; font-weight:700; white-space:nowrap;">کۆی گشتی</span>
                            <span style="font-size:0.85rem; font-weight:800; color:#2e7d32;">${val(b.quantity)}</span>
                        </div>
                        <div style="width:1px; height:28px; background:#ddd;"></div>
                        <div style="display:flex; align-items:center; gap:5px; flex:1; justify-content:center;">
                            <span style="font-size:0.68rem; color:#999; font-weight:700; white-space:nowrap;">بەردەست</span>
                            <span style="font-size:0.85rem; font-weight:800; color:#1565c0;">${val(b.available)}</span>
                        </div>
                    </div>

                    <!-- Entered by footer label -->
                    ${b.creator_name && b.creator_name !== 'null' ? `
                    <div style="margin-top:8px; text-align:left; display:flex; align-items:center; gap:6px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="2" style="width:13px;height:13px;flex-shrink:0;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span style="font-size:0.72rem; color:#bbb;">داخڵکراوە لەلایەن:</span>
                        <span style="font-size:0.72rem; color:#888; font-weight:700;">${hl(b.creator_name)}</span>
                    </div>` : ''}
                </div>
            </div>`;
        })
        .catch(() => {
            body.innerHTML = '<div style="text-align:center;padding:40px;color:red;">هەڵە لە بارکردنی زانیاریەکان</div>';
        });
}

// ── Add/Edit Modal ──────────────────────────────────────────────
function openBookModal(id = null) {
    const modal = document.getElementById('bookModal');
    document.getElementById('bookForm').reset();
    document.getElementById('bookId').value = '';
    document.getElementById('bookModalTitle').textContent = 'زیادکردنی کتێبی نوێ';
    // clear smart inputs
    document.querySelectorAll('#bookModal .smart-input').forEach(i => i.value = '');
    document.querySelectorAll('#bookModal input[type="hidden"]').forEach(i => { if (i.id !== 'bookId') i.value = ''; });
    document.getElementById('bk_sync_pub').checked = false;
    
    // Default language: Kurdish
    document.getElementById('bk_language').value = 'کوردی';

    // Reset media
    document.getElementById('coverPreview').innerHTML = '<span>وێنە لێرە دەردەکەوێت</span>';
    document.getElementById('cover_file_name').textContent = '';
    document.getElementById('pdf_file_name').textContent = '';
    document.getElementById('bk_pdf_link').value = '';
    document.getElementById('existing_pdf_info').style.display = 'none';
    document.getElementById('bk_description').value = '';
    setPdfType('upload');

    if (id) {
        document.getElementById('bookModalTitle').textContent = 'دەستکاری کتێب';
        fetch(`fetch_data.php?action=get_book&id=${id}`)
            .then(r => r.json())
            .then(b => {
                document.getElementById('bookId').value       = b.id;
                document.getElementById('bk_title').value    = b.title || '';
                document.getElementById('bk_author_id').value = b.author_id || '';
                document.querySelector('[data-type="author"] .smart-input').value     = b.author_name || '';
                document.getElementById('bk_translator_id').value = b.translator_id || '';
                document.querySelector('[data-type="translator"] .smart-input').value = b.translator_name || '';
                document.getElementById('bk_category_id').value = b.category_id || '';
                document.querySelector('[data-type="category"] .smart-input').value   = b.category_name || '';
                document.getElementById('bk_quantity').value  = b.quantity || 1;
                document.getElementById('bk_code').value      = b.book_code || '';
                document.getElementById('bk_shelf').value     = b.shelf_code || '';
                document.getElementById('bk_year').value      = b.publish_year || '';
                document.getElementById('bk_description').value = b.description || '';
                document.getElementById('bk_sync_pub').checked = (b.category_id == 471);
                
                // Handle languages
                document.getElementById('bk_language').value = (b.language || 'کوردی').split(',')[0].trim();

                // Handle Media
                if (b.cover_image) {
                    document.getElementById('coverPreview').innerHTML = `<img src="${b.cover_image}"><button type="button" class="remove-img-btn" id="removeImgBtn" onclick="removeCoverSelection()" title="سڕینەوەی وێنە">&times;</button>`;
                    document.getElementById('removeImgBtn').style.display = 'flex';
                }
                if (b.pdf_file) {
                    document.getElementById('existing_pdf_info').style.display = 'block';
                }
                document.getElementById('bk_pdf_link').value = b.pdf_link || '';
                if (b.pdf_link && !b.pdf_file) {
                    setPdfType('link');
                }
            });
    }
    modal.classList.add('show');
    document.getElementById('bookOverlay').classList.add('show');
}

function closeBookModal() {
    document.getElementById('bookModal').classList.remove('show');
    document.getElementById('bookOverlay').classList.remove('show');
}

async function saveBook(addNew = false) {
    const btns = document.querySelectorAll('.lib-modal-footer .lib-btn-primary, .lib-modal-footer .lib-btn-gold');
    btns.forEach(b => { b.disabled = true; b.style.opacity = '0.6'; b.style.cursor = 'not-allowed'; });

    const title = document.getElementById('bk_title').value.trim();
    if (!title) { 
        libToast('ناوی کتێب پێویستە!', 'error'); 
        btns.forEach(b => { b.disabled = false; b.style.opacity = '1'; b.style.cursor = 'pointer'; });
        return; 
    }
    const catId = document.getElementById('bk_category_id').value;
    const catName = document.querySelector('[data-type="category"] .smart-input').value.trim();
    if (!catId && !catName) { 
        libToast('تکایە بەش هەڵبژێرە!', 'error'); 
        btns.forEach(b => { b.disabled = false; b.style.opacity = '1'; b.style.cursor = 'pointer'; });
        return; 
    }

    const fd = new FormData();
    fd.append('action',        'save');
    fd.append('id',            document.getElementById('bookId').value);
    fd.append('title',         title);
    fd.append('author_id',     document.getElementById('bk_author_id').value);
    fd.append('author_name',   document.querySelector('[data-type="author"] .smart-input').value);
    fd.append('translator_id', document.getElementById('bk_translator_id').value);
    fd.append('translator_name', document.querySelector('[data-type="translator"] .smart-input').value);
    fd.append('category_id',   document.getElementById('bk_category_id').value);
    fd.append('category_name', document.querySelector('[data-type="category"] .smart-input').value);
    fd.append('quantity',      document.getElementById('bk_quantity').value);
    fd.append('book_code',     document.getElementById('bk_code').value);
    fd.append('shelf_code',    document.getElementById('bk_shelf').value);
    fd.append('publish_year',  document.getElementById('bk_year').value);
    fd.append('description',   document.getElementById('bk_description').value);
    fd.append('sync_pub',      document.getElementById('bk_sync_pub').checked ? '1' : '0');
    
    // Collect languages
    fd.append('language', document.getElementById('bk_language').value);

    // Append Media
    const coverFile = document.getElementById('bk_cover').files[0];
    if (coverFile) fd.append('cover_image', coverFile);

    const pdfFile = document.getElementById('bk_pdf_file').files[0];
    if (pdfFile) fd.append('pdf_file', pdfFile);
    
    fd.append('pdf_link', document.getElementById('bk_pdf_link').value);
    fd.append('delete_cover', document.getElementById('bk_delete_cover').value);

    try {
        const resp = await fetch('save_book.php', { method:'POST', body:fd });
        const text = await resp.text();
        let res;
        try {
            res = JSON.parse(text);
        } catch(e) {
            console.error("Server raw:", text);
            libToast('هەڵەی سێرڤەر: ' + text.substring(0, 50), 'error');
            return;
        }
        
        libToast(res.message || (res.success ? 'پاشەکەوتکرا' : 'هەڵە'), res.success ? 'success' : 'error');
        if (res.success) {
            loadBooks(currentPage);
            if (addNew) { 
                document.getElementById('bookForm').reset(); 
                document.getElementById('bookModalTitle').textContent = 'زیادکردنی کتێبی نوێ';
                document.querySelectorAll('#bookModal .smart-input').forEach(i => i.value = '');
                document.querySelectorAll('#bookModal input[type="hidden"]').forEach(i => { if (i.id !== 'bookId') i.value = ''; });
            } else {
                closeBookModal();
            }
        }
    } catch(err) {
        libToast('پەیوەندی سەرنەکەوت', 'error');
    }
    btns.forEach(b => { b.disabled = false; b.style.opacity = '1'; b.style.cursor = 'pointer'; });
}

// ── Smart Selects ───────────────────────────────────────────────
document.querySelectorAll('.smart-select').forEach(sel => {
    const type  = sel.dataset.type;
    const input = sel.querySelector('.smart-input');
    const menu  = sel.querySelector('.dd-menu');
    const items = sel.querySelector('.dd-items');
    const hidden = type === 'author' ? document.getElementById('bk_author_id')
                 : type === 'translator' ? document.getElementById('bk_translator_id')
                 : type === 'category' ? document.getElementById('bk_category_id')
                 : null;
    const source = type === 'author' ? ALL_AUTHORS : type === 'translator' ? ALL_TRANSLATORS : type === 'category' ? ALL_CATEGORIES : ALL_TITLES;

    const render = (q = '') => {
        const list = type === 'title' 
            ? source.filter(x => x.toLowerCase().includes(q.toLowerCase())).map(x => ({id: x, name: x}))
            : source.filter(x => x.name.toLowerCase().includes(q.toLowerCase()));

        items.innerHTML = list.slice(0, 80).map(it => {
            const displayName = it.shelf_code ? `${it.name} (${it.shelf_code})` : it.name;
            return `<div class="smart-item" data-id="${it.id}" data-name="${it.name.replace(/"/g, '&quot;')}">${displayName}</div>`;
        }).join('');
        
        items.querySelectorAll('.smart-item').forEach(d => d.addEventListener('click', () => {
            input.value  = d.dataset.name;
            if (hidden) {
                hidden.value = d.dataset.id;
                // If category changed and we are adding a new book, auto-fill the next book code
                if (type === 'category' && !document.getElementById('bookId').value) {
                    fetch(`fetch_data.php?action=get_next_book_code&category_id=${d.dataset.id}`)
                        .then(r => r.json())
                        .then(res => {
                            if (res.next_code) {
                                document.getElementById('bk_code').value = res.next_code;
                            }
                            if (res.suggested_shelf) {
                                document.getElementById('bk_shelf').value = res.suggested_shelf;
                            }
                            updateFullCodePreview();
                        });
                }
            }
            menu.classList.remove('show');
        }));
    };

    input.addEventListener('focus', () => { 
        document.querySelectorAll('.dd-menu').forEach(m => m.classList.remove('show'));
        render(input.value); 
        menu.classList.add('show'); 
    });
    input.addEventListener('input', () => { render(input.value); menu.classList.add('show'); hidden.value = ''; });
    document.addEventListener('click', e => { if (!sel.contains(e.target)) menu.classList.remove('show'); });
});

// Close all menus when focusing any input in the modal
document.querySelectorAll('#bookModal input, #bookModal select').forEach(el => {
    el.addEventListener('focus', () => {
        document.querySelectorAll('.dd-menu').forEach(m => m.classList.remove('show'));
    }, true);
});

function promptAddCategory() {
    const name = prompt('ناوی بەشی نوێ بنووسە:');
    if (!name || !name.trim()) return;
    const fd = new FormData();
    fd.append('action', 'add_category'); fd.append('name', name.trim());
    fetch('fetch_data.php', { method:'POST', body:fd }).then(r => r.json()).then(d => {
        if (d.success) {
            ALL_CATEGORIES.push({ id: d.id, name: name.trim() });
            document.querySelector('[data-type="category"] .smart-input').value = name.trim();
            document.getElementById('bk_category_id').value = d.id;
            // For a brand new category, the first book code should be 1
            if (!document.getElementById('bookId').value) {
                document.getElementById('bk_code').value = 1;
            }
        }
    });
}

// ── Media Handlers ─────────────────────────────────────────────
function previewCover(input) {
    const box = document.getElementById('coverPreview');
    const info = document.getElementById('cover_file_name');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            box.innerHTML = `<img src="${e.target.result}"><button type="button" class="remove-img-btn" id="removeImgBtn" onclick="removeCoverSelection()" title="سڕینەوەی وێنە">&times;</button>`;
            document.getElementById('removeImgBtn').style.display = 'flex';
            info.textContent = input.files[0].name;
            document.getElementById('bk_delete_cover').value = '0';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeCoverSelection() {
    document.getElementById('bk_cover').value = '';
    document.getElementById('cover_file_name').innerText = '';
    document.getElementById('coverPreview').innerHTML = '<span>وێنە لێرە دەردەکەوێت</span><button type="button" class="remove-img-btn" id="removeImgBtn" onclick="removeCoverSelection()" style="display:none;" title="سڕینەوەی وێنە">&times;</button>';
    document.getElementById('bk_delete_cover').value = '1';
}

function updatePdfInfo(input) {
    const info = document.getElementById('pdf_file_name');
    if (input.files && input.files[0]) {
        info.textContent = input.files[0].name + ' (' + (input.files[0].size / 1024 / 1024).toFixed(2) + ' MB)';
    }
}

function setPdfType(type) {
    const btnUpload = document.getElementById('btn_pdf_upload');
    const btnLink   = document.getElementById('btn_pdf_link');
    const areaUpload = document.getElementById('pdf_upload_area');
    const areaLink   = document.getElementById('pdf_link_area');

    if (type === 'upload') {
        btnUpload.classList.add('active');
        btnLink.classList.remove('active');
        areaUpload.style.display = 'block';
        areaLink.style.display = 'none';
    } else {
        btnUpload.classList.remove('active');
        btnLink.classList.add('active');
        areaUpload.style.display = 'none';
        areaLink.style.display = 'block';
    }
}

// Force English digits for book code (numbers only)
document.getElementById('bk_code')?.addEventListener('input', function() {
    const digits = {'٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};
    let v = this.value;
    let newV = '';
    for (let char of v) {
        if (digits[char]) newV += digits[char];
        else if (/[0-9]/.test(char)) newV += char;
    }
    this.value = newV;
});

// Force English chars for shelf code (mapping Kurdish keys)
document.getElementById('bk_shelf')?.addEventListener('input', function() {
    const kurdishToEnglish = {
        'ش':'A', 'س':'S', 'ی':'D', 'ێ':'D', 'د':'D', 'ب':'F', 'ل':'G', 'ا':'H', 'ت':'J', 'ن':'K', 'م':'L', 'ک':'K', 'ۆ':'O', 'پ':'P', 'ج':'J', 'ح':'P',
        'ئ':'Z', 'ء':'X', 'ؤ':'C', 'ر':'V', 'لا':'B', 'ى':'N', 'ة':'M', 'و':'W', 'ز':'Z', 'ظ':'Z', 'ڕ':'R', 'ڵ':'L', 'ڤ':'V', 'ژ':'Z', 'ف':'F', 'ق':'Q', 'غ':'G', 'ع':'A', 'ه':'H', 'خ':'X', 'ص':'S', 'ض':'D', 'ث':'T', 'ط':'T', 'ک':'K', 'گ':'G', 'ۆ':'O'
    };
    let v = this.value;
    let newV = '';
    for (let char of v) {
        if (kurdishToEnglish[char]) newV += kurdishToEnglish[char];
        else newV += char;
    }
    this.value = newV.toUpperCase();
    updateFullCodePreview();
});

function updateFullCodePreview() {
    const code = document.getElementById('bk_code')?.value || '';
    const shelf = document.getElementById('bk_shelf')?.value || '';
    const preview = document.getElementById('full_code_preview');
    if (!preview) return;
    if (code || shelf) {
        preview.style.display = 'block';
        preview.innerHTML = `کۆدی ناسنامەی کتێب: <span style="font-family:monospace; letter-spacing:1px;">${shelf}${code ? (shelf ? '-' : '') + code : ''}</span>`;
    } else {
        preview.style.display = 'none';
    }
}

document.getElementById('bk_code')?.addEventListener('input', updateFullCodePreview);
document.getElementById('bk_shelf')?.addEventListener('input', updateFullCodePreview);

// ── Event Listeners ─────────────────────────────────────────────
const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

document.addEventListener('DOMContentLoaded', () => {
    renderCategoryComboOptions();
    document.getElementById('categoryComboSearch')?.addEventListener('input', e => renderCategoryComboOptions(e.target.value));
    document.addEventListener('click', e => {
        const combo = document.getElementById('categoryCombo');
        if (combo && !combo.contains(e.target)) toggleCategoryCombo(false);
    });
    document.getElementById('categoryComboSearch')?.addEventListener('keydown', e => {
        if (e.key === 'Escape') toggleCategoryCombo(false);
        if (e.key === 'Enter') {
            e.preventDefault();
            document.querySelector('#categoryComboList .category-combo-option')?.click();
        }
    });
    ['filter_category','filter_author','filter_translator','filter_year'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => loadBooks(1));
    });
    document.getElementById('filter_search')?.addEventListener('input', debounce(() => loadBooks(1), 450));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeBookModal(); closeViewModal(); } });
    
    // Check for URL parameters to auto-open book or PDF
    const urlParams = new URLSearchParams(window.location.search);
    const viewBookId = urlParams.get('view');
    const viewPdfFile = urlParams.get('pdf');

    if (viewBookId) {
        viewBook(viewBookId);
    } else if (viewPdfFile) {
        switchTab('pdfLib'); 
        const pdfUrl = `view_pdf.php?file=${encodeURIComponent(viewPdfFile)}`;
        viewPdf(pdfUrl, viewPdfFile);
    }

    loadBooks(1);
});

window.addEventListener('online', () => {
    if (document.getElementById('kurdishLibTab')?.classList.contains('active')) {
        renderKurdishLib();
    }
});
window.addEventListener('offline', () => {
    if (document.getElementById('kurdishLibTab')?.classList.contains('active')) {
        renderKurdishLib();
    }
});
</script>

<?php require_once 'includes/layout_end.php'; ?>
