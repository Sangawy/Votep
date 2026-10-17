<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

// Standard ranks for the system
$ranks = [
    'سەرۆکی دەستەی کارگیری',
    'دەستەی کارگێڕی (هەمیشەیی)',
    'دەستەی کارگیڕی (یەدەگ)',
    'ئەنجومەنی ڕاوێژکاری',
    'ئەندامی کارا',
    'ئەندامی کارگیڕی',
    'ئەندام'
];

$posts_res = $conn->query("SELECT DISTINCT member_post FROM members WHERE member_post != '' ORDER BY member_post");
$posts = [];
while ($p = $posts_res->fetch_assoc())
    $posts[] = $p['member_post'];

$families_res = $conn->query("SELECT id, family_name FROM families ORDER BY family_name");
$families = [];
while ($f = $families_res->fetch_assoc())
    $families[] = $f;

$elections_res = $conn->query("SELECT id, title FROM elections ORDER BY election_date DESC");
$elections = [];
while ($e = $elections_res->fetch_assoc())
    $elections[] = $e;

// Available Fields
$all_fields = [
    'member_code' => 'کۆدی ئەندام',
    'full_name' => 'ناوی تەواو',
    'member_rank' => 'پلە',
    'member_post' => 'پایە',
    'family_name' => 'بنەماڵە',
    'profession' => 'پیشە',
    'education' => 'ئاستی خوێندن',
    'address' => 'ناونیشان',
    'birth_place' => 'شوێنی لەدایکبوون',
    'birth_date' => 'بەرواری لەدایکبوون',
    'membership_date' => 'بەرواری ئەندامبوون',
    'languages' => 'زمان',
    'blood_group' => 'گرووپی خوێن',
    'phone' => 'ژمارەی مۆبایل',
    'id_card_number' => 'ژمارەی ناسنامە',
    'security_code' => 'کۆدی ئاسایش'
];

// Handle Report Generation
$show_report = false;
$members = [];
$selected_fields = [];

