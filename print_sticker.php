<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit; }

require_once 'config.php';
$conn = getDB();

$book_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$book = null;
$system_name = "کتێبخانەی ساداتی پیرخدری شاهۆ";
$logo_path = "assets/logo.png";

if ($conn) {
    // Get Book Details
    if ($book_id > 0) {
        $stmt = $conn->prepare("SELECT b.*, c.name as category_name, a.name as author_name 
                                FROM books b 
                                LEFT JOIN categories c ON b.category_id = c.id 
                                LEFT JOIN authors a ON b.author_id = a.id 
                                WHERE b.id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            $book = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
}

// Format the combined code with space padding for cleaner sticker presentation (e.g. S - 46)
$shelf = "";
$bcode = "";
if ($book) {
    $shelf = trim($book['shelf_code'] ?? '');
    $bcode = trim($book['book_code'] ?? '');
    
    // Fallback: If shelf code is empty, look up the category default shelf code
    if (empty($shelf) && !empty($book['category_id'])) {
        $cat_id = (int)$book['category_id'];
        $shelf_res = $conn->query("SELECT shelf_code FROM books WHERE category_id = $cat_id AND shelf_code IS NOT NULL AND shelf_code != '' GROUP BY shelf_code ORDER BY COUNT(*) DESC LIMIT 1");
        if ($shelf_res && $row = $shelf_res->fetch_assoc()) {
            $shelf = trim($row['shelf_code']);
        }
    }
} else {
    $shelf = "S";
    $bcode = "46";
}

$code_val = "";
if ($shelf && $bcode) {
    $code_val = $shelf . " - " . $bcode;
} else {
    $code_val = $shelf ?: $bcode;
}

$quantity = $book ? ($book['quantity'] ?? 1) : 1;
?>
<!DOCTYPE html>
<html lang="ckb" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چاپی لەزگەی کتێب (6cm x 4cm) - <?php echo htmlspecialchars($book ? $book['title'] : 'نوێ'); ?></title>
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
            max-width: 1120px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 410px 1fr;
            gap: 25px;
            background: #ffffff;
            padding: 26px;
            border-radius: 20px;
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        /* Sidebar Controls */
        .controls {
            display: flex;
            flex-direction: column;
            gap: 15px;
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

        .book-badge-info {
            background: #fdf2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.85rem;
            color: #991b1b;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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

        .form-group label small {
            color: #94a3b8;
            font-weight: normal;
            font-size: 0.78rem;
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

        .toggle-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
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
            border-color: #cbd5e1;
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

        .btn-back {
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

        .btn-back:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }

        /* Preview Area */
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
            box-sizing: border-box;
            position: relative;
            overflow: hidden;
            user-select: none;
            direction: ltr !important;
            transition: width 0.3s, height 0.3s;
        }

        .sticker-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            padding: 18px 20px 14px 20px;
            height: 100%;
            box-sizing: border-box;
        }

        /* Top Logo Box */
        .sticker-logo-box {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 48px;
            margin-bottom: 2px;
        }

        .sticker-logo {
            max-height: 48px;
            max-width: 120px;
            object-fit: contain;
        }

        /* Main Code */
        .sticker-main-code {
            font-size: 74px;
            font-weight: 900;
            color: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-grow: 1;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            letter-spacing: 2px;
            width: 100%;
            text-align: center;
            line-height: 1;
        }

        /* Bottom Text Banner with Background */
        .sticker-bottom-banner {
            width: 100%;
            background: #0f172a;
            color: #ffffff;
            padding: 7px 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
            transition: background 0.2s;
        }

        .sticker-bottom-text {
            font-family: 'Rabar013', 'Rabar022', Tahoma, sans-serif;
            font-size: 20px;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-align: center;
            line-height: 1.2;
            letter-spacing: 0.5px;
            direction: rtl;
        }

        /* Spine Section (Right) */
        .sticker-right {
            width: 115px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            height: 100%;
            background: #fef2f2;
            border-left: 2px dashed #f87171;
            box-sizing: border-box;
            transition: width 0.3s;
        }

        .sticker-spine-label {
            position: absolute;
            top: 8px;
            right: 8px;
            font-size: 10px;
            color: #ef4444;
            font-family: 'Rabar022', sans-serif;
            direction: rtl;
        }

        .sticker-rotated-code {
            transform: rotate(90deg);
            transform-origin: center;
            color: #dc2626;
            font-weight: 900;
            font-size: 34px;
            white-space: nowrap;
            text-align: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            letter-spacing: 1.5px;
        }

        /* Zebra Print Guide Box */
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

        .badge-danger {
            background: #fee2e2;
            color: #b91c1c;
            border-color: #fca5a5;
        }

        /* Print Container (Hidden on Screen) */
        #printArea {
            display: none;
        }

        /* ── PRINT MEDIA RULES FOR ZEBRA THERMAL PRINTER ────────────── */
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
                box-sizing: border-box;
                display: flex;
                background: #ffffff !important;
                color: #000000 !important;
                overflow: hidden;
                position: relative;
                page-break-inside: avoid;
                break-inside: avoid;
                direction: ltr !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .print-sticker:not(:last-child) {
                page-break-after: always;
                break-after: page;
            }

            .print-left {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                align-items: center;
                height: 100%;
                box-sizing: border-box;
            }

            .print-logo-box {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                height: 8mm;
                margin-bottom: 0.5mm;
            }

            .print-logo {
                max-height: 8mm;
                max-width: 25mm;
                object-fit: contain;
                filter: grayscale(100%) contrast(150%);
            }

            .print-main-code {
                font-weight: 900;
                color: #000000 !important;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-grow: 1;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                letter-spacing: 1px;
                text-align: center;
                width: 100%;
                line-height: 1;
            }

            /* Black Background Banner for Thermal Print */
            .print-bottom-banner {
                width: 100%;
                background: #000000 !important;
                color: #ffffff !important;
                padding: 1.2mm 2mm;
                border-radius: 1.5mm;
                display: flex;
                align-items: center;
                justify-content: center;
                box-sizing: border-box;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .print-bottom-text {
                font-family: 'Rabar013', Tahoma, sans-serif;
                color: #ffffff !important;
                font-weight: bold;
                text-align: center;
                width: 100%;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                line-height: 1.1;
                direction: rtl;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .print-right {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100%;
                background: #ffffff !important;
                border-left: 1px dashed #000000 !important;
                box-sizing: border-box;
            }

            .print-rotated-code {
                transform: rotate(90deg);
                transform-origin: center;
                color: #000000 !important;
                font-weight: 900;
                white-space: nowrap;
                text-align: center;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                letter-spacing: 1px;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        
        <!-- SIDEBAR CONTROLS -->
        <div class="controls">
            <div class="controls-header">
                <h2>🖨️ چاپکردنی لەزگەی کتێب</h2>
                <p style="color: #64748b; font-size: 0.8rem; margin-top: 4px;">تایبەت بە لەزگەی <strong>6cm × 4cm</strong> و پرینتەری گەرمی Zebra</p>
                <?php if ($book): ?>
                    <div class="book-badge-info" title="<?php echo htmlspecialchars($book['title']); ?>">
                        📖 <?php echo htmlspecialchars($book['title']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sticker Size Selection -->
            <div class="form-group">
                <label>قەبارەی ڕۆڵی لەزگە (Sticker Size) <small>پانی × بەرزی</small></label>
                <select id="stickerSizeSelect">
                    <option value="60x40" selected>60mm × 40mm (6cm × 4cm) - ستاندارد</option>
                    <option value="50x30">50mm × 30mm (5cm × 3cm)</option>
                </select>
            </div>

            <!-- Shelf and Book Code -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <div class="form-group">
                    <label>کۆدی ڕەفە (Shelf)</label>
                    <input type="text" id="inputShelf" value="<?php echo htmlspecialchars($shelf); ?>" style="text-transform: uppercase; direction: ltr; text-align: left; font-family: monospace; font-weight: bold;">
                </div>
                <div class="form-group">
                    <label>کۆدی کتێب (Code)</label>
                    <input type="text" id="inputBookCode" value="<?php echo htmlspecialchars($bcode); ?>" style="direction: ltr; text-align: left; font-family: monospace; font-weight: bold;">
                </div>
            </div>

            <!-- Bottom Text -->
            <div class="form-group">
                <label>نوسینی خوارەوە (بە فۆنتی Rabar 013)</label>
                <input type="text" id="inputBottomText" value="<?php echo htmlspecialchars($system_name); ?>" style="font-family: 'Rabar013', sans-serif;">
            </div>

            <!-- Font Size Options -->
            <div class="form-group">
                <label>قەبارەی فۆنتی کۆد (Font Size)</label>
                <select id="fontSizeSelect">
                    <option value="small">بچووک (Small)</option>
                    <option value="medium">ناوەند (Medium)</option>
                    <option value="large" selected>گەورە (Large) - پێشنیارکراو</option>
                    <option value="xlarge">زۆر گەورە (Extra Large)</option>
                    <option value="huge">سەرەکردە (Huge)</option>
                </select>
            </div>

            <!-- Toggles for Logo and Spine -->
            <div class="toggle-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="toggleLogo" checked>
                    <span>پیشاندانی لۆگۆ 🛡️</span>
                </label>

                <label class="checkbox-label">
                    <input type="checkbox" id="toggleSpine" checked>
                    <span>کۆدی پشتی کتێب 🔖</span>
                </label>
            </div>

            <!-- Spine Customization Box -->
            <div class="spine-settings-box" id="spineSettingsBox" style="background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:12px; padding:12px; display:flex; flex-direction:column; gap:10px;">
                <div style="font-weight:800; font-size:0.86rem; color:#5a0202; display:flex; align-items:center; gap:6px;">
                    🔖 ڕێکخستنی بەشی پشتی کتێب (Spine)
                </div>

                <div class="form-group">
                    <label>پانی بەشی پشتی کتێب (Spine Width)</label>
                    <select id="spineWidthSelect">
                        <option value="15">مامناوەند (15mm - 1.5 سانتیم)</option>
                        <option value="18">پان (18mm - 1.8 سانتیم)</option>
                        <option value="20" selected>پێشنیارکراو و پڕ (20mm - 2 سانتیم)</option>
                        <option value="22">زۆر پان (22mm - 2.2 سانتیم)</option>
                        <option value="25">فراوان (25mm - بۆ کتێبی ئەستوور)</option>
                        <option value="12">باریک (12mm - پێشوو)</option>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <div class="form-group">
                        <label>قەبارەی فۆنت</label>
                        <select id="spineFontSizeSelect">
                            <option value="medium">مامناوەند (20pt)</option>
                            <option value="large" selected>گەورە (24pt)</option>
                            <option value="xlarge">زۆر گەورە (28pt)</option>
                            <option value="huge">سەرەکردە (32pt)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>ئاراستەی نوسین</label>
                        <select id="spineRotationSelect">
                            <option value="90" selected>سەرەوە بۆ خوارەوە (90°)</option>
                            <option value="-90">خوارەوە بۆ سەرەوە (270°)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Banner Style Selector -->
            <div class="form-group">
                <label>شێوازی باکراوندی نوسینەکە</label>
                <select id="bannerStyleSelect">
                    <option value="black" selected>باکراوندی ڕەش (نوسینی سپی) - تیژ و دیار</option>
                    <option value="maroon">باکراوندی مارۆنی شاهانە</option>
                    <option value="bordered">تەنها چوارچێوە (بێ باکراوند)</option>
                </select>
            </div>

            <!-- Copies -->
            <div class="form-group">
                <label>ژمارەی کۆپی بۆ چاپ (Copies)</label>
                <input type="number" id="inputCopies" value="1" min="1" max="100">
            </div>

            <!-- Print Trigger Button -->
            <button onclick="triggerPrint()" class="btn-print">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;height:20px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                دەستپێکردنی چاپکردن
            </button>

            <!-- Back Link -->
            <a href="books.php" class="btn-back">
                ↩️ گەڕانەوە بۆ پێڕستی کتێبەکان
            </a>
        </div>

        <!-- STICKER PREVIEW AREA -->
        <div class="preview-area">
            <div class="preview-header">
                <div class="preview-title">
                    <span>👁️ پێشبینینی شێوازی لەزگە</span>
                </div>
                <div class="preview-badge" id="previewDimensionBadge">60mm × 40mm (6cm × 4cm)</div>
            </div>
            
            <div class="sticker-wrapper" id="stickerWrapper">
                <div class="sticker-left" id="stickerLeft">
                    <!-- Top Logo -->
                    <div class="sticker-logo-box" id="previewLogoBox">
                        <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo" class="sticker-logo" id="previewLogoImg">
                    </div>

                    <!-- Main Code -->
                    <div class="sticker-main-code" id="previewMainCode"><?php echo htmlspecialchars($code_val); ?></div>

                    <!-- Bottom Text Banner with Background & Rabar013 -->
                    <div class="sticker-bottom-banner" id="previewBottomBanner">
                        <div class="sticker-bottom-text" id="previewBottomText"><?php echo htmlspecialchars($system_name); ?></div>
                    </div>
                </div>

                <!-- Right Spine Strip -->
                <div class="sticker-right" id="stickerRight">
                    <span class="sticker-spine-label">پشتی کتێب</span>
                    <div class="sticker-rotated-code" id="previewRotatedCode"><?php echo htmlspecialchars($code_val); ?></div>
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
                        <span>2. <strong>Paper size (قەبارە):</strong> دیاریبکە لەسەر</span>
                        <span class="badge-setting">6cm × 4cm</span>
                        <span>یان</span>
                        <span class="badge-setting">Custom</span>
                    </li>
                    <li>
                        <span>3. <strong>Margins (پەراوێز):</strong> پێویستە بکرێتە</span>
                        <span class="badge-setting badge-danger">None (هیچ)</span>
                        <span style="color:#b91c1c; font-weight:bold;">⚠️ زۆر گرنگە بۆ تێکنەچوونی پەڕە!</span>
                    </li>
                    <li>
                        <span>4. <strong>Headers and footers:</strong> تیکەکەی لابدە</span>
                        <span class="badge-setting">Unchecked (ناچالاک)</span>
                    </li>
                    <li>
                        <span>5. <strong>Background graphics:</strong> دڵنیابە</span>
                        <span class="badge-setting">تیک لێدراوە (Checked)</span>
                        <span>بۆ دەرکەوتنی باکراوندی نوسینەکە</span>
                    </li>
                </ul>
            </div>
        </div>

    </div>

    <!-- PRINT CONTAINER (DYNAMICALLY POPULATED) -->
    <div id="printArea"></div>

    <script>
        // Sticker Configuration Presets
        const SIZES = {
            '60x40': {
                name: '60mm × 40mm (6cm × 4cm)',
                widthMm: 60,
                heightMm: 40,
                previewWidth: 540,
                previewHeight: 360,
                previewSpineWidth: 115,
                spineWidthMm: 13,
                fontSizes: {
                    'small': { preview: '52px', print: '21pt' },
                    'medium': { preview: '64px', print: '25pt' },
                    'large': { preview: '74px', print: '29pt' },
                    'xlarge': { preview: '86px', print: '34pt' },
                    'huge': { preview: '98px', print: '39pt' }
                },
                bottomText: { preview: '20px', print: '9.5pt' },
                rotatedCode: { preview: '34px', print: '16pt' },
                paddingMm: '2.5mm 3.5mm 2mm 3.5mm'
            },
            '50x30': {
                name: '50mm × 30mm (5cm × 3cm)',
                widthMm: 50,
                heightMm: 30,
                previewWidth: 500,
                previewHeight: 300,
                previewSpineWidth: 95,
                spineWidthMm: 10,
                fontSizes: {
                    'small': { preview: '44px', print: '16pt' },
                    'medium': { preview: '52px', print: '19pt' },
                    'large': { preview: '60px', print: '22pt' },
                    'xlarge': { preview: '68px', print: '25pt' },
                    'huge': { preview: '76px', print: '28pt' }
                },
                bottomText: { preview: '16px', print: '7.5pt' },
                rotatedCode: { preview: '26px', print: '12pt' },
                paddingMm: '2mm 2.5mm 1.5mm 2.5mm'
            }
        };

        const stickerSizeSelect = document.getElementById('stickerSizeSelect');
        const inputShelf = document.getElementById('inputShelf');
        const inputBookCode = document.getElementById('inputBookCode');
        const inputBottomText = document.getElementById('inputBottomText');
        const fontSizeSelect = document.getElementById('fontSizeSelect');
        const toggleLogo = document.getElementById('toggleLogo');
        const toggleSpine = document.getElementById('toggleSpine');
        const spineSettingsBox = document.getElementById('spineSettingsBox');
        const spineWidthSelect = document.getElementById('spineWidthSelect');
        const spineFontSizeSelect = document.getElementById('spineFontSizeSelect');
        const spineRotationSelect = document.getElementById('spineRotationSelect');
        const bannerStyleSelect = document.getElementById('bannerStyleSelect');

        const stickerWrapper = document.getElementById('stickerWrapper');
        const stickerRight = document.getElementById('stickerRight');
        const previewDimensionBadge = document.getElementById('previewDimensionBadge');
        const previewLogoBox = document.getElementById('previewLogoBox');
        const previewMainCode = document.getElementById('previewMainCode');
        const previewBottomBanner = document.getElementById('previewBottomBanner');
        const previewBottomText = document.getElementById('previewBottomText');
        const previewRotatedCode = document.getElementById('previewRotatedCode');
        const dynamicPrintStyle = document.getElementById('dynamicPrintStyle');

        // Spine Font Size Mapping
        const spineFontMap = {
            'medium': { preview: '44px', print: '20pt' },
            'large': { preview: '54px', print: '24pt' },
            'xlarge': { preview: '64px', print: '28pt' },
            'huge': { preview: '74px', print: '32pt' }
        };

        // Get combined code dynamically
        function getCombinedCode() {
            const shelf = inputShelf.value.trim().toUpperCase();
            const bcode = inputBookCode.value.trim();
            if (shelf && bcode) {
                return shelf + " - " + bcode;
            }
            return shelf || bcode || '';
        }

        // Dynamic preview update
        function updatePreview() {
            const sizeKey = stickerSizeSelect.value;
            const cfg = SIZES[sizeKey] || SIZES['60x40'];
            const code = getCombinedCode();
            const bText = inputBottomText.value;
            const fSizeKey = fontSizeSelect.value;
            const hasLogo = toggleLogo.checked;
            const hasSpine = toggleSpine.checked;
            const bannerStyle = bannerStyleSelect.value;

            // Dimensions badge
            previewDimensionBadge.textContent = cfg.name;

            // Update Preview Box dimensions
            stickerWrapper.style.width = cfg.previewWidth + 'px';
            stickerWrapper.style.height = cfg.previewHeight + 'px';

            // Logo visibility
            previewLogoBox.style.display = hasLogo ? 'flex' : 'none';

            // Spine settings box visibility
            spineSettingsBox.style.display = hasSpine ? 'flex' : 'none';

            // Spine visibility, width, and font
            if (hasSpine) {
                stickerRight.style.display = 'flex';
                
                // Calculate dynamic spine width in pixels
                const spineWidthMm = parseFloat(spineWidthSelect.value) || 20;
                const pxPerMm = cfg.previewWidth / cfg.widthMm;
                const spinePx = Math.round(spineWidthMm * pxPerMm);
                stickerRight.style.width = spinePx + 'px';

                // Spine Font & Rotation
                const chosenSpineFont = spineFontMap[spineFontSizeSelect.value] || spineFontMap['large'];
                const spineRot = spineRotationSelect.value;

                previewRotatedCode.textContent = code;
                previewRotatedCode.style.fontSize = chosenSpineFont.preview;
                previewRotatedCode.style.transform = `rotate(${spineRot}deg)`;
            } else {
                stickerRight.style.display = 'none';
            }

            // Main text and sizes
            previewMainCode.textContent = code;
            const chosenFont = cfg.fontSizes[fSizeKey] || cfg.fontSizes['large'];
            previewMainCode.style.fontSize = chosenFont.preview;

            // Bottom banner text and style
            previewBottomText.textContent = bText;
            previewBottomText.style.fontSize = cfg.bottomText.preview;

            if (bannerStyle === 'black') {
                previewBottomBanner.style.background = '#0f172a';
                previewBottomBanner.style.border = 'none';
                previewBottomText.style.color = '#ffffff';
            } else if (bannerStyle === 'maroon') {
                previewBottomBanner.style.background = '#5a0202';
                previewBottomBanner.style.border = 'none';
                previewBottomText.style.color = '#ffffff';
            } else if (bannerStyle === 'bordered') {
                previewBottomBanner.style.background = 'transparent';
                previewBottomBanner.style.border = '2px solid #0f172a';
                previewBottomText.style.color = '#0f172a';
            }

            // Update Dynamic @page print CSS rule
            dynamicPrintStyle.textContent = `
                @page {
                    size: ${cfg.widthMm}mm ${cfg.heightMm}mm;
                    margin: 0;
                }
            `;
        }

        stickerSizeSelect.addEventListener('change', updatePreview);
        inputShelf.addEventListener('input', updatePreview);
        inputBookCode.addEventListener('input', updatePreview);
        inputBottomText.addEventListener('input', updatePreview);
        fontSizeSelect.addEventListener('change', updatePreview);
        toggleLogo.addEventListener('change', updatePreview);
        toggleSpine.addEventListener('change', updatePreview);
        spineWidthSelect.addEventListener('change', updatePreview);
        spineFontSizeSelect.addEventListener('change', updatePreview);
        spineRotationSelect.addEventListener('change', updatePreview);
        bannerStyleSelect.addEventListener('change', updatePreview);

        // Print triggering
        function triggerPrint() {
            const sizeKey = stickerSizeSelect.value;
            const cfg = SIZES[sizeKey] || SIZES['60x40'];
            const code = getCombinedCode();
            const bText = inputBottomText.value;
            const fSizeKey = fontSizeSelect.value;
            const chosenFont = cfg.fontSizes[fSizeKey] || cfg.fontSizes['large'];
            const printFontSize = chosenFont.print;
            const hasLogo = toggleLogo.checked;
            const hasSpine = toggleSpine.checked;
            const bannerStyle = bannerStyleSelect.value;
            const copies = parseInt(document.getElementById('inputCopies').value) || 1;

            // Spine settings
            const spineWidthMm = parseFloat(spineWidthSelect.value) || 20;
            const chosenSpineFont = spineFontMap[spineFontSizeSelect.value] || spineFontMap['large'];
            const spineRot = spineRotationSelect.value;

            const printArea = document.getElementById('printArea');
            printArea.innerHTML = '';

            // Compute banner print CSS
            let bannerCss = 'background: #000000 !important; color: #ffffff !important; border: none;';
            let textCss = 'color: #ffffff !important;';
            if (bannerStyle === 'bordered') {
                bannerCss = 'background: transparent !important; color: #000000 !important; border: 0.4mm solid #000000;';
                textCss = 'color: #000000 !important;';
            }

            for (let i = 0; i < copies; i++) {
                const sticker = document.createElement('div');
                sticker.className = 'print-sticker';
                sticker.style.width = cfg.widthMm + 'mm';
                sticker.style.height = cfg.heightMm + 'mm';

                let logoHtml = '';
                if (hasLogo) {
                    logoHtml = `
                        <div class="print-logo-box">
                            <img src="<?php echo htmlspecialchars($logo_path); ?>" class="print-logo" alt="Logo">
                        </div>
                    `;
                }

                let spineHtml = '';
                if (hasSpine) {
                    spineHtml = `
                        <div class="print-right" style="width: ${spineWidthMm}mm;">
                            <div class="print-rotated-code" style="font-size: ${chosenSpineFont.print}; transform: rotate(${spineRot}deg);">${code}</div>
                        </div>
                    `;
                }

                sticker.innerHTML = `
                    <div class="print-left" style="padding: 2mm 2.5mm 1.5mm 2.5mm;">
                        ${logoHtml}
                        <div class="print-main-code" style="font-size: ${printFontSize};">${code}</div>
                        <div class="print-bottom-banner" style="${bannerCss}">
                            <div class="print-bottom-text" style="font-size: 8.5pt; ${textCss}">${bText}</div>
                        </div>
                    </div>
                    ${spineHtml}
                `;
                printArea.appendChild(sticker);
            }

            // Trigger native print dialog
            setTimeout(() => {
                window.print();
            }, 50);
        }

        // Initialize preview on page load
        updatePreview();
    </script>
</body>
</html>
