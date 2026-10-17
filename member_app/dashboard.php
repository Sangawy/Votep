<?php
require_once __DIR__ . '/auth.php';

// Calculate Profile Completeness
$totalPoints = 0;
$missingItems = [];

// 1. Profile photo (Highest importance as requested by user)
$hasProfilePhoto = !empty($current_member['profile_image']);
if ($hasProfilePhoto) {
    $totalPoints += 25;
} else {
    $missingItems[] = 'وێنەی کەسی (گرنگترینە)';
}

// 2. National card front
if (isset($member_docs['کارتی نیشتمانی (دیوی پێشەوە)']) || isset($member_docs['کارتی نیشتمانی'])) {
    $totalPoints += 15;
} else {
    $missingItems[] = 'کارتی نیشتمانی (دیوی پێشەوە)';
}

// 3. National card back
if (isset($member_docs['کارتی نیشتمانی (دیوی دواوە)'])) {
    $totalPoints += 15;
} else {
    $missingItems[] = 'کارتی نیشتمانی (دیوی دواوە)';
}

// 4. Information card
if (isset($member_docs['کارتی زانیاری'])) {
    $totalPoints += 15;
} else {
    $missingItems[] = 'کارتی زانیاری';
}

// Check if all primary documents are uploaded (front, back, info)
$docFront = $member_docs['کارتی نیشتمانی (دیوی پێشەوە)'] ?? ($member_docs['کارتی نیشتمانی'] ?? null);
$docBack = $member_docs['کارتی نیشتمانی (دیوی دواوە)'] ?? null;
$docInfo = $member_docs['کارتی زانیاری'] ?? null;
$isDocsCompleted = (!empty($docFront) && !empty($docBack) && !empty($docInfo));

// 5. Security code
if (!empty($current_member['security_code'])) {
    $totalPoints += 10;
} else {
    $missingItems[] = 'کۆدی ئاسایش';
}

// 6. Phone, Address, Education & Blood Group
if (!empty($current_member['phone'])) $totalPoints += 5; else $missingItems[] = 'ژمارەی مۆبایل';
if (!empty($current_member['address'])) $totalPoints += 5; else $missingItems[] = 'ناونیشان';
if (!empty($current_member['education']) || !empty($current_member['education_level'])) $totalPoints += 5; else $missingItems[] = 'ئاستی خوێندن';
if (!empty($current_member['blood_group'])) $totalPoints += 5; else $missingItems[] = 'گرووپی خوێن';

// -------------------------------------------------------------
// BUILD TRIPARTITE NAME (ناوی سیانی: خۆی + باوک + باپیر)
// -------------------------------------------------------------
$personTitle = '';
if (preg_match('/^(السيد\s+ملا|السید\s+مەلا|السيد\s+شێخ|السید\s+شیخ|الشیخ|الشيخ|السيد|السید|سید|سەید|ملا|مەلا|شێخ|شیخ)/u', $current_member['full_name'], $tm)) {
    $personTitle = $tm[1];
}

$coreFirst = !empty($current_member['first_name']) ? trim($current_member['first_name']) : (function_exists('extractFirstName') ? extractFirstName($current_member['full_name']) : trim($current_member['full_name']));
if (empty($coreFirst)) $coreFirst = trim($current_member['full_name']);

$coreFather = !empty($current_member['father_name']) ? trim($current_member['father_name']) : '';
$coreGrand = !empty($current_member['grandfather_name']) ? trim($current_member['grandfather_name']) : '';

if (empty($coreFather) && !empty($current_member['parent_id'])) {
    $fat = $conn->query("SELECT full_name, first_name, parent_id FROM members WHERE id = " . (int)$current_member['parent_id'])->fetch_assoc();
    if ($fat) {
        $coreFather = !empty($fat['first_name']) ? $fat['first_name'] : (function_exists('extractFirstName') ? extractFirstName($fat['full_name']) : trim($fat['full_name']));
        if (empty($coreGrand) && !empty($fat['parent_id'])) {
            $gfat = $conn->query("SELECT full_name, first_name FROM members WHERE id = " . (int)$fat['parent_id'])->fetch_assoc();
            if ($gfat) {
                $coreGrand = !empty($gfat['first_name']) ? $gfat['first_name'] : (function_exists('extractFirstName') ? extractFirstName($gfat['full_name']) : trim($gfat['full_name']));
            }
        }
    }
}

$tripartiteParts = array_filter([$coreFirst, $coreFather, $coreGrand]);
if (count($tripartiteParts) > 1) {
    $memberTripartiteName = trim(($personTitle ? $personTitle . ' ' : '') . implode(' ', $tripartiteParts));
} else {
    $memberTripartiteName = $current_member['full_name'];
}

function getMemberAvatarUrl($imgPath) {
    if (!empty($imgPath)) {
        $clean = ltrim($imgPath, '/');
        if (file_exists(__DIR__ . '/../' . $clean)) {
            return '../' . $clean;
        }
    }
    return 'default_user.svg';
}

// -------------------------------------------------------------
// FETCH UNCLES & COUSINS (مام و ئامۆزاکان)
// -------------------------------------------------------------
$father_id = (int)$current_member['parent_id'];
$grandfather_id = 0;
$uncles = [];
$cousins_by_uncle = [];

