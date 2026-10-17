<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$id = $_GET['id'] ?? 0;
$query = "SELECT a.*, m.full_name, m.member_code, r.rank_name, u.unit_name, m.position, m.unit_id
          FROM staff_attendance a 
          JOIN staff_members m ON a.staff_id = m.id 
          JOIN staff_ranks r ON m.rank_id = r.id 
          JOIN staff_units u ON m.unit_id = u.id 
          WHERE a.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$a = $res->fetch_assoc();

if (!$a) {
    die("تۆمارەکە نەدۆزرایەوە");
}

// Fetch Unit Manager and Deputy
$unit_id = $a['unit_id'];
$sig_query = "SELECT 
                m1.full_name as manager_name, r1.rank_name as manager_rank,
                m2.full_name as deputy_name, r2.rank_name as deputy_rank
              FROM staff_units u
              LEFT JOIN staff_members m1 ON u.manager_id = m1.id
              LEFT JOIN staff_ranks r1 ON m1.rank_id = r1.id
              LEFT JOIN staff_members m2 ON u.deputy_id = m2.id
              LEFT JOIN staff_ranks r2 ON m2.rank_id = r2.id
              WHERE u.id = ?";
$stmt_sig = $conn->prepare($sig_query);
$stmt_sig->bind_param("i", $unit_id);
$stmt_sig->execute();
$sig = $stmt_sig->get_result()->fetch_assoc();

$type_name = $a['leave_type'];
switch($a['leave_type']) {
    case 'Annual Leave': $type_name = 'مۆڵەتی ئاسایی'; break;
    case 'Sick Leave': $type_name = 'مۆڵەتی نەخۆشی'; break;
    case 'Absence': $type_name = 'غیابات'; break;
    case 'Official Mission': $type_name = 'ئیفاد (ئەرکی فەرمی)'; break;
}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>پرینتی داواکاری مۆڵەت - <?php echo htmlspecialchars($a['full_name']); ?></title>
    <style>
        @font-face {
            font-family: 'Rabar013';
            src: url('assets/font/Rabar_013.ttf') format('truetype');
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
        }

        h1, h2, .form-title, .info-label, strong, b {
            font-family: 'Rabar013', 'Segoe UI', Tahoma, sans-serif;
        }

        .print-container {
            background: white;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 20mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            box-sizing: border-box;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }

        .header img {
            height: 80px;
        }

        .header-text {
            text-align: center;
            flex-grow: 1;
        }

        .header-text h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #333;
        }

        .header-text h2 {
            margin: 5px 0 0;
            font-size: 1.1rem;
            color: #666;
        }

        .form-title {
            text-align: center;
            margin: 20px 0;
            text-decoration: underline;
            font-size: 1.4rem;
            font-weight: bold;
        }

        .content-section {
            margin-bottom: 25px;
            line-height: 1.8;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            border-bottom: 1px dotted #ccc;
            padding-bottom: 5px;
        }

        .info-label {
            font-weight: bold;
            min-width: 120px;
        }

        .info-value {
            flex-grow: 1;
        }

        .reason-box {
            border: 1px solid #333;
            padding: 15px;
            margin-top: 10px;
            min-height: 100px;
            background: #fafafa;
        }

        .signatures {
            margin-top: 50px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
        }

        .signature-block {
            text-align: center;
        }

        .sig-line {
            margin-top: 60px;
            border-top: 1px solid #333;
            padding-top: 5px;
        }

        .footer-note {
            position: absolute;
            bottom: 20mm;
            left: 20mm;
            right: 20mm;
            font-size: 0.8rem;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
        }

        .action-bar {
            position: fixed;
            top: 20px;
            left: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }

        @media print {
            body { background: white; padding: 0; }
            .print-container { box-shadow: none; margin: 0; width: 100%; border: none; }
            .action-bar { display: none; }
        }
    </style>
</head>
<body>

<div class="action-bar">
    <button onclick="window.print()" class="btn btn-primary">🖨️ چاپکردن</button>
    <a href="staff_attendance.php" class="btn btn-secondary">🔙 گەڕانەوە</a>