if (isset($_GET['generate'])) {
    $show_report = true;

    // Filters
    $filter_ranks = $_GET['filter_ranks'] ?? [];
    $filter_posts = $_GET['filter_posts'] ?? [];
    $filter_families = $_GET['filter_families'] ?? [];
    $filter_elections = $_GET['filter_elections'] ?? [];

    // Fields & Order
    $raw_fields = $_GET['fields'] ?? [];
    $field_orders = $_GET['orders'] ?? [];

    // Sort fields by order
    asort($field_orders);
    foreach ($field_orders as $key => $order) {
        if (isset($raw_fields[$key])) {
            $selected_fields[$key] = $all_fields[$key];
        }
    }

    // Fallback if no fields selected
    if (empty($selected_fields)) {
        $selected_fields = ['member_code' => 'کۆدی ئەندام', 'full_name' => 'ناوی تەواو'];
    }

    // Build Query - تەنها ئەندامانی فەرمی
    $where_clauses = ["m.is_official_member = 1"];
    $joins = "";
    $types = "";
    $params = [];

    if (!empty($filter_ranks)) {
        $placeholders = implode(',', array_fill(0, count($filter_ranks), '?'));
        $where_clauses[] = "m.member_rank IN ($placeholders)";
        foreach ($filter_ranks as $fr) {
            $params[] = $fr;
            $types .= "s";
        }
    }
    if (!empty($filter_posts)) {
        $placeholders = implode(',', array_fill(0, count($filter_posts), '?'));
        $where_clauses[] = "m.member_post IN ($placeholders)";
        foreach ($filter_posts as $fp) {
            $params[] = $fp;
            $types .= "s";
        }
    }
    if (!empty($filter_families)) {
        $placeholders = implode(',', array_fill(0, count($filter_families), '?'));
        $where_clauses[] = "m.family_id IN ($placeholders)";
        foreach ($filter_families as $ff) {
            $params[] = (int) $ff;
            $types .= "i";
        }
    }
    if (!empty($filter_elections)) {
        $placeholders = implode(',', array_fill(0, count($filter_elections), '?'));
        $joins .= " JOIN congress_members cm ON m.id = cm.member_id ";
        $where_clauses[] = "cm.election_id IN ($placeholders)";
        foreach ($filter_elections as $fe) {
            $params[] = (int) $fe;
            $types .= "i";
        }
    }

    $sql = "SELECT DISTINCT m.*, f.family_name 
            FROM members m 
            LEFT JOIN families f ON m.family_id = f.id 
            $joins
            WHERE " . implode(" AND ", $where_clauses) . "
            ORDER BY CAST(m.member_code AS UNSIGNED) ASC, m.full_name ASC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// DEDICATED PRINT MODE
if (isset($_GET['print_mode']) && $_GET['print_mode'] == '1') {
    // Fetch Settings
    $settings = [
        'system_name' => 'سیستەمی پیرخدری',
        'system_subtitle' => 'بنەماڵەی پیرخدری شاهۆ',
        'logo' => 'assets/logo.png'
    ];
    $res = $conn->query("SELECT * FROM settings WHERE id = 1");
    if ($res && $row = $res->fetch_assoc()) {
        $settings = array_merge($settings, $row);
    }

    // Render pure HTML without the system layout
    ?>
    <!DOCTYPE html>
    <html lang="ku" dir="rtl">

    <head>
        <meta charset="UTF-8">
        <title>ڕاپۆرتی ئەندامان - چاپکردن</title>
        <style>
            @font-face {
                font-family: 'Rabar_013';
                src: url('assets/font/Rabar_013.ttf') format('truetype');
            }

            @font-face {
                font-family: 'Rabar_005';
                src: url('assets/font/Rabar_005.ttf') format('truetype');
            }

            body {
                font-family: 'Rabar_005', Arial, sans-serif;
                margin: 0;
                padding: 15px;
                background: #fff;
                color: #000;
                font-size: 12pt;
            }

            .print-header {
                display: table;
                width: 100%;
                border-bottom: 2px solid #000;
                margin-bottom: 15px;
                padding-bottom: 10px;
                font-family: 'Rabar_013', Arial, sans-serif;
            }

            .header-cell {
                display: table-cell;
                vertical-align: middle;
            }

            .lib-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
                page-break-inside: auto;
            }

            .lib-table th,
            .lib-table td {
                border: 1px solid #000;
                padding: 6px 4px;
                text-align: center;
                font-size: 12pt;
            }

            .lib-table th {
                background-color: #00796b !important;
                color: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                font-family: 'Rabar_013', Arial, sans-serif;
                font-weight: normal;
            }

            .lib-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            .lib-table thead {
                display: table-header-group;
            }

            .report-footer {
                display: table;
                width: 100%;
                font-family: 'Rabar_013', Arial, sans-serif;
            }

            .footer-cell {
                display: table-cell;
                width: 50%;
                text-align: center;
            }

            .print-controls {
                text-align: center;
                margin-bottom: 20px;
            }

            .print-btn {
                background: #00796b;
                color: white;
                border: none;
                padding: 10px 20px;
                cursor: pointer;
                border-radius: 5px;
                font-size: 16px;
                margin: 0 10px;
                font-family: 'Rabar_013', Arial, sans-serif;
            }

            .close-btn {
                background: #e74c3c;
                color: white;
                border: none;
                padding: 10px 20px;
                cursor: pointer;
                border-radius: 5px;
                font-size: 16px;
                margin: 0 10px;
                font-family: 'Rabar_013', Arial, sans-serif;
            }

            @media print {
                .print-controls {
                    display: none !important;
                }

                body {
                    padding: 0;
                }

                @page {
                    margin: 15mm;
                    size:
                        <?php echo ($_GET['orientation'] ?? 'portrait'); ?>
                    ;
                }

                /* Fixed Footer to bottom of page */
                .report-footer {
                    position: fixed !important;
                    bottom: 0.2cm !important;
                    left: 0 !important;
                    width: 100% !important;
                }
            }
        </style>
    </head>

    <body>
        <div class="print-controls">
            <button class="print-btn" onclick="window.print()">🖨️ چاپکردن (Print)</button>
            <button class="close-btn" onclick="window.close()">❌ داخستن (Close)</button>
        </div>

        <div class="print-header">
            <div class="header-cell" style="text-align: right; width: 40%;">
                <div style="display: inline-block; text-align: center;">
                    <h2 style="margin: 0; font-size: 16pt; white-space: nowrap;"><?php echo htmlspecialchars($settings['system_name']); ?></h2>
                    <h4 style="margin: 5px 0 0 0; white-space: nowrap;"><?php echo htmlspecialchars($settings['system_subtitle']); ?></h4>
                    <p style="margin: 5px 0 0 0; font-size: 10pt; white-space: nowrap;"><?php echo htmlspecialchars($_GET['header_department'] ?? 'بەشی ئیدارە و کارگێڕی / نوسینگە'); ?></p>
                </div>
            </div>
            <div class="header-cell" style="text-align: center; width: 20%;">
                <img src="<?php echo $settings['logo']; ?>" style="height: 80px;">
            </div>
            <div class="header-cell" style="text-align: left; width: 40%; font-size: 10pt;">
                <p style="margin: 0;">بەرواری چاپ: <?php echo date('Y/m/d'); ?></p>
                <p style="margin: 5px 0 0 0;">کات: <?php echo date('H:i'); ?></p>
            </div>
        </div>

        <h3 style="text-align: center;">
            <?php echo htmlspecialchars($_GET['report_title'] ?? 'ڕاپۆرتی لیستی ئەندامان'); ?>
            (<?php echo count($members); ?> ئەندام)
        </h3>

        <table class="lib-table">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <?php foreach ($selected_fields as $key => $label): ?>
                        <th><?php echo $label; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $index => $m): ?>
                    <tr>
                        <td style="font-weight: bold;"><?php echo $index + 1; ?></td>
                        <?php foreach ($selected_fields as $key => $label): ?>
                            <td>
                                <?php
                                $val = $m[$key] ?? '-';
                                if ($key === 'full_name') {
                                    echo htmlspecialchars(buildFourPartName($m));
                                } elseif ($key === 'birth_date' || $key === 'membership_date') {
                                    echo $val ?: '-';
                                } else {
                                    echo htmlspecialchars($val ?: '-');
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($members)): ?>
                    <tr>
                        <td colspan="<?php echo count($selected_fields) + 1; ?>" style="padding: 30px; color: #888;">هیچ
                            ئەندامێک نەدۆزرایەوە بەپێی ئەم فلتەرانە.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="report-footer">
        
        </div>
        <script>
            // Automatically open print dialog on load
            window.onload = function () {
                setTimeout(function () {
                    window.print();
                }, 500);
            };
        </script>
    </body>

    </html>
    <?php
    exit; // Stop execution so layout.php is never loaded
}

