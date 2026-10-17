<?php
require_once 'config.php';
$conn = getDB();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0)
    die("ناسنامەی کارمەند دیاری نەکراوە.");

$stmt = $conn->prepare("SELECT m.*, r.rank_name, u.unit_name 
                        FROM staff_members m 
                        JOIN staff_ranks r ON m.rank_id = r.id 
                        JOIN staff_units u ON m.unit_id = u.id 
                        WHERE m.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$staff)
    die("کارمەند نەدۆزرایەوە.");

// Fetch Signatories for Footer
$settings = $conn->query("SELECT * FROM settings WHERE id = 1")->fetch_assoc();
$unit_id = $staff['unit_id'];
$unit_info = $conn->query("SELECT manager_id, deputy_id FROM staff_units WHERE id = $unit_id")->fetch_assoc();

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
    <title>خشتەی ڕەوشت - <?php echo htmlspecialchars($staff['full_name']); ?></title>
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
        }

        .a4-page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            box-sizing: border-box;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .header-right {
            text-align: right;
            line-height: 1.6;
            font-size: 0.9rem;
        }

        .header-left {
            text-align: left;
            line-height: 1.8;
            font-size: 0.95rem;
            font-weight: bold;
        }

        .report-title {
            text-align: center;
            margin: 40px 0;
        }

        .report-title h1 {
            display: inline-block;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
            font-family: 'Rabar013';
            font-size: 1.8rem;
            margin: 0;
        }

        .staff-info {
            margin-bottom: 30px;
            font-size: 1.1rem;
            line-height: 2;
        }

        .info-row {
            display: flex;
            gap: 10px;
        }

        .label {
            font-weight: bold;
            min-width: 100px;
        }

        .conduct-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .conduct-table th,
        .conduct-table td {
            border: 1.5px solid #000;
            padding: 12px;
            text-align: center;
        }

        .conduct-table th {
            background: #f9f9f9;
            font-weight: bold;
        }

        .empty-row {
            height: 300px;
            vertical-align: middle;
            font-size: 1.2rem;
            color: #555;
        }

        .footer-sigs {
            margin-top: 80px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .sig-box {
            text-align: center;
            width: 40%;
        }

        .sig-box p {
            margin: 5px 0;
            font-weight: bold;
        }

        @media print {
            body {
                background: none;
            }

            .a4-page {
                margin: 0;
                box-shadow: none;
                width: 100%;
                height: 100%;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()"
            style="padding: 10px 25px; cursor: pointer; background: #5a0202; color: white; border: none; border-radius: 5px; font-weight: bold;">🖨️
            چاپکردن</button>
    </div>

    <div class="a4-page">
        <div class="header-top">
            <div class="header-left">
                ژمارە / <br>
                ڕێکەوت / <?php echo date('Y / m / d'); ?>
            </div>
            <div class="header-right">
                وەزارەتی پێشمەرگە<br>
                سەرۆکایەتی هەیئەی ئەرکان<br>
                ئیدارە و میرە<br>
                بەڕێوەبەرایەتی گشتی ڕاگەیاندن و هۆشیاری نیشتمانی
            </div>
        </div>

        <div class="report-title">
            <h1>خشتەی ڕەوشت</h1>
        </div>

        <div class="staff-info">
            <div class="info-row"><span class="label">پلە /</span> <?php echo htmlspecialchars($staff['rank_name']); ?>
            </div>
            <div class="info-row"><span class="label">ناو /</span> <?php echo htmlspecialchars($staff['full_name']); ?>
            </div>
            <div class="info-row"><span class="label">یەکە /</span> <?php echo htmlspecialchars($staff['unit_name']); ?>
            </div>
        </div>

        <table class="conduct-table">
            <thead>
                <tr>
                    <th style="width: 40px;">ژ</th>
                    <th style="width: 120px;">ڕێکەوت</th>
                    <th>سزا</th>
                    <th>جۆری تاوان</th>
                    <th>تێبینی</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $conduct_res = $conn->query("SELECT * FROM staff_conduct WHERE staff_id = $id AND type = 'penalty' ORDER BY conduct_date ASC");
                if ($conduct_res->num_rows > 0):
                    $i = 1;
                    while ($c = $conduct_res->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo $c['conduct_date']; ?></td>
                            <td><?php echo htmlspecialchars($c['penalty_name']); ?></td>
                            <td><?php echo htmlspecialchars($c['crime_type']); ?></td>
                            <td><?php echo htmlspecialchars($c['notes']); ?></td>
                        </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="5" class="empty-row">
                            هیچ جۆرە سزا و تاوانێکی لە ئەستۆ نییە
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer-sigs">
            <div class="sig-box">
                <p>ڕێکخەر</p>
                <br><br>
                <p>.......................................</p>
            </div>
            <div class="sig-box">
                <p><?php echo htmlspecialchars($manager['rank_name'] ?? 'لیوا'); ?></p>
                <p><?php echo htmlspecialchars($manager['full_name'] ?? 'عوسمان محەمەد مستەفا'); ?></p>
                <p>بەڕێوەبەری گشتی</p>
            </div>
        </div>
    </div>
</body>

</html>