<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$categories = [];
if ($conn !== null) {
    $res = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
    while ($row = $res->fetch_assoc()) $categories[] = $row;
}

// Layout Config
$page_title = 'خواستنەکان';
$active_nav = 'borrows';
require_once 'includes/layout.php';
?>

<style>
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { background: #fff; padding: 25px; border-radius: 16px; box-shadow: var(--shadow-md); border: 1px solid var(--border); display: flex; align-items: center; gap: 20px; }
    .stat-icon { width: 60px; height: 60px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; }
    .stat-icon.borrowed { background: #fff3e0; color: #e65100; }
    .stat-icon.overdue { background: #ffebee; color: #d32f2f; }
    .stat-info h4 { font-size: 1.8rem; line-height: 1; margin-bottom: 5px; color: var(--maroon); }
    .stat-info p { color: var(--text-muted); font-weight: 600; }

    .records-header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .borrow-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 3000; display: none; align-items: center; justify-content: center; padding: 24px; }
    .borrow-modal-overlay.show { display: flex; }
    .borrow-section { background: #fff; border-radius: 20px; padding: 30px; box-shadow: var(--shadow-lg); border: 1px solid var(--border); width: min(1180px, 100%); max-height: calc(100vh - 48px); overflow-y: auto; }
    .section-title { font-size: 1.2rem; margin-bottom: 25px; color: var(--maroon); display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; font-weight: 700; }
    .section-title .title-text { flex: 1; }
    .modal-close-btn { width: 36px; height: 36px; border: none; border-radius: 50%; background: #f2f2f2; color: #555; cursor: pointer; font-size: 1.5rem; line-height: 1; display: inline-flex; align-items: center; justify-content: center; transition: .2s; }
    .modal-close-btn:hover { background: #ffebee; color: #d32f2f; }
    
    .books-select-box { border: 1px solid #eee; border-radius: 15px; padding: 20px; background: #fafafa; position: relative; }
    .selection-controls { display: grid; grid-template-columns: 1fr 200px; gap: 15px; margin-bottom: 20px; }
    .search-input-wrapper { position: relative; }
    .search-results { position: absolute; top: calc(100% + 5px); left: 0; right: 0; background: #fff; border: 1px solid #ddd; border-radius: 12px; box-shadow: var(--shadow-lg); z-index: 100; max-height: 360px; overflow-y: auto; display: none; padding: 8px; }
    .result-item { padding: 11px 12px; cursor: pointer; border-bottom: 1px solid #f5f5f5; display: grid; grid-template-columns: auto 1fr auto; gap: 12px; align-items: center; border-radius: 10px; }
    .result-item:last-child { border-bottom: none; }
    .result-item:hover { background: #f8f9fa; }
    .result-item.selected { background: #f0faf8; border-color: #d7eee9; }
    .result-item.disabled { opacity: 0.55; cursor: not-allowed; }
    .result-item input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--maroon); cursor: pointer; }
    .result-item.disabled input[type="checkbox"] { cursor: not-allowed; }
    .result-item .book-info strong { color: #222; font-size: 0.92rem; }
    .result-item .book-cat { font-size: 0.8rem; background: #eee; padding: 2px 8px; border-radius: 4px; color: #666; }
    
    .selected-books-container { display: flex; flex-wrap: wrap; gap: 10px; min-height: 45px; padding: 10px; background: #fff; border: 1px dashed #ccc; border-radius: 10px; }
    .book-tag { background: var(--maroon); color: #fff; padding: 6px 14px; border-radius: 20px; display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 4px 10px rgba(90, 2, 2, 0.2); animation: fadeIn 0.3s; }
    .book-tag .remove-btn { cursor: pointer; font-size: 1.1rem; width: 18px; height: 18px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .book-tag .remove-btn:hover { background: #fff; color: var(--maroon); }
    
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

    .overdue-alert { background: #fff5f5; border-right: 5px solid #d32f2f; padding: 20px; border-radius: 12px; margin-bottom: 30px; display: none; }
    .overdue-alert.active { display: block; }
    .overdue-alert h5 { color: #d32f2f; margin-bottom: 10px; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; }

    .rec-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; }
    .rec-badge.borrowed { background: #fff3e0; color: #e65100; }
    .rec-badge.returned { background: #e8f5e9; color: #2e7d32; }
    .rec-badge.overdue { background: #ffebee; color: #d32f2f; }

    .records-filter-bar { display: grid; grid-template-columns: 1fr 180px 170px auto; gap: 12px; padding: 18px; border-bottom: 1px solid var(--border); background: #fbfbfb; align-items: center; }
    .records-filter-bar input,
    .records-filter-bar select { height: 44px; border: 1px solid var(--border); border-radius: 10px; padding: 0 12px; font-family: inherit; font-weight: 600; background: #fff; }
    .records-count { color: var(--text-muted); font-size: .86rem; font-weight: 700; white-space: nowrap; }

    #recordsBody td { vertical-align: middle; }

    @media (max-width: 760px) {
        .selection-controls { grid-template-columns: 1fr; }
        .records-filter-bar { grid-template-columns: 1fr; }
        .search-results { position: static; margin-top: 8px; }
    }
</style>

<!-- Overdue Notification -->
<div class="overdue-alert" id="overdueBox">
    <h5>⚠️ ئاگادارکردنەوەی بەسەرچوون</h5>
    <p id="overdueContent">خەریکە بار دەکرێت...</p>
</div>

<div class="stats-row">
    <div class="stat-card">
        <div class="stat-icon borrowed">📁</div>
        <div class="stat-info">
            <h4 id="totalBorrowed">0</h4>
            <p>کتێبی بارکراو</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon overdue">⏰</div>
        <div class="stat-info">
            <h4 id="totalOverdue">0</h4>
            <p>بەسەرچووەکان</p>
        </div>
    </div>
</div>

<!-- Borrow Form -->
<div class="borrow-modal-overlay" id="borrowModalOverlay" onclick="if(event.target === this) closeBorrowModal()">
<div class="borrow-section" role="dialog" aria-modal="true" aria-labelledby="borrowModalTitle">
    <h3 class="section-title" id="borrowModalTitle">
        <span class="title-text">➕ تۆمارکردنی خواستنی نوێ</span>
        <button type="button" class="modal-close-btn" onclick="closeBorrowModal()" title="داخستن">&times;</button>
    </h3>
    <div class="lib-form-grid" style="margin-bottom: 25px;">
        <div class="lib-field">
            <label>ناوی ئەو کەسەی دەیبات</label>
            <input type="text" id="borrower_name" placeholder="ناوی تەواو بنووسە...">
        </div>
        <div class="lib-field">
            <label>ژمارەی مۆبایل</label>
            <input type="text" id="borrower_phone" placeholder="07XXXXXXXXX">
        </div>
        <div class="lib-field">
            <label>یەکەکەی (Unit)</label>
            <input type="text" id="borrower_unit" placeholder="بەش یان یەکە...">
        </div>
        <div class="lib-field">
            <label>ڕێکەوتی بردن</label>
            <input type="date" id="borrow_date" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="lib-field">
            <label>ڕێکەوتی گەڕانەوە</label>
            <input type="date" id="due_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
        </div>
    </div>

    <div class="books-select-box">
        <h4 style="margin-bottom: 15px; color: #666; display: flex; justify-content: space-between; font-size: 0.95rem;">
            <span>کتێب دیاری بکە:</span>
            <span id="selectionCount" style="color: var(--maroon); font-weight: 700;">0 دانە هەڵبژێردراوە</span>
        </h4>
        
        <div class="selection-controls">
            <div class="search-input-wrapper">
                <input type="text" id="bookSearch" placeholder="گەڕان بەدوای ناوی کتێب یان کۆد..." class="lib-field" style="padding: 12px; width: 100%; border-radius: 10px; border: 2px solid #eee;" autocomplete="off">
                <div class="search-results" id="searchResults"></div>
            </div>
            <select id="catFilter" onchange="handleBookSearch()" style="padding: 10px; border-radius: 10px; border: 2px solid #eee; font-family: inherit;">
                <option value="">هەموو بەشەکان</option>
                <?php foreach($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="selected-books-container" id="selectedBooks">
            <div style="color: #999; font-size: 0.9rem; padding: 5px;">هیچ کتێبێک هەڵنەبژێردراوە...</div>
        </div>
    </div>

    <div style="display: flex; justify-content: flex-end; margin-top: 25px;">
        <button class="lib-btn lib-btn-primary" onclick="submitBorrowRequest()" style="padding: 15px 40px; font-size: 1rem;">تۆمارکردن لە سیستم</button>
    </div>
</div>
</div>

<!-- Records Table -->
<div class="lib-card">
    <div class="card-header">
        <h3 style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
            <span>📜 تۆمارەکانی خواستن</span>
            <div class="records-header-actions">
                <button class="lib-btn lib-btn-primary" onclick="openBorrowModal()" style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:1.25rem; line-height:1;">+</span>
                    خواستنی نوێ
                </button>
                <button class="lib-btn lib-btn-gold" onclick="window.open('print_borrows.php', '_blank')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    چاپی لیستەکە
                </button>
            </div>
        </h3>
    </div>
    <div class="records-filter-bar">
        <input type="text" id="recordsSearch" placeholder="گەڕان بە ناو، مۆبایل، یەکە، ناوی کتێب یان کۆد..." autocomplete="off">
        <select id="recordsStatusFilter">
            <option value="">هەموو دۆخەکان</option>
            <option value="borrowed">خواستراو</option>
            <option value="overdue">بەسەرچوو</option>
            <option value="returned">گەڕاوەتەوە</option>
        </select>
        <select id="recordsDateFilter">
            <option value="">هەموو ڕێکەوتەکان</option>
            <option value="today">ئەمڕۆ</option>
            <option value="week">ئەم هەفتەیە</option>
            <option value="month">ئەم مانگە</option>
        </select>
        <div class="records-count" id="recordsCount">0 تۆمار</div>
    </div>
    <div class="lib-table-wrapper">
        <table class="lib-table">
            <thead>
                <tr>
                    <th>بەکارهێنەر</th>
                    <th>مۆبایل / یەکە</th>
                    <th>ناوی کتێب</th>
                    <th>ڕێکەوتی بردن</th>
                    <th>پێویستە بگەڕێتەوە</th>
                    <th>دۆخ</th>
                    <th style="width: 120px;">کار</th>
                </tr>
            </thead>
            <tbody id="recordsBody">
                <tr><td colspan="7" style="text-align:center; padding: 40px;">لە بارکردندایە...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    let ALL_BOOKS = [];
    let ALL_BORROW_RECORDS = [];
    let selectedBookIds = new Set();

    window.onload = () => {
        loadBooksData();
        loadBorrowRecords();
    };

    async function loadBooksData() {
        try {
            const res = await fetch('fetch_data.php?action=filter_books&limit=10000');
            const data = await res.json();
            ALL_BOOKS = Array.isArray(data) ? data : (data.books || []);
            handleBookSearch(false);
        } catch (e) {
            console.error("Failed to load books data");
        }
    }

    document.getElementById('bookSearch').oninput = () => handleBookSearch(true);
    document.getElementById('bookSearch').onfocus = () => handleBookSearch(true);
    document.getElementById('recordsSearch')?.addEventListener('input', renderBorrowRecords);
    document.getElementById('recordsStatusFilter')?.addEventListener('change', renderBorrowRecords);
    document.getElementById('recordsDateFilter')?.addEventListener('change', renderBorrowRecords);

    function openBorrowModal() {
        document.getElementById('borrowModalOverlay')?.classList.add('show');
        setTimeout(() => document.getElementById('borrower_name')?.focus(), 0);
    }

    function closeBorrowModal() {
        document.getElementById('borrowModalOverlay')?.classList.remove('show');
        document.getElementById('searchResults').style.display = 'none';
    }

    function escapeHtml(value) {
        return (value ?? '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }

    function handleBookSearch(showResults = true) {
        const query = document.getElementById('bookSearch').value.toLowerCase().trim();
        const normalizedQuery = query.replace(/-/g, '');
        const catId = document.getElementById('catFilter').value;
        const resultsDiv = document.getElementById('searchResults');

        if (!showResults) {
            resultsDiv.style.display = 'none';
            return;
        }

        const filtered = ALL_BOOKS.filter(b => {
            const title = (b.title || '').toLowerCase();
            const code = (b.book_code || '').toLowerCase();
            const shelf = (b.shelf_code || '').toLowerCase();
            const fullCode = (shelf + (code ? (shelf ? '-' : '') + code : '')).toLowerCase();
            const normalizedFullCode = fullCode.replace(/-/g, '');
            const author = (b.author_name || '').toLowerCase();
            
            const matchSearch = !query || 
                title.includes(query) || 
                code.includes(query) || 
                shelf.includes(query) || 
                fullCode.includes(query) || 
                normalizedFullCode.includes(normalizedQuery) ||
                author.includes(query);
                
            const matchCat = !catId || b.category_id == catId;
            return matchSearch && matchCat;
        });

        if (filtered.length > 0) {
            resultsDiv.innerHTML = filtered.slice(0, 200).map(b => {
                const isSelected = selectedBookIds.has(Number(b.id));
                const isAvailable = Number(b.available) > 0 || isSelected;
                const disabledAttr = isAvailable ? '' : 'disabled';
                return `
                    <label class="result-item ${isSelected ? 'selected' : ''} ${isAvailable ? '' : 'disabled'}">
                        <input type="checkbox" ${isSelected ? 'checked' : ''} ${disabledAttr} onchange="toggleBookSelection(${Number(b.id)}, this.checked)">
                        <div class="book-info">
                            <strong>${escapeHtml(b.title)}</strong><br>
                            <small style="color: #888;">${escapeHtml((b.shelf_code && b.book_code) ? b.shelf_code + '-' + b.book_code : (b.book_code || '-'))} ${b.author_name ? ' - ' + escapeHtml(b.author_name) : ''}</small>
                        </div>
                        <div style="text-align: left;">
                            <span class="book-cat">${escapeHtml(b.category_name || '-')}</span><br>
                            <span style="font-size: 0.75rem; color: ${isAvailable ? '#27ae60' : '#e74c3c'}; font-weight: bold;">
                                ${Number(b.available) > 0 ? `بەردەستە (${b.available})` : (isSelected ? 'هەڵبژێردراوە' : 'بەردەست نییە')}
                            </span>
                        </div>
                    </label>
                `;
            }).join('');
            resultsDiv.style.display = 'block';
        } else {
            resultsDiv.innerHTML = '<div style="padding:15px; text-align:center; color:#999;">هیچ کتێبێک نەدۆزرایەوە</div>';
            resultsDiv.style.display = 'block';
        }
    }

    function toggleBookSelection(id, checked) {
        if (checked) selectedBookIds.add(Number(id));
        else selectedBookIds.delete(Number(id));
        renderSelectedBooks();
        handleBookSearch(true);
    }

    function removeBookFromSelection(id) {
        selectedBookIds.delete(Number(id));
        renderSelectedBooks();
        handleBookSearch(document.getElementById('searchResults').style.display === 'block');
    }

    function renderSelectedBooks() {
        const container = document.getElementById('selectedBooks');
        const countSpan = document.getElementById('selectionCount');
        
        if (selectedBookIds.size === 0) {
            container.innerHTML = '<div style="color: #999; font-size: 0.9rem; padding: 5px;">هیچ کتێبێک هەڵنەبژێردراوە...</div>';
            countSpan.textContent = "0 دانە هەڵبژێردراوە";
            return;
        }

        const selectedArray = Array.from(selectedBookIds).map(id => ALL_BOOKS.find(b => Number(b.id) === Number(id))).filter(Boolean);
        container.innerHTML = selectedArray.map(b => `
            <div class="book-tag">
                <span>${escapeHtml(b.title)}</span>
                <span class="remove-btn" onclick="removeBookFromSelection(${Number(b.id)})">&times;</span>
            </div>
        `).join('');
        
        countSpan.textContent = `${selectedBookIds.size} دانە هەڵبژێردراوە`;
    }

    async function submitBorrowRequest() {
        const name = document.getElementById('borrower_name').value.trim();
        const phone = document.getElementById('borrower_phone').value.trim();
        const unit = document.getElementById('borrower_unit').value.trim();
        const bDate = document.getElementById('borrow_date').value;
        const dDate = document.getElementById('due_date').value;
        const hooks = Array.from(selectedBookIds);

        if (!name || hooks.length === 0) {
            libToast('تکایە ناوی کەسەکە و لانیکەم کتێبێک هەڵبژێرە', 'error');
            return;
        }

        try {
            // First create/update user
            const uFd = new FormData();
            uFd.append('full_name', name); uFd.append('phone', phone); uFd.append('unit', unit);
            uFd.append('username', name.replace(/\s+/g,'').toLowerCase() + Math.floor(Math.random()*100));
            
            const uRes = await fetch('fetch_data.php?action=add_user', { method: 'POST', body: uFd }).then(r=>r.json());
            if (!uRes.success) { libToast(uRes.message, 'error'); return; }

            // Then borrow each book
            for (let bid of hooks) {
                const bFd = new FormData();
                bFd.append('action', 'borrow_book'); bFd.append('book_id', bid);
                bFd.append('user_id', uRes.id); bFd.append('borrow_date', bDate); bFd.append('due_date', dDate);
                await fetch('fetch_data.php', { method: 'POST', body: bFd });
            }

            libToast('بە سەرکەوتوویی تۆمارکرا');
            resetForm();
            closeBorrowModal();
            loadBooksData();
            loadBorrowRecords();
        } catch (e) { libToast('هەڵەیەک ڕوویدا', 'error'); }
    }

    function resetForm() {
        document.getElementById('borrower_name').value = '';
        document.getElementById('borrower_phone').value = '';
        document.getElementById('borrower_unit').value = '';
        document.getElementById('bookSearch').value = '';
        document.getElementById('searchResults').style.display = 'none';
        selectedBookIds.clear();
        renderSelectedBooks();
    }

    async function loadBorrowRecords() {
        try {
            const res = await fetch('fetch_data.php?action=get_borrow_records').then(r=>r.json());
            ALL_BORROW_RECORDS = Array.isArray(res) ? res : [];
            renderBorrowRecords();
        } catch (e) { console.error(e); }
    }

    function getRecordStatus(record, today) {
        if (record.status === 'returned') return { cls: 'returned', text: 'گەڕاوەتەوە' };
        if (record.due_date < today) return { cls: 'overdue', text: 'بەسەرچوو' };
        return { cls: 'borrowed', text: 'خواستراو' };
    }

    function matchesDateFilter(dateValue, filterValue) {
        if (!filterValue) return true;
        if (!dateValue) return false;

        const recordDate = new Date(dateValue + 'T00:00:00');
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (filterValue === 'today') return recordDate.getTime() === today.getTime();

        const start = new Date(today);
        if (filterValue === 'week') start.setDate(today.getDate() - 7);
        if (filterValue === 'month') start.setMonth(today.getMonth() - 1);
        return recordDate >= start && recordDate <= today;
    }

    function renderBorrowRecords() {
        const tbody = document.getElementById('recordsBody');
        const query = (document.getElementById('recordsSearch')?.value || '').toLowerCase().trim();
        const statusFilter = document.getElementById('recordsStatusFilter')?.value || '';
        const dateFilter = document.getElementById('recordsDateFilter')?.value || '';
        const today = new Date().toISOString().split('T')[0];

        let overdueList = [], borrowedCount = 0, overdueCount = 0;
        ALL_BORROW_RECORDS.forEach(r => {
            const status = getRecordStatus(r, today);
            if (status.cls !== 'returned') borrowedCount++;
            if (status.cls === 'overdue') {
                overdueCount++;
                overdueList.push(`${r.full_name} (${r.book_title})`);
            }
        });

        const filtered = ALL_BORROW_RECORDS.filter(r => {
            const status = getRecordStatus(r, today);
            const fullBookCode = (r.shelf_code || '') + (r.book_code ? (r.shelf_code ? '-' : '') + r.book_code : '');
            const normalizedFullCode = fullBookCode.toLowerCase().replace(/-/g, '');
            const normalizedQuery = query.replace(/-/g, '');
            
            const searchable = [r.full_name, r.phone, r.unit, r.book_title, r.book_code, r.shelf_code, fullBookCode, r.borrow_date, r.due_date]
                .map(v => (v || '').toString().toLowerCase())
                .join(' ');

            return (!query || searchable.includes(query) || normalizedFullCode.includes(normalizedQuery))
                && (!statusFilter || status.cls === statusFilter)
                && matchesDateFilter(r.borrow_date, dateFilter);
        });

        document.getElementById('recordsCount').textContent = `${filtered.length} تۆمار`;

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:#999;">هیچ تۆمارێک نەدۆزرایەوە</td></tr>';
        } else {
            tbody.innerHTML = filtered.map(r => {
                const status = getRecordStatus(r, today);
                return `<tr>
                    <td style="font-weight:700;">${escapeHtml(r.full_name || '-')}</td>
                    <td><div style="font-size:0.85rem;">${escapeHtml(r.phone || '-')}</div><div style="font-size:0.8rem; color:#888;">${escapeHtml(r.unit || '-')}</div></td>
                    <td>
                        <div style="color:var(--maroon); font-weight:600;">${escapeHtml(r.book_title || '-')}</div>
                        <div style="font-size:0.75rem; color:#666;">کۆد: ${escapeHtml((r.shelf_code && r.book_code) ? r.shelf_code + '-' + r.book_code : (r.book_code || '-'))}</div>
                    </td>
                    <td>${escapeHtml(r.borrow_date || '-')}</td>
                    <td style="color:${status.cls === 'overdue' ? 'red' : 'inherit'}; font-weight:${status.cls === 'overdue' ? '700' : '400'};">${escapeHtml(r.due_date || '-')}</td>
                    <td><span class="rec-badge ${status.cls}">${status.text}</span></td>
                    <td style="text-align: left;">
                        ${r.status !== 'returned' ? `<button class="lib-btn lib-btn-success" onclick="returnBook(${Number(r.id)})" style="padding: 5px 12px; font-size: 0.75rem;">گەڕانەوە</button>` : '<span style="color:#2e7d32; font-weight:bold;">گەڕاوەتەوە</span>'}
                    </td>
                </tr>`;
            }).join('');
        }

        document.getElementById('totalBorrowed').textContent = borrowedCount;
        document.getElementById('totalOverdue').textContent = overdueCount;

        const oBox = document.getElementById('overdueBox');
        if (overdueCount > 0) {
            oBox.classList.add('active');
            document.getElementById('overdueContent').innerHTML = `ئێستا <strong>${overdueCount}</strong> خواستن بەرواریان بەسەرچووە: <br><small>${overdueList.map(escapeHtml).join(' — ')}</small>`;
        } else { oBox.classList.remove('active'); }
    }

    async function returnBook(id) {
        if (!confirm('ئایا دڵنیایت ئەم کتێبە گەڕاوەتەوە؟')) return;
        const fd = new FormData();
        fd.append('action', 'return_book'); fd.append('record_id', id);
        const res = await fetch('fetch_data.php', { method: 'POST', body: fd }).then(r=>r.json());
        if (res.success) { libToast('بە سەرکەوتوویی گەڕایەوە'); loadBooksData(); loadBorrowRecords(); }
    }

    // Close results on outside click
    document.addEventListener('click', e => { if (!e.target.closest('.books-select-box')) document.getElementById('searchResults').style.display = 'none'; });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeBorrowModal(); });
</script>

<?php require_once 'includes/layout_end.php'; ?>