// NORMAL UI MODE
$page_title = "ڕاپۆرت و پرینتی ئەندامان";
$active_nav = 'members_list';
$sys_module = 'nusinga';
require_once 'includes/layout.php';
?>

<style>
    .report-config {
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        padding: 25px;
        margin-bottom: 30px;
        border: 1px solid #eee;
    }

    .config-section {
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #f0f0f0;
    }

    .config-section:last-child {
        border-bottom: none;
    }

    .config-title {
        font-weight: bold;
        color: var(--primary);
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .check-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 10px;
    }

    .check-item {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f8f9fa;
        padding: 8px 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: 0.2s;
    }

    .check-item:hover {
        background: #eef2f7;
    }

    .check-item input {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    /* Custom Dropdown Filter */
    .dropdown-filter {
        position: relative;
        width: 100%;
        margin-top: 5px;
    }
    .dropdown-filter-btn {
        width: 100%;
        padding: 10px 15px;
        background: #f8f9fa;
        border: 1px solid #d1d9e6;
        border-radius: 8px;
        text-align: right;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.95rem;
        color: #2c3e50;
        transition: 0.2s;
    }
    .dropdown-filter-btn:hover {
        background: #eef2f7;
    }
    .dropdown-filter-content {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #d1d9e6;
        border-radius: 8px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        z-index: 1000;
        margin-top: 5px;
        overflow: hidden;
    }
    .dropdown-filter-content.active {
        display: block;
    }
    .dropdown-filter-search {
        width: 100%;
        padding: 12px;
        border: none;
        border-bottom: 1px solid #eee;
        box-sizing: border-box;
        outline: none;
        font-family: inherit;
        background: #fdfdfd;
    }
    .dropdown-filter-list {
        max-height: 250px;
        overflow-y: auto;
        padding: 8px;
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .field-order-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 15px;
    }

    .field-order-item {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #fff;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 10px;
    }

    .field-order-item input[type="number"] {
        width: 50px;
        padding: 5px;
        border: 1px solid #ccc;
        border-radius: 5px;
        text-align: center;
    }

    .report-preview {
        background: #fff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    }

    @media print {

        /* 1. Global Reset for A4 */
        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            width: 210mm !important;
            /* Force A4 width */
            height: auto !important;
            font-size: 11pt !important;
            font-family: 'Inter', 'Roboto', 'Arial', sans-serif !important;
        }

        /* 2. Hide UI */
        header,
        nav,
        footer,
        .sidebar,
        .top-bar,
        .breadcrumb,
        .no-print,
        .lib-btn,
        .report-config,
        .submit-bar,
        aside {
            display: none !important;
        }

        /* 3. Main Container */
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        .report-preview {
            width: 100% !important;
            margin: 0 !important;
            padding: 10mm !important;
            /* Margin inside page */
            box-sizing: border-box !important;
        }

        /* 4. Professional Header Table */
        .print-header {
            display: table !important;
            width: 100% !important;
            border-bottom: 2px solid #000 !important;
            margin-bottom: 20px !important;
            padding-bottom: 10px !important;
        }

        .header-cell {
            display: table-cell !important;
            vertical-align: middle !important;
        }

        /* 5. Professional Table */
        .lib-table {
            width: 100% !important;
            border-collapse: collapse !important;
            border: 2px solid #000 !important;
            margin-top: 10px !important;
        }

        .lib-table th,
        .lib-table td {
            border: 1px solid #000 !important;
            padding: 8px !important;
            text-align: center !important;
        }

        .lib-table th {
            background-color: #00796b !important;
            color: #fff !important;
            -webkit-print-color-adjust: exact !important;
        }

        /* 6. Signature Footer Table */
        .report-footer {
            display: table !important;
            width: 100% !important;
            margin-top: 50px !important;
            position: relative !important;
            /* Avoid fixed if it causes overlap issues */
        }

        .footer-cell {
            display: table-cell !important;
            width: 50% !important;
            text-align: center !important;
            vertical-align: top !important;
        }

        /* Page Break Control */
        tr {
            page-break-inside: avoid !important;
        }

        thead {
            display: table-header-group !important;
        }
    }

    .print-header,
    .report-footer {
        display: none;
    }
</style>

<script>
    function toggleGroup(containerId, state) {
        const container = document.getElementById(containerId);
        const checks = container.querySelectorAll('input[type="checkbox"]');
        checks.forEach(c => {
            c.checked = state;
            c.dispatchEvent(new Event('change'));
        });
    }

    function toggleDropdown(id) {
        document.querySelectorAll('.dropdown-filter-content').forEach(el => {
            if (el.id !== 'content_' + id) el.classList.remove('active');
        });
        document.getElementById('content_' + id).classList.toggle('active');
    }

    function filterDropdownList(groupId, query) {
        query = query.toLowerCase();
        const labels = document.getElementById(groupId).querySelectorAll('label.check-item');
        labels.forEach(label => {
            const text = label.textContent.toLowerCase();
            label.style.display = text.includes(query) ? 'flex' : 'none';
        });
    }

    function updateBtnText(type) {
        let group = '';
        let title = '';
        if (type === 'rank') { group = 'rank_group'; title = 'پلەکان'; }
        if (type === 'post') { group = 'post_group'; title = 'پایەکان'; }
        if (type === 'fam') { group = 'fam_group'; title = 'بنەماڵەکان'; }
        if (type === 'elect') { group = 'elect_group'; title = 'کۆنگرەکان'; }
        
        const checkedCount = document.getElementById(group).querySelectorAll('input:checked').length;
        const btnText = document.getElementById('btn_text_' + type);
        if (checkedCount > 0) {
            btnText.textContent = checkedCount + ' دیاریکراوە';
            btnText.style.color = '#27ae60';
            btnText.style.fontWeight = 'bold';
        } else {
            btnText.textContent = 'هەڵبژاردنی ' + title;
            btnText.style.color = '#2c3e50';
            btnText.style.fontWeight = 'normal';
        }
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-filter') && !e.target.closest('.check-item') && !e.target.closest('.config-section')) {
            document.querySelectorAll('.dropdown-filter-content').forEach(el => el.classList.remove('active'));
        }
    });

    window.addEventListener('DOMContentLoaded', () => {
        updateBtnText('rank');
        updateBtnText('post');
        updateBtnText('fam');
        updateBtnText('elect');
    });
