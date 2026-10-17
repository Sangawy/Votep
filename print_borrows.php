<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$settings = [
    'system_name' => 'کتێبخانەی پێشمەرگە',
    'logo' => 'assets/logo.jpg',
    'description' => 'سیستەمی بەڕێوەبردنی کتێبخانە'
];

$records = [];
$prepared_by = $_SESSION['username'] ?? 'بەکارهێنەر';

if ($conn) {
    // Get Settings
    $res = $conn->query("SELECT * FROM settings WHERE id = 1");
    if ($res && $res->num_rows > 0) {
        $settings = $res->fetch_assoc();
    }

    // Get Borrowed Records
    $sql = "SELECT br.*, u.full_name, u.phone, u.unit, b.title as book_title, b.book_code 
            FROM borrow_records br 
            JOIN users u ON br.user_id = u.id 
            JOIN books b ON br.book_id = b.id 
            ORDER BY br.borrow_date DESC";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>پرینتکردنی لیستی خواستنەکان</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #5a0202; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Noto Sans Arabic', sans-serif; background: #fff; color: #000; padding: 10px; }
        
        @page { size: landscape; margin: 10mm; }

        .print-container { max-width: 100%; margin: 0 auto; }
        
        /* Header Styling Compact */
        .print-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--primary); padding-bottom: 10px; margin-bottom: 20px; }
        .logo-section { width: 60px; height: 60px; }
        .logo-section img { width: 100%; height: 100%; object-fit: contain; }
        
        .title-section { text-align: center; flex: 1; }
        .title-section h1 { font-size: 1.6rem; color: var(--primary); margin-bottom: 3px; }
        .title-section h2 { font-size: 1.1rem; color: #444; margin-bottom: 2px; }
        .title-section p { font-size: 0.85rem; color: #666; }
        
        .meta-section { text-align: left; font-size: 0.8rem; line-height: 1.4; }
        .meta-section strong { color: var(--primary); }

        /* Table Styling Compact */
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th { background: #f4f4f4 !important; color: #000; padding: 8px 5px; text-align: right; border: 1px solid #ccc; font-weight: 700; font-size: 0.85rem; }
        td { padding: 6px 5px; border: 1px solid #ccc; font-size: 0.8rem; line-height: 1.2; }
        tr:nth-child(even) { background: #fafafa; }

        .status-cell { font-weight: 700; font-size: 0.75rem; }
        .overdue { color: #d32f2f; }

        .footer { margin-top: 30px; display: flex; justify-content: space-around; text-align: center; }
        .footer-box { width: 200px; font-size: 0.85rem; }
        .signature-line { margin-top: 30px; border-top: 1px dotted #000; padding-top: 5px; }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            th { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="print-container">
        <header class="print-header">
            <div class="logo-section">
                <img src="<?php echo htmlspecialchars($settings['logo'] ?? 'assets/logo.jpg'); ?>" alt="Logo">
            </div>
            <div class="title-section">
                <h1><?php echo htmlspecialchars($settings['system_name'] ?? 'کتێبخانەی پێشمەرگە'); ?></h1>
                <h2>ڕاپۆرتی خواستنەکان (Borrowing Report)</h2>
                <p><?php echo htmlspecialchars($settings['description'] ?? 'سیستەمی بەڕێوەبردنی کتێبخانە'); ?></p>
            </div>
            <div class="meta-section">
                <p><strong>ڕێکەوت:</strong> <?php echo date('Y-m-d'); ?></p>
                <p><strong>کات:</strong> <?php echo date('H:i'); ?></p>
                <p><strong>ئامادەکار:</strong> <?php echo htmlspecialchars($prepared_by); ?></p>
            </div>
        </header>

        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">#</th>
                    <th>بەکارهێنەر</th>
                    <th>ژمارەی مۆبایل</th>
                    <th>بەش/یەکە</th>
                    <th>ناوی کتێب</th>
                    <th>کۆدی کتێب</th>
                    <th>ڕێکەوتی بردن</th>
                    <th>پێویستە بگەڕێتەوە</th>
                    <th>دۆخ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="9" style="text-align:center; padding: 40px;">هیچ تۆمارێک نەدۆزرایەوە</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $index => $r): ?>
                        <?php 
                            $is_overdue = (strtotime($r['due_date']) < time() && $r['status'] == 'borrowed');
                            $status_text = ($r['status'] == 'borrowed' ? 'خواستراو' : 'گەڕاوەتەوە');
                            if ($is_overdue) $status_text = 'بەسەرچوو';
                        ?>
                        <tr>
                            <td style="text-align: center;"><?php echo $index + 1; ?></td>
                            <td><strong><?php echo htmlspecialchars($r['full_name'] ?? '-'); ?></strong></td>
                            <td><?php echo htmlspecialchars($r['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($r['unit'] ?? '-'); ?></td>
                            <td style="color: var(--primary); font-weight: 600;"><?php echo htmlspecialchars($r['book_title'] ?? '-'); ?></td>
                            <td style="font-family: monospace;"><?php echo htmlspecialchars($r['book_code'] ?? '-'); ?></td>
                            <td><?php echo $r['borrow_date']; ?></td>
                            <td style="<?php echo $is_overdue ? 'color: red; font-weight: 700;' : ''; ?>"><?php echo $r['due_date']; ?></td>
                            <td class="status-cell <?php echo $is_overdue ? 'overdue' : ''; ?>"><?php echo $status_text; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer">
            <div class="footer-box">
                <p><strong>ئامادەکاری ڕاپۆرت</strong></p>
                <div class="signature-line"><?php echo htmlspecialchars($prepared_by); ?></div>
            </div>
            <div class="footer-box">
                <p><strong>واژووی بەڕێوەبەر</strong></p>
                <div class="signature-line">مۆر و واژوو</div>
            </div>
        </div>
    </div>

    <div class="no-print" style="position: fixed; bottom: 20px; right: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: var(--primary); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: bold;">دووبارە پرینتکردن</button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #eee; color: #333; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; margin-right: 10px;">داخستن</button>
    </div>
</body>
</html>
