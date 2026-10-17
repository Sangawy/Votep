<?php
session_start();
if (!isset($_SESSION['public_admin'])) {
    header("Location: login.php");
    exit;
}
require_once '../db_config.php';
$conn = getPublicDB();

// Handle Search Query
$search = $_GET['search'] ?? '';
$query = "SELECT * FROM public_books";
if (!empty($search)) {
    $esc = $conn->real_escape_string($search);
    $query .= " WHERE title LIKE '%$esc%' OR author_name LIKE '%$esc%' OR book_code LIKE '%$esc%' OR shelf_code LIKE '%$esc%'";
}
$query .= " ORDER BY id DESC";
$res = $conn->query($query);

$settings_res = $conn->query("SELECT * FROM public_settings WHERE id = 1");
$site = $settings_res->fetch_assoc();
$site_color = $site['primary_color'] ?? '#5a0202';
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>بەڕێوەبردنی کتێبەکان</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?php echo $site_color; ?>;
            --primary-light: <?php echo $site_color; ?>15;
            --primary-hover: <?php echo $site_color; ?>e0;
            --dark: #1e293b;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Noto Sans Arabic', sans-serif;
            background: var(--bg);
            color: var(--dark);
            padding: 15px;
            line-height: 1.5;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Glassmorphic Responsive Header */
        .header {
            background: #fff;
            padding: 20px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            border: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-title h2 {
            font-size: 1.3rem;
            color: var(--primary);
            font-weight: 700;
        }

        .header-title p {
            font-size: 0.82rem;
            color: #64748b;
            margin-top: 3px;
        }

        /* Toolbar Actions */
        .toolbar {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }

        .search-box {
            display: flex;
            width: 100%;
            background: #fff;
            border-radius: 14px;
            border: 1.5px solid var(--border);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .search-box input {
            flex-grow: 1;
            padding: 14px 16px;
            border: none;
            outline: none;
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--dark);
        }

        .search-box button {
            padding: 0 20px;
            background: var(--primary);
            color: #fff;
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-weight: bold;
            transition: 0.2s;
        }

        .search-box button:hover {
            background: var(--primary-hover);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        /* Buttons Styling */
        .btn {
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.88rem;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-add {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 10px rgba(90, 2, 2, 0.15);
            flex-grow: 1;
        }

        .btn-add:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-back {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-back:hover {
            background: #e2e8f0;
        }

        /* Desktop View Table (hidden on mobile) */
        .desktop-table-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--border);
            display: block;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 16px;
            text-align: right;
            border-bottom: 1px solid #f1f5f9;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .table-book-img {
            width: 45px;
            height: 65px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            background: #f1f5f9;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
            white-space: nowrap;
        }

        /* Column Specific Width Rules */
        .col-book-img {
            width: 75px;
            text-align: center;
        }
        .col-book-title {
            /* Automatic flexible width */
        }
        .col-book-cat {
            width: 130px;
            white-space: nowrap;
        }
        .col-book-code {
            width: 140px;
            white-space: nowrap;
        }
        .col-book-qty {
            width: 110px;
            white-space: nowrap;
        }
        .col-book-actions {
            width: 220px;
            white-space: nowrap;
        }

        .action-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            width: 100%;
        }

        @media (min-width: 850px) {
            .action-group {
                flex-direction: row;
                gap: 8px;
            }
        }

        .btn-action {
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
            white-space: nowrap;
            width: 100%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        @media (min-width: 850px) {
            .btn-action {
                width: auto;
            }
        }

        .btn-edit {
            background: #e0f2fe;
            color: #0369a1;
        }

        .btn-edit:hover {
            background: #bae6fd;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-delete:hover {
            background: #fecaca;
            transform: translateY(-1px);
        }

        /* Mobile Card Grid (hidden on desktop) */
        .mobile-cards-container {
            display: none;
            flex-direction: column;
            gap: 15px;
        }

        .book-mobile-card {
            background: #fff;
            border-radius: 18px;
            padding: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            display: flex;
            gap: 15px;
            position: relative;
        }

        .mobile-cover-wrapper {
            flex-shrink: 0;
        }

        .mobile-book-img {
            width: 75px;
            height: 110px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        }

        .mobile-details {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .mobile-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
            line-height: 1.3;
        }

        .mobile-author {
            font-size: 0.85rem;
            color: #475569;
            margin-bottom: 6px;
        }

        .mobile-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
        }

        .mobile-actions {
            display: flex;
            gap: 8px;
            margin-top: 5px;
        }

        .mobile-btn {
            flex: 1;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        /* Responsive Breakpoints */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            .desktop-table-card {
                display: none;
            }
            .mobile-cards-container {
                display: flex;
            }
            .header {
                border-radius: 16px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Top Toolbar Header -->
        <div class="header">
            <div class="header-title">
                <h2>بەڕێوەبردنی کتێبخانەی ئۆنلاین</h2>
                <p><?php echo htmlspecialchars($site['system_name'] ?? 'مەڵبەندی پیرخدر'); ?></p>
            </div>
            <div class="action-buttons">
                <a href="dashboard.php" class="btn btn-back">📊 داشبۆرد</a>
            </div>
        </div>

        <!-- Search Bar & Add Button -->
        <div class="toolbar">
            <form class="search-box" method="GET">
                <input type="text" name="search" placeholder="بگەڕێ لە ناونیشان، نووسەر، کۆد..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">🔍</button>
            </form>
            <a href="book_edit.php" class="btn btn-add">➕ زیادکردنی کتێبی نوێ</a>
        </div>

        <!-- 1. DESKTOP VIEW TABLE (Visible on tablets and PCs) -->
        <div class="desktop-table-card">
            <table>
                <thead>
                    <tr>
                        <th class="col-book-img">بەرگ</th>
                        <th class="col-book-title">ناونیشان</th>
                        <th class="col-book-title">نووسەر / وەرگێڕ</th>
                        <th class="col-book-cat">پۆلێن</th>
                        <th class="col-book-code">ڕەفە - کۆد</th>
                        <th class="col-book-qty">بەردەست</th>
                        <th class="col-book-actions">کردارەکان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($res && $res->num_rows > 0): ?>
                        <?php while($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td class="col-book-img">
                                    <?php 
                                    $cover_src = 'https://via.placeholder.com/45x65?text=No+Cover';
                                    if (!empty($row['cover_image'])) {
                                        $cover_src = (strpos($row['cover_image'], 'http') === 0) 
                                            ? $row['cover_image'] 
                                            : '../' . $row['cover_image'];
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($cover_src); ?>" class="table-book-img">
                                </td>
                                <td class="col-book-title" style="font-weight:700; color:var(--primary);"><?php echo htmlspecialchars($row['title']); ?></td>
                                <td class="col-book-title">
                                    <div><strong>✍️:</strong> <?php echo htmlspecialchars($row['author_name'] ?: '—'); ?></div>
                                    <?php if (!empty($row['translator_name'])): ?>
                                        <div style="font-size: 0.78rem; color: #64748b; margin-top:3px;"><strong>🌍:</strong> <?php echo htmlspecialchars($row['translator_name']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="col-book-cat"><span class="badge"><?php echo htmlspecialchars($row['category_name'] ?: '—'); ?></span></td>
                                <td class="col-book-code" dir="ltr" style="text-align: right;">
                                    <span style="font-family: monospace; font-weight: bold; background: #f1f5f9; padding: 6px 12px; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap;">
                                        <?php echo htmlspecialchars(($row['shelf_code'] ?: '') . ' - ' . ($row['book_code'] ?: '')); ?>
                                    </span>
                                </td>
                                <td class="col-book-qty" style="font-weight: 700; color: <?php echo $row['available'] > 0 ? '#16a34a' : '#dc2626'; ?>; white-space: nowrap;">
                                    <?php echo (int)$row['available'] . ' / ' . (int)$row['quantity']; ?>
                                </td>
                                <td class="col-book-actions">
                                    <div class="action-group">
                                        <a href="book_edit.php?id=<?php echo $row['id']; ?>" class="btn-action btn-edit">✍️ دەستکاری</a>
                                        <button onclick="deleteBook(<?php echo $row['id']; ?>)" class="btn-action btn-delete">🗑️ سڕینەوە</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; color:#64748b; padding:50px;">هیچ کتێبێک نەدۆزرایەوە</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. MOBILE VIEW CARD LIST (Visible on mobile screens) -->
        <div class="mobile-cards-container">
            <?php if ($res && $res->num_rows > 0): ?>
                <?php 
                // Reset result pointer to reuse results
                $res->data_seek(0);
                while($row = $res->fetch_assoc()): 
                ?>
                    <div class="book-mobile-card">
                        <div class="mobile-cover-wrapper">
                            <?php 
                            $cover_src = 'https://via.placeholder.com/75x110?text=No+Cover';
                            if (!empty($row['cover_image'])) {
                                $cover_src = (strpos($row['cover_image'], 'http') === 0) 
                                    ? $row['cover_image'] 
                                    : '../' . $row['cover_image'];
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($cover_src); ?>" class="mobile-book-img">
                        </div>
                        <div class="mobile-details">
                            <div>
                                <h3 class="mobile-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                                <div class="mobile-author">✍️ نووسەر: <?php echo htmlspecialchars($row['author_name'] ?: '—'); ?></div>
                                <div class="mobile-meta-row">
                                    <span class="badge" style="background:#f1f5f9;"><?php echo htmlspecialchars($row['category_name'] ?: '—'); ?></span>
                                    <span class="badge" style="background:#fef2f2; color:var(--primary); font-family:monospace;"><?php echo htmlspecialchars($row['shelf_code'] . ' - ' . $row['book_code']); ?></span>
                                    <span class="badge" style="background:<?php echo $row['available'] > 0 ? '#f0fdf4; color:#16a34a;' : '#fef2f2; color:#dc2626;'; ?>">
                                        بەردەست: <?php echo (int)$row['available'] . ' / ' . (int)$row['quantity']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="mobile-actions">
                                <a href="book_edit.php?id=<?php echo $row['id']; ?>" class="mobile-btn btn-edit">✍️ دەستکاری</a>
                                <button onclick="deleteBook(<?php echo $row['id']; ?>)" class="mobile-btn btn-delete">🗑️ سڕینەوە</button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align:center; color:#64748b; padding:40px; background:#fff; border-radius:18px; box-shadow:var(--shadow);">هیچ کتێبێک نەدۆزرایەوە</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function deleteBook(id) {
            if (confirm('ئایا دڵنیایت لە سڕینەوەی ئەم کتێبە؟')) {
                window.location.href = 'book_process.php?action=delete&id=' + id;
            }
        }
    </script>
</body>
</html>
