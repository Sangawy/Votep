<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
require_once 'includes/notifications.php';
$conn = getDB();

if (isset($_GET['mark_read'])) {
    markNotificationsRead($user_id);
    header('Location: portal.php');
    exit;
}

// Fetch current user and their permissions
$user_id = $_SESSION['user_id'];
$user_data = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

// Very solid check for admin
$current_user = strtolower($user_data['username'] ?? '');
$is_super_admin = ($current_user === 'admin' || $current_user === 'sangawy' || ($user_data['role'] ?? '') === 'owner');
$can_manage = $is_super_admin || (isset($user_data['can_manage_users']) && $user_data['can_manage_users']);

$settings = [
    'system_name' => 'سیستەمی گشتی وەزارەت',
    'description' => 'سیستەمی گشتی بەڕێوەبردنی کارگێڕی و زانیاری',
    'logo' => 'assets/logo.jpg'
];

$sections = [];

if ($conn) {
    if ($res = $conn->query("SELECT system_name, description, logo, sidebar_color FROM settings WHERE id = 1")) {
        if ($row = $res->fetch_assoc()) {
            if (!empty($row['system_name']))
                $settings['system_name'] = $row['system_name'];
            if (!empty($row['description']))
                $settings['description'] = $row['description'];
            if (!empty($row['logo']))
                $settings['logo'] = $row['logo'];
            $settings['sidebar_color'] = $row['sidebar_color'] ?? '#5a0202';
        }
    }


    // Auto-fix Nusinga link and Rename Labels
    $conn->query("UPDATE system_sections SET link = 'docs_dashboard.php' WHERE (section_key = 'docs' OR section_title LIKE '%نوسینگە%')");
    $conn->query("UPDATE system_sections SET link = 'members_list.php' WHERE (section_key = 'nusinga' OR section_title LIKE '%ئەندامان%')");
    $conn->query("UPDATE system_sections SET section_title = 'ئەندامان' WHERE section_key = 'nusinga'");
    $conn->query("UPDATE system_sections SET link = REPLACE(link, 'staff_list.php', 'members_list.php')");
    $conn->query("UPDATE system_sections SET link = REPLACE(link, 'libraray_Peshmerga', 'pirxdry') WHERE link LIKE '%libraray_Peshmerga%'");
    $res_sec = $conn->query("SELECT * FROM system_sections WHERE is_active = 1 ORDER BY id ASC");
    if ($res_sec) {
        while ($s = $res_sec->fetch_assoc()) {
            $title = $s['section_title'] ?? '';
            $key = $s['section_key'] ?? '';

            if (in_array($key, ['activity', 'activities', 'settings'], true) || $title === 'چالاکیەکان' || $title === 'ڕێکخستنەکان') {
                continue;
            }

            if ($key === 'archive' || $title === 'ئەرشیف') {
                $s['section_title'] = 'ئەرشیفی چالاکی';
            }

            $sections[] = $s;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پۆرتاڵی سەرەکی - <?php echo htmlspecialchars($settings['system_name']); ?></title>
    <style>
        @font-face {
            font-family: 'Rabar013';
            src: url('assets/font/Rabar_013.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Rabar022';
            src: url('assets/font/Rabar_022.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        @font-face {
            font-family: 'Rabar005';
            src: url('assets/font/Rabar_005.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        :root {
            --primary:
                <?php echo $settings['sidebar_color'] ?? '#5a0202'; ?>
            ;
            --secondary: #C8A951;
            --accent: #0f8f8c;
            --bg: #eef2f6;
            --ink: #18212f;
            --muted: #667085;
            --card: rgba(255, 255, 255, 0.88);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Rabar022', 'Rabar005', Tahoma, Arial, sans-serif;
            background: var(--bg);
            min-height: 100vh;
            height: 100vh;
            overflow: hidden;
            color: var(--ink);
            text-rendering: geometricPrecision;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: 'Rabar013', 'Rabar022', Tahoma, Arial, sans-serif;
        }

        .portal-header {
            min-height: 30vh;
            background:
                linear-gradient(120deg, rgba(15, 143, 140, 0.92) 0%, rgba(90, 2, 2, 0.88) 72%, rgba(48, 1, 1, 0.95) 100%),
                radial-gradient(circle at 18% 18%, rgba(255, 255, 255, 0.2), transparent 26%);
            color: #fff;
            padding: clamp(28px, 5vh, 54px) 20px clamp(64px, 9vh, 88px);
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .portal-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(200, 169, 81, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .portal-header h1 {
            font-size: clamp(1.9rem, 3vw, 2.75rem);
            font-weight: 800;
            position: relative;
            z-index: 2;
            margin-bottom: 8px;
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }

        .portal-header p {
            font-size: clamp(0.95rem, 1.4vw, 1.12rem);
            opacity: 0.9;
            position: relative;
            z-index: 2;
            font-weight: 400;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .portal-container {
            width: min(1400px, calc(100% - 48px));
            max-height: calc(70vh + 20px);
            margin: clamp(-66px, -7vh, -46px) auto 0;
            padding: 0 0 20px;
            position: relative;
            z-index: 10;
        }

        .portal-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(150px, 1fr));
            gap: clamp(12px, 1.4vw, 18px);
            align-items: stretch;
        }

        .portal-card {
            min-height: clamp(205px, 27vh, 252px);
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(255, 255, 255, 0.86)),
                var(--card);
            backdrop-filter: blur(18px);
            border-radius: 18px;
            padding: clamp(18px, 2.2vh, 26px) clamp(14px, 1.8vw, 20px);
            text-align: center;
            box-shadow: 0 18px 40px rgba(17, 24, 39, 0.10);
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.8);
            position: relative;
            overflow: hidden;
        }

        .portal-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(145deg, rgba(15, 143, 140, 0.10), transparent 45%, rgba(200, 169, 81, 0.12));
            opacity: 0;
            transition: 0.35s;
            pointer-events: none;
        }

        .portal-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, transparent, var(--secondary), transparent);
            transform: translateX(-100%);
            transition: 0.5s;
        }

        .portal-card:hover:not(.locked)::before {
            transform: translateX(100%);
        }

        .portal-card:hover:not(.locked) {
            transform: translateY(-8px);
            box-shadow: 0 24px 52px rgba(17, 24, 39, 0.18);
            border-color: var(--secondary);
            background: #fff;
        }

        .portal-card:hover:not(.locked)::after {
            opacity: 1;
        }

        .portal-card.locked {
            opacity: 0.6;
            cursor: not-allowed;
            filter: grayscale(0.8);
            background: #f0f0f0;
            border: 1px dashed #ccc;
        }

        .portal-card.locked:hover {
            transform: none;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
        }

        .card-icon {
            width: clamp(66px, 7vw, 82px);
            height: clamp(66px, 7vw, 82px);
            background: #fff;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(30px, 3.5vw, 40px);
            margin-bottom: clamp(14px, 2vh, 20px);
            transition: 0.4s;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #f0f0f0;
            position: relative;
            z-index: 1;
        }

        .card-icon img {
            max-width: 74%;
            max-height: 74%;
            object-fit: contain;
        }

        .portal-card:hover:not(.locked) .card-icon {
            transform: scale(1.1) rotate(5deg);
            background: var(--primary);
            color: #fff;
            box-shadow: 0 12px 30px rgba(90, 2, 2, 0.2);
        }

        .portal-card h3 {
            font-size: clamp(1.1rem, 1.55vw, 1.45rem);
            font-weight: 800;
            margin-bottom: 8px;
            color: var(--ink);
            transition: 0.3s;
            position: relative;
            z-index: 1;
        }

        .portal-card:hover:not(.locked) h3 {
            color: var(--primary);
        }

        .portal-card p {
            font-size: clamp(0.78rem, 1vw, 0.9rem);
            color: var(--muted);
            line-height: 1.65;
            margin-bottom: 14px;
            max-width: 22ch;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            position: relative;
            z-index: 1;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 30px;
            font-size: 0.72rem;
            font-weight: 700;
            background: #f1f2f6;
            color: #888;
            transition: 0.3s;
            position: relative;
            z-index: 1;
        }

        .portal-card:hover:not(.locked) .status-badge {
            background: var(--secondary);
            color: var(--primary);
        }

        .status-badge.locked-text {
            background: #eee;
            color: #999;
        }

        .logout-btn {
            position: fixed;
            bottom: 22px;
            left: 24px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            color: #d32f2f;
            padding: 12px 22px;
            border-radius: 14px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            border: 1px solid rgba(211, 47, 47, 0.2);
        }

        .logout-btn:hover {
            background: #d32f2f;
            color: #fff;
            transform: scale(1.05);
        }

        @media (max-width: 1200px) {
            .portal-grid {
                grid-template-columns: repeat(4, minmax(140px, 1fr));
            }
        }

        @media (max-width: 900px) {
            body {
                overflow: auto;
                height: auto;
            }

            .portal-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .portal-header h1 {
                font-size: 1.8rem;
            }

            .portal-container {
                margin-top: -30px;
                width: min(100% - 24px, 420px);
            }

            .portal-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <header class="portal-header">
        <?php
        checkActivityNotifications($user_id);
        $unread = getUnreadNotifications($user_id);
        if (!empty($unread)):
            ?>
            <div id="notif-area" style="position: absolute; top: 20px; right: 20px; z-index: 100; max-width: 300px;">
                <div class="lib-btn lib-btn-gold" onclick="toggleNotifs()"
                    style="cursor:pointer; padding: 10px 20px; border-radius: 50px; display: flex; align-items: center; gap: 10px; background: var(--secondary); color: var(--primary);">
                    🔔 ئاگاداریەکان (<?php echo count($unread); ?>)
                </div>
                <div id="notif-dropdown"
                    style="display:none; position: absolute; top: 50px; right: 0; background: #fff; color: #333; width: 300px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); overflow: hidden; text-align: right;">
                    <div
                        style="padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                        <strong style="color: var(--primary);">ئاگادارییەکان</strong>
                        <a href="?mark_read=1"
                            style="font-size: 0.7rem; color: var(--secondary); text-decoration: none;">هەموویان بخوێنەوە</a>
                    </div>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($unread as $n): ?>
                            <div style="padding: 12px 15px; border-bottom: 1px solid #f9f9f9; font-size: 0.9rem; cursor: pointer;"
                                onclick="this.style.opacity='0.5'">
                                <?php echo htmlspecialchars($n['message']); ?>
                                <div style="font-size: 0.7rem; color: #999; margin-top: 5px;"><?php echo $n['sent_at']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <script>
                function toggleNotifs() {
                    const dd = document.getElementById('notif-dropdown');
                    dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
                }
            </script>
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($settings['system_name']); ?></h1>
        <p><?php echo htmlspecialchars($settings['description']); ?></p>
    </header>

    <div class="portal-container">
        <div class="portal-grid">
            <?php foreach ($sections as $s): ?>
                <?php
                $perm_key = 'can_access_' . $s['section_key'];
                $has_access = (isset($user_data[$perm_key]) && $user_data[$perm_key]) || $is_super_admin;

                if (!$has_access)
                    continue;

                $status_text = ($s['link'] === '#') ? 'بۆ داهاتوو' : 'چالاکە';
                ?>
                <a href="<?php echo $s['link']; ?>" class="portal-card">
                    <div class="card-icon"><?php echo $s['icon']; ?></div>
                    <h3><?php echo htmlspecialchars($s['section_title']); ?></h3>
                    <p><?php echo htmlspecialchars($s['section_description']); ?></p>
                    <span class="status-badge"><?php echo $status_text; ?></span>
                </a>
            <?php endforeach; ?>

            <!-- User Management Module (Admins only) -->
            <?php if ($can_manage): ?>
                <a href="admin_dashboard.php" class="portal-card"
                    style="border: 2px solid var(--secondary); background: #fffcf4;">
                    <div class="card-icon" style="color: #e67e22; background: #fff5e6;">⚙️</div>
                    <h3>کۆنترۆڵ پانێڵ</h3>
                    <p>بەڕێوەبردنی بەکارهێنەران، دیاریکردنی دەسەڵاتەکان، و ڕێکخستنی گشتی سیستم.</p>
                    <span class="status-badge" style="background: var(--secondary); color: var(--primary);">ئەدمین
                        پاشکۆ</span>
                </a>

                <a href="deployer.php" class="portal-card"
                    style="border: 2px solid #6366f1; background: #f5f7ff;">
                    <div class="card-icon" style="color: #4f46e5; background: #eef2ff;">🚀</div>
                    <h3>هاوتاکردن و دیپلۆیمێنت</h3>
                    <p>هاوتاکردنی گۆڕانکارییەکان بۆ هۆست، باکئەپی خێرا، و ناردن بۆ گیت‌هەب (Deployer).</p>
                    <span class="status-badge" style="background: #4f46e5; color: #fff;">Live Sync</span>
                </a>
            <?php endif; ?>

        </div>
    </div>

    <a href="logout.php" class="logout-btn">
        <span>⬅️</span> دەرچوون لە سیستم
    </a>
</body>

</html>