if ($father_id > 0) {
    $fRow = $conn->query("SELECT parent_id, full_name FROM members WHERE id = $father_id")->fetch_assoc();
    $grandfather_id = (int)($fRow['parent_id'] ?? 0);
    if ($grandfather_id > 0) {
        // Sons of grandfather (excluding father) = Paternal Uncles (مامەکان)
        $uRes = $conn->query("
            SELECT * FROM members 
            WHERE parent_id = $grandfather_id AND id != $father_id 
            ORDER BY id ASC
        ");
        if ($uRes) {
            while ($u = $uRes->fetch_assoc()) {
                $uncles[] = $u;
                $uId = (int)$u['id'];
                // Sons of each uncle = Cousins (ئامۆزاکان)
                $cRes = $conn->query("SELECT * FROM members WHERE parent_id = $uId ORDER BY id ASC");
                $cousins = [];
                if ($cRes) {
                    while ($c = $cRes->fetch_assoc()) {
                        $cousins[] = $c;
                    }
                }
                $cousins_by_uncle[$uId] = $cousins;
            }
        }
    }
}

// -------------------------------------------------------------
// FETCH CHILDREN & REQUESTS (منداڵەکان)
// -------------------------------------------------------------
// 1. Live confirmed children
$childrenRes = $conn->query("SELECT * FROM members WHERE parent_id = $member_id ORDER BY id ASC");
$confirmed_children = [];
if ($childrenRes) {
    while ($ch = $childrenRes->fetch_assoc()) {
        $confirmed_children[] = $ch;
    }
}

// 2. Pending & recent requests
$reqRes = $conn->query("SELECT * FROM member_requests WHERE member_id = $member_id ORDER BY id DESC");
$member_requests = [];
if ($reqRes) {
    while ($rq = $reqRes->fetch_assoc()) {
        $member_requests[] = $rq;
    }
}
?>
<!DOCTYPE html>
<html lang="ku" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>پۆرتاڵی ئەندامان - <?php echo htmlspecialchars($current_member['full_name']); ?></title>

    <!-- Google Fonts & FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --app-primary: #1a5653;
            --app-primary-dark: #0f3533;
            --app-primary-light: #2c7a76;
            --app-gold: #c5a059;
            --app-gold-light: #fef7e7;
            --app-gold-dark: #9a7630;
            --app-bg: #f1f5f9;
            --app-card: #ffffff;
            --app-text: #0f172a;
            --app-muted: #64748b;
            --app-border: #e2e8f0;
            --app-success: #10b981;
            --app-danger: #ef4444;
            --app-warning: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            font-family: 'Noto Sans Arabic', Tahoma, sans-serif;
            background: var(--app-bg);
            color: var(--app-text);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            padding-bottom: 90px;
        }

        /* Mobile App Container */
        .app-container {
            width: 100%;
            max-width: 540px;
            min-height: 100vh;
            background: #fff;
            position: relative;
            box-shadow: 0 0 40px rgba(0,0,0,0.06);
            display: flex;
            flex-direction: column;
        }

        /* App Top Bar */
        .app-header {
            background: linear-gradient(135deg, #1a5653 0%, #0d3835 100%);
            color: #fff;
            padding: 24px 20px 20px;
            border-radius: 0 0 28px 28px;
            box-shadow: 0 10px 25px rgba(26, 86, 83, 0.22);
            position: relative;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .user-greeting {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar-wrap {
            position: relative;
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #fff;
            padding: 2px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.25);
            flex-shrink: 0;
        }

        .user-avatar-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            display: block;
        }

        .avatar-cam-btn {
            position: absolute;
            bottom: -2px;
            left: -2px;
            width: 22px;
            height: 22px;
            background: var(--app-gold);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
            border: 2px solid #fff;
        }

        .user-name-title h2 {
            font-size: 1.1rem;
            font-weight: 800;
            line-height: 1.3;
        }

        .user-name-title p {
            font-size: 0.78rem;
            color: var(--app-gold-light);
            opacity: 0.95;
        }

        .btn-logout {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            border: none;
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .btn-logout:hover {
            background: rgba(239, 68, 68, 0.35);
        }

        /* Badges Strip */
        .member-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .badge-pill {
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(5px);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.76rem;
            font-weight: 700;
            color: #f7edd7;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(255,255,255,0.15);
        }

        /* Profile Completion Meter */
        .completion-card {
            background: #fff;
            margin: -10px 16px 16px;
            border-radius: 20px;
            padding: 16px 18px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            border: 1px solid var(--app-border);
            position: relative;
            z-index: 2;
            cursor: pointer;
            user-select: none;
            transition: all 0.2s ease;
        }

        .completion-card:hover {
            box-shadow: 0 10px 28px rgba(26, 86, 83, 0.12);
            border-color: #cbd5e1;
        }

        .completion-card:active {
            transform: scale(0.99);
        }

        .comp-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .comp-title {
            font-size: 0.86rem;
            font-weight: 800;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .comp-percent {
            font-size: 1.05rem;
            font-weight: 900;
            color: <?php echo $totalPoints >= 80 ? 'var(--app-success)' : ($totalPoints >= 50 ? 'var(--app-warning)' : 'var(--app-danger)'); ?>;
        }

        .progress-bar-wrap {
            width: 100%;
            height: 9px;
            background: #e2e8f0;
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .progress-bar-fill {
            height: 100%;
            width: <?php echo $totalPoints; ?>%;
            background: linear-gradient(90deg, #10b981 0%, #1a5653 100%);
            border-radius: 20px;
            transition: width 0.5s ease;
        }

        .missing-tags-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .missing-tag {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fee2e2;
            padding: 3px 9px;
            border-radius: 12px;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .complete-tag {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #d1fae5;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.76rem;
            font-weight: 700;
        }

        /* Toggle documents bar inside completion card */
        .docs-toggle-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px dashed #e2e8f0;
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--app-primary);
        }

        .docs-toggle-info {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .docs-toggle-pill {
            width: 22px;
            height: 22px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            color: var(--app-primary);
            transition: transform 0.2s ease, background 0.2s;
        }

        .completion-card:hover .docs-toggle-pill {
            background: #e2e8f0;
        }

        .badge-done {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-right: 6px;
        }

        .btn-hide-docs {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 0.74rem;
            font-weight: 700;
            font-family: inherit;
            padding: 4px 10px;
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
        }

        .btn-hide-docs:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* Nav Tab Switcher */
        .app-tabs {
            display: flex;
            background: #e2e8f0;
            border-radius: 16px;
            padding: 4px;
            margin: 0 16px 16px;
            gap: 4px;
        }

        .app-tab-btn {
            flex: 1;
            padding: 10px 4px;
            background: transparent;
            border: none;
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.84rem;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.25s ease;
        }

        .app-tab-btn.active {
            background: #fff;
            color: var(--app-primary);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        /* Content Sections */
        .tab-content {
            display: none;
            padding: 0 16px;
            animation: fadeIn 0.25s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Section Cards */
        .card-box {
            background: #fff;
            border-radius: 20px;
            padding: 18px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.04);
            border: 1px solid var(--app-border);
            margin-bottom: 16px;
        }

        .card-box-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
        }

        .card-box-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-box-title i {
            color: var(--app-primary);
        }

        /* Document Upload Slots */
        .doc-slots-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .doc-slot {
            background: #f8fafc;
            border: 1.5px dashed var(--app-border);
            border-radius: 16px;
            padding: 14px 10px;
            text-align: center;
            position: relative;
            transition: all 0.2s;
            cursor: pointer;
        }

        .doc-slot:hover {
            border-color: var(--app-primary);
            background: #f1f5f9;
        }

        .doc-slot.uploaded {
            border-style: solid;
            border-color: #10b981;
            background: #f0fdf4;
        }

        .doc-slot-icon {
            font-size: 1.6rem;
            color: var(--app-muted);
            margin-bottom: 6px;
        }

        .doc-slot.uploaded .doc-slot-icon {
            color: #10b981;
        }

        .doc-slot-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 4px;
        }

        .doc-slot-status {
            font-size: 0.7rem;
            font-weight: 700;
        }

        .status-ok { color: #059669; }
        .status-miss { color: #dc2626; }

        .hidden-file-input {
            display: none;
        }

        /* Form Inputs */
        .field-row {
            margin-bottom: 14px;
        }

        .field-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 6px;
        }

        .field-input {
            width: 100%;
            height: 44px;
            border: 1.5px solid var(--app-border);
            border-radius: 12px;
            padding: 0 14px;
            font-family: inherit;
            font-size: 0.9rem;
            background: #f8fafc;
            color: #0f172a;
            outline: none;
            transition: all 0.2s;
        }

        .field-input:focus {
            border-color: var(--app-primary);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(26, 86, 83, 0.1);
        }

        .readonly-input {
            background: #f1f5f9 !important;
            color: #475569 !important;
            font-weight: 700;
            border-style: dashed;
            cursor: default;
        }

        .field-select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231a5653' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: left 12px center;
            background-size: 16px;
            padding-left: 36px !important;
            cursor: pointer;
        }

        /* VIP Photo Card - "وێنەی کەسی گرنگترینە" */
        .vip-photo-card {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 18px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            transition: all 0.25s ease;
        }

        .vip-photo-card.has-photo {
            background: #f0fdf4;
            border-color: #86efac;
            box-shadow: 0 4px 16px rgba(16, 185, 129, 0.1);
        }

        .vip-photo-card.missing-photo {
            background: #fff1f2;
            border-color: #fca5a5;
            box-shadow: 0 4px 16px rgba(239, 68, 68, 0.15);
        }

        .vip-photo-preview-wrap {
            position: relative;
            width: 84px;
            height: 84px;
            flex-shrink: 0;
        }

        .vip-photo-img {
            width: 100%;
            height: 100%;
            border-radius: 20px;
            object-fit: cover;
            border: 3px solid #fff;
            box-shadow: 0 4px 14px rgba(0,0,0,0.12);
            background: #e2e8f0;
            display: block;
        }

        .vip-photo-cam-badge {
            position: absolute;
            bottom: -4px;
            left: -4px;
            width: 28px;
            height: 28px;
            background: #c5a059;
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            cursor: pointer;
            border: 2px solid #fff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.25);
        }

        .vip-photo-info {
            flex: 1;
        }

        .vip-photo-title {
            font-size: 0.9rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .vip-photo-desc {
            font-size: 0.76rem;
            margin: 0 0 8px 0;
            line-height: 1.4;
        }

        .btn-vip-upload {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--app-primary);
            color: #fff;
            padding: 6px 14px;
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-vip-upload:hover {
            background: var(--app-primary-light);
        }

        .form-section-banner {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 12px 14px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .btn-save-profile {
            width: 100%;
            height: 46px;
            background: var(--app-primary);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
            box-shadow: 0 6px 18px rgba(26, 86, 83, 0.25);
            transition: all 0.2s;
        }

        .btn-save-profile:active {
            transform: scale(0.98);
        }

        /* Uncles & Cousins Styling */
        .uncle-card {
            background: #f8fafc;
            border: 1px solid var(--app-border);
            border-radius: 16px;
            padding: 14px;
            margin-bottom: 12px;
        }

        .uncle-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .uncle-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .uncle-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--app-gold);
            background: #fff;
        }

        .uncle-name-block h4 {
            font-size: 0.92rem;
            font-weight: 800;
            color: #0f172a;
        }

        .uncle-name-block span {
            font-size: 0.72rem;
            color: var(--app-gold-dark);
            font-weight: 700;
        }

        .cousins-list {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #cbd5e1;
        }

        .cousins-title {
            font-size: 0.74rem;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .cousin-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 4px 10px;
            font-size: 0.78rem;
            font-weight: 600;
            color: #334155;
            margin: 3px;
        }

        .cousin-chip i {
            color: var(--app-primary);
            font-size: 0.7rem;
        }

        /* Children Tab */
        .child-card {
            background: #f8fafc;
            border: 1.5px solid #d1fae5;
            border-radius: 16px;
            padding: 12px 14px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .btn-edit-child-pill {
            background: #f0fdf4;
            border: 1.5px solid #86efac;
            color: #166534;
            font-family: inherit;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-edit-child-pill:hover {
            background: #dcfce7;
            border-color: #22c55e;
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(34, 197, 94, 0.2);
        }

        .btn-edit-req-pill {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #b45309;
            font-family: inherit;
            font-size: 0.74rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-cancel-req-pill {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            color: #b91c1c;
            font-family: inherit;
            font-size: 0.74rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .request-card {
            background: #fffbeb;
            border: 1.5px dashed #f59e0b;
            border-radius: 16px;
            padding: 12px 14px;
            margin-bottom: 10px;
        }

        .btn-add-child {
            width: 100%;
            height: 48px;
            background: linear-gradient(135deg, #c5a059 0%, #a68138 100%);
            color: #fff;
            border: none;
            border-radius: 16px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 16px;
            box-shadow: 0 8px 20px rgba(197, 160, 89, 0.3);
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .modal-box {
            background: #fff;
            width: 100%;
            max-width: 440px;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            animation: modalSlide 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalSlide {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .modal-header {
            background: var(--app-primary);
            color: #fff;
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1rem;
            font-weight: 800;
        }

        .modal-close-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: #fff;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-body {
            padding: 20px;
        }

        /* Toast Notification */
        .toast-msg {
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #0f172a;
            color: #fff;
            padding: 12px 22px;
            border-radius: 30px;
            font-size: 0.88rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 10000;
            white-space: nowrap;
        }

        .toast-msg.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        /* Bottom Fixed Navigation Bar (App Bar) */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 540px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border-top: 1px solid var(--app-border);
            display: flex;
            justify-content: space-around;
            padding: 8px 12px 10px;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.06);
            z-index: 100;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 12px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            background: transparent;
        }

        .nav-item i {
            font-size: 1.25rem;
        }

        .nav-item.active {
            color: var(--app-primary);
        }
    </style>
</head>
<body>

    <div class="app-container">

        <!-- App Header -->
        <div class="app-header">
            <div class="header-top">
                <div class="user-greeting">
                    <div class="user-avatar-wrap">
                        <img src="<?php echo htmlspecialchars(getMemberAvatarUrl($current_member['profile_image'] ?? '')); ?>" 
                             alt="Avatar" class="user-avatar-img" id="headerAvatar">
                        <label for="avatar_upload" class="avatar-cam-btn" title="گۆڕینی وێنەی کەسی">
                            <i class="fas fa-camera"></i>
                        </label>
                        <input type="file" id="avatar_upload" class="hidden-file-input" accept="image/*" onchange="uploadSingleDoc('وێنەی کەسی', this)">
                    </div>
                    <div class="user-name-title">
                        <h2><?php echo htmlspecialchars($memberTripartiteName); ?></h2>
                        <p><?php echo htmlspecialchars('باوکی: ' . ($current_member['father_full_name'] ?: ($coreFather ?: 'نادیار'))); ?></p>
                    </div>
                </div>

                <a href="logout.php" class="btn-logout" title="دەرچوون">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>

            <div class="member-badges">
                <div class="badge-pill">
                    <i class="fas fa-landmark"></i> <?php echo htmlspecialchars($cleanFamily); ?>
                </div>
                <div class="badge-pill">
                    <i class="fas fa-hashtag"></i> کۆد: #<?php echo htmlspecialchars($current_member['member_code'] ?: $current_member['id']); ?>
                </div>
                <div class="badge-pill">
                    <i class="fas fa-check-circle" style="color: #10b981;"></i> ئەندامی پەسەندکراو
                </div>
            </div>
        </div>

        <!-- Completion Meter (Clickable to Toggle Documents) -->
        <div class="completion-card" onclick="toggleDocumentsPanel()" title="کلیک بکە بۆ پیشاندان یان شاردنەوەی بەڵگەنامەکان">
            <div class="comp-header">
                <div class="comp-title">
                    <i class="fas fa-shield-alt" style="color: var(--app-primary);"></i> تەواوکاری پەڕگەی کەسی
                </div>
                <div class="comp-percent"><?php echo $totalPoints; ?>%</div>
            </div>
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill"></div>
            </div>
            
            <?php if (!empty($missingItems)): ?>
                <div class="missing-tags-wrap">
                    <span style="font-size: 0.72rem; color: #64748b; margin-left: 4px;">پێویستە پڕبکرێتەوە:</span>
                    <?php foreach($missingItems as $item): ?>
                        <div class="missing-tag">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($item); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="complete-tag">
                    <i class="fas fa-check-double"></i> پیرۆزە! سەرجەم بەڵگەنامە و زانیارییەکانت بە تەواوی تۆمارکراون.
                </div>
            <?php endif; ?>

            <!-- Interactive Toggle Hint -->
            <div class="docs-toggle-bar">
                <div class="docs-toggle-info">
                    <i class="fas fa-folder-open"></i>
                    <span id="docsToggleText">
                        <?php echo $isDocsCompleted ? 'بەڵگەنامەکان پڕکراونەتەوە (کلیک بکە بۆ بینین)' : 'بەڵگەنامەکان کراوەن (کلیک بکە بۆ شاردنەوە)'; ?>
                    </span>
                </div>
                <span class="docs-toggle-pill">
                    <i class="fas <?php echo $isDocsCompleted ? 'fa-chevron-down' : 'fa-chevron-up'; ?>" id="docsChevron"></i>
                </span>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="app-tabs">
            <button class="app-tab-btn active" onclick="switchAppTab('profile')">
                <i class="fas fa-user-circle"></i> پرۆفایل و بەڵگەنامە
            </button>
            <button class="app-tab-btn" onclick="switchAppTab('kinship')">
                <i class="fas fa-users"></i> مام و ئامۆزاکان
            </button>
            <button class="app-tab-btn" onclick="switchAppTab('children')">
                <i class="fas fa-baby"></i> منداڵەکانم
            </button>
        </div>

        <!-- ============================================================== -->
        <!-- TAB 1: PROFILE & DOCUMENTS                                     -->
        <!-- ============================================================== -->
        <div id="tab-profile" class="tab-content active">

            <!-- Documents Upload Slots (Hidden if completed, toggled via completion card) -->
            <div class="card-box" id="documentsPanel" style="<?php echo $isDocsCompleted ? 'display: none;' : ''; ?>">
                <div class="card-box-header">
                    <div class="card-box-title">
                        <i class="fas fa-folder-open"></i> بەڵگەنامە فەرمییەکان
                        <?php if ($isDocsCompleted): ?>
                            <span class="badge-done"><i class="fas fa-check-circle"></i> پڕکراوەتەوە</span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 0.75rem; color: #64748b;">تەنها وێنە یان PDF</span>
                        <button type="button" class="btn-hide-docs" onclick="toggleDocumentsPanel()" title="داخستنی پانێڵ">
                            <i class="fas fa-chevron-up"></i> شاردنەوە
                        </button>
                    </div>
                </div>

                <div class="doc-slots-grid">
                    
                    <!-- 1. National Card Front -->
                    <?php 
                    $docFront = $member_docs['کارتی نیشتمانی (دیوی پێشەوە)'] ?? ($member_docs['کارتی نیشتمانی'] ?? null); 
                    ?>
                    <label class="doc-slot <?php echo $docFront ? 'uploaded' : ''; ?>" for="file_front">
                        <div class="doc-slot-icon">
                            <i class="fas <?php echo $docFront ? 'fa-check-circle' : 'fa-id-card'; ?>"></i>
                        </div>
                        <div class="doc-slot-title">کارتی نیشتمانی (دیوی پێشەوە)</div>
                        <div class="doc-slot-status <?php echo $docFront ? 'status-ok' : 'status-miss'; ?>">
                            <?php echo $docFront ? '✓ بارکراوە (گۆڕین)' : '+ وێنە بگرە / باربکە'; ?>
                        </div>
                        <input type="file" id="file_front" class="hidden-file-input" accept="image/*,application/pdf" onchange="uploadSingleDoc('کارتی نیشتمانی (دیوی پێشەوە)', this)">
                    </label>

                    <!-- 2. National Card Back -->
                    <?php $docBack = $member_docs['کارتی نیشتمانی (دیوی دواوە)'] ?? null; ?>
                    <label class="doc-slot <?php echo $docBack ? 'uploaded' : ''; ?>" for="file_back">
                        <div class="doc-slot-icon">
                            <i class="fas <?php echo $docBack ? 'fa-check-circle' : 'fa-id-card-alt'; ?>"></i>
                        </div>
                        <div class="doc-slot-title">کارتی نیشتمانی (دیوی دواوە)</div>
                        <div class="doc-slot-status <?php echo $docBack ? 'status-ok' : 'status-miss'; ?>">
                            <?php echo $docBack ? '✓ بارکراوە (گۆڕین)' : '+ وێنە بگرە / باربکە'; ?>
                        </div>
                        <input type="file" id="file_back" class="hidden-file-input" accept="image/*,application/pdf" onchange="uploadSingleDoc('کارتی نیشتمانی (دیوی دواوە)', this)">
                    </label>

                    <!-- 3. Information Card -->
                    <?php $docInfo = $member_docs['کارتی زانیاری'] ?? null; ?>
                    <label class="doc-slot <?php echo $docInfo ? 'uploaded' : ''; ?>" for="file_info">
                        <div class="doc-slot-icon">
                            <i class="fas <?php echo $docInfo ? 'fa-check-circle' : 'fa-file-invoice'; ?>"></i>
                        </div>
                        <div class="doc-slot-title">کارتی زانیاری</div>
                        <div class="doc-slot-status <?php echo $docInfo ? 'status-ok' : 'status-miss'; ?>">
                            <?php echo $docInfo ? '✓ بارکراوە (گۆڕین)' : '+ وێنە بگرە / باربکە'; ?>
                        </div>
                        <input type="file" id="file_info" class="hidden-file-input" accept="image/*,application/pdf" onchange="uploadSingleDoc('کارتی زانیاری', this)">
                    </label>

                    <!-- 4. Civil Status / Passport (Optional) -->
                    <?php $docCivil = $member_docs['ناسنامە'] ?? null; ?>
                    <label class="doc-slot <?php echo $docCivil ? 'uploaded' : ''; ?>" for="file_civil">
                        <div class="doc-slot-icon">
                            <i class="fas <?php echo $docCivil ? 'fa-check-circle' : 'fa-passport'; ?>"></i>
                        </div>
                        <div class="doc-slot-title">ناسنامە / پاسپۆرت (ئارەزوومەندانە)</div>
                        <div class="doc-slot-status <?php echo $docCivil ? 'status-ok' : 'status-miss'; ?>">
                            <?php echo $docCivil ? '✓ بارکراوە (گۆڕین)' : '+ وێنە بگرە / باربکە'; ?>
                        </div>
                        <input type="file" id="file_civil" class="hidden-file-input" accept="image/*,application/pdf" onchange="uploadSingleDoc('ناسنامە', this)">
                    </label>

                </div>
            </div>

            <!-- Personal Data Form (Official Format) -->
            <div class="card-box">
                <div class="card-box-header">
                    <div class="card-box-title">
                        <i class="fas fa-file-signature" style="color: var(--app-primary);"></i> زانیارییە کەسییەکان (پەڕگەی فەرمی)
                    </div>
                    <span class="badge-done"><i class="fas fa-check-circle"></i> فۆڕمی پەسەندکردن</span>
                </div>

                <!-- VIP Photo Section - وێنەی کەسی گرنگترینە -->
                <div class="vip-photo-card <?php echo $hasProfilePhoto ? 'has-photo' : 'missing-photo'; ?>">
                    <div class="vip-photo-preview-wrap">
                        <img src="<?php echo htmlspecialchars(getMemberAvatarUrl($current_member['profile_image'] ?? '')); ?>" 
                             alt="وێنەی کەسی" class="vip-photo-img" id="vipPhotoPreview">
                        <label for="vip_photo_input" class="vip-photo-cam-badge" title="گۆڕینی وێنەی کەسی">
                            <i class="fas fa-camera"></i>
                        </label>
                    </div>
                    <div class="vip-photo-info">
                        <div class="vip-photo-title">
                            <i class="fas fa-star" style="color: #f59e0b;"></i> وێنەی کەسی (مەرجی هەرە سەرەکی)
                        </div>
                        <p class="vip-photo-desc">
                            <?php if ($hasProfilePhoto): ?>
                                <span style="color: #059669; font-weight: 700;">
                                    <i class="fas fa-check-circle"></i> وێنەی کەسیت تۆمارکراوە، دەتوانیت بیگۆڕیت
                                </span>
                            <?php else: ?>
                                <span style="color: #dc2626; font-weight: 800;">
                                    <i class="fas fa-exclamation-triangle"></i> زۆر گرنگ: تکایە وێنەی کەسی خۆت دابنێ!
                                </span>
                            <?php endif; ?>
                        </p>
                        <label for="vip_photo_input" class="btn-vip-upload">
                            <i class="fas fa-camera"></i> <?php echo $hasProfilePhoto ? 'گۆڕینی وێنەی کەسی' : 'بارکردنی وێنەی کەسی ئێستا'; ?>
                        </label>
                        <input type="file" id="vip_photo_input" class="hidden-file-input" accept="image/*" onchange="uploadSingleDoc('وێنەی کەسی', this)">
                    </div>
                </div>

                <form id="profileForm" onsubmit="saveProfileData(event)">
                    
                    <!-- 1. Full Name (ناوی سیانی: ناوی خۆی، باوک، باپیر) -->
                    <div class="field-row">
                        <label class="field-label">
                            <i class="fas fa-user-tag" style="color: var(--app-primary);"></i> ناوی سیانی (ناو، باوک، باپیر):
                        </label>
                        <input type="text" class="field-input readonly-input" 
                               value="<?php echo htmlspecialchars($memberTripartiteName); ?>" readonly>
                        <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px;">
                            <span style="font-size: 0.72rem; color: #334155; background: #f1f5f9; padding: 2px 8px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <strong>ناو:</strong> <?php echo htmlspecialchars($coreFirst); ?>
                            </span>
                            <?php if (!empty($coreFather)): ?>
                            <span style="font-size: 0.72rem; color: #334155; background: #f1f5f9; padding: 2px 8px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <strong>باوک:</strong> <?php echo htmlspecialchars($coreFather); ?>
                            </span>
                            <?php endif; ?>
                            <?php if (!empty($coreGrand)): ?>
                            <span style="font-size: 0.72rem; color: #334155; background: #f1f5f9; padding: 2px 8px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <strong>باپیر:</strong> <?php echo htmlspecialchars($coreGrand); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 2. Family (بنەماڵە) -->
                    <div class="field-row">
                        <label class="field-label">
                            <i class="fas fa-landmark" style="color: var(--app-primary);"></i> بنەماڵە:
                        </label>
                        <input type="text" class="field-input readonly-input" 
                               value="<?php echo htmlspecialchars($cleanFamily); ?>" readonly>
                    </div>

                    <!-- 3. Education Level (ئاستی خوێندن) -->
                    <div class="field-row">
                        <label class="field-label" for="f_education">
                            <i class="fas fa-graduation-cap" style="color: var(--app-primary);"></i> ئاستی خوێندن:
                        </label>
                        <?php 
                        $eduVal = trim($current_member['education'] ?: ($current_member['education_level'] ?: ''));
                        $eduOptions = ['نەخوێندەوار', 'سەرەتایی', 'ناوەندی', 'ئامادەیی', 'دبلۆم', 'بەکالۆریۆس', 'ماستەر', 'دکتۆرا'];
                        ?>
                        <select name="education" id="f_education" class="field-input field-select">
                            <option value="">-- ئاستی خوێندنت هەڵبژێرە --</option>
                            <?php foreach($eduOptions as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($eduVal === $opt) ? 'selected' : ''; ?>>
                                    <?php echo $opt; ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if (!empty($eduVal) && !in_array($eduVal, $eduOptions)): ?>
                                <option value="<?php echo htmlspecialchars($eduVal); ?>" selected><?php echo htmlspecialchars($eduVal); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- 4. Address (ناونیشان) -->
                    <div class="field-row">
                        <label class="field-label" for="f_address">
                            <i class="fas fa-map-marker-alt" style="color: var(--app-primary);"></i> ناونیشان (شوێنی نیشتەجێبوونی ئێستا):
                        </label>
                        <input type="text" name="address" id="f_address" class="field-input" 
                               value="<?php echo htmlspecialchars($current_member['address'] ?: ''); ?>" 
                               placeholder="شار، گەڕەک، کۆڵان، گوند...">
                    </div>

                    <!-- 5. Birth Place & Date (بەروار و شوێنی لە دایک بوون) -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="field-row">
                            <label class="field-label" for="f_birth_place">
                                <i class="fas fa-city" style="color: var(--app-primary);"></i> شوێنی لەدایکبوون:
                            </label>
                            <input type="text" name="birth_place" id="f_birth_place" class="field-input" 
                                   value="<?php echo htmlspecialchars($current_member['birth_place'] ?: ''); ?>" 
                                   placeholder="شار / گوند...">
                        </div>
                        <div class="field-row">
                            <label class="field-label" for="f_birth_date">
                                <i class="fas fa-calendar-alt" style="color: var(--app-primary);"></i> بەرواری لەدایکبوون:
                            </label>
                            <input type="date" name="birth_date" id="f_birth_date" class="field-input" 
                                   value="<?php echo htmlspecialchars($current_member['birth_date'] ?: ''); ?>">
                        </div>
                    </div>

                    <!-- 6. Languages (زمان) -->
                    <div class="field-row">
                        <label class="field-label" for="f_languages">
                            <i class="fas fa-language" style="color: var(--app-primary);"></i> زمانەکان:
                        </label>
                        <input type="text" name="languages" id="f_languages" class="field-input" 
                               value="<?php echo htmlspecialchars($current_member['languages'] ?: ''); ?>" 
                               placeholder="نموونە: کوردی، عەرەبی، ئینگلیزی...">
                    </div>

                    <!-- 7. Membership Date (بەرواری بە ئەندام بوون) -->
                    <div class="field-row">
                        <label class="field-label" for="f_membership_date">
                            <i class="fas fa-calendar-check" style="color: var(--app-primary);"></i> بەرواری بە ئەندام بوون:
                        </label>
                        <input type="date" name="membership_date" id="f_membership_date" class="field-input" 
                               value="<?php echo htmlspecialchars($current_member['membership_date'] ?: ''); ?>">
                    </div>

                    <!-- 8. Profession (پیشە) -->
                    <div class="field-row">
                        <label class="field-label" for="f_profession">
                            <i class="fas fa-briefcase" style="color: var(--app-primary);"></i> پیشە:
                        </label>
                        <input type="text" name="profession" id="f_profession" class="field-input" 
                               value="<?php echo htmlspecialchars($current_member['profession'] ?: ($current_member['job'] ?: '')); ?>" 
                               placeholder="پیشەی ئێستات (فەرمانبەر، مامۆستا، کاسب، پزیشک...)">
                    </div>

                    <!-- 9. Blood Group (گرووپی خوێن) -->
                    <div class="field-row">
                        <label class="field-label" for="f_blood_group">
                            <i class="fas fa-tint" style="color: #ef4444;"></i> گرووپی خوێن:
                        </label>
                        <?php 
                        $bgVal = trim($current_member['blood_group'] ?: '');
                        $bgOptions = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                        ?>
                        <select name="blood_group" id="f_blood_group" class="field-input field-select">
                            <option value="">-- گرووپی خوێنت هەڵبژێرە --</option>
                            <?php foreach($bgOptions as $bg): ?>
                                <option value="<?php echo $bg; ?>" <?php echo ($bgVal === $bg) ? 'selected' : ''; ?>>
                                    <?php echo $bg; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 10. Phone (ژمارەی مۆبایل) -->
                    <div class="field-row">
                        <label class="field-label" for="f_phone">
                            <i class="fas fa-phone-alt" style="color: var(--app-primary);"></i> ژمارەی مۆبایل:
                        </label>
                        <input type="tel" name="phone" id="f_phone" class="field-input" dir="ltr" 
                               value="<?php echo htmlspecialchars($current_member['phone'] ?: ''); ?>" 
                               placeholder="0770xxxxxxx">
                    </div>

                    <!-- 11. Security Code & ID Card (کۆدی ئاسایش و کارتی نیشتمانی) -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="field-row">
                            <label class="field-label" for="f_security_code">
                                <i class="fas fa-lock" style="color: var(--app-gold);"></i> کۆدی ئاسایش:
                            </label>
                            <input type="text" name="security_code" id="f_security_code" class="field-input" 
                                   value="<?php echo htmlspecialchars($current_member['security_code'] ?: ''); ?>" 
                                   placeholder="کۆدی ئاسایش داخل بکە...">
                        </div>
                        <div class="field-row">
                            <label class="field-label" for="f_id_card_number">
                                <i class="fas fa-id-card" style="color: var(--app-primary);"></i> کارتی نیشتمانی:
                            </label>
                            <input type="text" name="id_card_number" id="f_id_card_number" class="field-input" 
                                   value="<?php echo htmlspecialchars($current_member['id_card_number'] ?: ($current_member['civil_id'] ?: '')); ?>" 
                                   placeholder="ژمارەی کارتی نیشتمانی...">
                        </div>
                    </div>

                    <button type="submit" class="btn-save-profile" id="btnSaveProfile">
                        <i class="fas fa-save"></i> پاشەکەوتکردنی هەموو گۆڕانکارییەکان
                    </button>
                </form>
            </div>

        </div>

        <!-- ============================================================== -->
        <!-- TAB 2: UNCLES & COUSINS (مام و ئامۆزاکان)                       -->
        <!-- ============================================================== -->
        <div id="tab-kinship" class="tab-content">
            <div class="card-box">
                <div class="card-box-header">
                    <div class="card-box-title">
                        <i class="fas fa-sitemap"></i> مامەکانی بەڕێزت و ئامۆزاکانت
                    </div>
                </div>
                <p style="font-size: 0.82rem; color: #64748b; margin-bottom: 16px; line-height: 1.5;">
                    ئەم بەشە بەپێی درەختی ڕەچەڵەک، تەنها مامەکانت (براکانی باوکت) و ئامۆزاکانت (وەچەی مامەکانت) پیشان دەدات:
                </p>

                <?php if (empty($uncles)): ?>
                    <div style="text-align: center; padding: 40px 20px; color: #94a3b8; background: #f8fafc; border-radius: 16px;">
                        <i class="fas fa-user-friends" style="font-size: 2.2rem; margin-bottom: 10px; color: #cbd5e1;"></i>
                        <p style="font-weight: 700; font-size: 0.9rem;">هیچ مامێک لە درەختدا بۆ باوکت تۆمار نەکراوە.</p>
                        <p style="font-size: 0.78rem; margin-top: 4px;">ئەگەر باوکت برای هەیە، تکایە پەیوەندی بە بەڕێوەبەری بنەماڵەکەتەوە بکە بۆ بەستنەوەی نەسەبەکەیان.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($uncles as $u): ?>
                        <div class="uncle-card">
                            <div class="uncle-header">
                                <div class="uncle-info">
                                    <img src="<?php echo htmlspecialchars(getMemberAvatarUrl($u['profile_image'] ?? '')); ?>" 
                                         alt="Uncle" class="uncle-avatar">
                                    <div class="uncle-name-block">
                                        <h4><?php echo htmlspecialchars($u['full_name']); ?></h4>
                                        <span><i class="fas fa-award"></i> مامی بەڕێزت</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Cousins under this uncle -->
                            <?php 
                            $cList = $cousins_by_uncle[$u['id']] ?? []; 
                            ?>
                            <div class="cousins-list">
                                <div class="cousins-title">
                                    <i class="fas fa-user-friends"></i> ئامۆزاکانت (منداڵەکانی ئەم مامەت) (<?php echo count($cList); ?>):
                                </div>
                                <?php if (empty($cList)): ?>
                                    <span style="font-size: 0.75rem; color: #94a3b8; font-style: italic;">منداڵی تۆمارکراوی نییە لە سیستەمدا.</span>
                                <?php else: ?>
                                    <div>
                                        <?php foreach($cList as $cousin): ?>
                                            <div class="cousin-chip">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($cousin['full_name']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- TAB 3: MY CHILDREN & REQUESTS                                  -->
        <!-- ============================================================== -->
        <div id="tab-children" class="tab-content">
            
            <button class="btn-add-child" onclick="openAddChildModal()">
                <i class="fas fa-plus-circle"></i> داواکاری زیادکردنی منداڵی نوێ
            </button>

            <!-- Pending Requests Section -->
            <?php if (!empty($member_requests)): ?>
                <div class="card-box">
                    <div class="card-box-header">
                        <div class="card-box-title" style="color: #d97706;">
                            <i class="fas fa-hourglass-half" style="color: #f59e0b;"></i> داواکارییە نێردراوەکان بۆ لیژنە
                        </div>
                    </div>
                    <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 12px;">
                        ئەم داواکارییانە ڕاستەوخۆ ناخرێنە سەر درەختەکە تا بەڕێوەبەر لە پەیجی کۆنفێرم پەسەندیان نەکات:
                    </p>

                    <?php foreach($member_requests as $req): ?>
                        <div class="request-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                <div>
                                    <strong style="font-size: 0.95rem; color: #0f172a;"><?php echo htmlspecialchars($req['child_name']); ?></strong>
                                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                                        ڕەگەز: <strong><?php echo htmlspecialchars($req['gender']); ?></strong> 
                                        <?php if (!empty($req['birth_date'])): ?>
                                            | بەروار: <?php echo htmlspecialchars($req['birth_date']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($req['status'] === 'pending'): ?>
                                        <span style="background: #fef3c7; color: #b45309; padding: 3px 9px; border-radius: 12px; font-size: 0.74rem; font-weight: 800;">
                                            ⏳ چاوەڕوانکراو
                                        </span>
                                    <?php elseif ($req['status'] === 'approved'): ?>
                                        <span style="background: #d1fae5; color: #065f46; padding: 3px 9px; border-radius: 12px; font-size: 0.74rem; font-weight: 800;">
                                            ✓ پەسەندکرا
                                        </span>
                                    <?php else: ?>
                                        <span style="background: #fee2e2; color: #991b1b; padding: 3px 9px; border-radius: 12px; font-size: 0.74rem; font-weight: 800;">
                                            ✗ ڕەتکرایەوە
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($req['notes'])): ?>
                                <div style="font-size: 0.75rem; color: #78350f; background: rgba(245, 158, 11, 0.1); padding: 5px 8px; border-radius: 8px; margin-top: 6px;">
                                    تێبینی: <?php echo htmlspecialchars($req['notes']); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($req['status'] === 'pending'): ?>
                                <div style="display: flex; gap: 8px; margin-top: 8px; padding-top: 8px; border-top: 1px dashed rgba(245, 158, 11, 0.3);">
                                    <button type="button" class="btn-edit-req-pill" onclick='openEditRequestModal(<?php echo json_encode($req, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>)'>
                                        <i class="fas fa-edit"></i> دەستکاری داواکاری
                                    </button>
                                    <button type="button" class="btn-cancel-req-pill" onclick="cancelChildRequest(<?php echo (int)$req['id']; ?>)">
                                        <i class="fas fa-trash-alt"></i> هەڵوەشاندنەوە
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Approved Children Section -->
            <div class="card-box">
                <div class="card-box-header">
                    <div class="card-box-title">
                        <i class="fas fa-child"></i> منداڵە تۆمارکراوەکان لە نەسەبدا (<?php echo count($confirmed_children); ?>)
                    </div>
                </div>

                <?php if (empty($confirmed_children)): ?>
                    <div style="text-align: center; padding: 30px 10px; color: #94a3b8;">
                        <i class="fas fa-baby-carriage" style="font-size: 2rem; color: #cbd5e1; margin-bottom: 8px;"></i>
                        <p style="font-weight: 700; font-size: 0.88rem;">هیچ منداڵێکت لە درەختی نەسەبدا تۆمار نەکراوە.</p>
                        <p style="font-size: 0.76rem; margin-top: 4px;">دەتوانیت بە دوگمەی سەرەوە داواکاری زیادکردنی منداڵەکانت بنێریت.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($confirmed_children as $ch): 
                        $chAvatar = getMemberAvatarUrl($ch['profile_image'] ?? '');
                        $chBirth = !empty($ch['birth_date']) ? $ch['birth_date'] : '';
                        $chPhone = !empty($ch['phone']) ? $ch['phone'] : '';
                        $chEdu = !empty($ch['education']) ? $ch['education'] : (!empty($ch['education_level']) ? $ch['education_level'] : '');
                        $chJob = !empty($ch['profession']) ? $ch['profession'] : (!empty($ch['job']) ? $ch['job'] : '');
                        $chBlood = !empty($ch['blood_group']) ? $ch['blood_group'] : '';
                    ?>
                        <div class="child-card" style="flex-direction: column; align-items: stretch; gap: 10px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?php echo htmlspecialchars($chAvatar); ?>" 
                                         alt="<?php echo htmlspecialchars($ch['full_name']); ?>"
                                         id="child-avatar-display-<?php echo $ch['id']; ?>"
                                         style="width: 44px; height: 44px; border-radius: 12px; object-fit: cover; border: 2px solid #86efac; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.06);">
                                    <div>
                                        <div style="font-size: 0.94rem; font-weight: 800; color: #0f172a;">
                                            <?php echo htmlspecialchars($ch['full_name']); ?>
                                        </div>
                                        <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px; display: flex; flex-wrap: wrap; gap: 8px;">
                                            <span><i class="fas <?php echo ($ch['gender'] ?? '') === 'مێ' ? 'fa-female' : 'fa-child'; ?>" style="color: #059669;"></i> <?php echo htmlspecialchars($ch['gender'] ?? 'نێر'); ?></span>
                                            <span>کۆد: #<?php echo htmlspecialchars($ch['member_code'] ?: $ch['id']); ?></span>
                                            <?php if ($chBirth): ?>
                                                <span><i class="far fa-calendar-alt"></i> <?php echo htmlspecialchars($chBirth); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <span style="font-size: 0.72rem; color: #059669; font-weight: 800; background: #ecfdf5; padding: 4px 8px; border-radius: 10px; align-self: flex-start;">
                                    ✓ تۆمارکراوە
                                </span>
                            </div>

                            <?php if ($chEdu || $chJob || $chBlood || $chPhone): ?>
                                <div style="background: #f1f5f9; border-radius: 10px; padding: 6px 10px; font-size: 0.73rem; color: #475569; display: flex; flex-wrap: wrap; gap: 10px;">
                                    <?php if ($chEdu): ?><span><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($chEdu); ?></span><?php endif; ?>
                                    <?php if ($chJob): ?><span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($chJob); ?></span><?php endif; ?>
                                    <?php if ($chBlood): ?><span><i class="fas fa-tint" style="color: #ef4444;"></i> <?php echo htmlspecialchars($chBlood); ?></span><?php endif; ?>
                                    <?php if ($chPhone): ?><span><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($chPhone); ?></span><?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div style="display: flex; justify-content: flex-end; border-top: 1px dashed #e2e8f0; padding-top: 8px;">
                                <button type="button" class="btn-edit-child-pill" onclick='openEditChildModal(<?php echo json_encode($ch, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>)'>
                                    <i class="fas fa-user-edit"></i> دەستکاری زانیاری و فۆڕم
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

    </div>

    <!-- ADD CHILD MODAL -->
    <div class="modal-overlay" id="addChildModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> داواکاری زیادکردنی منداڵ</h3>
                <button type="button" class="modal-close-btn" onclick="closeAddChildModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addChildForm" onsubmit="submitChildRequest(event)">
                    <div class="field-row">
                        <label class="field-label" for="c_name">ناوی تەواوی منداڵ (تەنها ناوی خۆی):</label>
                        <input type="text" name="child_name" id="c_name" class="field-input" placeholder="بۆ نموونە: ئاراس" required>
                    </div>

                    <div class="field-row">
                        <label class="field-label">ڕەگەز:</label>
                        <div style="display: flex; gap: 14px; margin-top: 4px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 0.9rem; cursor: pointer;">
                                <input type="radio" name="gender" value="نێر" checked> نێر (کوڕ)
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 0.9rem; cursor: pointer;">
                                <input type="radio" name="gender" value="مێ"> مێ (کچ)
                            </label>
                        </div>
                    </div>

                    <div class="field-row">
                        <label class="field-label" for="c_birth">بەرواری لەدایکبوون (ئارەزوومەندانە):</label>
                        <input type="date" name="birth_date" id="c_birth" class="field-input">
                    </div>

                    <div class="field-row">
                        <label class="field-label" for="c_notes">تێبینی یان ژمارەی ناسنامە:</label>
                        <input type="text" name="notes" id="c_notes" class="field-input" placeholder="هەر زانیارییەکی تر...">
                    </div>

                    <div style="background: #fef3c7; border: 1px solid #fde68a; color: #92400e; padding: 10px 12px; border-radius: 12px; font-size: 0.76rem; line-height: 1.5; margin-bottom: 16px;">
                        <i class="fas fa-info-circle"></i> <strong>تێبینی گرنگ:</strong> منداڵەکە ڕاستەوخۆ ناچێتە ناو درەختی فەرمی تا لەلایەن لیژنەی باڵای ئەنسابەوە لە پەیجی کۆنفێرم پەسەند دەکرێت.
                    </div>

                    <button type="submit" class="btn-save-profile" style="background: #c5a059;" id="btnSubmitChild">
                        <i class="fas fa-paper-plane"></i> ناردنی داواکاری بۆ لیژنە
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT CHILD MODAL (Father editing confirmed child form) -->
    <div class="modal-overlay" id="editChildModal">
        <div class="modal-box" style="max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> دەستکاری زانیارییەکانی منداڵ</h3>
                <button type="button" class="modal-close-btn" onclick="closeEditChildModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Child Photo Upload Card -->
                <div style="background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 16px; padding: 12px; display: flex; align-items: center; gap: 14px; margin-bottom: 16px;">
                    <div style="position: relative; width: 68px; height: 68px; flex-shrink: 0;">
                        <img id="edit_child_avatar_preview" src="default_user.svg" alt="وێنەی منداڵ" style="width: 100%; height: 100%; border-radius: 16px; object-fit: cover; border: 2px solid #fff; box-shadow: 0 3px 10px rgba(0,0,0,0.1);">
                        <div style="position: absolute; bottom: -4px; left: -4px; width: 24px; height: 24px; background: #1a5653; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 0.84rem; font-weight: 800; color: #0f172a; margin-bottom: 3px;">وێنەی کەسی منداڵ</div>
                        <div style="font-size: 0.72rem; color: #64748b; margin-bottom: 6px;">دەتوانیت وێنەی تایبەت بە منداڵەکەت باربکەیت</div>
                        <button type="button" id="btnUploadChildPhoto" onclick="triggerChildPhotoUpload()" style="background: #fff; border: 1.5px solid #cbd5e1; color: #1e293b; padding: 5px 12px; border-radius: 8px; font-size: 0.76rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fas fa-upload"></i> هەڵبژاردنی وێنە
                        </button>
                        <input type="file" id="edit_child_photo_file" accept="image/*" class="hidden-file-input" onchange="uploadChildPhoto(this)">
                    </div>
                </div>

                <form id="editChildForm" onsubmit="submitEditChildProfile(event)">
                    <input type="hidden" name="child_id" id="edit_child_id">

                    <div class="field-row">
                        <label class="field-label" for="edit_child_first_name">ناوی کەسی منداڵ (تەنها ناوی خۆی):</label>
                        <input type="text" name="first_name" id="edit_child_first_name" class="field-input" required>
                    </div>

                    <div class="field-row">
                        <label class="field-label">ڕەگەز:</label>
                        <div style="display: flex; gap: 14px; margin-top: 4px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 0.9rem; cursor: pointer;">
                                <input type="radio" name="gender" id="edit_child_gender_male" value="نێر"> نێر (کوڕ)
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 0.9rem; cursor: pointer;">
                                <input type="radio" name="gender" id="edit_child_gender_female" value="مێ"> مێ (کچ)
                            </label>
                        </div>
                    </div>

                    <div class="field-row">
                        <label class="field-label" for="edit_child_education">ئاستی خوێندن:</label>
                        <select name="education" id="edit_child_education" class="field-input field-select">
                            <option value="">-- دیاری نەکراوە --</option>
                            <option value="نەخوێندەوار">نەخوێندەوار</option>
                            <option value="سەرەتایی">سەرەتایی</option>
                            <option value="ناوەندی">ناوەندی</option>
                            <option value="ئامادەیی">ئامادەیی</option>
                            <option value="پەیمانگا">پەیمانگا</option>
                            <option value="بەکالۆریۆس">بەکالۆریۆس</option>
                            <option value="ماستەر">ماستەر</option>
                            <option value="دکتۆرا">دکتۆرا</option>
                        </select>
                    </div>

                    <div class="field-row">
                        <label class="field-label" for="edit_child_profession">پیشە / کار:</label>
                        <input type="text" name="profession" id="edit_child_profession" class="field-input" placeholder="بۆ نموونە: قوتابی، فەرمانبەر...">
                    </div>

                    <div class="field-row">
                        <label class="field-label" for="edit_child_blood">گرووپی خوێن:</label>
                        <select name="blood_group" id="edit_child_blood" class="field-input field-select">
                            <option value="">-- دیاری نەکراوە --</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="field-row">
                            <label class="field-label" for="edit_child_birth_date">بەرواری لەدایکبوون:</label>
                            <input type="date" name="birth_date" id="edit_child_birth_date" class="field-input">
                        </div>
                        <div class="field-row">
                            <label class="field-label" for="edit_child_birth_place">شوێنی لەدایکبوون:</label>
                            <input type="text" name="birth_place" id="edit_child_birth_place" class="field-input" placeholder="شار / ناوچە">
                        </div>
                    </div>

                    <div class="field-row">
                        <label class="field-label" for="edit_child_address">ناونیشانی نیشتەجێبوون:</label>
                        <input type="text" name="address" id="edit_child_address" class="field-input" placeholder="شار، گەڕەک...">
                    </div>

                    <div class="field-row">
                        <label class="field-label" for="edit_child_phone">ژمارەی مۆبایل:</label>
                        <input type="tel" name="phone" id="edit_child_phone" class="field-input" placeholder="07xxxxxxxxx" dir="ltr" style="text-align: right;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="field-row">
                            <label class="field-label" for="edit_child_id_card">ژ. کارتی نیشتمانی / ناسنامە:</label>
                            <input type="text" name="id_card_number" id="edit_child_id_card" class="field-input" placeholder="ژمارەی ناسنامە">
                        </div>
                        <div class="field-row">
                            <label class="field-label" for="edit_child_sec_code">کۆدی ئاسایش:</label>
                            <input type="text" name="security_code" id="edit_child_sec_code" class="field-input" placeholder="کۆدی بەڵگەنامە">
                        </div>
                    </div>

                    <button type="submit" class="btn-save-profile" id="btnSaveChildProfile">
                        <i class="fas fa-save"></i> پاشەکەوتکردنی زانیارییەکانی منداڵ
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT REQUEST MODAL (Editing pending child request) -->
    <div class="modal-overlay" id="editRequestModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> دەستکاری داواکاری نێردراو</h3>
                <button type="button" class="modal-close-btn" onclick="closeEditRequestModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editRequestForm" onsubmit="submitEditRequest(event)">
                    <input type="hidden" name="request_id" id="edit_req_id">

                    <div class="field-row">
                        <label class="field-label" for="edit_req_child_name">ناوی تەواوی منداڵ (تەنها ناوی خۆی):</label>
                        <input type="text" name="child_name" id="edit_req_child_name" class="field-input" required>
                    </div>

                    <div class="field-row">
                        <label class="field-label">ڕەگەز:</label>
                        <div style="display: flex; gap: 14px; margin-top: 4px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 0.9rem; cursor: pointer;">
                                <input type="radio" name="gender" id="edit_req_gender_male" value="نێر"> نێر (کوڕ)
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: 700; font-size: 0.9rem; cursor: pointer;">
                                <input type="radio" name="gender" id="edit_req_gender_female" value="مێ"> مێ (کچ)
                            </label>
                        </div>
                    </div>

                    <div class="field-row">
                        <label class="field-label" for="edit_req_birth_date">بەرواری لەدایکبوون:</label>
                        <input type="date" name="birth_date" id="edit_req_birth_date" class="field-input">
                    </div>

                    <div class="field-row">
                        <label class="field-label" for="edit_req_notes">تێبینی یان ژمارەی ناسنامە:</label>
                        <input type="text" name="notes" id="edit_req_notes" class="field-input">
                    </div>

                    <button type="submit" class="btn-save-profile" style="background: #c5a059;" id="btnUpdateReq">
                        <i class="fas fa-save"></i> پاشەکەوتکردنی گۆڕانکاری داواکاری
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Notification Box -->
    <div class="toast-msg" id="toastMsg">
        <i class="fas fa-check-circle" style="color: #10b981;"></i>
        <span id="toastText">کردارەکە بە سەرکەوتوویی ئەنجامدرا</span>
    </div>

    <!-- Bottom Navigation Bar (Dock) -->
    <nav class="bottom-nav">
        <button class="nav-item active" id="dock-profile" onclick="switchAppTab('profile')">
            <i class="fas fa-id-card"></i>
            <span>پرۆفایلەکەم</span>
        </button>
        <button class="nav-item" id="dock-kinship" onclick="switchAppTab('kinship')">
            <i class="fas fa-users"></i>
            <span>مام و ئامۆزا</span>
        </button>
        <button class="nav-item" id="dock-children" onclick="switchAppTab('children')">
            <i class="fas fa-baby"></i>
            <span>منداڵەکانم</span>
        </button>
    </nav>

    <!-- App JavaScript -->
    <script>
        function switchAppTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.app-tab-btn').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));

            const targetTab = document.getElementById('tab-' + tabName);
            if (targetTab) targetTab.classList.add('active');

            const btns = document.querySelectorAll('.app-tab-btn');
            if (tabName === 'profile') btns[0].classList.add('active');
            if (tabName === 'kinship') btns[1].classList.add('active');
            if (tabName === 'children') btns[2].classList.add('active');

            const dockItem = document.getElementById('dock-' + tabName);
            if (dockItem) dockItem.classList.add('active');

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Toggle Documents Upload Panel
        function toggleDocumentsPanel() {
            // Ensure we are viewing the profile tab
            const activeTab = document.querySelector('.tab-content.active');
            if (!activeTab || activeTab.id !== 'tab-profile') {
                switchAppTab('profile');
            }

            const panel = document.getElementById('documentsPanel');
            const chevron = document.getElementById('docsChevron');
            const toggleText = document.getElementById('docsToggleText');
            if (!panel) return;

            const isHidden = (panel.style.display === 'none' || window.getComputedStyle(panel).display === 'none');

            if (isHidden) {
                panel.style.display = 'block';
                panel.style.animation = 'fadeIn 0.25s ease';
                if (chevron) chevron.className = 'fas fa-chevron-up';
                if (toggleText) toggleText.textContent = 'بەڵگەنامەکان کراوەن (کلیک بکە بۆ شاردنەوە)';
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                panel.style.display = 'none';
                if (chevron) chevron.className = 'fas fa-chevron-down';
                if (toggleText) toggleText.textContent = 'بەڵگەنامەکان پڕکراونەتەوە (کلیک بکە بۆ بینین)';
            }
        }

        function showToast(text, isError = false) {
            const toast = document.getElementById('toastMsg');
            const toastText = document.getElementById('toastText');
            toastText.textContent = text;
            if (isError) {
                toast.style.background = '#dc2626';
            } else {
                toast.style.background = '#0f172a';
            }
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3500);
        }

        // Upload single document
        function uploadSingleDoc(docType, inputElement) {
            if (!inputElement.files || !inputElement.files[0]) return;

            const file = inputElement.files[0];
            const formData = new FormData();
            formData.append('action', 'upload_document');
            formData.append('doc_type', docType);
            formData.append('file', file);

            showToast('چاوەڕوانبە، فایلەکە باردەکرێت...');

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'بەڵگەنامەکە بە سەرکەوتوویی بارکرا');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1200);
                } else {
                    showToast(data.message || 'هەڵەیەک ڕوویدا', true);
                }
            })
            .catch(err => {
                console.error(err);
                showToast('هەڵەی پەیوەندی بە سێرڤەر', true);
            });
        }

        // Save profile personal data
        function saveProfileData(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveProfile');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> پاشەکەوت دەکرێت...';

            const form = document.getElementById('profileForm');
            const formData = new FormData(form);
            formData.append('action', 'update_profile');

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> پاشەکەوتکردنی گۆڕانکارییەکان';
                if (data.success) {
                    showToast(data.message);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.message || 'هەڵەیەک ڕوویدا', true);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> پاشەکەوتکردنی گۆڕانکارییەکان';
                showToast('هەڵەی پەیوەندی بە سێرڤەر', true);
            });
        }

        // Add child modal handlers
        function openAddChildModal() {
            document.getElementById('addChildModal').style.display = 'flex';
        }
        function closeAddChildModal() {
            document.getElementById('addChildModal').style.display = 'none';
        }

        function submitChildRequest(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitChild');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> دەنێردرێت...';

            const form = document.getElementById('addChildForm');
            const formData = new FormData(form);
            formData.append('action', 'add_child_request');

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> ناردنی داواکاری بۆ لیژنە';
                if (data.success) {
                    closeAddChildModal();
                    showToast(data.message);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(data.message || 'هەڵەیەک ڕوویدا', true);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane"></i> ناردنی داواکاری بۆ لیژنە';
                showToast('هەڵەی پەیوەندی بە سێرڤەر', true);
            });
        }

        // ==========================================
        // EDIT CONFIRMED CHILD HANDLERS (FATHER EDIT)
        // ==========================================
        function openEditChildModal(child) {
            if (!child) return;
            document.getElementById('edit_child_id').value = child.id || '';
            document.getElementById('edit_child_first_name').value = child.first_name || child.full_name || '';

            const gender = child.gender || 'نێر';
            if (gender === 'مێ') {
                document.getElementById('edit_child_gender_female').checked = true;
            } else {
                document.getElementById('edit_child_gender_male').checked = true;
            }

            document.getElementById('edit_child_education').value = child.education || child.education_level || '';
            document.getElementById('edit_child_profession').value = child.profession || child.job || '';
            document.getElementById('edit_child_blood').value = child.blood_group || '';
            document.getElementById('edit_child_birth_date').value = child.birth_date || '';
            document.getElementById('edit_child_birth_place').value = child.birth_place || '';
            document.getElementById('edit_child_address').value = child.address || '';
            document.getElementById('edit_child_phone').value = child.phone || '';
            document.getElementById('edit_child_id_card').value = child.id_card_number || '';
            document.getElementById('edit_child_sec_code').value = child.security_code || '';

            // Child photo preview
            const previewImg = document.getElementById('edit_child_avatar_preview');
            if (child.profile_image && child.profile_image.trim() !== '') {
                previewImg.src = '../' + child.profile_image.replace(/^\/+/, '');
            } else {
                previewImg.src = 'default_user.svg';
            }

            document.getElementById('editChildModal').style.display = 'flex';
        }

        function closeEditChildModal() {
            document.getElementById('editChildModal').style.display = 'none';
        }

        function triggerChildPhotoUpload() {
            document.getElementById('edit_child_photo_file').click();
        }

        function uploadChildPhoto(input) {
            if (!input.files || !input.files[0]) return;
            const childId = document.getElementById('edit_child_id').value;
            if (!childId) {
                showToast('هەڵە: منداڵ دیاری نەکراوە', true);
                return;
            }

            const btn = document.getElementById('btnUploadChildPhoto');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> باردەکرێت...';

            const formData = new FormData();
            formData.append('action', 'upload_child_photo');
            formData.append('child_id', childId);
            formData.append('photo', input.files[0]);

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                input.value = '';
                if (data.success) {
                    document.getElementById('edit_child_avatar_preview').src = data.photo_url;
                    const cardAvatar = document.getElementById('child-avatar-display-' + childId);
                    if (cardAvatar) {
                        cardAvatar.src = data.photo_url;
                    }
                    showToast(data.message);
                } else {
                    showToast(data.message || 'هەڵە لە بارکردنی وێنە', true);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = originalText;
                input.value = '';
                showToast('هەڵەی پەیوەندی بە سێرڤەر', true);
            });
        }

        function submitEditChildProfile(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveChildProfile');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> پاشەکەوت دەکرێت...';

            const form = document.getElementById('editChildForm');
            const formData = new FormData(form);
            formData.append('action', 'update_child_profile');

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> پاشەکەوتکردنی زانیارییەکانی منداڵ';
                if (data.success) {
                    closeEditChildModal();
                    showToast(data.message);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1200);
                } else {
                    showToast(data.message || 'هەڵەیەک ڕوویدا', true);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> پاشەکەوتکردنی زانیارییەکانی منداڵ';
                showToast('هەڵەی پەیوەندی بە سێرڤەر', true);
            });
        }

        // ==========================================
        // EDIT / CANCEL PENDING REQUEST HANDLERS
        // ==========================================
        function openEditRequestModal(req) {
            if (!req) return;
            document.getElementById('edit_req_id').value = req.id || '';
            document.getElementById('edit_req_child_name').value = req.child_name || '';

            const gender = req.gender || 'نێر';
            if (gender === 'مێ') {
                document.getElementById('edit_req_gender_female').checked = true;
            } else {
                document.getElementById('edit_req_gender_male').checked = true;
            }

            document.getElementById('edit_req_birth_date').value = req.birth_date || '';
            document.getElementById('edit_req_notes').value = req.notes || '';

            document.getElementById('editRequestModal').style.display = 'flex';
        }

        function closeEditRequestModal() {
            document.getElementById('editRequestModal').style.display = 'none';
        }

        function submitEditRequest(e) {
            e.preventDefault();
            const btn = document.getElementById('btnUpdateReq');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> پاشەکەوت دەکرێت...';

            const form = document.getElementById('editRequestForm');
            const formData = new FormData(form);
            formData.append('action', 'update_child_request');

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> پاشەکەوتکردنی گۆڕانکاری داواکاری';
                if (data.success) {
                    closeEditRequestModal();
                    showToast(data.message);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1200);
                } else {
                    showToast(data.message || 'هەڵەیەک ڕوویدا', true);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> پاشەکەوتکردنی گۆڕانکاری داواکاری';
                showToast('هەڵەی پەیوەندی بە سێرڤەر', true);
            });
        }

        function cancelChildRequest(reqId) {
            if (!confirm('ئایا دڵنیایت لە هەڵوەشاندنەوەی ئەم داواکارییە؟')) return;

            const formData = new FormData();
            formData.append('action', 'cancel_request');
            formData.append('request_id', reqId);

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1200);
                } else {
                    showToast(data.message || 'نەتوانرا هەڵبوەشێنرێتەوە', true);
                }
            })
            .catch(err => {
                showToast('هەڵەی پەیوەندی بە سێرڤەر', true);
            });
        }
    </script>
</body>
</html>
