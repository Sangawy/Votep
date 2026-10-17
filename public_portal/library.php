<?php
require_once 'db_config.php';
$conn = getPublicDB();

// Site settings
$settings_res = $conn->query("SELECT * FROM public_settings WHERE id = 1");
$site = $settings_res->fetch_assoc();
$site_name     = $site['system_name']    ?? 'مەڵبەندی پیرخدر';
$site_subtitle = $site['system_subtitle'] ?? 'ساداتی پیرخدری شاهۆ';
$site_color    = $site['primary_color']  ?? '#0c7179';

// Logo
$display_logo = $site['logo_path'] ?? '';
if (!empty($display_logo) && !file_exists($display_logo) && file_exists('../' . $display_logo)) {
    $display_logo = '../' . $display_logo;
}

// Search & filter
$search   = trim($_GET['q'] ?? '');
$category = trim($_GET['cat'] ?? '');
$page_num = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page_num - 1) * $per_page;

// Build query
$where = "WHERE 1=1";
$params = [];
$types  = '';
if ($search !== '') {
    $where .= " AND (title LIKE ? OR author_name LIKE ? OR shelf_code LIKE ? OR book_code LIKE ? OR CONCAT(shelf_code, '-', book_code) LIKE ? OR CONCAT(shelf_code, book_code) LIKE ? OR description LIKE ?)";
    $s = "%$search%";
    // Bind 7 times for the 7 placeholders
    $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s; $params[] = $s;
    $types .= 'sssssss';
}
if ($category !== '') {
    $where .= " AND category_name = ?";
    $params[] = $category;
    $types .= 's';
}

// Count
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM public_books $where");
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total_books);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = max(1, ceil($total_books / $per_page));

