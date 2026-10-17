<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

require_once 'config.php';
$conn = getDB();

$shelves = [];
$shelf_categories = [];
$system_name = "کتێبخانەی ساداتی پیرخدری شاهۆ";
$logo_path = "assets/logo.png";

if ($conn) {
    // 1. Fetch all distinct shelf codes from books
    $res = $conn->query("SELECT DISTINCT shelf_code FROM books WHERE shelf_code IS NOT NULL AND shelf_code != '' ORDER BY shelf_code ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $code = trim($row['shelf_code']);
            if (!empty($code)) $shelves[] = $code;
        }
    }

    // 2. Fetch category names mapped to shelves
    $cat_res = $conn->query("SELECT c.name as cat_name, 
        (SELECT shelf_code FROM books b WHERE b.category_id = c.id AND shelf_code IS NOT NULL AND shelf_code != '' GROUP BY shelf_code ORDER BY COUNT(*) DESC LIMIT 1) as shelf_code
        FROM categories c ORDER BY c.name ASC");
    if ($cat_res) {
        while ($r = $cat_res->fetch_assoc()) {
            if (!empty($r['shelf_code'])) {
                $sc = trim($r['shelf_code']);
                if (!isset($shelf_categories[$sc])) {
                    $shelf_categories[$sc] = [];
                }
                $shelf_categories[$sc][] = $r['cat_name'];
            }
        }
    }
}

// Fallback list of shelves if none in database
if (empty($shelves)) {
    $shelves = ['A', 'D', 'F', 'FK', 'K', 'KO', 'L', 'M', 'P', 'S', 'T', 'Y', 'Z'];
}

$selected_shelf = isset($_GET['shelf']) ? trim($_GET['shelf']) : ($shelves[0] ?? 'A');
$initial_cat = !empty($shelf_categories[$selected_shelf]) ? $shelf_categories[$selected_shelf][0] : 'ئاینی';

