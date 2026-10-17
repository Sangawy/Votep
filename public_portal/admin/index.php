<?php
session_start();
if (!isset($_SESSION['public_admin'])) {
    header("Location: login.php");
    exit;
}
require_once '../db_config.php';
$conn = getPublicDB();

// Fetch activities
$res = $conn->query("SELECT * FROM public_activities ORDER BY activity_date DESC, id DESC");

$settings_res = $conn->query("SELECT * FROM public_settings WHERE id = 1");
$site = $settings_res->fetch_assoc();
$site_color = $site['primary_color'] ?? '#5a0202';
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>بەڕێوەبردنی چالاکییەکان</title>
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
            gap: 10px;
            width: 100%;
            margin-bottom: 20px;
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

        .btn-logout {
            background: #fef2f2;
            color: #b91c1c;
        }

        .btn-logout:hover {
            background: #fee2e2;
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

        .activity-img {
            width: 70px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-pub {
            background: #e6f4ea;
            color: #1e7e34;
        }

        .status-draft {
            background: #fff3cd;
            color: #856404;
        }

        /* Column Specific Width Rules */
        .col-img {
            width: 90px;
            text-align: center;
        }
        .col-title {
            /* Automatic flexible width */
        }
        .col-date {
            width: 140px;
            white-space: nowrap;
        }
        .col-status {
            width: 130px;
            white-space: nowrap;
        }
        .col-actions {
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

        .activity-mobile-card {
            background: #fff;
            border-radius: 18px;
            padding: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            display: flex;
            gap: 15px;
            position: relative;
        }

        .mobile-img-wrapper {
            flex-shrink: 0;
        }

        .mobile-act-img {
            width: 80px;
            height: 80px;
            border-radius: 10px;
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
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .mobile-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
            align-items: center;
        }

        .mobile-date {
            font-size: 0.78rem;
            color: #64748b;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .mobile-actions {
            display: flex;
            gap: 8px;
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
            .toolbar {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Top Responsive Header -->
        <div class="header">
            <div class="header-title">
                <h2>بەڕێوەبردنی چالاکییەکان</h2>
                <p><?php echo htmlspecialchars($site['system_name'] ?? 'مەڵبەندی پیرخدر'); ?></p>
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="dashboard.php" class="btn btn-back">📊 داشبۆرد</a>
                <a href="logout.php" class="btn btn-logout">🚪 چوونەدەرەوە</a>
            </div>
        </div>

        <!-- Add Button -->
        <div class="toolbar">
            <a href="activity_edit.php" class="btn btn-add">➕ زیادکردنی چالاکی نوێ</a>
        </div>

        <!-- 1. DESKTOP VIEW TABLE (Visible on tablets and PCs) -->
        <div class="desktop-table-card">
            <table>
                <thead>
                    <tr>
                        <th class="col-img">وێنە</th>
                        <th class="col-title">ناونیشان</th>
                        <th class="col-date">بەروار</th>
                        <th class="col-status">دۆخ</th>
                        <th class="col-actions">کردارەکان</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($res && $res->num_rows > 0): ?>
                        <?php while($row = $res->fetch_assoc()): ?>
                            <tr>
                                <td class="col-img">
                                    <?php 
                                    $img_src = 'https://via.placeholder.com/70x50?text=No+Img';
                                    if (!empty($row['image_path'])) {
                                        $img_src = (strpos($row['image_path'], 'http') === 0) 
                                            ? $row['image_path'] 
                                            : '../' . $row['image_path'];
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($img_src); ?>" class="activity-img">
                                </td>
                                <td class="col-title" style="font-weight:700; color:var(--dark);"><?php echo htmlspecialchars($row['title']); ?></td>
                                <td class="col-date">
                                    <span style="font-family: monospace; font-size:0.85rem; font-weight:600; color: #475569; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;">
                                        📅 <?php echo htmlspecialchars($row['activity_date']); ?>
                                    </span>
                                </td>
                                <td class="col-status">
                                    <span class="badge <?php echo $row['is_published'] ? 'status-pub' : 'status-draft'; ?>">
                                        <?php echo $row['is_published'] ? '🟢 بڵاوکراوە' : '🟡 ڕەشنووس'; ?>
                                    </span>
                                </td>
                                <td class="col-actions">
                                    <div class="action-group">
                                        <a href="activity_edit.php?id=<?php echo $row['id']; ?>" class="btn-action btn-edit">✍️ دەستکاری</a>
                                        <button onclick="deleteActivity(<?php echo $row['id']; ?>)" class="btn-action btn-delete">🗑️ سڕینەوە</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align:center; color:#64748b; padding:50px;">هیچ چالاکییەک نەدۆزرایەوە</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. MOBILE VIEW CARD LIST (Visible on mobile screens) -->
        <div class="mobile-cards-container">
            <?php if ($res && $res->num_rows > 0): ?>
                <?php 
                $res->data_seek(0); // Reset result pointer
                while($row = $res->fetch_assoc()): 
                    $img_src = 'https://via.placeholder.com/80x80?text=No+Img';
                    if (!empty($row['image_path'])) {
                        $img_src = (strpos($row['image_path'], 'http') === 0) 
                            ? $row['image_path'] 
                            : '../' . $row['image_path'];
                    }
                ?>
                    <div class="activity-mobile-card">
                        <div class="mobile-img-wrapper">
                            <img src="<?php echo htmlspecialchars($img_src); ?>" class="mobile-act-img">
                        </div>
                        <div class="mobile-details">
                            <div>
                                <h3 class="mobile-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                                <div class="mobile-meta-row">
                                    <span class="mobile-date">📅 <?php echo htmlspecialchars($row['activity_date']); ?></span>
                                    <span class="badge <?php echo $row['is_published'] ? 'status-pub' : 'status-draft'; ?>" style="font-size:0.7rem; padding: 2px 8px;">
                                        <?php echo $row['is_published'] ? 'بڵاوکراوە' : 'ڕەشنووس'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="mobile-actions">
                                <a href="activity_edit.php?id=<?php echo $row['id']; ?>" class="mobile-btn btn-edit">✍️ دەستکاری</a>
                                <button onclick="deleteActivity(<?php echo $row['id']; ?>)" class="mobile-btn btn-delete">🗑️ سڕینەوە</button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align:center; color:#64748b; padding:40px; background:#fff; border-radius:18px; box-shadow:var(--shadow);">هیچ چالاکییەک نەدۆزرایەوە</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function deleteActivity(id) {
            if (confirm('ئایا دڵنیایت لە سڕینەوەی ئەم چالاکییە؟')) {
                window.location.href = 'activity_process.php?action=delete&id=' + id;
            }
        }
    </script>
</body>
</html>