// Books
$params_page = $params;
$types_page  = $types . 'ii';
$params_page[] = $per_page;
$params_page[] = $offset;
$books_stmt = $conn->prepare("SELECT * FROM public_books $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$books_stmt->bind_param($types_page, ...$params_page);
$books_stmt->execute();
$books_res = $books_stmt->get_result();

// Categories
$cats_res = $conn->query("SELECT DISTINCT category_name FROM public_books WHERE category_name IS NOT NULL AND category_name != '' ORDER BY category_name");
$categories = [];
while ($cr = $cats_res->fetch_assoc()) $categories[] = $cr['category_name'];

// Visitor Counter
$check_col = $conn->query("SHOW COLUMNS FROM `public_settings` LIKE 'visitor_count'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE `public_settings` ADD `visitor_count` INT DEFAULT 0");
}
$conn->query("UPDATE public_settings SET visitor_count = visitor_count + 1 WHERE id = 1");
$v_res = $conn->query("SELECT visitor_count FROM public_settings WHERE id = 1");
$visitor_count = $v_res->fetch_assoc()['visitor_count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>کتێبخانە - <?php echo htmlspecialchars($site_name); ?></title>
    <meta name="description" content="گەڕان لە نێوان کتێبەکانی کتێبخانەی <?php echo htmlspecialchars($site_name); ?>">

    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;700;900&display=swap" rel="stylesheet">
    <style>
        @font-face { font-family: 'Rabar'; src: url('../assets/font/Rabar_022.ttf') format('truetype'); }
        @font-face { font-family: 'RabarBold'; src: url('../assets/font/Rabar_005.ttf') format('truetype'); font-weight:bold; }

        :root {
            --primary: <?php echo $site_color; ?>;
            --primary-light: <?php echo $site_color; ?>20;
            --gold: #d4af37;
            --dark: #1a1a1a;
            --gray: #f4f4f6;
            --border: #e8e8e8;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --radius: 16px;
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Rabar','Noto Sans Arabic',sans-serif; background:#f7f8fc; color:var(--dark); direction:rtl; }
        a { text-decoration:none; color:inherit; }

        /* ===== HEADER ===== */
        header {
            background:#fff;
            border-bottom:1px solid var(--border);
            padding:14px 5%;
            display:flex; justify-content:space-between; align-items:center;
            position:sticky; top:0; z-index:100;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        .logo { display:flex; align-items:center; gap:12px; }
        .logo img { height:44px; width:auto; border-radius:8px; }
        .logo-box { width:44px; height:44px; background:var(--primary); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-family:'RabarBold'; font-size:1.3rem; }
        .logo-text h1 { font-size:1.2rem; color:var(--primary); font-family:'RabarBold'; }
        .logo-text p { font-size:0.8rem; color:#888; }
        nav ul { display:flex; gap:25px; list-style:none; }
        nav a { color:#555; font-size:0.9rem; transition:0.2s; }
        nav a:hover { color:var(--primary); }
        .btn-portal { background:var(--primary); color:#fff !important; padding:9px 22px; border-radius:30px; font-size:0.85rem; }

        /* ===== PAGE HERO ===== */
        .page-hero {
            background: linear-gradient(135deg, var(--primary) 0%, #0a5a60 100%);
            color: #fff;
            padding: 60px 5% 40px;
            position: relative;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute; top: -50px; left: -50px;
            width: 300px; height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .page-hero::after {
            content: '';
            position: absolute; bottom: -80px; right: 10%;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
        }
        .page-hero-content { position: relative; z-index: 1; text-align: center; }
        .page-hero h2 { font-family:'RabarBold'; font-size:2.2rem; margin-bottom:8px; }
        .page-hero p { font-size:1rem; opacity:0.85; margin-bottom:25px; }

        /* Search bar inside hero */
        .search-bar {
            display: flex; gap: 0;
            max-width: 600px; margin: 0 auto;
            background: #fff; border-radius: 50px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }
        .search-bar input {
            flex: 1; border: none; outline: none;
            padding: 14px 25px; font-family:'Rabar'; font-size:0.95rem; color:var(--dark);
            background: transparent;
        }
        .search-bar button[type="submit"] {
            background: var(--gold); border: none; cursor: pointer;
            padding: 14px 30px; color: #1a1a1a; font-family:'RabarBold';
            font-size:0.95rem; transition:0.2s;
            display: flex; align-items: center; gap: 8px;
        }
        .search-bar button[type="submit"]:hover { background: #c49f2f; }
        .search-clear {
            background: none !important; border: none; cursor: pointer;
            padding: 0 12px !important; color: #bbb !important; font-size: 1.1rem;
            display: none; transition: 0.2s;
            flex-shrink: 0;
        }
        .search-clear:hover { color: var(--primary) !important; }

        /* Stats */
        .hero-stats { display:flex; gap:40px; margin-top:30px; justify-content: center; }
        .stat { font-size:0.85rem; opacity:0.8; }
        .stat strong { display:block; font-size:1.5rem; font-family:'RabarBold'; opacity:1; }

        /* ===== MAIN LAYOUT ===== */
        .main-layout {
            max-width: 96%;
            margin: 0 auto;
            padding: 30px 0 100px;
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 30px;
            align-items: start;
        }

        /* ===== SIDEBAR ===== */
        .sidebar { position: sticky; top: 80px; }
        .sidebar-card {
            background: #fff; border-radius: var(--radius);
            box-shadow: var(--shadow); padding: 20px; margin-bottom: 20px;
            border: 1px solid var(--border);
        }
        .sidebar-card h4 {
            font-family:'RabarBold'; color:var(--primary);
            font-size:1rem; margin-bottom:15px;
            padding-bottom:10px; border-bottom:2px solid var(--primary-light);
        }
        .cat-list { list-style:none; }
        .cat-list li { margin-bottom:5px; }
        .cat-list a {
            display:flex; justify-content:space-between; align-items:center;
            padding:9px 12px; border-radius:10px; color:#555; font-size:0.88rem;
            transition:0.2s;
        }
        .cat-list a:hover, .cat-list a.active {
            background:var(--primary-light); color:var(--primary); font-family:'RabarBold';
        }
        .cat-count { background:var(--gray); padding:2px 8px; border-radius:20px; font-size:0.75rem; }

        /* ===== BOOKS GRID ===== */
        .books-toolbar {
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:20px; flex-wrap:wrap; gap:10px;
        }
        .toolbar-info { font-size:0.9rem; color:#777; }
        .toolbar-info strong { color:var(--primary); font-family:'RabarBold'; }
        .view-toggle { display:flex; gap:5px; }
        .view-btn {
            width:36px; height:36px; border:1px solid var(--border); background:#fff;
            border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center;
            font-size:1rem; transition:0.2s;
        }
        .view-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }

        .books-grid {
            display:grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap:20px;
        }
        .books-grid.list-view { grid-template-columns: 1fr; }

        /* Book Card */
        .book-card {
            background:#fff; border-radius:var(--radius); overflow:hidden;
            box-shadow:var(--shadow); border:1px solid var(--border);
            transition:0.3s; cursor:pointer;
        }
        .book-card:hover { transform:translateY(-5px); box-shadow:0 12px 35px rgba(0,0,0,0.12); }
        .book-cover {
            width:100%; aspect-ratio:2/3;
            background:#eee; background-size:cover; background-position:center;
            position:relative; overflow:hidden;
        }
        .book-cover-overlay {
            position:absolute; inset:0;
            background:linear-gradient(to top, rgba(0,0,0,0.5) 0%, transparent 50%);
            opacity:0; transition:0.3s;
        }
        .book-card:hover .book-cover-overlay { opacity:1; }
        .book-cat-badge {
            position:absolute; top:10px; right:10px;
            background:var(--gold); color:#1a1a1a;
            padding:3px 10px; border-radius:20px; font-size:0.72rem; font-family:'RabarBold';
        }
        .book-info { padding:14px; }
        .book-title { font-family:'RabarBold'; font-size:0.92rem; color:var(--dark); margin-bottom:5px; line-height:1.4; }
        .book-author { font-size:0.78rem; color:#888; }
        .book-code { font-size:0.75rem; color:var(--primary); margin-top:6px; font-family:monospace; }

        /* List view card */
        .books-grid.list-view .book-card { display:flex; align-items:center; gap:15px; }
        .books-grid.list-view .book-cover { width:70px; height:100px; aspect-ratio:unset; flex-shrink:0; }
        .books-grid.list-view .book-info { flex:1; padding:15px 0; }

        /* Empty state */
        .empty-state {
            grid-column: 1/-1; text-align:center; padding:80px 20px; color:#aaa;
        }
        .empty-state-icon { font-size:4rem; margin-bottom:20px; display:block; }
        .empty-state h3 { font-family:'RabarBold'; color:#ccc; font-size:1.3rem; margin-bottom:10px; }

        /* Pagination */
        .pagination { display:flex; gap:8px; justify-content:center; margin-top:40px; flex-wrap:wrap; }
        .page-btn {
            padding:8px 16px; border:1px solid var(--border); border-radius:10px;
            background:#fff; color:#555; cursor:pointer; font-family:'Rabar'; font-size:0.9rem;
            transition:0.2s; text-decoration:none;
        }
        .page-btn:hover, .page-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }
        .page-btn.disabled { opacity:0.4; pointer-events:none; }

        /* ===== BOOK DETAIL MODAL ===== */
        .modal-backdrop {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,0.55); backdrop-filter:blur(6px);
            z-index:5000; align-items:center; justify-content:center; padding:16px;
        }
        .modal-backdrop.open { display:flex; }
        .modal-box {
            background:#fff; border-radius:22px;
            width:100%; max-width:520px;
            max-height:92vh; overflow-y:auto;
            animation:modalIn 0.28s cubic-bezier(.34,1.56,.64,1);
            box-shadow:0 30px 90px rgba(0,0,0,0.28);
        }
        @keyframes modalIn { from{opacity:0;transform:scale(0.88) translateY(30px)} to{opacity:1;transform:scale(1) translateY(0)} }

        /* header */
        .modal-header {
            background:var(--primary); color:#fff;
            padding:18px 22px; border-radius:22px 22px 0 0;
            display:flex; justify-content:space-between; align-items:center;
        }
        .modal-header h3 { font-family:'RabarBold'; font-size:1rem; letter-spacing:0.5px; }
        .modal-close {
            background:rgba(255,255,255,0.18); border:none; color:#fff;
            width:30px; height:30px; border-radius:50%; cursor:pointer;
            font-size:1rem; display:flex; align-items:center; justify-content:center;
            transition:0.2s; flex-shrink:0;
        }
        .modal-close:hover { background:rgba(255,255,255,0.35); }

        /* cover — full width hero */
        .modal-cover-hero {
            width:100%; height:210px;
            background:#ddd; background-size:cover; background-position:center;
            position:relative;
        }
        .modal-cover-hero::after {
            content:''; position:absolute; inset:0;
            background:linear-gradient(to top, rgba(0,0,0,0.55) 0%, transparent 60%);
        }
        .modal-cover-hero .modal-cat-badge {
            position:absolute; bottom:14px; right:16px; z-index:2;
            background:var(--gold); color:#1a1a1a;
            padding:4px 14px; border-radius:20px;
            font-size:0.78rem; font-family:'RabarBold';
        }
        /* fallback cover (no image) */
        .modal-cover-hero.no-img {
            background:linear-gradient(135deg,var(--primary) 0%,#0a3d40 100%);
            display:flex; align-items:center; justify-content:center;
        }
        .modal-cover-hero.no-img::after { display:none; }
        .modal-fallback-icon { font-size:4rem; opacity:0.5; z-index:1; }

        /* content area */
        .modal-content { padding:22px 22px 10px; }
        .modal-title {
            font-family:'RabarBold'; font-size:1.35rem; color:var(--dark);
            line-height:1.4; margin-bottom:18px;
        }

        /* person chips (clickable) */
        .modal-persons { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:14px; }
        .modal-person {
            display:flex; align-items:center; gap:8px;
            background:#f4f4f6; border-radius:12px; padding:10px 16px;
            cursor:pointer; transition:0.2s; border:1.5px solid transparent;
        }
        .modal-person:hover { background:var(--primary-light); border-color:var(--primary); }
        .modal-person-icon { font-size:1.1rem; }
        .modal-person-info { }
        .modal-person-info small { display:block; font-size:0.68rem; color:#aaa; margin-bottom:2px; }
        .modal-person-info strong { font-family:'RabarBold'; color:var(--dark); font-size:0.9rem; }
        .modal-person:hover .modal-person-info strong { color:var(--primary); }

        /* meta chips */
        .modal-meta { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
        .modal-meta-chip {
            background:#f4f4f6; border-radius:10px; padding:7px 14px;
            font-size:0.82rem; color:#555;
        }
        .modal-meta-chip small { display:block; font-size:0.68rem; color:#aaa; margin-bottom:2px; }
        .modal-meta-chip strong { font-family:'RabarBold'; color:var(--dark); }

        /* description */
        .modal-desc-block {
            background:#f8f9fb; border-radius:12px; padding:14px 16px;
            margin-bottom:14px; border-right:3px solid var(--primary);
        }
        .modal-desc-label { font-size:0.72rem; color:var(--primary); font-family:'RabarBold'; margin-bottom:6px; }
        .modal-desc-text { font-size:0.88rem; color:#555; line-height:1.75; }

        .modal-footer { padding:0 22px 18px; }
        .modal-close-btn {
            background:#f0f0f0; border:none; padding:10px 28px;
            border-radius:10px; cursor:pointer; font-family:'Rabar';
            font-size:0.92rem; color:#555; transition:0.2s;
        }
        .modal-close-btn:hover { background:#e0e0e0; }

        /* double-click hint on card */
        .book-card { position:relative; }
        .dbl-hint {
            position:absolute; bottom:70px; left:50%; transform:translateX(-50%);
            background:rgba(0,0,0,0.65); color:#fff; font-size:0.68rem;
            padding:3px 10px; border-radius:20px; white-space:nowrap;
            opacity:0; transition:0.2s; pointer-events:none; z-index:5;
        }
        .book-card:hover .dbl-hint { opacity:1; }

        /* ===== MOBILE NAV ===== */
        .mobile-nav { display:none; }

        @media (max-width: 768px) {
            .main-layout { grid-template-columns:1fr; padding:20px 4% 90px; gap:20px; }
            .sidebar { display:none; }   /* hidden on mobile — replaced by dropdown */
            nav { display:none !important; }
            .books-grid { grid-template-columns: repeat(2, 1fr); gap:12px; }
            .page-hero h2 { font-size:1.6rem; }
            .hero-stats { gap:20px; }
            .mobile-nav {
                display:flex; position:fixed; bottom:0; left:0; width:100%;
                background:#fff; border-top:1px solid #eee; z-index:2000;
                justify-content:space-around; padding:10px 0;
                box-shadow:0 -5px 20px rgba(0,0,0,0.06);
            }
            .mobile-nav a {
                text-decoration:none; color:#777; font-size:0.75rem;
                display:flex; flex-direction:column; align-items:center; gap:4px;
                font-family:'Rabar';
            }
            .mobile-nav a.active { color:var(--primary); }
            .mobile-nav-icon { font-size:1.35rem; }
            .search-bar { flex-direction:row; border-radius:14px; }
            .search-bar input { font-size:0.85rem; }
            .mobile-cat-bar { display:flex !important; }
        }
        @media (min-width: 769px) {
            .mobile-cat-bar { display:none !important; }
        }

        /* ===== Mobile Category Dropdown Bar ===== */
        .mobile-cat-bar {
            display:none;
            align-items:center; gap:10px;
            padding:10px 4% 0;
            direction:rtl;
        }
        .mobile-cat-select {
            flex:1;
            appearance:none; -webkit-appearance:none;
            background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%235a0202' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat left 12px center;
            background-size:18px;
            border:2px solid var(--primary-light); border-radius:14px;
            padding:10px 14px 10px 38px;
            font-family:'RabarBold'; font-size:0.9rem; color:var(--dark);
            cursor:pointer; outline:none; box-shadow:0 2px 10px rgba(0,0,0,0.06);
            transition:border-color 0.2s;
        }
        .mobile-cat-select:focus { border-color:var(--primary); }
        .mobile-cat-badge {
            background:var(--primary); color:#fff;
            font-family:'RabarBold'; font-size:0.75rem;
            padding:5px 12px; border-radius:20px; white-space:nowrap;
            flex-shrink:0;
        }

    </style>
</head>
<body>

    <header>
        <a href="index.php" class="logo">
            <?php if (!empty($display_logo)): ?>
                <img src="<?php echo htmlspecialchars($display_logo); ?>" alt="Logo">
            <?php else: ?>
                <div class="logo-box"><?php echo mb_substr($site_name, 0, 1, 'UTF-8'); ?></div>
            <?php endif; ?>
            <div class="logo-text">
                <h1><?php echo htmlspecialchars($site_name); ?></h1>
                <p><?php echo htmlspecialchars($site_subtitle); ?></p>
            </div>
        </a>
        <nav>
            <ul>
                <li><a href="index.php">سەرەکی</a></li>
                <li><a href="index.php#activities">چالاکییەکان</a></li>
                <li><a href="library.php" style="color:var(--primary); font-family:'RabarBold';">کتێبخانە</a></li>
                <li><a href="index.php#about">دەربارە</a></li>
                <li><a href="../login.php" class="btn-portal">چوونەژوورەوە</a></li>
            </ul>
        </nav>
    </header>

    <!-- Page Hero with Search -->
    <div class="page-hero">
        <div class="page-hero-content">
            <h2>📚 کتێبخانەی دیجیتاڵ</h2>
            <p>گەڕان بکە لە نێوان هەموو کتێبەکانی کتێبخانەکە</p>
            <form method="GET" action="library.php">
                <?php if ($category): ?><input type="hidden" name="cat" value="<?php echo htmlspecialchars($category); ?>"><?php endif; ?>
                <div class="search-bar">
                    <input type="text" name="q" id="searchInput" placeholder="گەڕان بە ناوی کتێب یان نووسەر..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="button" class="search-clear" id="searchClear" onclick="clearSearchInput()">✕</button>
                    <button type="submit">🔍 گەڕان</button>
                </div>
            </form>
            <div class="hero-stats">
                <div class="stat"><strong><?php echo $total_books; ?></strong>کتێبی دۆزراوە</div>
                <div class="stat"><strong><?php echo count($categories); ?></strong>دەستە</div>
                <div class="stat"><strong><?php echo number_format($visitor_count); ?></strong>سەردانکەر</div>
            </div>
        </div>
    </div>

    <!-- Mobile Category Dropdown (shown only on mobile via CSS) -->
    <div class="mobile-cat-bar">
        <select class="mobile-cat-select" id="mobileCatSelect" onchange="mobileCatGo(this)">
            <option value="" <?php echo !$category ? 'selected' : ''; ?>>📂 هەموو دەستەبەندییەکان</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?php echo htmlspecialchars($cat); ?>"
                    <?php echo $category === $cat ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($cat); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <?php if ($category): ?>
        <a href="library.php<?php echo $search ? '?q='.urlencode($search) : ''; ?>"
           class="mobile-cat-badge" title="سڕینەوەی فلتەر">
            ✕ <?php echo htmlspecialchars($category); ?>
        </a>
        <?php endif; ?>
    </div>

    <div class="main-layout">

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-card">
                <h4>📂 دەستەبەندییەکان</h4>
                <ul class="cat-list">
                    <li>
                        <a href="library.php<?php echo $search ? '?q='.urlencode($search) : ''; ?>"
                           class="<?php echo !$category ? 'active' : ''; ?>">
                            <span>هەموو کتێبەکان</span>
                            <span class="cat-count"><?php
                                $c = $conn->query("SELECT COUNT(*) as n FROM public_books");
                                echo $c->fetch_assoc()['n'];
                            ?></span>
                        </a>
                    </li>
                    <?php foreach ($categories as $cat):
                        $cat_count_res = $conn->query("SELECT COUNT(*) as n FROM public_books WHERE category_name = '" . $conn->real_escape_string($cat) . "'");
                        $cat_count = $cat_count_res->fetch_assoc()['n'];
                        $href = 'library.php?cat=' . urlencode($cat) . ($search ? '&q='.urlencode($search) : '');
                    ?>
                    <li>
                        <a href="<?php echo $href; ?>" class="<?php echo $category === $cat ? 'active' : ''; ?>">
                            <span><?php echo htmlspecialchars($cat); ?></span>
                            <span class="cat-count"><?php echo $cat_count; ?></span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if ($search || $category): ?>
            <div class="sidebar-card">
                <h4>🗑️ فلتەرەکان</h4>
                <?php if ($search): ?><p style="font-size:0.85rem; color:#777; margin-bottom:8px;">گەڕان: <strong><?php echo htmlspecialchars($search); ?></strong></p><?php endif; ?>
                <?php if ($category): ?><p style="font-size:0.85rem; color:#777; margin-bottom:12px;">دەستە: <strong><?php echo htmlspecialchars($category); ?></strong></p><?php endif; ?>
                <a href="library.php" style="color:var(--primary); font-size:0.85rem; font-family:'RabarBold';">✕ سڕینەوەی فلتەرەکان</a>
            </div>
            <?php endif; ?>
        </aside>

        <!-- Books Section -->
        <section>
            <div class="books-toolbar">
                <p class="toolbar-info">
                    <?php if ($total_books > 0): ?>
                        <strong><?php echo $total_books; ?></strong> کتێب دۆزراوە
                        <?php if ($total_pages > 1): ?> · لاپەڕە <strong><?php echo $page_num; ?></strong> لە <strong><?php echo $total_pages; ?></strong><?php endif; ?>
                    <?php else: ?>
                        هیچ کتێبێک نەدۆزرایەوە
                    <?php endif; ?>
                </p>
                <div class="view-toggle">
                    <button class="view-btn active" id="grid-btn" onclick="setView('grid')" title="Grid">⊞</button>
                    <button class="view-btn" id="list-btn" onclick="setView('list')" title="List">☰</button>
                </div>
            </div>

            <div class="books-grid" id="books-grid">
                <?php if ($books_res && $books_res->num_rows > 0):
                    while ($bk = $books_res->fetch_assoc()):
                        $cover = $bk['cover_image'] ?: 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?q=80&w=300&auto=format&fit=crop';
                ?>
                <div class="book-card" 
                     ondblclick="openBook(this)"
                     onclick="handleCardClick(this)"
                     data-book='<?php echo htmlspecialchars(json_encode([
                        "title"       => $bk["title"],
                        "author"      => $bk["author_name"]     ?? "",
                        "translator"  => $bk["translator_name"] ?? "",
                        "category"    => $bk["category_name"]   ?? "",
                        "book_code"   => $bk["book_code"]        ?? "",
                        "shelf_code"  => $bk["shelf_code"]       ?? "",
                        "year"        => $bk["publish_year"]     ?? "",
                        "desc"        => $bk["description"]      ?? "",
                        "quantity"    => $bk["quantity"]         ?? 1,
                        "available"   => $bk["available"]        ?? 1,
                        "creator"     => $bk["creator_name"]     ?? "",
                        "cover"       => $cover
                     ]), ENT_QUOTES); ?>'>
                    <div class="book-cover" style="background-image:url('<?php echo htmlspecialchars($cover); ?>');">
                        <div class="book-cover-overlay"></div>
                        <?php if ($bk['category_name']): ?>
                        <span class="book-cat-badge"><?php echo htmlspecialchars($bk['category_name']); ?></span>
                        <?php endif; ?>
                        <span class="dbl-hint">دوو جار کلیک بکە</span>
                    </div>
                    <div class="book-info">
                        <div class="book-title"><?php echo htmlspecialchars($bk['title']); ?></div>
                        <?php if ($bk['author_name']): ?>
                        <div class="book-author">✍️ <?php echo htmlspecialchars($bk['author_name']); ?></div>
                        <?php endif; ?>
                        <?php if ($bk['book_code']): ?>
                        <div class="book-code">#<?php echo htmlspecialchars($bk['shelf_code'] . '-' . $bk['book_code']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php endwhile;
                else: ?>
                <div class="empty-state">
                    <span class="empty-state-icon">📭</span>
                    <h3>هیچ کتێبێک نەدۆزرایەوە</h3>
                    <p style="margin-top:10px; font-size:0.9rem;">هەوڵ بدە پەیوەست بەرپرسی کتێبخانەکە بکەیتەوە.</p>
                    <a href="library.php" style="display:inline-block; margin-top:20px; background:var(--primary); color:#fff; padding:12px 30px; border-radius:30px; font-family:'RabarBold';">هەموو کتێبەکان</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                $base_url = 'library.php?' . ($search ? 'q='.urlencode($search).'&' : '') . ($category ? 'cat='.urlencode($category).'&' : '');
                ?>
                <a href="<?php echo $base_url . 'page=' . max(1, $page_num - 1); ?>" class="page-btn <?php echo $page_num <= 1 ? 'disabled' : ''; ?>">◀ پێشوو</a>
                <?php for ($i = max(1, $page_num - 2); $i <= min($total_pages, $page_num + 2); $i++): ?>
                    <a href="<?php echo $base_url . 'page=' . $i; ?>" class="page-btn <?php echo $i === $page_num ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <a href="<?php echo $base_url . 'page=' . min($total_pages, $page_num + 1); ?>" class="page-btn <?php echo $page_num >= $total_pages ? 'disabled' : ''; ?>">داهاتوو ▶</a>
            </div>
            <?php endif; ?>
        </section>
    </div>

    <!-- Mobile Bottom Nav -->
    <div class="mobile-nav">
        <a href="index.php"><span class="mobile-nav-icon">🏠</span><span>سەرەکی</span></a>
        <a href="index.php#activities"><span class="mobile-nav-icon">📢</span><span>چالاکی</span></a>
        <a href="library.php" class="active"><span class="mobile-nav-icon">📚</span><span>کتێبخانە</span></a>
        <a href="../login.php"><span class="mobile-nav-icon">👤</span><span>ژوورەوە</span></a>
    </div>

    <!-- Book Detail Modal -->
    <div class="modal-backdrop" id="bookModal" onclick="closeOnBackdrop(event)">
        <div class="modal-box">

            <!-- Header -->
            <div class="modal-header">
                <h3>زانیارییەکانی کتێب</h3>
                <button class="modal-close" onclick="closeModal()">&#x2715;</button>
            </div>

            <!-- Cover Hero -->
            <div class="modal-cover-hero" id="modal-cover-hero">
                <span class="modal-cat-badge" id="modal-cat"></span>
            </div>

            <!-- Content -->
            <div class="modal-content">
                <div class="modal-title" id="modal-title"></div>

                <!-- Persons -->
                <div class="modal-persons">
                    <div class="modal-person" id="chip-author" onclick="filterByPerson('author')" title="کلیک بکە بۆ هەموو کتێبەکانی ئەم نووسەر">
                        <span class="modal-person-icon">✍️</span>
                        <div class="modal-person-info">
                            <small>نووسەر</small>
                            <strong id="modal-author">—</strong>
                        </div>
                    </div>
                    <div class="modal-person" id="chip-translator" onclick="filterByPerson('translator')" title="کلیک بکە بۆ کتێبەکانی ئەم وەرگێڕ">
                        <span class="modal-person-icon">🌍</span>
                        <div class="modal-person-info">
                            <small>وەرگێڕ</small>
                            <strong id="modal-translator">—</strong>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="modal-desc-block" id="modal-desc-block">
                    <div class="modal-desc-label">📖 دەربارەی کتێب</div>
                    <div class="modal-desc-text" id="modal-desc"></div>
                </div>

                <!-- Meta chips -->
                <div class="modal-meta" style="justify-content:center; margin-bottom:20px;">
                    <div class="modal-meta-chip" id="chip-year" style="min-width:120px; text-align:center; background:var(--gray);">
                        <small>📅 ساڵی چاپ</small>
                        <strong id="modal-year" style="font-size:1.1rem;">—</strong>
                    </div>
                </div>

                <!-- Detailed Stats Grid (Matching Screenshot 1) -->
                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:1px; background:#eee; border:1px solid #eee; border-radius:12px; overflow:hidden; margin-bottom:15px;">
                    <div style="background:#fff; padding:12px 5px; text-align:center;">
                        <small style="display:block; color:#aaa; font-size:0.65rem; margin-bottom:4px;">کۆدی کتێب</small>
                        <strong id="modal-book-code" style="color:var(--dark); font-size:0.85rem;">—</strong>
                    </div>
                    <div style="background:#fff; padding:12px 5px; text-align:center;">
                        <small style="display:block; color:#aaa; font-size:0.65rem; margin-bottom:4px;">کۆدی ڕەفە</small>
                        <strong id="modal-shelf-code" style="color:#d32f2f; font-size:0.85rem;">—</strong>
                    </div>
                    <div style="background:#fff; padding:12px 5px; text-align:center;">
                        <small style="display:block; color:#aaa; font-size:0.65rem; margin-bottom:4px;">کۆی گشتی</small>
                        <strong id="modal-quantity" style="color:var(--primary); font-size:0.85rem;">—</strong>
                    </div>
                    <div style="background:#fff; padding:12px 5px; text-align:center;">
                        <small style="display:block; color:#aaa; font-size:0.65rem; margin-bottom:4px;">بەردەست</small>
                        <strong id="modal-available" style="color:#1976d2; font-size:0.85rem;">—</strong>
                    </div>
                </div>

                <!-- Creator info -->
                <div id="modal-creator-row" style="display:flex; align-items:center; gap:8px; font-size:0.75rem; color:#888; margin-bottom:10px;">
                    <span>👤 داخڵکراوە لەلایەن:</span>
                    <strong id="modal-creator" style="color:#555;">—</strong>
                </div>

            </div>

            <div class="modal-footer">
                <button class="modal-close-btn" onclick="closeModal()">داخستن</button>
            </div>
        </div>
    </div>

    <script>
        let currentBook = {};

        function openBook(card) {
            const d = JSON.parse(card.getAttribute('data-book'));
            currentBook = d;

            // Title
            document.getElementById('modal-title').textContent = d.title || '—';

            // Cover hero
            const hero = document.getElementById('modal-cover-hero');
            if (d.cover) {
                hero.style.backgroundImage = `url('${d.cover}')`;
                hero.classList.remove('no-img');
                const fi = hero.querySelector('.modal-fallback-icon');
                if (fi) fi.remove();
            } else {
                hero.style.backgroundImage = '';
                hero.classList.add('no-img');
                if (!hero.querySelector('.modal-fallback-icon')) {
                    const ic = document.createElement('span');
                    ic.className = 'modal-fallback-icon'; ic.textContent = '📖';
                    hero.appendChild(ic);
                }
            }

            // Category badge
            const cat = document.getElementById('modal-cat');
            cat.textContent = d.category || '';
            cat.style.display = d.category ? 'inline-block' : 'none';

            // Author
            document.getElementById('modal-author').textContent = d.author || '—';

            // Translator
            const chipTrans = document.getElementById('chip-translator');
            if (d.translator) {
                document.getElementById('modal-translator').textContent = d.translator;
                chipTrans.style.display = 'flex';
            } else {
                chipTrans.style.display = 'none';
            }

            // Year
            const chipYear = document.getElementById('chip-year');
            if (d.year) {
                document.getElementById('modal-year').textContent = d.year;
                chipYear.style.display = 'block';
            } else {
                chipYear.style.display = 'none';
            }

            // Codes & Quantities
            document.getElementById('modal-book-code').textContent = d.book_code || '—';
            document.getElementById('modal-shelf-code').textContent = d.shelf_code || '—';
            document.getElementById('modal-quantity').textContent = d.quantity || '0';
            document.getElementById('modal-available').textContent = d.available || '0';

            // Creator
            const creatorRow = document.getElementById('modal-creator-row');
            if (d.creator) {
                document.getElementById('modal-creator').textContent = d.creator;
                creatorRow.style.display = 'flex';
            } else {
                creatorRow.style.display = 'none';
            }

            // Description
            const descBlock = document.getElementById('modal-desc-block');
            const descText  = document.getElementById('modal-desc');
            if (d.desc && d.desc.trim()) {
                descText.textContent = d.desc;
                descBlock.style.display = 'block';
            } else {
                descBlock.style.display = 'none';
            }

            document.getElementById('bookModal').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function filterByPerson(type) {
            const name = type === 'author' ? currentBook.author : currentBook.translator;
            if (!name) return;
            closeModal();
            window.location.href = 'library.php?q=' + encodeURIComponent(name);
        }

        let lastTap = 0;
        function handleCardClick(card) {
            const now = Date.now();
            if (now - lastTap < 300 && now - lastTap > 0) {
                openBook(card);
                lastTap = 0;
            } else {
                lastTap = now;
            }
        }

        function closeModal() {
            document.getElementById('bookModal').classList.remove('open');
            document.body.style.overflow = '';
        }
        function closeOnBackdrop(e) {
            if (e.target === document.getElementById('bookModal')) closeModal();
        }
        let pressTimer;
        function touchStart(card) { pressTimer = setTimeout(() => openBook(card), 500); }
        function touchEnd() { clearTimeout(pressTimer); }
        document.addEventListener('keydown', e => { if(e.key === 'Escape') closeModal(); });

        function mobileCatGo(sel) {
            const cat = sel.value;
            if (cat === '') {
                window.location.href = 'library.php';
            } else {
                window.location.href = 'library.php?cat=' + encodeURIComponent(cat);
            }
        }

        function setView(v) {
            const grid = document.getElementById('books-grid');
            document.getElementById('grid-btn').classList.toggle('active', v === 'grid');
            document.getElementById('list-btn').classList.toggle('active', v === 'list');
            if (v === 'list') grid.classList.add('list-view');
            else grid.classList.remove('list-view');
            localStorage.setItem('lib-view', v);
        }
        const saved = localStorage.getItem('lib-view');
        if (saved) setView(saved);

        // Search clear logic
        const sInput = document.getElementById('searchInput');
        const sClear = document.getElementById('searchClear');
        function toggleClear() { sClear.style.display = sInput.value ? 'block' : 'none'; }
        if (sInput) {
            sInput.addEventListener('input', toggleClear);
            toggleClear();
        }
        function clearSearchInput() {
            sInput.value = '';
            toggleClear();
            sInput.focus();
        }
    </script>



</body>
</html>
