<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$type = isset($_GET['type']) ? $_GET['type'] : 'completed'; // completed or pending
$title = ($type == 'completed') ? "لیستی چالاکییە ئەنجامدراوەکان" : "لیستی چالاکییە ئەنجام نەدراوەکان (چاوەڕوان)";

// Fetch settings
$settings_res = $conn->query("SELECT * FROM settings WHERE id = 1");
$settings = $settings_res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="ckb" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?></title>
    <style>
        @font-face { font-family: 'Rabar022'; src: url('assets/font/Rabar_022.ttf') format('truetype'); }
        @page { margin: 1cm; }
        body { font-family: 'Rabar022', Tahoma, sans-serif; padding: 10px; color: #333; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #5a0202; padding-bottom: 10px; margin-bottom: 20px; }
        .header img { width: 60px; height: 60px; object-fit: contain; }
        .header-text { text-align: center; flex: 1; }
        .header-text h1 { margin: 0; color: #5a0202; font-size: 1.3rem; }
        .header-text h2 { margin: 0; color: #666; font-size: 0.9rem; }
        
        .report-title { text-align: center; font-size: 1.3rem; font-weight: bold; margin-bottom: 20px; color: #5a0202; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; table-layout: auto; }
        th { background: #5a0202; color: #fff; padding: 10px; text-align: center; font-size: 0.85rem; white-space: nowrap; }
        td { padding: 8px 10px; border: 1px solid #ddd; text-align: center; font-size: 0.8rem; }
        tr:nth-child(even) { background: #f9f9f9; }
        
        .footer { margin-top: 30px; text-align: left; font-size: 0.8rem; color: #888; border-top: 1px solid #eee; padding-top: 10px; }
        
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="no-print" style="text-align: center; margin-bottom: 20px;">
    <button onclick="window.print()" style="padding: 8px 25px; background: #2e7d32; color: #fff; border: none; border-radius: 5px; cursor: pointer; font-family: inherit;">🖨️ چاپکردنی لیستەکە</button>
</div>

<div class="header">
    <img src="<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo">
    <div class="header-text">
        <h1><?php echo htmlspecialchars($settings['system_name']); ?></h1>
        <h2><?php echo htmlspecialchars($settings['system_subtitle']); ?></h2>
    </div>
    <div style="width: 80px;"></div>
</div>

<div class="report-title"><?php echo $title; ?></div>

<table>
    <thead>
        <tr>
            <th style="width: 40px;">ڕیز</th>
            <th>ناوی چالاکی</th>
            <th>ڕێکەوت</th>
            <th>شوێن</th>
            <th>بەرپرس</th>
            <th>بەشداربوان</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $status_filter = ($type == 'completed') ? "status != 0" : "status = 0";
        $res = $conn->query("SELECT * FROM activities WHERE is_deleted = 0 AND $status_filter ORDER BY activity_date DESC");
        if ($res && $res->num_rows > 0):
            $i = 1;
            while ($row = $res->fetch_assoc()):
                $formatted_date = date('Y-m-d', strtotime($row['activity_date']));
        ?>
            <tr>
                <td style="text-align:center;"><?php echo $i++; ?></td>
                <td style="font-weight: bold; white-space: nowrap;"><?php echo htmlspecialchars($row['name']); ?></td>
                <td style="white-space: nowrap;"><?php echo $formatted_date; ?></td>
                <td style="white-space: nowrap;"><?php echo htmlspecialchars($row['location']); ?></td>
                <td style="white-space: nowrap;"><?php echo htmlspecialchars($row['implemented_by']); ?></td>
                <td style="text-align:center;"><?php echo $row['participants_count']; ?></td>
            </tr>
        <?php
            endwhile;
        else:
            echo '<tr><td colspan="6" style="text-align:center;">هیچ چالاکییەک نەدۆزرایەوە.</td></tr>';
        endif;
        ?>
    </tbody>
</table>

<div class="footer">
    چاپکراوە لە کاتی: <?php echo date('Y-m-d H:i'); ?> | <?php echo htmlspecialchars($settings['system_name']); ?> (<?php echo htmlspecialchars($settings['owner_name']); ?>)
</div>

</body>
</html>
