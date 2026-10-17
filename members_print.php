<?php
session_start();
if (!isset($_SESSION['user_id']))
    die("Access Denied");

require_once 'config.php';
$conn = getDB();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0)
    die("Invalid ID");

$stmt = $conn->prepare("SELECT m.*, f.family_name 
                        FROM members m 
                        LEFT JOIN families f ON m.family_id = f.id 
                        WHERE m.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$member)
    die("Member not found");

// Fetch Branding (Single row table)
$branding = $conn->query("SELECT * FROM settings LIMIT 1")->fetch_assoc();

$org_name = $branding['system_name'] ?? "مەڵبەندی ڕۆشنبیری و کۆمەڵایەتی";
$sub_title = $branding['system_subtitle'] ?? "ساداتی پیرخدری شاهۆ";
$dept_name = "بەشی کارگێڕی و ئامار";
$logo = $branding['logo'] ?? "assets/logo.ico";
?>
<!DOCTYPE html>
<html dir="rtl" lang="ku">

<head>
    <meta charset="UTF-8">
    <title>چاپکردنی فۆڕمی: <?php echo htmlspecialchars($member['full_name']); ?></title>
    <style>
        @font-face {
            font-family: 'Rabar013';
            src: url('assets/font/Rabar_013.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Rabar022';
            src: url('assets/font/Rabar_022.ttf') format('truetype');
        }

        @page {
            size: A4;
            margin: 10mm;
        }

        body {
            font-family: 'Rabar022', 'Tahoma', sans-serif;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        h1,
        h2,
        h3,
        h4,
        .form-title {
            font-family: 'Rabar013', sans-serif;
        }

        .print-container {
            width: 210mm;
            max-height: 297mm;
            padding: 8mm 10mm;
            margin: auto;
            box-sizing: border-box;
            background: #fff;
            position: relative;
            overflow: hidden;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .header-text {
            text-align: center;
            flex: 1;
        }

        .header-text h1 {
            margin: 0;
            font-size: 22px;
            font-weight: bold;
        }

        .header-text h2 {
            margin: 3px 0;
            font-size: 18px;
        }

        .header-text h3 {
            margin: 0;
            font-size: 16px;
        }

        .logo {
            width: 90px;
            height: 90px;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .form-title {
            background: #2c3e50;
            color: #fff;
            text-align: center;
            padding: 8px;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 12px;
            -webkit-print-color-adjust: exact;
        }

        .top-section {
            display: grid;
            grid-template-columns: 1fr 140px;
            gap: 15px;
            margin-bottom: 12px;
            align-items: start;
        }

        .photo-box {
            width: 140px;
            height: 170px;
            border: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            overflow: hidden;
        }

        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .main-fields {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .field-group {
            display: flex;
            border: 1px solid #000;
        }

        .field-label {
            width: 130px;
            padding: 8px;
            background: #f2f2f2;
            border-right: 1px solid #000;
            font-weight: bold;
            text-align: center;
            font-family: 'Rabar013';
            font-size: 14px;
            -webkit-print-color-adjust: exact;
        }

        .field-value {
            flex: 1;
            padding: 8px;
            text-align: center;
            font-size: 18px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .info-table td {
            border: 1px solid #000;
            padding: 8px 12px;
        }

        .info-table .label-cell {
            width: 200px;
            background: #f2f2f2;
            font-weight: bold;
            text-align: right;
            font-family: 'Rabar013';
            font-size: 14px;
            -webkit-print-color-adjust: exact;
        }

        .info-table .value-cell {
            text-align: center;
            font-size: 16px;
        }

        .footer-note {
            font-size: 15px;
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .attachments-note {
            background: #eee;
            padding: 6px 12px;
            display: inline-block;
            margin-bottom: 15px;
            font-weight: bold;
            font-size: 14px;
            -webkit-print-color-adjust: exact;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            text-align: center;
            margin-top: 15px;
            gap: 8px;
        }

        .signature-box h4 {
            margin: 0 0 40px 0;
            font-size: 13px;
            font-family: 'Rabar013';
        }

        .date-line {
            margin-top: 15px;
            text-align: right;
            padding-right: 20px;
            font-size: 17px;
        }

        @media print {
            .no-print {
                display: none;
            }

            .print-container {
                width: 100%;
                padding: 0;
                border: none;
            }
        }
    </style>
</head>

<body>

    <div class="no-print"
        style="text-align: center; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #ddd;">
        <button onclick="window.print()"
            style="padding: 10px 30px; background: #27ae60; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-family: 'Rabar013'; font-weight: bold;">🖨️
            دەستپێکردنی چاپکردن</button>
    </div>

    <div class="print-container">
        <div class="header">
            <div class="logo">
                <img src="<?php echo $logo; ?>" alt="Logo">
            </div>
            <div class="header-text">
                <h1><?php echo htmlspecialchars($org_name); ?></h1>
                <h2><?php echo htmlspecialchars($sub_title); ?></h2>
                <h3><?php echo htmlspecialchars($dept_name); ?></h3>
            </div>
            <div style="width: 90px;"></div>
        </div>

        <div class="form-title">فۆڕمی زانیاری ئەندامان</div>

        <div class="top-section">
            <div class="main-fields">
                <div style="display: flex; justify-content: flex-start; margin-bottom: 8px;">
                    <div class="field-group" style="width: 280px;">
                        <div class="field-label">کۆدی ئەندام</div>
                        <div class="field-value"><?php echo htmlspecialchars($member['member_code'] ?? ''); ?></div>
                    </div>
                </div>
                <div class="field-group">
                    <div class="field-label">ناوی سیانی</div>
                    <div class="field-value" style="font-weight: bold; font-size: 22px;">
                        <?php echo htmlspecialchars($member['full_name'] ?? ''); ?></div>
                </div>
                <div style="height: 45px; border: 1px solid #000; margin-top: 5px; background: #fff;"></div>
            </div>
            <div class="photo-box">
                <?php if (!empty($member['profile_image']) && file_exists($member['profile_image'])): ?>
                    <img src="<?php echo $member['profile_image']; ?>" alt="Photo">
                <?php else: ?>
                    وێنە
                <?php endif; ?>
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td class="label-cell">ئاستی خوێندن</td>
                <td class="value-cell"><?php echo htmlspecialchars($member['education'] ?? ''); ?></td>
            </tr>
            <tr>
                <td class="label-cell">ناونیشان</td>
                <td class="value-cell"><?php echo htmlspecialchars($member['address'] ?? ''); ?></td>
            </tr>
            <tr>
                <td class="label-cell">بەروار و شوێنی لەدایکبوون</td>
                <td class="value-cell"><?php echo htmlspecialchars($member['birth_date'] ?? ''); ?>
                    <?php echo !empty($member['birth_place']) ? " - " . htmlspecialchars($member['birth_place']) : ''; ?>
                </td>
            </tr>
            <tr>
                <td class="label-cell">بنەماڵە</td>
                <td class="value-cell"><?php echo htmlspecialchars($member['family_name'] ?? ''); ?></td>
            </tr>
            <tr>
                <td class="label-cell">زمان</td>
                <td class="value-cell"><?php echo htmlspecialchars($member['languages'] ?? ''); ?></td>
            </tr>
            <tr>
                <td class="label-cell">بەرواری بە ئەندامبوون</td>
                <td class="value-cell"><?php echo htmlspecialchars($member['membership_date'] ?? ''); ?></td>
            </tr>
            <tr>
                <td class="label-cell">پیشە</td>
                <td class="value-cell"><?php echo htmlspecialchars($member['profession'] ?? ''); ?></td>
            </tr>
            <tr>
                <td class="label-cell">گرووپی خوێن</td>
                <td class="value-cell"><?php echo htmlspecialchars($member['blood_group'] ?? ''); ?></td>
            </tr>
            <tr>
                <td class="label-cell">ژمارەی مۆبایل</td>
                <td class="value-cell" style="direction: ltr;"><?php echo htmlspecialchars($member['phone'] ?? ''); ?>
                </td>
            </tr>
        </table>

        <div class="footer-note">
            من کە لە خوارەوە واژۆم کردووە بە بەرپرسیارم لە ڕاستی و دروستی هەموو ئەو زانیارییانەی لەم فۆڕمەدا تۆمار کراوە
            .
        </div>

        <div class="attachments-note">
            هاوپێچ : وێنەیەک لە کارتی نیشتمانی یان ناسنامەی باری کەسی (نفوس).
        </div>

        <div class="signatures">
            <div class="signature-box">
                <h4>واژۆی ئەندام</h4>
            </div>
            <div class="signature-box">
                <h4>بەشی نەسەب و ئاینی</h4>
            </div>
            <div class="signature-box">
                <h4>بەشی کارگێڕی و ئامار</h4>
            </div>
            <div class="signature-box">
                <h4>بەڕێوەبەری مەڵبەند</h4>
            </div>
        </div>


    </div>

</body>

</html>