</script>

<div class="no-print">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin: 0;"><i class="fas fa-file-invoice"></i> دروستکردنی ڕاپۆرت</h2>
        <div style="display: flex; gap: 10px;">
            <a href="members_list.php" class="lib-btn lib-btn-danger">🔙 گەڕانەوە</a>
        </div>
    </div>

    <form method="GET" action="members_reports.php" class="report-config" style="padding: 35px;">
        <input type="hidden" name="generate" value="1">

        <!-- 1. Filters -->
        <div class="config-section">
            <div class="config-title">🔍 فلتەرکردن (هەڵبژاردنی چەند دانەیەک)</div>

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label style="font-weight: bold;">بەپێی پلە:</label>
                        <div style="font-size: 0.75rem;">
                            <a href="javascript:void(0)" onclick="toggleGroup('rank_group', true); updateBtnText('rank')" style="color: #27ae60; text-decoration: none;">هەمووی</a> |
                            <a href="javascript:void(0)" onclick="toggleGroup('rank_group', false); updateBtnText('rank')" style="color: #e74c3c; text-decoration: none;">هیچ</a>
                        </div>
                    </div>
                    <div class="dropdown-filter" id="dropdown_rank">
                        <div class="dropdown-filter-btn" onclick="toggleDropdown('rank')">
                            <span id="btn_text_rank">هەڵبژاردنی پلەکان</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="dropdown-filter-content" id="content_rank">
                            <input type="text" class="dropdown-filter-search" placeholder="گەڕان بەدوای پلە..." onkeyup="filterDropdownList('rank_group', this.value)">
                            <div id="rank_group" class="dropdown-filter-list">
                                <?php foreach ($ranks as $r): ?>
                                    <label class="check-item">
                                        <input type="checkbox" name="filter_ranks[]" value="<?php echo htmlspecialchars($r); ?>"
                                            <?php echo in_array($r, $_GET['filter_ranks'] ?? []) ? 'checked' : ''; ?>
                                            onchange="updateBtnText('rank')">
                                        <span><?php echo htmlspecialchars($r); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label style="font-weight: bold;">بەپێی پایە:</label>
                        <div style="font-size: 0.75rem;">
                            <a href="javascript:void(0)" onclick="toggleGroup('post_group', true); updateBtnText('post')" style="color: #27ae60; text-decoration: none;">هەمووی</a> |
                            <a href="javascript:void(0)" onclick="toggleGroup('post_group', false); updateBtnText('post')" style="color: #e74c3c; text-decoration: none;">هیچ</a>
                        </div>
                    </div>
                    <div class="dropdown-filter" id="dropdown_post">
                        <div class="dropdown-filter-btn" onclick="toggleDropdown('post')">
                            <span id="btn_text_post">هەڵبژاردنی پایەکان</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="dropdown-filter-content" id="content_post">
                            <input type="text" class="dropdown-filter-search" placeholder="گەڕان بەدوای پایە..." onkeyup="filterDropdownList('post_group', this.value)">
                            <div id="post_group" class="dropdown-filter-list">
                                <?php foreach ($posts as $p): ?>
                                    <label class="check-item">
                                        <input type="checkbox" name="filter_posts[]" value="<?php echo htmlspecialchars($p); ?>"
                                            <?php echo in_array($p, $_GET['filter_posts'] ?? []) ? 'checked' : ''; ?>
                                            onchange="updateBtnText('post')">
                                        <span><?php echo htmlspecialchars($p); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label style="font-weight: bold;">بەپێی بنەماڵە:</label>
                        <div style="font-size: 0.75rem;">
                            <a href="javascript:void(0)" onclick="toggleGroup('fam_group', true); updateBtnText('fam')" style="color: #27ae60; text-decoration: none;">هەمووی</a> |
                            <a href="javascript:void(0)" onclick="toggleGroup('fam_group', false); updateBtnText('fam')" style="color: #e74c3c; text-decoration: none;">هیچ</a>
                        </div>
                    </div>
                    <div class="dropdown-filter" id="dropdown_fam">
                        <div class="dropdown-filter-btn" onclick="toggleDropdown('fam')">
                            <span id="btn_text_fam">هەڵبژاردنی بنەماڵەکان</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="dropdown-filter-content" id="content_fam">
                            <input type="text" class="dropdown-filter-search" placeholder="گەڕان بەدوای بنەماڵە..." onkeyup="filterDropdownList('fam_group', this.value)">
                            <div id="fam_group" class="dropdown-filter-list">
                                <?php foreach ($families as $f): ?>
                                    <label class="check-item">
                                        <input type="checkbox" name="filter_families[]" value="<?php echo $f['id']; ?>" 
                                            <?php echo in_array($f['id'], $_GET['filter_families'] ?? []) ? 'checked' : ''; ?>
                                            onchange="updateBtnText('fam')">
                                        <span><?php echo htmlspecialchars($f['family_name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label style="font-weight: bold;">بەپێی کۆنگرە:</label>
                        <div style="font-size: 0.75rem;">
                            <a href="javascript:void(0)" onclick="toggleGroup('elect_group', true); updateBtnText('elect')" style="color: #27ae60; text-decoration: none;">هەمووی</a> |
                            <a href="javascript:void(0)" onclick="toggleGroup('elect_group', false); updateBtnText('elect')" style="color: #e74c3c; text-decoration: none;">هیچ</a>
                        </div>
                    </div>
                    <div class="dropdown-filter" id="dropdown_elect">
                        <div class="dropdown-filter-btn" onclick="toggleDropdown('elect')">
                            <span id="btn_text_elect">هەڵبژاردنی کۆنگرەکان</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="dropdown-filter-content" id="content_elect">
                            <input type="text" class="dropdown-filter-search" placeholder="گەڕان بەدوای کۆنگرە..." onkeyup="filterDropdownList('elect_group', this.value)">
                            <div id="elect_group" class="dropdown-filter-list">
                                <?php foreach ($elections as $e): ?>
                                    <label class="check-item">
                                        <input type="checkbox" name="filter_elections[]" value="<?php echo $e['id']; ?>" 
                                            <?php echo in_array($e['id'], $_GET['filter_elections'] ?? []) ? 'checked' : ''; ?>
                                            onchange="updateBtnText('elect')">
                                        <span><?php echo htmlspecialchars($e['title']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Columns & Order -->
        <div class="config-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <div class="config-title" style="margin: 0;">📋 هەڵبژاردنی کۆڵۆمەکان و ڕیزبەندییان</div>
                <div
                    style="font-size: 0.85rem; background: #fff; padding: 5px 15px; border-radius: 20px; border: 1px solid #eee;">
                    <a href="javascript:void(0)" onclick="toggleGroup('fields_group', true)"
                        style="color: #27ae60; text-decoration: none; font-weight: bold;">هەڵبژاردنی هەمووی</a> |
                    <a href="javascript:void(0)" onclick="toggleGroup('fields_group', false)"
                        style="color: #e74c3c; text-decoration: none; font-weight: bold;">لادانی هەمووی</a>
                </div>
            </div>
            <div id="fields_group" class="field-order-list">
                <?php
                $i = 1;
                foreach ($all_fields as $key => $label):
                    $checked = isset($_GET['fields'][$key]) || !isset($_GET['generate']);
                    $order = $_GET['orders'][$key] ?? $i;
                    ?>
                    <div class="field-order-item"
                        style="display: flex; align-items: center; justify-content: space-between; background: #fdfdfd; padding: 12px; border: 1px solid #e0e0e0;">
                        <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                            <input type="checkbox" name="fields[<?php echo $key; ?>]" value="on" <?php echo $checked ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                            <span style="font-weight: 500; color: #34495e;"><?php echo $label; ?></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <span style="font-size: 0.7rem; color: #95a5a6;">ڕیزبەندی:</span>
                            <input type="number" name="orders[<?php echo $key; ?>]" value="<?php echo $order; ?>"
                                title="ڕیزبەندی"
                                style="width: 45px; height: 30px; border-radius: 4px; border: 1px solid #bdc3c7; text-align: center; font-size: 0.9rem;">
                        </div>
                    </div>
                    <?php
                    $i++;
                endforeach; ?>
            </div>
        </div>

        <!-- Print & Title Configuration at the bottom of the form -->
        <div class="config-section" style="background: #eef2f7; border: 1px solid #d1d9e6; border-radius: 12px; padding: 15px; margin-bottom: 20px;">
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 20px; justify-content: space-between;">
                <div style="display: flex; flex-direction: column; gap: 15px; flex: 1; min-width: 300px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="font-weight: bold; color: #2c3e50; white-space: nowrap; width: 140px;">ناوی بەش / نوسینگە:</label>
                        <input type="text" name="header_department" class="lib-input" style="flex: 1; padding: 8px;" 
                               value="<?php echo htmlspecialchars($_GET['header_department'] ?? 'بەشی ئیدارە و کارگێڕی / نوسینگە'); ?>">
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="font-weight: bold; color: #2c3e50; white-space: nowrap; width: 140px;">عنوان/تایتڵی ڕاپۆرت:</label>
                        <input type="text" name="report_title" class="lib-input" style="flex: 1; padding: 8px;" 
                               value="<?php echo htmlspecialchars($_GET['report_title'] ?? 'ڕاپۆرتی لیستی ئەندامان'); ?>">
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-weight: bold; color: #2c3e50;">📏 ئاڕاستەی لاپەڕە (بۆ پرینت):</div>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="orientation" value="portrait" <?php echo ($_GET['orientation'] ?? 'portrait') == 'portrait' ? 'checked' : ''; ?>> ستونی (Portrait)
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="radio" name="orientation" value="landscape" <?php echo ($_GET['orientation'] ?? '') == 'landscape' ? 'checked' : ''; ?>> ئاسۆیی (Landscape)
                    </label>
                </div>
            </div>
        </div>

        <div style="text-align: center;">
            <button type="submit" class="lib-btn lib-btn-primary" style="padding: 12px 60px; font-size: 1.1rem;">📊
                دروستکردنی ڕاپۆرت</button>
        </div>
    </form>
</div>

<?php if ($show_report): ?>
    <div class="report-preview">
        <!-- Branding Header (Table-based for reliability) -->
        <div class="print-header">
            <div class="header-cell" style="text-align: right; width: 40%;">
                <div style="display: inline-block; text-align: center;">
                    <h2 style="margin: 0; color: #2c3e50; font-size: 1.4rem; white-space: nowrap;">
                        <?php echo htmlspecialchars($settings['system_name']); ?>
                    </h2>
                    <h4 style="margin: 5px 0 0 0; color: #7f8c8d; white-space: nowrap;"><?php echo htmlspecialchars($settings['system_subtitle']); ?>
                    </h4>
                    <p style="margin: 5px 0 0 0; font-size: 0.85rem; white-space: nowrap;"><?php echo htmlspecialchars($_GET['header_department'] ?? 'بەشی ئیدارە و کارگێڕی / نوسینگە'); ?></p>
                </div>
            </div>
            <div class="header-cell" style="text-align: center; width: 20%;">
                <img src="<?php echo $settings['logo']; ?>" style="height: 75px; width: auto; object-fit: contain;">
            </div>
            <div class="header-cell" style="text-align: left; width: 40%; font-size: 0.85rem;">
                <p style="margin: 0;">بەرواری چاپ: <?php echo date('Y/m/d'); ?></p>
                <p style="margin: 5px 0 0 0;">کات: <?php echo date('H:i'); ?></p>
                <p style="margin: 5px 0 0 0;">لاپەڕە: <span class="page-number"></span></p>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;"
            class="no-print">
            <div style="text-align: center; flex: 1;">
                <h2 style="margin: 0; color: #2c3e50;">
                    <?php echo htmlspecialchars($_GET['report_title'] ?? 'ڕاپۆرتی لیستی ئەندامان'); ?>
                </h2>
                <p style="color: #7f8c8d; margin-top: 5px;">بەروار: <?php echo date('Y/m/d'); ?> | ژمارەی ئەندامان:
                    <?php echo count($members); ?>
                </p>
            </div>
            <?php
            $print_url = "members_reports.php?" . $_SERVER['QUERY_STRING'] . "&print_mode=1";
            ?>
            <a href="<?php echo htmlspecialchars($print_url); ?>" target="_blank" class="lib-btn lib-btn-success no-print"
                style="padding: 10px 30px; background: #27ae60; border-color: #27ae60; text-decoration: none; display: inline-block;">
                <i class="fas fa-print"></i> چاپکردن
            </a>
        </div>

        <div class="report-table-container">
            <table class="lib-table">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">#</th>
                        <?php foreach ($selected_fields as $key => $label): ?>
                            <th style="text-align: center;"><?php echo $label; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $index => $m): ?>
                        <tr>
                            <td style="text-align: center; font-weight: bold;"><?php echo $index + 1; ?></td>
                            <?php foreach ($selected_fields as $key => $label): ?>
                                <td style="text-align: center;">
                                    <?php
                                    $val = $m[$key] ?? '-';
                                    if ($key === 'full_name') {
                                        echo htmlspecialchars(buildFourPartName($m));
                                    } elseif ($key === 'birth_date' || $key === 'membership_date') {
                                        echo $val ?: '-';
                                    } else {
                                        echo htmlspecialchars($val ?: '-');
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="<?php echo count($selected_fields) + 1; ?>"
                                style="text-align: center; padding: 30px; color: #888;">هیچ ئەندامێک نەدۆزرایەوە بەپێی ئەم
                                فلتەرانە.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Signature Footer (Table-based for reliability) -->
        <div class="report-footer">
            <div class="footer-cell">
                <p
                    style="margin: 0; font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding-bottom: 5px; width: 180px;">
                    واژۆی بەرپرسی بەش</p>
                <br><br><br><br>
            </div>
            <div class="footer-cell">
                <p
                    style="margin: 0; font-weight: bold; border-bottom: 1px solid #000; display: inline-block; padding-bottom: 5px; width: 180px;">
                    مۆری فەرمی</p>
                <br><br><br><br>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/layout_end.php'; ?>