<?php
session_start();
if (!isset($_SESSION['public_admin'])) {
    header("Location: login.php");
    exit;
}
require_once '../db_config.php';
$conn = getPublicDB();

$id = $_GET['id'] ?? 0;
$book = null;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM public_books WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();
}

$settings_res = $conn->query("SELECT * FROM public_settings WHERE id = 1");
$site = $settings_res->fetch_assoc();
$site_color = $site['primary_color'] ?? '#5a0202';
$titles = [];
$authors = [];
$translators = [];
$categories = [];

$res = $conn->query("SELECT DISTINCT title FROM public_books WHERE title IS NOT NULL AND title != '' ORDER BY title");
if ($res) while($r = $res->fetch_assoc()) $titles[] = $r['title'];

$res = $conn->query("SELECT DISTINCT author_name FROM public_books WHERE author_name IS NOT NULL AND author_name != '' ORDER BY author_name");
if ($res) while($r = $res->fetch_assoc()) $authors[] = $r['author_name'];

$res = $conn->query("SELECT DISTINCT translator_name FROM public_books WHERE translator_name IS NOT NULL AND translator_name != '' ORDER BY translator_name");
if ($res) while($r = $res->fetch_assoc()) $translators[] = $r['translator_name'];

$res = $conn->query("SELECT DISTINCT category_name FROM public_books WHERE category_name IS NOT NULL AND category_name != '' ORDER BY category_name");
if ($res) while($r = $res->fetch_assoc()) $categories[] = $r['category_name'];
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $book ? 'دەستکاریکردنی کتێب' : 'زیادکردنی کتێبی نوێ'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?php echo $site_color; ?>;
            --primary-light: <?php echo $site_color; ?>15;
            --primary-hover: <?php echo $site_color; ?>e0;
            --dark: #1e293b;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Noto Sans Arabic', sans-serif;
            background: var(--bg);
            color: var(--dark);
            line-height: 1.6;
            padding: 10px;
            padding-bottom: 40px;
        }

        .container {
            max-width: 600px;
            margin: 10px auto;
            background: var(--card-bg);
            border-radius: 24px;
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        /* Glassmorphic Premium Header */
        .header {
            background: linear-gradient(135deg, var(--primary), var(--primary-hover));
            padding: 30px 20px;
            text-align: center;
            color: #fff;
            position: relative;
        }

        .header h2 {
            font-size: 1.45rem;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .header p {
            font-size: 0.85rem;
            opacity: 0.85;
        }

        form {
            padding: 24px 20px;
        }

        /* Form Layout */
        .section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary);
            margin: 25px 0 15px 0;
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .section-title:first-of-type {
            margin-top: 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #475569;
            font-size: 0.88rem;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            right: 14px;
            font-size: 1.1rem;
            color: #94a3b8;
            pointer-events: none;
        }

        .form-group input, 
        .form-group textarea, 
        .form-group select {
            width: 100%;
            padding: 14px 44px 14px 14px;
            border: 1.5px solid var(--border);
            border-radius: 14px;
            outline: none;
            font-family: inherit;
            font-size: 0.95rem;
            color: var(--dark);
            background-color: #f8fafc;
            transition: all 0.25s ease;
        }

        .form-group input:focus, 
        .form-group textarea:focus {
            border-color: var(--primary);
            background-color: #fff;
            box-shadow: 0 0 0 4px var(--primary-light);
        }

        .row-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* Camera & File Upload Styled Component */
        .file-upload-container {
            border: 2px dashed #cbd5e1;
            border-radius: 18px;
            padding: 20px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            margin-bottom: 20px;
        }

        .file-upload-container:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }

        .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .file-upload-icon {
            font-size: 2.2rem;
            margin-bottom: 4px;
        }

        .file-upload-text {
            font-weight: 700;
            color: #475569;
            font-size: 0.9rem;
        }

        .file-upload-subtext {
            font-size: 0.78rem;
            color: #94a3b8;
        }

        .file-upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        /* Cover Preview Card */
        .cover-preview-card {
            background: #f8fafc;
            border: 1.5px solid var(--border);
            border-radius: 18px;
            padding: 15px;
            text-align: center;
            margin-top: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 180px;
        }

        .cover-preview-img {
            max-width: 120px;
            max-height: 180px;
            border-radius: 8px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            object-fit: cover;
            border: 2px solid #fff;
            display: none;
        }

        .cover-placeholder {
            font-size: 3.5rem;
            opacity: 0.3;
        }

        /* Action Buttons */
        .btn-bar {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 16px 20px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 4px 12px rgba(90, 2, 2, 0.2);
        }

        .btn-submit:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #475569;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        /* Responsive Improvements */
        @media (max-width: 480px) {
            body {
                padding: 5px;
            }
            .container {
                border-radius: 18px;
            }
            form {
                padding: 16px 14px;
            }
            .row-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .btn {
                padding: 14px 16px;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Glassmorphic Header -->
        <div class="header">
            <h2><?php echo $book ? '📝 دەستکاریکردنی زانیاری کتێب' : '➕ زیادکردنی کتێبی نوێ'; ?></h2>
            <p><?php echo htmlspecialchars($site['system_name'] ?? 'مەڵبەندی پیرخدر'); ?></p>
        </div>

        <form action="book_process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            
            <!-- DATALISTS FOR AUTOCOMPLETE -->
            <datalist id="bookTitles">
                <?php foreach($titles as $t) echo '<option value="'.htmlspecialchars($t).'">'; ?>
            </datalist>
            <datalist id="bookAuthors">
                <?php foreach($authors as $a) echo '<option value="'.htmlspecialchars($a).'">'; ?>
            </datalist>
            <datalist id="bookTranslators">
                <?php foreach($translators as $tr) echo '<option value="'.htmlspecialchars($tr).'">'; ?>
            </datalist>
            <datalist id="bookCategories">
                <?php foreach($categories as $c) echo '<option value="'.htmlspecialchars($c).'">'; ?>
            </datalist>
            
            <!-- SECTION 1: MAIN INFO -->
            <div class="section-title">📖 زانیارییە سەرەکییەکان</div>
            
            <div class="form-group">
                <label>ناونیشانی کتێب *</label>
                <div class="input-wrapper">
                    <span class="input-icon">📘</span>
                    <input type="text" name="title" required value="<?php echo htmlspecialchars($book['title'] ?? ''); ?>" placeholder="بۆ نموونە: دیوانی پیرخدر" list="bookTitles">
                </div>
            </div>
            
            <div class="row-grid">
                <div class="form-group">
                    <label>نووسەر *</label>
                    <div class="input-wrapper">
                        <span class="input-icon">✍️</span>
                        <input type="text" name="author_name" required value="<?php echo htmlspecialchars($book['author_name'] ?? ''); ?>" placeholder="ناوی نووسەر" list="bookAuthors">
                    </div>
                </div>
                <div class="form-group">
                    <label>وەرگێڕ</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🌍</span>
                        <input type="text" name="translator_name" value="<?php echo htmlspecialchars($book['translator_name'] ?? ''); ?>" placeholder="ناوی وەرگێڕ (ئارەزوومەندانە)" list="bookTranslators">
                    </div>
                </div>
            </div>
            
            <div class="row-grid">
                <div class="form-group">
                    <label>پۆلێن (کاتێگۆری) *</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🏷️</span>
                        <input type="text" name="category_name" required value="<?php echo htmlspecialchars($book['category_name'] ?? ''); ?>" placeholder="ئاینی، مێژوویی..." list="bookCategories">
                    </div>
                </div>
                <div class="form-group">
                    <label>ساڵی چاپ</label>
                    <div class="input-wrapper">
                        <span class="input-icon">📅</span>
                        <input type="text" name="publish_year" value="<?php echo htmlspecialchars($book['publish_year'] ?? ''); ?>" placeholder="ساڵی چاپ">
                    </div>
                </div>
            </div>
            
            <!-- SECTION 2: LOCATIONS & QUANTITY -->
            <div class="section-title">🗄️ شوێن و کۆگا</div>
            
            <div class="row-grid">
                <div class="form-group">
                    <label>کۆدی ڕەفە *</label>
                    <div class="input-wrapper">
                        <span class="input-icon">📦</span>
                        <input type="text" name="shelf_code" required value="<?php echo htmlspecialchars($book['shelf_code'] ?? ''); ?>" placeholder="A-12">
                    </div>
                </div>
                <div class="form-group">
                    <label>کۆدی کتێب *</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔢</span>
                        <input type="text" name="book_code" required value="<?php echo htmlspecialchars($book['book_code'] ?? ''); ?>" placeholder="0045">
                    </div>
                </div>
            </div>
            
            <div class="row-grid">
                <div class="form-group">
                    <label>چەندایەتی (تەواوی کۆگا)</label>
                    <div class="input-wrapper">
                        <span class="input-icon">📊</span>
                        <input type="number" name="quantity" min="1" required value="<?php echo htmlspecialchars($book['quantity'] ?? '1'); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>بەردەست</label>
                    <div class="input-wrapper">
                        <span class="input-icon">✅</span>
                        <input type="number" name="available" min="0" required value="<?php echo htmlspecialchars($book['available'] ?? '1'); ?>">
                    </div>
                </div>
            </div>
            
            <!-- SECTION 3: IMAGE CAPTURE -->
            <div class="section-title">📸 بەرگی کتێب</div>
            
            <!-- Camera capturing container -->
            <div class="file-upload-container">
                <div class="file-upload-label">
                    <span class="file-upload-icon">📸</span>
                    <span class="file-upload-text">وێنەگرتنی بەرگ بە کامێرا یان گالەری</span>
                    <span class="file-upload-subtext">کلیک بکە بۆ بەکارهێنانی کامێرای مۆبایلەکەت</span>
                </div>
                <input type="file" name="cover_file" id="cover_file" accept="image/*" class="file-upload-input" onchange="previewFile(this)">
            </div>
            
            <!-- Fallback URL based Cover input -->
            <div class="form-group">
                <label>بەستەری بەرگ (سەرچاوەی ئۆنلاین)</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔗</span>
                    <input type="url" name="cover_image" id="cover_input" value="<?php echo htmlspecialchars($book['cover_image'] ?? ''); ?>" placeholder="https://example.com/cover.jpg" oninput="updateCoverPreview()">
                </div>
            </div>

            <!-- Preview Card -->
            <div class="cover-preview-card">
                <div class="cover-placeholder" id="cover_placeholder">📖</div>
                <img src="<?php echo htmlspecialchars($book['cover_image'] ?? ''); ?>" class="cover-preview-img" id="cover_img_el">
            </div>

            <!-- SECTION 4: EXTRA INFO -->
            <div class="section-title">📝 دەربارەی کتێب</div>
            
            <div class="form-group">
                <label>کورتە باسی کتێب</label>
                <div class="input-wrapper" style="align-items: flex-start;">
                    <span class="input-icon" style="top: 14px;">📄</span>
                    <textarea name="description" rows="4" style="padding-top: 14px;" placeholder="کورتەیەکی کورت بنووسە دەربارەی ئەم کتێبە..."><?php echo htmlspecialchars($book['description'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label>تۆمارکەر (Creator Name)</label>
                <div class="input-wrapper">
                    <span class="input-icon">👤</span>
                    <input type="text" name="creator_name" value="<?php echo htmlspecialchars($book['creator_name'] ?? $_SESSION['public_admin_name'] ?? 'ئەدمین'); ?>">
                </div>
            </div>
            
            <div class="btn-bar">
                <button type="submit" class="btn btn-submit">💾 پاشەکەوتکردن</button>
                <a href="books.php" class="btn btn-cancel">❌ پاشگەزبوونەوە</a>
            </div>
        </form>
    </div>

    <script>
        function previewFile(input) {
            const img = document.getElementById('cover_img_el');
            const placeholder = document.getElementById('cover_placeholder');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    img.style.display = 'block';
                    placeholder.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function updateCoverPreview() {
            const input = document.getElementById('cover_input');
            const img = document.getElementById('cover_img_el');
            const placeholder = document.getElementById('cover_placeholder');
            
            // Only update from input URL if no file is chosen
            const fileInput = document.getElementById('cover_file');
            if (fileInput.files && fileInput.files[0]) return;
            
            if (input.value.trim() !== '') {
                img.src = input.value;
                img.style.display = 'block';
                placeholder.style.display = 'none';
            } else {
                img.style.display = 'none';
                placeholder.style.display = 'block';
            }
        }
        
        // Run on page load
        updateCoverPreview();
    </script>
</body>
</html>
