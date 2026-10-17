<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';

$message = '';
$messageType = '';

$conn = getDB();
$authors = [];
$translators = [];
$categories = [];

if ($conn !== null) {
    $result = $conn->query("SELECT id, name FROM authors ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $authors[] = $row;
    }
    
    $result = $conn->query("SELECT id, name FROM translators ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $translators[] = $row;
    }
    
    $res = $conn->query("SELECT c.id, c.name, 
                        (SELECT shelf_code FROM books b WHERE b.category_id = c.id AND shelf_code IS NOT NULL AND shelf_code != '' GROUP BY shelf_code ORDER BY COUNT(*) DESC LIMIT 1) as shelf_code
                        FROM categories c ORDER BY c.name");
    while($row = $res->fetch_assoc()) $categories[] = $row;
}
?>
<!DOCTYPE html>
<html lang="ckb" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>زیادکردنی کتێب - کتێبخانەی پێشمەرگە</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        @font-face {
            font-family: 'Rabar013';
            src: url('assets/font/Rabar_013.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Rabar022';
            src: url('assets/font/Rabar_022.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Rabar022', 'Segoe UI', Tahoma, Arial, sans-serif; background: #f5f5f5; }
        h1, h2, h3, h4, h5, h6, .form-title { font-family: 'Rabar013', serif; }
        
        .form-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-card { background: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .form-title { font-size: 1.5rem; color: #5a0202; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #C8A951; }
        
        .form-row { margin-bottom: 20px; }
        .form-row label { display: block; font-weight: 600; margin-bottom: 8px; color: #1a1a1a; }
        .form-row input, .form-row select { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-family: inherit; font-size: 1rem; text-align: right; }
        .form-row input:focus, .form-row select:focus { border-color: #5a0202; outline: none; }
        
        .form-row input::placeholder { color: #999; }
        .form-row input[type="number"] { text-align: left; direction: ltr; }
        
        .btn-save { background: #5a0202; color: #fff; border: none; padding: 14px 30px; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; }
        .btn-save:hover { background: #7a2929; }
        .btn-cancel { background: #666; color: #fff; border: none; padding: 14px 30px; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        
        .button-group { display: flex; gap: 15px; margin-top: 25px; }
        
        .message { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .message.success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .message.error { background: #ffebee; color: #5a0202; border: 1px solid #ffcdd2; }
        
        .smart-select { position: relative; }
        .smart-select .select-btn { width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 8px; background: #fff; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-size: 1rem; }
        .smart-select .select-btn:hover { border-color: #C8A951; }
        .smart-select .dropdown-menu { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 2px solid #e0e0e0; border-top: none; border-radius: 0 0 8px 8px; max-height: 250px; overflow-y: auto; z-index: 1000; display: none; }
        .smart-select .dropdown-menu.show { display: block; }
        .smart-select .dropdown-item { padding: 12px 15px; cursor: pointer; transition: background 0.2s; }
        .smart-select .dropdown-item:hover { background: #f5f5f5; }
        .smart-select .dropdown-item.selected { background: #C8A951; color: #5a0202; }
        .smart-select .add-new-btn { padding: 12px 15px; border-top: 2px solid #e0e0e0; color: #5a0202; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .smart-select .add-new-btn:hover { background: #fff3e0; }
        
        .smart-select .search-input { padding: 10px; border: none; border-bottom: 2px solid #e0e0e0; width: 100%; font-size: 1rem; }
        .smart-select .no-results { padding: 15px; text-align: center; color: #999; }
        
        .new-item-input { display: none; padding: 10px; border: 2px solid #5a0202; border-radius: 8px; margin-top: 10px; }
        .new-item-input.show { display: flex; gap: 10px; }
        .new-item-input input { flex: 1; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem; }
        .new-item-input button { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-add-item { background: #5a0202; color: #fff; }
        .btn-cancel-item { background: #e0e0e0; color: #333; }
        
        .form-row .help-text { font-size: 0.85rem; color: #666; margin-top: 5px; }
        .form-row .required { color: #d32f2f; }
        
        .checkbox-row { display: flex; align-items: center; gap: 10px; }
        .checkbox-row input[type="checkbox"] { width: auto; }
        
        @media (max-width: 600px) {
            .form-card { padding: 20px; }
            .button-group { flex-direction: column; }
            .btn-cancel { text-align: center; }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo"><img src="assets/logo.svg" alt="Logo" onerror="this.style.display='none'">
                    <div class="logo-placeholder-small">ک</div>
                </div>
                <h3>کتێبخانەی پێشمەرگە</h3>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>داشبۆرد</span>
                </a>
                <a href="books.php" class="nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                    <span>کتێبەکان</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    <span>ڕێکخستنەکان</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="nav-item logout">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span>دەرچوون</span>
                </a>
            </div>
        </aside>
        
        <div class="main-content">
            <header class="topbar">
                <div class="topbar-title">
                    <h1>کتێبخانەی پێشمەرگە</h1>
                    <p>وەزارەتی پێشمەرگە - ئەمینداریەتی گشتی</p>
                </div>
                <div class="topbar-user">
                    <div class="user-info">
                        <span class="user-label">بەکارهێنەر:</span>
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                    <div class="user-avatar">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </div>
                </div>
            </header>
            
            <main class="content">
                <div class="form-container">
                    <div class="form-card">
                        <h2 class="form-title">زیادکردنی کتێب</h2>
                        
                        <div id="message-area"></div>
                        
                        <form id="book-form">
                            <div class="form-row">
                                <label for="title">ناوی کتێب <span class="required">*</span></label>
                                <input type="text" id="title" name="title" placeholder="ناوی کتێب بنووسە" required>
                            </div>
                            
                            <div class="form-row">
                                <label for="author">نوسەر</label>
                                <div class="smart-select" data-type="author">
                                    <button type="button" class="select-btn">
                                        <span class="selected-text">-- هەڵبژاردن --</span>
                                        <span>▼</span>
                                    </button>
                                    <div class="dropdown-menu">
                                        <input type="text" class="search-input" placeholder="گەڕان...">
                                        <div class="dropdown-items"></div>
                                        <div class="add-new-btn">
                                            <span>+</span> زیادکردنی نوێ
                                        </div>
                                    </div>
                                    <input type="hidden" name="author_id" id="author_id">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <label for="translator">وەرگێڕ</label>
                                <div class="smart-select" data-type="translator">
                                    <button type="button" class="select-btn">
                                        <span class="selected-text">-- هەڵبژاردن --</span>
                                        <span>▼</span>
                                    </button>
                                    <div class="dropdown-menu">
                                        <input type="text" class="search-input" placeholder="گەڕان...">
                                        <div class="dropdown-items"></div>
                                        <div class="add-new-btn">
                                            <span>+</span> زیادکردنی نوێ
                                        </div>
                                    </div>
                                    <input type="hidden" name="translator_id" id="translator_id">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <label for="category">بەش <span class="required">*</span></label>
                                <div class="smart-select" data-type="category">
                                    <button type="button" class="select-btn">
                                        <span class="selected-text">-- هەڵبژاردن --</span>
                                        <span>▼</span>
                                    </button>
                                    <div class="dropdown-menu">
                                        <input type="text" class="search-input" placeholder="گەڕان...">
                                        <div class="dropdown-items"></div>
                                        <div class="add-new-btn">
                                            <span>+</span> زیادکردنی نوێ
                                        </div>
                                    </div>
                                    <input type="hidden" name="category_id" id="category_id">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <label for="quantity">دانەی بەردەست <span class="required">*</span></label>
                                <input type="number" id="quantity" name="quantity" value="1" min="1" placeholder="ژمارە بنووسە">
                                <p class="help-text">تەنها ژمارە بنووسە</p>
                            </div>
                            
                            <div class="form-row">
                                <label for="book_code">کۆدی کتێب <span class="required">*</span></label>
                                <input type="text" id="book_code" name="book_code" lang="en" placeholder="123..." style="direction:ltr;text-align:left;font-weight:bold;" required>
                            </div>
                            
                            <div class="form-row">
                                <label for="shelf_code">کۆدی ڕەف</label>
                                <input type="text" id="shelf_code" name="shelf_code" lang="en" placeholder="D-12..." style="direction:ltr;text-align:left;font-family:monospace;font-weight:bold;">
                            </div>
                            <div id="full_code_preview" style="background: #fdf2f2; padding: 15px; border-radius: 12px; border: 2px dashed #5a0202; font-weight: bold; color: #5a0202; text-align: center; margin-bottom: 20px; display: none; font-size: 1.1rem;"></div>
                            
                            <div class="form-row">
                                <label for="publish_year">ساڵی بڵاوکردنەوە</label>
                                <input type="number" id="publish_year" name="publish_year" min="1800" max="2030" placeholder="ساڵ">
                            </div>
                            
                            <div class="form-row">
                                <label for="language">زمان</label>
                                <input type="text" id="language" name="language" placeholder="زمانی کتێبەکە">
                            </div>
                            
                            <div class="button-group">
                                <button type="submit" class="btn-save">پاشەکەوتکردن</button>
                                <a href="books.php" class="btn-cancel">پاشگەزبوونەوە</a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        const authors = <?php echo json_encode($authors); ?>;
        const translators = <?php echo json_encode($translators); ?>;
        const categories = <?php echo json_encode($categories); ?>;
        
        document.querySelectorAll('.smart-select').forEach(select => {
            const type = select.dataset.type;
            const btn = select.querySelector('.select-btn');
            const menu = select.querySelector('.dropdown-menu');
            const searchInput = select.querySelector('.search-input');
            const itemsContainer = select.querySelector('.dropdown-items');
            const addNewBtn = select.querySelector('.add-new-btn');
            const hiddenInput = select.querySelector('input[type="hidden"]');
            
            let items = [];
            if (type === 'author') items = authors;
            else if (type === 'translator') items = translators;
            else if (type === 'category') items = categories;
            
            function renderItems(filter = '') {
                itemsContainer.innerHTML = '';
                const filtered = items.filter(item => 
                    item.name.toLowerCase().includes(filter.toLowerCase())
                );
                
                if (filtered.length === 0) {
                    itemsContainer.innerHTML = '<div class="no-results">هیچ ئەنجامێک نەدۆزرایەوە</div>';
                    return;
                }
                
                filtered.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'dropdown-item';
                    div.textContent = item.shelf_code ? `${item.name} (${item.shelf_code})` : item.name;
                    div.dataset.id = item.id;
                    div.addEventListener('click', () => {
                        hiddenInput.value = item.id;
                        btn.querySelector('.selected-text').textContent = item.name;
                        // Auto-fill next book code
                        if (type === 'category') {
                            fetch(`fetch_data.php?action=get_next_book_code&category_id=${item.id}`)
                                .then(r => r.json())
                                .then(res => {
                                    if (res.next_code) {
                                        document.getElementById('book_code').value = res.next_code;
                                    }
                                    if (res.suggested_shelf) {
                                        document.getElementById('shelf_code').value = res.suggested_shelf;
                                    }
                                    updateFullCodePreview();
                                });
                                });
                        }
                        menu.classList.remove('show');
                    });
                    itemsContainer.appendChild(div);
                });
            }
            
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                    if (m !== menu) m.classList.remove('show');
                });
                menu.classList.toggle('show');
                renderItems();
                searchInput.value = '';
                searchInput.focus();
            });
            
            searchInput.addEventListener('input', () => {
                renderItems(searchInput.value);
            });
            
            addNewBtn.addEventListener('click', () => {
                menu.classList.remove('show');
                const inputDiv = document.createElement('div');
                inputDiv.className = 'new-item-input show';
                inputDiv.innerHTML = `
                    <input type="text" placeholder="ناوی نوێ بنووسە" class="new-item-name">
                    <button type="button" class="btn-add-item">زیادکردن</button>
                    <button type="button" class="btn-cancel-item">پاشگەزبوونەوە</button>
                `;
                select.appendChild(inputDiv);
                
                const newItemInput = inputDiv.querySelector('.new-item-name');
                const addBtn = inputDiv.querySelector('.btn-add-item');
                const cancelBtn = inputDiv.querySelector('.btn-cancel-item');
                
                newItemInput.focus();
                
                addBtn.addEventListener('click', async () => {
                    const name = newItemInput.value.trim();
                    if (!name) return;
                    
                    const actionType = type === 'author' ? 'add_author' : 
                                     type === 'translator' ? 'add_translator' : 'add_category';
                    
                    const formData = new FormData();
                    formData.append('name', name);
                    
                    try {
                        const response = await fetch(`fetch_data.php?action=${actionType}`, {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            items.push({ id: result.id, name: name });
                            hiddenInput.value = result.id;
                            btn.querySelector('.selected-text').textContent = name;
                            showMessage(result.exists ? 'ناوەکە پێشتر هەبووە' : 'زیادکرا', 'success');
                        } else {
                            showMessage(result.message, 'error');
                        }
                    } catch (err) {
                        showMessage('هەڵە لە پەیوەندی', 'error');
                    }
                    
                    inputDiv.remove();
                });
                
                cancelBtn.addEventListener('click', () => {
                    inputDiv.remove();
                });
            });
            
            document.addEventListener('click', (e) => {
                if (!select.contains(e.target)) {
                    menu.classList.remove('show');
                }
            });
        });
        
        document.getElementById('book-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const category_id = document.getElementById('category_id').value;
            if (!category_id) { showMessage('تکایە بەش هەڵبژێرە', 'error'); return; }
            
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('title', document.getElementById('title').value);
            formData.append('author_id', document.getElementById('author_id').value || '');
            formData.append('translator_id', document.getElementById('translator_id').value || '');
            formData.append('category_id', document.getElementById('category_id').value || '');
            formData.append('quantity', document.getElementById('quantity').value);
            formData.append('book_code', document.getElementById('book_code').value);
            formData.append('shelf_code', document.getElementById('shelf_code').value);
            formData.append('publish_year', document.getElementById('publish_year').value);
            formData.append('language', document.getElementById('language').value);
            
            try {
                const response = await fetch('save_book.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    showMessage(result.message, 'success');
                    document.getElementById('book-form').reset();
                    document.querySelectorAll('.smart-select .selected-text').forEach(el => {
                        el.textContent = '-- هەڵبژاردن --';
                    });
                    document.querySelectorAll('input[type="hidden"]').forEach(el => el.value = '');
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (err) {
                showMessage('هەڵە لە پەیوەندی', 'error');
            }
        });
        
        function showMessage(text, type) {
            const msgArea = document.getElementById('message-area');
            msgArea.innerHTML = `<div class="message ${type}">${text}</div>`;
            setTimeout(() => msgArea.innerHTML = '', 5000);
        }
        
        document.getElementById('quantity').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        document.getElementById('title').addEventListener('input', function() {
            this.value = this.value.replace(/^[0-9]+$/, '');
        });
        
        document.getElementById('title').addEventListener('keypress', function(e) {
            if (/^[0-9]$/.test(e.key) && this.value.length === 0) {
                e.preventDefault();
            }
        });
        // Force English digits for book code (numbers only)
        document.getElementById('book_code')?.addEventListener('input', function() {
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
        document.getElementById('shelf_code')?.addEventListener('input', function() {
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
            const code = document.getElementById('book_code')?.value || '';
            const shelf = document.getElementById('shelf_code')?.value || '';
            const preview = document.getElementById('full_code_preview');
            if (!preview) return;
            if (code || shelf) {
                preview.style.display = 'block';
                preview.innerHTML = `کۆدی ناسنامەی کتێب: <span style="font-family:monospace; letter-spacing:1px;">${shelf}${code ? (shelf ? '-' : '') + code : ''}</span>`;
            } else {
                preview.style.display = 'none';
            }
        }
        document.getElementById('book_code')?.addEventListener('input', updateFullCodePreview);
        document.getElementById('shelf_code')?.addEventListener('input', updateFullCodePreview);
    </script>
</body>
</html>
