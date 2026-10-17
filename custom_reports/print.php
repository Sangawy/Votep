<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized access');
}

require_once '../config.php';
$conn = getDB();

// 1. Get System Settings
$settings = [
    'system_name' => 'سیستەمی پێشمەرگە',
    'logo' => 'assets/imgae/user.png',
    'primary_color' => '#5a0202'
];
$res_set = $conn->query("SELECT system_name, logo, sidebar_color FROM settings WHERE id = 1");
if ($res_set && $row_set = $res_set->fetch_assoc()) {
    if (!empty($row_set['system_name'])) $settings['system_name'] = $row_set['system_name'];
    if (!empty($row_set['logo'])) $settings['logo'] = $row_set['logo'];
    if (!empty($row_set['sidebar_color'])) $settings['primary_color'] = $row_set['sidebar_color'];
}

// Optional fields mapping
$OPTIONAL_FIELDS = [
    'rank_name' => 'پلە',
    'full_name' => 'ناوی تەواو',
    'position' => 'پایە',
    'birth_year' => 'لەدایکبوون',
    'education' => 'بڕوانامە',
    'phone' => 'مۆبایل',
    'unit_name' => 'یەکە',
    'birth_place' => 'شوێنی لەدایکبوون',
    'id_number' => 'ژمارەی ناسنامە',
    'city' => 'شار',
    'town' => 'قەزا',
    'district' => 'ناحیه',
    'village' => 'گوند',
    'branch' => 'ڕشتە',
    'last_promotion_date' => 'دوا پلە',
    'promotion_due_date' => 'شایستەی داهاتوو',
    'appointment_order' => 'ژ. فەرمانی دامەزاردن',
    'appointment_date' => 'بەرواری فەرمانی دامەزاردن',
    'civil_id' => 'ژمارەی ناسنامەی مەدەنی',
    'form_number' => 'ژمارەی فۆڕمی خۆراک',
    'voting_number' => 'ژمارەی دەنگدان',
    'biometric_number' => 'ژمارەی بایۆ مەتری',
    'marital_status' => 'باری خێزانی',
    'children_count' => 'ژمارەی منداڵەکان',
    'military_branch_sinf' => 'صنف (جۆری چەک)',
    'military_education' => 'بڕوانامەی سەربازی',
    'health_status' => 'باری تەندروستی',
    'physical_fitness' => 'لێهاتوویی جەستەیی',
    'blood_group' => 'گروپی خوێن',
    'languages' => 'زمانەکان',
    'injuries' => 'برینداربوون / کەمئەندامی',
    'honors_medals' => 'مەدالیا و سوپاسنامەکان',
    'notes' => 'تێبینی',
];

// Fixed fields (these will always show if needed, or we can just rely on selection)
$FIXED_FIELDS = []; // We will rely on the order and selection from index.php

// Get user selection from POST
$selected_optional = $_POST['fields'] ?? [];
if (!is_array($selected_optional)) {
    $selected_optional = [];
}

// Get print settings
$sort_by = $_POST['sort_by'] ?? 'priority_desc';
$show_watermark = ($_POST['show_watermark'] ?? '0') === '1';
$sig_preparer = ($_POST['sig_preparer'] ?? '0') === '1';
$sig_reviewer = ($_POST['sig_reviewer'] ?? '0') === '1';
$sig_manager = ($_POST['sig_manager'] ?? '0') === '1';

// Combine fixed and selected optional fields
$all_visible_fields = $FIXED_FIELDS;
foreach ($selected_optional as $field) {
    if (isset($OPTIONAL_FIELDS[$field])) {
        $all_visible_fields[$field] = $OPTIONAL_FIELDS[$field];
    }
}

// Fetch data sorted based on selection
switch ($sort_by) {
    case 'seniority_asc':
        $order_clause = "ORDER BY m.last_promotion_date ASC, m.full_name ASC";
        break;
    case 'age_asc':
        $order_clause = "ORDER BY m.birth_year ASC, m.full_name ASC";
        break;
    case 'education_asc':
        $order_clause = "ORDER BY m.education ASC, m.full_name ASC";
        break;
    case 'priority_desc':
    default:
        $order_clause = "ORDER BY r.priority DESC, m.full_name ASC";
        break;
}

$query = "SELECT m.*, r.rank_name, r.priority, u.unit_name 
          FROM staff_members m 
          JOIN staff_ranks r ON m.rank_id = r.id 
          JOIN staff_units u ON m.unit_id = u.id 
          $order_clause";
$result = $conn->query($query);

// Total pages calculation for pagination (X-Y)
$total_rows = $result ? $result->num_rows : 0;
$rows_per_page = 20;
$total_pages = ceil($total_rows / $rows_per_page);
if ($total_pages == 0) $total_pages = 1;

