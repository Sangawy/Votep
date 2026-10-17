<?php
session_start();
if (!isset($_SESSION['public_admin'])) {
    header("Location: login.php");
    exit;
}
require_once '../db_config.php';
$conn = getPublicDB();

$id = $_GET['id'] ?? null;
$act = ['title' => '', 'content' => '', 'activity_date' => date('Y-m-d'), 'is_published' => 1, 'image_path' => ''];

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM public_activities WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $act = $stmt->get_result()->fetch_assoc();
}

$settings_res = $conn->query("SELECT * FROM public_settings WHERE id = 1");
$site = $settings_res->fetch_assoc();
$site_color = $site['primary_color'] ?? '#5a0202';
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $id ? 'دەستکاری چالاکی' : 'زیادکردنی چالاکی نوێ'; ?></title>
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
            max-width: 650px;
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

        .form-group input[type="text"], 
        .form-group input[type="date"], 
        .form-group select, 
        .form-group textarea {
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

        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
            background-repeat: no-repeat;
            background-position: left 14px center;
            background-size: 16px;
        }

        .form-group input:focus, 
        .form-group select:focus, 
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

        /* Camera & File Upload Dropzone */
        .file-upload-container {
            border: 2px dashed #cbd5e1;
            border-radius: 18px;
            padding: 24px;
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

        /* Images Preview Grid */
        .gallery-preview-title {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 10px;
            margin-top: 15px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 16px;
            border: 1.5px solid var(--border);
        }

        .gallery-item {
            position: relative;
            aspect-ratio: 1;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }

        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .btn-delete-img {
            position: absolute;
            top: 5px;
            left: 5px;
            background: #ef4444;
            color: #fff;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            text-align: center;
            line-height: 20px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: 0.15s;
        }

        .btn-delete-img:hover {
            background: #dc2626;
            transform: scale(1.1);
        }

        /* Action Buttons */
        .btn-bar {
            display: flex;
            gap: 12px;
            margin-top: 30px;
        }

        .btn-form {
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
            .btn-form {
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
            <h2><?php echo $id ? '📝 دەستکاریکردنی چالاکی' : '➕ زیادکردنی چالاکی نوێ'; ?></h2>
            <p><?php echo htmlspecialchars($site['system_name'] ?? 'مەڵبەندی پیرخدر'); ?></p>
        </div>

        <form action="activity_process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            
            <!-- SECTION 1: CONTENT -->
            <div class="section-title">📅 زانیارییەکان چالاکی</div>
            
            <div class="form-group">
                <label>ناونیشانی چالاکی *</label>
                <div class="input-wrapper">
                    <span class="input-icon">📢</span>
                    <input type="text" name="title" required value="<?php echo htmlspecialchars($act['title']); ?>" placeholder="بۆ نموونە: سیمیناری بەستنی کۆنفرانس">
                </div>
            </div>
            
            <div class="form-group">
                <label>ناوەڕۆک (وەسف) *</label>
                <div class="input-wrapper" style="align-items: flex-start;">
                    <span class="input-icon" style="top: 14px;">📄</span>
                    <textarea name="content" rows="6" style="padding-top: 14px;" required placeholder="وردەکاری و ناوەڕۆکی چالاکییەکە لێرە بنووسە..."><?php echo htmlspecialchars($act['content']); ?></textarea>
                </div>
            </div>

            <div class="row-grid">
                <div class="form-group">
                    <label>بەروار *</label>
                    <div class="input-wrapper">
                        <span class="input-icon">📅</span>
                        <input type="date" name="activity_date" required value="<?php echo htmlspecialchars($act['activity_date']); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>دۆخی بڵاوکردنەوە</label>
                    <div class="input-wrapper">
                        <span class="input-icon">⚙️</span>
                        <select name="is_published">
                            <option value="1" <?php echo $act['is_published'] ? 'selected' : ''; ?>>بڵاوکراوە</option>
                            <option value="0" <?php echo !$act['is_published'] ? 'selected' : ''; ?>>ڕەشنووس</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: GALLERY IMAGES -->
            <div class="section-title">🖼️ وێنەکانی چالاکی</div>

            <!-- Dropzone for choosing/capturing multiple files -->
            <div class="file-upload-container">
                <div class="file-upload-label">
                    <span class="file-upload-icon">📸</span>
                    <span class="file-upload-text">بارکردن یان گرتنی وێنەی چالاکی</span>
                    <span class="file-upload-subtext">کلیک بکە بۆ هەڵبژاردنی یەک یان چەند وێنەیەک</span>
                </div>
                <input type="file" name="activity_images[]" accept="image/*" multiple class="file-upload-input" <?php echo $id ? '' : 'required'; ?>>
            </div>

            <!-- Previews of selected files -->
            <div id="local-preview-container" style="display:none; margin-bottom: 20px;">
                <div class="gallery-preview-title">🖼️ وێنە هەڵبژێردراوە نوێیەکان:</div>
                <div class="gallery-grid" id="local-gallery-grid"></div>
            </div>

            <!-- Previews of existing files on database -->
            <?php 
            if ($id) {
                $img_res = $conn->query("SELECT * FROM public_activity_images WHERE activity_id = $id ORDER BY sort_order ASC, id ASC");
                if ($img_res && $img_res->num_rows > 0) {
                    echo '<div style="margin-bottom: 20px;">';
                    echo '<div class="gallery-preview-title">🖼️ وێنەکانی ئێستای نێو داتابەیس:</div>';
                    echo '<div class="gallery-grid">';
                    $images = [];
                    while($row_img = $img_res->fetch_assoc()) {
                        $images[] = $row_img;
                    }
                    $total_images = count($images);
                    
                    foreach($images as $index => $img) {
                        $is_cover = ($act['image_path'] === $img['image_path']);
                        
                        echo '<div class="gallery-item" ' . ($is_cover ? 'style="border: 3px solid var(--primary);"' : '') . '>';
                        echo '<img src="../' . htmlspecialchars($img['image_path']) . '">';
                        
                        if ($is_cover) {
                            echo '<div style="position:absolute; top:5px; right:5px; background:var(--primary); color:#fff; font-size:10px; padding:2px 8px; border-radius:10px; font-weight:bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">⭐ کەڤەر</div>';
                        }
                        
                        echo '<div style="position:absolute; bottom:0; left:0; width:100%; background:rgba(0,0,0,0.65); display:flex; justify-content:space-between; align-items:center; padding:4px 6px;">';
                        
                        echo '<div style="display:flex; gap:6px;">';
                        if ($index > 0) {
                            echo '<a href="activity_process.php?action=move_up&img_id=' . $img['id'] . '&id=' . $id . '" style="color:#fff; text-decoration:none; font-size:14px; background:rgba(255,255,255,0.2); border-radius:4px; padding:0 4px;" title="گواستنەوە بۆ ڕاست">▶️</a>';
                        }
                        if ($index < $total_images - 1) {
                            echo '<a href="activity_process.php?action=move_down&img_id=' . $img['id'] . '&id=' . $id . '" style="color:#fff; text-decoration:none; font-size:14px; background:rgba(255,255,255,0.2); border-radius:4px; padding:0 4px;" title="گواستنەوە بۆ چەپ">◀️</a>';
                        }
                        echo '</div>';
                        
                        if (!$is_cover) {
                            echo '<a href="activity_process.php?action=set_cover&img_id=' . $img['id'] . '&id=' . $id . '" style="color:#fff; text-decoration:none; font-size:10px; background:#10b981; padding:2px 6px; border-radius:4px; font-weight:bold;">وەک کەڤەر</a>';
                        } else {
                            echo '<div></div>'; // Spacer to keep layout balanced
                        }
                        
                        echo '</div>';
                        
                        echo '<a href="activity_process.php?action=delete_img&img_id=' . $img['id'] . '&id=' . $id . '" class="btn-delete-img" onclick="return confirm(\'ئایا دڵنیایت لە سڕینەوەی ئەم وێنەیە؟\')">×</a>';
                        echo '</div>';
                    }
                    echo '</div>';
                    echo '</div>';
                }
            }
            ?>

            <?php if ($act['image_path'] && (!isset($img_res) || $img_res->num_rows == 0)): ?>
                <div style="margin-bottom: 20px;">
                    <div class="gallery-preview-title">🖼️ وێنەی سەرەکی چالاکی:</div>
                    <div class="gallery-grid">
                        <div class="gallery-item">
                            <img src="../<?php echo htmlspecialchars($act['image_path']); ?>">
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="btn-bar">
                <button type="submit" class="btn-form btn-submit">💾 پاشەکەوتکردن</button>
                <a href="index.php" class="btn-form btn-cancel">❌ پاشگەزبوونەوە</a>
            </div>
        </form>
    </div>

    <script>
        // Multi-image file input change handler
        document.querySelector('.file-upload-input').addEventListener('change', function(e) {
            const previewContainer = document.getElementById('local-preview-container');
            const grid = document.getElementById('local-gallery-grid');
            grid.innerHTML = '';
            
            if (this.files && this.files.length > 0) {
                previewContainer.style.display = 'block';
                Array.from(this.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const item = document.createElement('div');
                        item.className = 'gallery-item';
                        item.innerHTML = `<img src="${e.target.result}">`;
                        grid.appendChild(item);
                    }
                    reader.readAsDataURL(file);
                });
            } else {
                previewContainer.style.display = 'none';
            }
        });
    </script>
</body>
</html>
