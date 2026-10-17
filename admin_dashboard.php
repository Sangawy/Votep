<?php
session_start();
require_once 'config.php';
$conn = getDB();

// Fetch current user and their permissions
$user_id = $_SESSION['user_id'] ?? 0;
$user_data = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

// Very solid check for admin
$current_user = strtolower($user_data['username'] ?? '');
$is_super_admin = ($current_user === 'admin' || $current_user === 'sangawy' || ($user_data['role'] ?? '') === 'owner');
$can_manage = $is_super_admin || (isset($user_data['can_manage_users']) && $user_data['can_manage_users']);

if (!$can_manage) {
    header('Location: portal.php');
    exit;
}

$settings = $conn->query("SELECT * FROM settings WHERE id = 1")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>داشبۆردی سەرپەرشتیار - کۆنترۆڵ پانێڵ</title>
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

        :root { --primary: #5a0202; --secondary: #C8A951; --accent: #f39c12; --bg: #f0f2f5; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Rabar022', 'Segoe UI', Tahoma, Arial, sans-serif; background: var(--bg); min-height: 100vh; overflow-x: hidden; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Rabar013', serif; }
        
        .admin-header { 
            background: #fff; padding: 20px 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            display: flex; justify-content: space-between; align-items: center;
        }
        .back-portal { text-decoration: none; color: #64748b; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        
        .main-content { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .welcome-section { margin-bottom: 40px; }
        .welcome-section h1 { font-size: 2rem; color: var(--primary); margin-bottom: 10px; }
        
        .admin-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; }
        
        .admin-card { 
            background: #fff; border-radius: 24px; padding: 40px; 
            text-align: center; text-decoration: none; color: inherit;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
            transition: all 0.3s ease; border: 1px solid #f1f5f9;
            display: flex; flex-direction: column; align-items: center;
        }
        .admin-card:hover { transform: translateY(-8px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border-color: var(--secondary); }
        
        .card-icon { 
            width: 80px; height: 80px; border-radius: 20px; background: #fef2f2;
            display: flex; align-items: center; justify-content: center;
            font-size: 35px; margin-bottom: 25px; transition: 0.3s;
        }
        .admin-card:hover .card-icon { background: var(--primary); color: #fff; transform: scale(1.1); }
        
        .admin-card h3 { font-size: 1.5rem; margin-bottom: 12px; color: #1e293b; }
        .admin-card p { color: #64748b; line-height: 1.6; }
        
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-box { background: #fff; padding: 20px; border-radius: 15px; border-right: 4px solid var(--primary); box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .stat-label { font-size: 0.9rem; color: #64748b; margin-bottom: 5px; }
        .stat-value { font-size: 1.5rem; font-weight: 800; color: #1e293b; }
    </style>
</head>
<body>
    <header class="admin-header">
        <div style="display: flex; align-items: center; gap: 20px;">
            <img src="<?php echo htmlspecialchars($settings['logo'] ?? 'assets/logo.jpg'); ?>" height="50">
            <h2 style="color: var(--primary); font-weight: 800;">کۆنترۆڵ پانێڵی گشتی</h2>
        </div>
        <a href="portal.php" class="back-portal">⬅️ گەڕانەوە بۆ پۆرتاڵ</a>
    </header>

    <div class="main-content">
        <div class="welcome-section">
            <h1>سڵاو، <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'ئەدمین'); ?></h1>
            <p>بەخێرهاتی بۆ داشبۆردی بەڕێوەبەرایەتی سیستم. لێرەوە دەتوانیت هەموو بەشەکان کۆنترۆڵ بکەیت.</p>
        </div>

        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-label">کۆی بەکارهێنەران</div>
                <div class="stat-value"><?php echo $conn->query("SELECT id FROM users")->num_rows; ?> یوزەر</div>
            </div>
            <div class="stat-box" style="border-right-color: #059669;">
                <div class="stat-label">کۆی کتێبەکان</div>
                <div class="stat-value"><?php echo $conn->query("SELECT id FROM books")->num_rows; ?> کتێب</div>
            </div>
            <div class="stat-box" style="border-right-color: var(--secondary);">
                <div class="stat-label">خواستنە ئەکتیڤەکان</div>
                <div class="stat-value"><?php echo $conn->query("SELECT id FROM borrow_records WHERE status='borrowed'")->num_rows; ?> ڕیکۆرد</div>
            </div>
        </div>

        <div class="admin-grid">
            <!-- User Management -->
            <a href="users_manage.php" class="admin-card">
                <div class="card-icon">👥</div>
                <h3>بەڕێوەبردنی یوزەر</h3>
                <p>زیادکردن، سڕینەوە، و دیاریکردنی دەسەڵاتەکانی هەر کارمەندێک لە بەشەکانی سیستم.</p>
            </a>

            <!-- System Settings -->
            <a href="settings.php" class="admin-card">
                <div class="card-icon">⚙️</div>
                <h3>ڕێکخستنی سیستم</h3>
                <p>گۆڕینی ناوی حکومەت، بەشەکان، لۆگۆ و زانیارییە گشتییەکانی سەر لاپەڕە و ڕاپۆرتەکان.</p>
            </a>

            <!-- Document Types Management -->
            <a href="doc_types_manage.php" class="admin-card">
                <div class="card-icon">🏷️</div>
                <h3>جۆرەکانی نوسراو</h3>
                <p>بەڕێوەبردنی جۆرەکانی نوسراو (هاتوو، دەرچوو، نهێنی، ...) و گۆڕینی ناوەکانیان بۆ کوردی.</p>
            </a>


            <!-- Activity Logs (Future) -->
            <div class="admin-card" style="opacity: 0.7; cursor: default;">
                <div class="card-icon">📜</div>
                <h3>لۆگی چالاکییەکان</h3>
                <p>بینینی هەموو ئەو گۆڕانکاریانەی کە یوزەرەکان لە سیستمەکەدا ئەنجامی دەدەن (بۆ داهاتوو).</p>
            </div>
        </div>
    </div>
</body>
</html>