// Determine Unit Name for Title
$unit_title = $settings['system_name'];
if ($result && $result->num_rows > 0) {
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    $first_unit = $data[0]['unit_name'] ?? '';
    $all_same_unit = true;
    foreach ($data as $row) {
        if ($row['unit_name'] !== $first_unit) {
            $all_same_unit = false;
            break;
        }
    }
    
    if ($all_same_unit && !empty($first_unit)) {
        $unit_title = "لیستی کارمەندانی " . $first_unit;
    } else {
        $unit_title = "لیستی کارمەندانی " . $settings['system_name'];
    }
} else {
    $data = [];
}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $unit_title; ?></title>
    <style>
        @font-face {
            font-family: 'Rabar_013';
            src: url('../assets/font/Rabar_013.ttf') format('truetype');
        }
        @font-face {
            font-family: 'Rabar_022';
            src: url('../assets/font/Rabar_022.ttf') format('truetype');
        }

        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            font-family: 'Rabar_022', 'Arial', sans-serif;
            margin: 0;
            padding: 10mm;
            color: #000;
            background-color: #fff;
            -webkit-print-color-adjust: exact;
        }
        .no-print {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
        }
        .no-print button {
            padding: 8px 16px;
            font-size: 13px;
            cursor: pointer;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            transition: 0.2s;
        }
        .btn-print { background: #1890ff; color: #fff; }
        .btn-close { background: #ff4d4f; color: #fff; }
        
        /* Watermark Style */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 600px;
            height: 600px;
            opacity: 0.05;
            z-index: -1;
            pointer-events: none;
        }
        .watermark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* Pagination and Signature Footer Style */
        .page-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 0 10mm 5mm 10mm;
            font-size: 11pt;
            color: #000;
        }
        .footer-sig-group {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            width: 100%;
            margin-bottom: 10px;
        }
        .footer-sig {
            text-align: center;
            font-weight: bold;
            font-family: 'Rabar_013', serif;
            min-height: 1.2em;
        }
        .page-number-row {
            text-align: center;
            width: 100%;
            font-family: 'Rabar_013', serif;
        }

        .report-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
            min-height: 80px;
            font-family: 'Rabar_013', serif;
        }
        .header-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }
        .header-title {
            text-align: right;
            flex: 1;
        }
        .header-title h1 {
            font-size: 11pt;
            margin: 0;
            color: #000;
            font-weight: bold;
        }
        .header-title p {
            font-size: 11pt;
            margin: 5px 0 0 0;
            color: #000;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
            font-size: 11pt;
            border: 1px solid #000 !important;
            font-family: 'Rabar_022', sans-serif;
        }
        th {
            background-color: #f2f2f2 !important;
            color: #000 !important;
            padding: 6px 5px !important;
            text-align: center;
            border: 1px solid #000 !important;
            font-weight: bold;
            font-family: 'Rabar_022', sans-serif;
        }
        td {
            border: 1px solid #000 !important;
            padding: 3px 5px !important;
            text-align: center;
            word-wrap: break-word;
            line-height: 1.2;
            font-family: 'Rabar_022', sans-serif;
        }
        tr:nth-child(even) {
            background-color: #fafafa !important;
        }
        
        @media print {
            .no-print { display: none !important; }
            body { 
                background: #fff !important; 
                margin: 0 !important; 
                padding: 10mm 10mm 20mm 10mm !important; 
            }
            table { 
                page-break-inside: auto !important; 
                width: 100% !important; 
                border: 1px solid #000 !important; 
            }
            th, td { 
                border: 1px solid #000 !important; 
            }
            tr { page-break-inside: avoid !important; page-break-after: auto !important; }

            /* Pagination using CSS counters */
            body {
                counter-reset: page;
            }
            .page-number::after {
                counter-increment: page;
                content: counter(page) " - <?php echo $total_pages; ?>";
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn-print">🖨️ چاپکردن (Print)</button>
        <button onclick="window.close()" class="btn-close">❌ داخستن (Close)</button>
    </div>

    <?php if ($show_watermark): ?>
    <div class="watermark">
        <img src="../<?php echo $settings['logo']; ?>" alt="Watermark">
    </div>
    <?php endif; ?>

    <div class="page-footer">
        <div class="footer-sig-group">
            <div class="footer-sig">
                <?php echo $sig_preparer ? 'ئیمزای ئامادەکار' : ''; ?>
            </div>
            
            <div class="footer-sig">
                <?php echo $sig_reviewer ? 'پێداچوونەوە (وردبینیکار)' : ''; ?>
            </div>
            
            <div class="footer-sig">
                <?php echo $sig_manager ? 'بەڕێوەبەری گشتی' : ''; ?>
            </div>
        </div>
        <div class="page-number-row">
            <div class="page-number"></div>
        </div>
    </div>
    
    <div class="report-header">
        <div class="header-title">
            <h1><?php echo $unit_title; ?></h1>
            <p>بەرواری چاپکردن: <?php echo date('Y-m-d'); ?></p>
        </div>
        <img src="../<?php echo $settings['logo']; ?>" class="header-logo" alt="Logo">
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 40px;">ڕیز</th>
                <?php foreach ($all_visible_fields as $field => $label): ?>
                    <th><?php echo $label; ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (empty($data)): ?>
                <tr><td colspan="<?php echo count($all_visible_fields) + 1; ?>" style="padding: 30px;">هیچ زانیارییەک نەدۆزرایەوە</td></tr>
            <?php else: 
                $index = 1;
                foreach ($data as $s): ?>
                    <tr>
                        <td><?php echo $index++; ?></td>
                        <?php foreach ($all_visible_fields as $field => $label): ?>
                            <td><?php echo htmlspecialchars($s[$field] ?? ''); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; 
            endif; ?>
        </tbody>
    </table>
</body>
</html>