// Helper function to add tatweel (kashida) on server-side initial load
function phpAddTatweel($text, $count = 4) {
    $clean = str_replace('ـ', '', $text);
    if ($count <= 0) return $clean;
    $tat = str_repeat('ـ', $count);
    return preg_replace('/([بتپتثجچحخسشصضطظعغفقکگلمنهیهئێی])(?=[\x{0600}-\x{06FF}])/u', '$1' . $tat, $clean);
}
$initial_cat_stretched = phpAddTatweel($initial_cat, 4);
?>
<!DOCTYPE html>
<html lang="ckb" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چاپکردنی لەزگەی ناوی ڕەفەکان (6cm x 4cm)</title>
    <link rel="icon" href="assets/logo.ico" type="image/x-icon">
    
    <style id="dynamicPrintStyle">
        @page {
            size: 60mm 40mm;
            margin: 0;
        }
    </style>

    <style>
        /* Kurdish Fonts */
        @font-face {
            font-family: 'Rabar013';
            src: url('assets/font/Rabar_013.ttf') format('truetype');
            font-weight: normal;
        }
        @font-face {
            font-family: 'Rabar022';
            src: url('assets/font/Rabar_022.ttf') format('truetype');
            font-weight: normal;
        }
        @font-face {
            font-family: 'Rabar005';
            src: url('assets/font/Rabar_005.ttf') format('truetype');
            font-weight: bold;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Rabar022', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            padding: 25px 15px;
            min-height: 100vh;
        }

        .container {
            max-width: 1140px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 440px 1fr;
            gap: 25px;
            background: #ffffff;
            padding: 28px;
            border-radius: 20px;
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        /* Sidebar Controls */
        .controls {
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding-left: 20px;
            border-left: 1px solid #e2e8f0;
        }

        .controls-header {
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 12px;
        }

        .controls h2 {
            color: #5a0202;
            font-size: 1.25rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Mode Switcher Tabs */
        .mode-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 10px;
        }

        .mode-tab {
            padding: 8px 12px;
            text-align: center;
            font-weight: 700;
            font-size: 0.85rem;
            border-radius: 8px;
            cursor: pointer;
            border: none;
            background: transparent;
            color: #64748b;
            transition: .2s;
            font-family: inherit;
        }

        .mode-tab.active {
            background: #5a0202;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(90, 2, 2, 0.2);
        }

        /* Quick Shelf Badges */
        .shelves-pills-area {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .shelves-pills-area label {
            font-weight: 700;
            font-size: 0.84rem;
            color: #475569;
        }

        .shelves-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            max-height: 110px;
            overflow-y: auto;
            padding: 4px 2px;
        }

        .shelf-pill {
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 800;
            font-size: 0.95rem;
            font-family: monospace;
            cursor: pointer;
            transition: .15s;
            color: #1e293b;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .shelf-pill:hover {
            background: #fdf2f2;
            border-color: #5a0202;
            color: #5a0202;
            transform: translateY(-1px);
        }

        .shelf-pill.active {
            background: #5a0202;
            border-color: #5a0202;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(90, 2, 2, 0.2);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-weight: 700;
            font-size: 0.86rem;
            color: #475569;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-group input, .form-group select {
            width: 100%;
            height: 42px;
            padding: 0 12px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.95rem;
            outline: none;
            transition: .2s;
            font-family: inherit;
            background: #fff;
        }

        .form-group input:focus, .form-group select:focus {
            border-color: #5a0202;
            box-shadow: 0 0 0 3px rgba(90, 2, 2, 0.1);
        }

        /* Tatweel / Stretch Buttons */
        .btn-tatweel {
            flex: 1;
            height: 32px;
            background: #f1f5f9;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
            transition: .15s;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            user-select: none;
        }

        .btn-tatweel:hover {
            background: #e2e8f0;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .btn-tatweel.active {
            background: #5a0202;
            color: #ffffff;
            border-color: #5a0202;
        }

        /* Font Size Step & Preset Buttons */
        .btn-size-step {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 4px 9px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #334155;
            cursor: pointer;
            transition: .15s;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            user-select: none;
        }

        .btn-size-step:hover {
            background: #5a0202;
            color: #ffffff;
            border-color: #5a0202;
        }

        .btn-preset-size {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 5px 2px;
            font-size: 0.72rem;
            font-weight: 700;
            color: #475569;
            cursor: pointer;
            transition: .15s;
            font-family: inherit;
            text-align: center;
            user-select: none;
        }

        .btn-preset-size:hover {
            background: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .btn-preset-size.active {
            background: #5a0202;
            color: #ffffff;
            border-color: #5a0202;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 0.82rem;
            color: #334155;
            cursor: pointer;
            padding: 8px 10px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            transition: .2s;
            user-select: none;
        }

        .checkbox-label:hover {
            background: #f1f5f9;
        }

        .checkbox-label input[type="checkbox"] {
            width: 17px;
            height: 17px;
            cursor: pointer;
            accent-color: #5a0202;
        }

        .btn-print {
            height: 48px;
            background: linear-gradient(135deg, #5a0202, #8b1a1a);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-weight: 800;
            font-size: 1.05rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: .2s;
            box-shadow: 0 4px 15px rgba(90, 2, 2, 0.25);
            font-family: inherit;
            margin-top: 4px;
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(90, 2, 2, 0.35);
        }

        .btn-secondary {
            height: 40px;
            background: #fff;
            color: #475569;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: .2s;
            font-family: inherit;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }

        /* Batch Checkbox List Area */
        .batch-area {
            display: none;
            flex-direction: column;
            gap: 10px;
        }

        .batch-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            max-height: 240px;
            overflow-y: auto;
            padding: 4px;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
        }

        .batch-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            transition: .15s;
        }

        .batch-item:hover {
            background: #fdf2f2;
            border-color: #5a0202;
        }

        .batch-item input[type="checkbox"] {
            width: 17px;
            height: 17px;
            cursor: pointer;
            accent-color: #5a0202;
        }

        .batch-code {
            font-family: monospace;
            font-weight: 900;
            font-size: 1rem;
            color: #5a0202;
            min-width: 28px;
        }

        .batch-desc {
            font-size: 0.76rem;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* STICKER PREVIEW AREA */
        .preview-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            border-radius: 16px;
            padding: 24px 20px;
            border: 2px dashed #cbd5e1;
        }

        .preview-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            max-width: 540px;
            margin-bottom: 14px;
        }

        .preview-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .preview-badge {
            background: #e0f2fe;
            color: #0284c7;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 20px;
        }

        /* 1.5 Ratio for 60mm x 40mm (540px x 360px) */
        .sticker-wrapper {
            width: 540px;
            height: 360px;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.08), 0 0 0 1px #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
            user-select: none;
            padding: 14px; /* Safe inset simulating physical thermal sticker margin */
            transition: all 0.2s;
        }

        .sticker-inner-frame {
            width: 100%;
            height: 100%;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: 12px 18px 10px 18px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        /* Outer Frame options (Applied safely inside the printable area) */
        .sticker-inner-frame.bordered-solid {
            border: 4px solid #000000;
        }

        .sticker-inner-frame.bordered-double {
            border: 6px double #000000;
        }

        .sticker-inner-frame.bordered-rounded {
            border: 4px solid #000000;
            border-radius: 18px;
        }

        /* Category Name On Top (لەسەر پیتەکە) */
        .sticker-shelf-category {
            font-family: 'Rabar013', 'Rabar022', Tahoma, sans-serif;
            font-size: 30px;
            font-weight: bold;
            color: #000000;
            text-align: center;
            line-height: 1.2;
            width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 6px 14px;
            border-radius: 8px;
            direction: rtl;
            transition: all 0.2s;
            margin-top: 4px;
        }

        .sticker-shelf-category.banner-black {
            background: #000000;
            color: #ffffff;
        }

        .sticker-shelf-category.banner-bordered {
            border: 2.5px solid #000000;
            background: transparent;
            color: #000000;
        }

        /* Giant Shelf Letter Below Category Name */
        .sticker-giant-letter {
            font-family: 'Segoe UI', 'Arial Black', Tahoma, sans-serif;
            font-weight: 900;
            font-size: 120px;
            color: #000000;
            line-height: 0.95;
            text-align: center;
            letter-spacing: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-grow: 1;
            width: 100%;
            direction: ltr !important;
        }

        /* Help Box */
        .guide-box {
            margin-top: 18px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 18px;
            width: 100%;
            max-width: 540px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .guide-title {
            font-size: 0.88rem;
            font-weight: 800;
            color: #5a0202;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
        }

        .guide-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
            font-size: 0.8rem;
            color: #475569;
            line-height: 1.4;
        }

        .guide-list li {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .badge-setting {
            background: #f1f5f9;
            color: #0f172a;
            padding: 2px 7px;
            border-radius: 6px;
            font-weight: 800;
            font-family: monospace;
            font-size: 0.82rem;
            border: 1px solid #cbd5e1;
        }

        /* Print Container (Hidden on Screen) */
        #printArea {
            display: none;
        }

        /* ── PRINT MEDIA RULES FOR ZEBRA THERMAL PRINTER (60mm x 40mm) ── */
        @media print {
            html, body {
                width: 60mm;
                height: 40mm;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .container {
                display: none !important;
            }

            #printArea {
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .print-sticker {
                width: 60mm;
                height: 40mm;
                box-sizing: border-box;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #ffffff !important;
                color: #000000 !important;
                overflow: hidden;
                position: relative;
                page-break-inside: avoid;
                break-inside: avoid;
                direction: rtl !important;
                margin: 0 !important;
                padding: 1.6mm 2mm !important; /* Safe inset from physical edge: solves cutoff on Zebra printers */
            }

            .print-sticker:not(:last-child) {
                page-break-after: always;
                break-after: page;
            }

            .print-inner-frame {
                width: 100%;
                height: 100%;
                box-sizing: border-box;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: space-between;
                padding: 1.2mm 2mm 1mm 2mm !important;
                background: transparent !important;
            }

            .print-inner-frame.bordered-solid {
                border: 0.6mm solid #000000 !important;
            }

            .print-inner-frame.bordered-double {
                border: 0.9mm double #000000 !important;
            }

            .print-inner-frame.bordered-rounded {
                border: 0.6mm solid #000000 !important;
                border-radius: 2.2mm !important;
            }

            /* Category on top */
            .print-shelf-category {
                font-family: 'Rabar013', 'Rabar022', Tahoma, sans-serif;
                font-weight: bold;
                color: #000000 !important;
                text-align: center;
                line-height: 1.1;
                width: 100%;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                direction: rtl;
                padding: 0.5mm 1mm;
                box-sizing: border-box;
            }

            .print-shelf-category.banner-black {
                background: #000000 !important;
                color: #ffffff !important;
                border-radius: 1.5mm;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .print-shelf-category.banner-bordered {
                border: 0.4mm solid #000000 !important;
                border-radius: 1.5mm;
            }

            /* Giant Letter */
            .print-shelf-letter {
                font-family: 'Segoe UI', 'Arial Black', Tahoma, sans-serif;
                font-weight: 900;
                color: #000000 !important;
                line-height: 0.95;
                text-align: center;
                letter-spacing: 1px;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-grow: 1;
                width: 100%;
                direction: ltr !important;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        
        <!-- SIDEBAR CONTROLS -->
        <div class="controls">
            <div class="controls-header">
                <h2>🏷️ لەزگەی ناوی ڕەفەکان</h2>
                <p style="color: #64748b; font-size: 0.8rem; margin-top: 4px;">تایبەت بە لەزگەی <strong>6cm × 4cm</strong> و لێواری ڕەفەی کتێبخانە</p>
            </div>

            <!-- Mode Selector Tabs -->
            <div class="mode-tabs">
                <button type="button" class="mode-tab active" id="tabSingle" onclick="switchMode('single')">تاک (Single Shelf)</button>
                <button type="button" class="mode-tab" id="tabBatch" onclick="switchMode('batch')">بەکۆمەڵ (Batch Print)</button>
            </div>

            <!-- Quick Shelf Pills -->
            <div class="shelves-pills-area" id="pillsSection">
                <label>هەڵبژاردنی خێرای ڕەفەکان لە سیستەمەوە:</label>
                <div class="shelves-pills">
                    <?php foreach ($shelves as $sh): ?>
                        <div class="shelf-pill <?php echo $sh === $selected_shelf ? 'active' : ''; ?>" onclick="selectPill('<?php echo htmlspecialchars($sh); ?>')">
                            <span><?php echo htmlspecialchars($sh); ?></span>
                            <?php if (!empty($shelf_categories[$sh])): ?>
                                <small style="font-size:0.7rem; opacity:0.75; font-family:sans-serif;">(<?php echo htmlspecialchars($shelf_categories[$sh][0]); ?>)</small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- SINGLE MODE FORM -->
            <div id="singleControls" style="display:flex; flex-direction:column; gap:12px;">
                <!-- DEDICATED CATEGORY BOX (ناوی بەش و قەبارە و کشانی پیتەکان) -->
                <div style="background:#ffffff; border:2px solid #5a0202; border-radius:12px; padding:12px 14px; box-shadow:0 3px 10px rgba(90, 2, 2, 0.08); display:flex; flex-direction:column; gap:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <label style="font-size:0.92rem; font-weight:800; color:#5a0202; margin:0; display:flex; align-items:center; gap:6px;">
                            <span>✍️ ناوی بەش (دەستکاری نوسینەکە بکە):</span>
                        </label>
                        <span style="font-size:0.75rem; background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:6px; font-weight:700;">سەرەکی</span>
                    </div>

                    <!-- Direct Text Input (Can type or edit freely) -->
                    <input type="text" id="inputCatName" value="<?php echo htmlspecialchars($initial_cat_stretched); ?>" 
                           placeholder="ناوی بەش بۆ نموونە: ئــــایـــنــــی"
                           style="font-family:'Rabar013', 'Rabar022', Tahoma, sans-serif; font-size:1.25rem; font-weight:bold; text-align:center; color:#5a0202; height:46px; border:2px solid #cbd5e1; border-radius:10px; background:#fffdfd;">

                    <!-- Quick Stretch / Kashida Buttons (ئاسایی، کەمێک، مامناوەند، زۆر، زیاتر) -->
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                            <span style="font-size:0.8rem; font-weight:700; color:#475569;">✨ کێشانی پیتەکان (کشیدە / کشاندن):</span>
                            <span style="font-size:0.72rem; color:#64748b;">(بۆ پڕکردنەوەی چوارچێوە)</span>
                        </div>
                        <div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:5px;">
                            <button type="button" class="btn-tatweel" onclick="setTatweelMode(0)" title="ئاسایی">ئاینی</button>
                            <button type="button" class="btn-tatweel" onclick="setTatweelMode(1)" title="کەمێک کشان">ئـایـنـی</button>
                            <button type="button" class="btn-tatweel" onclick="setTatweelMode(2)" title="مامناوەند">ئــایــنــی</button>
                            <button type="button" class="btn-tatweel active" onclick="setTatweelMode(4)" title="زۆر کشان (پێشنیارکراو)">ئــــایــــنــــی</button>
                            <button type="button" class="btn-tatweel" onclick="setTatweelMode(6)" title="زۆر درێژ">ئــــــایــــــنــــــی</button>
                        </div>
                    </div>

                    <!-- Font Size Controls (گەورەکردن و بچوککردنی سایز) -->
                    <div style="padding-top:10px; border-top:1px dashed #e2e8f0; display:flex; flex-direction:column; gap:6px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:0.82rem; font-weight:700; color:#334155;">
                                🔍 قەبارەی فۆنتی ناوی بەش (ئاینی):
                            </span>
                            <div style="display:flex; align-items:center; gap:5px;">
                                <button type="button" onclick="stepCatFontSize(-1)" class="btn-size-step" title="بچوککردنەوە">➖ بچوککردن</button>
                                <span id="catFontSizeBadge" style="min-width:54px; text-align:center; font-weight:800; font-family:monospace; font-size:0.92rem; color:#5a0202; background:#fdf2f2; padding:3px 8px; border-radius:6px; border:1px solid #fecaca;">16pt</span>
                                <button type="button" onclick="stepCatFontSize(1)" class="btn-size-step" title="گەورەکردن">➕ گەورەکردن</button>
                            </div>
                        </div>

                        <!-- Quick Presets -->
                        <div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:4px;">
                            <button type="button" class="btn-preset-size" onclick="selectCatPreset(0)" data-idx="0">بچووک (12)</button>
                            <button type="button" class="btn-preset-size" onclick="selectCatPreset(1)" data-idx="1">مامناوەند (14)</button>
                            <button type="button" class="btn-preset-size active" onclick="selectCatPreset(2)" data-idx="2">گەورە (16)</button>
                            <button type="button" class="btn-preset-size" onclick="selectCatPreset(3)" data-idx="3">زۆر گەورە (19)</button>
                            <button type="button" class="btn-preset-size" onclick="selectCatPreset(4)" data-idx="4">سەرەکردە (24)</button>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>پیتی ڕەفە (کۆدەکە)</label>
                    <input type="text" id="inputShelfCode" value="<?php echo htmlspecialchars($selected_shelf); ?>" style="text-transform: uppercase; direction: ltr; text-align: center; font-family: monospace; font-weight: 900; font-size: 1.5rem; letter-spacing: 2px;">
                </div>

                <div class="form-group">
                    <label>ژمارەی کۆپی (Copies)</label>
                    <input type="number" id="inputCopies" value="1" min="1" max="100">
                </div>
            </div>

            <!-- BATCH MODE LIST -->
            <div id="batchControls" class="batch-area">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.84rem; font-weight:700; color:#475569;">دەستنیشانکردنی ڕەفەکان:</span>
                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="toggleAllBatch(true)" style="background:none; border:none; color:#5a0202; font-weight:700; font-size:0.78rem; cursor:pointer;">هەمووی</button>
                        <span style="color:#cbd5e1;">|</span>
                        <button type="button" onclick="toggleAllBatch(false)" style="background:none; border:none; color:#64748b; font-weight:700; font-size:0.78rem; cursor:pointer;">لابردن</button>
                    </div>
                </div>

                <div class="batch-grid">
                    <?php foreach ($shelves as $sh): ?>
                        <?php $cName = !empty($shelf_categories[$sh]) ? $shelf_categories[$sh][0] : 'ڕەفەی کتێب'; ?>
                        <label class="batch-item">
                            <input type="checkbox" class="batch-checkbox" value="<?php echo htmlspecialchars($sh); ?>" data-cat="<?php echo htmlspecialchars($cName); ?>" checked onchange="updateBatchCount()">
                            <span class="batch-code"><?php echo htmlspecialchars($sh); ?></span>
                            <span class="batch-desc"><?php echo htmlspecialchars($cName); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="form-group" style="margin-top:4px;">
                    <label>کۆپی بۆ هەر ڕەفەیەک</label>
                    <input type="number" id="inputBatchCopies" value="1" min="1" max="50">
                </div>
            </div>

            <!-- DESIGN OPTIONS -->
            <div style="background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:12px; padding:12px; display:flex; flex-direction:column; gap:10px;">
                <div style="font-weight:800; font-size:0.86rem; color:#5a0202;">
                    🎨 شێواز و قەبارەی فۆنتەکان
                </div>

                <label class="checkbox-label">
                    <input type="checkbox" id="toggleShowCat" checked>
                    <span>پیشاندانی ناوی بەش لەسەر پیتەکە</span>
                </label>

                <!-- Font size for Category Name (ئاینی) -->
                <div class="form-group">
                    <label>قەبارەی فۆنتی ناوی بەش (ئاینی)</label>
                    <select id="catFontSizeSelect">
                        <option value="small">بچووک (Small - 12pt)</option>
                        <option value="medium">مامناوەند (Medium - 14pt)</option>
                        <option value="large" selected>گەورە (Large - 16pt) - پێشنیارکراو</option>
                        <option value="xlarge">زۆر گەورە (Extra Large - 19pt)</option>
                        <option value="huge">سەرەکردە (Huge - 23pt)</option>
                    </select>
                </div>

                <!-- Font size for Shelf Letter (A) -->
                <div class="form-group">
                    <label>قەبارەی پیتی ڕەفەکە (A)</label>
                    <select id="letterSizeSelect">
                        <option value="large">گەورە (Large - 50pt)</option>
                        <option value="xlarge" selected>زۆر گەورە (Extra Large - 58pt) - پێشنیارکراو</option>
                        <option value="giant">سەرەکردە (Giant - 68pt)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>شێوازی چوارچێوەی ناوی بەشەکە</label>
                    <select id="catStyleSelect">
                        <option value="plain">نوسینی ئاسایی (تۆخ و ڕوون)</option>
                        <option value="banner-black">باکراوندی ڕەش (نوسینی سپی)</option>
                        <option value="banner-bordered" selected>چوارچێوەدار (Bordered)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>چوارچێوەی دەوری لەزگە (Border)</label>
                    <select id="borderStyleSelect">
                        <option value="none">بێ چوارچێوە</option>
                        <option value="bordered-solid">چوارچێوەی ئەستوور (Solid Frame)</option>
                        <option value="bordered-double" selected>چوارچێوەی جووت هێڵ (Double Frame)</option>
                        <option value="bordered-rounded">چوارچێوەی گۆشە خڕ (Rounded Frame)</option>
                    </select>
                </div>
            </div>

            <!-- Print Trigger Button -->
            <button onclick="triggerPrint()" class="btn-print" id="btnPrint">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;height:20px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span id="btnPrintText">دەستپێکردنی چاپکردن</span>
            </button>

            <!-- Navigation Links -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                <a href="print_sticker.php" class="btn-secondary" title="چاپکردنی لەزگەی کتێب">
                    📖 چاپی لەزگەی کتێب
                </a>
                <a href="library_settings.php" class="btn-secondary">
                    ⚙️ ڕێکخستنی ڕەفەکان
                </a>
            </div>
        </div>

        <!-- STICKER PREVIEW AREA -->
        <div class="preview-area">
            <div class="preview-header">
                <div class="preview-title">
                    <span>👁️ پێشبینینی لەزگەی ڕەفە</span>
                </div>
                <div class="preview-badge">60mm × 40mm (6cm × 4cm)</div>
            </div>
            
            <div class="sticker-wrapper" id="stickerWrapper">
                <div class="sticker-inner-frame bordered-double" id="previewInnerFrame">
                    <!-- Category Name on Top (لەسەر پیتەکە) -->
                    <div class="sticker-shelf-category banner-bordered" id="previewCatName"><?php echo htmlspecialchars($initial_cat_stretched); ?></div>

                    <!-- Giant Shelf Letter Below -->
                    <div class="sticker-giant-letter" id="previewGiantLetter"><?php echo htmlspecialchars($selected_shelf); ?></div>
                </div>
            </div>
            
            <!-- Exact Zebra Printer Setup Guide -->
            <div class="guide-box">
                <div class="guide-title">
                    <span>⚙️ ڕێکخستنی زۆر گرنگ بۆ پەنجەرەی پرینت (Chrome / Edge)</span>
                </div>
                <ul class="guide-list">
                    <li>
                        <span>1. <strong>Destination (پرینتەر):</strong> پرینتەری</span>
                        <span class="badge-setting">ZDesigner ZD888-203dpi ZPL</span>
                    </li>
                    <li>
                        <span>2. <strong>Paper size:</strong> دڵنیابە لەسەر</span>
                        <span class="badge-setting">6cm × 4cm</span>
                        <span>دانراوە</span>
                    </li>
                    <li>
                        <span>3. <strong>Margins (پەراوێز):</strong> پێویستە بکرێتە</span>
                        <span class="badge-setting" style="background:#fee2e2; color:#b91c1c;">None (هیچ)</span>
                    </li>
                    <li>
                        <span>4. <strong>Headers & Footers:</strong> ناچالاک بێت (بێ تیک)</span>
                    </li>
                </ul>
            </div>
        </div>

    </div>

    <!-- PRINT CONTAINER (DYNAMICALLY POPULATED) -->
    <div id="printArea"></div>

    <script>
        // Shelf to Category mapping from PHP
        const SHELF_CATEGORIES = <?php echo json_encode($shelf_categories, JSON_UNESCAPED_UNICODE); ?>;

        let currentMode = 'single'; // 'single' or 'batch'
        let currentTatweelLevel = 4; // Default to stretched (ئــــایــــنــــی)

        // Font scale list for Category Name (ئاینی)
        const CAT_FONT_SIZES = [
            { label: 'بچووک', pt: '12pt', px: '22px' },
            { label: 'مامناوەند', pt: '14pt', px: '26px' },
            { label: 'گەورە', pt: '16pt', px: '30px' },
            { label: 'زۆر گەورە', pt: '19pt', px: '36px' },
            { label: 'سەرەکردە', pt: '24pt', px: '44px' }
        ];
        let currentCatFontIdx = 2; // Default 16pt (گەورە)

        const inputShelfCode = document.getElementById('inputShelfCode');
        const inputCatName = document.getElementById('inputCatName');
        const toggleShowCat = document.getElementById('toggleShowCat');
        const catFontSizeSelect = document.getElementById('catFontSizeSelect');
        const letterSizeSelect = document.getElementById('letterSizeSelect');
        const catStyleSelect = document.getElementById('catStyleSelect');
        const borderStyleSelect = document.getElementById('borderStyleSelect');

        const stickerWrapper = document.getElementById('stickerWrapper');
        const previewCatName = document.getElementById('previewCatName');
        const previewGiantLetter = document.getElementById('previewGiantLetter');
        const btnPrintText = document.getElementById('btnPrintText');

        // Font scale map for Shelf Letter (A)
        const FONT_MAP = {
            'large': { preview: '100px', print: '50pt' },
            'xlarge': { preview: '120px', print: '58pt' },
            'giant': { preview: '140px', print: '68pt' }
        };

        // Kashida / Tatweel function
        function applyTatweel(text, count) {
            if (!text) return '';
            const clean = text.replace(/ـ+/g, '');
            if (count <= 0) return clean;
            const tat = 'ـ'.repeat(count);
            return clean.replace(/([بتپتثجچحخسشصضطظعغفقکگلمنهیهئێی])(?=[\u0600-\u06FF])/g, '$1' + tat);
        }

        function setTatweelMode(count) {
            currentTatweelLevel = count;
            inputCatName.value = applyTatweel(inputCatName.value, count);

            // Update button UI active classes
            const btns = document.querySelectorAll('.btn-tatweel');
            btns.forEach((btn, idx) => {
                btn.classList.toggle('active', 
                    (idx === 0 && count === 0) || 
                    (idx === 1 && count === 1) || 
                    (idx === 2 && count === 2) || 
                    (idx === 3 && count === 4) || 
                    (idx === 4 && count >= 5)
                );
            });

            updatePreview();
        }

        // Stepper for Font Size (+ and -)
        function stepCatFontSize(delta) {
            currentCatFontIdx = Math.max(0, Math.min(CAT_FONT_SIZES.length - 1, currentCatFontIdx + delta));
            updateCatFontSizeUI();
            updatePreview();
        }

        // Direct Preset Click
        function selectCatPreset(idx) {
            currentCatFontIdx = idx;
            updateCatFontSizeUI();
            updatePreview();
        }

        function updateCatFontSizeUI() {
            const item = CAT_FONT_SIZES[currentCatFontIdx];
            const badge = document.getElementById('catFontSizeBadge');
            if (badge) badge.textContent = item.pt;

            document.querySelectorAll('.btn-preset-size').forEach((btn, idx) => {
                btn.classList.toggle('active', idx === currentCatFontIdx);
            });

            if (catFontSizeSelect) {
                const mapKeys = ['small', 'medium', 'large', 'xlarge', 'huge'];
                catFontSizeSelect.value = mapKeys[currentCatFontIdx] || 'large';
            }
        }

        function switchMode(mode) {
            currentMode = mode;
            document.getElementById('tabSingle').classList.toggle('active', mode === 'single');
            document.getElementById('tabBatch').classList.toggle('active', mode === 'batch');
            
            document.getElementById('singleControls').style.display = mode === 'single' ? 'flex' : 'none';
            document.getElementById('pillsSection').style.display = mode === 'single' ? 'flex' : 'none';
            document.getElementById('batchControls').style.display = mode === 'batch' ? 'flex' : 'none';

            updateBatchCount();
        }

        function selectPill(shelf) {
            inputShelfCode.value = shelf;
            document.querySelectorAll('.shelf-pill').forEach(el => {
                el.classList.toggle('active', el.textContent.trim().startsWith(shelf));
            });
            if (SHELF_CATEGORIES[shelf] && SHELF_CATEGORIES[shelf].length > 0) {
                const baseCat = SHELF_CATEGORIES[shelf][0];
                inputCatName.value = applyTatweel(baseCat, currentTatweelLevel);
            }
            updatePreview();
        }

        function toggleAllBatch(check) {
            document.querySelectorAll('.batch-checkbox').forEach(cb => cb.checked = check);
            updateBatchCount();
        }

        function updateBatchCount() {
            if (currentMode === 'batch') {
                const count = document.querySelectorAll('.batch-checkbox:checked').length;
                btnPrintText.textContent = `چاپکردنی ${count} لەزگەی ڕەفە`;
            } else {
                btnPrintText.textContent = 'دەستپێکردنی چاپکردن';
            }
        }

        function updatePreview() {
            const shelfCode = inputShelfCode.value.trim().toUpperCase() || 'A';
            const catName = inputCatName.value.trim();
            const showCat = toggleShowCat.checked;
            const catStyle = catStyleSelect.value;
            const chosenCatFont = CAT_FONT_SIZES[currentCatFontIdx] || CAT_FONT_SIZES[2];

            const fSizeKey = letterSizeSelect.value;
            const borderStyle = borderStyleSelect.value;

            // Giant Letter
            previewGiantLetter.textContent = shelfCode;
            previewGiantLetter.style.fontSize = FONT_MAP[fSizeKey].preview;

            // Category Name on Top
            if (showCat && catName) {
                previewCatName.textContent = catName;
                previewCatName.style.display = 'block';
                previewCatName.style.fontSize = chosenCatFont.px;
                previewCatName.className = 'sticker-shelf-category';
                if (catStyle !== 'plain') {
                    previewCatName.classList.add(catStyle);
                }
            } else {
                previewCatName.style.display = 'none';
            }

            // Outer Border
            const previewInnerFrame = document.getElementById('previewInnerFrame');
            if (previewInnerFrame) {
                previewInnerFrame.className = 'sticker-inner-frame';
                if (borderStyle !== 'none') {
                    previewInnerFrame.classList.add(borderStyle);
                }
            }
        }

        inputShelfCode.addEventListener('input', updatePreview);
        inputCatName.addEventListener('input', updatePreview);
        inputCatName.addEventListener('keyup', updatePreview);
        toggleShowCat.addEventListener('change', updatePreview);
        
        catFontSizeSelect.addEventListener('change', () => {
            const mapKeys = ['small', 'medium', 'large', 'xlarge', 'huge'];
            const idx = mapKeys.indexOf(catFontSizeSelect.value);
            if (idx !== -1) {
                currentCatFontIdx = idx;
                updateCatFontSizeUI();
            }
            updatePreview();
        });

        catStyleSelect.addEventListener('change', updatePreview);
        letterSizeSelect.addEventListener('change', updatePreview);
        borderStyleSelect.addEventListener('change', updatePreview);

        // Printing Execution
        function triggerPrint() {
            const printArea = document.getElementById('printArea');
            printArea.innerHTML = '';

            const chosenCatFont = CAT_FONT_SIZES[currentCatFontIdx] || CAT_FONT_SIZES[2];

            const fSizeKey = letterSizeSelect.value;
            const chosenFont = FONT_MAP[fSizeKey];
            const borderStyle = borderStyleSelect.value;
            const showCat = toggleShowCat.checked;
            const catStyle = catStyleSelect.value;

            let itemsToPrint = [];

            if (currentMode === 'single') {
                const shelf = inputShelfCode.value.trim().toUpperCase() || 'A';
                const cat = showCat ? inputCatName.value.trim() : '';
                const copies = parseInt(document.getElementById('inputCopies').value) || 1;
                for (let i = 0; i < copies; i++) {
                    itemsToPrint.push({ shelf: shelf, cat: cat });
                }
            } else {
                // Batch Mode
                const batchCopies = parseInt(document.getElementById('inputBatchCopies').value) || 1;
                const checkedBoxes = document.querySelectorAll('.batch-checkbox:checked');
                checkedBoxes.forEach(cb => {
                    const sh = cb.value.trim().toUpperCase();
                    let rawCat = cb.getAttribute('data-cat') || '';
                    let cat = showCat ? applyTatweel(rawCat, currentTatweelLevel) : '';
                    for (let i = 0; i < batchCopies; i++) {
                        itemsToPrint.push({ shelf: sh, cat: cat });
                    }
                });
            }

            if (itemsToPrint.length === 0) {
                alert('تکایە لانیکەم یەک ڕەفە هەڵبژێرە بۆ چاپکردن!');
                return;
            }

            // Generate HTML for each sticker with safe inner-frame
            itemsToPrint.forEach(item => {
                const sticker = document.createElement('div');
                sticker.className = 'print-sticker';

                const innerFrame = document.createElement('div');
                innerFrame.className = 'print-inner-frame';
                if (borderStyle !== 'none') {
                    innerFrame.classList.add(borderStyle);
                }

                let catHtml = '';
                if (item.cat) {
                    let extraClass = (catStyle !== 'plain') ? ' ' + catStyle : '';
                    catHtml = `<div class="print-shelf-category${extraClass}" style="font-size: ${chosenCatFont.pt};">${item.cat}</div>`;
                }

                innerFrame.innerHTML = `
                    ${catHtml}
                    <div class="print-shelf-letter" style="font-size: ${chosenFont.print};">${item.shelf}</div>
                `;
                sticker.appendChild(innerFrame);
                printArea.appendChild(sticker);
            });

            // Trigger native print dialog
            setTimeout(() => {
                window.print();
            }, 50);
        }

        // Initialize preview
        updatePreview();
    </script>
</body>
</html>
