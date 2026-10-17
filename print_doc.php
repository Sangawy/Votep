<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit("Invalid Request");
}

require_once 'config.php';
$conn = getDB();
$logo_path = 'assets/logo.jpg';
if ($conn) {
    $res = $conn->query("SELECT logo FROM settings WHERE id = 1");
    if ($res && $row = $res->fetch_assoc()) {
        if (!empty($row['logo'])) $logo_path = $row['logo'];
    }
}

$num = htmlspecialchars(urldecode($_POST['num'] ?? ''));
$date = htmlspecialchars(urldecode($_POST['date'] ?? ''));
$to = htmlspecialchars(urldecode($_POST['to'] ?? ''));
$subject = htmlspecialchars(urldecode($_POST['subject'] ?? ''));
$content = urldecode($_POST['content'] ?? '');
$sign_name = htmlspecialchars(urldecode($_POST['sign_name'] ?? ''));
$sign_role = htmlspecialchars(urldecode($_POST['sign_role'] ?? ''));

?>
<!DOCTYPE html>
<html lang="ckb" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>چاپکردنی نوسراو - <?php echo $num; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Arabic', Arial, sans-serif;
            background: #fff;
            color: #000;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact;
        }

        .a4-page {
            width: 210mm;
            min-height: 297mm;
            padding: 15mm;
            margin: 10mm auto;
            background: white;
            box-sizing: border-box;
            position: relative;
        }

        /* Ministry Header Styling */
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .header-right, .header-left, .header-center {
            text-align: center;
        }

        .header-right h3, .header-left h3 {
            margin: 0;
            font-size: 13pt;
            font-weight: 700;
            line-height: 1.4;
        }

        .header-center h3 {
            margin: 0 0 10px 0;
            font-size: 13pt;
            font-weight: 700;
        }

        .header-logo {
            width: 100px;
            height: 100px;
            object-fit: contain;
        }

        /* Number and Date Box */
        .doc-meta-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 2px solid #000;
            border-radius: 8px;
            padding: 5px 15px;
            margin-bottom: 30px;
        }

        .meta-col {
            font-size: 14pt;
            font-weight: 700;
        }

        .doc-title {
            text-align: center;
            font-size: 18pt;
            font-weight: 700;
            margin-bottom: 40px;
        }

        .doc-meta {
            margin-bottom: 20px;
            line-height: 1.6;
            font-size: 14pt;
        }

        .doc-meta .meta-row {
            display: flex;
            margin-bottom: 15px;
        }

        .doc-meta .meta-label {
            font-weight: bold;
            margin-left: 10px;
            white-space: nowrap;
        }
        
        .doc-meta .meta-value {
            border-bottom: 1px solid #000;
            flex: 1;
            padding-bottom: 2px;
            font-weight: bold;
        }

        .doc-body {
            font-size: 14pt;
            line-height: 1.8;
            min-height: 120mm;
            text-align: justify;
        }

        .sign-area {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }

        .sign-box {
            text-align: center;
            width: 250px;
        }

        .sign-box h4 {
            margin-bottom: 80px;
            font-size: 14pt;
        }

        .qr-code-box {
            position: absolute;
            bottom: 20mm;
            left: 15mm;
            text-align: center;
        }

        .qr-code-box img {
            width: 100px;
            height: 100px;
            border: 1px solid #eee;
            padding: 5px;
            background: white;
        }

        .qr-label {
            font-size: 8pt;
            margin-top: 5px;
            color: #666;
            font-weight: bold;
        }

        @media print {
            body { background: none; }
            .a4-page { margin: 0; width: 100%; height: 100%; padding: 15mm; box-shadow: none; border: none; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body onload="setTimeout(function(){ window.print(); }, 500);">

    <div class="a4-page">
        <header class="print-header">
            <div class="header-right">
                <h3>هەرێمی كوردستان - عێراق</h3>
                <h3>ئەنجومەنی وەزيران</h3>
                <h3>وەزارەتی پێشمەرگە</h3>
                <h3>سەرۆکایەتی ئەرکان</h3>
                <h3>دائیرەی ئیدارە و میرە</h3>
                <h3>بەڕێوەبەرایەتی ئیدارەی گشتی</h3>
            </div>
            
            <div class="header-center">
                <h3>بەناوی خوای گەورە و میهرەبان</h3>
                <img src="<?php echo htmlspecialchars($logo_path); ?>" class="header-logo" alt="وەزارەت" onerror="this.src=''; this.alt='ئارم'">
            </div>
            
            <div class="header-left">
                <h3>إقليم كوردستان - العراق</h3>
                <h3>مجلس الوزراء</h3>
                <h3>وزارة البيشمركة</h3>
                <h3>رئاسة الأركان</h3>
                <h3>دائرة الإدارة والميرة</h3>
                <h3>مديرية الإدارة العامة</h3>
            </div>
        </header>

        <div class="doc-meta-box">
            <div class="meta-col">کوردی</div>
            <div class="meta-col">ڕێکەوت: <?php echo $date; ?></div>
            <div class="meta-col" style="direction: ltr; text-align: right;">(<?php echo $num; ?> / أ) ژمارە</div>
        </div>

        <h2 class="doc-title">فـەرمـانی وەزاری</h2>

        <div class="doc-meta">
            <?php if (!empty($to)): ?>
            <div class="meta-row">
                <span class="meta-label">بۆ /</span>
                <span class="meta-value"><?php echo $to; ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($subject)): ?>
            <div class="meta-row">
                <span class="meta-label">بابەت /</span>
                <span class="meta-value"><?php echo $subject; ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="doc-body">
            <?php 
                echo $content;
            ?>
        </div>

        <div class="sign-area">
            <div class="sign-box">
                <!-- Left blank if needed -->
            </div>
            <div class="sign-box">
                <h4><?php echo empty($sign_name) ? '<br>' : $sign_name; ?><br><?php echo empty($sign_role) ? '<br>' : $sign_role; ?></h4>
            </div>
        </div>

        <?php 
            // Verification URL - adjust the domain/IP to your local setup
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            // Safe Base Path Detection
            $base_dir = '/';
            if (strpos($_SERVER['SCRIPT_NAME'], '/libraray_Peshmerga/') !== false) {
                $base_dir = '/libraray_Peshmerga/';
            }
            $base_dir = rtrim($base_dir, '/');

            $verify_url = "$protocol://$host$base_dir/docs?search=" . urlencode($num);
            $qr_api = "https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=" . urlencode($verify_url);
        ?>
        <div class="qr-code-box">
            <img src="<?php echo $qr_api; ?>" alt="QR Code">
            <div class="qr-label">بۆ دڵنیابوونەوە لە ڕاستی نوسراوەکە سکانی بکە</div>
        </div>
    </div>

</body>
</html>
