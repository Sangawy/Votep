<?php
require_once 'config.php';
$conn = getDB();

$from_id = (int) ($_GET['from_id'] ?? 0);
if ($from_id <= 0)
    die("ناسنامەی ئەفسەری پێشوو دیاری نەکراوە.");

$stmt = $conn->prepare("SELECT m.*, r.rank_name, u.unit_name 
                        FROM staff_members m 
                        JOIN staff_ranks r ON m.rank_id = r.id 
                        JOIN staff_units u ON m.unit_id = u.id 
                        WHERE m.id = ?");
$stmt->bind_param("i", $from_id);
$stmt->execute();
$staff_from = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$staff_from)
    die("ئەفسەر نەدۆزرایەوە.");

// Fetch Incoming Officer if provided
$to_id = (int) ($_GET['to_id'] ?? 0);
$staff_to = null;
if ($to_id > 0) {
    $stmt = $conn->prepare("SELECT m.*, r.rank_name FROM staff_members m JOIN staff_ranks r ON m.rank_id = r.id WHERE m.id = ?");
    $stmt->bind_param("i", $to_id);
    $stmt->execute();
    $staff_to = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch Unit Manager for Signature
$unit_id = $staff_from['unit_id'];
$unit_info = $conn->query("SELECT manager_id FROM staff_units WHERE id = $unit_id")->fetch_assoc();
$manager = null;
if ($unit_info && $unit_info['manager_id']) {
    $m_res = $conn->query("SELECT m.full_name, r.rank_name FROM staff_members m JOIN staff_ranks r ON m.rank_id = r.id WHERE m.id = " . $unit_info['manager_id']);
    $manager = $m_res->fetch_assoc();
}

// Fetch Assets
$assets = [];
$res = $conn->query("SELECT * FROM staff_assets WHERE staff_id = $from_id AND status = 'Held' ORDER BY category ASC");
while ($row = $res->fetch_assoc())
    $assets[] = $row;
?>
<!DOCTYPE html>
<html lang="ckb" dir="rtl">

<head>
    <meta charset="UTF-8">
    <title>لیستی ئاڵوگۆڕکردن - <?php echo htmlspecialchars($staff_from['full_name']); ?></title>
    <style>
        @font-face {
            font-family: 'Rabar013';
            src: url('assets/font/Rabar_013.ttf');
        }

        @font-face {
            font-family: 'Rabar022';
            src: url('assets/font/Rabar_022.ttf');
        }

        body {
            font-family: 'Rabar022', sans-serif;
            margin: 0;
            padding: 0;
            background: #eee;
            font-size: 11pt;
        }

        .a4-page {
            width: 297mm;
            min-height: 210mm;
            padding: 15mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            box-sizing: border-box;
        }

        .header-section {
            margin-bottom: 20px;
        }

        .main-title {
            text-align: center;
            background: #e0e0e0;
            padding: 8px;
            border: 1.5px solid #000;
            font-family: 'Rabar013';
            font-size: 1.2rem;
            margin-bottom: 20px;
        }

        .info-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .handover-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .handover-table th,
        .handover-table td {
            border: 1.5px solid #000;
            padding: 6px;
            text-align: center;
            overflow: hidden;
            word-wrap: break-word;
        }

        .handover-table th {
            background: #f2f2f2;
            font-size: 0.95rem;
        }

        .sub-header th {
            padding: 4px;
            font-size: 0.85rem;
        }

        .footer-sigs {
            margin-top: 40px;
            display: flex;
            justify-content: space-around;
            font-weight: bold;
        }

        .sig-box {
            text-align: center;
            width: 30%;
        }

        .sig-box p {
            margin: 2px 0;
            line-height: 1.2;
        }

        @media print {
            @page { size: landscape; margin: 5mm; }
            body { background: none; }
            .a4-page { margin: 0; box-shadow: none; width: 100%; height: auto; padding: 5mm; }
            .no-print { display: none; }
        }
    </style>
</head>

<body>
    <div class="no-print" style="position: fixed; top: 20px; right: 20px; z-index: 100;">
        <button onclick="window.print()"
            style="padding: 10px 25px; cursor: pointer; background: #5a0202; color: white; border: none; border-radius: 5px; font-weight: bold;">🖨️
            چاپکردنی فۆڕم</button>
    </div>

    <div class="a4-page" style="width: 280mm; margin: 10px auto;">
        <div class="main-title">
            لیستی ئاڵوگۆڕکردن لە نێوان ئەفسەری پێشوو ئەفسەری هاتوو ( قوائم الدور والتسلیم بین الضباط السابق واللاحق )
        </div>

        <div class="info-header">
            <div>یەکە : <?php echo htmlspecialchars($staff_from['unit_name']); ?></div>
            <div>ڕێکەوت : <?php echo date('Y / m / d'); ?></div>
        </div>

        <table class="handover-table">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 30px;">ز</th>
                    <th rowspan="2" style="width: 50px;">ژمارەی لاپەڕە</th>
                    <th rowspan="2" style="width: 50px;">ژمارەی تۆمار</th>
                    <th colspan="2">شوێنی گۆڕینی پایە</th>
                    <th rowspan="2">ناوی کەلوپەل (فنیة + ثابتة)</th>
                    <th rowspan="2" style="width: 60px;">ژمارەی کەلوپەل</th>
                    <th rowspan="2" style="width: 100px;">ئۆتۆمبێل</th>
                    <th rowspan="2" style="width: 100px;">چەک</th>
                    <th rowspan="2" style="width: 100px;">تێبینی</th>
                </tr>
                <tr class="sub-header">
                    <th style="width: 120px;">لە (ئەفسەری پێشوو)</th>
                    <th style="width: 120px;">بۆ (ئەفسەری هاتوو)</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $i = 1;
                if (empty($assets)) {
                    // Blank rows if no assets
                    for ($r = 1; $r <= 10; $r++) {
                        echo "<tr><td>$r</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
                    }
                } else {
                    foreach ($assets as $a) {
                        $item_name = htmlspecialchars($a['item_name']);
                        $is_car = ($a['category'] == 'Vehicle');
                        $is_weapon = ($a['category'] == 'Weapon');

                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td></td>";
                        echo "<td>" . htmlspecialchars($a['order_number']) . "</td>";
                        echo "<td>" . htmlspecialchars($staff_from['rank_name'] . " " . $staff_from['full_name']) . "</td>";
                        echo "<td>" . ($staff_to ? htmlspecialchars($staff_to['rank_name'] . " " . $staff_to['full_name']) : "") . "</td>";
                        echo "<td>" . ($is_car || $is_weapon ? "" : $item_name) . "</td>";
                        echo "<td>1</td>";
                        echo "<td>" . ($is_car ? $item_name : "") . "</td>";
                        echo "<td>" . ($is_weapon ? $item_name : "") . "</td>";
                        echo "<td>" . htmlspecialchars($a['serial_number']) . "</td>";
                        echo "</tr>";
                    }
                    // Add some empty rows to reach at least 10
                    for ($r = $i; $r <= 10; $r++) {
                        echo "<tr><td>$r</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>";
                    }
                }
                ?>
            </tbody>
        </table>

        <div class="footer-sigs">
            <div class="sig-box">
                <p>ئەفسەری پێشوو</p>
                <p style="margin-top: 0.5cm;">.......................................</p>
            </div>
            <div class="sig-box">
                <p>ئەفسەری هاتوو</p>
                <p style="margin-top: 0.5cm;">
                    <?php echo $staff_to ? htmlspecialchars($staff_to['rank_name'] . " " . $staff_to['full_name']) : "......................................."; ?>
                </p>
            </div>
            <div class="sig-box">
                <p>بەڕێوەبەری گشتی / سەرۆکی دەستەی کارگێڕی</p>
                <p style="margin-top: 0.5cm;">
                    <?php echo $manager ? htmlspecialchars($manager['rank_name'] . " " . $manager['full_name']) : "......................................."; ?>
                </p>
            </div>
        </div>
    </div>
</body>

</html>