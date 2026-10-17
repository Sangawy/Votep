<?php
session_start();
if (!isset($_SESSION['public_admin'])) {
    header("Location: login.php");
    exit;
}
require_once '../db_config.php';
$conn = getPublicDB();

// Fetch statistics
$act_res = $conn->query("SELECT COUNT(*) as count FROM public_activities");
$act_count = $act_res ? $act_res->fetch_assoc()['count'] : 0;

$book_res = $conn->query("SELECT COUNT(*) as count FROM public_books");
$book_count = $book_res ? $book_res->fetch_assoc()['count'] : 0;

$settings_res = $conn->query("SELECT * FROM public_settings WHERE id = 1");
$site = $settings_res->fetch_assoc();
$site_color = $site['primary_color'] ?? '#5a0202';
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تەختەی بەڕێوەبردنی پۆرتاڵ</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?php echo $site_color; ?>;
            --primary-light: <?php echo $site_color; ?>15;
            --dark: #1a1a1a;
            --shadow: 0 8px 30px rgba(0,0,0,0.06);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Noto Sans Arabic', sans-serif;
            background-color: #f7f9fc;
            color: var(--dark);
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 40px auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            padding: 25px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 1.5rem;
            color: var(--primary);
        }
        .logout-btn {
            background: #eaecef;
            color: #4b5563;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 0.9rem;
            transition: 0.2s;
        }
        .logout-btn:hover {
            background: #e2e4e8;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #fff;
            padding: 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            text-align: center;
            border-bottom: 4px solid var(--primary);
        }
        .stat-card h3 {
            font-size: 0.95rem;
            color: #6b7280;
            margin-bottom: 10px;
        }
        .stat-card .number {
            font-size: 3rem;
            font-weight: bold;
            color: var(--primary);
        }
        .menu-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .menu-card {
            background: #fff;
            padding: 35px 25px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            text-align: center;
            text-decoration: none;
            color: var(--dark);
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.02);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px var(--primary-light);
            border-color: var(--primary);
        }
        .menu-card .icon {
            font-size: 3rem;
        }
        .menu-card h2 {
            font-size: 1.3rem;
            color: var(--primary);
        }
        .menu-card p {
            font-size: 0.9rem;
            color: #6b7280;
        }
        .footer-link {
            text-align: center;
            margin-top: 40px;
        }
        .footer-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: bold;
            font-size: 0.95rem;
        }
        @media (max-width: 600px) {
            .stats-grid, .menu-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>کۆنترۆڵی بەڕێوەبردنی پۆرتاڵ</h1>
                <p style="font-size:0.85rem; color:#6b7280; margin-top:5px;"><?php echo htmlspecialchars($site['system_name'] ?? 'پیرخدر'); ?></p>
            </div>
            <a href="logout.php" class="logout-btn">🔑 چوونەدەرەوە</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>چالاکییەکان لەسەر پۆرتاڵ</h3>
                <div class="number"><?php echo $act_count; ?></div>
            </div>
            <div class="stat-card">
                <h3>کتێبەکان لە کتێبخانەی ئۆنلاین</h3>
                <div class="number"><?php echo $book_count; ?></div>
            </div>
        </div>

        <div class="menu-grid">
            <a href="index.php" class="menu-card">
                <div class="icon">📅</div>
                <h2>بەڕێوەبردنی چالاکییەکان</h2>
                <p>زیادکردن، سڕینەوە و دەستکاریکردنی هەواڵ و چالاکییەکان</p>
            </a>
            <a href="books.php" class="menu-card">
                <div class="icon">📚</div>
                <h2>بەڕێوەبردنی کتێبەکان</h2>
                <p>زیادکردن و بەڕێوەبردنی سەرجەم کتێب و نامیلکەکان</p>
            </a>
        </div>

        <div class="footer-link">
            <a href="../index.php" target="_blank">🌐 سەردانیکردنی ماڵپەڕی سەرەکی</a>
        </div>
    </div>
</body>
</html>
