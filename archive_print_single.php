<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

if (!isset($_GET['id'])) {
    die("ناسنامەی چالاکی دیاری نەکراوە.");
}

$id = (int)$_GET['id'];
$include_images = isset($_GET['images']) && $_GET['images'] == '1';

$res = $conn->query("SELECT * FROM activities WHERE id = $id");
$data = $res->fetch_assoc();

if (!$data) die("چالاکیەکە نەدۆزرایەوە.");

// Fetch settings for logo and name
$settings_res = $conn->query("SELECT * FROM settings WHERE id = 1");
$settings = $settings_res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="ckb" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ڕاپۆرتی چالاکی - <?php echo htmlspecialchars($data['name']); ?></title>
    <style>
        @font-face {
            font-family: 'Rabar022';
            src: url('assets/font/Rabar_022.ttf') format('truetype');
        }
        @page { margin: 1cm; }
        body {
            font-family: 'Rabar022', Tahoma, sans-serif;
            padding: 10px;
            color: #333;
            line-height: 1.4;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #5a0202;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header img { width: 70px; height: 70px; object-fit: contain; }
        .header-text { text-align: center; flex: 1; }
        .header-text h1 { margin: 0; color: #5a0202; font-size: 1.4rem; }
        .header-text h2 { margin: 2px 0 0; color: #666; font-size: 0.95rem; }

        .report-title {
            text-align: center;
            background: #f4f4f4;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-size: 1.4rem;
            font-weight: bold;
            color: #5a0202;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
        }
        .info-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .info-label { font-weight: bold; color: #5a0202; width: 150px; display: inline-block; }

        .details-section { margin-bottom: 40px; }
        .details-title { font-weight: bold; color: #5a0202; border-right: 4px solid #5a0202; padding-right: 10px; margin-bottom: 10px; }
        .details-content { background: #fafafa; padding: 15px; border-radius: 8px; border: 1px solid #eee; min-height: 100px; white-space: pre-wrap; }

        .gallery {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 30px;
        }
        .gallery img {
            width: 100%;
            height: 350px;
            object-fit: contain;
            border-radius: 8px;
            border: 1px solid #eee;
            background: #fdfdfd;
        }

        .footer {
            margin-top: 50px;
            text-align: left;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 0.9rem;
            color: #777;
        }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .lib-btn-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align: center; margin-bottom: 20px;">
    <button onclick="window.print()" style="padding: 10px 30px; background: #2e7d32; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-family: inherit;">🖨️ چاپکردن</button>
</div>

<div class="header">
    <img src="<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo">
    <div class="header-text">
        <h1><?php echo htmlspecialchars($settings['system_name']); ?></h1>
        <h2><?php echo htmlspecialchars($settings['system_subtitle']); ?></h2>
    </div>
    <div style="width: 100px;"></div> <!-- Spacer -->
</div>

<div class="report-title">ڕاپۆرتی چالاکی ئەنجامدراو</div>

<div class="info-grid" style="grid-template-columns: 1fr; gap: 10px; margin-bottom: 30px;">
    <div class="info-item" style="border-bottom: 2px solid #5a0202; padding-bottom: 15px; margin-bottom: 10px;">
        <span class="info-label" style="width: 120px;">ناوی چالاکی:</span> 
        <span style="font-size: 1.3rem; font-weight: bold;"><?php echo htmlspecialchars($data['name']); ?></span>
    </div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div class="info-item"><span class="info-label">ڕێکەوت:</span> <?php echo date('Y-m-d', strtotime($data['activity_date'])); ?></div>
        <div class="info-item"><span class="info-label">شوێن:</span> <?php echo htmlspecialchars($data['location']); ?></div>
        <div class="info-item"><span class="info-label">بەرپرسی چالاکی:</span> <?php echo htmlspecialchars($data['implemented_by']); ?></div>
        <div class="info-item"><span class="info-label">ژمارەی بەشداربوان:</span> <?php echo htmlspecialchars($data['participants_count']); ?></div>
    </div>
</div>

<div class="details-section">
    <div class="details-title">کورتەیەک لەسەر چالاکی</div>
    <div class="details-content"><?php echo !empty($data['summary']) ? htmlspecialchars($data['summary']) : 'زانیاری نەنوسراوە.'; ?></div>
</div>

<div class="details-section">
    <div class="details-title">تێبینییەکان</div>
    <div class="details-content"><?php echo !empty($data['notes']) ? htmlspecialchars($data['notes']) : 'تێبینی نییە.'; ?></div>
</div>

<?php if ($include_images && !empty($data['folder_path']) && is_dir($data['folder_path'])): ?>
    <div class="details-title">وێنەکانی چالاکی</div>
    <div class="gallery">
        <?php
        $files = glob($data['folder_path'] . '/*.{jpg,jpeg,png,gif,JPG,JPEG,PNG}', GLOB_BRACE);
        foreach ($files as $file) {
            echo '<img src="' . htmlspecialchars($file) . '" alt="Activity Image">';
        }
        ?>
    </div>
<?php endif; ?>

<div class="footer">
    چاپکراوە لە کاتی: <?php echo date('Y-m-d H:i'); ?> | <?php echo htmlspecialchars($settings['system_name']); ?> (<?php echo htmlspecialchars($settings['owner_name']); ?>)
</div>

<script>
    // Auto trigger print if not already printing
    window.onload = function() {
        // window.print();
    };
</script>

</body>
</html>
