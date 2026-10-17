<?php
require_once 'db_config.php';
$conn = getPublicDB();

$id = $_GET['id'] ?? 0;
$stmt = $conn->prepare("SELECT * FROM public_activities WHERE id = ? AND is_published = 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$act = $stmt->get_result()->fetch_assoc();

if (!$act) {
    header("Location: index.php");
    exit;
}

// Increment View Count
$conn->query("UPDATE public_activities SET view_count = view_count + 1 WHERE id = $id");
$act['view_count']++; // Update local object for display

$settings_res = $conn->query("SELECT * FROM public_settings WHERE id = 1");
$site = $settings_res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($act['title']); ?> - <?php echo htmlspecialchars($site['system_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: <?php echo $site['primary_color'] ?? '#5a0202'; ?>; }
        @font-face { font-family: 'Rabar'; src: url('assets/font/Rabar_022.ttf') format('truetype'); }
        @font-face { font-family: 'RabarBold'; src: url('assets/font/Rabar_005.ttf') format('truetype'); }
        body { font-family: 'Rabar', 'Noto Sans Arabic', sans-serif; background: #f9f9f9; margin: 0; padding: 0; line-height: 1.8; }
        .nav-back { padding: 20px 5%; background: #fff; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px; }
        .nav-back a { text-decoration: none; color: var(--primary); font-weight: bold; }
        .container { max-width: 900px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .act-date { color: #888; font-size: 0.9rem; margin-bottom: 10px; display: block; }
        h1 { font-family: 'RabarBold'; color: var(--primary); font-size: 2.2rem; margin-bottom: 30px; line-height: 1.3; }
        .main-img { width: 100%; border-radius: 15px; margin-bottom: 40px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .content { font-size: 1.15rem; color: #444; white-space: pre-wrap; margin-bottom: 40px; }
        .gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; }
        .gallery-img { width: 100%; height: 200px; object-fit: cover; border-radius: 10px; cursor: pointer; transition: 0.3s; }
        .gallery-img:hover { transform: scale(1.02); }
        footer { text-align: center; padding: 40px; color: #aaa; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="nav-back">
        <a href="index.php">🏠 گەڕانەوە بۆ سەرەکی</a>
        <span>/</span>
        <span>چالاکییەکان</span>
    </div>

    <article class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <span class="act-date">📅 بڵاوکراوەتەوە لە: <?php echo str_replace('-', '/', $act['activity_date']); ?></span>
            <span style="color:#888; font-size:0.9rem;">👁️ <?php echo number_format($act['view_count']); ?> بینین</span>
        </div>
        <h1><?php echo htmlspecialchars($act['title']); ?></h1>
        
        <?php if ($act['image_path']): ?>
            <img src="<?php echo htmlspecialchars($act['image_path']); ?>" class="main-img" alt="Activity Image">
        <?php endif; ?>

        <div class="content">
            <?php echo htmlspecialchars($act['content']); ?>
        </div>

        <?php
        $imgs_res = $conn->query("SELECT * FROM public_activity_images WHERE activity_id = $id");
        if ($imgs_res && $imgs_res->num_rows > 0):
        ?>
        <div style="border-top: 1px solid #eee; padding-top: 30px;">
            <h3 style="font-family:'RabarBold'; margin-bottom: 20px;">وێنەکان</h3>
            <div class="gallery">
                <?php while($g_img = $imgs_res->fetch_assoc()): ?>
                    <img src="<?php echo htmlspecialchars($g_img['image_path']); ?>" class="gallery-img" onclick="window.open(this.src)">
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </article>

    <footer>
        &copy; ٢٠٢٦ هەموو مافەکان پارێزراوە بۆ مەڵبەندی پیرخدر
    </footer>
</body>
</html>
