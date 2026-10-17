<?php
require_once 'config.php';
$conn = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("ناسنامەی کارمەند دیاری نەکراوە.");

$stmt = $conn->prepare("SELECT m.*, r.rank_name, u.unit_name 
                        FROM staff_members m 
                        JOIN staff_ranks r ON m.rank_id = r.id 
                        JOIN staff_units u ON m.unit_id = u.id 
                        WHERE m.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$staff) die("کارمەند نەدۆزرایەوە.");

// Fetch Unit Manager for Signature
$unit_id = $staff['unit_id'];
$unit_info = $conn->query("SELECT manager_id FROM staff_units WHERE id = $unit_id")->fetch_assoc();
$manager = null;
if ($unit_info && $unit_info['manager_id']) {
    $m_res = $conn->query("SELECT m.full_name, r.rank_name FROM staff_members m JOIN staff_ranks r ON m.rank_id = r.id WHERE m.id = " . $unit_info['manager_id']);
    $manager = $m_res->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="ckb" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>لیستی مەوجودات - <?php echo htmlspecialchars($staff['full_name']); ?></title>
    <style>
        @font-face { font-family: 'Rabar013'; src: url('assets/font/Rabar_013.ttf'); }
        @font-face { font-family: 'Rabar022'; src: url('assets/font/Rabar_022.ttf'); }
        
        body { font-family: 'Rabar022', sans-serif; margin: 0; padding: 0; background: #eee; }
        .a4-page { width: 210mm; min-height: 297mm; padding: 15mm; margin: 10mm auto; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); position: relative; box-sizing: border-box; }
        
        .header-section { display: flex; justify-content: space-between; margin-bottom: 30px; line-height: 1.6; }
        .report-title { text-align: center; margin: 20px 0 40px; }
        .report-title h1 { border-bottom: 2px solid #000; padding-bottom: 5px; font-family: 'Rabar013'; font-size: 1.6rem; display: inline-block; }
        
        .staff-summary { margin-bottom: 25px; padding: 10px; background: #f9f9f9; border-right: 4px solid var(--maroon); font-size: 1.1rem; }
        
        .asset-table { width: 100%; border-collapse: collapse; }
        .asset-table th, .asset-table td { border: 1.5px solid #000; padding: 10px; text-align: center; }
        .asset-table th { background: #f2f2f2; font-weight: bold; }
        
        .footer-sigs { margin-top: 60px; display: flex; justify-content: space-between; }
        .sig-box { text-align: center; width: 45%; font-weight: bold; }
        
        @media print {
            @page { size: portrait; margin: 10mm; }
            body { background: none; }
            .a4-page { margin: 0; box-shadow: none; width: 100%; height: auto; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()" style="padding: 10px 25px; cursor: pointer; background: #5a0202; color: white; border: none; border-radius: 5px; font-weight: bold;">🖨️ چاپکردن</button>
    </div>

    <div class="a4-page">
        <div class="header-section">
            <div style="text-align: left;">
                ژمارە: <br>
                ڕێکەوت: <?php echo date('Y / m / d'); ?>
            </div>
            <div style="text-align: right;">
                وەزارەتی پێشمەرگە<br>
                سەرۆکایەتی هەیئەی ئەرکان<br>
                بەڕێوەبەرایەتی گشتی ڕاگەیاندن و هۆشیاری نیشتمانی
            </div>
        </div>

        <div class="report-title">
            <h1>لیستی مەوجودات و زممە</h1>
        </div>

        <div class="staff-summary">
            ئەم کەلوپەلانەی لای خوارەوە لە لایەن (<?php echo htmlspecialchars($staff['rank_name'] . ' ' . $staff['full_name']); ?>) وەرگیراوە و لە ژێر بەرپرسیارییەتی ئەودایە:
        </div>

        <table class="asset-table">
            <thead>
                <tr>
                    <th style="width: 40px;">ز</th>
                    <th>ناوی کەلوپەل</th>
                    <th>جۆر</th>
                    <th>ژمارەی زنجیرەیی (S/N)</th>
                    <th>بەرواری وەرگرتن</th>
                    <th>دۆخی فیزیکی</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $res = $conn->query("SELECT * FROM staff_assets WHERE staff_id = $id AND status = 'Held' ORDER BY category ASC");
                $i = 1;
                if ($res->num_rows > 0) {
                    while($a = $res->fetch_assoc()) {
                        $cat_name = "";
                        switch($a['category']) {
                            case 'Weapon': $cat_name = "چەک"; break;
                            case 'Vehicle': $cat_name = "ئۆتۆمبێل"; break;
                            case 'Property': $cat_name = "شوێن"; break;
                            case 'Tech': $cat_name = "تەکنەلۆژیا"; break;
                            default: $cat_name = "تر";
                        }
                        echo "<tr>";
                        echo "<td>".$i++."</td>";
                        echo "<td>".htmlspecialchars($a['item_name'])."</td>";
                        echo "<td>$cat_name</td>";
                        echo "<td>".htmlspecialchars($a['serial_number'] ?: '-')."</td>";
                        echo "<td>".$a['received_date']."</td>";
                        echo "<td>".htmlspecialchars($a['physical_condition'] ?: '-')."</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='6' style='padding: 30px; color: #888;'>هیچ کەلوپەلێکی لای ئەم ئەفسەرە نییە.</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <div class="footer-sigs">
            <div class="sig-box">
                <p>ئەفسەری وەرگر (خاوەن زممە)</p>
                <p style="margin-top: 1cm;"><?php echo htmlspecialchars($staff['rank_name'] . ' ' . $staff['full_name']); ?></p>
            </div>
            <div class="sig-box">
                <p>بەڕێوەبەری گشتی / سەرۆکی دەستەی کارگێڕی</p>
                <p style="margin-top: 1cm;"><?php echo $manager ? htmlspecialchars($manager['rank_name'] . " " . $manager['full_name']) : "......................................."; ?></p>
            </div>
        </div>
    </div>
</body>
</html>