</div>

<div class="print-container">
    <div class="header">
        <img src="assets/logo.png" alt="Logo" onerror="this.src='assets/logo.svg'">
        <div class="header-text">
            <h1>وەزارەتی پێشمەرگە</h1>
            <h2>بەڕێوەبەرایەتی گشتی ڕاگەیاندن و هۆشیاری نیشتمانی</h2>
        </div>
        <div style="width: 80px;"></div> <!-- Spacer for balance -->
    </div>

    <div class="form-title">داواکاری مۆڵەت</div>

    <div class="content-section">
        <p>داواکارم ڕەزامەندی بفەرموون لەسەر پێدانی <strong>(<?php echo $a['duration_days']; ?>)</strong> ڕۆژ 
           <strong><?php echo $type_name; ?></strong> 
           لە ڕێکەوتی <strong><?php echo $a['start_date']; ?></strong> 
           تاوەکو ڕێکەوتی <strong><?php echo $a['end_date']; ?></strong>.
        </p>
    </div>

    <div class="content-section">
        <span class="info-label">مەبەست / شوێنی مۆڵەت:</span>
        <div class="reason-box">
            <?php echo nl2br(htmlspecialchars($a['reason'] ?? '')); ?>
        </div>
    </div>

    <div style="margin-top: 60px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; text-align: center;">
        <!-- Manager Signature -->
        <div style="line-height: 1.8;">
            <div style="font-weight: bold; text-decoration: underline; margin-bottom: 15px;">بڕیاری بەڕێوەبەر / سەرۆکی دەستەی کارگێڕی</div>
            <?php if (!empty($sig['manager_name'])): ?>
                <div><?php echo htmlspecialchars($sig['manager_rank']); ?></div>
                <div style="font-weight: bold;"><?php echo htmlspecialchars($sig['manager_name']); ?></div>
                <div>بەڕێوەبەری <?php echo htmlspecialchars($a['unit_name']); ?></div>
            <?php else: ?>
                <div style="color: #999; font-style: italic;">(بەرپرسی یەکە دیاری نەکراوە)</div>
            <?php endif; ?>
            <div style="margin-top: 15px;">واژۆ: .......................................</div>
            <div>ڕێکەوت: ...... / ...... / <?php echo date('Y'); ?></div>
        </div>

        <!-- Applicant Info (Already on the right, keeping it consistent) -->
        <div style="line-height: 1.8;">
            <div style="font-weight: bold; text-decoration: underline; margin-bottom: 15px;">زانیاری داواکار</div>
            <div><?php echo htmlspecialchars($a['rank_name'] ?? ''); ?></div>
            <div style="font-weight: bold;"><?php echo htmlspecialchars($a['full_name'] ?? ''); ?></div>
            <div>بەشی <?php echo htmlspecialchars($a['position'] ?? 'نادیار'); ?></div>
            <div style="margin-top: 15px;">واژۆ: .......................................</div>
            <div>ڕێکەوت: ...... / ...... / <?php echo date('Y'); ?></div>
        </div>
    </div>

    <?php if (!empty($sig['deputy_name'])): ?>
    <div style="margin-top: 40px; border-top: 1px dashed #eee; padding-top: 20px; text-align: center; opacity: 0.8;">
        <div style="font-size: 0.9rem;">تێبینی: جێگری بەڕێوەبەر (<?php echo htmlspecialchars($sig['deputy_rank'] . ' ' . $sig['deputy_name']); ?>) ئاگادار کرایەوە.</div>
    </div>
    <?php endif; ?>

    <div class="footer-note">
        <span>سیستەمی ئەلیکترۆنی وەزارەتی پێشمەرگە</span>
        <span>کۆدی کارمەند: <?php echo htmlspecialchars($a['member_code'] ?? ''); ?></span>
        <span>کاتی چاپکردن: <?php echo date('Y-m-d H:i'); ?></span>
    </div>
</div>

</body>
</html>
